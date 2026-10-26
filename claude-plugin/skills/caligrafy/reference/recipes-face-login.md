# Recipe: face login with FaceDetector face prints

> Written from the framework source (`public/js/services/detect.js`, `framework/core/Auth.php`, `encryptDecrypt()`
> in `framework/helpers/corehelpers.php`) and a working proof app. Related gotchas: G16 (`Vue.markRaw`),
> G42 (face-api, `startStream()`, `faceprint()`), G47 (controller names), G48 (error status codes).

**Security first.** There is no liveness check: a photo or a video of the user can pass, and a stolen descriptor
can be replayed by anyone who can call the API. Use face login as a convenience (with a password fallback) or as a
second factor, never as the only protection of sensitive data. Say so to the user when you build it.

End result: a `facedetect` client app (`/caligrafy-quill:new-app facedetect facelogin`) with an enrol screen and a
login screen, and a `FaceLoginController` that stores encrypted prints and compares them on the server.

## 1. Tables

```sql
-- application/models/FaceLogin/create_db.sql (run with /caligrafy-quill:db)
CREATE TABLE IF NOT EXISTS face_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  permissions INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_face_users_username UNIQUE (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS face_prints (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  faceuser_id INT UNSIGNED NOT NULL,
  descriptor TEXT NOT NULL,                      -- encryptDecrypt('encrypt', json) with APP_KEY, never plain
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_face_prints_faceuser_id FOREIGN KEY (faceuser_id) REFERENCES face_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- failures live in their own table, NOT on face_users: Auth::authorized() compares the whole face_users row with
-- the copy in the session, so any later change to the row (a failed attempt by someone else) logs the user out
CREATE TABLE IF NOT EXISTS face_login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  attempt_key VARCHAR(191) NOT NULL,             -- client IP + username ('*' for 1:N)
  failures INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_face_login_attempts_key UNIQUE (attempt_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
The MySQL container runs in UTC, so `CURRENT_TIMESTAMP` defaults are UTC: write times from PHP with
`gmdate('Y-m-d H:i:s', ...)` and compare with `gmdate()` too (`date()` / `now()` use the PHP time zone and the
lockout would be off by hours).

## 2. Models and authentication

```php
// application/models/FaceLogin/FaceUser.php
use Caligrafy\Model;
use Caligrafy\Database;
class FaceUser extends Model {
    public $id; public $username; public $permissions; public $created_at;
    public function findByUsername($username) {
        $rows = Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE username = ? LIMIT 1', [(string)$username]));
        return !empty($rows[0]) ? $this->arrayToModel($rows[0]) : null;
    }
}
```
```php
// application/config/routing/web.php - one authentication model per app: replace Auth::authentication('User', 'users')
Auth::authentication('FaceUser', 'face_users');
Route::get('/facelogin', function() { return view('Client/app', ['appName' => 'facelogin', 'app' => 'index', 'authorized' => authorized()]); });
Route::post('/facelogin/enroll', 'FaceLoginController@enroll');
Route::post('/facelogin/login', 'FaceLoginController@login');
Route::get('/facelogin/me', 'FaceLoginController@me');
Route::post('/facelogin/logout', 'FaceLoginController@signout');
```
If the project also has password users, keep `User` as the authentication model and make face login a second step
after the password, or run face login in its own project: `Auth::authentication()` holds a single model.

## 3. Controller

```php
// application/controllers/FaceLogin/FaceLoginController.php
use Caligrafy\Controller;
use Caligrafy\Database;
use Caligrafy\Auth;

class FaceLoginController extends Controller {
    const MATCH = 0.5;          // same person: euclidean distance < 0.5 (G42)
    const SAMPLE_SPREAD = 0.6;  // enrolment samples must all be within 0.6 of each other
    const MAX_FAILURES = 5;
    const LOCK_SECONDS = 60;

    public function index() { return api(['authorized' => authorized()]); }
    private function fail($code, $message) { http_response_code($code); return api(['error' => $message]); }

    private static function descriptor($d) {          // 128 finite numbers or null
        if (!is_array($d) || count($d) !== 128) { return null; }
        $out = [];
        foreach ($d as $v) { if (!is_numeric($v) || !is_finite((float)$v)) { return null; } $out[] = (float)$v; }
        return $out;
    }
    private static function distance($a, $b) {
        $s = 0.0; for ($i = 0; $i < 128; $i++) { $s += ($a[$i] - $b[$i]) ** 2; } return sqrt($s);
    }
    private function prints($userId = null) {        // [['faceuser_id' => .., 'descriptor' => [128]], ...]
        $sql = 'SELECT faceuser_id, descriptor FROM face_prints' . ($userId ? ' WHERE faceuser_id = ?' : '');
        $rows = Database::toArray(Database::execute($sql, $userId ? [$userId] : []));
        return array_map(fn($r) => ['faceuser_id' => (int)$r['faceuser_id'],
                                    'descriptor' => json_decode(encryptDecrypt('decrypt', $r['descriptor']), true)], $rows);
    }

    public function enroll() {
        $p = $this->request->parameters();
        $username = trim((string)($p['username'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) { return $this->fail(422, 'Username: 3-64 letters, digits, . _ -'); }
        $samples = array_map([self::class, 'descriptor'], is_array($p['samples'] ?? null) ? $p['samples'] : []);
        if (count($samples) < 3 || count($samples) > 5 || in_array(null, $samples, true)) { return $this->fail(422, 'Send 3 to 5 face samples'); }
        foreach ($samples as $i => $a) { foreach (array_slice($samples, $i + 1) as $b) {
            if (self::distance($a, $b) >= self::SAMPLE_SPREAD) { return $this->fail(422, 'The samples do not look like the same face: try again with steady light'); }
        } }
        $users = new FaceUser('face_users');
        if ($users->findByUsername($username)) { return $this->fail(409, 'This username is taken'); }
        foreach ($this->prints() as $known) { foreach ($samples as $s) {           // one person, one account
            if (self::distance($known['descriptor'], $s) < self::MATCH) { return $this->fail(409, 'This face is already enrolled'); }
        } }
        Database::execute('INSERT INTO face_users (username) VALUES (?)', [$username]);
        $id = (int)Database::lastInsertId();
        foreach ($samples as $s) {
            Database::execute('INSERT INTO face_prints (faceuser_id, descriptor) VALUES (?, ?)', [$id, encryptDecrypt('encrypt', json_encode($s))]);
        }
        http_response_code(201);
        return api(['enrolled' => $username]);
    }

    public function login() {
        $p = $this->request->parameters();
        $username = trim((string)($p['username'] ?? ''));             // optional: 1:1 when given, else 1:N
        $probe = self::descriptor($p['descriptor'] ?? null);
        if (!$probe) { return $this->fail(422, 'Invalid face print'); }
        $key = ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($username !== '' ? strtolower($username) : '*');
        $now = gmdate('Y-m-d H:i:s');

        $attempt = Database::toArray(Database::execute('SELECT * FROM face_login_attempts WHERE attempt_key = ?', [$key]))[0] ?? null;
        if ($attempt && $attempt['locked_until'] && $attempt['locked_until'] > $now) {
            return $this->fail(429, 'Too many failed attempts: wait a minute');
        }

        $users = new FaceUser('face_users');
        $target = $username !== '' ? $users->findByUsername($username) : null;
        $best = null; $bestDistance = INF;
        if ($username === '' || $target) {
            foreach ($this->prints($target ? (int)$target->id : null) as $known) {
                $d = self::distance($known['descriptor'], $probe);
                if ($d < $bestDistance) { $bestDistance = $d; $best = $known['faceuser_id']; }
            }
        }
        if ($best === null || $bestDistance >= self::MATCH) {
            $failures = ($attempt && (!$attempt['locked_until'] || $attempt['locked_until'] <= $now) ? (int)$attempt['failures'] : 0) + 1;
            $lock = $failures >= self::MAX_FAILURES ? gmdate('Y-m-d H:i:s', time() + self::LOCK_SECONDS) : null;
            Database::execute('INSERT INTO face_login_attempts (attempt_key, failures, locked_until, modified_at) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE failures = VALUES(failures), locked_until = VALUES(locked_until), modified_at = VALUES(modified_at)',
                              [$key, $lock ? 0 : $failures, $lock, $now]);
            return $this->fail(401, 'Face not recognized');           // same message whether or not the username exists
        }

        Database::execute('DELETE FROM face_login_attempts WHERE attempt_key = ?', [$key]);
        session_regenerate_id(true);                                  // new session id on every login (fixation)
        $user = $users->find($best);
        Auth::authorize($user);                                       // or the authorize($user) helper
        return api(['user' => ['username' => $user->username]]);
    }

    public function me() { return authorized() ? api(['user' => ['username' => user()->username ?? null]]) : $this->fail(401, 'Not logged in'); }
    public function signout() { Auth::unauthorize(); session_regenerate_id(true); return api(['loggedOut' => true]); }
}
```
Notes:
- The descriptors are encrypted, so SQL cannot compare them: 1:N login decrypts every print, which is fine for
  hundreds of users. For more, require the username (1:1) or store the prints unencrypted in a protected table.
- Thresholds: same person usually < 0.5, different people > 0.6 (G42). Do not loosen 0.5 to make login "easier".
- Rotating `APP_KEY` makes every stored print unreadable (and every Bearer key invalid): users must enrol again.
- Never return descriptors from any route, never log them.

## 4. Client (facedetect app)

Keep the scaffolded markup the detector needs (`#detector`, the media element `id="detection"`); see the new-app
skill for the Caligrafy black and white look.

```js
// public/facelogin/scripts/main.js
const app = Vue.createApp({
  data() {
    return {
      env: env, detector: null, cameraError: '', mode: 'login', username: '', samples: [], status: '', error: '',
      faceSeen: false, history: [], busy: false,
      config: { headers: { 'Authorization': 'Bearer ' + apiKey, 'Accept': 'application/json', 'Content-Type': 'application/json' }, timeout: 30000 }
    };
  },
  methods: {
    async startCamera() {
      // markRaw: a reactive FaceDetector breaks face-api (G16); autoStart false so camera errors reach us (G42)
      this.detector = Vue.markRaw(new FaceDetector('detection', { autoStart: false }));
      try {
        await this.detector.ready;
        await this.detector.startStream({ video: { facingMode: 'user' }, audio: false });
        this.preview();
      } catch (e) {
        this.cameraError = e.name === 'NotAllowedError' ? 'Camera access is blocked: allow it in the browser settings'
                         : e.name === 'NotFoundError' ? 'No camera found' : e.message;
      }
    },
    preview() {
      // cheap tiny-detector check, smoothed over the last 4 frames so the hint does not flicker
      const media = this.detector.resolveMedia();
      const tick = async () => {
        if (!this.detector) return;
        const found = !!(await faceapi.detectSingleFace(media, new faceapi.TinyFaceDetectorOptions()));
        this.history = [...this.history.slice(-3), found];
        this.faceSeen = this.history.filter(Boolean).length >= 3;
        setTimeout(tick, 250);
      };
      tick();
    },
    async capture() {
      // faceprint() fails on a blink or a turned head: retry over ~6 frames before giving up
      let last;
      for (let i = 0; i < 6; i++) {
        try { return (await this.detector.faceprint()).descriptor; }      // no argument: uses the detector's live video
        catch (e) { last = e; await new Promise(r => setTimeout(r, 150)); }
      }
      throw last;
    },
    async captureSamples() {              // @click="captureSamples()" - with @click="captureSamples" Vue passes the
      this.samples = []; this.error = ''; // click event, and passing it on to faceprint(media) breaks detection
      try {
        for (let i = 0; i < 4; i++) { this.status = `Sample ${i + 1}/4`; this.samples.push(await this.capture()); await new Promise(r => setTimeout(r, 400)); }
        const { data } = await axios.post(this.env.home + 'facelogin/enroll', { username: this.username, samples: this.samples }, this.config);
        if (typeof data === 'string') throw new Error(data);                // [ERROR ...] with HTTP 200 (G48)
        this.status = 'Enrolled: you can log in'; this.mode = 'login';
      } catch (e) { this.error = e.response?.data?.error || e.message; } finally { this.samples = []; }
    },
    async login() {
      this.busy = true; this.error = '';
      try {
        const descriptor = await this.capture();
        const { data } = await axios.post(this.env.home + 'facelogin/login', { username: this.username || undefined, descriptor }, this.config);
        if (typeof data === 'string') throw new Error(data);
        this.status = 'Welcome ' + data.user.username;
      } catch (e) { this.error = e.response?.data?.error || e.message; } finally { this.busy = false; }
    }
  },
  mounted() { this.startCamera(); },
  beforeUnmount() { if (this.detector) this.detector.stopStream(); this.detector = null; }
});
app.mount('#app');
```
```html
<video id="detection" autoplay muted playsinline></video>
<p class="default-text textcolor5">{{ faceSeen ? 'Face detected' : 'Look at the camera' }}</p>
<p class="error" v-if="cameraError">{{ cameraError }}</p>
<input class="form-control" v-model="username" placeholder="Username">
<button class="btn btn-primary" v-if="mode === 'enroll'" @click="captureSamples()" :disabled="!faceSeen">Enrol</button>
<button class="btn btn-primary" v-else @click="login()" :disabled="!faceSeen || busy">Log in</button>
<p class="error" v-if="error">{{ error }}</p>
```
- The camera needs `https` or `localhost` (`startStream()` rejects otherwise).
- `faceprint()` uses the SSD MobileNet detector (accurate, slower); the preview uses the tiny detector (fast). Both
  models ship in `public/resources/models`.

## Checklist
- [ ] Controller not named after a core controller (G47); `php -l` on each file; SQL run with the db skill.
- [ ] Prints encrypted with `encryptDecrypt` (APP_KEY), compared on the server, never returned.
- [ ] Enrolment: 3-5 samples within 0.6 of each other; face already enrolled (< 0.5) refused.
- [ ] Login: threshold 0.5, optional username, same error for unknown user and wrong face, 5 failures -> 60 s lock in UTC.
- [ ] `session_regenerate_id(true)` + `Auth::authorize()`; lockout data outside `face_users`.
- [ ] Client: `Vue.markRaw`, `autoStart: false` + `startStream()` errors, `@click="captureSamples()"`.
- [ ] User told: no liveness, prints replayable - convenience or second factor only.
