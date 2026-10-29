<?php

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
