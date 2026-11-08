<?php

use Caligrafy\Auth;
use Caligrafy\Context;
use Caligrafy\Flash;
use Caligrafy\Form;
use Caligrafy\Request;
use Caligrafy\Route;
use Caligrafy\Uploads;
use PHPUnit\Framework\TestCase;

/**
 * Route::form (2.19.0): Form::outcome() is what a form post leads to, without a server
 */
final class FormRouteTest extends TestCase
{
    private $saved;

    protected function setUp(): void
    {
        $this->saved = $_SESSION ?? null;
        $_SESSION = array('token' => str_repeat('a', 64));
        Flash::reset();
        Route::reset();
    }

    protected function tearDown(): void
    {
        Flash::reset();
        Route::reset();
        Auth::reset();
        $_POST = $_REQUEST = array();
        if ($this->saved === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved;
        }
    }

    private function user(): Context
    {
        return new Context('web', (object) array('id' => 3, 'username' => 'ann', 'permissions' => 0));
    }

    private function env(array $extra = array()): array
    {
        return $extra + array('referer' => 'http://localhost:8080/books/new?x=1', 'host' => 'localhost:8080', 'route' => array(), 'bearer' => false, 'csrf' => false, 'method' => 'POST');
    }

    public function testRegistersPostByDefaultAndTheMethodsAsked(): void
    {
        Route::form('/books', 'qa_save_book');
        Route::form('/books/{id}', 'qa_save_book', array('method' => array('PUT', 'DELETE')));
        $this->assertSame(array(
            array('method' => 'POST', 'route' => 'books'),
            array('method' => 'PUT', 'route' => 'books/{id}'),
            array('method' => 'DELETE', 'route' => 'books/{id}'),
        ), Route::routes());
        $this->assertInstanceOf(Closure::class, Route::handler('POST', '/books'));
        $this->expectException(InvalidArgumentException::class);
        Route::form('/books', 'qa_save_book', array('method' => 'GET'));
    }

    public function testSuccessRedirectsToThePathFilledFromTheResultWithTheFlashMessage(): void
    {
        $outcome = Form::outcome('qa_save_book', array('success' => '/books/{id}', 'flash' => 'Saved {title}'), array('title' => 'Dune', 'id' => '12'), $this->user(), false, $this->env());
        $this->assertSame(array('redirect' => '/books/12'), $outcome);
        $this->assertSame('Saved Dune', $_SESSION['__flash']['messages']['success']);

        // the route variables fill what the result does not have; values are url-encoded in paths
        $outcome = Form::outcome('qa_save_book', array('success' => '/shelves/{shelf}/{title}'), array('title' => 'A b/c'), $this->user(), false, $this->env(array('route' => array('shelf' => 'sci-fi'))));
        $this->assertSame('/shelves/sci-fi/A%20b%2Fc', $outcome['redirect']);
    }

    public function testSuccessCanBeACallableAndDefaultsToBack(): void
    {
        $outcome = Form::outcome('qa_save_book', array('success' => function ($result) { return '/done/' . $result['id']; }), array('title' => 'Dune'), $this->user(), false, $this->env());
        $this->assertSame(array('redirect' => '/done/7'), $outcome);
        $this->assertSame(array('redirect' => 'http://localhost:8080/books/new?x=1'), Form::outcome('qa_save_book', array(), array('title' => 'Dune'), $this->user(), false, $this->env()));
    }

    public function testAPlaceholderWithoutAValueIsAMistake(): void
    {
        $this->expectException(LogicException::class);
        Form::outcome('qa_save_book', array('success' => '/books/{slug}'), array('title' => 'Dune'), $this->user(), false, $this->env());
    }

    public function testInvalidInputGoesBackWithTheErrorsTheOldInputAndTheMessage(): void
    {
        $outcome = Form::outcome('qa_save_book', array('success' => '/books/{id}'), array('title' => str_repeat('x', 30), 'id' => 'abc', 'password' => 'hunter22', '_token' => 'x', 'tags' => array('a', 'b')), $this->user(), false, $this->env());
        $this->assertSame(array('redirect' => 'http://localhost:8080/books/new?x=1'), $outcome);
        $flash = $_SESSION['__flash'];
        $this->assertArrayHasKey('title', $flash['errors']);
        $this->assertArrayHasKey('id', $flash['errors']);
        $this->assertSame(array('title' => str_repeat('x', 30), 'id' => 'abc', 'tags' => array('a', 'b')), $flash['old'], 'no password, no _token');
        $this->assertSame('Some of the inputs are invalid', $flash['messages']['error']);

        // the next request reads them once
        Flash::age();
        $this->assertArrayNotHasKey('__flash', $_SESSION);
        $this->assertNotNull(errors('title'));
        $this->assertNull(errors('author'));
        $this->assertCount(2, errors());
        $this->assertSame('abc', old('id'));
        $this->assertSame('none', old('author', 'none'));
        $this->assertSame('', old('password'));
        $this->assertSame('Some of the inputs are invalid', flash('error'));
        Flash::age();
        $this->assertSame(array(), errors());
        $this->assertNull(flash('error'));
    }

    public function testAConflictGoesBackTooAndTheErrorMessageCanBeSet(): void
    {
        $outcome = Form::outcome('qa_save_book', array('back' => '/shelves/{shelf}/new', 'error' => 'Not saved'), array('title' => 'taken'), $this->user(), false, $this->env(array('route' => array('shelf' => 'sf'))));
        $this->assertSame(array('redirect' => '/shelves/sf/new'), $outcome);
        $this->assertSame('Not saved', $_SESSION['__flash']['messages']['error']);
        $this->assertSame(array('title' => 'taken'), $_SESSION['__flash']['old']);
    }

    public function testBackNeverLeavesTheSite(): void
    {
        $this->assertSame('/', Form::back(array(), $this->env(array('referer' => 'https://evil.example/phish'))));
        $this->assertSame('/', Form::back(array(), $this->env(array('referer' => 'http://localhost:9999/books'))), 'another port');
        $this->assertSame('/', Form::back(array(), $this->env(array('referer' => 'javascript:alert(1)'))));
        $this->assertSame('/', Form::back(array(), $this->env(array('referer' => '//evil.example/x'))));
        $this->assertSame('/', Form::back(array(), $this->env(array('referer' => ''))));
        $this->assertSame('http://LOCALHOST:8080/a', Form::back(array(), $this->env(array('referer' => 'http://LOCALHOST:8080/a'))));
    }

    public function testRefusals(): void
    {
        // a visitor: 403 without authentication, the sign-in page with it
        $this->assertSame(array('error' => 1006), Form::outcome('qa_save_book', array(), array('title' => 'Dune'), Context::guest('web'), false, $this->env()));
        Auth::authentication('QaAuthUser', 'users');
        $this->assertSame(array('redirect' => '/login'), Form::outcome('qa_save_book', array(), array('title' => 'Dune'), Context::guest('web'), false, $this->env()));
        $this->assertSame(array('redirect' => '/account/sign-in'), Form::outcome('qa_save_book', array('signIn' => '/account/sign-in'), array('title' => 'Dune'), Context::guest('web'), false, $this->env()));
        $this->assertSame(array('error' => 1006), Form::outcome('qa_save_book', array(), array('title' => 'secret'), $this->user(), false, $this->env()));
        $this->assertSame(array('error' => 1005), Form::outcome('qa_save_book', array(), array('title' => 'missing'), $this->user(), false, $this->env()));
        $this->assertArrayNotHasKey('__flash', $_SESSION, 'refusals flash nothing');
    }

    public function testJsonRequestsGetTheRestAnswer(): void
    {
        $env = $this->env(array('csrf' => true));
        $this->assertSame(array('status' => 201, 'json' => array('data' => array('id' => 7, 'title' => 'Dune', 'cover' => null))), Form::outcome('qa_save_book', array('success' => '/x'), array('title' => 'Dune'), $this->user(), true, $env));
        $put = Form::outcome('qa_save_book', array(), array('title' => 'Dune'), $this->user(), true, array('method' => 'PUT') + $env);
        $this->assertSame(200, $put['status']);
        $invalid = Form::outcome('qa_save_book', array(), array(), $this->user(), true, $env);
        $this->assertSame(422, $invalid['status']);
        $this->assertSame('invalid', $invalid['json']['error']['code']);
        $this->assertSame(409, Form::outcome('qa_save_book', array(), array('title' => 'taken'), $this->user(), true, $env)['status']);
        $this->assertSame(401, Form::outcome('qa_save_book', array(), array('title' => 'Dune'), Context::guest('web'), true, $env)['status']);
        $this->assertArrayNotHasKey('__flash', $_SESSION, 'JSON answers flash nothing');
    }

    public function testJsonWithTheSessionCookieNeedsTheCsrfHeader(): void
    {
        $refused = Form::outcome('qa_save_book', array(), array('title' => 'Dune'), $this->user(), true, $this->env());
        $this->assertSame(403, $refused['status']);
        $this->assertStringContainsString('X-CSRF-Token', $refused['json']['error']['message']);
        // a bearer token is not a cookie: no CSRF risk
        $token = new Context('web', null, array('id' => 1, 'scopes' => array('books:write'), 'user_id' => null));
        $this->assertSame(201, Form::outcome('qa_save_book', array(), array('title' => 'Dune'), $token, true, $this->env(array('bearer' => true)))['status']);
    }

    public function testUnknownCapabilityIsAMistake(): void
    {
        $this->expectException(LogicException::class);
        Form::outcome('nope', array(), array(), $this->user(), false, $this->env());
    }

    public function testFlashHelperKeepsAMessageForTheNextRequest(): void
    {
        Flash::age();   // Route::run() at the start of the request
        flash('notice', 'See you');
        $this->assertNull(flash('notice'), 'not in this request');
        Flash::age();
        $this->assertSame('See you', flash('notice'));
    }

    public function testAFormPostWithoutTheCsrfTokenIsRefusedButAWebhookIsNot(): void
    {
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        $request->method = 'POST';
        $request->accept = 'text/html';
        $request->userAgent = 'Browser';
        $request->authorization = '';
        $check = new ReflectionMethod(Request::class, 'checkCSRF');
        $_REQUEST = array('uri' => 'books');
        $_POST = array('title' => 'Dune', '_token' => 'wrong');
        try {
            $check->invoke($request);
            $this->fail('a form post without the session token is refused');
        } catch (Exception $e) {
            $this->assertSame(3000, $e->getCode());
        }
        $_POST['_token'] = $_SESSION['token'];
        $this->assertNull($check->invoke($request), 'the right token passes');

        unset($_POST['_token']);
        Route::webhook('/hooks/stripe', 'qa_record_event', array('verify' => 'stripe'));
        $_REQUEST = array('uri' => 'hooks/stripe');
        $this->assertNull($check->invoke($request), 'a webhook carries no token');
        $_REQUEST = array('uri' => 'hooks/stripe/other');
        $this->expectExceptionCode(3000);
        $check->invoke($request);
    }

    public function testFileInputsOfAFormAreSavedAndTheirUrlGiven(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'qaup');
        file_put_contents($tmp, str_repeat('p', 100));
        $saved = array();
        $uploads = new Uploads(array('cover' => array('name' => 'my cover.PNG', 'tmp_name' => $tmp, 'error' => 0, 'size' => 100)),
            function ($file, $allowed) use (&$saved) { $saved[] = $allowed; return 'http://localhost/public/uploads/abc123.png'; });
        $outcome = Form::outcome('qa_save_book', array('flash' => 'Cover {cover}'), array('title' => 'Dune', 'cover' => '/etc/passwd'), $this->user(), true, $this->env(array('csrf' => true, 'uploads' => $uploads)));
        $this->assertSame('http://localhost/public/uploads/abc123.png', $outcome['json']['data']['cover']);
        $this->assertSame(array(array('png', 'jpg')), $saved);
        unlink($tmp);
    }

    public function testSameAndAcceptedRefuseBeforeTheCapability(): void
    {
        $options = array('same' => array('password_again' => array('password', 'Not the same')), 'accepted' => array('terms' => 'Accept the terms'), 'back' => '/new');
        $outcome = Form::outcome('qa_save_book', $options, array('title' => 'crash', 'password' => 'aaa', 'password_again' => 'bbb'), $this->user(), false, $this->env());
        $this->assertSame(array('redirect' => '/new'), $outcome, 'the capability, which would have crashed, is not called');
        Flash::age();
        $this->assertSame(array('password_again' => 'Not the same', 'terms' => 'Accept the terms'), Flash::get('errors'));
        $this->assertArrayNotHasKey('password', Flash::get('old'), 'a password is never kept');
        $good = Form::outcome('qa_save_book', $options + array('success' => '/done'), array('title' => 'Dune', 'password' => 'aaa', 'password_again' => 'aaa', 'terms' => '1'), $this->user(), false, $this->env());
        $this->assertSame(array('redirect' => '/done'), $good);
    }

    public function testServerInputsReplaceWhatTheFormSentButNotTheRoute(): void
    {
        $merged = Form::withServerInputs(array('inputs' => array('return_url' => 'https://here/back', 'id' => 9)), array('return_url' => 'https://evil/', 'title' => 'x'), array('id' => 3));
        $this->assertSame(array('return_url' => 'https://here/back', 'title' => 'x', 'id' => 3), $merged);
        $called = Form::withServerInputs(array('inputs' => function ($input) { return array('title' => strtoupper($input['title'])); }), array('title' => 'x'));
        $this->assertSame(array('title' => 'X'), $called);
        $this->assertSame(array('title' => 'x'), Form::withServerInputs(array(), array('title' => 'x')));
    }

    public function testVisitorsOnlySendsSignedInPeopleAway(): void
    {
        $options = array('visitorsOnly' => '/dashboard', 'success' => '/done');
        $this->assertSame(array('redirect' => '/dashboard'), Form::outcome('qa_save_book', $options, array('title' => 'crash'), $this->user(), false, $this->env()));
        $json = Form::outcome('qa_save_book', $options, array('title' => 'crash'), $this->user(), true, $this->env(array('csrf' => true)));
        $this->assertSame(403, $json['status']);
    }

    public function testSignsInThePersonOfTheAnswer(): void
    {
        $signed = array();
        $env = $this->env(array('signIn' => function ($id) use (&$signed) { $signed[] = $id; }));
        Form::outcome('qa_save_book', array('signsIn' => 'id', 'success' => '/done'), array('title' => 'Dune', 'id' => 7), $this->user(), false, $env);
        $this->assertCount(1, $signed);
        $this->expectException(LogicException::class);
        Form::outcome('qa_save_book', array('signsIn' => 'nothing_there'), array('title' => 'Dune'), $this->user(), false, $env);
    }

    public function testSuccessMayLeaveForAFullAddress(): void
    {
        $outcome = Form::outcome('qa_save_book', array('success' => function ($result) { return 'https://pay.example.com/s/1'; }), array('title' => 'Dune'), $this->user(), false, $this->env());
        $this->assertSame(array('redirect' => 'https://pay.example.com/s/1'), $outcome);
    }

    public function testTheSignInOnlyGoesBackToAPageOfThisSite(): void
    {
        foreach (array('/books', '/books/3?page=2', '/') as $good) {
            $this->assertTrue(\Caligrafy\AuthRoutes::safeReturn($good), $good);
        }
        foreach (array('https://evil.example', '//evil.example', '/\\evil.example', 'books', '', "/a\nb", null, array('/a')) as $bad) {
            $this->assertFalse(\Caligrafy\AuthRoutes::safeReturn($bad), var_export($bad, true));
        }
    }

    public function testKeepQueryAddsTheQueryStringOnly(): void
    {
        $this->assertSame('/books?page=2', Route::withQuery('/books', array('uri' => 'old/books', 'page' => '2')));
        $this->assertSame('/books?a=1&page=2#top', Route::withQuery('/books?a=1#top', array('page' => '2')));
        $this->assertSame('/books', Route::withQuery('/books', array('uri' => 'old/books')));
    }

    public function testAWebOnlyCapabilityIsReachedThroughRouteActionOnly(): void
    {
        foreach (array('web', 'rest', 'mcp', 'cli') as $channel) {
            try {
                \Caligrafy\Capabilities::call('qa_web_action', array('to' => '/x'), new Context($channel));
                $this->fail("reached over $channel");
            } catch (\Caligrafy\ServiceError $e) {
                $this->assertSame('not_found', $e->kind, $channel);
            }
        }
        $this->assertArrayNotHasKey('qa_web_action', \Caligrafy\Capabilities::available(Context::local('mcp')));
        $outcome = Form::outcome('qa_web_action', array(), array('to' => '/x'), $this->user(), false, $this->env());
        $this->assertArrayNotHasKey('redirect', array_diff_key($outcome, array('error' => 1)), 'Route::form cannot reach it');
        $context = new Context('web');
        $context->viaAction = true;
        $answer = \Caligrafy\Capabilities::call('qa_web_action', array('to' => '/x'), $context);
        $this->assertInstanceOf(\Caligrafy\Web::class, $answer);
        $this->assertSame(array('type' => 'redirect', 'target' => '/x', 'data' => null, 'status' => 302, 'headers' => array('X-Qa' => 'yes')), $answer->describe());
    }

    public function testRouteActionRegistersItsMethods(): void
    {
        Route::action('/go', 'qa_web_action');
        Route::action('/go/{id}', 'qa_web_action', array('method' => array('POST', 'DELETE')));
        $this->assertSame(array(
            array('method' => 'GET', 'route' => 'go'),
            array('method' => 'POST', 'route' => 'go/{id}'),
            array('method' => 'DELETE', 'route' => 'go/{id}'),
        ), Route::routes());
        $this->expectException(InvalidArgumentException::class);
        Route::action('/go', 'qa_web_action', array('method' => 'PATCH'));
    }

    public function testANumberFieldRefusesListsAndBooleans(): void
    {
        foreach (array(array(1), true) as $wrong) {
            $outcome = Form::outcome('qa_save_book', array('back' => '/new'), array('title' => 'Dune', 'id' => $wrong), $this->user(), true, $this->env(array('csrf' => true)));
            $this->assertSame(422, $outcome['status']);
            $this->assertArrayHasKey('id', $outcome['json']['error']['fields']);
        }
    }
}
