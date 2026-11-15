**Eager loading** reads the related rows of a [query](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) in
one more query per relation, instead of one query per row. Listing 50 books and then reading each one's author runs 51
queries (the N+1 problem); `with('author')` runs two: one for the books, one for all their authors
(`WHERE id IN (...)`). `withCount()` adds a count per row in one `GROUP BY` query. While you develop, Caligrafy warns
you when the same query runs 10 times in a request. Since 2.19.0.

```php
$books   = Book::with('author', ['tags' => ['pivot' => true]])->get();   // author: a row or null; tags: a list
$authors = Author::withCount('books')->get();                            // books_count on each
$loan    = Database::table('loans')->with('book.author')->first();       // the loan, its book, the book's author
```

<br/>

## With Claude

```text
/caligrafy-quill:new-capability get_book books:read read-only: one book with its author, its loans and its tags
```

Claude reads the rows with `Book::with(...)`, and never queries in a loop.

**Check** before you say OK:
+ related rows come from `with()` or `withCount()`, not a query inside a `foreach`;
+ many-to-many relations say `['pivot' => true]` (or the pivot's name);
+ `php caligrafer.php call get_book id=3` shows the related rows under their names (`author`, `loans`, `tags`).

<br/>

## By hand

1. **Name the relations.** `with()` follows the name you give and the table it is called on, so the models need no code:
    ```php
    $book = Book::with('author', 'loans', array('tags' => array('pivot' => true)))->find($input['id']);
    ```
   Four queries run: the book, then its author, its loans and its tags, each with `WHERE ... IN (...)`.
2. **Count instead of loading.**
    ```php
    return Author::withCount('books')->orderBy('name')->get();   // each author with books_count
    ```
3. **Narrow the related rows** with a function:
    ```php
    Book::with(['loans' => function ($q) { $q->whereNull('returned_at'); }])->get();
    ```

<br/>

## <a name="names"></a>Reference: how `with()` reads a name

| Name | Called on | Kind | It reads | You get |
|---|---|---|---|---|
| `author` (singular) | `books` | belongs to | `books.author_id` -> `authors.id` | one row, or `null` |
| `books` (plural) | `authors` | has many | `books.author_id` = `authors.id` | a list, or `[]` |
| `['tags' => ['pivot' => true]]` | `books` | many to many | `books_tags.book_id` = `books.id`, `books_tags.tag_id` = `tags.id` | a list, or `[]` |
| `book.author` | `loans` | nested | `loans.book_id` -> `books.id`, then `books.author_id` -> `authors.id` | `book` with its `author` |
| `['current_loan' => ['type' => 'hasOne', 'table' => 'loans']]` | `books` | has one | `loans.book_id` = `books.id` | the first row, or `null` |

+ The related rows are **arrays**, also under models: `$book->author['name']`, `$book->tags[0]['name']`.
+ The key must be in the rows: `Book::select('id', 'title')->with('author')` has no `author_id`, so `author` is `null`.
+ The pivot of `books` and `tags` is `books_tags`; when it does not exist but `tags_books` does, `with()` uses that one.
+ Keys are read in chunks of 1000 (`Query::IN_CHUNK`); the main rows are never repeated by a join.

<br/>

## <a name="settings"></a>Reference: the settings of a relation

```php
Book::with(['writer' => ['table' => 'authors', 'foreign_key' => 'author_id']])->find(3);   // $book->writer
Book::with(['current_loan' => ['type' => 'hasOne', 'table' => 'loans', 'query' => function ($q) { $q->whereNull('returned_at'); }]])->get();
```

| Setting | Meaning | Default |
|---|---|---|
| `type` | `belongsTo`, `hasOne`, `hasMany` or `belongsToMany` | singular name: `belongsTo`; plural: `hasMany`; with `pivot`: `belongsToMany` |
| `table` | the related table | the name in the plural (`belongsTo`, `hasOne`), the name itself (`hasMany`, `belongsToMany`) |
| `foreign_key` | belongs to: the column of this table; has one/many: the column of the related table; many to many: the pivot's column toward this table | `<name>_id` (belongs to), `<this table in the singular>_id` (the others) |
| `local_key` | the column of this table the others point at | `id` |
| `owner_key` | the column of the related table that is pointed at | `id` |
| `pivot` | `true` (the conventional pivot) or its name | none |
| `related_key` | the pivot's column toward the related table | `<name in the singular>_id` |
| `query` | a function that narrows the related rows | none |

An unknown setting, or a name that is not letters, digits and `_`, is refused before any query runs. All of it:
[Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships#override).

### `withCount()`

```php
Database::table('tags')->withCount(['books' => ['pivot' => true]])->pluck('books_count', 'name');
Author::withCount(['books' => function ($q) { $q->where('published_year', '<', 1970); }])->pluck('books_count', 'name');
```

It counts plural relations only (has many, many to many): `withCount('author')` is refused. A row with nothing to count
gets `0`.

<br/>

## <a name="nplusone"></a>Reference: the N+1 warning

While you develop (`APP_ENV` other than `production`), when the same `SELECT` shape runs 10 times in one request,
Caligrafy writes one line in the PHP error log, with the first file and line outside `framework/`:

```text
[Caligrafy] Probable N+1 query: the same SELECT ran 10 times in this request (select * from `books` where `id` = ? limit ?), from <the file>:<the line>. Load the rows once with with(): Database::table('books')->with('author')->get() or Book::with('author')->get().
```

+ It counts every query run through `Database::execute()`, models included. Production does not count.
+ `Query::$threshold` (10) is the count; `Query::$logger` (a callable) receives the line instead of `error_log()`;
  `Query::watch(true)` or `watch(false)` forces it on or off, `watch(null)` decides from `APP_ENV` again and forgets the
  shapes counted ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-watch)).

<br/>

## Common mistakes

+ **Relations follow names, not the database** (G124): irregular plurals (`person`/`people`) need `table` or `type`; a
  `limit()` in a `query` constraint limits the whole relation query, not each parent; a constraint on a many-to-many
  relation runs on the join: qualify columns (`tags.label`).
+ **`select()` without the key column**: the relation is `null`.
+ **The N+1 warning in a worker** (G127): `APP_ENV` is read once per process, so a long worker counts across jobs;
  call `Query::watch(null)` between jobs.
+ **A pivot inside `Database::transaction()` on PostgreSQL**: a failed query aborts the transaction; name the pivot
  (G124).

<br/>

## Related pages

+ [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships): the conventions and the pivot
+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries#eager)
+ [Getting the rows](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Getting-Rows)
+ [API: Query::with()](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-with) · [withCount()](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-withcount)
