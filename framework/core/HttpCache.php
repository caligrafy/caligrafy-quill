<?php
/**
 * HttpCache.php
 * Public answers that shared caches may keep (2.26.0)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class HttpCache
 * A capability declared with cache (#[Capability(..., readOnly: true, public: true, cache: 30)]) answered over REST, or
 * a page declared with Route::page(..., ['cache' => 30]), is sent with Cache-Control: public, max-age=30 when the
 * request is a GET or HEAD with no session cookie and no Authorization header: the production image's Apache cache
 * (docker/production/cache.conf), a CDN or a proxy then answer the next visitors from memory, without PHP.
 *
 * Never kept: an answer to a signed-in person or a token (it is not marked), an answer that sets a cookie (a session
 * started while answering: a form's CSRF token) and any answer other than 200: guard() turns them into
 * Cache-Control: private, no-store just before the headers are sent. Vary: Cookie, Authorization keeps the copy for
 * visitors without them.
 */
class HttpCache
{
    const MAX = 86400;

    private static $armed = false;

    /**
     * Marks the current answer public for $seconds when this request may share it
     * @return bool whether it was marked
     */
    public static function publicFor($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds <= 0 || headers_sent() || !self::shareable()) {
            return false;
        }
        $seconds = min($seconds, self::MAX);
        header("Cache-Control: public, max-age=$seconds, s-maxage=$seconds");
        header('Vary: Cookie, Authorization');
        if (!self::$armed) {
            self::$armed = true;
            header_register_callback(array(__CLASS__, 'guard'));
        }
        return true;
    }

    /**
     * Whether this request may receive a shared answer: GET or HEAD, no session cookie, no Authorization header
     */
    public static function shareable()
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }
        if (isset($_COOKIE[session_name()]) || !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return false;
        }
        return true;
    }

    /**
     * Right before the headers go out: an answer that sets a cookie or is not a 200 is never kept
     */
    public static function guard()
    {
        $public = false;
        $cookie = false;
        foreach (headers_list() as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                $cookie = true;
            } elseif (stripos($header, 'Cache-Control:') === 0 && stripos($header, 'public') !== false) {
                $public = true;
            }
        }
        if ($public && ($cookie || http_response_code() !== 200)) {
            header('Cache-Control: private, no-store');
        }
    }

    /**
     * For tests: forget that the header callback is registered
     */
    public static function reset()
    {
        self::$armed = false;
    }
}
