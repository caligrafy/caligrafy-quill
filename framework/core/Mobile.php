<?php
/**
 * Mobile.php
 * Phone and tablet apps (2.27.0): signing in from an app, its personal access token, and the calls it makes from
 * another origin (CORS)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Mobile
 * Route::mobile(['scheme' => 'com.example.books']) (after Route::auth() in web.php) lets the iOS and Android apps of
 * php caligrafer.php mobile <name> sign their user in and call the capabilities as that user:
 *   GET  /mobile/authorize   opened by the app in the system browser: sign in (the site's own /login), then a page asks
 *                            "Sign in to <app> on this device?". Allow sends the browser to <scheme>://auth?code=...&state=...
 *   POST /mobile/authorize   that page's answer (the session's CSRF token): Allow or Not now
 *   POST /mobile/token       the app trades the code (one use, 5 minutes) and its PKCE verifier for a personal access
 *                            token of that user (Tokens::create: "Mobile: <device>", the 'scopes', 'days' days)
 *   GET  /mobile/session     who the app's token signs in (401 when it no longer works)
 *   (redirect=http://localhost:5173 instead of a scheme: the app in a browser while it is built, only when this site is
 *   served on this computer: the code comes back to <redirect>/?mobile_auth=1&state=...&code=...)
 *   POST /mobile/signout     revokes the app's own token, and forgets the phone's push registration
 * The app sends Authorization: Bearer cq_... to /capabilities (Route::capabilities) like any program; no cookie and no
 * CSRF token. The browsers of the app (capacitor://localhost, https://localhost, http://localhost[:port]) and the
 * 'origins' option get CORS headers, never Access-Control-Allow-Credentials: the session cookie never leaves the site.
 * A project without accounts (no Auth::authentication, 2.27.2) gets the CORS only: its apps call the public
 * capabilities, with no sign-in and no token.
 * Codes: PKCE S256 (RFC 7636), the challenge given to /authorize, the verifier only to /token. Tables
 * caligrafy_mobile_codes (php caligrafer.php platform install; the deploy step creates them).
 */
class Mobile
{
    const CODES = 'caligrafy_mobile_codes';
    const CODE_SECONDS = 300;
    const DEFAULT_DAYS = 90;
    const APP_ORIGINS = array('capacitor://localhost', 'ionic://localhost', 'https://localhost', 'http://localhost');
    const VIEW = '../../framework/views/mobile/authorize';

    /** @var array|null the options of Route::mobile (null: no Route::mobile, no CORS) */
    public static $options = null;

    /** @var callable|null replaces header() and exit in tests: fn(int $status, array $headers) */
    public static $respond = null;

    /**
     * The options with their defaults: scheme (required: the app's URL scheme, its bundle id is a good one), scopes
     * (['*']), days (90), prefix ('/mobile'), origins (more origins allowed to call with a token), name (APP_NAME, else the site's address)
     */
    public static function options(array $options)
    {
        $o = array_merge(array('scheme' => null, 'scopes' => array('*'), 'days' => self::DEFAULT_DAYS, 'prefix' => '/mobile',
            'origins' => array(), 'name' => null), $options);
        $schemes = array_values(array_filter(array_map('strtolower', array_map('trim', (array) $o['scheme'])), 'strlen'));
        if (!$schemes) {
            throw new \InvalidArgumentException("Route::mobile: 'scheme' is required: the URL scheme of the app (its app id, such as 'com.example.books'), the one capacitor.config.json declares");
        }
        foreach ($schemes as $scheme) {
            if (!self::validScheme($scheme)) {
                throw new \InvalidArgumentException("Route::mobile: '$scheme' is not an app's URL scheme: letters, digits, + - . (3 to 64), starting with a letter, never http, https, file, javascript or data");
            }
        }
        $o['scheme'] = $schemes;
        $o['scopes'] = Tokens::normalizeScopes($o['scopes']);
        if (!$o['scopes']) {
            throw new \InvalidArgumentException("Route::mobile: 'scopes' grants the app at least one scope ('*', 'books:read'...)");
        }
        if (!is_int($o['days']) && !ctype_digit((string) $o['days']) || (int) $o['days'] < 1 || (int) $o['days'] > 400) {
            throw new \InvalidArgumentException("Route::mobile: 'days' is how long the app stays signed in, 1 to 400 days");
        }
        $o['days'] = (int) $o['days'];
        $o['prefix'] = rtrim('/' . trim((string) $o['prefix'], '/'), '/');
        if ($o['prefix'] === '') {
            throw new \InvalidArgumentException("Route::mobile: 'prefix' is a path such as '/mobile'");
        }
        $origins = array();
        foreach ((array) $o['origins'] as $origin) {
            $origin = rtrim(strtolower(trim((string) $origin)), '/');
            if (!preg_match('#^[a-z][a-z0-9+.-]*://[a-z0-9.-]+(:\d{1,5})?$#', $origin)) {
                throw new \InvalidArgumentException("Route::mobile: '$origin' is not an origin (scheme://host[:port], no path)");
            }
            $origins[] = $origin;
        }
        $o['origins'] = $origins;
        // the app's name on the consent page: the option, APP_NAME, else the site's address (Mobile::appName())
        $o['name'] = trim((string) ($o['name'] ?? '')) ?: trim((string) (defined('APP_NAME') && APP_NAME ? APP_NAME : getenv('APP_NAME')));
        return $o;
    }

    /**
     * The name the consent page and the refusals give the app: Route::mobile's 'name', APP_NAME, else this site's address
     */
    public static function appName()
    {
        $name = self::$options['name'] ?? '';
        return $name !== '' ? $name : (preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?: 'this site');
    }

    public static function validScheme($scheme)
    {
        return is_string($scheme) && preg_match('/^[a-z][a-z0-9+.-]{2,63}$/', $scheme) === 1
            && !in_array($scheme, array('http', 'https', 'file', 'javascript', 'data', 'about', 'blob', 'ftp', 'mailto', 'tel', 'sms'), true);
    }

    /**
     * Registers the routes (Route::mobile)
     */
    public static function register(array $options, $accounts = true)
    {
        $o = self::options($options);
        $o['accounts'] = (bool) $accounts;
        self::$options = $o;
        if (!$o['accounts']) {
            return $o;      // an app without accounts (2.27.2): its phone apps call the public capabilities, CORS only
        }
        $p = $o['prefix'];
        Route::get("$p/authorize", function () { self::authorizePage(); });
        Route::post("$p/authorize", function () { self::authorize(); });
        Route::post("$p/token", function () { self::token(); });
        Route::get("$p/session", function () { self::session(); });
        Route::post("$p/signout", function () { self::signOut(); });
        return $o;
    }

    // ---- CORS ---------------------------------------------------------------------------------------------------

    /**
     * Whether an origin may call the app with a token: the app's own web views, this computer while developing, and
     * the 'origins' option
     */
    public static function allowedOrigin($origin)
    {
        if (self::$options === null || !is_string($origin) || $origin === '') {
            return false;
        }
        $origin = strtolower($origin);
        if (in_array($origin, self::APP_ORIGINS, true) || in_array($origin, self::$options['origins'], true)) {
            return true;
        }
        // the development server of the app (npm run dev), any port of this computer
        return preg_match('#^http://(localhost|127\.0\.0\.1)(:\d{1,5})?$#', $origin) === 1;
    }

    /**
     * The CORS headers of a request (Route::run calls it first): an allowed Origin gets them, and its preflight
     * (OPTIONS) is answered here with 204. Never Access-Control-Allow-Credentials: an app calls with its token
     * @return bool true when the request was answered (a preflight)
     */
    public static function cors()
    {
        if (self::$options === null) {
            return false;
        }
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $headers = array('Vary' => 'Origin');
        $allowed = self::allowedOrigin($origin);
        if ($allowed) {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }
        if ($method === 'OPTIONS' && $origin !== '') {
            if ($allowed) {
                $headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, DELETE, OPTIONS';
                $headers['Access-Control-Allow-Headers'] = 'Authorization, Content-Type, Accept, X-CSRF-Token';   // the JS client sends the last one (2.27.2)
                $headers['Access-Control-Max-Age'] = '600';
            }
            self::send($allowed ? 204 : 403, $headers);
            return true;
        }
        self::send(null, $headers);
        return false;
    }

    // ---- the routes -----------------------------------------------------------------------------------------------

    /**
     * What /authorize was given, checked: scheme (one of Route::mobile's), state, challenge, device
     * @return array|string the values, or why they are refused
     */
    public static function authorizeInput(array $source)
    {
        $o = self::$options;
        $get = function ($name) use ($source) { return is_string($source[$name] ?? null) ? trim($source[$name]) : ''; };
        $scheme = strtolower($get('scheme'));
        $redirect = rtrim(strtolower($get('redirect')), '/');
        if ($redirect !== '') {
            // the app in a browser while it is built (npm run dev): only a site served on this computer sends the code
            // back to this computer's development server
            if (!self::developmentRedirect($redirect)) {
                return 'This link was not opened by an app of ' . self::appName() . '.';
            }
            $scheme = '';
        } else {
            if ($scheme === '' && count($o['scheme']) === 1) {
                $scheme = $o['scheme'][0];
            }
            if (!in_array($scheme, $o['scheme'], true)) {
                return 'This link was not opened by an app of ' . self::appName() . '.';
            }
        }
        $challenge = $get('challenge');
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge) || $get('method') !== '' && $get('method') !== 'S256') {
            return 'The app sent an incomplete sign-in request. Close this page and try again from the app.';
        }
        $state = $get('state');
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state)) {
            return 'The app sent an incomplete sign-in request. Close this page and try again from the app.';
        }
        $device = mb_substr(preg_replace('/[^\p{L}\p{N} ._\'()-]/u', '', $get('device')), 0, 60);
        return array('scheme' => $scheme, 'redirect' => $redirect, 'challenge' => $challenge, 'state' => $state,
            'device' => trim($device) !== '' ? trim($device) : 'a phone',
            'back' => ($redirect !== '' ? $redirect . '/?mobile_auth=1&' : $scheme . '://auth?') . 'state=' . rawurlencode($state));
    }

    /**
     * Whether a development server may get the code: http://localhost[:port] (or 127.0.0.1), and only when this site is
     * itself served on this computer (php caligrafer.php server start): never online
     */
    public static function developmentRedirect($redirect)
    {
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        return preg_match('#^http://(localhost|127\.0\.0\.1)(:\d{1,5})?$#', (string) $redirect) === 1
            && in_array($host, array('localhost', '127.0.0.1'), true);
    }

    private static function authorizePage()
    {
        $input = self::authorizeInput($_GET);
        if (is_string($input)) {
            return self::message(400, 'This sign-in link does not work', $input);
        }
        (new PageController())->render(self::VIEW, array('signedIn' => true, 'comeBack' => true,
            'with' => array('mobile' => $input + array('app' => self::appName(), 'action' => ltrim(self::$options['prefix'], '/') . '/authorize'))));
    }

    private static function authorize()
    {
        if (!request()->authorizeSessionRequest()) {
            return self::message(403, 'This page expired', 'Close it and sign in again from the app.');
        }
        if (!Auth::active() || !Auth::authorized() || !($user = Auth::user())) {
            return self::message(401, 'You are signed out', 'Close this page and sign in again from the app.');
        }
        $input = self::authorizeInput($_POST);
        if (is_string($input)) {
            return self::message(400, 'This sign-in link does not work', $input);
        }
        $back = $input['back'];
        if (($_POST['decision'] ?? '') !== 'allow') {
            return self::send(302, array('Location' => $back . '&error=access_denied'));
        }
        $code = self::issueCode($user->id, $input['challenge'], $input['device']);
        self::send(302, array('Location' => $back . '&code=' . $code));
    }

    /**
     * A one-time code for a user (5 minutes): only its hash is kept
     */
    public static function issueCode($userId, $challenge, $device)
    {
        $code = bin2hex(random_bytes(32));
        Database::execute('INSERT INTO ' . self::CODES . ' (code_hash, user_id, challenge, device, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
            array(hash('sha256', $code), (int) $userId, $challenge, $device, date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() + self::CODE_SECONDS)));
        if (random_int(1, 20) === 1) {
            Database::execute('DELETE FROM ' . self::CODES . ' WHERE expires_at < ?', array(date('Y-m-d H:i:s')));
        }
        return $code;
    }

    /**
     * Trades a code and its PKCE verifier for a token: used once, whatever the answer
     * @return array ['token' => 'cq_...', 'expires_at' => ..., 'user_id' => ...]
     * @throws ServiceError invalid (a malformed code or verifier) or unauthenticated (unknown, used, expired, wrong verifier)
     */
    public static function exchange($code, $verifier)
    {
        if (!is_string($code) || !preg_match('/^[a-f0-9]{64}$/', $code)) {
            throw ServiceError::invalid(array('code' => 'The code the sign-in page gave the app'));
        }
        if (!is_string($verifier) || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier)) {
            throw ServiceError::invalid(array('verifier' => 'The PKCE verifier: 43 to 128 letters, digits, - . _ ~'));
        }
        $hash = hash('sha256', $code);
        $rows = Database::toArray(Database::execute('SELECT * FROM ' . self::CODES . ' WHERE code_hash = ?', array($hash)));
        Database::execute('DELETE FROM ' . self::CODES . ' WHERE code_hash = ?', array($hash));
        $row = $rows[0] ?? null;
        // a code is used once: the DELETE that took it is the one that may answer (two calls at once: one wins)
        if (!$row || Database::rowCount() < 1 || Tokens::expired($row['expires_at'])) {
            throw ServiceError::unauthenticated('This sign-in expired or was already used: sign in again');
        }
        if (!hash_equals((string) $row['challenge'], self::challenge($verifier))) {
            throw ServiceError::unauthenticated('This sign-in does not belong to this app: sign in again');
        }
        $o = self::$options ?? self::options(array('scheme' => 'app.caligrafy'));
        $expires = time() + $o['days'] * 86400;
        $token = Tokens::create('Mobile: ' . mb_substr((string) $row['device'], 0, 60), $o['scopes'], (int) $row['user_id'], $expires);
        return array('token' => $token['token'], 'token_id' => $token['id'], 'expires_at' => Tokens::expiresAt($expires), 'user_id' => (int) $row['user_id']);
    }

    /**
     * The PKCE S256 challenge of a verifier: base64url(sha256(verifier)), no padding
     */
    public static function challenge($verifier)
    {
        return rtrim(strtr(base64_encode(hash('sha256', (string) $verifier, true)), '+/', '-_'), '=');
    }

    private static function token()
    {
        $p = (array) (request()->parameters ?? array());
        try {
            $result = self::exchange($p['code'] ?? null, $p['verifier'] ?? null);
        } catch (ServiceError $e) {
            return self::json($e->status(), $e->toArray());
        }
        $user = Auth::active() ? Auth::find($result['user_id']) : null;
        self::json(200, array('data' => array('token' => $result['token'], 'expires_at' => $result['expires_at'], 'user' => self::profile($user, $result['user_id']))));
    }

    private static function session()
    {
        $token = self::bearerToken();
        if (!$token) {
            return self::json(401, array('error' => array('code' => 'unauthenticated', 'message' => 'Sign in again from the app')));
        }
        $user = !empty($token['user_id']) && Auth::active() ? Auth::find($token['user_id']) : null;
        if (!empty($token['user_id']) && Auth::active() && !$user) {
            return self::json(401, array('error' => array('code' => 'unauthenticated', 'message' => 'This account no longer exists')));
        }
        self::json(200, array('data' => array('user' => self::profile($user, $token['user_id']), 'expires_at' => $token['expires_at'], 'scopes' => $token['scopes'])));
    }

    private static function signOut()
    {
        $token = self::bearerToken();
        if ($token) {
            Tokens::revoke($token['id']);
            Notify::forgetDevicesOfToken($token['id']);
        }
        // signed out either way: an app whose token no longer works is signed out already
        self::json(200, array('data' => array('signed_out' => true)));
    }

    /**
     * The verified token of the request (Bearer cq_...), or null
     */
    public static function bearerToken()
    {
        $bearer = request()->bearer();
        if ($bearer === null || !Tokens::looksLikeToken($bearer)) {
            return null;
        }
        try {
            return Tokens::verify($bearer);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function profile($user, $id)
    {
        $attribute = Context::$levelAttribute;
        return array('id' => $user->id ?? ($id !== null ? (int) $id : null), 'username' => $user->username ?? null, 'permissions' => (int) ($user->$attribute ?? 0));
    }

    // ---- answers --------------------------------------------------------------------------------------------------

    private static function json($status, array $body)
    {
        self::send($status, array('Content-Type' => 'application/json', 'Cache-Control' => 'no-store'));
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function message($status, $title, $text)
    {
        self::send($status, array('Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store'));
        $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES); };
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($title) . '</title></head>'
            . '<body style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:520px;margin:15vh auto;padding:0 24px;color:#111">'
            . '<h1 style="font-size:24px">' . $e($title) . '</h1><p style="font-size:16px;line-height:1.5">' . $e($text) . '</p></body></html>';
    }

    private static function send($status, array $headers)
    {
        if (self::$respond) {
            call_user_func(self::$respond, $status, $headers);
            return;
        }
        if (headers_sent()) {
            return;
        }
        if ($status !== null) {
            http_response_code($status);
        }
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
    }
}
