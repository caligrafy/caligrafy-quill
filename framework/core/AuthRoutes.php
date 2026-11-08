<?php
/**
 * AuthRoutes.php
 * The sign-in pages without a controller: Route::auth()
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class AuthRoutes (2.19.0)
 * Route::auth() (after Auth::authentication('User', 'users') in web.php) registers, with the views of the
 * authentication module (application/views/Auth/login.pug and register.pug):
 *   GET  /login           the sign-in page (a signed-in person goes to 'home')
 *   POST /login           signs in: the username and passcode fields, checked with the model's findByUsername() and
 *                         verifyPassword() (an old encrypted passcode is upgraded to a hash); a new session id
 *                         (session_regenerate_id(true)) and a new CSRF token; then 'home'
 *   GET|POST /logout      signs out: a new session id and CSRF token, then 'logout'
 *   GET  /notAuthorized   where Auth::guard() sends people below the level a page needs (403)
 *   GET|POST /register    the sign-up page and the register_user capability, then the sign-in page ('register' => false: none)
 *   with 'passkeys' => true, the JSON endpoints of Caligrafy\Passkey (the passkeys table of the face login recipe, the
 *   page sends the X-CSRF-Token header; public/js/services/passkey.js in the browser):
 *   POST /passkey/register/options, /passkey/register (signed in), /passkey/login/options, /passkey/login
 * Failed sign-ins are counted per IP and username (Throttle, in the Cache): after 'attempts' (5) within 'lock' seconds
 * (900) that username is refused from that address (429) until the window ends.
 * Options: prefix (''), home ('/'), logout ('/'), register (true, or the name of the capability), attempts, lock,
 * views (['login' => 'Auth/login', 'register' => 'Auth/register']), passkeys (false).
 * A JSON request (Accept: application/json) gets JSON answers and needs the X-CSRF-Token header.
 * Not provided by the framework, so refused with a LogicException: 'face' (the face login recipe has its own
 * controller and tables) and 'reset' (password reset: the users table has no email address).
 */
class AuthRoutes
{
    // what a check against a password that does not exist costs: response times do not tell which usernames exist
    const DUMMY_HASH = '$2y$10$B0NLUgDUK3CbImwrVfa/FuOtSOKrb7DZTGG3USPSF4Comn4cy5RtG';

    /** @var callable|null replaces session_regenerate_id(true) (tests) */
    public static $regenerate = null;

    /**
     * The options with their defaults
     */
    public static function options(array $options)
    {
        if (!empty($options['face'])) {
            throw new \LogicException("Route::auth: the framework has no face sign-in routes: Caligrafy\\FaceLogin checks the liveness, and the routes and tables are those of the face login recipe (recipes-face-login.md)");
        }
        if (!empty($options['reset'])) {
            throw new \LogicException("Route::auth: password reset is not built in: the users table of the authentication module has no email address");
        }
        $o = array_merge(array('prefix' => '', 'home' => '/', 'logout' => '/', 'register' => true, 'attempts' => 5, 'lock' => 900,
            'views' => array(), 'passkeys' => false), $options);
        $o['prefix'] = rtrim('/' . trim((string) $o['prefix'], '/'), '/');
        $o['views'] = array_merge(array('login' => 'Auth/login', 'register' => 'Auth/register'), (array) $o['views']);
        if ($o['register'] === true) {
            $o['register'] = 'register_user';
        }
        return $o;
    }

    /**
     * Registers the routes (Route::auth)
     */
    public static function register(array $options = array())
    {
        $o = self::options($options);
        $p = $o['prefix'];
        Route::get("$p/login", function () use ($o) { self::loginPage($o); });
        Route::post("$p/login", function () use ($o) { self::login($o); });
        Route::get("$p/logout", function () use ($o) { self::logout($o); });
        Route::post("$p/logout", function () use ($o) { self::logout($o); });
        Route::get("$p/notAuthorized", function () use ($o) { self::notAuthorized($o); });
        if ($o['register']) {
            Route::get("$p/register", function () use ($o) { self::registerPage($o); });
            Route::post("$p/register", function () use ($o) { self::signUp($o); });
        }
        if ($o['passkeys']) {
            Route::post("$p/passkey/register/options", function () { self::passkey('registerOptions'); });
            Route::post("$p/passkey/register", function () { self::passkey('register'); });
            Route::post("$p/passkey/login/options", function () { self::passkey('loginOptions'); });
            Route::post("$p/passkey/login", function () { self::passkey('login'); });
        }
        return $o;
    }

    /**
     * Checks a username and a password, with the failures counted per IP and username
     * @param callable $find function (string $username): ?user (a model with verifyPassword())
     * @return array ['status' => 'ok', 'user' => $user] | ['status' => 'invalid'] | ['status' => 'locked']
     */
    public static function attempt($username, $password, $ip, Throttle $throttle, callable $find)
    {
        $username = trim((string) $username);
        $key = Throttle::key('signin', $ip, $username);
        if ($throttle->locked($key)) {
            return array('status' => 'locked');
        }
        $user = $username !== '' ? call_user_func($find, $username) : null;
        if (!$user) {
            password_verify((string) $password, self::DUMMY_HASH);
        }
        if (!$user || !$user->verifyPassword((string) $password)) {
            $throttle->fail($key);
            return array('status' => $throttle->locked($key) ? 'locked' : 'invalid');
        }
        $throttle->clear($key);
        return array('status' => 'ok', 'user' => $user);
    }

    /**
     * Signs a user in: a new session id (fixation), a new CSRF token, then Auth::authorize()
     * @param callable|null $authorize replaces Auth::authorize (tests)
     */
    public static function signIn($user, ?callable $authorize = null)
    {
        self::regenerate();
        $ok = call_user_func($authorize ?? array(Auth::class, 'authorize'), $user);
        return (bool) $ok;
    }

    /**
     * Signs out: Auth::unauthorize(), a new session id and a new CSRF token
     */
    public static function signOut(?callable $unauthorize = null)
    {
        call_user_func($unauthorize ?? array(Auth::class, 'unauthorize'));
        self::regenerate();
    }

    /**
     * session_regenerate_id(true) and a new CSRF token (self::$regenerate in tests)
     */
    public static function regenerate()
    {
        if (self::$regenerate !== null) {
            call_user_func(self::$regenerate);
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }

    /**
     * The address of the request (REMOTE_ADDR: a forwarded header can be written by anyone)
     */
    public static function ip()
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    // ---- the routes --------------------------------------------------------------------------------------------

    private static function loginPage(array $o)
    {
        if (self::signedIn()) {
            redirect($o['home']);
            return;
        }
        $data = array();
        if (($text = Flash::get('messages', array())['success'] ?? null) !== null) {
            $data = array('error' => true, 'status' => 'success', 'message_header' => '', 'message' => $text);
        }
        view($o['views']['login'], $data);
    }

    private static function login(array $o)
    {
        $request = request();
        $json = $request->wantsJson();
        if ($json && !$request->authorizeSessionRequest()) {
            return self::json(403, array('error' => array('code' => 'forbidden', 'message' => 'Missing or invalid CSRF token: send the X-CSRF-Token header')));
        }
        $model = self::model();
        $p = (array) $request->parameters;
        $text = function ($value) { return is_string($value) ? $value : ''; };
        $password = $text($p['passcode'] ?? ($p['password'] ?? ''));
        $result = self::attempt($text($p['username'] ?? ''), $password, self::ip(), new Throttle($o['attempts'], $o['lock']),
            function ($username) use ($model) { return $model->findByUsername($username); });
        if ($result['status'] !== 'ok') {
            $locked = $result['status'] === 'locked';
            $message = $locked ? 'Too many failed attempts: try again in ' . max(1, (int) ceil($o['lock'] / 60)) . ' minutes' : 'Invalid credentials';
            if ($json) {
                return self::json($locked ? 429 : 401, array('error' => array('code' => $locked ? 'locked' : 'unauthenticated', 'message' => $message)));
            }
            if ($locked) {
                http_response_code(429);
            }
            return view($o['views']['login'], array('error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => $message));
        }
        $user = self::upgrade($model, $result['user'], $password);
        self::signIn($user);
        if ($json) {
            return self::json(200, array('data' => array('user' => self::profile($user))));
        }
        redirect($o['home']);
    }

    private static function logout(array $o)
    {
        self::signOut();
        if (request()->wantsJson()) {
            return self::json(200, array('data' => array('signedOut' => true)));
        }
        redirect($o['logout']);
    }

    private static function notAuthorized(array $o)
    {
        http_response_code(403);
        view($o['views']['login'], array('error' => true, 'status' => 'danger', 'message_header' => 'Restricted Page', 'message' => 'You have not been granted permission to access this page'));
    }

    private static function registerPage(array $o)
    {
        if (self::signedIn()) {
            redirect($o['home']);
            return;
        }
        view($o['views']['register']);
    }

    private static function signUp(array $o)
    {
        $request = request();
        $json = $request->wantsJson();
        if ($json && !$request->authorizeSessionRequest()) {
            return self::json(403, array('error' => array('code' => 'forbidden', 'message' => 'Missing or invalid CSRF token: send the X-CSRF-Token header')));
        }
        $p = (array) $request->parameters;
        $user = (object) array('username' => (string) ($p['username'] ?? ''));
        $page = array('error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'user' => $user);
        if (array_key_exists('confirmpasscode', $p) && (string) ($p['passcode'] ?? '') !== (string) $p['confirmpasscode']) {
            $error = ServiceError::invalid(array('passcode' => 'passwords don\'t match'), 'The passwords you entered don\'t match. Make sure that the password and the confirm password fields match.');
            return $json ? self::json(422, $error->toArray()) : view($o['views']['register'], $page + array('message' => $error->getMessage(), 'errors' => $error->fields));
        }
        try {
            $profile = Capabilities::call($o['register'], array('username' => $p['username'] ?? '', 'passcode' => $p['passcode'] ?? ''), Context::fromRequest($request, 'web'));
        } catch (ServiceError $e) {
            if ($json) {
                return self::json($e->status(), $e->toArray());
            }
            $message = $e->kind === 'conflict' ? 'You are already registered' : ($e->kind === 'invalid' ? 'Some of the inputs are invalid. Make sure all the required inputs are entered properly' : $e->getMessage());
            $errors = $e->kind === 'conflict' ? array('username' => $e->getMessage()) : $e->fields;
            return view($o['views']['register'], $page + array('message' => $message, 'errors' => $errors));
        }
        if ($json) {
            return self::json(201, array('data' => $profile));
        }
        Flash::message('success', 'Your account is ready: sign in');
        redirect($o['prefix'] . '/login');
    }

    /**
     * The passkey endpoints: the ceremonies of Caligrafy\Passkey, a new session at sign-in
     */
    private static function passkey($step)
    {
        $request = request();
        if (!$request->authorizeSessionRequest()) {
            return self::json(403, array('error' => 'Missing or invalid CSRF token: send the X-CSRF-Token header'));
        }
        $p = (array) $request->parameters;
        try {
            switch ($step) {
                case 'registerOptions':
                case 'register':
                    if (!self::signedIn()) {
                        return self::json(401, array('error' => 'Sign in first'));
                    }
                    $user = Auth::user();
                    if ($step === 'registerOptions') {
                        return self::json(200, (new Passkey())->registrationOptions($user->id, $user->username));
                    }
                    $credential = (new Passkey())->register($p, $user->id, $p['name'] ?? null);
                    return self::json(201, array('passkey' => array('name' => $credential['name'] ?? 'Passkey')));
                case 'loginOptions':
                    return self::json(200, (new Passkey())->authenticationOptions());
                default:
                    $userId = (new Passkey())->authenticate($p);
                    $user = Auth::find($userId);
                    if (!$user) {
                        return self::json(401, array('error' => 'Unknown account'));
                    }
                    self::signIn($user);
                    return self::json(200, array('user' => self::profile($user)));
            }
        } catch (PasskeyException $e) {
            $status = (int) $e->getCode();
            return self::json($status >= 400 && $status < 600 ? $status : 400, array('error' => $e->getMessage()));
        }
    }

    // ---- helpers -----------------------------------------------------------------------------------------------

    private static function model()
    {
        $model = Auth::model();
        if ($model === null) {
            throw new \LogicException("Route::auth needs Auth::authentication('User', 'users') in web.php");
        }
        if (!method_exists($model, 'findByUsername') || !method_exists($model, 'verifyPassword')) {
            throw new \LogicException('Route::auth: the authentication model needs findByUsername() and verifyPassword(), as the User model of the authentication module');
        }
        return $model;
    }

    private static function signedIn()
    {
        return Auth::active() && Auth::authorized();
    }

    // passcodes stored by older versions (reversible encryption) become a password hash
    private static function upgrade($model, $user, $password)
    {
        if (method_exists($user, 'needsRehash') && $user->needsRehash() && method_exists($user, 'hashPassword')) {
            $model->update(array('passcode' => $user::hashPassword($password)), $user->id);
            $user = $model->find($user->id) ?: $user;
        }
        return $user;
    }

    private static function profile($user)
    {
        $attribute = Context::$levelAttribute;
        return array('id' => $user->id ?? null, 'username' => $user->username ?? null, 'permissions' => (int) ($user->$attribute ?? 0));
    }

    private static function json($status, $body)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return null;
    }
}
