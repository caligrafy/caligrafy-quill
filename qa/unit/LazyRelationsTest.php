<?php

use Caligrafy\Database;
use Caligrafy\Errors;
use Caligrafy\Lazy;
use Caligrafy\LazyLoadingException;
use Caligrafy\Model;
use Caligrafy\Query;
use PHPUnit\Framework\TestCase;

/**
 * N+1 prevention by default (2.26.0): a relation method run on one of several models a query read throws [ERROR 2004]
 * in development, logs one warning per request and relation in production (or with STRICT_RELATIONS=false); lazy() and
 * #[Caligrafy\Lazy] allow it. On an in-memory SQLite through Database.
 */
final class LazyRelationsTest extends TestCase
{
    private $log = array();

    protected function setUp(): void
    {
        Query::watch(false);
        Model::$strictRelations = true;
        Query::$logger = function ($message) { $this->log[] = $message; };
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE lz_authors (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec('CREATE TABLE lz_books (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, author_id INTEGER)');
        $pdo->exec('CREATE TABLE lz_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, text TEXT, book_id INTEGER)');
        $pdo->exec("INSERT INTO lz_authors (name) VALUES ('Ada'), ('Grace')");
        $pdo->exec("INSERT INTO lz_books (title, author_id) VALUES ('Alpha', 1), ('Beta', 2), ('Gamma', 2)");
        $pdo->exec("INSERT INTO lz_notes (text, book_id) VALUES ('n1', 1), ('n2', 1)");
        Database::useConnection($pdo);
    }

    protected function tearDown(): void
    {
        Database::useConnection(null);
        Query::$logger = null;
        Query::watch(null);
        Model::$strictRelations = null;
    }

    private function lazyError(callable $call): LazyLoadingException
    {
        try {
            $call();
        } catch (LazyLoadingException $e) {
            return $e;
        }
        $this->fail('a LazyLoadingException was expected');
    }

    public function testARelationOnOneOfSeveralRowsThrowsInDevelopment(): void
    {
        $books = LzBook::query()->orderBy('id')->get();
        $e = $this->lazyError(fn () => $books[0]->author());
        $this->assertSame(2004, $e->getCode());
        $this->assertStringStartsWith('[ERROR 2004] Lazy loading prevented: LzBook::author() was called on one of several LzBook rows read by a query', $e->getMessage());
        $this->assertStringContainsString("LzBook::with('author')->get(), then read \$lzBook->author", $e->getMessage());
        $this->assertStringContainsString('$lzBook->lazy()->author()', $e->getMessage());
        $this->assertStringContainsString('#[Caligrafy\\Lazy]', $e->getMessage());
        $this->assertStringContainsString('STRICT_RELATIONS=false', $e->getMessage());
        $this->assertStringContainsString('From ' . __FILE__ . ':', $e->getMessage(), 'it names the line that called the relation');
        $this->assertStringContainsString('LzBook::notes()', $this->lazyError(fn () => $books[1]->notes())->getMessage(), 'hasMany too');
        $this->assertStringContainsString('LzBook::authors()', $this->lazyError(fn () => $books[1]->authors())->getMessage(), 'belongsTo (hasMany) too');
        $this->assertSame('[ERROR 2004] Lazy loading prevented', Errors::get(2004));
        $this->assertSame(500, Errors::status(2004));
    }

    public function testPaginateAndChunkRowsAreGuardedToo(): void
    {
        $page = LzBook::query()->paginate(2);
        $this->assertSame(2004, $this->lazyError(fn () => $page['data'][0]->author())->getCode());
        $chunks = array();
        LzBook::query()->chunk(2, function ($rows) use (&$chunks) {
            $chunks[] = count($rows) > 1 ? $this->lazyError(fn () => $rows[0]->author())->getCode() : $rows[0]->author()->name;
        });
        $this->assertSame(array(2004, 'Grace'), $chunks, 'a chunk of one row is not guarded');
    }

    public function testAModelReadAloneLoadsItsRelations(): void
    {
        $this->assertSame('Grace', LzBook::find(2)->author()->name, 'find(): one row is not N+1');
        $this->assertSame('Ada', LzBook::query()->orderBy('id')->first()->author()->name, 'first()');
        $this->assertSame('Grace', LzBook::where('id', 3)->get()[0]->author()->name, 'a result of one row');
        $this->assertCount(2, (new LzBook('lz_books'))->find(1)->notes(), 'the instance find()');
        $all = (new LzBook('lz_books'))->all();
        $this->assertSame('Ada', $all[0]->author()->name, 'the instance all() is not guarded (the repeated-query warning still watches it)');
        $this->assertSame(array(), $this->log);
    }

    public function testLazyAllowsOneCall(): void
    {
        $books = LzBook::query()->orderBy('id')->get();
        $this->assertSame('Ada', $books[0]->lazy()->author()->name);
        $this->lazyError(fn () => $books[0]->author());
        $this->assertCount(2, $books[0]->lazy()->notes());
    }

    public function testTheLazyAttributeAllowsARelation(): void
    {
        $books = LzBook::query()->orderBy('id')->get();
        $this->assertSame('Ada', $books[0]->writer()->name);
        $this->assertSame('Grace', $books[1]->writer()->name);
        $this->assertSame(array(), $this->log);
    }

    public function testAnEagerLoadedRelationSaysToReadIt(): void
    {
        $books = LzBook::with(['author' => ['table' => 'lz_authors']])->orderBy('id')->get();
        $this->assertSame('Ada', $books[0]->author['name']);
        $e = $this->lazyError(fn () => $books[0]->author());
        $this->assertStringContainsString("already have 'author' from with(): read \$lzBook->author instead", $e->getMessage());
    }

    public function testBelongsToManySuggestsThePivot(): void
    {
        $books = LzBook::query()->get();
        $e = $this->lazyError(fn () => $books[0]->tags());
        $this->assertStringContainsString("LzBook::with(['tags' => ['pivot' => true]])->get()", $e->getMessage());
    }

    public function testProductionLogsOncePerRequestAndRelation(): void
    {
        Model::$strictRelations = false;
        $books = LzBook::query()->orderBy('id')->get();
        $names = array_map(fn ($book) => $book->author()->name, $books);
        $this->assertSame(array('Ada', 'Grace', 'Grace'), $names, 'nothing breaks');
        $this->assertCount(1, $this->log, 'one warning for author()');
        $this->assertStringStartsWith('[Caligrafy] Lazy loading: LzBook::author() ran on one of several LzBook rows read by a query, one more query per row (N+1), from ' . __FILE__ . ':', $this->log[0]);
        $this->assertStringContainsString("Load it with the rows: LzBook::with('author')->get(), then read \$lzBook->author.", $this->log[0]);
        $books[0]->notes();
        $books[1]->notes();
        $this->assertCount(2, $this->log, 'one more for notes()');
        Query::forgetShapes();   // a new request
        $books[2]->author();
        $this->assertCount(3, $this->log);
    }

    public function testStrictnessComesFromAppEnvAndStrictRelations(): void
    {
        $env = getenv('APP_ENV');
        $strict = getenv('STRICT_RELATIONS');
        try {
            foreach (array(array('local', false, true), array('local', 'false', false), array('local', 'true', true),
                           array('production', false, false), array('production', 'true', false), array('', false, true)) as [$appEnv, $setting, $expected]) {
                putenv('APP_ENV=' . $appEnv);
                $setting === false ? putenv('STRICT_RELATIONS') : putenv('STRICT_RELATIONS=' . $setting);
                Model::$strictRelations = null;
                $this->assertSame($expected, Model::strictRelations(), "APP_ENV=$appEnv STRICT_RELATIONS=" . var_export($setting, true));
            }
        } finally {
            $env === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $env);
            $strict === false ? putenv('STRICT_RELATIONS') : putenv('STRICT_RELATIONS=' . $strict);
        }
    }

    public function testTheGuardAddsNothingToTheModel(): void
    {
        $books = LzBook::query()->orderBy('id')->get();
        $this->assertSame(array('id' => 1, 'title' => 'Alpha', 'author_id' => 1), array_intersect_key(get_object_vars($books[0]), array('id' => 1, 'title' => 1, 'author_id' => 1)));
        $this->assertSame('{"table":"lz_books","id":1,"title":"Alpha","author_id":1}', json_encode($books[0]));
    }
}

class LzAuthor extends Model
{
    public $table = 'lz_authors';
}

class LzBook extends Model
{
    public $table = 'lz_books';
    public $id;
    public $title;
    public $author_id;

    public function author()
    {
        return $this->hasOne('LzAuthor', 'lz_authors', 'author_id');
    }

    public function notes()
    {
        return $this->hasMany('LzAuthor', 'lz_notes', 'book_id');
    }

    public function authors()
    {
        return $this->belongsTo('LzAuthor', 'lz_notes', 'book_id');
    }

    public function tags()
    {
        return $this->belongsToMany('LzAuthor', 'lz_tags');
    }

    #[Lazy]
    public function writer()
    {
        return $this->hasOne('LzAuthor', 'lz_authors', 'author_id');
    }
}
