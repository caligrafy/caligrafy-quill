# Recipe: face login with liveness, anti-replay and passkeys

> Written from the framework source (`public/js/services/detect.js` `FaceDetector.liveness()`, `framework/core/FaceLogin.php`,
> `framework/core/Passkey.php`, `public/js/services/passkey.js`, `framework/core/Auth.php`, `encryptDecrypt()`) and tested
> end to end by the QA fixture `qa/fixtures/facelogin` (same controllers, tables and client as below).
> Related gotchas: G16 (`Vue.markRaw`), G42 (face-api, liveness, face prints, passkeys), G47 (controller names), G48 (error status codes).

**Security first - tell the user.**
- Face login runs in the browser. The liveness check (blink, head turns, smile, in an order chosen by the server) stops
  photos; the single-use nonce and the rule "prints must differ and never have been used before" stop replayed
  requests. It is **not** proof against a determined attacker: a deepfake video fed through a virtual camera, or a
  modified page that sends made-up prints, can pass. Browser-side checks raise the bar, they do not prove a live person.
- The strong factor is a **passkey** (WebAuthn, `Caligrafy\Passkey`): a key pair kept by the device, bound to the
  domain (phishing-proof), unlocked with the device's own biometrics or PIN.
- Recommended: face login as a convenience; accounts with a passkey confirm with it after the face (`step-up`,
  2 factors) or log in with the passkey alone. Sensitive accounts (admin, payments, health data): require a passkey.

Policies (`FaceLoginController::PASSKEY_POLICY`, for accounts that registered a passkey):

| Policy | Face login | Passkey login | Use for |
|---|---|---|---|
| `face` | logs in alone | logs in alone | low-risk apps (convenience only) |
| `step-up` (default) | face, then the passkey | logs in alone | most apps: the face is a hint, the passkey proves it |
| `passkey` | refused (403) | logs in alone | sensitive accounts |

Accounts without a passkey log in with the face alone under every policy. To protect them, make a passkey a
condition of enrolment (for example: enrol() refuses accounts with `permissions >= 1` until they have a passkey).

End result: a `facedetect` client app (`/caligrafy-quill:new-app facedetect facelogin`) where a user who logged in with
their password enrols their face (with a liveness check) and adds a passkey, then logs in with the face, the passkey, or both.

## 1. Tables

Face login and passkeys belong to the users of the authentication module (`users`, `User`, 9.1). Enrolment needs a
password login first, so nobody can create an account with a face. Run with `/caligrafy-quill:db`.
```sql
-- application/models/FaceLogin/create_db.sql
/* Face login and passkeys for the users of the Caligrafy authentication module (users table).
 * Safe to run more than once. MySQL runs in UTC: write and compare times with gmdate() in PHP.
 */
CREATE TABLE IF NOT EXISTS face_prints (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  descriptor TEXT NOT NULL,                      -- encryptDecrypt('encrypt', json) with APP_KEY, never plain
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_face_prints_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- hashes of the prints of accepted logins: the same prints sent again with a new nonce are refused
CREATE TABLE IF NOT EXISTS face_login_used_prints (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  print_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_face_login_used_prints_hash UNIQUE (print_hash),
  CONSTRAINT fk_face_login_used_prints_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- failures live in their own table, NOT on users: Auth::authorized() compares the whole users row with the copy in
-- the session, so any later change to the row (a failed attempt by someone else) would log the user out
CREATE TABLE IF NOT EXISTS face_login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  attempt_key VARCHAR(191) NOT NULL,             -- client IP + username ('*' for 1:N)
  failures INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_face_login_attempts_key UNIQUE (attempt_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- passkeys (Caligrafy\Passkey)
CREATE TABLE IF NOT EXISTS passkeys (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  credential_id VARCHAR(255) NOT NULL,           -- base64url
  public_key TEXT NOT NULL,                      -- PEM
  sign_count INT UNSIGNED NOT NULL DEFAULT 0,
  name VARCHAR(64) NOT NULL DEFAULT 'Passkey',
  transports VARCHAR(255) NULL,
  aaguid CHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  CONSTRAINT uq_passkeys_credential_id UNIQUE (credential_id),
  CONSTRAINT fk_passkeys_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
The MySQL container runs in UTC, so `CURRENT_TIMESTAMP` defaults are UTC: write times from PHP with
`gmdate('Y-m-d H:i:s', ...)` and compare with `gmdate()` too.

## 2. Routes

```php
// application/config/routing/web.php - Auth::authentication('User', 'users') and the Auth routes (/login, /register) enabled
// the page: /apps/facelogin (generic route)
Route::post('/facelogin/start', 'FaceLoginController@start');
Route::post('/facelogin/enroll', 'FaceLoginController@enroll');
Route::post('/facelogin/login', 'FaceLoginController@login');
Route::get('/facelogin/me', 'FaceLoginController@me');
Route::post('/facelogin/logout', 'FaceLoginController@signout');
Route::post('/passkey/register/options', 'PasskeyController@registerOptions');
Route::post('/passkey/register', 'PasskeyController@register');
Route::post('/passkey/login/options', 'PasskeyController@loginOptions');
Route::post('/passkey/login', 'PasskeyController@login');
```
Passkeys: set `PASSKEY_RP_ID` (the domain, ex `example.com`) and `PASSKEY_ORIGIN` (ex `https://www.example.com`) in
`.env` for production. Without them the request host is used (fine on `http://localhost`). Passkeys and the camera
need https, except on localhost.

## 3. Flow

1. `POST /facelogin/start` -> `FaceLogin::challenge($username)`: a 64-hex nonce and 3 of `blink`, `turn-left`,
   `turn-right`, `smile` in a random order, kept in the session with their expiry (60 s). A new start replaces the old one.
2. Browser: `await detector.liveness({ challenges })` prompts each challenge, follows the eye aspect ratio (blink), the
   head yaw (turns, the person's own left and right) and the `happy` expression (smile) frame by frame, and captures
   4 face prints while the person moves.
3. `POST /facelogin/login { nonce, liveness, prints }` -> `FaceLogin::verify()`. The nonce is consumed (single use,
   even when the check fails; unknown, expired, from another session or for another username: 401). The liveness result
   must list the requested challenges in order (422). The 3-12 prints must be the same face (all within 0.6) and
   **not identical** (closer than 0.001 = a replayed static print: 422). `FaceLogin::match()` then finds the account
   (mean distance < 0.5, none >= 0.6); the hashes of the prints must never have been used before (an exact replay with
   a fresh nonce: 401); every failure counts for the lockout (5 -> 60 s, 429).
4. Accounts with a passkey (`step-up`): the answer is `{ step: 'passkey', options }`; the browser calls
   `PasskeyClient.get(options)` and `POST /passkey/login`; only then is the user authorized (`factors: ['face', 'passkey']`).

## 4. Controllers

```php
// application/controllers/FaceLogin/FaceLoginController.php
use Caligrafy\Controller;
use Caligrafy\Database;
use Caligrafy\Auth;
use Caligrafy\FaceLogin;
use Caligrafy\FaceLoginException;
use Caligrafy\Passkey;

/**
 * Face login with a liveness check (FaceDetector.liveness() + Caligrafy\FaceLogin) for the users of the
 * authentication module. Enrolment needs a logged-in user (password first).
 */
class FaceLoginController extends Controller {
    const MAX_FAILURES = 5;
    const LOCK_SECONDS = 60;
    // what face login does for accounts that have a passkey:
    //   'face'    face alone logs in (convenience)
    //   'step-up' face, then the passkey (2 factors) - recommended for sensitive accounts
    //   'passkey' face login refused: the account logs in with its passkey only
    const PASSKEY_POLICY = 'step-up';
    const PENDING = '__face_pending';      // face checked, passkey expected

    private function fail($code, $message) { http_response_code($code); return api(['error' => $message]); }

    private function username($p) {
        $username = trim((string)($p['username'] ?? ''));
        return $username === '' || preg_match('/^[A-Za-z0-9._@-]{1,100}$/', $username) ? $username : null;
    }

    private function enrolled($userId = null) {    // [['user' => id, 'descriptor' => [128]], ...]
        $sql = 'SELECT user_id, descriptor FROM face_prints' . ($userId ? ' WHERE user_id = ?' : '');
        $rows = Database::toArray(Database::execute($sql, $userId ? [$userId] : [])) ?: [];
        return array_map(fn($r) => ['user' => (int)$r['user_id'], 'descriptor' => json_decode(encryptDecrypt('decrypt', $r['descriptor']), true)], $rows);
    }

    // POST /facelogin/start { username? } -> { nonce, challenges, expires_in }
    public function start() {
        $p = $this->request->parameters();
        if (!empty($p['enroll'])) {                               // enrolment: the logged-in account
            return authorized() ? api(FaceLogin::challenge(user()->username)) : $this->fail(401, 'Log in with your password first');
        }
        $username = $this->username($p);
        if ($username === null) { return $this->fail(422, 'Invalid username'); }
        return api(FaceLogin::challenge($username));
    }

    // POST /facelogin/enroll { nonce, liveness, prints } - logged-in users only
    public function enroll() {
        if (!authorized()) { return $this->fail(401, 'Log in with your password first'); }
        $user = user();
        try {
            $prints = FaceLogin::verify($this->request->parameters(), $user->username);
        } catch (FaceLoginException $e) {
            return $this->fail($e->getCode(), $e->getMessage());
        }
        if (FaceLogin::enrolledElsewhere($prints, $this->enrolled(), $user->id) !== null) {
            return $this->fail(409, 'This face is already enrolled on another account');
        }
        Database::execute('DELETE FROM face_prints WHERE user_id = ?', [$user->id]);
        foreach (array_slice($prints, 0, 5) as $print) {
            Database::execute('INSERT INTO face_prints (user_id, descriptor) VALUES (?, ?)', [$user->id, encryptDecrypt('encrypt', json_encode($print))]);
        }
        http_response_code(201);
        return api(['enrolled' => min(5, count($prints))]);
    }

    // POST /facelogin/login { username?, nonce, liveness, prints }
    public function login() {
        $p = $this->request->parameters();
        $username = $this->username($p);
        if ($username === null) { return $this->fail(422, 'Invalid username'); }
        $key = ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($username !== '' ? strtolower($username) : '*');
        $now = gmdate('Y-m-d H:i:s');
        $attempt = Database::toArray(Database::execute('SELECT * FROM face_login_attempts WHERE attempt_key = ?', [$key]))[0] ?? null;
        if ($attempt && $attempt['locked_until'] && $attempt['locked_until'] > $now) {
            return $this->fail(429, 'Too many failed attempts: wait a minute');
        }
        $failed = function ($code, $message) use ($attempt, $key, $now) {
            $failures = ($attempt && (!$attempt['locked_until'] || $attempt['locked_until'] <= $now) ? (int)$attempt['failures'] : 0) + 1;
            $lock = $failures >= self::MAX_FAILURES ? gmdate('Y-m-d H:i:s', time() + self::LOCK_SECONDS) : null;
            Database::execute('INSERT INTO face_login_attempts (attempt_key, failures, locked_until, modified_at) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE failures = VALUES(failures), locked_until = VALUES(locked_until), modified_at = VALUES(modified_at)',
                              [$key, $lock ? 0 : $failures, $lock, $now]);
            return $this->fail($code, $message);
        };

        try {
            $prints = FaceLogin::verify($p, $username);           // nonce (single use), liveness, prints
        } catch (FaceLoginException $e) {
            return $failed($e->getCode(), $e->getMessage());
        }
        $users = new User('users');
        $target = $username !== '' ? $users->findByUsername($username) : null;
        $match = $username === '' || $target ? FaceLogin::match($prints, $this->enrolled($target ? (int)$target->id : null)) : null;
        if (!$match) {
            return $failed(401, 'Face not recognized');           // same message whether or not the username exists
        }
        $hashes = array_map([FaceLogin::class, 'printHash'], $prints);
        $seen = Database::toArray(Database::execute('SELECT COUNT(*) AS n FROM face_login_used_prints WHERE print_hash IN (' . implode(',', array_fill(0, count($hashes), '?')) . ')', $hashes));
        if ((int)($seen[0]['n'] ?? 0) > 0) {
            return $failed(401, 'These face prints were already used: replay refused');
        }
        foreach ($hashes as $h) {
            Database::execute('INSERT IGNORE INTO face_login_used_prints (user_id, print_hash) VALUES (?, ?)', [$match['user'], $h]);
        }
        Database::execute('DELETE FROM face_login_attempts WHERE attempt_key = ?', [$key]);

        $user = $users->find($match['user']);
        $passkey = new Passkey();
        if (self::PASSKEY_POLICY !== 'face' && $passkey->credentials($user->id)) {
            if (self::PASSKEY_POLICY === 'passkey') { return $this->fail(403, 'This account logs in with its passkey'); }
            $_SESSION[self::PENDING] = ['user' => (string)$user->id, 'expires' => time() + 120];
            return api(['step' => 'passkey', 'options' => $passkey->authenticationOptions($user->id)]);
        }
        session_regenerate_id(true);                              // new session id on every login (fixation)
        Auth::authorize($user);
        return api(['user' => ['username' => $user->username], 'factors' => ['face']]);
    }

    public function me() { return authorized() ? api(['user' => ['username' => user()->username ?? null]]) : $this->fail(401, 'Not logged in'); }
    public function signout() { Auth::unauthorize(); session_regenerate_id(true); return api(['loggedOut' => true]); }
}
```
```php
// application/controllers/FaceLogin/PasskeyController.php
use Caligrafy\Controller;
use Caligrafy\Database;
use Caligrafy\Auth;
use Caligrafy\Passkey;
use Caligrafy\PasskeyException;

/**
 * Passkeys (Caligrafy\Passkey): registration for logged-in users, login alone or after face login (step-up)
 */
class PasskeyController extends Controller {

    private function fail($code, $message) { http_response_code($code); return api(['error' => $message]); }

    // the user whose face was just recognized, waiting for the passkey (FaceLoginController::PENDING)
    private function pending() {
        $p = $_SESSION[FaceLoginController::PENDING] ?? null;
        return is_array($p) && $p['expires'] >= time() ? $p['user'] : null;
    }

    // POST /passkey/register/options
    public function registerOptions() {
        if (!authorized()) { return $this->fail(401, 'Log in first'); }
        $user = user();
        return api((new Passkey())->registrationOptions($user->id, $user->username));
    }

    // POST /passkey/register { id, clientDataJSON, attestationObject, transports, name? }
    public function register() {
        if (!authorized()) { return $this->fail(401, 'Log in first'); }
        $p = $this->request->parameters();
        try {
            $credential = (new Passkey())->register($p, user()->id, $p['name'] ?? null);
        } catch (PasskeyException $e) {
            return $this->fail($e->getCode(), $e->getMessage());
        }
        http_response_code(201);
        return api(['passkey' => ['name' => $credential['name']]]);
    }

    // POST /passkey/login/options: after face login only this user's passkeys, else any passkey of the site
    public function loginOptions() {
        return api((new Passkey())->authenticationOptions($this->pending()));
    }

    // POST /passkey/login { id, clientDataJSON, authenticatorData, signature, userHandle }
    public function login() {
        $pending = $this->pending();
        unset($_SESSION[FaceLoginController::PENDING]);
        try {
            $userId = (new Passkey())->authenticate($this->request->parameters());
        } catch (PasskeyException $e) {
            return $this->fail($e->getCode(), $e->getMessage());
        }
        if ($pending !== null && $pending !== $userId) { return $this->fail(401, 'This passkey belongs to another account'); }
        $user = (new User('users'))->find($userId);
        if (!$user) { return $this->fail(401, 'Unknown account'); }
        session_regenerate_id(true);
        Auth::authorize($user);
        return api(['user' => ['username' => $user->username], 'factors' => $pending !== null ? ['face', 'passkey'] : ['passkey']]);
    }
}
```
Notes:
- The descriptors are encrypted, so SQL cannot compare them: 1:N login decrypts every print, fine for hundreds of
  users. For more, require the username (1:1).
- Thresholds are static properties (`FaceLogin::$match` 0.5, `$spread` 0.6, `$distinct` 0.001, `$ttl` 60, `$count` 3,
  `$minPrints` 3, `$maxPrints` 12). Never loosen 0.5.
- Rotating `APP_KEY` makes every stored print unreadable: users must enrol again (passkeys keep working).
- Never return descriptors or public keys from a route, never log them.
- `Passkey` keeps the WebAuthn challenge in the session (single use, `$timeout` 120 s) and checks the exact origin;
  `userVerification` and `residentKey` are `required` (the device asks for its PIN or biometrics; login without a username).

## 5. Client (facedetect app)

`public/facelogin/index.php`: the facedetect scaffold page with `passkey.js` loaded after `detect.js`:
```html
<script src="<?php echo APP_SERVICE_ROOT.'face-api.min.js'; ?>"></script>
<script src="<?php echo APP_SERVICE_ROOT.'detect.js'; ?>"></script>
<script src="<?php echo APP_SERVICE_ROOT.'passkey.js'; ?>"></script>
```
```html
<div id="app">
    <header><h1>Face Login</h1></header>
    <section id="detector">
        <video id="detection" width="480" height="360" autoplay muted playsinline></video>
    </section>
    <section class="controls container">
        <p class="error" v-if="cameraError">{{ cameraError }}</p>
        <p id="prompt" class="default-text">{{ prompt }}</p>
        <input class="form-control" v-model="username" placeholder="Username (optional)">
        <button id="login" class="btn btn-primary" @click="login()" :disabled="busy">Log in with my face</button>
        <button id="passkeyLogin" class="btn btn-primary" v-if="passkeys" @click="passkeyLogin()" :disabled="busy">Log in with a passkey</button>
        <button id="enroll" class="btn btn-primary" @click="enroll()" :disabled="busy">Enrol my face</button>
        <button id="passkeyRegister" class="btn btn-primary" v-if="passkeys" @click="passkeyRegister()" :disabled="busy">Add a passkey</button>
        <p id="status">{{ status }}</p>
        <p id="error" class="error" v-if="error">{{ error }}</p>
    </section>
</div>
```
```js
// public/facelogin/scripts/main.js
// Face login with liveness + passkeys (recipe: claude-plugin/skills/caligrafy/reference/recipes-face-login.md)
const PROMPTS = { 'blink': 'Blink', 'turn-left': 'Turn your head to your left', 'turn-right': 'Turn your head to your right', 'smile': 'Smile' };

const app = Vue.createApp({
  data() {
    return {
      env: env, detector: null, cameraError: '', username: '', prompt: 'Look at the camera', status: '', error: '', busy: false,
      passkeys: PasskeyClient.supported(),
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
      } catch (e) {
        this.cameraError = e.name === 'NotAllowedError' ? 'Camera access is blocked: allow it in the browser settings'
                         : e.name === 'NotFoundError' ? 'No camera found' : e.message;
      }
    },
    post(path, body) {
      return axios.post(this.env.home + path, body, this.config).then(({ data }) => {
        if (typeof data === 'string') throw new Error(data);         // [ERROR ...] with HTTP 200 (G48)
        return data;
      });
    },
    // server challenge -> liveness on the camera -> { nonce, liveness, prints } for the server
    async check(start) {
      const challenge = await this.post('facelogin/start', start);
      const result = await this.detector.liveness({
        challenges: challenge.challenges,
        timeout: Math.min(20000, challenge.expires_in * 1000 - 2000),
        onProgress: (p) => { this.prompt = p.challenge ? `${p.index + 1}/${p.total}: ${PROMPTS[p.challenge]}` : 'Done'; }
      });
      if (!result.passed) throw new Error(result.reason);
      return { nonce: challenge.nonce, liveness: { passed: true, challenges: result.challenges }, prints: result.prints.map(p => p.descriptor) };
    },
    async run(task) {
      this.busy = true; this.error = ''; this.status = '';
      try { await task(); } catch (e) { this.error = e.response?.data?.error || e.message; } finally { this.busy = false; this.prompt = 'Look at the camera'; }
    },
    enroll() {
      return this.run(async () => {
        const data = await this.post('facelogin/enroll', await this.check({ enroll: true }));
        this.status = `Face enrolled (${data.enrolled} prints)`;
      });
    },
    login() {
      return this.run(async () => {
        const username = this.username || undefined;
        const data = await this.post('facelogin/login', Object.assign({ username: username }, await this.check({ username: username })));
        if (data.step === 'passkey') {                                  // step-up: this account also needs its passkey
          this.prompt = 'Confirm with your passkey';
          return this.finishPasskey(data.options);
        }
        this.status = 'Welcome ' + data.user.username;
      });
    },
    async finishPasskey(options) {
      const data = await this.post('passkey/login', await PasskeyClient.get(options));
      this.status = `Welcome ${data.user.username} (${data.factors.join(' + ')})`;
    },
    passkeyLogin() {
      return this.run(async () => this.finishPasskey(await this.post('passkey/login/options', {})));
    },
    passkeyRegister() {
      return this.run(async () => {
        const options = await this.post('passkey/register/options', {});
        await this.post('passkey/register', Object.assign({ name: 'This device' }, await PasskeyClient.create(options)));
        this.status = 'Passkey added';
      });
    }
  },
  mounted() { this.startCamera(); },
  beforeUnmount() { if (this.detector) this.detector.stopStream(); this.detector = null; }
});
app.mount('#app');
```
- `detector.liveness()` resolves (it does not reject) with `{ passed, reason, challenges, prints, frames, duration }`;
  only a missing media element or models reject. `onProgress({ challenge, index, total, passed })` drives the prompt.
- Keep `@click="login()"` with parentheses: without them Vue passes the click event as the first argument.
- `PasskeyClient.create()` rejects with `InvalidStateError` when the device already has a passkey for this account and
  with `NotAllowedError` when the user cancels: show a message, do not retry in a loop.

## Testing without a camera or an authenticator

- Fake camera: draw an image into a canvas and return its stream before the detector starts:
  ```js
  const c = document.createElement('canvas'); c.width = 640; c.height = 480;
  const img = new Image(); img.src = '/public/facelogin/test-face.png';
  img.onload = () => setInterval(() => c.getContext('2d').drawImage(img, 0, 0, 640, 480), 50);
  navigator.mediaDevices.getUserMedia = async () => c.captureStream(15);
  ```
  `faceprint()` works on it; `liveness()` must **fail** (a photo does not blink or turn): that is the photo attack test.
- Passkeys: Chrome DevTools > More tools > WebAuthn > "Enable virtual authenticator environment" (internal transport,
  resident keys, user verification) registers and logs in without a device. On the server, the QA suite uses a
  software authenticator (`qa/unit/fixtures/SoftAuthenticator.php`, EC P-256, "none" attestation).
- Only real devices show: the blink and turn thresholds on real faces and cameras (tune `thresholds`), Touch ID /
  Windows Hello / Android prompts, synced passkeys (sign count 0) and attestation formats other than "none".

## Checklist
- [ ] Controllers not named after an example controller unless namespaced (G47); `php -l`; SQL run with the db skill.
- [ ] Enrolment only for a logged-in user, with a liveness check; a face already enrolled on another account refused.
- [ ] Login: `FaceLogin::verify()` (nonce, liveness, prints), then `FaceLogin::match()`, used-print hashes, lockout outside `users`.
- [ ] Prints encrypted with `encryptDecrypt` (APP_KEY), compared on the server, never returned.
- [ ] Passkey policy chosen and explained; `PASSKEY_RP_ID` / `PASSKEY_ORIGIN` set in production; https.
- [ ] `session_regenerate_id(true)` + `Auth::authorize()` only after every required factor.
- [ ] Client: `Vue.markRaw`, `autoStart: false` + `startStream()` errors, challenges from the server, `@click="login()"`.
- [ ] User told: browser liveness raises the bar but is not proof; passkeys for sensitive accounts.
