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
        try {
            $entry = Capabilities::get($name);
            $context = Context::fromRequest($request, 'rest');
            if ($entry !== null && !$entry['capability']->readOnly && self::needsCsrf($request, $context) && !self::validCsrf($request)) {
                throw new ServiceError('forbidden', 'Missing or invalid CSRF token: send the X-CSRF-Token header');
            }
            $result = Capabilities::call($name, self::input($request), $context);
            self::send($request->method === 'POST' && $entry !== null && !$entry['capability']->readOnly ? 201 : 200, array('data' => $result));
        } catch (ServiceError $e) {
            if ($e->kind === 'unauthenticated' && !headers_sent()) {
                header('WWW-Authenticate: Bearer');
            }
            self::send($e->status(), $e->toArray());
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
        self::send(200, array('data' => Capabilities::describe($context), 'csrf' => $_SESSION['token'] ?? null));
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
        return array_merge($body, $route);
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
