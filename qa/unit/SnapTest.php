<?php

use Caligrafy\Capabilities;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Route;
use Caligrafy\Snap\Cards;
use Caligrafy\Snap\Conversation;
use Caligrafy\Snap\Hints;
use Caligrafy\Snap\Input;
use Caligrafy\Snap\Say;
use Caligrafy\Snap\Tasks;
use PHPUnit\Framework\TestCase;

/**
 * Snap Cards and the assistant (2.23.0) without a database: the snap_* capabilities ship with the framework and are
 * never MCP tools, Route::snap() declares the shell, cards are built from what a capability declares (the notes
 * fixtures), the assistant's line names what a card is about, an empty answer leads somewhere, hints are read from
 * application/config/snap.php, and #[Capability(async: true)] makes a capability a background task
 */
final class SnapTest extends TestCase
{
    private $hints = null;

    protected function tearDown(): void
    {
        if ($this->hints !== null && is_file($this->hints)) {
            unlink($this->hints);
        }
        Hints::forget();
        Route::reset();
        Cards::$path = '/snap';
    }

    private function admin()
    {
        return new Context('web', (object) array('id' => 1, Context::$levelAttribute => 9));
    }

    public function testTheCapabilitiesShipWithTheFrameworkAndAreNotMcpTools(): void
    {
        $names = array('snap_card', 'snap_catalog', 'snap_open', 'snap_run', 'snap_ask', 'snap_home', 'snap_conversation', 'snap_history',
            'snap_rate', 'snap_shortcuts', 'snap_shortcut_save', 'snap_shortcut_delete', 'snap_task', 'snap_tasks', 'snap_task_run',
            'snap_offers', 'snap_noticed', 'snap_noticing_set', 'snap_forget', 'snap_dismiss', 'snap_routine_save', 'snap_routine_delete', 'snap_routines_due');
        foreach ($names as $name) {
            $this->assertNotNull(Capabilities::get($name), "$name is registered");
            $this->assertFalse(Capabilities::get($name)['capability']->mcp, "$name is not an MCP tool");
        }
        $this->assertTrue(Capabilities::get('snap_ask')['capability']->webOnly, 'the assistant answers a page only');
        $this->assertTrue(Capabilities::get('snap_run')['capability']->webOnly, 'a card is sent from a page only');
        $this->assertSame('caligrafy:queue', Capabilities::get('snap_task_run')['capability']->scope, 'only the queue runs a task');
        $this->assertStringNotContainsString('snapHome', \Caligrafy\Client::generate(), 'nor functions of the JS client: they serve the Snap shell');
    }

    public function testAsyncIsAFlagOfTheCapability(): void
    {
        $this->assertFalse((new Capability(name: 'x'))->async, 'off by default: nothing changes for existing capabilities');
        $this->assertTrue((new Capability(name: 'x', async: true))->async);
        $this->assertFalse(Tasks::async('qa_list_notes'));
        if (!defined('CONFIG_PATH')) {
            $dir = sys_get_temp_dir() . '/caligrafy-snap-test-' . getmypid() . '/';
            @mkdir($dir, 0777, true);
            define('CONFIG_PATH', $dir);
        }
        $this->hints = CONFIG_PATH . 'snap.php';
        file_put_contents($this->hints, "<?php return array('about' => 'a notes app', 'cards' => array('qa_list_notes' => array('async' => true, 'title' => 'Your notes')));");
        Hints::forget();
        $this->assertTrue(Tasks::async('qa_list_notes'), 'or the hint of application/config/snap.php');
        $this->assertSame('a notes app', Hints::about());
        $this->assertSame('Your notes', Hints::for('qa_list_notes')['title']);
        $this->assertSame(array(), Hints::for('qa_echo_note'), 'a card without hints works as it is');
    }

    public function testRouteSnapDeclaresTheShell(): void
    {
        Route::reset();
        Route::snap();
        $routes = array_map(function ($r) { return $r['method'] . ' ' . $r['route']; }, Route::routes());
        foreach (array('GET snap', 'GET snap/c/{capability}', 'POST snap/run', 'POST snap/call/{__snap}', 'GET assistant', 'POST assistant/ask', 'GET assistant/noticed') as $route) {
            $this->assertContains($route, $routes);
        }
        Route::reset();
        Route::snap(array('assistant' => '/help', 'cards' => '/cards'));
        $routes = array_map(function ($r) { return $r['method'] . ' ' . $r['route']; }, Route::routes());
        $this->assertContains('GET help', $routes);
        $this->assertContains('POST cards/run', $routes);
        $this->assertStringStartsWith('/cards/c/qa_list_notes', Cards::url('qa_list_notes'), 'a card links to where the cards are');
    }

    public function testACardIsBuiltFromWhatTheCapabilityDeclares(): void
    {
        $form = Cards::ask('qa_echo_note', $this->admin());
        $this->assertSame(array('ask', 'form', 'write'), array($form['family'], $form['type'], $form['risk']));
        $states = array_column($form['fields'], 'state', 'name');
        $this->assertSame('needed', $states['title'], 'a required input still missing is needed');
        $this->assertSame('optional', $states['stars'], 'the others are optional details');
        $this->assertSame('To qa echo note, I just need title.', Say::on($form)['say'], 'the line names what the card is about');
        $said = Cards::ask('qa_echo_note', $this->admin(), array('title' => 'Dune'));
        $this->assertSame('said', array_column($said['fields'], 'state', 'name')['title'], 'what was given is shown as said');
        $this->assertSame('destroy', Cards::ask('qa_purge_notes', $this->admin())['risk'], 'from destructive: true');
    }

    public function testAnEmptyAnswerLeadsSomewhere(): void
    {
        $empty = array('type' => 'list', 'capability' => 'qa_list_notes', 'rows' => array(), 'input' => array());
        $this->assertTrue(Conversation::empty($empty));
        $this->assertFalse(Conversation::empty(array('type' => 'list', 'capability' => 'qa_list_notes', 'rows' => array(array('one')))));
        $texts = array_column(Conversation::suggestions(array($empty), $this->admin()), 'text');
        $this->assertSame(array('Back to Home', 'What else can I do?'), array_slice($texts, -2), 'never a dead end');
    }

    public function testInputsArePlainValues(): void
    {
        $this->assertSame(array('id' => 7, 'title' => 'Dune'), Input::decode('{"id":7,"title":"Dune","nested":{"x":1}}'));
        $this->assertSame(array(), Input::decode('not json'));
        $this->assertSame(array('a' => 'b'), Input::decode((object) array('a' => 'b')));
    }
}
