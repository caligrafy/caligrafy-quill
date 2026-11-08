<?php

use Caligrafy\Auth;
use Caligrafy\AuthRoutes;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Flash;
use Caligrafy\Query;
use Caligrafy\Route;
use Caligrafy\Throttle;
use PHPUnit\Framework\TestCase;

/**
 * Route::auth (2.19.0): the routes, the sign-in check with its throttle, a new session id at sign-in and sign-out
 */
final class AuthRoutesTest extends TestCase
{
    private $saved;
    private $store = array();
    private $regenerated = 0;

    protected function setUp(): void
    {
        $this->saved = $_SESSION ?? null;
        $_SESSION = array('token' => str_repeat('a', 64));
        Route::reset();
        $this->store = array();
        $this->regenerated = 0;
        AuthRoutes::$regenerate = function () { $this->regenerated++; };
    }

    protected function tearDown(): void
    {
        Database::useConnection(null);
        Flash::reset();
        Route::reset();
        Auth::reset();
        AuthRoutes::$regenerate = null;
        if ($this->saved === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved;
        }
    }

    // a store in memory instead of the Cache
    private function throttle($max = 3, $seconds = 60): Throttle
    {
        return new Throttle($max, $seconds, array(
            'get' => function ($key) { return $this->store[$key] ?? null; },
            'put' => function ($key, $value) { $this->store[$key] = $value; },
            'forget' => function ($key) { unset($this->store[$key]); },
        ));
    }

    private function finder(): callable
    {
        $ann = new QaAuthUser('users');
        $ann->id = 4;
        $ann->username = 'ann';
        $ann->passcode = password_hash('Passw0rd!', PASSWORD_BCRYPT, array('cost' => 4));
        return function ($username) use ($ann) { return strtolower($username) === 'ann' ? $ann : null; };
    }

    private function paths(): array
    {
        return array_map(function ($r) { return $r['method'] . ' ' . $r['route']; }, Route::routes());
    }

    public function testRegistersTheSignInPages(): void
    {
        $options = Route::auth();
        $this->assertSame(array('GET login', 'POST login', 'GET logout', 'POST logout', 'GET notAuthorized', 'GET register', 'POST register'), $this->paths());
        $this->assertSame('register_user', $options['register']);
        $this->assertSame(array('login' => 'Auth/login', 'register' => 'Auth/register'), $options['views']);
        $this->assertInstanceOf(Closure::class, Route::handler('POST', '/login'));
    }

    public function testPrefixNoRegisterAndPasskeys(): void
    {
        Route::auth(array('prefix' => '/account/', 'register' => false, 'passkeys' => true, 'views' => array('login' => 'Account/signin')));
        $this->assertSame(array('GET account/login', 'POST account/login', 'GET account/logout', 'POST account/logout', 'GET account/notAuthorized',
            'POST account/passkey/register/options', 'POST account/passkey/register', 'POST account/passkey/login/options', 'POST account/passkey/login'), $this->paths());
        $this->assertSame('Account/signin', AuthRoutes::options(array('views' => array('login' => 'Account/signin')))['views']['login']);
        $this->assertSame('Auth/register', AuthRoutes::options(array('views' => array('login' => 'Account/signin')))['views']['register']);
    }

    public function testFaceSignInAndPasswordResetAreNotProvided(): void
    {
        foreach (array('face', 'reset') as $option) {
            try {
                Route::auth(array($option => true));
                $this->fail("$option is refused");
            } catch (LogicException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertSame(array(), Route::routes(), 'nothing registered');
    }

    public function testTheRightPasswordSignsIn(): void
    {
        $result = AuthRoutes::attempt(' ann ', 'Passw0rd!', '10.0.0.1', $this->throttle(), $this->finder());
        $this->assertSame('ok', $result['status']);
        $this->assertSame(4, $result['user']->id);
    }

    public function testWrongPasswordsAndUnknownUsersAreInvalidThenLocked(): void
    {
        $throttle = $this->throttle(3);
        $this->assertSame('invalid', AuthRoutes::attempt('ann', 'nope', '10.0.0.1', $throttle, $this->finder())['status']);
        $this->assertSame('invalid', AuthRoutes::attempt('ANN', 'nope', '10.0.0.1', $throttle, $this->finder())['status'], 'the username is counted in lower case');
        $this->assertSame('locked', AuthRoutes::attempt('ann', 'nope', '10.0.0.1', $throttle, $this->finder())['status'], 'the third failure locks');
        $this->assertSame('locked', AuthRoutes::attempt('ann', 'Passw0rd!', '10.0.0.1', $throttle, $this->finder())['status'], 'even the right password while locked');
        // another address, or another username, is counted apart
        $this->assertSame('ok', AuthRoutes::attempt('ann', 'Passw0rd!', '10.0.0.2', $throttle, $this->finder())['status']);
        $this->assertSame('invalid', AuthRoutes::attempt('bob', 'x', '10.0.0.1', $throttle, $this->finder())['status'], 'an unknown user');
        $this->assertSame('invalid', AuthRoutes::attempt('', 'x', '10.0.0.1', $throttle, $this->finder())['status']);
    }

    public function testAFailedSignInSaysSoInHumanWordsOrTheAppsOwn(): void
    {
        $o = AuthRoutes::options(array());
        $this->assertSame(AuthRoutes::MESSAGES, $o['messages']);
        $this->assertStringNotContainsString('Invalid credentials', $o['messages']['invalid']);
        $this->assertStringContainsString('{minutes}', $o['messages']['locked']);
        // an app's own words; unknown keys and the other message are left as they are (2.19.3)
        $o = AuthRoutes::options(array('messages' => array('invalid' => 'That email and password do not match an account.', 'other' => 'x')));
        $this->assertSame('That email and password do not match an account.', $o['messages']['invalid']);
        $this->assertSame(AuthRoutes::MESSAGES['locked'], $o['messages']['locked']);
        $this->assertArrayNotHasKey('other', $o['messages']);
    }

    public function testASuccessForgetsTheFailures(): void
    {
        $throttle = $this->throttle(3);
        AuthRoutes::attempt('ann', 'nope', '10.0.0.1', $throttle, $this->finder());
        AuthRoutes::attempt('ann', 'nope', '10.0.0.1', $throttle, $this->finder());
        $this->assertSame('ok', AuthRoutes::attempt('ann', 'Passw0rd!', '10.0.0.1', $throttle, $this->finder())['status']);
        $this->assertSame(0, $throttle->failures(Throttle::key('signin', '10.0.0.1', 'ann')));
        $this->assertSame('invalid', AuthRoutes::attempt('ann', 'nope', '10.0.0.1', $throttle, $this->finder())['status']);
    }

    public function testTheLockEndsWithTheWindow(): void
    {
        $throttle = $this->throttle(1, 60);
        $key = Throttle::key('signin', '10.0.0.1', 'ann');
        $throttle->fail($key);
        $this->assertTrue($throttle->locked($key));
        $this->store[$key]['until'] = time() - 1;
        $this->assertFalse($throttle->locked($key));
        $this->assertSame(1, $throttle->fail($key), 'a new window starts');
    }

    public function testTheThrottleUsesTheSessionWhenTheCacheIsOff(): void
    {
        putenv('CACHE_STORE=off');
        try {
            $throttle = new Throttle(2, 60);
            $key = Throttle::key('signin', '1.2.3.4', 'ann');
            $throttle->fail($key);
            $this->assertSame(1, $_SESSION['__throttle'][$key]['failures']);
            $throttle->fail($key);
            $this->assertTrue($throttle->locked($key));
            $throttle->clear($key);
            $this->assertFalse($throttle->locked($key));
        } finally {
            putenv('CACHE_STORE');
        }
    }

    public function testSignInAndSignOutRegenerateTheSessionAndTheCsrfToken(): void
    {
        $authorized = array();
        $user = (object) array('id' => 4);
        $this->assertTrue(AuthRoutes::signIn($user, function ($u) use (&$authorized) { $authorized[] = $u; return true; }));
        $this->assertSame(1, $this->regenerated, 'session_regenerate_id(true) at sign-in');
        $this->assertSame(array($user), $authorized);
        $this->assertNotSame(str_repeat('a', 64), $_SESSION['token']);
        $this->assertSame(64, strlen($_SESSION['token']));

        $token = $_SESSION['token'];
        $calls = 0;
        AuthRoutes::signOut(function () use (&$calls) { $calls++; });
        $this->assertSame(1, $calls);
        $this->assertSame(2, $this->regenerated, 'and at sign-out');
        $this->assertNotSame($token, $_SESSION['token']);
    }

    public function testAuthorizeWithTheAuthenticationModel(): void
    {
        Auth::authentication('QaAuthUser', 'users');
        $this->assertInstanceOf(QaAuthUser::class, Auth::model());
        $this->assertNotSame(Auth::model(), Auth::model(), 'a copy each time');
        $user = $this->finder()('ann');
        $this->assertTrue(AuthRoutes::signIn($user));
        $this->assertArrayHasKey('__authorization', $_SESSION);
        AuthRoutes::signOut();
        $this->assertArrayNotHasKey('__authorization', $_SESSION);
        Auth::reset();
        $this->assertNull(Auth::model());
    }

    // an SQLite users table with ann (ann@example.com) and a team
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, email TEXT, passcode TEXT, permissions INTEGER)');
        $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO teams VALUES (7, 'Readers')");
        $stmt = $pdo->prepare('INSERT INTO users (username, email, passcode, permissions) VALUES (?, ?, ?, 0)');
        $stmt->execute(array('ann', 'ann@example.com', password_hash('Passw0rd!', PASSWORD_BCRYPT, array('cost' => 4))));
        Database::useConnection($pdo);
        Query::watch(false);
        return $pdo;
    }

    public function testTheIdentifierOptionAndItsChecks(): void
    {
        $this->assertSame('username', AuthRoutes::options(array())['identifier']);
        $this->assertFalse(AuthRoutes::options(array())['signInAfterRegister']);
        $this->assertSame('email', AuthRoutes::options(array('identifier' => 'email'))['identifier']);
        foreach (array('passcode', 'email; DROP TABLE users', '', 'users.email') as $bad) {
            try {
                AuthRoutes::options(array('identifier' => $bad));
                $this->fail("'$bad' is refused");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('identifier', $e->getMessage());
            }
        }
    }

    public function testSignInWithTheEmailAddressThroughTheQueryBuilder(): void
    {
        $this->database();
        $find = AuthRoutes::finder(new QaAuthUser('users'), 'email');
        $result = AuthRoutes::attempt('ann@example.com', 'Passw0rd!', '10.0.0.1', $this->throttle(), $find);
        $this->assertSame('ok', $result['status']);
        $this->assertInstanceOf(QaAuthUser::class, $result['user']);
        $this->assertSame('ann', $result['user']->username);
        $this->assertSame('invalid', AuthRoutes::attempt('ann', 'Passw0rd!', '10.0.0.1', $this->throttle(), $find)['status'], 'the username is not the identifier');
        $this->assertNull(AuthRoutes::finder(new QaAuthUser('users'), 'username')("' OR 1=1 --"), 'a bound query');
    }

    public function testTheFinderPrefersFindByUsernameThenFindBy(): void
    {
        $model = new class('users') extends QaAuthUser {
            public $calls = array();
            public function findByUsername($u) { $this->calls[] = "username:$u"; return null; }
            public function findBy($column, $value) { $this->calls[] = "$column:$value"; return null; }
        };
        AuthRoutes::finder($model, 'username')('ann');
        AuthRoutes::finder($model, 'email')('ann@example.com');
        $this->assertSame(array('username:ann', 'email:ann@example.com'), $model->calls);
    }

    public function testTheShippedUserModelFindsByAnyColumn(): void
    {
        $this->database();
        if (!class_exists('User', false)) {
            require dirname(__DIR__, 2) . '/framework/settings/application/models/Auth/User.php';
        }
        $this->assertSame('ann', (new User('users'))->findBy('email', 'ann@example.com')->username);
        $this->assertSame('ann@example.com', (new User('users'))->findByUsername('ann')->email);
        $this->assertNull((new User('users'))->findByUsername('bob'));
    }

    public function testSignUpPassesEveryDeclaredInputAndNeverEchoesPasswords(): void
    {
        $this->database();
        Auth::authentication('QaAuthUser', 'users');
        $o = AuthRoutes::options(array('register' => 'qa_register_member', 'identifier' => 'email'));
        $form = array('username' => 'bob', 'email' => 'ann@example.com', 'passcode' => 'long-password', 'confirmpasscode' => 'long-password',
            'team_id' => '99', 'permissions' => '9', '_token' => 'x');
        $this->assertSame(array('username' => 'bob', 'email' => 'ann@example.com', 'passcode' => 'long-password', 'confirmpasscode' => 'long-password', 'team_id' => '99'),
            AuthRoutes::registerInput('qa_register_member', $form), 'only what the rules declare');
        $this->assertSame(array('username' => 'bob', 'passcode' => 'long-password'), AuthRoutes::registerInput('no_such_capability', $form));

        $outcome = AuthRoutes::registration($o, $form, Context::guest('web'));
        $this->assertSame('Auth/register', $outcome['view']);
        $this->assertSame('This email is already used', $outcome['data']['errors']['email']);
        $this->assertSame('There is no such team', $outcome['data']['errors']['team_id']);
        $this->assertEquals((object) array('username' => 'bob', 'email' => 'ann@example.com', 'team_id' => '99'), $outcome['data']['user'], 'no password in the old input');

        $outcome = AuthRoutes::registration($o, array('email' => 'bob@example.com', 'passcode' => 'a', 'confirmpasscode' => 'b'), Context::guest('web'), true);
        $this->assertSame(422, $outcome['status']);

        $outcome = AuthRoutes::registration($o, array('team_id' => '7', 'email' => 'bob@example.com') + $form, Context::guest('web'));
        $this->assertSame(array('redirect' => '/login'), $outcome, 'then the sign-in page, as before');
        Flash::age();
        $this->assertSame('Your account is ready: sign in', Flash::get('messages', array())['success'] ?? null);
        $this->assertSame(0, $this->regenerated, 'not signed in');
        $this->assertArrayNotHasKey('__authorization', $_SESSION);
        $this->assertSame('0', (string) Database::table('users')->where('email', 'bob@example.com')->value('permissions'), 'permissions was not passed');
    }

    public function testSignInAfterRegister(): void
    {
        $this->database();
        Auth::authentication('QaAuthUser', 'users');
        $o = AuthRoutes::options(array('register' => 'qa_register_member', 'identifier' => 'email', 'signInAfterRegister' => true, 'home' => '/books'));
        $form = array('username' => 'cy', 'email' => 'cy@example.com', 'passcode' => 'long-password', 'confirmpasscode' => 'long-password');
        $this->assertSame(array('redirect' => '/books'), AuthRoutes::registration($o, $form, Context::guest('web')));
        $this->assertSame(1, $this->regenerated, 'a new session id, as at sign-in');
        $this->assertNotSame(str_repeat('a', 64), $_SESSION['token'], 'and a new CSRF token');
        $this->assertArrayHasKey('__authorization', $_SESSION);

        AuthRoutes::signOut();
        $json = AuthRoutes::registration($o, array('email' => 'dee@example.com', 'username' => 'dee') + $form, Context::guest('web'), true);
        $this->assertSame(201, $json['status']);
        $this->assertSame('dee@example.com', $json['json']['data']['email']);
        $this->assertArrayHasKey('__authorization', $_SESSION);
    }

    // the sign-out form posts the page's _token: signOut() gives a new CSRF token, and the request must not be checked
    // again against it ([ERROR 3000] after signing out, 2.19.0)
    public function testTheSignOutFormSignsOutWithoutACsrfError(): void
    {
        if (!function_exists('getallheaders')) {
            function getallheaders() { return array(); }
        }
        $server = $_SERVER;
        $post = $_POST;
        $request = $_REQUEST;
        try {
            Auth::authentication('QaAuthUser', 'users');
            Route::auth();
            $this->assertTrue(AuthRoutes::signIn($this->finder()('ann')));
            $this->assertArrayHasKey('__authorization', $_SESSION);
            $token = $_SESSION['token'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = array('_token' => $token);
            $_REQUEST = array('_token' => $token, 'uri' => 'logout');
            ob_start();
            try {
                Route::handler('POST', '/logout')();
            } finally {
                ob_end_clean();
            }
            $this->assertNotSame($token, $_SESSION['token'], 'signed out: a new CSRF token');
            $this->assertArrayNotHasKey('__authorization', $_SESSION);
        } finally {
            $_SERVER = $server;
            $_POST = $post;
            $_REQUEST = $request;
        }
    }
}
