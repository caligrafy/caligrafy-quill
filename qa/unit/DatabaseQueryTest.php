<?php

use Caligrafy\Database;
use Caligrafy\Query;
use PHPUnit\Framework\TestCase;

/**
 * Database::transaction(), the builder's SQL run for real on an in-memory SQLite (it accepts the backticks), and the
 * N+1 warning of development
 */
final class DatabaseQueryTest extends TestCase
{
    private $pdo;
    private $log;

    protected function setUp(): void
    {
        Query::watch(false);
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE authors (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, author_id INTEGER, views INTEGER DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY, label TEXT)');
        $this->pdo->exec('CREATE TABLE book_tag (book_id INTEGER, tag_id INTEGER)');
        $this->pdo->exec("INSERT INTO authors VALUES (1, 'Ada'), (2, 'Bo')");
        $this->pdo->exec("INSERT INTO tags VALUES (1, 'php'), (2, 'sql')");
        Database::useConnection($this->pdo);
        $this->log = '';
        Query::$logger = function ($message) { $this->log .= $message . "\n"; };
    }

    protected function tearDown(): void
    {
        Query::$logger = null;
        Database::useConnection(null);
        Query::useExecutor(null);
        Query::watch(null);
    }

    private function rows($table)
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }

    public function testTransactionCommits(): void
    {
        $id = Database::transaction(function () {
            return Database::table('books')->insert(array('title' => 'A', 'author_id' => 1));
        });
        $this->assertSame('1', (string) $id);
        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame(1, $this->rows('books'));
    }

    public function testTransactionRollsBackAndRethrows(): void
    {
        try {
            Database::transaction(function () {
                Database::table('books')->insert(array('title' => 'A'));
                throw new RuntimeException('stop');
            });
            $this->fail('the exception was swallowed');
        } catch (RuntimeException $e) {
            $this->assertSame('stop', $e->getMessage());
        }
        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame(0, $this->rows('books'));
    }

    public function testNestedTransactionsRunInTheOuterOne(): void
    {
        try {
            Database::transaction(function () {
                Database::transaction(function () {
                    Database::table('books')->insert(array('title' => 'inner'));
                });
                $this->assertTrue($this->pdo->inTransaction(), 'the inner call did not commit');
                Database::table('books')->insert(array('title' => 'outer'));
                throw new LogicException('outer fails');
            });
        } catch (LogicException $e) {
        }
        $this->assertSame(0, $this->rows('books'), 'both inserts rolled back');

        $this->pdo->beginTransaction();     // a test's own transaction
        Database::transaction(function () { Database::table('books')->insert(array('title' => 'x')); });
        $this->assertTrue($this->pdo->inTransaction(), 'an outside transaction is left to its owner');
        $this->pdo->rollBack();
    }

    public function testTransactionNeedsAConnection(): void
    {
        Database::useConnection(null);       // DB_ACTIVATE is false in the unit tests
        $this->expectExceptionCode(2003);
        Database::transaction(function () {});
    }

    public function testTheSqlRunsOnARealDatabase(): void
    {
        $books = Database::table('books');
        $books->insert(array(array('title' => 'Alpha', 'author_id' => 1), array('title' => 'Beta', 'author_id' => 2), array('title' => 'Gamma', 'author_id' => 2), array('title' => '100%', 'author_id' => null)));
        $this->pdo->exec('INSERT INTO book_tag VALUES (1, 1), (1, 2), (3, 2)');

        $this->assertSame(4, Database::table('books')->count());
        $this->assertTrue(Database::table('books')->where('title', 'Beta')->exists());
        $this->assertFalse(Database::table('books')->where('title', 'Zeta')->exists());
        $this->assertSame(array('Beta', 'Gamma'), Database::table('books')->where('author_id', 2)->orderBy('title')->pluck('title'));
        $this->assertSame('Gamma', Database::table('books')->whereIn('id', array(2, 3))->latest('id')->value('title'));
        $this->assertSame(array('Gamma'), Database::table('books')->whereBetween('id', array(3, 3))->pluck('title'));
        $this->assertSame(array('100%'), Database::table('books')->whereNull('author_id')->pluck('title'));
        $this->assertSame(array('Beta'), Database::table('books')->where('id', '>', 1)->orderBy('id')->limit(1)->offset(0)->pluck('title'));
        $this->assertSame(array('Gamma', '100%'), Database::table('books')->orderBy('id')->offset(2)->pluck('title'));

        $page = Database::table('books')->orderBy('id')->paginate(3, 2);
        $this->assertSame(array('100%'), array_column($page['data'], 'title'));
        $this->assertSame(array(4, 2, 3, 2), array($page['total'], $page['page'], $page['per_page'], $page['pages']));

        $this->assertSame(2, Database::table('books')->where('author_id', 2)->update(array('title' => 'Two')));
        $this->assertSame(1, Database::table('books')->where('id', 1)->increment('views', 5));
        $this->assertSame(5, (int) Database::table('books')->find(1)['views']);

        $all = Database::table('books')->orderBy('id')->with('author', array('tags' => array('pivot' => true)))->withCount(array('tags' => array('pivot' => true)))->get();
        $this->assertSame('Ada', $all[0]['author']['name']);
        $this->assertNull($all[3]['author']);
        $this->assertSame(array('php', 'sql'), array_column($all[0]['tags'], 'label'));
        $this->assertSame(array(2, 0, 1, 0), array_map('intval', array_column($all, 'tags_count')));
        $authors = Database::table('authors')->orderBy('id')->with('books')->withCount('books')->get();
        $this->assertSame(array(1, 2), array_column($authors, 'books_count'));
        $this->assertSame(array('Two', 'Two'), array_column($authors[1]['books'], 'title'));

        $this->assertSame(1, Database::table('books')->where('title', '100%')->delete());
        $this->assertSame(3, $this->rows('books'));
    }

    public function testNPlusOneWarningInDevelopment(): void
    {
        Query::watch(true);
        Query::useExecutor(new QaFakeExecutor());
        for ($i = 1; $i <= 9; $i++) {
            Database::table('authors')->find($i);
        }
        $this->assertSame('', $this->log, 'nine identical shapes: no warning');
        $line = __LINE__ + 2;
        for ($i = 10; $i <= 30; $i++) {
            Database::table('authors')->find($i);
        }
        $log = $this->log;
        $this->assertSame(1, substr_count($log, 'Probable N+1 query'), 'one warning per shape');
        $this->assertStringContainsString('select * from `authors` where `id` = ? limit ?', $log);
        $this->assertStringContainsString(__FILE__ . ':' . $line, $log);
        $this->assertStringContainsString("with('author')", $log);

        // raw SQL through Database::execute(): literals are folded too, and writes are not counted
        Query::useExecutor(null);
        for ($i = 1; $i <= 12; $i++) {
            Database::execute("SELECT name FROM authors WHERE id = $i AND name != 'x$i'");
            Database::execute('UPDATE authors SET name = name WHERE id = ?', array($i));
        }
        $log = $this->log;
        $this->assertSame(2, substr_count($log, 'Probable N+1 query'));
        $this->assertStringContainsString("select name from authors where id = ? and name != ?", $log);
        $this->assertStringNotContainsString('update', $log);
    }

    public function testNoWatchInProduction(): void
    {
        $env = getenv('APP_ENV');
        putenv('APP_ENV=production');
        Query::watch(null);
        try {
            $this->assertFalse(Query::watching());
            Query::useExecutor(new QaFakeExecutor());
            for ($i = 0; $i < 30; $i++) {
                Database::table('authors')->find($i);
            }
            $this->assertSame('', $this->log);
        } finally {
            $env === false ? putenv('APP_ENV') : putenv("APP_ENV=$env");
            Query::watch(null);
        }
        putenv('APP_ENV=local');
        $this->assertTrue(Query::watching());
        $env === false ? putenv('APP_ENV') : putenv("APP_ENV=$env");
    }

    public function testShapes(): void
    {
        $this->assertSame('select * from t where a in (?) and b = ? and c = ? limit ?', Query::shape("SELECT *  FROM t\nWHERE a IN (?, ?,?) AND b = 'it\\'s' AND c = -4.5 LIMIT 10"));
        $this->assertSame('select * from t2 where `col1` = ?', Query::shape('SELECT * FROM t2 WHERE `col1` = 7'));
    }
}
