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

qa_test('the pivot is shared from the other side of the relationship', function () {
    $t = (new Qa_tag('qa_tags'))->find(2);
    qa_equals(1, count($t->books()));
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

// ---------------------------------------------------------------------------
qa_section('OpenAI');

qa_test('model constants are valid OpenAI ids', function () {
    qa_equals('gpt-4o-mini', GPT4OMINI);
    class_exists('Caligrafy\\Assistant');
    qa_equals('gpt-4o-mini', ASSISTANT_GPT4OMINI);
    qa_assert(defined('GPT4_32'), 'GPT4_32 alias is defined');
});

qa_test('OpenAI::command() only resolves the documented commands', function () {
    qa_equals(SUMMARIZE, OpenAI::command('summarize'));
    qa_equals(ANALYZE, OpenAI::command('ANALYZE'));
    qa_equals(null, OpenAI::command('APP_KEY'));
    qa_equals(null, OpenAI::command('DB_PASSWORD'));
    qa_equals(TEXT, OpenAI::type('text'));
    qa_equals(IMAGE, OpenAI::type('image'));
    qa_equals(null, OpenAI::type('OPEN_AI_KEY'));
});

qa_test('COMPLETE keeps the text after [insert]', function () {
    $ai = new OpenAI();
    $prop = new ReflectionProperty($ai, '_openai_url');
    $prop->setAccessible(true);
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
    $prop->setAccessible(true);
    $prop->setValue($ai, 'http://127.0.0.1:8765/v1/chat/completions');
    $out = $ai->delegate('Some text', 'one line', SUMMARIZE, [], ['model' => 'gpt-4o-mini']);
    $body = json_decode($out['response']['echo']['body'], true);
    qa_equals('gpt-4o-mini', $body['model']);
    qa_equals('system', $body['messages'][0]['role']);
    qa_equals(SUMMARIZE, $body['messages'][0]['content']);
    qa_contains('one line', $body['messages'][1]['content']);
});

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
    $prop->setAccessible(true);
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
    qa_equals(['card'], $params['payment_method_types']);
});

qa_summary();
