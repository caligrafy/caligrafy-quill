# Recipe: face login with liveness, anti-replay and passkeys

> Written from the framework source (`public/js/services/detect.js` `FaceDetector.liveness()`, `framework/core/FaceLogin.php`,
> `framework/core/Passkey.php`, `public/js/services/passkey.js`, `framework/core/Auth.php`, `encryptDecrypt()`) and tested
> end to end by the QA fixture `qa/fixtures/facelogin` (same controllers, tables and client as below).
> Related gotchas: G16 (`Vue.markRaw`), G42 (face-api, liveness, face prints, passkeys), G47 (controller names), G48 (error status codes).

**Security first - tell the user.**
- Face login runs in the browser. The liveness check (head turns and a smile, in an order chosen by the server) stops
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

End result: a `facedetect` client app (`/caligrafy-quill:new-app facedetect facelogin`) in the Caligrafy black and white
look, with two tabs. **Enrol your face**: the user signs in with their password (or creates an account) on the page,
scans their face (liveness check) and can add a passkey. **Sign in**: the camera shows an oval guide with placement hints;
when the face is in the oval the liveness check starts by itself, with the prompts on the video, then the face is
matched (and the passkey asked for, for accounts that have one). A passkey button is always offered.

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
// application/config/routing/web.php - Auth::authentication('User', 'users') enabled (user(), authorized())
// the page: /apps/facelogin (generic route)
Route::post('/facelogin/password', 'FaceLoginController@password');   // password sign-in from the page (before enrolment)
Route::post('/facelogin/register', 'FaceLoginController@register');   // new account from the page
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

0. Enrolment only: `POST /facelogin/password { username, password }` (or `/facelogin/register` for a new account, same
   rules as the authentication module's `/register`: letters and digits, 8 to 72 characters) signs the user in. Failures
   count for the same lockout as face login (5 -> 60 s, 429); an unknown username costs the same bcrypt work as a wrong
   password. Then `POST /facelogin/start { enroll: true }` + `/facelogin/enroll` as below.
1. `POST /facelogin/start` -> `FaceLogin::challenge($username)`: a 64-hex nonce and 2 of `turn-left`, `turn-right`,
   `smile` in a random order (6 possible sequences; `FaceLogin::$count`, 1 to 3), kept in the session with their expiry
   (60 s). A new start replaces the old one.
2. Browser: `await detector.liveness({ challenges })` prompts each challenge, follows the head yaw (turns, the person's
   own left and right) and the `happy` expression (smile) frame by frame, and captures 4 face prints while the person
   moves (it passes only with at least 3, the minimum of `verify()`).
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
 * authentication module. Enrolment: a password sign-in (or a new account) first, then a liveness check.
 * Login: a liveness check (the challenges chosen by start()), then the face is matched.
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

    // lockout after MAX_FAILURES (kept outside users): [locked?, function($code, $message) recording a failure]
    private function guard($key) {
        $now = gmdate('Y-m-d H:i:s');
        $attempt = Database::toArray(Database::execute('SELECT * FROM face_login_attempts WHERE attempt_key = ?', [$key]))[0] ?? null;
        $locked = $attempt && $attempt['locked_until'] && $attempt['locked_until'] > $now;
        $failed = function ($code, $message) use ($attempt, $key, $now) {
            $failures = ($attempt && (!$attempt['locked_until'] || $attempt['locked_until'] <= $now) ? (int)$attempt['failures'] : 0) + 1;
            $lock = $failures >= self::MAX_FAILURES ? gmdate('Y-m-d H:i:s', time() + self::LOCK_SECONDS) : null;
            Database::execute('INSERT INTO face_login_attempts (attempt_key, failures, locked_until, modified_at) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE failures = VALUES(failures), locked_until = VALUES(locked_until), modified_at = VALUES(modified_at)',
                              [$key, $lock ? 0 : $failures, $lock, $now]);
            return $this->fail($code, $message);
        };
        return [$locked, $failed];
    }

    private function signIn($user, $factors) {
        session_regenerate_id(true);                              // new session id on every login (fixation)
        Auth::authorize($user);
        return api(['user' => ['username' => $user->username], 'factors' => $factors, 'enrolled' => count($this->enrolled((int)$user->id)) > 0]);
    }

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

    // POST /facelogin/password { username, password } - password sign-in before enrolment
    public function password() {
        $p = $this->request->parameters();
        $username = $this->username($p);
        if (!$username) { return $this->fail(422, 'Enter a valid username'); }
        $key = 'pw|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower($username);
        [$locked, $failed] = $this->guard($key);
        if ($locked) { return $this->fail(429, 'Too many failed attempts: wait a minute'); }
        $user = (new User('users'))->findByUsername($username);
        if (!$user) {
            // same work as a real check so that response times do not reveal which usernames exist
            password_verify((string)($p['password'] ?? ''), '$2y$10$B0NLUgDUK3CbImwrVfa/FuOtSOKrb7DZTGG3USPSF4Comn4cy5RtG');
        }
        if (!$user || !$user->verifyPassword((string)($p['password'] ?? ''))) {
            return $failed(401, 'Invalid username or password');
        }
        Database::execute('DELETE FROM face_login_attempts WHERE attempt_key = ?', [$key]);
        return $this->signIn($user, ['password']);
    }

    // POST /facelogin/register { username, password } - creates the account and signs it in, ready to enrol
    // (the rules of the authentication module's /register: letters and digits, passwords of 8 to 72 characters)
    public function register() {
        $p = $this->request->parameters();
        $username = trim((string)($p['username'] ?? ''));
        $password = (string)($p['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{1,100}$/', $username)) { return $this->fail(422, 'Usernames use letters and digits (up to 100)'); }
        if (strlen($password) < 8 || strlen($password) > 72) { return $this->fail(422, 'Passwords need 8 to 72 characters'); }
        $users = new User('users');
        if ($users->findByUsername($username)) { return $this->fail(409, 'This username is taken'); }
        Database::execute('INSERT INTO users (username, passcode) VALUES (?, ?)', [$username, User::hashPassword($password)]);
        http_response_code(201);
        return $this->signIn($users->findByUsername($username), ['password']);
    }

    // POST /facelogin/login { username?, nonce, liveness, prints }
    public function login() {
        $p = $this->request->parameters();
        $username = $this->username($p);
        if ($username === null) { return $this->fail(422, 'Invalid username'); }
        $key = ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($username !== '' ? strtolower($username) : '*');
        [$locked, $failed] = $this->guard($key);
        if ($locked) { return $this->fail(429, 'Too many failed attempts: wait a minute'); }

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
        return $this->signIn($user, ['face']);
    }

    public function me() {
        if (!authorized()) { return $this->fail(401, 'Not logged in'); }
        return api(['user' => ['username' => user()->username ?? null], 'enrolled' => count($this->enrolled((int)user()->id)) > 0]);
    }
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
- Thresholds are static properties (`FaceLogin::$match` 0.5, `$spread` 0.6, `$distinct` 0.001, `$ttl` 60, `$count` 2,
  `$minPrints` 3, `$maxPrints` 12). Never loosen 0.5.
- Rotating `APP_KEY` makes every stored print unreadable: users must enrol again (passkeys keep working).
- Never return descriptors or public keys from a route, never log them.
- `Passkey` keeps the WebAuthn challenge in the session (single use, `$timeout` 120 s) and checks the exact origin;
  `userVerification` and `residentKey` are `required` (the device asks for its PIN or biometrics; login without a username).

## 5. Client (facedetect app)

Scaffold the app with `/caligrafy-quill:new-app facedetect facelogin`, then replace its three files with the ones
below (the facedetect scaffold's own look is not used). The page loads `theme.css` for the Caligrafy black and white
look and `passkey.js` after `detect.js`.
```php
<!-- public/facelogin/index.php -->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Face Login</title>

        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="<?php echo scripts('css'); ?>" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').end(request()->uriComponents).'/css/styles.css';?>">
    </head>

    <body>
        <div id="app" v-cloak>
            <header class="color0 caligrafy-header">
                <nav class="horizontal">
                    <h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy</h1>
                </nav>
            </header>

            <main class="facelogin-page">
                <div class="card dropshadow color0 facelogin-card">

                    <!-- flow switch -->
                    <div class="tabs" role="tablist">
                        <button role="tab" :class="{ active: tab === 'login' }" :aria-selected="tab === 'login'" @click="show('login')">Sign in</button>
                        <button role="tab" :class="{ active: tab === 'enroll' }" :aria-selected="tab === 'enroll'" @click="show('enroll')">Enrol your face</button>
                    </div>

                    <!-- camera, shared by both flows -->
                    <div class="viewport" v-show="cameraVisible" :class="viewportState">
                        <video id="detection" width="640" height="480" autoplay muted playsinline></video>
                        <div class="guide"></div>
                        <div class="overlay-prompt" v-if="overlay">{{ overlay }}</div>
                    </div>
                    <p class="error" v-if="cameraError && cameraVisible">{{ cameraError }}</p>

                    <!-- ============ SIGN IN ============ -->
                    <section v-if="tab === 'login'" class="flow">
                        <template v-if="!signedIn">
                            <h2 class="flow-title">Sign in with your face</h2>
                            <p class="flow-status" :class="loginState">{{ loginMessage }}</p>
                            <div class="flow-actions">
                                <button class="btn btn-primary" v-if="loginState === 'failed'" @click="scan()">Try again</button>
                                <button class="btn btn-secondary" v-if="passkeys" @click="passkeyLogin()" :disabled="busy">Use a passkey</button>
                            </div>
                            <p class="flow-note">No face enrolled yet? <a href="#" @click.prevent="show('enroll')">Enrol your face</a></p>
                        </template>

                        <div v-else class="done">
                            <div class="done-mark">&#10003;</div>
                            <h2 class="flow-title">Welcome, {{ user }}</h2>
                            <p class="flow-note">Signed in with {{ factors.join(' + ') }}</p>
                            <div class="flow-actions">
                                <button class="btn btn-secondary" @click="logout()" :disabled="busy">Log out</button>
                            </div>
                        </div>
                    </section>

                    <!-- ============ ENROL ============ -->
                    <section v-if="tab === 'enroll'" class="flow">
                        <ol class="steps">
                            <li :class="stepClass(1)"><span>1</span>Account</li>
                            <li :class="stepClass(2)"><span>2</span>Face scan</li>
                            <li :class="stepClass(3)"><span>3</span>Done</li>
                        </ol>

                        <!-- step 1: password sign-in or new account -->
                        <form v-if="enrollStep === 1" @submit.prevent="account()" class="account-form">
                            <h2 class="flow-title">{{ newAccount ? 'Create your account' : 'Confirm it is you' }}</h2>
                            <p class="flow-note">{{ newAccount ? 'Choose a username and a password.' : 'Sign in with your password before enrolling your face.' }}</p>
                            <label class="default-text textcolor5" for="username">Username</label>
                            <input id="username" class="form-control" v-model.trim="form.username" autocomplete="username" required>
                            <label class="default-text textcolor5" for="password">Password</label>
                            <input id="password" class="form-control" type="password" v-model="form.password" :autocomplete="newAccount ? 'new-password' : 'current-password'" :minlength="newAccount ? 8 : null" required>
                            <div class="flow-actions">
                                <button class="btn btn-primary" type="submit" :disabled="busy">{{ newAccount ? 'Create' : 'Continue' }}</button>
                            </div>
                            <p class="flow-note">
                                <a href="#" @click.prevent="newAccount = !newAccount; error = ''">{{ newAccount ? 'I already have an account' : 'Create a new account' }}</a>
                            </p>
                        </form>

                        <!-- step 2: liveness scan -->
                        <div v-if="enrollStep === 2">
                            <h2 class="flow-title">Scan your face</h2>
                            <p class="flow-note">Signed in as <strong>{{ user }}</strong>. Center your face in the oval, then follow the prompts on the video (turn your head, smile).</p>
                            <div class="flow-actions">
                                <button class="btn btn-primary" @click="enroll()" :disabled="busy || !!cameraError">{{ busy ? 'Scanning…' : 'Start scan' }}</button>
                                <button class="btn btn-secondary" @click="logout()" :disabled="busy">Not you?</button>
                            </div>
                        </div>

                        <!-- step 3: enrolled -->
                        <div v-if="enrollStep === 3" class="done">
                            <div class="done-mark">&#10003;</div>
                            <h2 class="flow-title">Your face is enrolled</h2>
                            <p class="flow-note">{{ user }} can now sign in with their face: center it in the oval and follow the prompts.</p>
                            <div class="flow-actions">
                                <button class="btn btn-primary" @click="tryLogin()" :disabled="busy">Try it</button>
                                <button class="btn btn-secondary" v-if="passkeys" @click="passkeyRegister()" :disabled="busy">Add a passkey</button>
                                <button class="btn btn-secondary" @click="enrollStep = 2; status = ''">Re-scan</button>
                            </div>
                            <p class="flow-note">A passkey makes face sign-in a two-step login: the face, then this device's fingerprint, face unlock or PIN.</p>
                        </div>
                    </section>

                    <p class="flow-success" v-if="status">{{ status }}</p>
                    <p class="error" v-if="error">{{ error }}</p>
                </div>
            </main>
        </div>

        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo scripts('axios'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'face-api.min.js'; ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'detect.js'; ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'passkey.js'; ?>"></script>
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <script>loadVue({ scripts: ['main'] });</script>
    </body>
</html>
```
```css
/* public/facelogin/css/styles.css */
/* Face login app (recipes-face-login.md): the Caligrafy black and white look comes from theme.css */

[v-cloak] { display: none; }

body { background: #f7f7f7; }

/* the Caligrafy header is fixed (styles.css): the page starts below it and centers the card */
.facelogin-page {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  min-height: 100vh;
  padding: 110px 16px 40px;
}

html .card.facelogin-card {
  width: 100%;
  min-width: 0;                   /* theme.css sets min-width: auto on cards: in this flex row it would grow to the 640px video */
  align-self: flex-start;         /* styles.css centers cards vertically: the card would jump between tabs */
  max-width: 520px;
  margin: 0 auto;
  padding: 0 0 24px;
  overflow: hidden;
}

/* flow switch */
.tabs {
  display: flex;
  border-bottom: 1px solid #e5e5e5;
}
.tabs button {
  flex: 1;
  padding: 16px 8px;
  background: #fafafa;
  border: 0;
  border-bottom: 3px solid transparent;
  color: #888;
  font-weight: 600;
  cursor: pointer;
}
.tabs button.active {
  background: #fff;
  color: #000;
  border-bottom-color: #000;
}

/* camera with the face guide */
.viewport {
  position: relative;
  margin: 24px 24px 0;
  aspect-ratio: 4 / 3;
  background: #111;
  border-radius: 8px;
  overflow: hidden;
}
.viewport video {
  display: block;
  width: 100%;
  max-width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1);          /* mirror, like a selfie camera */
}
.viewport canvas { display: none; }
.guide {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 46%;
  height: 78%;
  transform: translate(-50%, -50%);
  border: 3px dashed rgba(255, 255, 255, 0.85);
  border-radius: 50%;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45);   /* dims everything outside the oval */
  transition: border-color 0.2s;
  pointer-events: none;
}
.viewport.aligned .guide { border-style: solid; border-color: #ffffff; }
.viewport.scanning .guide { border-style: solid; border-color: #3fb950; }
.viewport.success .guide { border-style: solid; border-color: #3fb950; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6); }
.viewport.failed .guide { border-style: solid; border-color: #e5534b; }

.overlay-prompt {
  position: absolute;
  left: 50%;
  bottom: 14px;
  transform: translateX(-50%);
  padding: 6px 14px;
  border-radius: 16px;
  background: rgba(0, 0, 0, 0.75);
  color: #fff;
  font-weight: 600;
  white-space: nowrap;
}

/* flows */
.flow { padding: 0 24px; text-align: center; }
.flow-title { font-size: 1.25em; font-weight: 600; margin: 20px 0 8px; }
.flow-status { min-height: 1.5em; margin: 0 0 12px; color: #333; }
.flow-status.failed { color: #b42318; }
.flow-status.success { color: #1a7f37; }
.flow-note { color: #777; font-size: 0.9em; margin: 8px 0; }
.flow-note a { color: #000; text-decoration: underline; }
.flow-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin: 12px 0; }
.flow-success { margin: 12px 24px 0; text-align: center; color: #1a7f37; }
.facelogin-card > p.error { margin: 12px 24px 0; text-align: center; }

.account-form { max-width: 340px; margin: 0 auto; text-align: left; }
.account-form .flow-title, .account-form .flow-note { text-align: center; }
.account-form .form-control { margin-bottom: 12px; }

/* enrolment steps */
.steps {
  display: flex;
  justify-content: center;
  gap: 24px;
  list-style: none;
  padding: 0;
  margin: 20px 0 0;
  color: #aaa;
  font-size: 0.85em;
}
.steps li { display: flex; align-items: center; gap: 6px; padding: 0; }
.steps span {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 1px solid #ccc;
}
.steps li.current { color: #000; font-weight: 600; }
.steps li.current span { background: #000; border-color: #000; color: #fff; }
.steps li.complete { color: #1a7f37; }
.steps li.complete span { border-color: #1a7f37; }

.done-mark {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  width: 56px;
  height: 56px;
  margin-top: 24px;
  border-radius: 50%;
  background: #000;
  color: #fff;
  font-size: 28px;
}

@media (max-width: 480px) {
  .viewport { margin: 16px 16px 0; }
  .flow { padding: 0 16px; }
  .steps { gap: 12px; }
}
```
```js
// public/facelogin/scripts/main.js
// Face login: enrolment (password, then a liveness scan) and sign-in (face in the oval, then a liveness scan),
// passkeys alone or after the face (recipe: claude-plugin/skills/caligrafy/reference/recipes-face-login.md)
const PROMPTS = { 'turn-left': 'Turn your head to your left', 'turn-right': 'Turn your head to your right', 'smile': 'Smile' };
const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

const app = Vue.createApp({
  data() {
    return {
      env: env, detector: null, cameraError: '', camera: null,
      tab: 'login', user: null, factors: [], enrolled: false,
      enrollStep: 1, newAccount: false, form: { username: '', password: '' },
      loginState: 'waiting', loginMessage: 'Starting the camera…', overlay: '', scanToken: 0,
      status: '', error: '', busy: false,
      passkeys: PasskeyClient.supported(),
      config: { headers: { 'Authorization': 'Bearer ' + apiKey, 'Accept': 'application/json', 'Content-Type': 'application/json' }, timeout: 30000 }
    };
  },
  computed: {
    signedIn() { return !!this.user; },
    cameraVisible() { return this.tab === 'login' ? !this.signedIn : this.enrollStep === 2; },
    viewportState() { return this.tab === 'login' ? this.loginState : (this.busy ? 'scanning' : ''); }
  },
  methods: {
    startCamera() {
      // markRaw: a reactive FaceDetector breaks face-api (G16); autoStart false so camera errors reach us (G42)
      this.detector = Vue.markRaw(new FaceDetector('detection', { autoStart: false }));
      this.camera = (async () => {
        await this.detector.ready;
        await this.detector.startStream({ video: { facingMode: 'user', width: 640, height: 480 }, audio: false });
      })().catch((e) => {
        this.cameraError = e.name === 'NotAllowedError' ? 'Camera access is blocked: allow it in the browser settings'
                         : e.name === 'NotFoundError' ? 'No camera found' : e.message;
        throw e;
      });
      return this.camera;
    },
    post(path, body) {
      return axios.post(this.env.home + path, body, this.config).then(({ data }) => {
        if (typeof data === 'string') throw new Error(data);         // [ERROR ...] with HTTP 200 (G48)
        return data;
      });
    },
    message(e) {
      const err = e.response?.data?.error;
      return (typeof err === 'object' ? err?.message : err) || e.message;
    },
    async run(task) {
      this.busy = true; this.error = ''; this.status = '';
      try { await task(); }
      catch (e) { this.error = this.message(e); }
      finally { this.busy = false; this.overlay = ''; }
    },
    signedInAs(data) {
      this.user = data.user.username;
      this.factors = data.factors || [];
      if (data.enrolled !== undefined) this.enrolled = data.enrolled;
    },
    async refreshUser() {
      try {
        const { data } = await axios.get(this.env.home + 'facelogin/me', this.config);
        this.signedInAs(Object.assign({ factors: ['an earlier session'] }, data));
        this.enrollStep = this.enrolled ? 3 : 2;
      } catch (e) {
        this.user = null;
      }
    },

    // switch between the two flows
    show(tab) {
      this.tab = tab; this.error = ''; this.status = '';
      this.scanToken++;                                    // stops a running scan
      if (tab === 'login' && !this.signedIn) this.scan();
    },
    stepClass(n) { return n === this.enrollStep ? 'current' : n < this.enrollStep ? 'complete' : ''; },

    // ---------- sign-in: the face in the oval starts the liveness scan, then the face is recognized ----------
    // where the face is in the frame: 'none', 'many', 'off', 'far', 'near' or 'ok'
    placement(print) {
      const media = this.detector.media;
      const w = media.videoWidth || media.width, h = media.videoHeight || media.height;
      const box = print.detection.box;
      const cx = (box.x + box.width / 2) / w, cy = (box.y + box.height / 2) / h, size = box.width / w;
      if (Math.abs(cx - 0.5) > 0.15 || Math.abs(cy - 0.5) > 0.2) return 'off';
      if (size < 0.2) return 'far';
      if (size > 0.7) return 'near';
      return 'ok';
    },
    async scan() {
      const token = ++this.scanToken;
      const live = () => token === this.scanToken && this.tab === 'login' && !this.signedIn;
      this.error = '';
      this.loginState = 'waiting'; this.loginMessage = 'Starting the camera…';
      try { await this.camera; } catch (e) { this.loginState = 'failed'; this.loginMessage = 'The camera is needed to sign in with your face'; return; }

      while (live()) {
        let print = null, where = 'none';
        try { print = await this.detector.faceprint(); where = this.placement(print); }
        catch (e) { where = e.message === 'More than one face found' ? 'many' : 'none'; }
        if (!live()) return;

        if (where !== 'ok') {
          this.loginState = 'waiting';
          this.loginMessage = { none: 'Place your face inside the oval', many: 'Only one face, please', off: 'Center your face in the oval', far: 'Move a little closer', near: 'Move back a little' }[where];
          await sleep(150);
          continue;
        }

        // the face is placed: liveness challenges chosen by the server, then the prints are matched
        this.loginState = 'scanning'; this.loginMessage = 'Follow the prompts on the video';
        try {
          const response = await this.check({});
          if (!live()) return;                                         // the user left the tab during the scan
          const data = await this.post('facelogin/login', response);
          if (data.step === 'passkey') {                              // step-up: this account also needs its passkey
            this.loginMessage = 'Face recognized: confirm with your passkey';
            await this.finishPasskey(data.options);
          } else {
            this.signedInAs(data);
          }
          this.loginState = 'success';
        } catch (e) {
          if (live()) this.scanFailed(this.message(e));
        } finally {
          this.overlay = '';
        }
        return;
      }
    },
    scanFailed(message) {
      this.loginState = 'failed';
      this.loginMessage = message === 'Face not recognized' ? 'Face not recognized. Is it enrolled?' : message;
    },
    async finishPasskey(options) {
      this.signedInAs(await this.post('passkey/login', await PasskeyClient.get(options)));
    },
    passkeyLogin() {
      this.scanToken++;
      return this.run(async () => {
        try { await this.finishPasskey(await this.post('passkey/login/options', {})); }
        catch (e) { this.scanFailed(this.message(e)); }
      });
    },

    // ---------- enrolment ----------
    account() {
      return this.run(async () => {
        const data = await this.post(this.newAccount ? 'facelogin/register' : 'facelogin/password', { username: this.form.username, password: this.form.password });
        this.form.password = '';
        this.signedInAs(data);
        this.enrollStep = this.enrolled ? 3 : 2;
      });
    },
    // server challenge -> liveness on the camera -> { nonce, liveness, prints } for the server
    async check(start) {
      await this.camera;
      const challenge = await this.post('facelogin/start', start);
      const result = await this.detector.liveness({
        challenges: challenge.challenges,
        timeout: Math.min(20000, challenge.expires_in * 1000 - 2000),
        onProgress: (p) => { this.overlay = p.challenge ? `${p.index + 1}/${p.total} · ${PROMPTS[p.challenge]}` : 'Done'; }
      });
      if (!result.passed) throw new Error('The scan did not complete (' + result.reason + '): try again');
      this.overlay = 'Checking…';
      return { nonce: challenge.nonce, liveness: { passed: true, challenges: result.challenges }, prints: result.prints.map(p => p.descriptor) };
    },
    enroll() {
      return this.run(async () => {
        this.overlay = 'Look at the camera';
        await this.post('facelogin/enroll', await this.check({ enroll: true }));
        this.enrolled = true;
        this.enrollStep = 3;
      });
    },
    passkeyRegister() {
      return this.run(async () => {
        const options = await this.post('passkey/register/options', {});
        await this.post('passkey/register', Object.assign({ name: 'This device' }, await PasskeyClient.create(options)));
        this.status = 'Passkey added: face sign-in on this account now confirms with it';
      });
    },
    async tryLogin() {
      await this.logout();
      this.show('login');
    },
    logout() {
      return this.run(async () => {
        await this.post('facelogin/logout', {});
        this.user = null; this.factors = []; this.enrolled = false;
        this.enrollStep = 1;
        if (this.tab === 'login') this.$nextTick(() => this.scan());
      });
    }
  },
  async mounted() {
    this.startCamera().catch(() => {});
    await this.refreshUser();
    this.show(this.tab);           // keeps a tab the user picked while the page was loading
  },
  beforeUnmount() { this.scanToken++; if (this.detector) this.detector.stopStream(); this.detector = null; }
});
app.mount('#app');
```
- Sign in: `scan()` loops on `detector.faceprint()` (SSD, one face) and `placement()` until the face box is centered
  and 20-70% of the frame width, then runs `check({})`: the server challenge, `detector.liveness()` with the prompts on
  the video (`onProgress`), then `POST /facelogin/login`. A failure stops the loop (`Try again`), so the server lockout is
  not reached by a loop; switching tabs stops it too (`scanToken`).
- `detector.liveness()` resolves (it does not reject) with `{ passed, reason, challenges, prints, frames, duration }`;
  only a missing media element or models reject. `onProgress({ challenge, index, total, passed })` drives the prompt.
- The video is mirrored with CSS (`scaleX(-1)`, like a selfie camera). The prompts say the person's own left and right,
  which the mirror does not change; `detect.js` measures the unmirrored stream.
- Layout: the Caligrafy header is fixed (`styles.css`), so the page has a top padding; `theme.css` gives `.card`
  `min-width: auto` and `styles.css` `align-self: center`: the card in this flex row needs `min-width: 0` (or the 640px
  video widens it) and `align-self: flex-start` (or it moves when the tab content changes height).
- Keep `@click="scan()"` / `@click="enroll()"` with parentheses: without them Vue passes the click event as the first argument.
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
  `faceprint()` works on it, so the Sign in tab gets past the placement hints; `liveness()` must **fail** (a photo does
  not turn or start to smile: `Challenge not completed: ...` after the timeout): that is the photo attack test.
- Passkeys: Chrome DevTools > More tools > WebAuthn > "Enable virtual authenticator environment" (internal transport,
  resident keys, user verification) registers and logs in without a device. On the server, the QA suite uses a
  software authenticator (`qa/unit/fixtures/SoftAuthenticator.php`, EC P-256, "none" attestation).
- Only real devices show: the turn and smile thresholds on real faces and cameras (tune `thresholds`), Touch ID /
  Windows Hello / Android prompts, synced passkeys (sign count 0) and attestation formats other than "none".

## Checklist
- [ ] Controllers not named after an example controller unless namespaced (G47); `php -l`; SQL run with the db skill.
- [ ] Enrolment only for a logged-in user (password sign-in or new account, with the lockout), with a liveness check;
      a face already enrolled on another account refused.
- [ ] Login: `FaceLogin::verify()` (nonce, liveness, prints), then `FaceLogin::match()`, used-print hashes, lockout outside `users`.
- [ ] Prints encrypted with `encryptDecrypt` (APP_KEY), compared on the server, never returned.
- [ ] Passkey policy chosen and explained; `PASSKEY_RP_ID` / `PASSKEY_ORIGIN` set in production; https.
- [ ] `session_regenerate_id(true)` + `Auth::authorize()` only after every required factor.
- [ ] Client: `Vue.markRaw`, `autoStart: false` + `startStream()` errors, challenges from the server (no `blink`),
      sign-in loop stopped on failure and on tab change, `theme.css` + Caligrafy header.
- [ ] User told: browser liveness raises the bar but is not proof; passkeys for sensitive accounts.
