# Queries: the query builder

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Queries
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

The **query builder** reads and writes your tables without SQL strings. You chain a few calls, and Caligrafy writes one
SQL statement with every value bound (sent apart from the SQL, so what a person types can never change the query). It
is how a capability lists, filters, pages and counts rows, and loads related rows in one query instead of one per row.
Since 2.19.0.

```php
use Caligrafy\Database;

$rows  = Database::table('books')->where('published_year', '<', 1975)->orderBy('title')->limit(10)->get();   // arrays
$books = Book::where('author_id', 1)->with('author')->paginate(20);                                        // Book models
```

+ `Database::table('books')` gives **arrays**, one per row.
+ `Book::query()`, or any call on the model class (`Book::where(...)`, `Book::find(3)`, `Book::with(...)`), gives
  **`Book` models**, on the model's table ([Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)).

The examples use the Bookshelf tables and sample rows of [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models).

## With Claude

Say what the list filters, sorts and returns:

```text
/caligrafy-quill:new-capability list_books books:read read-only: the books sorted by title, 20 per page, with their author and tags; filters author_id and search (title or summary)
```

Claude writes the capability with the query builder: `Book::with(...)->orderBy(...)`, a `where()` per filter given,
`search()` for words, and `paginate(20, $input['page'] ?? 1)` with `'page' => 'integer'` in `$rules`.

**Check** before you say OK:
+ no SQL string is built from an input, and no query runs inside a loop;
+ every input `handle()` reads is in `$rules` (`page` too);
+ `update()` and `delete()` of a query have a `where()`;
+ `php caligrafer.php call list_books search=shadow` returns the right rows.

## By hand

Run the commands in the Docker container: `docker compose exec dev-box php caligrafer.php ...` (without Docker, drop
`docker compose exec dev-box`).

1. **Create the capability.** Run:
    ```bash
    docker compose exec dev-box php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only
    ```
2. **Write the query.** Make `application/services/Bookshelf/ListBooks.php` read:
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Service;

    #[Capability(
        name: 'list_books',
        description: 'Lists the books of the Bookshelf catalog, sorted by title, 20 per page, each with its author and its tags. Filters: author_id, search (words of the title or the summary). Returns data (the books), total, page, per_page and pages.',
        scope: 'books:read',
        readOnly: true,
        public: true
    )]
    class ListBooks extends Service
    {
        public $rules = array(
            'author_id' => 'integer',
            'search' => 'max_len,100',
            'page' => 'integer'
        );

        public $descriptions = array(
            'author_id' => 'Optional: only the books of this author',
            'search' => 'Optional: words to find in the title or the summary',
            'page' => 'Optional: the page, 1 by default (20 books per page)'
        );

        public function handle(array $input, Context $context)
        {
            $query = Book::with('author', array('tags' => array('pivot' => true)))->orderBy('title');
            if (!empty($input['author_id'])) {
                $query->where('author_id', $input['author_id']);
            }
            if (!empty($input['search'])) {
                $query->search(array('title', 'summary'), $input['search']);
            }
            return $query->paginate(20, $input['page'] ?? 1);
        }
    }
    ```
    A query built step by step changes as you call it: `$query->where(...)` adds to `$query`.
3. **Run it.**
    ```bash
    docker compose exec dev-box php caligrafer.php call list_books search=shadow
    ```
    returns one page: `"data"` holds *A Wizard of Earthsea* with its `author` and its `tags`, then
    `"total": 1, "page": 1, "per_page": 20, "pages": 1`. With `author_id=2` the page holds *Kindred* and *Parable of
    the Sower* (`"total": 2`); with `author_id=abc` the answer is
    `"author_id": "The Author Id field must be a number without a decimal"`.

One capability, and the page, the REST API, the Vue app and agents all get the same filtered, paged list
([Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#web)).

**Check:**
+ `call list_books search=shadow` gives `"total": 1`;
+ `call list_books search=50%` gives `"total": 0`: `%` is a character to find, not a wildcard;
+ `call list_books page=2` gives `"data": []` with `"page": 2` (there are fewer than 21 books).

## Reference: building a query

```php
Book::where('author_id', 1)->get();                               // author_id = 1
Book::where('published_year', '<', 1975)->where('author_id', 1)->get();   // AND
Book::where('published_year', '<', 1970)->orWhere('author_id', 3)->get(); // OR
Book::where(['author_id' => 1, 'published_year' => 1968])->get(); // several equalities, in parentheses
Book::where(function ($q) {                                       // a group in parentheses
    $q->where('published_year', '<', 1970)->orWhere('author_id', 3);
})->where('summary', '!=', null)->get();

Book::whereIn('id', [1, 2, 3])->get();                            // whereNotIn too
Book::whereNull('summary')->get();                                // whereNotNull too
Book::whereBetween('published_year', [1960, 1980])->get();        // both ends included
Book::query()->search(['title', 'summary'], $words)->get();       // title or summary contains the words
Book::query()->latest()->limit(5)->offset(5)->get();              // newest first (created_at); oldest() the other way
Book::query()->select('id', 'title as name')->arrays()->get();    // two columns, renamed
```

The SQL they write (`toSql()` and `getBindings()` show it, without running it):

| Query | SQL | Bindings |
|---|---|---|
| `Book::where(['author_id' => 1, 'published_year' => 1968])` | ``SELECT * FROM `books` WHERE (`author_id` = ? AND `published_year` = ?)`` | `[1, 1968]` |
| the group above | ``... WHERE (`published_year` < ? OR `author_id` = ?) AND `summary` IS NOT NULL`` | `[1970, 3]` |
| `Book::whereIn('id', [1, 2, 3])->whereNull('summary')->whereBetween('published_year', [1960, 1980])` | ``... WHERE `id` IN (?, ?, ?) AND `summary` IS NULL AND `published_year` BETWEEN ? AND ?`` | `[1, 2, 3, 1960, 1980]` |
| `Book::query()->search(['title', 'summary'], 'shadow 50%')` | ``... WHERE (`title` LIKE ? OR `summary` LIKE ?)`` | `['%shadow 50\%%', '%shadow 50\%%']` |
| `Book::query()->latest()->limit(5)->offset(5)` | ``SELECT * FROM `books` ORDER BY `created_at` DESC LIMIT 5 OFFSET 5`` | `[]` |
| `Book::query()->select('id', 'title as name')` | ``SELECT `id`, `title` AS `name` FROM `books` `` | `[]` |

| Call | Meaning |
|---|---|
| `where($column, $value)` | `=`; `where($column, null)` is `IS NULL` |
| `where($column, $operator, $value)` | operators `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `like`, `not like`; `where($column, '!=', null)` is `IS NOT NULL` |
| `where([$column => $value, ...])`, `where(function ($q) {...})` | equalities joined by `AND`, a group; both in parentheses |
| `orWhere(...)` | the same forms, joined by `OR` |
| `whereIn($column, $list)`, `whereNotIn($column, $list)` | an empty list: `whereIn` matches no row, `whereNotIn` adds no condition |
| `whereNull($column)`, `whereNotNull($column)`, `whereBetween($column, [$from, $to])` | |
| `search($columns, $words)` | `(c1 LIKE ? OR c2 LIKE ?)` with `%words%` bound; `%` and `_` in the words are escaped; empty words add no condition |
| `orderBy($column, 'asc' or 'desc')`, `latest($column = 'created_at')`, `oldest($column = 'created_at')` | |
| `limit($n)`, `offset($n)` | whole numbers >= 0 |
| `select(...$columns)` | `'id'`, `'books.*'`, `'title as name'`; one array works too |
| `arrays()` | rows stay arrays (undoes the models of `Book::query()`) |
| `input($array)` | where `paginate()` reads `page` instead of the query string |
| `with(...)`, `withCount(...)` | related rows and counts: [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships) |

**Names are checked, values are bound.** A table or column name must be letters, digits and `_` (or `table.column`);
anything else is refused before any SQL runs: `[ERROR 2001] Invalid table or column name 'name; DROP': letters, digits
and _ only, with one optional prefix (table.column)`. Another operator gives `[ERROR 2001] Operator not allowed: use
=, !=, <>, <, >, <=, >=, like or not like`. A boolean is bound as `0`/`1`, a `DateTime` as `Y-m-d H:i:s`; an array
value is refused (use `whereIn`).

**Selected columns and models.** A model has every property its class declares, so
`Book::query()->select('id', 'title')->get()` gives `Book` models whose other columns are `null` (and a capability
returns them, `null`s included). For only the columns you selected (a CSV export, a small list), add `arrays()`:
`Book::query()->select('id', 'title')->arrays()->get()` gives `[['id' => 1, 'title' => 'A Wizard of Earthsea'], ...]`,
as `Database::table('books')->select('id', 'title')->get()` does.

## Reference: getting the rows

| To get | Call | Returns |
|---|---|---|
| every row | `get()` | a list (empty when none) |
| the first row | `first()` | one row, or `null` |
| one row by id | `find(3)`, `find($value, 'isbn')` | one row, or `null` |
| one value | `value('title')` | `'Kindred'`, or `null` |
| one column | `pluck('title')`, `pluck('title', 'id')` | `['A Wizard of Earthsea', ...]`, `[1 => 'A Wizard of Earthsea', ...]` |
| how many | `count()` | a number (order and limit ignored) |
| whether any | `exists()` | `true` or `false` |
| a page | `paginate(20, $page)` | `['data', 'total', 'page', 'per_page', 'pages']` |
| many rows, in parts | `chunk(500, function ($rows, $part) { ... })` | `true`, or `false` when the function returned `false` |

`pluck()` also takes a count of `withCount()` or a relation of `with()`:
`Author::withCount('books')->pluck('books_count', 'name')` gives `['Ursula K. Le Guin' => 2, ...]`. `chunk()` reads in
`id` order unless the query has an order.

### Paging

```php
$page = Book::query()->orderBy('title')->paginate(2, 2);
// ['data' => [Kindred, Parable of the Sower], 'total' => 5, 'page' => 2, 'per_page' => 2, 'pages' => 3]
```

+ Without a page number, `paginate()` reads `page` from the query string (`?page=2`). In a capability, pass the page
  of its input, as in the steps above: REST, an agent or the command line have no query string.
+ A page number that is not a whole number >= 1 is page 1. A page past the last one gives `'data' => []` (and runs
  no second query). `pages` is at least 1.

### Related rows: `with()` and the N+1 problem

Listing 50 books and then reading each one's author runs 51 queries (the N+1 problem). `with()` runs two: one for the
books, one for all their authors (`WHERE id IN (...)`).

```php
$books   = Book::with('author', ['tags' => ['pivot' => true]])->get();   // author: a row or null; tags: a list
$authors = Author::withCount('books')->get();                            // books_count on each
$loan    = Database::table('loans')->with('book.author')->first();       // the loan, its book, the book's author
```

The names, the conventions and the settings are on
[Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships).

**The N+1 warning.** While you develop (`APP_ENV` other than `production`), when the same query runs 10 times in one
request, Caligrafy writes one line in the PHP error log:

```text
[Caligrafy] Probable N+1 query: the same SELECT ran 10 times in this request (select * from `books` where `id` = ? limit ?), from <the file>:<the line>. Load the rows once with with(): Database::table('books')->with('author')->get() or Book::with('author')->get().
```

Add a `with()` where it says. Production does not count.

## Reference: writing rows

```php
$id = Database::table('tags')->insert(['name' => 'poetry']);                    // the new id, as a string: "4"
Database::table('tags')->insert([['name' => 'essay'], ['name' => 'travel']]);   // several rows, one statement

Database::table('tags')->where('id', $id)->update(['name' => 'poems']);         // 1: the rows changed
Book::where('id', 1)->increment('published_year');                              // also decrement('stock', 2)
Database::table('tags')->whereIn('name', ['essay', 'travel', 'poems'])->delete(); // 3: the rows deleted
```

+ `insert()` takes one row, or a list of rows that all have the same columns. Arrays and objects are written as JSON,
  booleans as `0`/`1`.
+ `update()`, `delete()`, `increment()` and `decrement()` need a `where...()`. Without one they refuse to run, so a
  missing condition never empties a table:
  `delete() needs a where(): ->where('id', $id)->delete(...). For every row of the table, write the SQL with Database::execute()`.
  `increment('stock', 5, ['restocked_at' => date('Y-m-d H:i:s')])` sets other columns at the same time.

### Transactions

```php
$id = Database::transaction(function () use ($input) {
    // the book changes hands: close its open loan, then open the new one
    Database::table('loans')->where('book_id', $input['book_id'])->whereNull('returned_at')->update(['returned_at' => date('Y-m-d H:i:s')]);
    return Database::table('loans')->insert(['book_id' => $input['book_id'], 'borrower' => $input['borrower'], 'due_on' => $input['due_on']]);
});
```

All of it is saved, or none of it: an exception rolls everything back and is thrown again; otherwise
`transaction()` returns what the function returns. A transaction inside another one (or inside a test, which runs in
a transaction) is part of the outer one: catching its exception inside the outer one does not undo its rows. MySQL
saves a `CREATE` or an `ALTER` at once, which ends a transaction early: keep table changes in
[migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).

## Under the hood: when to keep SQL

The builder writes one table's `SELECT` (with its relations), `INSERT`, `UPDATE` and `DELETE`. Keep SQL, with `?`
placeholders, for joins with `GROUP BY`, subqueries, database functions and changes to every row of a table:

```php
$rows = Database::execute('SELECT author_id, MAX(published_year) AS latest FROM books GROUP BY author_id HAVING latest > ?', [1970]);
// [['author_id' => 2, 'latest' => 1993], ['author_id' => 3, 'latest' => 1972]]
```

The model's own methods of before 2.19 keep working too: `(new Book('books'))->create($input)`,
`->update($changes, $id)`, `->delete($id)`, `$book->save()`
([Models: other ways](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models#querybuilder)). New code writes with
`Book::insert()`, `Book::where('id', $id)->update()` and `->delete()`.

`Query::useExecutor($object)` sends the builder's queries to another object with `execute($sql, $bindings)` (a test
double); `Query::useExecutor(null)` goes back to the database.

## Common mistakes

+ **`Book::find()` with a `find()` of your own in the model** (G122): it fails. Leave it out, or `Book::query()->find(3)`.
+ **A table the class name does not give** (G123): `BookReview` reads `bookreviews`; declare
  `protected static $tableName = 'book_reviews';`.
+ **`Book::search(...)`**: not forwarded; write `Book::query()->search(...)`. And the model's own
  `(new Book('books'))->search('title', '%x%')` takes `%` as a wildcard, the builder's `search()` does not (G126).
+ **`paginate(20)` in a capability**: it reads the query string, which REST, agents and the command line do not send.
  Write `paginate(20, $input['page'] ?? 1)` with `'page' => 'integer'` in `$rules`.
+ **`update()` without `where()`** (G125): refused. `whereNotIn('id', [])` and `search($columns, '')` add no condition,
  so they do not count as one.
+ **Selected columns with models**: the other properties come back `null`; add `arrays()`.
+ **The N+1 warning in a worker** (G127): `APP_ENV` is read once per process, so a long worker counts across jobs;
  call `Query::watch(null)` between jobs.

## Related pages

+ [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships): `with()`, `withCount()`, pivots
+ [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models): the tables and the models
+ [Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): capabilities on every channel
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#download): a list as a CSV file
