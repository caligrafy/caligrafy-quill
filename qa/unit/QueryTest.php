<?php

use Caligrafy\Database;
use Caligrafy\Query;
use PHPUnit\Framework\TestCase;

/**
 * The query builder: the SQL and bindings of every method, without a database (a fake executor records the queries)
 */
final class QueryTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        Query::watch(false);
        $this->db = new QaFakeExecutor();
        Query::useExecutor($this->db);
    }

    protected function tearDown(): void
    {
        Query::useExecutor(null);
        Query::watch(null);
        $_GET = array();
    }

    private function last()
    {
        return end($this->db->queries);
    }

    public function testDatabaseTableReturnsAQuery(): void
    {
        $query = Database::table('books');
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame('SELECT * FROM `books`', $query->toSql());
        $this->assertSame(array(), $query->getBindings());
    }

    public function testSelectAndWhereForms(): void
    {
        $query = Database::table('books')
            ->select('id', 'books.title', 'price as cost', 'authors.*')
            ->where('price', '<', 20)
            ->where('author_id', 3)
            ->where(array('status' => 'new', 'featured' => true))
            ->orWhere('title', 'like', 'PHP%');
        $this->assertSame(
            'SELECT `id`, `books`.`title`, `price` AS `cost`, `authors`.* FROM `books` WHERE `price` < ? AND `author_id` = ? AND (`status` = ? AND `featured` = ?) OR `title` LIKE ?',
            $query->toSql()
        );
        $this->assertSame(array(20, 3, 'new', 1, 'PHP%'), $query->getBindings());
        $this->assertSame('SELECT `id`, `title` FROM `books`', Database::table('books')->select(array('id', 'title'))->toSql());
    }

    public function testNullComparisonsAndGroups(): void
    {
        $query = Database::table('books')
            ->where('deleted_at', null)
            ->where('isbn', '!=', null)
            ->where(function (Query $q) { $q->where('price', '>', 5)->orWhere('free', 1); })
            ->orWhere(function (Query $q) { $q->where('price', '<=', 100)->where('price', '>=', 50); });
        $this->assertSame(
            'SELECT * FROM `books` WHERE `deleted_at` IS NULL AND `isbn` IS NOT NULL AND (`price` > ? OR `free` = ?) OR (`price` <= ? AND `price` >= ?)',
            $query->toSql()
        );
        $this->assertSame(array(5, 1, 100, 50), $query->getBindings());
    }

    public function testInNullBetween(): void
    {
        $query = Database::table('books')
            ->whereIn('id', array(1, 2, 3))
            ->whereNotIn('author_id', array(9))
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->whereBetween('price', array(10, 20));
        $this->assertSame(
            'SELECT * FROM `books` WHERE `id` IN (?, ?, ?) AND `author_id` NOT IN (?) AND `deleted_at` IS NULL AND `published_at` IS NOT NULL AND `price` BETWEEN ? AND ?',
            $query->toSql()
        );
        $this->assertSame(array(1, 2, 3, 9, 10, 20), $query->getBindings());
        $this->assertSame('SELECT * FROM `books` WHERE 0 = 1', Database::table('books')->whereIn('id', array())->toSql());
        $this->assertSame('SELECT * FROM `books`', Database::table('books')->whereNotIn('id', array())->toSql());
    }

    public function testOrderLimitOffset(): void
    {
        $this->assertSame(
            'SELECT * FROM `books` ORDER BY `title` ASC, `created_at` DESC, `updated_at` ASC LIMIT 10 OFFSET 20',
            Database::table('books')->orderBy('title')->latest()->oldest('updated_at')->limit(10)->offset('20')->toSql()
        );
        $this->assertSame('SELECT * FROM `books` ORDER BY `price` DESC', Database::table('books')->orderBy('price', 'DESC')->toSql());
        $this->assertSame('SELECT * FROM `books` LIMIT 9223372036854775807 OFFSET 5', Database::table('books')->offset(5)->toSql());
    }

    public function testSearchEscapesWildcards(): void
    {
        $query = Database::table('books')->where('active', 1)->search(array('title', 'summary'), '  50%_off\\ ');
        $this->assertSame('SELECT * FROM `books` WHERE `active` = ? AND (`title` LIKE ? OR `summary` LIKE ?)', $query->toSql());
        $this->assertSame(array(1, '%50\\%\\_off\\\\%', '%50\\%\\_off\\\\%'), $query->getBindings());
        $this->assertSame('SELECT * FROM `books`', Database::table('books')->search(array('title'), '   ')->toSql());
    }

    public function testGetFirstFindValuePluck(): void
    {
        $db = new QaFakeExecutor(array(), function ($sql) {
            if (strpos($sql, 'SELECT `title`, `id`') === 0) {
                return array(array('title' => 'A', 'id' => 1), array('title' => 'B', 'id' => 2));
            }
            return array(array('id' => 1, 'title' => 'A'), array('id' => 2, 'title' => 'B'));
        });
        Query::useExecutor($db);
        $books = Database::table('books')->where('price', '<', 9);
        $this->assertCount(2, $books->get());
        $this->assertSame(array('SELECT * FROM `books` WHERE `price` < ?', array(9)), $db->queries[0]);

        $this->assertSame(array('id' => 1, 'title' => 'A'), $books->first());
        $this->assertSame('SELECT * FROM `books` WHERE `price` < ? LIMIT 1', $db->queries[1][0]);

        $books->find(5);
        $this->assertSame(array('SELECT * FROM `books` WHERE `price` < ? AND `id` = ? LIMIT 1', array(9, 5)), $db->queries[2]);
        $this->assertSame('SELECT * FROM `books` WHERE `price` < ?', $books->toSql(), 'first() and find() leave the query as it was');

        $this->assertSame(1, $books->value('id'));
        $this->assertSame(array('SELECT `id` FROM `books` WHERE `price` < ? LIMIT 1', array(9)), $db->queries[3]);

        $this->assertSame(array(1 => 'A', 2 => 'B'), Database::table('books')->pluck('title', 'id'));
        $this->assertSame('SELECT `title`, `id` FROM `books`', $db->queries[4][0]);
    }

    public function testCountAndExists(): void
    {
        $db = new QaFakeExecutor(array(), function ($sql) {
            return strpos($sql, 'COUNT') !== false ? array(array('aggregate' => '42')) : array(array('found' => 1));
        });
        Query::useExecutor($db);
        $query = Database::table('books')->where('author_id', 2)->orderBy('title')->limit(5)->with('author');
        $this->assertSame(42, $query->count());
        $this->assertSame(array('SELECT COUNT(*) AS `aggregate` FROM `books` WHERE `author_id` = ?', array(2)), $db->queries[0]);
        $this->assertTrue($query->exists());
        $this->assertSame('SELECT 1 AS `found` FROM `books` WHERE `author_id` = ? LIMIT 1', $db->queries[1][0]);
        $this->assertCount(2, $db->queries, 'count() and exists() load no relation');
    }

    public function testPaginateMath(): void
    {
        $db = new QaFakeExecutor(array(), function ($sql) {
            return strpos($sql, 'COUNT') !== false ? array(array('aggregate' => 45)) : array(array('id' => 1));
        });
        Query::useExecutor($db);

        $page = Database::table('books')->where('active', 1)->paginate(20, 3);
        $this->assertSame(array('data' => array(array('id' => 1)), 'total' => 45, 'page' => 3, 'per_page' => 20, 'pages' => 3), $page);
        $this->assertSame(array('SELECT * FROM `books` WHERE `active` = ? LIMIT 20 OFFSET 40', array(1)), $db->queries[1]);

        $past = Database::table('books')->paginate(20, 4);
        $this->assertSame(array(), $past['data']);
        $this->assertCount(3, $db->queries, 'a page past the last one runs only the count');

        $_GET['page'] = '2';
        $this->assertSame(2, Database::table('books')->paginate(10)['page']);
        $this->assertStringEndsWith('LIMIT 10 OFFSET 10', end($db->queries)[0]);
        $this->assertSame(5, Database::table('books')->paginate(10)['pages']);

        foreach (array('0', '-3', 'abc', '2.5', array('x'), '1 OR 1=1') as $bad) {
            $this->assertSame(1, Database::table('books')->input(array('page' => $bad))->paginate(10)['page']);
        }
        $this->assertSame(4, Database::table('books')->input(array('page' => 4))->paginate(10)['page']);
        $this->assertStringEndsWith('LIMIT 10 OFFSET 30', end($db->queries)[0]);

        $_GET = array();
        $none = new QaFakeExecutor(array(), function () { return array(array('aggregate' => 0)); });
        Query::useExecutor($none);
        $empty = Database::table('books')->paginate();
        $this->assertSame(array('data' => array(), 'total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1), $empty);

        $this->expectException(InvalidArgumentException::class);
        Database::table('books')->paginate(0);
    }

    public function testChunk(): void
    {
        $db = new QaFakeExecutor(array(), function ($sql) {
            preg_match('/OFFSET (\d+)/', $sql, $m);
            $offset = (int) $m[1];
            return $offset < 5 ? array_slice(array(array('id' => 1), array('id' => 2), array('id' => 3), array('id' => 4), array('id' => 5)), $offset, 2) : array();
        });
        Query::useExecutor($db);
        $seen = array();
        $this->assertTrue(Database::table('books')->where('active', 1)->chunk(2, function ($rows, $n) use (&$seen) { $seen[$n] = count($rows); }));
        $this->assertSame(array(1 => 2, 2 => 2, 3 => 1), $seen);
        $this->assertSame('SELECT * FROM `books` WHERE `active` = ? ORDER BY `id` ASC LIMIT 2 OFFSET 0', $db->queries[0][0]);
        $this->assertCount(3, $db->queries);

        $this->assertFalse(Database::table('books')->latest()->chunk(2, function () { return false; }));
        $this->assertSame('SELECT * FROM `books` ORDER BY `created_at` DESC LIMIT 2 OFFSET 0', end($db->queries)[0]);
    }

    public function testInsert(): void
    {
        $id = Database::table('books')->insert(array('title' => 'A', 'tags' => array('x'), 'free' => false, 'price' => null));
        $this->assertSame(41, $id);
        $this->assertSame(array('INSERT INTO `books` (`title`, `tags`, `free`, `price`) VALUES (?, ?, ?, ?)', array('A', '["x"]', 0, null)), $this->last());

        Database::table('books')->insert(array(array('title' => 'A', 'price' => 1), array('title' => 'B', 'price' => 2)));
        $this->assertSame(array('INSERT INTO `books` (`title`, `price`) VALUES (?, ?), (?, ?)', array('A', 1, 'B', 2)), $this->last());

        $this->expectException(InvalidArgumentException::class);
        Database::table('books')->insert(array(array('title' => 'A'), array('name' => 'B')));
    }

    public function testUpdateDeleteIncrement(): void
    {
        $this->assertSame(3, Database::table('books')->where('id', 7)->update(array('title' => 'New', 'featured' => true)));
        $this->assertSame(array('UPDATE `books` SET `title` = ?, `featured` = ? WHERE `id` = ?', array('New', 1, 7)), $this->last());

        $this->assertSame(3, Database::table('books')->whereIn('id', array(1, 2))->delete());
        $this->assertSame(array('DELETE FROM `books` WHERE `id` IN (?, ?)', array(1, 2)), $this->last());

        Database::table('books')->where('id', 7)->increment('views');
        $this->assertSame(array('UPDATE `books` SET `views` = `views` + ? WHERE `id` = ?', array(1, 7)), $this->last());
        Database::table('books')->where('id', 7)->decrement('stock', 2, array('sold_out' => false));
        $this->assertSame(array('UPDATE `books` SET `stock` = `stock` - ?, `sold_out` = ? WHERE `id` = ?', array(2, 0, 7)), $this->last());
    }

    public function testWritesWithoutAWhereAreRefused(): void
    {
        foreach (array(
            function () { Database::table('books')->update(array('title' => 'x')); },
            function () { Database::table('books')->delete(); },
            function () { Database::table('books')->increment('views'); },
            function () { Database::table('books')->decrement('views'); },
            function () { Database::table('books')->search(array('title'), '')->delete(); },
        ) as $write) {
            try {
                $write();
                $this->fail('a write without a where ran');
            } catch (LogicException $e) {
                $this->assertStringContainsString('needs a where()', $e->getMessage());
            }
        }
        $this->assertSame(array(), $this->db->queries);
    }

    /**
     * Injection attempts through every place that takes a name: refused before any SQL is built
     */
    public function testIdentifiersAreValidated(): void
    {
        $bad = array('id`; DROP TABLE users', 'id; --', 'a.b.c', '1col', 'na me', '', 'id)', "id'", 'title/**/', 'books.*.x', '`id`', 'id OR 1=1');
        $attempts = array(
            function ($n) { new Query($n); },
            function ($n) { Database::table('books')->select($n); },
            function ($n) { Database::table('books')->where($n, 1); },
            function ($n) { Database::table('books')->orWhere($n, '=', 1); },
            function ($n) { Database::table('books')->where(array($n => 1)); },
            function ($n) { Database::table('books')->whereIn($n, array(1)); },
            function ($n) { Database::table('books')->whereNotIn($n, array(1)); },
            function ($n) { Database::table('books')->whereNull($n); },
            function ($n) { Database::table('books')->whereNotNull($n); },
            function ($n) { Database::table('books')->whereBetween($n, array(1, 2)); },
            function ($n) { Database::table('books')->orderBy($n); },
            function ($n) { Database::table('books')->search(array($n), 'x'); },
            function ($n) { Database::table('books')->insert(array($n => 1)); },
            function ($n) { Database::table('books')->where('id', 1)->update(array($n => 1)); },
            function ($n) { Database::table('books')->where('id', 1)->increment($n); },
            function ($n) { Database::table('books')->pluck($n); },
            function ($n) { Database::table('books')->value($n); },
            function ($n) { Database::table('books')->with(array('author' => array('foreign_key' => $n)))->get(); },
        );
        foreach ($attempts as $i => $attempt) {
            foreach ($bad as $name) {
                try {
                    $attempt($name);
                    $this->fail("attempt $i accepted '$name'");
                } catch (InvalidArgumentException $e) {
                    $this->assertSame(2001, $e->getCode(), "attempt $i with '$name': " . $e->getMessage());
                }
            }
        }
        foreach (array('author`', 'author; --', 'au thor', '', 'author.', '.author', '1author') as $relation) {
            try {
                Database::table('books')->with($relation)->get();
                $this->fail("relation '$relation' accepted");
            } catch (InvalidArgumentException $e) {
                $this->assertSame(2001, $e->getCode());
            }
        }
        $this->assertSame(array(), $this->db->queries, 'no query ran');
        foreach (array(1.5, 1, null, true) as $notAString) {
            try {
                Database::table('books')->where($notAString, 1);
                $this->fail('a non-string column was accepted');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(2001, $e->getCode());
            }
        }
    }

    public function testOperatorsAndDirectionsComeFromAnAllowList(): void
    {
        foreach (array('=', '!=', '<>', '<', '>', '<=', '>=', 'like', 'NOT LIKE', ' Like ') as $op) {
            $this->assertStringContainsString(' ?', Database::table('books')->where('a', $op, 1)->toSql());
        }
        foreach (array('= 1 OR 1 =', 'IN', 'REGEXP', ';', '<=>', 'OR', '', 5) as $op) {
            try {
                Database::table('books')->where('a', $op, 1);
                $this->fail("operator '$op' was accepted");
            } catch (InvalidArgumentException $e) {
                $this->assertSame(2001, $e->getCode());
            }
        }
        foreach (array('asc; DROP TABLE x', 'up', '', null) as $direction) {
            try {
                Database::table('books')->orderBy('a', $direction);
                $this->fail('direction accepted');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(2001, $e->getCode());
            }
        }
        foreach (array('10; DROP', -1, '1.5', 'ALL') as $limit) {
            try {
                Database::table('books')->limit($limit);
                $this->fail('limit accepted');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('whole number', $e->getMessage());
            }
        }
    }

    public function testValuesAreAlwaysBound(): void
    {
        $evil = "x' OR '1'='1";
        $query = Database::table('users')->where('name', $evil)->search(array('email'), $evil)->whereIn('id', array($evil));
        $this->assertStringNotContainsString("'1'", $query->toSql());
        $this->assertSame(array($evil, "%x' OR '1'='1%", $evil), $query->getBindings());
        $this->expectException(InvalidArgumentException::class);
        Database::table('users')->where('id', array(1, 2));
    }

    public function testModelsHydratesRows(): void
    {
        Query::useExecutor(new QaFakeExecutor(array('books' => array(array('id' => 1, 'title' => 'A')), 'qabooks' => array(array('id' => 1)))));
        $books = Database::table('books')->models(QaBook::class)->get();
        $this->assertInstanceOf(QaBook::class, $books[0]);
        $this->assertSame('A', $books[0]->title);
        $this->assertSame('books', $books[0]->table);
        $this->assertIsArray(QaBook::query()->arrays()->get()[0]);
        $this->expectException(InvalidArgumentException::class);
        Database::table('books')->models(stdClass::class);
    }

    public function testPluralAndSingular(): void
    {
        foreach (array('author' => 'authors', 'category' => 'categories', 'box' => 'boxes', 'class' => 'classes',
                       'church' => 'churches', 'wish' => 'wishes', 'day' => 'days', 'book' => 'books') as $one => $many) {
            $this->assertSame($many, Query::plural($one));
            $this->assertSame($one, Query::singular($many));
        }
        $this->assertSame('address', Query::singular('address'));
    }
}
