A [query](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) runs when you ask for its rows: `get()` for every
row, `first()` and `find()` for one, `value()` and `pluck()` for a value or a column, `count()` and `exists()` for a
number or a yes, `chunk()` for many rows in parts, `paginate()` for a page. What you get is **arrays** from
`Database::table()` and **models** from a model class. Since 2.19.0.

```php
use Caligrafy\Database;

$rows  = Database::table('books')->where('author_id', 2)->get();   // arrays
$book  = Book::find(3);                                            // a Book, or null
$count = Book::where('author_id', 2)->count();                     // 2
```

<br/>

## With Claude

```text
/caligrafy-quill:new-capability get_book books:read read-only: one book by its id, not found when there is none
```

Claude reads the row with `Book::find($input['id'])` (or `with(...)->find(...)`) and throws
`ServiceError::notFound(...)` when it is `null`.

**Check:** `php caligrafer.php call get_book id=999` answers `not_found`, not an empty `data`.

<br/>

## By hand

1. **Arrays or models.** `Database::table('books')` gives arrays, one per row. `Book::query()`, or a call of the query
   builder on the model class (`Book::where(...)`, `Book::find(3)`, `Book::with(...)`, `Book::first()`,
   `Book::paginate(20)`...), gives `Book` models, on the model's table: the class name in lower case plus `s`
   (`Book` -> `books`), unless the class declares `protected static $tableName = 'book_reviews';`.
2. **Switch between them.** `arrays()` keeps a model query's rows as arrays; `models(Book::class)` turns a
   `Database::table()` query's rows into models:
    ```php
    Book::query()->select('id', 'title')->arrays()->get();        // [['id' => 1, 'title' => 'A Wizard of Earthsea'], ...]
    Database::table('books')->models(Book::class)->get();         // Book models
    ```
3. **One row or nothing.** In a capability, a missing row is a refusal:
    ```php
    $book = Book::find($input['id']);
    if (!$book) {
        throw ServiceError::notFound('There is no book #' . $input['id']);
    }
    return $book;
    ```

<br/>

## <a name="get"></a>Reference: getting the rows

| To get | Call | Returns |
|---|---|---|
| every row | [`get()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-get) | a list (empty when none) |
| the first row | [`first()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-first) | one row, or `null` |
| one row by id | [`find(3)`, `find($value, 'isbn')`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-find) | one row, or `null` |
| one value | [`value('title')`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-value) | `'Kindred'`, or `null` |
| one column | [`pluck('title')`, `pluck('title', 'id')`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-pluck) | `['A Wizard of Earthsea', ...]`, `[1 => 'A Wizard of Earthsea', ...]` |
| how many | [`count()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-count) | a number (order and limit ignored) |
| whether any | [`exists()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-exists) | `true` or `false` |
| a page | [`paginate(20, $page)`](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Pagination) | `['data', 'total', 'page', 'per_page', 'pages']` |
| many rows, in parts | [`chunk(500, function ($rows, $part) { ... })`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-chunk) | `true`, or `false` when the function returned `false` |

+ `pluck()` also takes a count of `withCount()` or a relation of `with()`:
  `Author::withCount('books')->pluck('books_count', 'name')` gives `['Ursula K. Le Guin' => 2, ...]`.
+ `chunk()` reads in `id` order unless the query has an order; the function gets the rows and the number of the part
  (from 1), and returning `false` stops.
+ `count()`, `exists()`, `value()` and `pluck()` of a column run one small query, without the relations and without
  models.

<br/>

## <a name="models"></a>Reference: models and arrays

| Query | Rows |
|---|---|
| `Database::table('books')` | arrays |
| `Book::query()`, `Book::where(...)`, `Book::find(3)` | `Book` models, related rows (`with()`) as arrays |
| `->arrays()` | arrays (undoes the models) |
| `->models(Book::class)` | instances of a class that extends `Caligrafy\Model` (another class is refused) |

A model has every property its class declares: with `select()`, the other columns are `null`, and a capability returns
them, `null`s included. For only the columns you selected, add `arrays()`.

<br/>

## Common mistakes

+ **`Book::find()` with a `find()` of your own in the model** (G122): `Book::find(1)` then fails with "Non-static method
  cannot be called statically". Leave it out, or write `Book::query()->find(1)`.
+ **A table the class name does not give** (G123): `BookReview` reads `bookreviews`; declare
  `protected static $tableName = 'book_reviews';`.
+ **Selected columns with models** (G138): add `arrays()`.
+ **A query inside a `foreach`** over rows: load the related rows with
  [`with()`](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Eager-Loading) instead.

<br/>

## Related pages

+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries)
+ [Filters and order](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Filters-and-Order) · [Pagination](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Pagination) · [Eager loading](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Eager-Loading) · [Writing and transactions](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Writing-and-Transactions)
+ [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)
+ [API: Caligrafy\Query](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query) · [Caligrafy\Model](https://github.com/caligrafy/caligrafy-quill/wiki/API-Model)
