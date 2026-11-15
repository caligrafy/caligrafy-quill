<?php
/**
 * Rest.php
 * The REST channel of capabilities
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Rest
 * Answers HTTP requests with capabilities, in JSON:
 * - Route::capability('POST', '/books', 'create_book') maps one route to one capability
 * - Route::capabilities('/capabilities') adds GET /capabilities (what the caller may call, with the input schemas, and
 *   the CSRF token of the session in "csrf") and
 *   POST /capabilities/{name} (call any capability by name: the JS client of the Vue apps uses it)
 *
 * Who calls: a personal access token (Authorization: Bearer cq_...), the legacy API key, or the signed-in user. Calls
 * made with the session cookie need the CSRF token in an X-CSRF-Token header (or _token) unless they only read.
 * Inputs: the JSON body (or form fields, or the query string for GET), then the route variables, which win.
 * Responses: 200 with the result ({"data": ...}), 201 when a POST creates, or {"error": {"code", "message", "fields"}}
 * with 401, 403, 404, 409 or 422.
 */
class Rest
{
    /**
     * Runs a capability for the current request and prints the response
     */
    public static function respond($name, $request = null)
    {
        $request = $request ?? request();
        $entry = Capabilities::get($name);
        $context = null;   // the caller, known once (a token is verified once)
        $answer = self::answer($name,
            function () use ($request, &$context) { return $context = Context::fromRequest($request, 'rest'); },
            function () use ($request) { return self::input($request); }, $request->method,
            function () use ($request, $entry, &$context) {
                return !($entry !== null && !$entry['capability']->readOnly && self::needsCsrf($request, $context) && !self::validCsrf($request));
            }, new Uploads($_FILES ?? array()));
        // cache (2.26.0): a public, read-only capability's answer to a visitor may be kept by shared caches
        if ($answer['status'] === 200 && $entry !== null && $entry['capability']->cache > 0 && $request->bearer() === null) {
            HttpCache::publicFor($entry['capability']->cache);
        }
        if ($answer['status'] === 401 && !headers_sent()) {
            header('WWW-Authenticate: Bearer');
        }
        self::send($answer['status'], $answer['body']);
    }

    /**
     * The answer to a call of a capability, without printing it (2.26.0: the web server prints it, the async server
     * sends it): ['status' => 200|201|4xx, 'body' => ['data' => ...] or ['error' => ...]]
     * @param callable $context returns the caller's Context
     * @param callable $input returns the inputs
     * @param string $method the HTTP method (a POST that creates answers 201)
     * @param callable|null $csrf returns false when a session caller's write lacks its CSRF token
     */
    public static function answer($name, callable $context, callable $input, $method = 'POST', ?callable $csrf = null, ?Uploads $uploads = null)
    {
        try {
            $entry = Capabilities::get($name);
            $caller = $context();
            if ($csrf !== null && !$csrf()) {
                throw new ServiceError('forbidden', 'Missing or invalid CSRF token: send the X-CSRF-Token header');
            }
            // file inputs (the `file` rule, 2.19.0): the uploaded files are saved once the caller is allowed
            $result = Capabilities::call($name, $input(), $caller, $uploads ?? new Uploads(array()));
            $creates = strtoupper((string) $method) === 'POST' && $entry !== null && !$entry['capability']->readOnly;
            return array('status' => $creates ? 201 : 200, 'body' => array('data' => $result));
        } catch (ServiceError $e) {
            return array('status' => $e->status(), 'body' => $e->toArray());
        }
    }

    /**
     * GET {prefix}: the capabilities the caller may call
     */
    public static function listing($request = null)
    {
        $request = $request ?? request();
        $context = Context::fromRequest($request, 'rest');
        // csrf: the token browser clients send back in X-CSRF-Token (readable only by pages of this origin)
        self::send(200, array('data' => Capabilities::describe($context), 'csrf' => session('token')));   // starts the session (2.19.0)
    }

    /**
     * The inputs: body or query string, then the route variables
     */
    public static function input($request)
    {
        $body = is_array($request->uriParameters ?? null) ? $request->uriParameters : array();
        unset($body['_token'], $body['_method'], $body['uri']);
        $route = is_array($request->currentBind ?? null) ? $request->currentBind : array();
        unset($route['uri']);
        foreach ($route as $key => $value) {
            if (is_int($key) || strpos((string) $key, '__') === 0) {
                unset($route[$key]);
            }
        }
        return array_merge($body, $route, self::routeVariables($request));
    }

    // The variables of the route's own pattern win over the query string, which Route also merges into the bind on a
    // GET (2.21.2): /books/3?id=9 is the book 3
    private static function routeVariables($request)
    {
        if (!is_string(Route::$currentRoute ?? null) || !is_string($request->uri ?? null)) {
            return array();
        }
        $variables = (array) bindRouteToVar(Route::$currentRoute, $request->uri);
        foreach ($variables as $key => $value) {
            if (is_int($key) || strpos((string) $key, '__') === 0) {
                unset($variables[$key]);
            }
        }
        return $variables;
    }

    private static function needsCsrf($request, Context $context)
    {
        // a token or the legacy key is not a cookie: a browser cannot send it on its own, so no CSRF risk
        return $request->bearer() === null && $context->authenticated();
    }

    private static function validCsrf($request)
    {
        $headers = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : array();
        $sent = $headers['x-csrf-token'] ?? ($_POST['_token'] ?? null);
        return is_string($sent) && isset($_SESSION['token']) && hash_equals($_SESSION['token'], $sent);
    }

    private static function send($status, $body)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
