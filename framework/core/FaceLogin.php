<?php
/**
 * FaceLogin.php
 * Server side of face login: single-use liveness challenges (nonce) and checks of the face prints captured during them
 * @author Dory A.Azar
 * @copyright 2026
 * @version 1.0
 *
*/

/**
 * Face login checks used with FaceDetector.liveness() (public/js/services/detect.js)
 *
 * 1. challenge($username) issues a nonce and $count of the CHALLENGES in a random order, kept in the session
 * 2. the browser runs detector.liveness({ challenges }) and posts { nonce, liveness, prints }
 * 3. verify($response, $username) consumes the nonce (single use, short lived) and checks the liveness result and the
 *    prints: enough of them, consistent with each other (same face) and not identical (a replayed static print)
 * 4. match($prints, $enrolled) finds the account whose enrolled prints are close to every print
 *
 * Face prints are computed in the browser: these checks stop photos, naive replays and stolen requests, they do not
 * stop a determined attacker who controls the browser. Use a passkey (Caligrafy\Passkey) for sensitive accounts.
 *
 * Distances are euclidean between 128-number face-api descriptors: same person usually < 0.5, others > 0.6
*/

namespace Caligrafy;

class FaceLoginException extends \Exception {}

class FaceLogin extends \stdClass {

    const SESSION_KEY = '__face_challenge';
    const CHALLENGES = ['turn-left', 'turn-right', 'smile'];

    public static $match = 0.5;      // a print matches an account when it is closer than this to one of its prints
    public static $spread = 0.6;     // prints of one check must all be within this of each other (same face)
    public static $distinct = 0.001; // ... and further apart than this: identical prints are a replay, not a live face
    public static $minPrints = 3;
    public static $maxPrints = 12;
    public static $count = 2;        // challenges per check: 2 of the 3, in a random order (6 possible sequences)
    public static $ttl = 60;         // seconds to complete a check
    public static $clock = null;     // callable returning the time (tests)

    private static function now()
    {
        return self::$clock ? (int)call_user_func(self::$clock) : time();
    }

    /**
     * Issues a new challenge and keeps it in the session (it replaces any previous one)
     * @param string|null $username the account the check is for (null: 1:N login)
     * @param int|null $count number of challenges (1 to 3, default self::$count)
     * @return array ['nonce' => string, 'challenges' => [...], 'expires_in' => seconds]
    */
    public static function challenge($username = null, $count = null, $ttl = null)
    {
        $count = max(1, min(count(self::CHALLENGES), (int)($count ?? self::$count)));
        $ttl = (int)($ttl ?? self::$ttl);
        $pool = self::CHALLENGES;
        // Fisher-Yates with random_int: the order is not predictable
        for ($i = count($pool) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
        }
        $challenge = [
            'nonce' => bin2hex(random_bytes(32)),
            'challenges' => array_slice($pool, 0, $count),
            'username' => $username !== null && $username !== '' ? strtolower((string)$username) : null,
            'expires' => self::now() + $ttl,
        ];
        $_SESSION[self::SESSION_KEY] = $challenge;
        return ['nonce' => $challenge['nonce'], 'challenges' => $challenge['challenges'], 'expires_in' => $ttl];
    }

    /**
     * Takes the challenge out of the session: a nonce can be used once, even when the check then fails
     * @return array the challenge
     * @throws FaceLoginException (401) when there is no challenge, the nonce differs, it expired or is for another account
    */
    public static function consume($nonce, $username = null)
    {
        $challenge = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_array($challenge) || !is_string($nonce) || !hash_equals($challenge['nonce'], $nonce)) {
            throw new FaceLoginException('Unknown or already used challenge: start again', 401);
        }
        if (self::now() > $challenge['expires']) {
            throw new FaceLoginException('The challenge expired: start again', 401);
        }
        $username = $username !== null && $username !== '' ? strtolower((string)$username) : null;
        if ($challenge['username'] !== $username) {
            throw new FaceLoginException('The challenge was issued for another account', 401);
        }
        return $challenge;
    }

    /**
     * Consumes the nonce and checks the liveness result and the prints of a response
     * @param array $response ['nonce' => .., 'liveness' => ['passed' => true, 'challenges' => [['name' =>, 'passed' =>], ..]],
     *                         'prints' => [[128 numbers] or ['descriptor' => [128 numbers]], ..]]
     * @return array the prints: [[128 floats], ..]
     * @throws FaceLoginException 401 (nonce) or 422 (liveness, prints)
    */
    public static function verify($response, $username = null)
    {
        $response = is_array($response) ? $response : [];
        $challenge = self::consume($response['nonce'] ?? null, $username);

        $liveness = $response['liveness'] ?? null;
        if (!is_array($liveness) || ($liveness['passed'] ?? false) !== true) {
            throw new FaceLoginException('The liveness check did not pass', 422);
        }
        $done = [];
        foreach (is_array($liveness['challenges'] ?? null) ? $liveness['challenges'] : [] as $c) {
            if (is_array($c) && ($c['passed'] ?? false) === true) { $done[] = $c['name'] ?? null; }
        }
        if ($done !== $challenge['challenges']) {
            throw new FaceLoginException('The liveness challenges do not match the ones requested', 422);
        }
        return self::checkPrints($response['prints'] ?? null);
    }

    /**
     * Validates the prints of one check: count, format, same face, not identical
     * @return array [[128 floats], ..]
    */
    public static function checkPrints($prints)
    {
        $prints = is_array($prints) ? array_values($prints) : [];
        if (count($prints) < self::$minPrints || count($prints) > self::$maxPrints) {
            throw new FaceLoginException('Send ' . self::$minPrints . ' to ' . self::$maxPrints . ' face prints', 422);
        }
        $out = [];
        foreach ($prints as $p) {
            $d = self::descriptor(is_array($p) && isset($p['descriptor']) ? $p['descriptor'] : $p);
            if (!$d) {
                throw new FaceLoginException('Invalid face print', 422);
            }
            $out[] = $d;
        }
        foreach ($out as $i => $a) {
            foreach (array_slice($out, $i + 1) as $b) {
                $distance = self::distance($a, $b);
                if ($distance < self::$distinct) {
                    throw new FaceLoginException('Identical face prints: they must come from a live camera', 422);
                }
                if ($distance >= self::$spread) {
                    throw new FaceLoginException('The face prints do not look like the same face', 422);
                }
            }
        }
        return $out;
    }

    /**
     * Finds the account that matches every print
     * @param array $prints [[128 floats], ..] from verify()
     * @param array $enrolled [['user' => id, 'descriptor' => [128 floats]], ..]
     * @return array|null ['user' => id, 'distance' => mean distance] or null when no account matches
    */
    public static function match($prints, $enrolled)
    {
        $byUser = [];
        foreach ($enrolled as $e) {
            $d = self::descriptor($e['descriptor'] ?? null);
            if ($d) { $byUser[$e['user']][] = $d; }
        }
        $best = null;
        foreach ($byUser as $user => $known) {
            $distances = [];
            foreach ($prints as $p) {
                $distances[] = min(array_map(fn($k) => self::distance($k, $p), $known));
            }
            $mean = array_sum($distances) / count($distances);
            // most prints close to the account, none of them far: a turned head is a little further than a frontal one
            if ($mean < self::$match && max($distances) < self::$spread && (!$best || $mean < $best['distance'])) {
                $best = ['user' => $user, 'distance' => $mean];
            }
        }
        return $best;
    }

    /**
     * Prints of a new enrolment must not match another account (one person, one account)
     * @return mixed the id of the account that already has this face, or null
    */
    public static function enrolledElsewhere($prints, $enrolled, $user = null)
    {
        foreach ($enrolled as $e) {
            if ($user !== null && (string)$e['user'] === (string)$user) { continue; }
            $d = self::descriptor($e['descriptor'] ?? null);
            foreach ($d ? $prints : [] as $p) {
                if (self::distance($d, $p) < self::$match) { return $e['user']; }
            }
        }
        return null;
    }

    /**
     * Hash of a print to remember the prints already accepted (an exact replay with a new nonce is refused)
    */
    public static function printHash($descriptor)
    {
        return hash('sha256', implode(',', array_map(fn($v) => sprintf('%.5f', $v), $descriptor)));
    }

    /**
     * 128 finite numbers as floats, or null
    */
    public static function descriptor($d)
    {
        if (!is_array($d) || count($d) !== 128) { return null; }
        $out = [];
        foreach (array_values($d) as $v) {
            if (!is_int($v) && !is_float($v) && !(is_string($v) && is_numeric($v))) { return null; }
            $v = (float)$v;
            if (!is_finite($v)) { return null; }
            $out[] = $v;
        }
        return $out;
    }

    public static function distance($a, $b)
    {
        $s = 0.0;
        for ($i = 0; $i < 128; $i++) { $s += ($a[$i] - $b[$i]) ** 2; }
        return sqrt($s);
    }
}
