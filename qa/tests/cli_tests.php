<?php
/**
 * Framework-level tests (helpers, Model, Database, payments, AI request building).
 * Run inside the QA web container:  php qa/tests/cli_tests.php
 */

require __DIR__ . '/bootstrap.php';

use Caligrafy\Database;
use Caligrafy\Model;
use Caligrafy\OpenAI;
use Caligrafy\Payment;

Database::connect();

function qa_reset_tables()
{
    Database::execute('SET FOREIGN_KEY_CHECKS = 0');
    foreach (Database::toArray(Database::execute("SHOW TABLES LIKE 'qa\\_%'")) as $row) {
        Database::execute('DROP TABLE `' . array_values($row)[0] . '`');
    }
    Database::execute('SET FOREIGN_KEY_CHECKS = 1');
    Database::execute('CREATE TABLE qa_publishers (id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT, publishername VARCHAR(255) NOT NULL, PRIMARY KEY (id)) ENGINE = InnoDB');
    Database::execute('CREATE TABLE qa_books (id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(255) NOT NULL, stock INT NOT NULL DEFAULT 5, featured TINYINT(1) NOT NULL DEFAULT 1, note VARCHAR(255) NULL DEFAULT "n/a", qa_publisher_id INT(25) UNSIGNED NULL, PRIMARY KEY (id)) ENGINE = InnoDB');
    Database::execute('CREATE TABLE qa_tags (id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT, label VARCHAR(50) NOT NULL, PRIMARY KEY (id)) ENGINE = InnoDB');
    Database::execute('CREATE TABLE qa_readers (id INT(15) NOT NULL AUTO_INCREMENT, name VARCHAR(50) NOT NULL, PRIMARY KEY (id)) ENGINE = InnoDB');
    Database::execute("INSERT INTO qa_publishers (publishername) VALUES ('Penguin'), ('Orbit')");
    Database::execute("INSERT INTO qa_books (title, qa_publisher_id) VALUES ('Dune', 1), ('Emma', 1), ('Ubik', 2)");
    Database::execute("INSERT INTO qa_tags (label) VALUES ('classic'), ('scifi')");
    Database::execute("INSERT INTO qa_readers (name) VALUES ('Ann'), ('Bob')");
}

class Qa_book extends Model {
    public $id; public $title; public $stock; public $featured; public $note; public $qa_publisher_id;
    public function publisher() { return $this->hasOne('Qa_publisher', 'qa_publishers'); }
    public function tags() { return $this->belongsToMany('Qa_tag', 'qa_tags'); }
    public function readers() { return $this->belongsToMany('Qa_reader', 'qa_readers'); }
}
class Qa_publisher extends Model {
    public $id; public $publishername;
    public function books() { return $this->hasMany('Qa_book', 'qa_books'); }
}
class Qa_tag extends Model {
    public $id; public $label;
    public function books() { return $this->belongsToMany('Qa_book', 'qa_books'); }
}
class Qa_reader extends Model { public $id; public $name; }
class Qa_book_with_helpers extends Qa_book { public $errors = []; public $isNew = false; public $label = ''; }
class Qa_long_named_category extends Model {
    public $id; public $name;
    public function subcategories() { return $this->belongsToMany('Qa_long_named_subcategory', 'qa_product_subcategories_long'); }
}
class Qa_long_named_subcategory extends Model { public $id; public $name; }

function book($id) { return (new Qa_book('qa_books'))->find($id); }

// ---------------------------------------------------------------------------
qa_section('Model: CRUD keeps falsy values (0, "", false) and only skips null');
qa_reset_tables();

qa_test('update() writes 0, "" and false', function () {
    $m = new Qa_book('qa_books');
    $m->update(['stock' => 0, 'note' => '', 'featured' => false], 1);
    $b = book(1);
    qa_equals('0', (string)$b->stock, 'stock');
    qa_equals('', $b->note, 'note');
    qa_equals('0', (string)$b->featured, 'featured');
});

qa_test('create() writes 0 and false, and null keeps the column default', function () {
    $m = new Qa_book('qa_books');
    $b = $m->create(['title' => 'Zero', 'stock' => 0, 'featured' => false, 'note' => null]);
    qa_equals('0', (string)$b->stock);
    qa_equals('0', (string)$b->featured);
    qa_equals('n/a', $b->note, 'null must be skipped so the DB default applies');
});

qa_test('helper properties that are not columns are ignored by save()', function () {
    $b = new Qa_book_with_helpers('qa_books');
    $b->title = 'Helpers';
    $saved = $b->save();
    qa_equals('Helpers', $saved->title);
    $saved->errors = ['x'];
    $saved->title = 'Helpers 2';
    qa_equals('Helpers 2', $saved->save()->title);
});

qa_test('save() on a model loaded from the DB updates without wiping other columns', function () {
    $b = book(2);
    $b->title = 'Emma (2nd ed.)';
    $b->save();
    $after = book(2);
    qa_equals('Emma (2nd ed.)', $after->title);
    qa_equals('1', (string)$after->qa_publisher_id);
});

// ---------------------------------------------------------------------------
qa_section('Model: SQL injection');
qa_reset_tables();

qa_test('update() binds the id: "1 OR 1=1" cannot update every row', function () {
    $m = new Qa_book('qa_books');
    try { $m->update(['title' => 'HACKED'], '1 OR 1=1'); } catch (Throwable $e) { /* rejecting is fine too */ }
    $hacked = Database::toArray(Database::execute("SELECT COUNT(*) c FROM qa_books WHERE title = 'HACKED'"))[0]['c'];
    qa_assert((int)$hacked <= 1, "$hacked rows were overwritten");
    qa_equals('Ubik', book(3)->title, 'row 3 must be untouched');
});

qa_test('search() binds the keywords: a quote does not break the query or leak rows', function () {
    $m = new Qa_book('qa_books');
    $rows = $m->search('title', "x' OR '1'='1");
    qa_equals(0, count($rows));
});

qa_test('search() still supports % and _ wildcards and several columns', function () {
    $m = new Qa_book('qa_books');
    qa_equals(1, count($m->search('title', 'D%')));
    qa_equals(1, count($m->search('title', '_mma')));
    qa_equals(1, count($m->search('title, note', 'Ubik')));
});

qa_test('search() rejects a scope that is not a column name', function () {
    $m = new Qa_book('qa_books');
    $failed = false;
    try { $m->search('title` = 1 OR `id', 'x'); } catch (Throwable $e) { $failed = true; }
    qa_assert($failed, 'an invalid column name must throw');
});

qa_test('all() accepts bound parameters', function () {
    $m = new Qa_book('qa_books');
    $rows = $m->all(['condition' => 'WHERE title = ?', 'order' => 'ORDER BY id DESC', 'bindings' => ["Dune"]]);
    qa_equals(1, count($rows));
    qa_equals('Dune', $rows[0]->title);
});

qa_test('all() without bindings keeps working (backward compatible)', function () {
    $m = new Qa_book('qa_books');
    qa_equals(3, count($m->all()));
    qa_equals(2, count($m->all(['order' => 'ORDER BY id', 'limit' => 'LIMIT 2'])));
});

// ---------------------------------------------------------------------------
qa_section('Model: relationships');
qa_reset_tables();

qa_test('hasOne() returns the parent model', function () {
    qa_equals('Penguin', book(1)->publisher()->publishername);
});

qa_test('hasMany() returns the children', function () {
    $p = (new Qa_publisher('qa_publishers'))->find(1);
    qa_equals(2, count($p->books()));
});

qa_test('belongsToMany() creates a pivot for UNSIGNED ids and attach/detach work', function () {
    $b = book(1);
    $b->tags();
    $tags = $b->attach('qa_tags', 1);
    qa_equals(1, count($tags));
    $tags = $b->attach('qa_tags', 2);
    qa_equals(2, count($tags));
    $tags = $b->detach('qa_tags', 1);
    qa_equals(1, count($tags));
    qa_equals('scifi', $tags[0]->label);
});

qa_test('a second pivot in the same database is created (unique FK names) with SIGNED ids', function () {
    $b = book(2);
    $b->readers();
    $readers = $b->attach('qa_readers', 2);
    qa_equals(1, count($readers));
    qa_equals('Bob', $readers[0]->name);
});

qa_test('pivot constraint names stay within the 64-character MySQL limit', function () {
    Database::execute('CREATE TABLE qa_product_categories_long (id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(20), PRIMARY KEY (id))');
    Database::execute('CREATE TABLE qa_product_subcategories_long (id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(20), PRIMARY KEY (id))');
    Database::execute("INSERT INTO qa_product_categories_long (name) VALUES ('c')");
    Database::execute("INSERT INTO qa_product_subcategories_long (name) VALUES ('s')");
    $c = (new Qa_long_named_category('qa_product_categories_long'))->find(1);
    $c->subcategories();
    qa_equals(1, count($c->attach('qa_product_subcategories_long', 1)));
});

qa_test('the pivot is shared from the other side of the relationship', function () {
    $t = (new Qa_tag('qa_tags'))->find(2);
    qa_equals(1, count($t->books()));
});

// ---------------------------------------------------------------------------
qa_section('Validation (documented behavior of Controller::check/validate/filter)');

qa_test('check() with rules returns true or an array of messages keyed by field', function () {
    $c = new Caligrafy\Controller();
    qa_equals(true, $c->check(['username' => 'bob42'], ['username' => 'required|alpha_numeric']));
    $errors = $c->check(['username' => 'bob 42!'], ['username' => 'required|alpha_numeric']);
    qa_assert(is_array($errors) && isset($errors['username']), json_encode($errors));
});

qa_test('check() with rules and filters returns the filtered data on success', function () {
    $c = new Caligrafy\Controller();
    $out = $c->check(['username' => '  bob42  '], ['username' => 'required|alpha_numeric'], ['username' => 'trim']);
    qa_equals(['username' => 'bob42'], $out);
});

qa_test('validate() and filter()', function () {
    $c = new Caligrafy\Controller();
    qa_equals(true, $c->validate(['n' => '5'], ['n' => 'integer|min_numeric,1']));
    qa_assert(is_array($c->validate(['n' => '0'], ['n' => 'integer|min_numeric,1'])));
    qa_equals(['e' => 'a@b.co'], $c->filter(['e' => ' A@B.CO '], ['e' => 'trim|lower_case']));
});

qa_test('an empty optional field skips its rules (only required checks emptiness)', function () {
    $c = new Caligrafy\Controller();
    qa_equals(true, $c->check(['passcode' => ''], ['passcode' => 'min_len,8|max_len,72']));
});

qa_test('GUMP::is_valid() with custom messages returns true or readable messages', function () {
    qa_equals(true, GUMP::is_valid(['u' => 'x'], ['u' => 'required']));
    $r = GUMP::is_valid(['u' => ''], ['u' => 'required'], ['u' => ['required' => 'Username is required']]);
    qa_assert(is_array($r) && strpos(implode(' ', $r), 'Username is required') !== false, json_encode($r));
});

// ---------------------------------------------------------------------------
qa_section('Database connection');

qa_test('DB_PORT is used in the DSN', function () {
    $dsn = Database::dsn('mysql', 'db.example', 'quill', '3307');
    qa_contains('port=3307', $dsn);
    qa_contains('host=db.example', $dsn);
});

qa_test('a DB_HOST that already carries ";port=" is left alone', function () {
    $dsn = Database::dsn('mysql', '127.0.0.1;port=3308', 'quill', '3306');
    qa_equals(1, substr_count($dsn, 'port='));
    qa_contains('port=3308', $dsn);
});

// ---------------------------------------------------------------------------
qa_section('Helpers');

qa_test('endWith() compares the whole needle', function () {
    qa_assert(endWith('index.php', '.php'), 'index.php ends with .php');
    qa_assert(!endWith('index.phpx', '.php'), 'index.phpx does not end with .php');
    qa_assert(!endWith('photo.jpg', 'xpg'), 'photo.jpg does not end with xpg');
    qa_assert(endWith('path/', '/'));
});

qa_test('startWith() still works', function () {
    qa_assert(startWith('/books', '/'));
    qa_assert(!startWith('books', '/'));
});

qa_test('session() stores falsy values when a value is given', function () {
    session('qa_zero', 0);
    qa_equals(0, session('qa_zero'));
    session('qa_false', false);
    qa_equals(false, session('qa_false'));
    session('qa_empty', '');
    qa_equals('', session('qa_empty'));
    session('qa_keep', 'x');
    qa_equals('x', session('qa_keep'), 'reading must not erase');
});

qa_test('scripts(), keywords() and copyright() accept empty strings', function () {
    keywords('');
    qa_equals('', keywords());
    copyright('');
    qa_equals('', copyright());
    scripts('qa', '');
    qa_equals('', scripts('qa'));
});

// echo server for outgoing requests
$echo = proc_open('php -S 127.0.0.1:8765 qa/fixtures/echo.php', [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
usleep(600000);

qa_test('httpRequest() sends the body for PUT, PATCH and DELETE (not only POST)', function () {
    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $r = httpRequest('http://127.0.0.1:8765/x', $method, ['a' => 1], ['Content-Type: application/json']);
        qa_equals($method, $r['echo']['method']);
        qa_equals('{"a":1}', $r['echo']['body'], "$method body");
    }
    $r = httpRequest('http://127.0.0.1:8765/x', 'GET', ['a' => 1], []);
    qa_equals('', $r['echo']['body'], 'GET must not send a body');
});

qa_test('uploadFile() refuses executable and unknown extensions', function () {
    foreach (['shell.php', 'SHELL.PHP', 'x.phtml', 'x.phar', 'x.php5', '.htaccess', 'noextension', 'x.svg', 'x.html', 'x.js'] as $name) {
        qa_assert(!isAllowedUpload($name), "$name must be refused by the allow-list");
        $tmp = tempnam(sys_get_temp_dir(), 'qa');
        file_put_contents($tmp, '<?php echo 1;');
        qa_equals('', uploadFile(['name' => $name, 'tmp_name' => $tmp, 'error' => 0, 'size' => 13]), "$name must be rejected");
    }
});

qa_test('uploadFile() accepts allowed extensions and a custom allow-list', function () {
    // move_uploaded_file() only moves real HTTP uploads: the HTTP suite covers the actual move.
    qa_assert(isAllowedUpload('photo.JPG'), 'jpg allowed by default');
    qa_assert(isAllowedUpload('report.pdf'), 'pdf allowed by default');
    qa_assert(!isAllowedUpload('report.pdf', ['png']), 'custom list overrides the default');
    qa_assert(isAllowedUpload('data.csv', ['csv']), 'custom list');
    qa_assert(!isAllowedUpload('x.php', ['php']), 'php-like extensions are never allowed');
});

qa_test('APP_DEBUG=false turns the SMTP debug output off', function () {
    $code = 'define("APP_DEBUG", "%s"); require "qa/tests/bootstrap.php"; $m = new Caligrafy\Mail(); $p = new ReflectionProperty($m, "mailer"); echo $p->getValue($m)->SMTPDebug;';
    qa_equals('0', trim(shell_exec('php -r ' . escapeshellarg(sprintf($code, 'false')) . ' 2>/dev/null')));
    qa_equals('1', trim(shell_exec('php -r ' . escapeshellarg(sprintf($code, 'true')) . ' 2>/dev/null')));
});

// ---------------------------------------------------------------------------
qa_section('OpenAI');

qa_test('model constants are valid OpenAI ids', function () {
    qa_equals('gpt-4o-mini', GPT4OMINI);
    class_exists('Caligrafy\\Assistant');
    qa_equals('gpt-4o-mini', ASSISTANT_GPT4OMINI);
    qa_equals('gpt-6-luna', ASSISTANT_DEFAULT_MODEL);
    qa_assert(defined('GPT4_32'), 'GPT4_32 alias is defined');
});

qa_test('OpenAI::command() only resolves the documented commands', function () {
    qa_equals(SUMMARIZE, OpenAI::command('summarize'));
    qa_equals(ANALYZE, OpenAI::command('ANALYZE'));
    qa_equals(null, OpenAI::command('APP_KEY'));
    qa_equals('', OpenAI::command('custom'), 'CUSTOM is valid (empty system prompt)');
    qa_equals(null, OpenAI::command('DB_PASSWORD'));
    qa_equals(TEXT, OpenAI::type('text'));
    qa_equals(IMAGE, OpenAI::type('image'));
    qa_equals(null, OpenAI::type('OPEN_AI_KEY'));
});

qa_test('COMPLETE keeps the text after [insert]', function () {
    $ai = new OpenAI();
    $prop = new ReflectionProperty($ai, '_openai_url');
    $prop->setValue($ai, 'http://127.0.0.1:8765/v1/chat/completions');
    $out = $ai->delegate('The cat [insert] on the mat.', '', COMPLETE);
    $body = json_decode($out['response']['echo']['body'], true);
    $user = end($body['messages'])['content'];
    qa_contains('The cat', $user);
    qa_contains('on the mat.', $user, 'suffix must be sent');
    qa_contains('[insert]', $user);
});

qa_test('other commands still send system + user messages', function () {
    $ai = new OpenAI();
    $prop = new ReflectionProperty($ai, '_openai_url');
    $prop->setValue($ai, 'http://127.0.0.1:8765/v1/chat/completions');
    $out = $ai->delegate('Some text', 'one line', SUMMARIZE, [], ['model' => 'gpt-4o-mini']);
    $body = json_decode($out['response']['echo']['body'], true);
    qa_equals('gpt-4o-mini', $body['model']);
    qa_equals('system', $body['messages'][0]['role']);
    qa_equals(SUMMARIZE, $body['messages'][0]['content']);
    qa_contains('one line', $body['messages'][1]['content']);
});

// ---------------------------------------------------------------------------
qa_section('OpenAI Assistant on the Responses and Conversations APIs (mock OpenAI server)');

$mock = proc_open('php -S 127.0.0.1:8766 qa/fixtures/openai_mock.php', [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $mockPipes);
usleep(600000);

function qa_assistant_url($object)
{
    $p = new ReflectionProperty($object, '_api_url');
    $p->setValue($object, 'http://127.0.0.1:8766/v1');
    return $object;
}

function qa_assistant($parameters = [])
{
    // build without the constructor so that no request reaches api.openai.com, then configure through the mock
    $ref = new ReflectionClass('Caligrafy\Assistant');
    $a = qa_assistant_url($ref->newInstanceWithoutConstructor());
    $a->__construct($parameters);
    return $a;
}

function qa_mock_requests($filter = null)
{
    $log = sys_get_temp_dir() . '/qa_openai_mock.log';
    $lines = file_exists($log)? array_filter(explode("\n", file_get_contents($log))) : [];
    $requests = array_map(function ($l) { return json_decode($l, true); }, $lines);
    return array_values($filter? array_filter($requests, $filter) : $requests);
}

function qa_mock_reset() { @unlink(sys_get_temp_dir() . '/qa_openai_mock.log'); }

qa_test('start() creates a conversation with an empty JSON object', function () {
    qa_mock_reset();
    $a = qa_assistant(['name' => 'QA'])->start();
    qa_equals('conv_qa', $a->thread_id);
    $req = qa_mock_requests();
    qa_equals('POST', $req[0]['method']);
    qa_equals('/v1/conversations', $req[0]['uri']);
    qa_equals('{}', $req[0]['body']);
});

qa_test('createMessage() + run() send one response request with the conversation and read the answer', function () {
    qa_mock_reset();
    $a = qa_assistant(['instructions' => 'Be brief'])->start()->createMessage('Hi')->run();
    qa_equals('resp_qa', $a->run_id);
    qa_equals('completed', $a->status);
    qa_equals('You said: Hi', $a->text());
    qa_equals(['user', 'assistant'], array_column($a->messages, 'role'));
    $body = json_decode(qa_mock_requests(function ($r) { return $r['uri'] === '/v1/responses'; })[0]['body'], true);
    qa_equals(GPT6_LUNA, $body['model'], 'default model');
    qa_equals('conv_qa', $body['conversation']);
    qa_equals('Be brief', $body['instructions']);
    qa_equals([['role' => 'user', 'content' => 'Hi']], $body['input']);
    qa_assert(!isset($body['tools']), 'file_search without a vector store must not be sent');
});

qa_test('the documented chain returns the messages', function () {
    $messages = qa_assistant(['name' => 'QA'])->start()->createMessage('Hello there')->run()->retrieveResponse()->messages;
    qa_equals('You said: Hello there', end($messages)['content'][0]['text']);
});

qa_test('file_paths are uploaded to a vector store used by file_search; code_interpreter gets the files', function () {
    qa_mock_reset();
    $tmp = tempnam(sys_get_temp_dir(), 'qa') . '.txt';
    file_put_contents($tmp, 'knowledge');
    $a = qa_assistant(['name' => 'Docs', 'file_paths' => [$tmp], 'tools' => [['type' => 'file_search'], ['type' => 'code_interpreter']]]);
    qa_equals('vs_qa', $a->vector_store_id);
    qa_equals(1, count($a->file_ids));
    $a->start()->createMessage('What is in the file?')->run('', null, [], 'gpt-6-astra');
    $uris = array_column(qa_mock_requests(), 'uri');
    qa_equals(['/v1/files', '/v1/vector_stores', '/v1/conversations', '/v1/responses'], $uris);
    $upload = json_decode(qa_mock_requests()[0]['body'], true);
    qa_equals(['file'], $upload['multipart'], 'multipart upload');
    qa_equals('assistants', $upload['fields']['purpose']);
    $body = json_decode(qa_mock_requests(function ($r) { return $r['uri'] === '/v1/responses'; })[0]['body'], true);
    qa_equals('gpt-6-astra', $body['model']);
    qa_equals(['type' => 'file_search', 'vector_store_ids' => ['vs_qa']], $body['tools'][0]);
    qa_equals(['type' => 'auto', 'file_ids' => $a->file_ids], $body['tools'][1]['container']);
});

qa_test('a response still in progress is polled until it completes', function () {
    $a = qa_assistant(['metadata' => ['qa' => 'background']])->start()->createMessage('Long task')->run();
    qa_equals('in_progress', $a->status);
    $a->retrieveResponse(10, 0);
    qa_equals('completed', $a->status);
    qa_equals('Done in the background', $a->text());
});

qa_test('retrieveResponse() gives up after the timeout', function () {
    $start = microtime(true);
    $a = qa_assistant(['metadata' => ['qa' => 'stuck']])->start()->createMessage('Never ends')->run()->retrieveResponse(2, 1);
    $elapsed = microtime(true) - $start;
    qa_equals('in_progress', $a->status);
    qa_equals('', $a->text());
    qa_assert($elapsed >= 2 && $elapsed < 6, "took $elapsed s");
});

qa_test('end(), deleteAssistantFile() and delete() call the right endpoints', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'qa') . '.txt';
    file_put_contents($tmp, 'x');
    $a = qa_assistant(['file_paths' => [$tmp]])->start();
    qa_mock_reset();
    $fileId = $a->file_ids[0];
    $a->end();
    qa_equals('', $a->thread_id);
    qa_equals(true, $a->deleteAssistantFile($fileId));
    qa_equals(true, $a->delete());
    $calls = array_map(function ($r) { return $r['method'] . ' ' . $r['uri']; }, qa_mock_requests());
    qa_equals(['DELETE /v1/conversations/conv_qa', "DELETE /v1/vector_stores/vs_qa/files/$fileId", "DELETE /v1/files/$fileId", 'DELETE /v1/vector_stores/vs_qa'], $calls);
});

qa_test('OpenAI IMAGE uses a GPT image model and returns base64 data', function () {
    $ai = new OpenAI(IMAGE);
    $p = new ReflectionProperty($ai, '_openai_url');
    $p->setValue($ai, 'http://127.0.0.1:8766/v1/images/generations');
    $out = $ai->delegate('A red bicycle');
    qa_equals(base64_encode('QA-PNG'), $out['response'][0]['b64_json']);
    qa_equals('image', $out['type']);
    qa_equals('gpt-image-2', OPENAI_DEFAULT_IMAGE_MODEL);
});

qa_test('OpenAI TEXT defaults to a current model and sends no legacy sampling parameters', function () {
    $ai = new OpenAI();
    qa_equals(['model' => GPT6_LUNA], $ai->parameters);
    qa_equals('gpt-6-astra', GPT4_LATEST, 'GPT4_LATEST points to the current flagship');
});

proc_terminate($mock);

proc_terminate($echo);

// ---------------------------------------------------------------------------
qa_section('Stripe payment');

class QaFakeSessions { public $params; public $opts; public function create($p, $o = []) { $this->params = $p; $this->opts = $o; return (object)['id' => 'cs_test_qa']; } }
class QaFakeStripe { public $checkout; public function __construct() { $this->checkout = (object)['sessions' => new QaFakeSessions()]; } }

function qa_payment_with_fake()
{
    $p = new Payment();
    $fake = new QaFakeStripe();
    $prop = new ReflectionProperty($p, '_stripe');
    $prop->setValue($p, $fake);
    return [$p, $fake];
}

qa_test('createCheckout() with a locale and a customer email', function () {
    [$p, $fake] = qa_payment_with_fake();
    $r = $p->createCheckout(2000, 'usd', 2, ['name' => 'T-shirt'], 'https://ok', 'https://ko', 'buyer@example.com', 'fr');
    qa_equals(true, $r['action_success'], json_encode($r));
    $params = $fake->checkout->sessions->params;
    qa_equals('fr', $params['locale']);
    qa_equals('buyer@example.com', $params['customer_email']);
    qa_equals(2000, $params['line_items'][0]['price_data']['unit_amount']);
    qa_equals(2, $params['line_items'][0]['quantity']);
    qa_equals('payment', $params['mode']);
    qa_equals('https://ok', $params['success_url']);
});

qa_test('createCheckout() with the defaults is unchanged', function () {
    [$p, $fake] = qa_payment_with_fake();
    $r = $p->createCheckout(1500, 'usd', 1, ['name' => 'Mug'], 'https://ok', 'https://ko');
    qa_equals(true, $r['action_success']);
    $params = $fake->checkout->sessions->params;
    qa_assert(!isset($params['locale']) && !isset($params['customer_email']));
    qa_assert(!isset($params['payment_method_types']), 'payment methods come from the dashboard by default');
});

qa_section('Stripe payment against stripe-mock (stripe-php ' . \Stripe\Stripe::VERSION . ')');

function qa_payment_mock()
{
    $p = new Payment();
    $prop = new ReflectionProperty($p, '_stripe');
    $prop->setValue($p, new \Stripe\StripeClient(['api_key' => 'sk_test_123', 'api_base' => 'http://stripe-mock:12111']));
    return $p;
}

qa_test('createPaymentIntent() and retrieveIntent()', function () {
    $p = qa_payment_mock();
    $r = $p->createPaymentIntent(1000, 'usd', ['order' => '42'], 'buyer@example.com', 'T-shirt');
    qa_equals(true, $r['action_success'], json_encode($r));
    qa_assert(!empty($r['data']->client_secret), 'client_secret');
    $again = $p->retrieveIntent($r['data']->id);
    qa_equals(true, $again['action_success'], json_encode($again));
});

qa_test('createCheckout() returns the hosted page url', function () {
    $p = qa_payment_mock();
    $r = $p->createCheckout(2000, 'usd', 1, ['name' => 'Mug'], 'https://example.com/ok', 'https://example.com/ko', 'buyer@example.com', 'fr');
    qa_equals(true, $r['action_success'], json_encode($r));
    qa_assert(array_key_exists('url', $r), 'url key');
    qa_equals(true, $p->retrieveCheckout($r['data']->id)['action_success']);
});

qa_test('createTransaction() with a token and the Connect helpers', function () {
    $p = qa_payment_mock();
    qa_equals(true, $p->createTransaction(500, 'usd', 'tok_visa')['action_success']);
    $account = $p->createAccount('vendor@example.com', 'US');
    qa_equals(true, $account['action_success'], json_encode($account));
    qa_equals(true, $p->createAccountLink($account['data']->id, 'https://example.com/r', 'https://example.com/b')['action_success']);
    qa_equals(true, $p->retrieve($account['data']->id)['action_success']);
});

qa_test('Stripe errors are returned, not thrown', function () {
    $p = new Payment();
    $prop = new ReflectionProperty($p, '_stripe');
    $prop->setValue($p, new \Stripe\StripeClient(['api_key' => 'sk_test_123', 'api_base' => 'http://127.0.0.1:1']));
    $r = $p->createPaymentIntent(1000, 'usd');
    qa_equals(false, $r['action_success']);
    qa_assert(!empty($r['error']));
});

qa_section('Crypto payment: Coinbase Business Checkouts API (mock server, real JWT signatures)');

use Caligrafy\CryptoPayment;

$coinbaseMock = proc_open('php -S 127.0.0.1:8767 qa/fixtures/coinbase_mock.php', [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $cbPipes);
usleep(600000);

function qa_b64url_decode($s) { return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

function qa_crypto($name, $secret)
{
    $c = new CryptoPayment($name, $secret);
    foreach (['_host' => '127.0.0.1:8767', '_scheme' => 'http'] as $prop => $value) {
        $p = new ReflectionProperty($c, $prop);
        $p->setValue($c, $value);
    }
    return $c;
}

function qa_coinbase_requests()
{
    $log = sys_get_temp_dir() . '/qa_coinbase_mock.log';
    $lines = file_exists($log)? array_filter(explode("\n", file_get_contents($log))) : [];
    return array_map(function ($l) { return json_decode($l, true); }, array_values($lines));
}

// a CDP Ed25519 secret is base64(seed . public key)
$cbKeypair = sodium_crypto_sign_seed_keypair(random_bytes(32));
$cbSecret = base64_encode(substr(sodium_crypto_sign_secretkey($cbKeypair), 0, 32) . sodium_crypto_sign_publickey($cbKeypair));
$cbName = 'organizations/org-qa/apiKeys/key-qa';

qa_test('createTransaction() creates a checkout and returns the hosted url', function () use ($cbName, $cbSecret) {
    @unlink(sys_get_temp_dir() . '/qa_coinbase_mock.log');
    $r = qa_crypto($cbName, $cbSecret)->createTransaction(10, 'usd', ['name' => 'T-shirt', 'description' => 'Blue'], ['order' => 42], 'https://example.com/ok', 'https://example.com/ko');
    qa_equals(true, $r['action_success'], json_encode($r));
    qa_equals('https://payments.coinbase.com/payment-links/pl_qa', $r['url']);
    qa_equals($r['url'], $r['data']['hosted_url'], 'backward-compatible hosted_url');
    $req = qa_coinbase_requests()[0];
    qa_equals(['amount' => '10.00', 'currency' => 'USD', 'description' => 'T-shirt - Blue', 'metadata' => ['order' => '42'],
               'successRedirectUrl' => 'https://example.com/ok', 'failRedirectUrl' => 'https://example.com/ko'], json_decode($req['body'], true));
    qa_assert(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $req['idempotency']), 'idempotency key is a UUID v4');
});

qa_test('the Ed25519 JWT has the CDP header and claims and a valid signature', function () use ($cbName, $cbSecret, $cbKeypair) {
    $jwt = substr(qa_coinbase_requests()[0]['auth'], 7);
    [$h, $p, $sig] = explode('.', $jwt);
    $header = json_decode(qa_b64url_decode($h), true);
    $claims = json_decode(qa_b64url_decode($p), true);
    qa_equals('EdDSA', $header['alg']);
    qa_equals($cbName, $header['kid']);
    qa_assert(strlen($header['nonce']) >= 16, 'nonce');
    qa_equals($cbName, $claims['sub']);
    qa_equals('cdp', $claims['iss']);
    qa_equals(['cdp_service'], $claims['aud']);
    qa_equals(120, $claims['exp'] - $claims['nbf']);
    qa_equals('POST 127.0.0.1:8767/api/v1/checkouts', $claims['uri']);
    qa_assert(sodium_crypto_sign_verify_detached(qa_b64url_decode($sig), "$h.$p", sodium_crypto_sign_publickey($cbKeypair)), 'signature verifies with the public key');
});

qa_test('a legacy ECDSA (PEM) key signs ES256', function () use ($cbName) {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);
    $jwt = qa_crypto($cbName, str_replace("\n", '\n', $pem))->jwt('GET', '/api/v1/checkouts');
    [$h, $p, $sig] = explode('.', $jwt);
    qa_equals('ES256', json_decode(qa_b64url_decode($h), true)['alg']);
    $raw = qa_b64url_decode($sig);
    qa_equals(64, strlen($raw));
    // rebuild the DER signature to verify it with openssl
    $int = function ($b) { $b = ltrim($b, "\x00"); if (ord($b[0]) & 0x80) $b = "\x00" . $b; return "\x02" . chr(strlen($b)) . $b; };
    $seq = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
    $der = "\x30" . chr(strlen($seq)) . $seq;
    qa_equals(1, openssl_verify("$h.$p", $der, openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256));
});

qa_test('getChargeStatus(), getCharges(), cancelTransaction() and refundTransaction()', function () use ($cbName, $cbSecret) {
    $c = qa_crypto($cbName, $cbSecret);
    qa_equals('COMPLETED', $c->getChargeStatus('68f7a946db0529ea9b6d3a12'));
    qa_equals(1, count($c->getCharges(['status' => 'ACTIVE'])['data']['checkouts']));
    qa_equals('DEACTIVATED', $c->cancelTransaction('68f7a946db0529ea9b6d3a12')['data']['status']);
    $refund = $c->refundTransaction('68f7a946db0529ea9b6d3a12', 5, 'USDC', 'damaged');
    qa_equals(['5.00', 'USDC', 'damaged'], [$refund['data']['amount'], $refund['data']['currency'], $refund['data']['reason']]);
    $cbRequests = qa_coinbase_requests(); $last = end($cbRequests);
    qa_equals('POST 127.0.0.1:8767/api/v1/checkouts/68f7a946db0529ea9b6d3a12/refund', json_decode(qa_b64url_decode(explode('.', substr($last['auth'], 7))[1]), true)['uri']);
});

qa_test('missing or invalid credentials return an error instead of calling Coinbase', function () {
    $r = qa_crypto('', '')->createTransaction(10, 'USD');
    qa_equals(false, $r['action_success']);
    qa_contains('COINBASE_API_KEY_NAME', $r['error']);
    $r = qa_crypto('organizations/x/apiKeys/y', base64_encode('short'))->createTransaction(10, 'USD');
    qa_contains('Ed25519', $r['error']);
});

proc_terminate($coinbaseMock);

qa_section('Groq Agent (mock server)');

$groqMock = proc_open('php -S 127.0.0.1:8768 qa/fixtures/groq_mock.php', [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $gPipes);
usleep(600000);
$_ENV['GROQ_API_BASE'] = 'http://127.0.0.1:8768/openai/v1';

function qa_groq_requests()
{
    $log = sys_get_temp_dir() . '/qa_groq_mock.log';
    $lines = file_exists($log)? array_filter(explode("\n", file_get_contents($log))) : [];
    return array_map(function ($l) { return json_decode($l, true); }, array_values($lines));
}

qa_test('delegate() uses the current default model when none is given', function () {
    @unlink(sys_get_temp_dir() . '/qa_groq_mock.log');
    $agent = new Caligrafy\Agent('gsk_qa');
    $r = $agent->delegate(messages: [['role' => 'user', 'content' => 'Hi']]);
    qa_equals(['api_success' => true, 'response' => 'echo: Hi'], $r);
    qa_equals('openai/gpt-oss-120b', json_decode(qa_groq_requests()[0]['body'], true)['model']);
    (new Caligrafy\Agent('gsk_qa'))->delegate(GROQ_SMALL_MODEL, [['role' => 'user', 'content' => 'x']]);
    qa_equals('openai/gpt-oss-20b', json_decode(qa_groq_requests()[1]['body'], true)['model'], 'explicit model');
});

qa_test('visualize() sends the image to the default vision model', function () {
    @unlink(sys_get_temp_dir() . '/qa_groq_mock.log');
    $img = tempnam(sys_get_temp_dir(), 'qa') . '.png';
    file_put_contents($img, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    $r = (new Caligrafy\Agent('gsk_qa'))->visualize($img, 'What is this?');
    qa_equals(true, $r['api_success'], json_encode($r));
    qa_equals('image of 2 parts', $r['response']);
    qa_equals('qwen/qwen3.8-27b', json_decode(qa_groq_requests()[0]['body'], true)['model']);
});

qa_test('transcribe() uses whisper-large-v3 and returns JSON text', function () {
    @unlink(sys_get_temp_dir() . '/qa_groq_mock.log');
    $audio = tempnam(sys_get_temp_dir(), 'qa') . '.mp3';
    file_put_contents($audio, 'fake audio');
    $r = json_decode((new Caligrafy\Agent('gsk_qa'))->transcribe($audio), true);
    qa_equals('hello from audio', $r['text'] ?? null, json_encode($r));
    qa_equals('whisper-large-v3', json_decode(qa_groq_requests()[0]['body'], true)['model']);
});

proc_terminate($groqMock);
unset($_ENV['GROQ_API_BASE']);

qa_section('Watson (watsonx Assistant v2 mock)');

$watsonMock = proc_open('php -S 127.0.0.1:8769 qa/fixtures/watson_mock.php', [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $wPipes);
usleep(600000);

function qa_watson_requests()
{
    $log = sys_get_temp_dir() . '/qa_watson_mock.log';
    $lines = file_exists($log)? array_filter(explode("\n", file_get_contents($log))) : [];
    return array_map(function ($l) { return json_decode($l, true); }, array_values($lines));
}

qa_test('sessions are created in the configured environment with the current API version', function () {
    @unlink(sys_get_temp_dir() . '/qa_watson_mock.log');
    $out = json_decode(shell_exec('php qa/tests/watson_probe.php env-qa 2>&1'), true);
    qa_equals('sess-qa', $out['session'] ?? null, json_encode($out));
    qa_equals('You said hello', $out['response'][0]['text']);
    $calls = array_map(function ($r) { return $r['method'] . ' ' . $r['uri']; }, qa_watson_requests());
    qa_equals([
        'POST /instances/qa-instance/v2/assistants/assistant-qa/environments/env-qa/sessions?version=2024-08-25',
        'POST /instances/qa-instance/v2/assistants/assistant-qa/environments/env-qa/sessions/sess-qa/message?version=2024-08-25',
        'DELETE /instances/qa-instance/v2/assistants/assistant-qa/environments/env-qa/sessions/sess-qa?version=2024-08-25'], $calls);
    qa_equals('Basic ' . base64_encode('apikey:watson-key'), qa_watson_requests()[0]['auth']);
    qa_equals(['bot_name' => 'Cali'], json_decode(qa_watson_requests()[1]['body'], true)['context']['skills']['main skill']['user_defined']);
});

qa_test('without an environment id the legacy assistant path is used', function () {
    @unlink(sys_get_temp_dir() . '/qa_watson_mock.log');
    shell_exec("php qa/tests/watson_probe.php '' 2>&1");
    qa_equals('POST /instances/qa-instance/v2/assistants/assistant-qa/sessions?version=2024-08-25', qa_watson_requests()[0]['method'] . ' ' . qa_watson_requests()[0]['uri']);
});

qa_test('a missing WATSON_URL gives a clear error', function () {
    $out = shell_exec('php -r ' . escapeshellarg('define("WATSON_URL", ""); require "qa/tests/bootstrap.php"; try { new Caligrafy\Watson("a"); } catch (Exception $e) { echo $e->getMessage(); }') . ' 2>&1');
    qa_contains('Set WATSON_URL', $out);
});

qa_test('Route::resolveController: declared classes win, short example names fall back to Caligrafy\Controllers', function () {
    qa_equals('AIController', Caligrafy\Route::resolveController('AIController'));
    qa_equals('Caligrafy\Controllers\WatsonController', Caligrafy\Route::resolveController('WatsonController'));
    qa_equals('Caligrafy\Controllers\AuthController', Caligrafy\Route::resolveController('\Caligrafy\Controllers\AuthController'));
    qa_equals('Shop\OrderController', Caligrafy\Route::resolveController('\Shop\OrderController'));
    qa_equals(null, Caligrafy\Route::resolveController('Shop\AIController'));
    qa_equals(null, Caligrafy\Route::resolveController('NopeController'));
});

proc_terminate($watsonMock);

qa_summary();
