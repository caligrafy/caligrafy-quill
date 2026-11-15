The [query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) narrows the rows with `where()`,
`whereIn()`, `whereNull()`, `whereBetween()` and `search()`, picks the columns with `select()`, and sorts and limits
them with `orderBy()`, `latest()`, `limit()` and `offset()`. Every value is bound (sent apart from the SQL), and every
name is checked, so what a person types can never change the query. Since 2.19.0.

```php
Book::where('published_year', '<', 1975)->where('author_id', 1)->orderBy('title')->limit(10)->get();
```

The examples use the Bookshelf tables of [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models).

<br/>

## With Claude

Say what the list filters and sorts:

```text
/caligrafy-quill:new-capability list_books books:read read-only: the books sorted by title, 20 per page; filters author_id and search (title or summary)
```

Claude adds a `where()` per filter given and `search()` for words, each input declared in `$rules`.

**Check** before you say OK: no SQL string is built from an input, and `php caligrafer.php call list_books search=50%`
gives `"total": 0` (`%` is a character to find, not a wildcard).

<br/>

## By hand

A query built step by step changes as you call it: `$query->where(...)` adds to `$query`. In a capability, add a
condition only for a filter that was given:

```php
public function handle(array $input, Context $context)
{
    $query = Book::with('author')->orderBy('title');
    if (!empty($input['author_id'])) {
        $query->where('author_id', $input['author_id']);
    }
    if (!empty($input['search'])) {
        $query->search(array('title', 'summary'), $input['search']);
    }
    return $query->paginate(20, $input['page'] ?? 1);
}
```

`toSql()` and `getBindings()` show the SQL and its values without running it:

```php
$query = Book::whereIn('id', [1, 2, 3])->whereNull('summary');
$query->toSql();        // SELECT * FROM `books` WHERE `id` IN (?, ?, ?) AND `summary` IS NULL
$query->getBindings();  // [1, 2, 3]
```

<br/>

## <a name="where"></a>Reference: conditions

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
```

| Call | Meaning |
|---|---|
| [`where($column, $value)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-where) | `=`; `where($column, null)` is `IS NULL` |
| `where($column, $operator, $value)` | operators `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `like`, `not like`; `where($column, '!=', null)` is `IS NOT NULL` |
| `where([$column => $value, ...])`, `where(function ($q) {...})` | equalities joined by `AND`, a group; both in parentheses |
| [`orWhere(...)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-orwhere) | the same forms, joined by `OR` |
| [`whereIn($column, $list)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-wherein), `whereNotIn($column, $list)` | an empty list: `whereIn` matches no row, `whereNotIn` adds no condition |
| `whereNull($column)`, `whereNotNull($column)` | `IS NULL`, `IS NOT NULL` |
| [`whereBetween($column, [$from, $to])`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-wherebetween) | both ends included |
| [`search($columns, $words)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-search) | `(c1 LIKE ? OR c2 LIKE ?)` with `%words%` bound; `%` and `_` in the words are escaped; empty words add no condition |

| Query | SQL | Bindings |
|---|---|---|
| `Book::where(['author_id' => 1, 'published_year' => 1968])` | ``SELECT * FROM `books` WHERE (`author_id` = ? AND `published_year` = ?)`` | `[1, 1968]` |
| the group above | ``... WHERE (`published_year` < ? OR `author_id` = ?) AND `summary` IS NOT NULL`` | `[1970, 3]` |
| `Book::query()->search(['title', 'summary'], 'shadow 50%')` | ``... WHERE (`title` LIKE ? OR `summary` LIKE ?)`` | `['%shadow 50\%%', '%shadow 50\%%']` |

<br/>

## <a name="order"></a>Reference: columns, order and limits

```php
Book::query()->latest()->limit(5)->offset(5)->get();              // newest first (created_at); oldest() the other way
Book::query()->select('id', 'title as name')->arrays()->get();    // two columns, renamed
```

| Call | Meaning |
|---|---|
| [`select(...$columns)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-select) | `'id'`, `'books.*'`, `'title as name'`; one array works too |
| [`orderBy($column, 'asc' or 'desc')`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-orderby) | several calls sort by several columns |
| `latest($column = 'created_at')`, `oldest($column = 'created_at')` | newest or oldest first |
| `limit($n)`, `offset($n)` | whole numbers >= 0 |

``Book::query()->latest()->limit(5)->offset(5)`` writes ``SELECT * FROM `books` ORDER BY `created_at` DESC LIMIT 5 OFFSET 5``.

**Names are checked, values are bound.** A table or column name must be letters, digits and `_` (or `table.column`);
anything else is refused before any SQL runs: `[ERROR 2001] Invalid table or column name 'name; DROP': letters, digits
and _ only, with one optional prefix (table.column)`. Another operator gives `[ERROR 2001] Operator not allowed: use
=, !=, <>, <, >, <=, >=, like or not like`. A boolean is bound as `0`/`1`, a `DateTime` as `Y-m-d H:i:s`; an array
value is refused (use `whereIn`).

<br/>

## Common mistakes

+ **`Book::search(...)`** is not forwarded to the builder: write `Book::query()->search(...)`. The model's own
  `(new Book('books'))->search('title', '%x%')` takes `%` as a wildcard; the builder's `search()` does not (G126).
+ **Selected columns with models**: `Book::query()->select('id', 'title')->get()` gives `Book` models whose other
  columns are `null`; add `arrays()` (G138).
+ **A filter read from `$input` that `$rules` does not declare**: only the inputs of `$rules` reach `handle()`.

<br/>

## Related pages

+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries): the overview
+ [Getting the rows](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Getting-Rows) · [Pagination](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Pagination) · [Eager loading](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Eager-Loading) · [Writing and transactions](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Writing-and-Transactions)
+ [API: Caligrafy\Query](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query)
