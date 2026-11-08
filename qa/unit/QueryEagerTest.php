<?php

use Caligrafy\Database;
use Caligrafy\Query;
use PHPUnit\Framework\TestCase;

/**
 * Eager loading: one query per relation (WHERE key IN (...)), the rows attached under the relation's name
 */
final class QueryEagerTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        Query::watch(false);
        $books = array();
        for ($i = 1; $i <= 20; $i++) {
            $books[] = array('id' => $i, 'title' => "Book $i", 'author_id' => $i === 20 ? null : ($i % 5) + 1, 'writer_id' => 7);
        }
        $this->db = new QaFakeExecutor(array(
            'books' => $books,
            'authors' => array(
                array('id' => 1, 'name' => 'Ada', 'country_id' => 1), array('id' => 2, 'name' => 'Bo', 'country_id' => 2),
                array('id' => 3, 'name' => 'Cy', 'country_id' => 1), array('id' => 4, 'name' => 'Di', 'country_id' => null),
                array('id' => 5, 'name' => 'Ed', 'country_id' => 9), array('id' => 6, 'name' => 'Fay', 'country_id' => 2),
            ),
            'countries' => array(array('id' => 1, 'name' => 'France'), array('id' => 2, 'name' => 'Japan')),
            'people' => array(array('id' => 7, 'name' => 'Ghost')),
            'tags' => array(array('id' => 1, 'label' => 'php'), array('id' => 2, 'label' => 'sql'), array('id' => 3, 'label' => 'none')),
            'book_tag' => array(array('book_id' => 1, 'tag_id' => 1), array('book_id' => 1, 'tag_id' => 2), array('book_id' => 2, 'tag_id' => 2)),
        ));
        Query::useExecutor($this->db);
    }

    protected function tearDown(): void
    {
        Query::useExecutor(null);
        Query::watch(null);
    }

    public function testTwentyBooksAndTheirAuthorsInTwoQueries(): void
    {
        $books = Database::table('books')->with('author')->get();
        $this->assertCount(2, $this->db->queries);
        $this->assertSame('SELECT * FROM `books`', $this->db->queries[0][0]);
        $this->assertSame(array('SELECT * FROM `authors` WHERE `id` IN (?, ?, ?, ?, ?)', array(2, 3, 4, 5, 1)), $this->db->queries[1]);
        $this->assertCount(20, $books);
        foreach ($books as $book) {
            if ($book['author_id'] === null) {
                $this->assertNull($book['author']);
            } else {
                $this->assertSame($book['author_id'], $book['author']['id']);
            }
        }
        $this->assertSame('Bo', $books[0]['author']['name']);
    }

    public function testNestedRelations(): void
    {
        $books = Database::table('books')->with('author.country', 'author')->where('id', '<', 6)->get();
        $this->assertSame(array(
            'SELECT * FROM `books` WHERE `id` < ?',
            'SELECT * FROM `authors` WHERE `id` IN (?, ?, ?, ?, ?)',
            'SELECT * FROM `countries` WHERE `id` IN (?, ?, ?)',
        ), $this->db->sql());
        $this->assertSame(array(1, 2, 9), $this->db->queries[2][1], 'unique, non-null keys');
        $this->assertSame('Japan', $books[0]['author']['country']['name']);       // book 1 -> author 2 -> Japan
        $this->assertNull($books[2]['author']['country']);                         // book 3 -> author 4 -> no country
        $this->assertNull($books[3]['author']['country']);                         // book 4 -> author 5 -> country 9 missing
    }

    public function testHasManyAndWithCount(): void
    {
        $authors = Database::table('authors')->with('books')->withCount('books')->get();
        $this->assertSame(array(
            'SELECT * FROM `authors`',
            'SELECT * FROM `books` WHERE `author_id` IN (?, ?, ?, ?, ?, ?)',
            'SELECT `author_id` AS `__key`, COUNT(*) AS `aggregate` FROM `books` WHERE `author_id` IN (?, ?, ?, ?, ?, ?) GROUP BY `author_id`',
        ), $this->db->sql());
        $this->assertSame(array(5, 10, 15), array_column($authors[0]['books'], 'id'));
        $this->assertSame(3, $authors[0]['books_count']);
        $this->assertSame(4, $authors[1]['books_count']);
        $this->assertSame(array(), $authors[5]['books'], 'no books: []');
        $this->assertSame(0, $authors[5]['books_count']);
    }

    public function testBelongsToManyThroughThePivotConvention(): void
    {
        $books = Database::table('books')->whereIn('id', array(1, 2, 3))->with(array('tags' => array('pivot' => true)))->withCount(array('tags' => array('pivot' => true)))->get();
        $this->assertSame(
            'SELECT `tags`.*, `book_tag`.`book_id` AS `__pivot_key` FROM `tags` INNER JOIN `book_tag` ON `book_tag`.`tag_id` = `tags`.`id` WHERE `book_tag`.`book_id` IN (?, ?, ?)',
            $this->db->queries[1][0]
        );
        $this->assertSame(
            'SELECT `book_tag`.`book_id` AS `__key`, COUNT(*) AS `aggregate` FROM `book_tag` WHERE `book_tag`.`book_id` IN (?, ?, ?) GROUP BY `book_tag`.`book_id`',
            $this->db->queries[2][0]
        );
        $this->assertCount(3, $this->db->queries);
        $this->assertSame(array(array('id' => 1, 'label' => 'php'), array('id' => 2, 'label' => 'sql')), $books[0]['tags'], 'no pivot key left');
        $this->assertSame(array('sql'), array_column($books[1]['tags'], 'label'));
        $this->assertSame(array(), $books[2]['tags']);
        $this->assertSame(array(2, 1, 0), array_column($books, 'tags_count'));
    }

    public function testExplicitSettings(): void
    {
        $books = Database::table('books')->where('id', 1)
            ->with(array('author' => array('table' => 'people', 'foreign_key' => 'writer_id')))
            ->with(array('labels' => array('table' => 'tags', 'pivot' => 'book_tag', 'related_key' => 'tag_id')))
            ->get();
        $this->assertSame(array('SELECT * FROM `people` WHERE `id` IN (?)', array(7)), $this->db->queries[1]);
        $this->assertSame('Ghost', $books[0]['author']['name']);
        $this->assertStringContainsString('FROM `tags` INNER JOIN `book_tag` ON `book_tag`.`tag_id` = `tags`.`id` WHERE `book_tag`.`book_id` IN (?)', $this->db->queries[2][0]);
        $this->assertSame(array('php', 'sql'), array_column($books[0]['labels'], 'label'));
    }

    public function testConventions(): void
    {
        $this->assertSame(
            array('type' => 'belongsTo', 'query' => null, 'pivot' => null, 'related_key' => null, 'table' => 'categories', 'foreign_key' => 'category_id', 'owner_key' => 'id', 'local_key' => null),
            Query::relation('books', 'category')
        );
        $hasMany = Query::relation('categories', 'books');
        $this->assertSame(array('hasMany', 'books', 'category_id', 'id'), array($hasMany['type'], $hasMany['table'], $hasMany['foreign_key'], $hasMany['local_key']));
        $many = Query::relation('tags', 'books', array('type' => 'belongsToMany'));
        $this->assertSame(array('book_tag', 'tag_id', 'book_id'), array($many['pivot'], $many['foreign_key'], $many['related_key']));
        $one = Query::relation('users', 'profile', array('type' => 'hasOne'));
        $this->assertSame(array('profiles', 'user_id'), array($one['table'], $one['foreign_key']));
    }

    public function testConstraintsAndHasOne(): void
    {
        $authors = Database::table('authors')->where('id', 1)
            ->with(array('books' => function (Query $q) { $q->where('title', 'Book 5')->orWhere('title', 'Book 15'); }))
            ->get();
        $this->assertSame(
            array('SELECT * FROM `books` WHERE (`title` = ? OR `title` = ?) AND `author_id` IN (?)', array('Book 5', 'Book 15', 1)),
            $this->db->queries[1],
            'the constraint is grouped so an orWhere() cannot escape the IN list'
        );
        $this->assertSame(array(5, 10, 15), array_column($authors[0]['books'], 'id'), 'the fake ignores the title filter');

        $authors = Database::table('authors')->where('id', 1)->with(array('book' => array('type' => 'hasOne')))->get();
        $this->assertSame(5, $authors[0]['book']['id']);
    }

    public function testNoRowsNoKeysNoExtraQuery(): void
    {
        $this->assertSame(array(), Database::table('books')->where('id', 99)->with('author', 'tags')->get());
        $this->assertCount(1, $this->db->queries);
        $books = Database::table('books')->where('id', 20)->with('author')->get();
        $this->assertNull($books[0]['author']);
        $this->assertCount(2, $this->db->queries, 'a book without author_id needs no author query');
    }

    public function testInListsAreChunkedOver1000Keys(): void
    {
        $rows = array();
        for ($i = 1; $i <= 2500; $i++) {
            $rows[] = array('id' => $i, 'author_id' => $i);
        }
        $db = new QaFakeExecutor(array('books' => $rows, 'authors' => array_map(function ($r) { return array('id' => $r['id']); }, $rows)));
        Query::useExecutor($db);
        $books = Database::table('books')->with('author')->get();
        $this->assertCount(4, $db->queries);
        $this->assertSame(array(1000, 1000, 500), array(count($db->queries[1][1]), count($db->queries[2][1]), count($db->queries[3][1])));
        $this->assertSame(2500, $books[2499]['author']['id']);
    }

    public function testModelsGetTheirRelations(): void
    {
        $books = Database::table('books')->where('id', 1)->with('author')->models(QaBook::class)->get();
        $this->assertInstanceOf(QaBook::class, $books[0]);
        $this->assertSame('Bo', $books[0]->author['name']);
    }

    public function testBadRelationSettingsAreRefused(): void
    {
        foreach (array(
            array('author' => array('foriegn_key' => 'x')),
            array('author' => array('type' => 'manyToMany')),
            array('author' => array('query' => 'not callable')),
            array('author' => 'people'),
        ) as $settings) {
            try {
                Database::table('books')->with($settings);
                $this->fail('accepted ' . json_encode($settings));
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        Database::table('books')->withCount('author')->get();
    }
}
