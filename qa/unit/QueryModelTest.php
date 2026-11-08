<?php

use Caligrafy\Database;
use Caligrafy\Model;
use Caligrafy\Query;
use PHPUnit\Framework\TestCase;

/**
 * Models and the query builder: Book::query(), Book::find(), Book::where() (static), and the instance methods
 * find(), all(), search(), create() unchanged (they run on an in-memory SQLite through Database)
 */
final class QueryModelTest extends TestCase
{
    protected function setUp(): void
    {
        Query::watch(false);
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, author_id INTEGER)');
        $pdo->exec("INSERT INTO books (title, author_id) VALUES ('Alpha', 1), ('Beta', 2), ('Gamma', 2)");
        Database::useConnection($pdo);
    }

    protected function tearDown(): void
    {
        Query::useExecutor(null);
        Database::useConnection(null);
        Query::watch(null);
    }

    public function testTheTableOfAModel(): void
    {
        $this->assertSame('SELECT * FROM `qabooks`', QaBook::query()->toSql());
        $this->assertSame('SELECT * FROM `qacategories`', QaCategory::query()->toSql());
        $this->assertSame('SELECT * FROM `people`', QaPerson::query()->toSql(), 'a static $tableName');
        $this->assertSame('SELECT * FROM `book_shelves`', QaShelf::query()->toSql(), 'the default of $table');
        $this->assertSame('SELECT * FROM `books`', (new QaBook('books'))->query()->toSql(), 'an instance uses its own table');
    }

    public function testStaticFormsReturnModels(): void
    {
        $db = new QaFakeExecutor(array('qabooks' => array(array('id' => 3, 'title' => 'C', 'author_id' => 2))));
        Query::useExecutor($db);
        $book = QaBook::find(3);
        $this->assertInstanceOf(QaBook::class, $book);
        $this->assertSame('C', $book->title);
        $this->assertSame(array('SELECT * FROM `qabooks` WHERE `id` = ? LIMIT 1', array(3)), $db->queries[0]);

        $books = QaBook::where('author_id', 2)->orderBy('title')->get();
        $this->assertSame('SELECT * FROM `qabooks` WHERE `author_id` = ? ORDER BY `title` ASC', $db->queries[1][0]);
        $this->assertInstanceOf(QaBook::class, $books[0]);
        $this->assertSame('SELECT * FROM `qabooks` LIMIT 1', (function () use ($db) { QaBook::first(); return end($db->queries)[0]; })());
        $this->assertSame(array('C'), QaBook::pluck('title'));
        $this->assertNull(QaBook::find(99));
    }

    public function testInstanceMethodsWorkAsBefore(): void
    {
        $model = new QaBook('books');
        $book = $model->find(2);
        $this->assertInstanceOf(QaBook::class, $book);
        $this->assertSame('Beta', $book->title);
        $this->assertSame('books', $book->table);
        $this->assertNull($model->find(42));

        $this->assertSame(array('Beta', 'Gamma'), array_map(function ($b) { return $b->title; }, $model->all(array('condition' => 'WHERE author_id = ?', 'order' => 'ORDER BY title', 'bindings' => array(2)))));
        $this->assertCount(3, $model->all());
        $this->assertSame(array('Gamma'), array_map(function ($b) { return $b->title; }, $model->search('title', '%amm%')));

        $created = (new Model('books'))->create(array('title' => 'Delta', 'author_id' => 3));
        $this->assertSame('Delta', $created->title, 'create() reads the row back through find()');
        $this->assertSame('Delta', (new Model())->associate('books')->find($created->id)->title);

        $audited = new QaAudited('books');
        $this->assertSame('Alpha', $audited->find(1)->title, 'an override of find() calls parent::find()');
        $this->assertSame(array(1), $audited->found);
    }

    public function testInstanceQueryMethodsUseTheInstanceTable(): void
    {
        $model = new QaBook('books');
        $books = $model->where('author_id', 2)->latest('id')->get();
        $this->assertSame(array('Gamma', 'Beta'), array_map(function ($b) { return $b->title; }, $books));
        $this->assertInstanceOf(QaBook::class, $books[0]);
        $this->assertSame(3, $model->count());
    }

    public function testUnknownMethodsStillFail(): void
    {
        foreach (array(function () { (new QaBook('books'))->nothing(); }, function () { QaBook::nothing(); }) as $call) {
            try {
                $call();
                $this->fail('an unknown method ran');
            } catch (Error $e) {
                $this->assertStringContainsString('Call to undefined method QaBook::nothing()', $e->getMessage());
            }
        }
    }

    public function testNoReflectionPerQuery(): void
    {
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            QaPerson::query()->where('id', $i)->toSql();
        }
        $this->assertLessThan(200, (hrtime(true) - $start) / 1e6, '1000 model queries are built in well under 200 ms');
    }
}
