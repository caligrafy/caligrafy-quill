<?php
/**
 * Kernel.php
 * The application kept in memory: capabilities answered without starting the framework again (2.26.0, experimental)
 * @copyright 2026
 *
*/

namespace Caligrafy\Async;

use Caligrafy\Cache;
use Caligrafy\Capabilities;
use Caligrafy\Container;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Model;
use Caligrafy\Query;
use Caligrafy\Rest;
use Caligrafy\ServiceError;
use Caligrafy\Tokens;

/**
 * Class Kernel
 * What the async server runs, whatever server it is: boot() once per process (settings, constants, the class map, the
 * application's config and container, the capabilities), then handle() for each request, one at a time per process.
 *
 * It answers the capabilities channel only, as Route::capabilities() does on the web server:
 *   GET  {prefix}         the capabilities the caller may call
 *   POST {prefix}/{name}  a call (GET too for a read-only capability), inputs in the JSON body or the query string
 * The caller is known from its token (Authorization: Bearer cq_...), or is a visitor. There is no session and no
 * cookie here: a request that brings the session cookie is the web server's (handles() says so), as is a capability
 * that Eligibility refuses. Between requests, everything a request may leave behind is forgotten (endRequest()).
 */
class Kernel
{
    private static $booted = false;
    private static $prefix = '/capabilities';
    private static $eligible = array();    // name => bool, decided once per process

    /**
     * Starts the framework once for this process (the root of the project is the current folder)
     */
    public static function boot($prefix = '/capabilities')
    {
        if (self::$booted) {
            return;
        }
        self::$prefix = '/' . trim((string) $prefix, '/');
        if (defined('CONFIG_PATH') && is_file(CONFIG_PATH . 'config.php')) {
            include CONFIG_PATH . 'config.php';
        }
        Container::boot();
        Capabilities::all();
        Container::keepInstances();
        self::$booted = true;
    }

    /**
     * Whether this server answers the request: a path of the capabilities channel, no session cookie, and (for a call)
     * a capability that may run here. Otherwise the web server answers it.
     */
    public static function handles($method, $path, array $cookies = array())
    {
        if (isset($cookies[session_name()])) {
            return false;
        }
        $path = '/' . trim((string) $path, '/');
        if ($path === self::$prefix) {
            return strtoupper($method) === 'GET';
        }
        if (strpos($path, self::$prefix . '/') !== 0) {
            return false;
        }
        $name = substr($path, strlen(self::$prefix) + 1);
        return preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) === 1 && self::eligible($name);
    }

    /**
     * Answers one request
     * @param array $headers lower-case names
     * @param array $query the query string's values
     * @return array ['status' => int, 'headers' => [name => value], 'body' => string]
     */
    public static function handle($method, $path, array $headers, $body, array $query = array())
    {
        $method = strtoupper((string) $method);
        $path = '/' . trim((string) $path, '/');
        try {
            $context = self::context($headers);
            if ($path === self::$prefix) {
                return self::json(200, array('data' => Capabilities::describe($context)));
            }
            $name = substr($path, strlen(self::$prefix) + 1);
            $entry = Capabilities::get($name);
            if ($method === 'GET' && $entry !== null && !$entry['capability']->readOnly) {
                return self::json(405, (new ServiceError('invalid', 'Changing data needs a POST'))->toArray());
            }
            $answer = Rest::answer($name, function () use ($context) { return $context; },
                function () use ($method, $body, $query) { return self::input($method, $body, $query); }, $method);
            $extra = array();
            $cache = $entry !== null ? $entry['capability']->cache : 0;
            if ($answer['status'] === 200 && $cache > 0 && empty($headers['authorization'])) {
                $extra = array('Cache-Control' => "public, max-age=$cache, s-maxage=$cache", 'Vary' => 'Cookie, Authorization');
            }
            if ($answer['status'] === 401) {
                $extra['WWW-Authenticate'] = 'Bearer';
            }
            return self::json($answer['status'], $answer['body'], $extra);
        } catch (\Throwable $e) {
            error_log('[Caligrafy async] ' . get_class($e) . ': ' . $e->getMessage());
            return self::json(500, array('error' => array('code' => 'error', 'message' => 'Something went wrong')));
        } finally {
            self::endRequest();
        }
    }

    /**
     * Whether a capability may run here (Eligibility, decided once per process)
     */
    public static function eligible($name)
    {
        if (!array_key_exists($name, self::$eligible)) {
            self::$eligible[$name] = Capabilities::get($name) !== null && Eligibility::eligible($name);
        }
        return self::$eligible[$name];
    }

    /**
     * Forgets what one request may leave in memory: this request's cache copies, a transaction left open, container
     * instances, the query watch, the lazy-loading warnings. The database connection stays open for the next request.
     */
    public static function endRequest()
    {
        Cache::clearMemory();
        Database::endRequest();
        Container::forgetInstances();
        Query::forgetShapes();
        if (method_exists(Model::class, 'forgetLazyWarnings')) {
            Model::forgetLazyWarnings();
        }
    }

    // the caller: a token, else a visitor (no session on this channel)
    private static function context(array $headers)
    {
        $authorization = (string) ($headers['authorization'] ?? '');
        if (preg_match('/^\s*Bearer\s+(\S+)/i', $authorization, $m) && Tokens::looksLikeToken($m[1])) {
            try {
                $token = Tokens::verify($m[1]);
            } catch (\Throwable $e) {
                $token = null;
            }
            return $token ? Context::forToken($token, 'rest') : Context::guest('rest');
        }
        return Context::guest('rest');
    }

    // the inputs: a JSON body (or a form) for a POST, the query string for a GET
    private static function input($method, $body, array $query)
    {
        if ($method !== 'POST' || trim((string) $body) === '') {
            return $query;
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            parse_str((string) $body, $data);
        }
        unset($data['_token'], $data['_method'], $data['uri']);
        return array_merge($query, is_array($data) ? $data : array());
    }

    private static function json($status, $body, array $headers = array())
    {
        return array('status' => $status, 'headers' => array('Content-Type' => 'application/json') + $headers,
            'body' => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
