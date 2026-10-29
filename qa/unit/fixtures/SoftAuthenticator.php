<?php
/**
 * A software WebAuthn authenticator for the QA tests: it answers the options of Caligrafy\Passkey like
 * navigator.credentials.create()/get() would, with an EC P-256 (ES256) key and "none" attestation.
 * Used by the unit tests (qa/unit/PasskeyTest.php) and the HTTP tests (qa/tests/http_tests.php).
 */

class QaSoftAuthenticator
{
    public $key;
    public $credentialId;
    public $counter = 0;
    public $userHandle;
    public $flags = 0x05; // user present + user verified

    public function __construct()
    {
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credentialId = random_bytes(32);
    }

    public static function b64u($bin) { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
    public static function unb64u($s) { return base64_decode(strtr($s, '-_', '+/')); }

    // minimal CBOR encoder: ints, byte strings (['bytes' => ..]), text, arrays, maps
    public static function cbor($v)
    {
        $head = function ($major, $n) {
            if ($n < 24) return chr(($major << 5) | $n);
            if ($n < 256) return chr(($major << 5) | 24) . chr($n);
            if ($n < 65536) return chr(($major << 5) | 25) . pack('n', $n);
            return chr(($major << 5) | 26) . pack('N', $n);
        };
        if ($v instanceof stdClass) $v = (array)$v ?: null;
        if ($v === null) return $head(5, 0); // empty map
        if (is_int($v)) return $v >= 0 ? $head(0, $v) : $head(1, -1 - $v);
        if (is_array($v) && array_key_exists('bytes', $v) && count($v) === 1) return $head(2, strlen($v['bytes'])) . $v['bytes'];
        if (is_string($v)) return $head(3, strlen($v)) . $v;
        if (is_array($v) && array_is_list($v)) return $head(4, count($v)) . implode('', array_map([self::class, 'cbor'], $v));
        if (is_array($v)) {
            $out = $head(5, count($v));
            foreach ($v as $k => $x) { $out .= self::cbor(is_numeric($k) ? (int)$k : $k) . self::cbor($x); }
            return $out;
        }
        throw new Exception('cbor: unsupported value');
    }

    private function coseKey()
    {
        $d = openssl_pkey_get_details($this->key)['ec'];
        $pad = fn($b) => str_pad($b, 32, "\0", STR_PAD_LEFT);
        // kty EC2, alg ES256, crv P-256, x, y
        return self::cbor([1 => 2, 3 => -7, -1 => 1, -2 => ['bytes' => $pad($d['x'])], -3 => ['bytes' => $pad($d['y'])]]);
    }

    // navigator.credentials.create() answer, as PasskeyClient.create() sends it
    public function create($options, $origin, $overrides = [])
    {
        $pk = $options['publicKey'];
        $this->userHandle = self::unb64u($pk['user']['id']);
        $clientData = json_encode(['type' => $overrides['type'] ?? 'webauthn.create', 'challenge' => $overrides['challenge'] ?? $pk['challenge'],
            'origin' => $origin, 'crossOrigin' => false]);
        $authData = hash('sha256', $overrides['rpId'] ?? $pk['rp']['id'], true) . chr(0x40 | $this->flags) . pack('N', $this->counter)
            . str_repeat("\0", 16) . pack('n', strlen($this->credentialId)) . $this->credentialId . $this->coseKey();
        $attestation = self::cbor(['fmt' => 'none', 'attStmt' => new stdClass(), 'authData' => ['bytes' => $authData]]);
        return ['id' => self::b64u($this->credentialId), 'clientDataJSON' => self::b64u($clientData),
            'attestationObject' => self::b64u($attestation), 'transports' => ['internal']];
    }

    // navigator.credentials.get() answer, as PasskeyClient.get() sends it
    public function get($options, $origin, $overrides = [])
    {
        $pk = $options['publicKey'];
        $this->counter++;
        $clientData = json_encode(['type' => 'webauthn.get', 'challenge' => $overrides['challenge'] ?? $pk['challenge'], 'origin' => $origin, 'crossOrigin' => false]);
        $authData = hash('sha256', $pk['rpId'], true) . chr($this->flags) . pack('N', $overrides['counter'] ?? $this->counter);
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);
        return ['id' => self::b64u($this->credentialId), 'clientDataJSON' => self::b64u($clientData), 'authenticatorData' => self::b64u($authData),
            'signature' => self::b64u($signature), 'userHandle' => self::b64u($this->userHandle ?? '')];
    }
}
