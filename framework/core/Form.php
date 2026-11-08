<?php
/**
 * Form.php
 * The web channel of capabilities for forms: Route::form()
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Form (2.19.0)
 * Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']) answers the form's POST
 * (or its PUT / DELETE through _method) with a capability, without a controller:
 * 1. the CSRF token of the form is checked by the request, as for every form (error 3000, 403)
 * 2. the capability is called with the form fields, the files of its `file` inputs (Uploads) and the route variables
 *    (which win), as the person who sent the form (Context::fromRequest(..., 'web'))
 * 3. success: a redirect to 'success' (a path whose {placeholders} are filled from the result, then from the route
 *    variables, or a callable($result) returning the path; without it, back to the form), with the 'flash' message
 *    (also with placeholders) for flash('success')
 * 4. invalid input (or a conflict): back to the form ('back', else the Referer of this site, else /) with the field
 *    errors, the message and the old input for the next request: errors($field), old($field), flash('error')
 * 5. a visitor where the capability needs someone: to the sign-in page ('signIn', default /login) when Auth is active,
 *    else 403; forbidden: 403 (error 1006); not found: 404 (error 1005)
 * A JSON request (Accept: application/json) gets the REST answer instead of redirects: {"data": ...} with 200 (201 for a
 * POST that changes data), or {"error": ...} with 401, 403, 404, 409 or 422. Sent with the session cookie, it needs the
 * X-CSRF-Token header, as REST.
 */
class Form
{
    /** fields never kept as old input */
    const SECRET = '/pass|secret|token|card|cvc|cvv|iban/i';

    /**
     * Answers the current request (the route's closure)
     */
    public static function handle($capability, array $options = array(), $request = null)
    {
        $request = $request ?? request();
        $headers = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : array();
        $outcome = self::outcome($capability, $options, Rest::input($request), Context::fromRequest($request, 'web'), $request->wantsJson(), array(
            'referer' => (string) ($headers['referer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')),
            'host' => (string) ($request->host ?: ($_SERVER['HTTP_HOST'] ?? '')),
            'route' => PageController::routeVariables(),
            'bearer' => $request->bearer() !== null,
            'csrf' => $request->authorizeSessionRequest(),
            'method' => $request->method,
            'uploads' => new Uploads($_FILES ?? array()),
        ));
        return self::perform($outcome);
    }

    /**
     * What a form post leads to, without answering it (testable without a server). Flash data is written to the session.
     * @param array $env referer, host, route (route variables), bearer (bool), csrf (bool: X-CSRF-Token is the
     *                   session's), method, uploads (Uploads|null)
     * @return array ['redirect' => url] | ['status' => int, 'json' => array] | ['error' => 1005|1006]
     */
    public static function outcome($capability, array $options, array $input, Context $context, $json, array $env = array())
    {
        $route = (array) ($env['route'] ?? array());
        $entry = Capabilities::get($capability);
        if ($entry === null) {
            throw new \LogicException("Route::form: no capability named '$capability'");
        }
        $readOnly = $entry['capability']->readOnly;
        try {
            // JSON with the session cookie: the X-CSRF-Token header (a form post was checked by the request already)
            if ($json && !$readOnly && empty($env['bearer']) && $context->authenticated() && empty($env['csrf'])) {
                throw new ServiceError('forbidden', 'Missing or invalid CSRF token: send the X-CSRF-Token header');
            }
            $result = Capabilities::call($capability, $input, $context, $env['uploads'] ?? null);
        } catch (ServiceError $e) {
            return self::refused($e, $options, $input, $json, $env);
        }

        if ($json) {
            return array('status' => strtoupper((string) ($env['method'] ?? 'POST')) === 'POST' && !$readOnly ? 201 : 200, 'json' => array('data' => $result));
        }
        $values = array_merge($route, is_array($result) ? array_filter($result, 'is_scalar') : array('result' => $result));
        $success = $options['success'] ?? null;
        if (is_callable($success) && !is_string($success)) {
            $to = (string) call_user_func($success, $result);
        } elseif (is_string($success) && $success !== '') {
            $to = self::fill($success, $values, true);
        } else {
            $to = self::back($options, $env);
        }
        foreach (self::messages($options['flash'] ?? null, 'success') as $key => $text) {
            Flash::message($key, self::fill($text, $values, false));
        }
        return array('redirect' => $to);
    }

    /**
     * Answers an outcome
     */
    public static function perform(array $outcome)
    {
        if (isset($outcome['error'])) {
            throw Errors::raise($outcome['error']);
        }
        if (isset($outcome['json'])) {
            if (!headers_sent()) {
                http_response_code($outcome['status']);
                header('Content-Type: application/json');
                if ($outcome['status'] === 401) {
                    header('WWW-Authenticate: Bearer');
                }
            }
            echo json_encode($outcome['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return null;
        }
        redirect($outcome['redirect']);
        return null;
    }

    /**
     * Fills {name} placeholders: Form::fill('/books/{id}', ['id' => 3]) is /books/3
     * @param bool $encode url-encode the values (paths)
     * @throws \LogicException when a placeholder has no value
     */
    public static function fill($template, array $values, $encode = true)
    {
        return preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', function ($m) use ($values, $encode, $template) {
            if (!array_key_exists($m[1], $values) || !is_scalar($values[$m[1]])) {
                throw new \LogicException("Route::form: '$template' needs {{$m[1]}}, which neither the result nor the route has");
            }
            $value = is_bool($values[$m[1]]) ? ($values[$m[1]] ? '1' : '0') : (string) $values[$m[1]];
            return $encode ? rawurlencode($value) : $value;
        }, (string) $template);
    }

    /**
     * Where "back" is: the 'back' option (with the route variables), else the Referer when it is a page of this site,
     * else the home page
     */
    public static function back(array $options, array $env)
    {
        if (!empty($options['back'])) {
            return self::fill((string) $options['back'], (array) ($env['route'] ?? array()), true);
        }
        $referer = (string) ($env['referer'] ?? '');
        if ($referer !== '' && self::sameSite($referer, (string) ($env['host'] ?? ''))) {
            return $referer;
        }
        return '/';
    }

    /**
     * Whether a URL is a page of this host (host and port): never redirect elsewhere from a Referer
     */
    public static function sameSite($url, $host)
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
            return false;
        }
        $origin = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        return $host !== '' && $origin === strtolower(trim($host));
    }

    /**
     * The input kept for old(): no secrets, no files, no framework fields
     */
    public static function oldInput(array $input)
    {
        $old = array();
        foreach ($input as $key => $value) {
            if (!is_string($key) || $key === '' || $key[0] === '_' || $key === 'uri' || preg_match(self::SECRET, $key)) {
                continue;
            }
            if (is_scalar($value) || $value === null || (is_array($value) && array_filter($value, function ($v) { return !is_scalar($v) && $v !== null; }) === array())) {
                $old[$key] = $value;
            }
        }
        return $old;
    }

    private static function refused(ServiceError $e, array $options, array $input, $json, array $env)
    {
        if ($json) {
            return array('status' => $e->status(), 'json' => $e->toArray());
        }
        switch ($e->kind) {
            case 'invalid':
            case 'conflict':
                Flash::put('errors', $e->fields);
                Flash::put('old', self::oldInput($input));
                foreach (self::messages($options['error'] ?? $e->getMessage(), 'error') as $key => $text) {
                    Flash::message($key, $text);
                }
                return array('redirect' => self::back($options, $env));
            case 'unauthenticated':
                if (Auth::active()) {
                    return array('redirect' => (string) ($options['signIn'] ?? '/login'));
                }
                return array('error' => 1006);
            case 'forbidden':
                return array('error' => 1006);
            default:
                return array('error' => 1005);
        }
    }

    // 'Saved' -> [$default => 'Saved'], ['saved' => 'Saved'] as it is
    private static function messages($option, $default)
    {
        if ($option === null || $option === '' || $option === false) {
            return array();
        }
        return is_array($option) ? $option : array($default => (string) $option);
    }
}
