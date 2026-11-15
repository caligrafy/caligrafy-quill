<?php

use Caligrafy\Capabilities;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Route;
use Caligrafy\Snap\Cards;
use Caligrafy\Snap\Conversation;
use Caligrafy\Snap\Designer;
use Caligrafy\Snap\Hints;
use Caligrafy\Snap\Input;
use Caligrafy\Snap\Say;
use Caligrafy\Snap\Planner;
use Caligrafy\Snap\Tasks;
use Caligrafy\Snap\Views;
use PHPUnit\Framework\TestCase;

/**
 * Snap Cards and the assistant (2.23.0) without a database: the snap_* capabilities ship with the framework and are
 * never MCP tools, Route::snap() declares the shell, cards are built from what a capability declares (the notes
 * fixtures), the assistant's line names what a card is about, an empty answer leads somewhere, hints are read from
 * application/config/snap.php, and #[Capability(async: true)] makes a capability a background task. Since 2.24.0 a card
 * shows the page made for its capability (Snap\Views), else a card designed from its data (Snap\Designer)
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
        Planner::$transport = null;
        Designer::$transport = null;
        Designer::$model = true;
        putenv('CACHE_STORE');
    }

    // application/config/snap.php for one test
    private function hints($php)
    {
        if (!defined('CONFIG_PATH')) {
            $dir = sys_get_temp_dir() . '/caligrafy-snap-test-' . getmypid() . '/';
            @mkdir($dir, 0777, true);
            define('CONFIG_PATH', $dir);
        }
        $this->hints = CONFIG_PATH . 'snap.php';
        file_put_contents($this->hints, '<?php return ' . $php . ';');
        Hints::forget();
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
        $this->hints("array('about' => 'a notes app', 'cards' => array('qa_list_notes' => array('async' => true, 'title' => 'Your notes')))");
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

    // application/views for one test: VIEW_PATH is a folder of the temporary directory
    private function views(array $files)
    {
        if (!defined('VIEW_PATH')) {
            define('VIEW_PATH', sys_get_temp_dir() . '/caligrafy-snap-views-' . getmypid() . '/');
        }
        foreach ($files as $path => $pug) {
            @mkdir(dirname(VIEW_PATH . $path), 0777, true);
            file_put_contents(VIEW_PATH . $path, $pug);
        }
    }

    public function testACardShowsOnlyThePartOfAPageMarkedForIt(): void
    {
        $this->views(array(
            'Notes/index.pug' => "section.hero\n  h1 Notes\nsection(data-snap='qa_list_notes')\n  ul\n",
            'Notes/show.pug' => "section(data-snap='qa_echo_note other')\n  p= \$note\n",
            'Notes/new.pug' => "form(data-snap='qa_export_rows')\n",
            'Notes/plain.pug' => "section\n  p A page with no mark\n",
        ));
        Route::reset();
        Route::page('/notes', 'Notes/index', array('data' => array('notes' => 'qa_list_notes'), 'query' => array('tag')));
        Route::page('/notes/{id}', 'Notes/show', array('data' => array('note' => 'qa_echo_note', 'all' => 'qa_list_notes')));
        Route::page('/notes/new', 'Notes/new', array('data' => array('notes' => 'qa_export_rows')));
        Route::page('/plain', 'Notes/plain', array('data' => array('rows' => 'qa_purge_notes')));
        $this->assertSame('/notes', Route::pages()[0]['route'], 'Route::page keeps its pages for Snap');
        $this->assertSame('/notes?snap-embed=qa_list_notes', Views::embed('qa_list_notes', array()), 'the page whose first data it is, when it marks a part');
        $this->assertSame('/notes?tag=work&snap-embed=qa_list_notes', Views::embed('qa_list_notes', array('tag' => 'work', 'x' => 1)), 'with the inputs the page reads from its address');
        $this->assertSame('/notes/7?snap-embed=qa_echo_note', Views::embed('qa_echo_note', array('id' => 7)), 'the route variables filled from the inputs; several names in a mark');
        $this->assertNull(Views::embed('qa_echo_note', array()), 'not without them');
        $this->assertNull(Views::embed('qa_export_rows', array()), 'never a page made to create something');
        $this->assertNull(Views::embed('qa_purge_notes', array()), 'never a whole page: a page without a mark gives a designed card');

        $this->hints("array('qa_list_notes' => array('view' => false), 'qa_export_rows' => array('view' => 'Notes/new'))");
        $this->assertNull(Views::embed('qa_list_notes', array()), "the hint 'view' => false keeps it a designed card");
        $this->assertSame('/notes/new?snap-embed=qa_export_rows', Views::embed('qa_export_rows', array()), "and 'view' names the page");
    }

    public function testACardViewIsTheAppsOwnMarkupMadeForTheCard(): void
    {
        $this->views(array('Notes/index.pug' => "section(data-snap='qa_list_notes')\n  ul\n"));
        $print = Views::fingerprint('Notes/index');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $print);
        $this->views(array(
            'snap/qa_list_notes.pug' => "//- snap card of qa_list_notes, from Notes/index.pug $print\nul.notes\n  each \$note in \$rows\n    li\n      a(href='#', data-snap-open='qa_echo_note', data-snap-input=json_encode(array('title' => \$note['title'])))= \$note['title']\nif \$page\n  p.pages= \$page['total'] . ' notes'\n",
            'snap/qa_web_action.pug' => "//- snap card of qa_web_action, from Notes/gone.pug 00000000\np= \$record['message']\n",
            'snap/qa_export_rows.pug' => "p= \$undefined_function_on_purpose()\n",
        ));
        $html = Views::card('qa_list_notes', array('data' => array(array('title' => 'Dune'), array('title' => '<b>Kindred</b>')), 'page' => 1, 'pages' => 1, 'total' => 2), array());
        $this->assertStringContainsString('<ul class="notes">', $html, 'rendered on the server from views/snap/<capability>.pug');
        $this->assertStringContainsString('data-snap-open="qa_echo_note"', $html, 'a link opens another card');
        $this->assertStringContainsString('&lt;b&gt;Kindred&lt;/b&gt;', $html, 'values are escaped as in any view');
        $this->assertStringContainsString('2 notes', $html, '$rows and $page for a list');
        $this->assertSame('<p>Sent</p>', Views::card('qa_web_action', array('message' => 'Sent'), array()), '$record for one record');
        $log = ini_set('error_log', '/dev/null');   // it is logged; not on the test's output
        $this->assertNull(Views::card('qa_export_rows', array(), array()), 'a card view that fails gives a designed card (and is logged)');
        ini_set('error_log', (string) $log);
        $this->assertNull(Views::card('qa_purge_notes', array(), array()), 'none without its file');
        $this->hints("array('qa_list_notes' => array('card' => false))");
        $this->assertNull(Views::card('qa_list_notes', array(), array()), "the hint 'card' => false");

        $cards = Views::cards();
        $this->assertSame(array('from' => 'Notes/index', 'stale' => false), array('from' => $cards['qa_list_notes']['from'], 'stale' => $cards['qa_list_notes']['stale']));
        $this->assertTrue($cards['qa_web_action']['stale'], 'stale: its page changed or is gone');
        $this->assertNull($cards['qa_export_rows']['stale'], 'unknown: its first line names no page');
        $this->views(array('Notes/index.pug' => "section(data-snap='qa_list_notes')\n  ol\n"));
        $this->assertTrue(Views::cards()['qa_list_notes']['stale'], 'the page changed since the card view was made');
    }

    public function testThePageInACardIsThePageAloneAndItsHeight(): void
    {
        $_GET['snap-embed'] = 'qa_list_notes';
        $html = Views::page('<html><head><title>x</title></head><body><main>Notes</main></body></html>');
        unset($_GET['snap-embed']);
        $this->assertMatchesRegularExpression('#snap-embed\.css[^>]*></head>#', $html, 'its style sheet hides the site frame');
        $this->assertMatchesRegularExpression('#snap-embed\.js[^>]*data-reads="1"></script></body>#', $html, 'a card that reads shows the page without its forms');
        $js = file_get_contents(dirname(__DIR__, 2) . '/public/snap/snap-embed.js');
        foreach (array('data-snap', 'postMessage', 'unframe', 'unroll') as $part) {
            $this->assertStringContainsString($part, $js);
        }
    }

    public function testWithoutAPageTheCardIsDesigned(): void
    {
        putenv('CACHE_STORE=off');
        Designer::$model = false;   // the rules
        $rows = Designer::rows('qa_people', array(
            array('id' => 4, 'name' => 'Ada Lovelace', 'title' => 'Analyst', 'initials' => 'AL', 'email' => 'ada@example.test', 'created_at' => '2026-11-01 10:00:00', 'is_admin' => 1),
        ));
        $this->assertSame('Ada Lovelace', $rows[0]['title'], 'a name is who someone is');
        $this->assertSame('Analyst', $rows[0]['subtitle'], 'a title what they do');
        $this->assertNotContains('AL', $rows[0]['meta'], 'initials and ids are not for people');
        $this->assertNotContains('Nov 1, 2026', $rows[0]['meta'], 'nor when a row was written');
        $this->assertSame('Admin', $rows[0]['badge']['text'], 'a true flag is a badge');
        $authors = Designer::rows('qa_authors', array(array('id' => 1, 'name' => 'Italo Calvino', 'bio' => 'Italian writer of playful, inventive novels.')));
        $this->assertSame('Italian writer of playful, inventive novels.', $authors[0]['subtitle']);

        $blocks = Designer::record('qa_book', array('id' => 1, 'title' => 'Kindred', 'author' => array('id' => 2, 'name' => 'Octavia E. Butler'),
            'pages' => 264, 'status' => 'overdue', 'summary' => str_repeat('A time-travel novel. ', 10)));
        $types = array_column($blocks, 'type');
        $this->assertSame('hero', $types[0]);
        $this->assertSame(array('Kindred', 'Octavia E. Butler'), array($blocks[0]['title'], $blocks[0]['subtitle']));
        $this->assertSame('danger', $blocks[0]['badges'][0]['tone'], 'a state is a badge with a tone');
        $this->assertContains('text', $types, 'a long text reads as text');
        $this->assertStringNotContainsString('"id"', json_encode($blocks), 'no ids');
    }

    public function testClaudeDesignsItWithTheDataOwnFieldsOnly(): void
    {
        putenv('CACHE_STORE=off');
        $sent = null;
        Designer::$transport = function ($request) use (&$sent) {
            $sent = $request;
            $design = array('title' => 'name', 'subtitle' => 'role', 'image' => '', 'meta' => array(array('path' => 'cases', 'label' => 'Cases', 'format' => 'number'),
                array('path' => 'password', 'label' => 'Secret', 'format' => 'text')),   // not a field of the data: left out
                'badges' => array(array('path' => 'lead', 'label' => 'Lead', 'tones' => array(array('value' => '1', 'tone' => 'success')))));
            return (object) array('content' => array((object) array('type' => 'text', 'text' => json_encode($design))));
        };
        $rows = Designer::rows('qa_team', array(array('name' => 'Grace', 'role' => 'Engineer', 'cases' => 3, 'lead' => true)));
        $this->assertSame('json_schema', $sent['outputConfig']['format']['type'], 'a structured output, not a forced tool');
        $this->assertArrayNotHasKey('toolChoice', $sent);
        $this->assertSame(array('title' => 'Grace', 'subtitle' => 'Engineer', 'meta' => array('3 cases'), 'badge' => array('text' => 'Lead', 'tone' => 'success')), $rows[0]);

        $this->hints("array('qa_team' => array('design' => array('title' => 'role')))");
        $this->assertSame('Engineer', Designer::rows('qa_team', array(array('name' => 'Grace', 'role' => 'Engineer')))[0]['title'], "the hint 'design' comes first");
    }

    public function testACardIsNamedByItsOwnNameThenWhatItIsAboutThenItsPeople(): void
    {
        $this->assertSame('Pediatric Brain Cancer', Cards::name(array('id' => 1, 'code' => 'Pediatric Brain Cancer', 'owner' => array('name' => 'John Doe'))), 'a code names it before its owner');
        $this->assertSame('Kindred', Cards::name(array('id' => 4, 'borrower' => array('name' => 'Sam'), 'book' => array('title' => 'Kindred'))), 'the thing it is about before the people around it');
        $this->assertSame('Sam', Cards::name(array('id' => 4, 'borrower' => array('name' => 'Sam'))), 'the people when nothing else names it');
        $this->assertSame('#9', Cards::name(array('id' => 9)));
    }
}
