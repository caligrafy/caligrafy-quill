<?php
/**
 * Passkey.php
 * Passkeys (WebAuthn / FIDO2): registration and authentication with credentials stored in a table
 * @author Dory A.Azar
 * @copyright 2026
 * @version 1.0
 *
*/

/**
 * Passkey wraps lbuchs/webauthn (MIT, no dependencies besides ext-openssl). The browser side is
 * public/js/services/passkey.js (PasskeyClient.create() / PasskeyClient.get()).
 *
 * Registration (logged-in user):  registrationOptions($id, $username) -> navigator.credentials.create() -> register($response, $id)
 * Authentication:                  authenticationOptions([$id])        -> navigator.credentials.get()    -> authenticate($response) returns the user id
 *
 * The challenge of each ceremony is kept in the session: single use, it expires after $timeout seconds.
 * Relying party: PASSKEY_RP_ID (the domain, ex: example.com) and PASSKEY_ORIGIN (ex: https://www.example.com) in .env.
 * Without them the request host is used, which is fine in development: set both in production.
 * Passkeys need https, except on localhost.
 *
 * Table (see the face login recipe / wiki 9.9.2):
 *   passkeys (id, user_id, credential_id, public_key, sign_count, name, transports, aaguid, created_at, last_used_at)
*/

namespace Caligrafy;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;

class PasskeyException extends \Exception {}

class Passkey extends \stdClass {

    const SESSION_KEY = '__passkey_challenge';

    public $timeout = 120;                  // seconds for the browser prompt and the challenge
    public $userVerification = 'required';  // the authenticator checks the user (PIN, fingerprint, face): 2 factors in 1
    public $residentKey = 'required';       // discoverable credential: login without typing a username

    protected $webauthn;
    protected $rpName;
    protected $rpId;
    protected $origin;
    protected $table;

    /**
     * @param string|null $rpName name shown by the browser (default APP_NAME or the rp id)
     * @param string|null $rpId domain (default PASSKEY_RP_ID, else the request host)
     * @param string|null $origin exact origin the browser must report (default PASSKEY_ORIGIN, else APP_PROTOCOL://request host)
     * @param string $table credentials table
    */
    public function __construct($rpName = null, $rpId = null, $origin = null, $table = 'passkeys')
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        $this->rpId = $rpId ?: (getenv('PASSKEY_RP_ID') ?: (parse_url('http://' . $host, PHP_URL_HOST) ?: 'localhost'));
        $this->origin = rtrim($origin ?: (getenv('PASSKEY_ORIGIN') ?: ((getenv('APP_PROTOCOL') ?: 'https') . '://' . $host)), '/');
        $this->table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $this->rpName = $rpName ?: (getenv('APP_NAME') ?: $this->rpId);
        $this->webauthn = $this->webauthn();
    }

    /**
     * A new library instance: an instance keeps its first challenge, so every ceremony needs its own
    */
    protected function webauthn()
    {
        // formats that need no vendor root certificates: passkeys are trusted on first use
        return new WebAuthn($this->rpName, $this->rpId, ['none', 'packed', 'apple', 'android-key', 'tpm', 'fido-u2f'], true);
    }

    public function rpId() { return $this->rpId; }
    public function origin() { return $this->origin; }

    /**
     * Options for navigator.credentials.create() (pass them to PasskeyClient.create())
     * @param mixed $userId id of the logged-in user (stored as the WebAuthn user handle: never an email or a name)
    */
    public function registrationOptions($userId, $userName, $displayName = null)
    {
        $exclude = array_map(fn($c) => self::fromBase64Url($c['credential_id']), $this->credentials($userId));
        $webauthn = $this->webauthn();
        $args = $webauthn->getCreateArgs((string)$userId, (string)$userName, (string)($displayName ?? $userName), $this->timeout,
            $this->residentKey, $this->userVerification, null, $exclude);
        $this->remember('create', $userId, $webauthn->getChallenge());
        return json_decode(json_encode($args), true);
    }

    /**
     * Verifies the response of navigator.credentials.create() and stores the credential
     * @param array $response from PasskeyClient.create(): id, clientDataJSON, attestationObject, transports
     * @return array the stored credential (without the public key)
     * @throws PasskeyException
    */
    public function register($response, $userId, $name = null)
    {
        $challenge = $this->consume('create', $userId);
        $clientData = $this->clientData($response);
        $attestation = self::fromBase64Url($response['attestationObject'] ?? null);
        try {
            $data = $this->webauthn->processCreate($clientData, $attestation, $challenge, $this->userVerification === 'required', true, false);
        } catch (\Throwable $e) {
            throw new PasskeyException('Passkey registration failed: ' . $e->getMessage(), 400);
        }
        $credentialId = self::toBase64Url($data->credentialId);
        if (!hash_equals($credentialId, (string)($response['id'] ?? ''))) {
            throw new PasskeyException('Passkey registration failed: credential id mismatch', 400);
        }
        if ($this->findCredential($credentialId)) {
            throw new PasskeyException('This passkey is already registered', 409);
        }
        $transports = array_values(array_filter(is_array($response['transports'] ?? null) ? $response['transports'] : [],
            fn($t) => is_string($t) && preg_match('/^[a-z-]{2,16}$/', $t)));
        $credential = [
            'user_id' => (string)$userId,
            'credential_id' => $credentialId,
            'public_key' => $data->credentialPublicKey,
            'sign_count' => (int)($data->signatureCounter ?? 0),
            'name' => mb_substr(trim((string)($name ?? '')) ?: 'Passkey', 0, 64),
            'transports' => implode(',', $transports),
            'aaguid' => $data->AAGUID instanceof ByteBuffer ? bin2hex($data->AAGUID->getBinaryString()) : null,
        ];
        $this->saveCredential($credential);
        unset($credential['public_key']);
        return $credential;
    }

    /**
     * Options for navigator.credentials.get() (pass them to PasskeyClient.get())
     * @param mixed $userId Optional. Limits the prompt to this user's passkeys; null lets the browser offer any
     *                      discoverable passkey of the site (login without a username)
    */
    public function authenticationOptions($userId = null)
    {
        $ids = $userId !== null ? array_map(fn($c) => self::fromBase64Url($c['credential_id']), $this->credentials($userId)) : [];
        $webauthn = $this->webauthn();
        $args = $webauthn->getGetArgs($ids, $this->timeout, true, true, true, true, true, $this->userVerification);
        $this->remember('get', $userId, $webauthn->getChallenge());
        return json_decode(json_encode($args), true);
    }

    /**
     * Verifies the response of navigator.credentials.get()
     * @param array $response from PasskeyClient.get(): id, clientDataJSON, authenticatorData, signature, userHandle
     * @return string the id of the user who owns the passkey
     * @throws PasskeyException (401)
    */
    public function authenticate($response)
    {
        $expected = $_SESSION[self::SESSION_KEY]['user'] ?? null;
        $challenge = $this->consume('get');
        $credential = $this->findCredential((string)($response['id'] ?? ''));
        if (!$credential) {
            throw new PasskeyException('Unknown passkey', 401);
        }
        if ($expected !== null && (string)$credential['user_id'] !== (string)$expected) {
            throw new PasskeyException('This passkey belongs to another account', 401);
        }
        if (!empty($response['userHandle']) && self::fromBase64Url($response['userHandle']) !== (string)$credential['user_id']) {
            throw new PasskeyException('This passkey belongs to another account', 401);
        }
        $clientData = $this->clientData($response);
        try {
            $this->webauthn->processGet($clientData, self::fromBase64Url($response['authenticatorData'] ?? null),
                self::fromBase64Url($response['signature'] ?? null), $credential['public_key'], $challenge,
                (int)$credential['sign_count'], $this->userVerification === 'required', true);
        } catch (\Throwable $e) {
            throw new PasskeyException('Passkey login failed: ' . $e->getMessage(), 401);
        }
        $this->touchCredential($credential['credential_id'], $this->webauthn->getSignatureCounter() ?? (int)$credential['sign_count']);
        return (string)$credential['user_id'];
    }

    /**
     * Storage: override these 4 methods to keep the credentials somewhere else
    */
    public function credentials($userId)
    {
        return Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE user_id = ?', [(string)$userId])) ?: [];
    }

    public function findCredential($credentialId)
    {
        return Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE credential_id = ? LIMIT 1', [(string)$credentialId]))[0] ?? null;
    }

    protected function saveCredential($c)
    {
        Database::execute('INSERT INTO ' . $this->table . ' (user_id, credential_id, public_key, sign_count, name, transports, aaguid) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$c['user_id'], $c['credential_id'], $c['public_key'], $c['sign_count'], $c['name'], $c['transports'], $c['aaguid']]);
    }

    protected function touchCredential($credentialId, $signCount)
    {
        Database::execute('UPDATE ' . $this->table . ' SET sign_count = ?, last_used_at = ? WHERE credential_id = ?',
            [(int)$signCount, gmdate('Y-m-d H:i:s'), $credentialId]);
    }

    public function remove($userId, $credentialId)
    {
        Database::execute('DELETE FROM ' . $this->table . ' WHERE user_id = ? AND credential_id = ?', [(string)$userId, (string)$credentialId]);
    }

    /**
     * Challenge of the ceremony in progress
    */
    protected function remember($type, $userId, ByteBuffer $challenge)
    {
        $_SESSION[self::SESSION_KEY] = [
            'type' => $type,
            'challenge' => base64_encode($challenge->getBinaryString()),
            'user' => $userId !== null ? (string)$userId : null,
            'expires' => time() + $this->timeout,
        ];
    }

    protected function consume($type, $userId = null)
    {
        $c = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_array($c) || $c['type'] !== $type || time() > $c['expires']) {
            throw new PasskeyException('No passkey request in progress or it expired: start again', 401);
        }
        if ($type === 'create' && (string)$c['user'] !== (string)$userId) {
            throw new PasskeyException('The passkey request was started by another account', 401);
        }
        return new ByteBuffer(base64_decode($c['challenge']));
    }

    /**
     * clientDataJSON with an exact origin check (the library only checks the domain suffix)
    */
    protected function clientData($response)
    {
        $json = self::fromBase64Url($response['clientDataJSON'] ?? null);
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['origin'] ?? null) !== $this->origin) {
            throw new PasskeyException('Passkey refused: wrong origin', 401);
        }
        if (!empty($data['crossOrigin'])) {
            throw new PasskeyException('Passkey refused: cross-origin request', 401);
        }
        return $json;
    }

    public static function toBase64Url($binary)
    {
        $binary = $binary instanceof ByteBuffer ? $binary->getBinaryString() : (string)$binary;
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function fromBase64Url($value)
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]*$/', $value)) {
            throw new PasskeyException('Invalid passkey response', 400);
        }
        return (string)base64_decode(strtr($value, '-_', '+/'));
    }
}
