<?php

use Caligrafy\Async\Eligibility;
use Caligrafy\Async\Kernel;
use Caligrafy\Cache;
use PHPUnit\Framework\TestCase;

/**
 * The async server (2.26.0, experimental): which capabilities it may run, which requests it answers, its answers, and
 * that nothing of one request is left for the next. The server itself (Workerman) is checked over HTTP by qa/tests.
 */
final class AsyncTest extends TestCase
{
    protected function setUp(): void
    {
        Kernel::boot('/capabilities');
    }

    public function testEligibilityReadsTheClassOwnSource(): void
    {
        $this->assertSame(array(), Eligibility::reasons('qa_echo_note'), 'returns data from its input and its context');
        $this->assertSame(array(), Eligibility::reasons('qa_list_notes'));
        $this->assertContains('webOnly: it reads the session or signs people in', Eligibility::reasons('qa_web_action'));
        $this->assertStringContainsString('is a file', implode(' ', Eligibility::reasons('qa_save_book')));
        $this->assertSame(array("no capability named 'nope'"), Eligibility::reasons('nope'));
    }

    public function testEligibilityRefusesStateOutputAndTheSession(): void
    {
        $reasons = Eligibility::sourceReasons('<?php class B { private static $cache = array(); public function handle(array $i, $c) {
            static $n = 0; $n++; self::$cache[$i["id"]] = 1; echo "x"; $u = $_SESSION["u"]; header("X: 1");
            if (!Auth::authorized()) exit; return session("k"); } }');
        foreach (array('static variable', 'static property', 'prints output', 'uses $_SESSION', 'calls header()', 'uses Auth', 'calls exit', 'calls session()') as $expected) {
            $this->assertStringContainsString($expected, implode(' | ', $reasons));
        }
        // comments, strings, declarations and method calls of other objects are not uses
        $this->assertSame(array(), Eligibility::sourceReasons('<?php class A { private static $x = 1; const ECHO = "echo";
            // echo $_SESSION, exit
            public function handle(array $i, $c) { $this->view($i); return Book::where("id", 1)->header("x") ?? self::$x; } }'));
    }

    public function testItAnswersOnlyTheCapabilitiesChannelWithoutASession(): void
    {
        $this->assertTrue(Kernel::handles('GET', '/capabilities'));
        $this->assertFalse(Kernel::handles('POST', '/capabilities'), 'the listing is a GET');
        $this->assertTrue(Kernel::handles('POST', '/capabilities/qa_echo_note'));
        $this->assertFalse(Kernel::handles('POST', '/capabilities/qa_web_action'), 'not eligible: the web server answers');
        $this->assertFalse(Kernel::handles('POST', '/capabilities/qa_echo_note', array(session_name() => 'abc')), 'a session is the web server\'s');
        $this->assertFalse(Kernel::handles('GET', '/books'));
        $this->assertFalse(Kernel::handles('POST', '/capabilities/../etc'));
    }

    public function testAnswers(): void
    {
        $list = Kernel::handle('GET', '/capabilities', array(), '');
        $this->assertSame(200, $list['status']);
        $this->assertSame('application/json', $list['headers']['Content-Type']);
        $this->assertContains('qa_list_notes', array_column(json_decode($list['body'], true)['data'], 'name'));

        $read = Kernel::handle('POST', '/capabilities/qa_list_notes', array(), '');
        $this->assertSame(200, $read['status']);
        $this->assertSame(array(array('title' => 'one'), array('title' => 'two')), json_decode($read['body'], true)['data']);

        $guest = Kernel::handle('POST', '/capabilities/qa_echo_note', array(), '{"title":"hi"}');
        $this->assertSame(401, $guest['status'], 'a write needs a caller');
        $this->assertSame('Bearer', $guest['headers']['WWW-Authenticate']);

        $get = Kernel::handle('GET', '/capabilities/qa_echo_note', array(), '', array('title' => 'hi'));
        $this->assertSame(405, $get['status'], 'changing data needs a POST');
    }

    public function testNothingOfARequestIsLeftForTheNext(): void
    {
        Caligrafy\Container::instance('async.boot', new stdClass());
        Caligrafy\Container::keepInstances();                     // what boot() keeps: the app's own registrations
        Caligrafy\Container::instance('async.request', new stdClass());   // made while answering one caller
        Kernel::endRequest();
        $this->assertTrue(Caligrafy\Container::has('async.boot'), 'the boot-time instance stays');
        $this->assertFalse(Caligrafy\Container::has('async.request'), 'one request\'s instance is forgotten');
        $this->assertSame(0, Caligrafy\Database::rowCount());
    }
}
