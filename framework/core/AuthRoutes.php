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
 *   POST /login           signs in: the username (or 'identifier') and passcode fields, checked with the model's
 *                         findByUsername() (another identifier: its findBy($column, $value), else the query builder)
 *                         and verifyPassword() (an old encrypted passcode is upgraded to a hash); a new session id
 *                         (session_regenerate_id(true)) and a new CSRF token; then 'home'
 *   GET|POST /logout      signs out: a new session id and CSRF token, then 'logout'
 *   GET  /notAuthorized   where Auth::guard() sends people below the level a page needs (403)
 *   GET|POST /register    the sign-up page and the register_user capability (every input its rules declare), then the
 *                         sign-in page, or signed in and 'home' with 'signInAfterRegister' ('register' => false: none)
 *   with 'passkeys' => true, the JSON endpoints of Caligrafy\Passkey (the passkeys table of the face login recipe, the
 *   page sends the X-CSRF-Token header; public/js/services/passkey.js in the browser):
 *   POST /passkey/register/options, /passkey/register (signed in), /passkey/login/options, /passkey/login
 * Failed sign-ins are counted per IP and username (Throttle, in the Cache): after 'attempts' (5) within 'lock' seconds
 * (900) that username is refused from that address (429) until the window ends.
 * Options: prefix (''), home ('/'), logout ('/'), register (true, or the name of the capability), attempts, lock,
 * views (['login' => 'Auth/login', 'register' => 'Auth/register']), passkeys (false), identifier ('username': the
 * users column people sign in with and the name of its field, 'email' for instance), signInAfterRegister (false),
 * attemptsPerUser (2.19.6: failures of one username from any address before it is locked, 0 = off), level (2.19.5: the lowest permission level that may sign in, 0 by default; below it, the sign-in is refused like a
 * wrong password), messages (what sign-in and sign-up say: 'invalid', 'locked' ({minutes} is the wait), 2.19.3; 'mismatch',
 * 'register_invalid', 'register_taken', 'registered', 2.19.4; see AuthRoutes::MESSAGES).
 * A failed sign-in shows the page again with what the person typed in the identifier field (never the password).
 * A JSON request (Accept: application/json) gets JSON answers and needs the X-CSRF-Token header.
 * Not provided by the framework, so refused with a LogicException: 'face' (the face login recipe has its own
 * controller and tables) and 'reset' (password reset: the users table has no email address).
 */
class AuthRoutes
{
    /** the session key of the page to go back to after signing in (2.20.0) */
    const RETURN_TO = '__auth_return';

    // what a check against a password that does not exist costs: response times do not tell which usernames exist
    const DUMMY_HASH = '$2y$10$B0NLUgDUK3CbImwrVfa/FuOtSOKrb7DZTGG3USPSF4Comn4cy5RtG';

    // what sign-in and sign-up say (human words; the 'messages' option replaces any of them): a failed sign-in
    // (2.19.3), a locked one ({minutes} is the wait), and the sign-up page (2.19.4): passwords that differ, inputs to fix,
    // an account that already exists, and the note on the sign-in page once the account is created
    const MESSAGES = array(
        'invalid' => "We couldn't sign you in with those details. Check them and try again.",
        'locked' => 'Too many tries in a row. For your security, wait {minutes} minutes, then try again.',
        'mismatch' => "The two passwords don't match. Type the same password in both fields.",
        'register_invalid' => 'A few details need a fix: see the notes under the fields.',
        'register_taken' => 'An account already exists with these details. Sign in instead, or use other details.',
        'registered' => 'Your account is ready. Sign in to start.',
    );

    /**
     * Whether the site has a sign-up page (2.19.6, the top bar and the sign-in page): true without Route::auth, and when
     * a Route::auth of the request has one; false when every Route::auth says 'register' => false
     */
    public static function signUpOpen()
    {
        return self::$signUp ?? true;
    }

    /**
     * Whether a user may sign in: their permission level (Context::$levelAttribute) is at least Route::auth's 'level'
     */
    public static function mayEnter($user, $level)
    {
        $attribute = Context::$levelAttribute;
        return (int) $level <= 0 || (int) ($user->$attribute ?? 0) >= (int) $level;
    }

    /** @var bool|null whether a Route::auth of this request has a sign-up page (null: no Route::auth) */
    public static $signUp = null;

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
            'views' => array(), 'passkeys' => false, 'identifier' => 'username', 'signInAfterRegister' => false, 'messages' => array(),
            'level' => 0, 'attemptsPerUser' => 0), $options);
        // failures of one username from any address before it is locked (2.19.6): 0 = off, 'attempts' still counts per address
        if (!is_int($o['attemptsPerUser']) && !ctype_digit((string) $o['attemptsPerUser'])) {
            throw new \InvalidArgumentException("Route::auth: 'attemptsPerUser' is a number of failed sign-ins (0 = off)");
        }
        $o['attemptsPerUser'] = (int) $o['attemptsPerUser'];
        // the lowest permission level that may sign in (2.19.5): 0 lets everyone in, 1 only administrators...
        if (!is_int($o['level']) && !ctype_digit((string) $o['level'])) {
            throw new \InvalidArgumentException("Route::auth: 'level' is the lowest permission level that may sign in (0, 1...)");
        }
        $o['level'] = (int) $o['level'];
        $o['messages'] = array_merge(self::MESSAGES, array_map('strval', array_intersect_key((array) $o['messages'], self::MESSAGES)));
        $o['identifier'] = (string) $o['identifier'];
        if (!preg_match(Query::NAME, $o['identifier']) || preg_match('/pass|secret|token/i', $o['identifier'])) {
            throw new \InvalidArgumentException("Route::auth: 'identifier' is a column of the users table that people sign in with ('username', 'email'), not '" . substr($o['identifier'], 0, 60) . "'");
        }
        $o['signInAfterRegister'] = (bool) $o['signInAfterRegister'];
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
        self::$signUp = (self::$signUp ?? false) || $o['register'] !== false;
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
            Route::post("$p/passkey/register/options", function () use ($o) { self::passkey('registerOptions', $o); });
            Route::post("$p/passkey/register", function () use ($o) { self::passkey('register', $o); });
            Route::post("$p/passkey/login/options", function () use ($o) { self::passkey('loginOptions', $o); });
            Route::post("$p/passkey/login", function () use ($o) { self::passkey('login', $o); });
        }
        return $o;
    }

    /**
     * Checks a username and a password, with the failures counted per IP and username
     * @param string $username the value of the identifier (the username, the email address...)
     * @param callable $find function (string $username): ?user (a model with verifyPassword())
     * @return array ['status' => 'ok', 'user' => $user] | ['status' => 'invalid'] | ['status' => 'locked']
     */
    public static function attempt($username, $password, $ip, Throttle $throttle, callable $find, $level = 0, $perUser = 0)
    {
        $username = trim((string) $username);
        $key = Throttle::key('signin', $ip, $username);
        // the 'attemptsPerUser' option: the failures of this username from any address (guessing spread over many machines)
        $everywhere = (int) $perUser > 0 ? $throttle->limitedTo($perUser) : null;
        $userKey = Throttle::key('signin-user', '*', $username);
        if ($throttle->locked($key) || ($everywhere !== null && $everywhere->locked($userKey))) {
            return array('status' => 'locked');
        }
        $user = $username !== '' ? call_user_func($find, $username) : null;
        if (!$user) {
            password_verify((string) $password, self::DUMMY_HASH);
        }
        // below the 'level' of Route::auth: refused exactly like a wrong password, so the page never tells who exists
        if (!$user || !$user->verifyPassword((string) $password) || !self::mayEnter($user, $level)) {
            $throttle->fail($key);
            if ($everywhere !== null) {
                $everywhere->fail($userKey);
            }
            return array('status' => $throttle->locked($key) || ($everywhere !== null && $everywhere->locked($userKey)) ? 'locked' : 'invalid');
        }
        $throttle->clear($key);
        if ($everywhere !== null) {
            $everywhere->clear($userKey);
        }
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
     * How a user is found by the value people sign in with: findByUsername() for 'username' when the model has it,
     * else the model's findBy($column, $value), else a bound query of the query builder on the model's table
     * @return callable function (string $value): ?user
     */
    public static function finder($model, $identifier = 'username')
    {
        if ($identifier === 'username' && method_exists($model, 'findByUsername')) {
            return function ($value) use ($model) { return $model->findByUsername($value); };
        }
        if (method_exists($model, 'findBy')) {
            return function ($value) use ($model, $identifier) { return $model->findBy($identifier, $value); };
        }
        Query::quote($identifier);
        return function ($value) use ($model, $identifier) {
            $row = (new Query((string) $model->table))->where($identifier, (string) $value)->first();
            return $row ? $model->arrayToModel($row) : null;
        };
    }

    /**
     * What the sign-up form sends to the register capability: every input its rules declare (the username and the
     * passcode when the capability is unknown)
     */
    public static function registerInput($capability, array $parameters)
    {
        $entry = Capabilities::get((string) $capability);
        $rules = $entry !== null ? (array) (get_class_vars($entry['class'])['rules'] ?? array()) : array();
        if (!$rules) {
            return array('username' => $parameters['username'] ?? '', 'passcode' => $parameters['passcode'] ?? '');
        }
        $input = array();
        foreach ($rules as $field => $rule) {
            if (is_string($field) && array_key_exists($field, $parameters)) {
                $input[$field] = $parameters[$field];
            }
        }
        return $input;
    }

    /**
     * What a sign-up leads to, without a request (signUp() answers it; tests)
     * @param array $o the options (options())
     * @param array $p the parameters of the form
     * @param bool $json a JSON request (its CSRF header is checked before)
     * @return array ['status' => int, 'json' => array] | ['view' => name, 'data' => array] | ['redirect' => path]
     */
    public static function registration(array $o, array $p, Context $context, $json = false)
    {
        $old = Form::oldInput(self::registerInput($o['register'], $p) + array('username' => $p['username'] ?? ''));
        $old['username'] = is_scalar($old['username'] ?? '') ? (string) ($old['username'] ?? '') : '';
        $page = array('error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'user' => (object) $old);
        if (array_key_exists('confirmpasscode', $p) && (string) ($p['passcode'] ?? '') !== (string) $p['confirmpasscode']) {
            $error = ServiceError::invalid(array('passcode' => 'passwords don\'t match'), $o['messages']['mismatch']);
            return $json ? array('status' => 422, 'json' => $error->toArray())
                : array('view' => $o['views']['register'], 'data' => $page + array('message' => $error->getMessage(), 'errors' => $error->fields));
        }
        try {
            $profile = Capabilities::call($o['register'], self::registerInput($o['register'], $p), $context);
        } catch (ServiceError $e) {
            if ($json) {
                return array('status' => $e->status(), 'json' => $e->toArray());
            }
            $message = $e->kind === 'conflict' ? $o['messages']['register_taken'] : ($e->kind === 'invalid' ? $o['messages']['register_invalid'] : $e->getMessage());
            $errors = $e->kind === 'conflict' ? array($o['identifier'] => $e->getMessage()) : $e->fields;
            return array('view' => $o['views']['register'], 'data' => $page + array('message' => $message, 'errors' => $errors));
        }
        if ($o['signInAfterRegister'] && ($user = self::registered($o, $profile, $p)) !== null && self::mayEnter($user, $o['level'])) {
            self::signIn($user);
            return $json ? array('status' => 201, 'json' => array('data' => $profile)) : array('redirect' => $o['home']);
        }
        if ($json) {
            return array('status' => 201, 'json' => array('data' => $profile));
        }
        Flash::message('success', $o['messages']['registered']);
        return array('redirect' => $o['prefix'] . '/login');
    }

    /**
     * The address of the request (REMOTE_ADDR: a forwarded header can be written by anyone)
     */
    public static function ip()
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    // ---- the routes --------------------------------------------------------------------------------------------

    /**
     * Whether ?return= of the sign-in page is a page of this site (2.20.0): a path, never another site
     */
    public static function safeReturn($return)
    {
        return is_string($return) && preg_match('#^/(?![/\\\\])[^\\\\\s]*$#', $return) === 1;
    }

    private static function loginPage(array $o)
    {
        $return = $_GET['return'] ?? null;
        if (self::signedIn()) {
            redirect(self::safeReturn($return) ? $return : $o['home']);
            return;
        }
        unset($_SESSION[self::RETURN_TO]);
        if (self::safeReturn($return)) {
            $_SESSION[self::RETURN_TO] = $return;   // where a successful sign-in goes (Route::page 'comeBack')
        }
        $data = array('identifier' => $o['identifier'], 'signUp' => $o['register'] !== false);
        if (($text = Flash::get('messages', array())['success'] ?? null) !== null) {
            $data += array('error' => true, 'status' => 'success', 'message_header' => '', 'message' => $text);
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
        $result = self::attempt($text($p[$o['identifier']] ?? ''), $password, self::ip(), new Throttle($o['attempts'], $o['lock']),
            self::finder($model, $o['identifier']), $o['level'], $o['attemptsPerUser']);
        if ($result['status'] !== 'ok') {
            $locked = $result['status'] === 'locked';
            $message = $locked ? str_replace('{minutes}', (string) max(1, (int) ceil($o['lock'] / 60)), $o['messages']['locked']) : $o['messages']['invalid'];
            if ($json) {
                return self::json($locked ? 429 : 401, array('error' => array('code' => $locked ? 'locked' : 'unauthenticated', 'message' => $message)));
            }
            if ($locked) {
                http_response_code(429);
            }
            // the page again, with what the person typed in the identifier field (so they only fix what is wrong)
            $typed = mb_substr(trim($text($p[$o['identifier']] ?? '')), 0, 254);
            return view($o['views']['login'], array('identifier' => $o['identifier'], 'signUp' => $o['register'] !== false, 'error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => $message,
                'user' => (object) array($o['identifier'] => $typed)));
        }
        $user = self::upgrade($model, $result['user'], $password);
        self::signIn($user);
        if ($json) {
            return self::json(200, array('data' => array('user' => self::profile($user))));
        }
        $back = $_SESSION[self::RETURN_TO] ?? null;
        unset($_SESSION[self::RETURN_TO]);
        redirect(self::safeReturn($back) ? $back : $o['home']);
    }

    private static function logout(array $o)
    {
        // JSON or not is read before signing out: a new Request after signOut() would check the posted _token against
        // the new CSRF token of the session and answer [ERROR 3000] (2.19.1)
        $json = request()->wantsJson();
        self::signOut();
        if ($json) {
            return self::json(200, array('data' => array('signedOut' => true)));
        }
        redirect($o['logout']);
    }

    private static function notAuthorized(array $o)
    {
        http_response_code(403);
        view($o['views']['login'], array('signUp' => $o['register'] !== false, 'error' => true, 'status' => 'danger', 'message_header' => 'Restricted Page', 'message' => 'You have not been granted permission to access this page'));
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
        $outcome = self::registration($o, (array) $request->parameters, Context::fromRequest($request, 'web'), $json);
        if (isset($outcome['json'])) {
            return self::json($outcome['status'], $outcome['json']);
        }
        if (isset($outcome['view'])) {
            return view($outcome['view'], $outcome['data']);
        }
        redirect($outcome['redirect']);
    }

    /**
     * The passkey endpoints: the ceremonies of Caligrafy\Passkey, a new session at sign-in
     */
    private static function passkey($step, array $o)
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
                    if (!$user || !self::mayEnter($user, $o['level'])) {
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
        if (!method_exists($model, 'verifyPassword')) {
            throw new \LogicException('Route::auth: the authentication model needs verifyPassword(), as the User model of the authentication module');
        }
        return $model;
    }

    // the user a sign-up created: by the id of its answer, else by the identifier it was given
    private static function registered(array $o, $profile, array $p)
    {
        $model = self::model();
        $id = is_array($profile) ? ($profile['id'] ?? null) : (is_object($profile) ? ($profile->id ?? null) : null);
        if ($id !== null && $id !== '' && is_scalar($id)) {
            $row = (new Query((string) $model->table))->find($id);
            if ($row) {
                return $model->arrayToModel($row);
            }
        }
        $value = $p[$o['identifier']] ?? null;
        return is_string($value) && trim($value) !== '' ? (self::finder($model, $o['identifier'])(trim($value)) ?: null) : null;
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
