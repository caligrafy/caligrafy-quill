<?php

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
