# Queries: the query builder

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Queries
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

The **query builder** reads and writes your tables without SQL strings: you chain a few calls, and Caligrafy writes
one SQL statement with every value bound. It is how a capability lists, filters, pages and counts rows, and loads
related rows in one query instead of one per row. Since 2.19.0.

```php
use Caligrafy\Database;

$rows  = Database::table('books')->where('price', '<', 20)->orderBy('title')->limit(10)->get();   // arrays
$books = Book::where('author_id', 3)->with('author', 'tags')->paginate(20);                       // Book models
```

+ `Database::table('books')` gives **arrays** (one per row).
+ `Book::query()`, or any call on the model class (`Book::where(...)`, `Book::find(12)`, `Book::with(...)`), gives
  **Book models**, on the model's table.

## Reading rows

```php
Book::where('author_id', 3)->get();                         // author_id = 3
Book::where('price', '<', 20)->where('stock', '>', 0)->get(); // AND
Book::where('price', '<', 5)->orWhere('featured', 1)->get();  // OR
Book::where(['author_id' => 3, 'published' => 1])->get();   // several equalities
Book::where(function ($q) {                                  // a group in parentheses
    $q->where('price', '<', 10)->orWhere('featured', 1);
})->where('published', 1)->get();

Book::whereIn('id', [1, 2, 3])->get();
Book::whereNull('deleted_at')->get();                       // also whereNotNull, whereNotIn
Book::whereBetween('price', [10, 20])->get();
Book::query()->search(['title', 'summary'], $keywords)->get();  // title or summary contains the words
Book::query()->latest()->limit(5)->get();                   // newest first (created_at); oldest() the other way
```

| To get | Call | Returns |
|---|---|---|
| every row | `get()` | a list (empty when none) |
| the first row | `first()` | one row, or `null` |
| one row by id | `find(12)` | one row, or `null` |
| one value | `value('title')` | the value, or `null` |
| one column | `pluck('title')`, `pluck('title', 'id')` | `['Dune', ...]`, `[12 => 'Dune', ...]` |
| how many | `count()` | a number |
| whether any | `exists()` | `true` or `false` |
| a page | `paginate(20)` | `['data', 'total', 'page', 'per_page', 'pages']` |
| many rows, in parts | `chunk(500, function ($rows) { ... })` | `true`, or `false` when the function returned `false` |

Operators are `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `like` and `not like`. `where('col', null)` is `IS NULL`.
`select('id', 'title')` picks the columns (`'price as cost'` renames one).

**Names are checked, values are bound.** A table or column name must be letters, digits and `_` (or
`table.column`); anything else is refused before any SQL runs (error 2001). Values never go into the SQL, so what a
person types is harmless. `search()` finds the words as they are: `%` and `_` in them are not wildcards.

## Paging

```php
$page = Book::where('published', 1)->orderBy('title')->paginate(20, $input['page'] ?? 1);
// ['data' => [20 books], 'total' => 134, 'page' => 2, 'per_page' => 20, 'pages' => 7]
```

Without a page number, `paginate()` reads `page` from the query string (`?page=2`). In a capability, pass the page of
its input, as above: a REST call, an agent or the command line has no query string. Declare `'page' => 'integer'` in
the capability's `$rules` so that it reaches `handle()`. A page past the last one gives no rows.

## Related rows: `with()` and the N+1 problem

Listing 50 books and then, for each one, reading its author runs 51 queries (the N+1 problem). `with()` runs two:
one for the books, one for all their authors.

```php
$books = Book::with('author', 'tags')->get();
// each book has author (one row, or null) and tags (a list, or [])

$authors = Database::table('authors')->withCount('books')->get();   // each author has books_count
```

`with()` follows the names of your tables:

| Name | Kind | Columns it uses |
|---|---|---|
| `author` on `books` (singular) | belongs to | `books.author_id` -> `authors.id` |
| `books` on `authors` (plural) | has many | `books.author_id` = `authors.id` |
| `['tags' => ['pivot' => true]]` | many to many | the pivot `books_tags` (or `tags_books`): `book_id`, `tag_id` |
| `author.country` | nested | the author's `country_id` -> `countries.id` |

When your names differ, say so: `with(['author' => ['table' => 'people', 'foreign_key' => 'writer_id']])`. To keep
only some related rows, give a function: `with(['books' => function ($q) { $q->where('published', 1); }])`.

+ The key column must be in the rows: `select('title')->with('author')` has no `author_id`, so no author.
+ Related rows are arrays, also under models: `$book->author['name']`.
+ Irregular plurals (`person`/`people`) need `table`.

**The N+1 warning.** While you develop (`APP_ENV` other than `production`), when the same query runs 10 times in one
request Caligrafy writes one warning in the PHP error log: "Probable N+1 query", with the file and line that ran it.
Add a `with()` there. Production does not count.

## Writing rows

```php
$id = Database::table('books')->insert(['title' => 'Dune', 'author_id' => 3]);       // the new id
Database::table('tags')->insert([['label' => 'sf'], ['label' => 'classic']]);       // several rows, one statement

Book::where('id', $id)->update(['price' => 12]);           // rows changed
Book::where('stock', 0)->delete();                         // rows deleted
Book::where('id', $id)->increment('views');                // also decrement('stock', 2)
```

`update()`, `delete()`, `increment()` and `decrement()` need a `where`: without one they refuse to run, so a missing
condition never empties a table. For every row of a table, write the SQL with `Database::execute()`.

The model methods you know keep working: `(new Book('books'))->create($input)`, `->update($input, $id)`,
`->delete($id)`, `$book->save()`.

## Transactions

```php
Database::transaction(function () use ($order) {
    $id = Database::table('orders')->insert($order);
    Database::table('stock')->where('book_id', $order['book_id'])->decrement('quantity');
    return $id;
});
```

All of it is saved, or none of it: an exception rolls everything back and is thrown again. A transaction inside
another one runs in the outer one. MySQL saves at once any `CREATE` or `ALTER`, which ends a transaction early: keep
table changes in [migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).

## In a capability

```php
#[Capability(
    name: 'list_books',
    description: 'Lists the published books, 20 per page, with their author. Filters: author_id, search (words of the title or summary).',
    scope: 'books:read',
    readOnly: true
)]
class ListBooks extends Service
{
    public $rules = array('author_id' => 'integer', 'search' => '', 'page' => 'integer');

    public function handle(array $input, Context $context)
    {
        $query = Book::where('published', 1)->with('author')->orderBy('title');
        if (!empty($input['author_id'])) {
            $query->where('author_id', $input['author_id']);
        }
        if (!empty($input['search'])) {
            $query->search(['title', 'summary'], $input['search']);
        }
        return $query->paginate(20, $input['page'] ?? 1);
    }
}
```

One capability, and the page, the REST API, the Vue app and agents all get the same filtered, paged list. A query
built step by step changes as you call it: `$query->where(...)` adds to `$query`.

## When to keep SQL

The builder writes one table's SELECT, INSERT, UPDATE and DELETE. Keep SQL, with `?` placeholders and
`Database::execute($sql, $bindings)`, for joins with `GROUP BY`, subqueries, functions of the database, a change to a
whole table, and anything the builder does not write. `$query->toSql()` and `$query->getBindings()` show what the
builder will run.

## Notes for models

+ `Book::query()` reads the table `books`: the class name in lower case, plus an `s` (`Category` -> `categories`,
  `BookReview` -> `bookreviews`). Another name: `protected static $tableName = 'book_reviews';` in the model.
+ `Book::find(12)` (the builder) and `$book->find(12)` (the model method) both work. A model that declares its own
  `find()` keeps it for `$book->find()`, but `Book::find()` then fails: use `Book::query()->find(12)`.
+ `all()`, `search()`, `create()`, `update()` and `delete()` of a model are unchanged, and stay instance methods.
