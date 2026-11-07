<?php
/**
 * Cache.php
 * Values kept for a while, so that slow work is done once
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Cache (2.18.0)
 *
 *     $rates = Cache::remember('exchange-rates', 600, fn () => $this->fetchRates());   // at most once every 10 minutes
 *     Cache::put('report:2026-11', $rows, 3600);  Cache::get('report:2026-11', []);  Cache::forget('report:2026-11');
 *
 * CACHE_STORE in .env: database (the default with DB_ACTIVATE=true: the caligrafy_cache table, which the deploy step
 * and php caligrafer.php platform install create, shared by the web server, the queue, scheduled jobs and every
 * server), file (the default without a database: a private folder of the temporary directory, of one server and one
 * system user, so the command line and the web server each have their own) or off (nothing is kept, remember()
 * always runs its function). Values are stored as JSON: strings, numbers, booleans,
 * null and arrays (an object comes back as an array). $seconds null keeps a value until it is forgotten or flushed.
 * php caligrafer.php cache clear empties the store.
 */
class Cache
{
    const TABLE = 'caligrafy_cache';
    const STORES = array('file', 'database', 'off');

    private static $memory = array();        // key => [expires (0 = never), value]: this request's reads and writes
    private static $fallback = null;         // the store used when the database store has no table

    /**
     * The store in use: file, database or off
     */
    public static function store()
    {
        if (self::$fallback !== null) {
            return self::$fallback;
        }
        $store = strtolower(trim((string) getenv('CACHE_STORE')));
        if ($store === '') {
            // the database when there is one: shared by the web server, the queue, the scheduler and every server
            $store = strtolower(trim((string) getenv('DB_ACTIVATE'))) === 'true' ? 'database' : 'file';
        }
        if (!in_array($store, self::STORES, true)) {
            throw new \InvalidArgumentException("CACHE_STORE=$store: use file, database or off");
        }
        return $store === 'file' && ClassMap::tempFolder('data') === null ? 'off' : $store;
    }

    /**
     * Runs a query of the database store; without the caligrafy_cache table (php caligrafer.php platform install
     * creates it), the file store is used for the rest of the request
     */
    private static function query($sql, array $args)
    {
        try {
            return Database::execute($sql, $args);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) !== 1146 && strpos($e->getMessage(), self::TABLE) === false) {
                throw $e;
            }
            error_log('Caligrafy cache: the ' . self::TABLE . ' table is missing (php caligrafer.php platform install): using the file store');
            self::$fallback = ClassMap::tempFolder('data') === null ? 'off' : 'file';
            return null;
        }
    }

    public static function get($key, $default = null)
    {
        $found = self::lookup($key);
        return $found === null ? $default : $found[0];
    }

    public static function has($key)
    {
        return self::lookup($key) !== null;
    }

    /**
     * Keeps $value for $seconds (null: until forgotten)
     */
    public static function put($key, $value, $seconds = null)
    {
        $key = self::key($key);
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new \InvalidArgumentException("The value of '$key' cannot be cached: " . json_last_error_msg());
        }
        if ($seconds !== null && (int) $seconds <= 0) {
            self::forget($key);
            return false;
        }
        $expires = $seconds === null ? 0 : time() + (int) $seconds;
        $store = self::store();
        if ($store === 'off') {
            return false;
        }
        self::$memory[$key] = array($expires, json_decode($json, true));
        if ($store === 'file') {
            $path = self::path($key);
            $tmp = $path . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, json_encode(array('k' => $key, 'e' => $expires, 'v' => $json))) === false || !@rename($tmp, $path)) {
                @unlink($tmp);
                return false;
            }
            return true;
        }
        if (self::query('REPLACE INTO `' . self::TABLE . '` (`key_hash`, `cache_key`, `value`, `expires_at`) VALUES (?, ?, ?, ?)',
            array(hash('sha256', $key), mb_substr($key, 0, 255), $json, $expires ?: null)) === null) {
            return self::put($key, $value, $seconds);          // the table is missing: the file store
        }
        if (mt_rand(1, 100) === 1) {
            self::query('DELETE FROM `' . self::TABLE . '` WHERE `expires_at` IS NOT NULL AND `expires_at` <= ?', array(time()));
        }
        return true;
    }

    /**
     * Keeps $value forever (until forgotten or flushed)
     */
    public static function forever($key, $value)
    {
        return self::put($key, $value, null);
    }

    /**
     * The cached value, or the result of $make, kept for $seconds
     */
    public static function remember($key, $seconds, callable $make)
    {
        $found = self::lookup($key);
        if ($found !== null) {
            return $found[0];
        }
        $value = $make();
        self::put($key, $value, $seconds);
        return json_decode(json_encode($value), true) ?? $value;   // what a later read returns
    }

    public static function forget($key)
    {
        $key = self::key($key);
        unset(self::$memory[$key]);
        $store = self::store();
        if ($store === 'file') {
            @unlink(self::path($key));
        } elseif ($store === 'database' && self::query('DELETE FROM `' . self::TABLE . '` WHERE `key_hash` = ?', array(hash('sha256', $key))) === null) {
            return self::forget($key);
        }
        return true;
    }

    /**
     * Empties the store
     * @return int|null how many values were removed (null when unknown)
     */
    public static function flush()
    {
        self::$memory = array();
        $store = self::store();
        if ($store === 'file') {
            $count = 0;
            foreach (glob(ClassMap::tempFolder('data') . DIRECTORY_SEPARATOR . '*.json') ?: array() as $file) {
                $count += @unlink($file) ? 1 : 0;
            }
            return $count;
        }
        if ($store === 'database') {
            return self::query('DELETE FROM `' . self::TABLE . '`', array()) === null ? self::flush() : null;
        }
        return 0;
    }

    /**
     * Forgets this request's copies (tests, a long worker)
     */
    public static function clearMemory()
    {
        self::$memory = array();
    }

    /**
     * [value] when the key is cached and not expired, else null
     */
    private static function lookup($key)
    {
        $key = self::key($key);
        $now = time();
        if (isset(self::$memory[$key]) && (self::$memory[$key][0] === 0 || self::$memory[$key][0] > $now)) {
            return array(self::$memory[$key][1]);
        }
        unset(self::$memory[$key]);
        $store = self::store();
        if ($store === 'off') {
            return null;
        }
        if ($store === 'file') {
            $path = self::path($key);
            $raw = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
            if (!is_array($raw) || ($raw['k'] ?? null) !== $key) {
                return null;
            }
            if ($raw['e'] !== 0 && $raw['e'] <= $now) {
                @unlink($path);
                return null;
            }
            $expires = (int) $raw['e'];
            $json = $raw['v'];
        } else {
            $result = self::query('SELECT `value`, `expires_at` FROM `' . self::TABLE . '` WHERE `key_hash` = ?', array(hash('sha256', $key)));
            if ($result === null) {
                return self::lookup($key);                    // the table is missing: the file store
            }
            $rows = Database::toArray($result);
            if (!$rows) {
                return null;
            }
            $expires = (int) ($rows[0]['expires_at'] ?? 0);
            if ($expires !== 0 && $expires <= $now) {
                self::query('DELETE FROM `' . self::TABLE . '` WHERE `key_hash` = ? AND `expires_at` <= ?', array(hash('sha256', $key), $now));
                return null;
            }
            $json = $rows[0]['value'];
        }
        $value = json_decode((string) $json, true);
        self::$memory[$key] = array($expires, $value);
        return array($value);
    }

    private static function path($key)
    {
        return ClassMap::tempFolder('data') . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }

    private static function key($key)
    {
        $key = (string) $key;
        if ($key === '') {
            throw new \InvalidArgumentException('A cache key cannot be empty');
        }
        return $key;
    }
}
