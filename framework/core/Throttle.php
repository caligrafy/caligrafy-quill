<?php
/**
 * Throttle.php
 * Counts failures per key and locks the key after too many: the sign-in of Route::auth()
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Throttle (2.19.0)
 *   $throttle = new Throttle(5, 900);          // 5 failures within 15 minutes lock the key for the rest of them
 *   if ($throttle->locked($key)) ...; $throttle->fail($key); $throttle->clear($key);
 * The counts are kept in the Cache (CACHE_STORE: the caligrafy_cache table or a private folder, shared by every
 * session, so that a new cookie does not start again), or in the session when the cache is off.
 * Each failure keeps the count for $seconds more.
 */
class Throttle
{
    /** @var int */
    public $max;

    /** @var int */
    public $seconds;

    /** @var array|null ['get' => fn ($key), 'put' => fn ($key, $value, $seconds), 'forget' => fn ($key)] */
    private $store;

    public function __construct($max = 5, $seconds = 900, ?array $store = null)
    {
        $this->max = max(1, (int) $max);
        $this->seconds = max(1, (int) $seconds);
        $this->store = $store;
    }

    /**
     * A key for a person at an address: the IP and the username (lower case), hashed
     */
    public static function key($scope, $ip, $username)
    {
        return $scope . ':' . hash('sha256', (string) $ip . '|' . strtolower(trim((string) $username)));
    }

    /**
     * The same counts and window with another limit (2.19.6: Route::auth's attemptsPerUser)
     */
    public function limitedTo($max)
    {
        $copy = clone $this;
        $copy->max = max(1, (int) $max);
        return $copy;
    }

    /**
     * Whether the key failed $max times within the window
     */
    public function locked($key)
    {
        return $this->failures($key) >= $this->max;
    }

    /**
     * The failures of the key within the window
     */
    public function failures($key)
    {
        $entry = $this->read($key);
        return is_array($entry) && (int) ($entry['until'] ?? 0) >= time() ? (int) ($entry['failures'] ?? 0) : 0;
    }

    /**
     * Counts one failure; returns the failures of the key
     */
    public function fail($key)
    {
        $failures = $this->failures($key) + 1;
        $this->write($key, array('failures' => $failures, 'until' => time() + $this->seconds));
        return $failures;
    }

    /**
     * Forgets the failures of the key (a success)
     */
    public function clear($key)
    {
        if ($this->store !== null) {
            call_user_func($this->store['forget'], $key);
        } elseif (self::cacheOn()) {
            Cache::forget($key);
        } else {
            unset($_SESSION['__throttle'][$key]);
        }
    }

    private function read($key)
    {
        if ($this->store !== null) {
            return call_user_func($this->store['get'], $key);
        }
        return self::cacheOn() ? Cache::get($key) : ($_SESSION['__throttle'][$key] ?? null);
    }

    private function write($key, array $value)
    {
        if ($this->store !== null) {
            call_user_func($this->store['put'], $key, $value, $this->seconds);
        } elseif (self::cacheOn()) {
            Cache::put($key, $value, $this->seconds);
        } else {
            $_SESSION['__throttle'][$key] = $value;
        }
    }

    private static function cacheOn()
    {
        try {
            return Cache::store() !== 'off';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
