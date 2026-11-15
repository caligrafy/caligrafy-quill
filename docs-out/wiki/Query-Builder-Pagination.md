`paginate()` gives one page of a [query](https://github.com/caligrafy/caligrafy-quill/wiki/Queries)'s rows with the
numbers a page needs: the rows, how many there are in all, which page it is and how many pages there are. It runs two
queries (a count, then the page), and one when the page is past the last. A capability that returns a page serves it
the same way to web pages, REST, the JS client, agents and Snap's lists. Since 2.19.0.

```php
$page = Book::query()->orderBy('title')->paginate(2, 2);
// ['data' => [Kindred, Parable of the Sower], 'total' => 5, 'page' => 2, 'per_page' => 2, 'pages' => 3]
```

<br/>

## With Claude

```text
/caligrafy-quill:new-capability list_books books:read read-only: the books sorted by title, 20 per page, with their author
```

Claude writes `paginate(20, $input['page'] ?? 1)` with `'page' => 'integer'` in `$rules`.

**Check** before you say OK: `php caligrafer.php call list_books page=2` gives `"page": 2` (and `"data": []` when there
are fewer than 21 books).

<br/>

## By hand

1. **The capability** pages with the page of its input: REST, an agent and the command line have no query string.
    ```php
    public $rules = array('author_id' => 'integer', 'page' => 'integer');

    public $descriptions = array(
        'author_id' => 'Optional: only the books of this author',
        'page' => 'Optional: the page, 1 by default (20 books per page)'
    );

    public function handle(array $input, Context $context)
    {
        $query = Book::with('author')->orderBy('title');
        if (!empty($input['author_id'])) {
            $query->where('author_id', $input['author_id']);
        }
        return $query->paginate(20, $input['page'] ?? 1);
    }
    ```
2. **The page**, in `application/config/routing/web.php`: the query string reaches the capability as its inputs
   (`?page=2`, `?author_id=2`), and the `query` option gives the view `$author_id` to keep in the links:
    ```php
    Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books', 'authors' => 'list_authors'], 'query' => ['author_id']]);
    ```
3. **The links**, in `application/views/Bookshelf/books.pug`
   ([the whole view](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)):
    ```pug
    //- the next and previous pages keep the author of the address (?author_id=2)
    - $filter = $author_id !== '' ? '&author_id=' . (int) $author_id : '';
    p.cq-auth-note
        | Page #{$books['page']} of #{$books['pages']}, #{$books['total']} books
        if $books['page'] > 1
            |  · 
            a(href="{$home}books?page=" . ($books['page'] - 1) . $filter) Previous
        if $books['page'] < $books['pages']
            |  · 
            a(href="{$home}books?page=" . ($books['page'] + 1) . $filter) Next
    ```
   The rows are `$books['data']`. Pug in Caligrafy is PHP: strings join with `.`.

<br/>

## <a name="reference"></a>Reference

[`paginate($perPage = 20, $page = null)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-paginate)
returns:

| Key | What it is |
|---|---|
| `data` | the rows of the page (arrays or models, with their relations) |
| `total` | the rows of the whole query |
| `page` | the page number used |
| `per_page` | the rows per page |
| `pages` | the number of pages, at least 1 |

+ **Without a page number**, `paginate()` reads `page` from [`input($array)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-input)
  when you gave one, else from the query string (`?page=2`).
+ A page number that is not a whole number >= 1 is page 1. A page past the last one gives `'data' => []` and runs no
  second query.
+ `$perPage` must be a whole number >= 1: anything else throws `paginate() needs a number of rows per page >= 1`.
+ Elsewhere a page keeps its shape: [`Route::download`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#download)
  exports the rows of that page only; a [Snap Show: List](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards)
  shows its pages; a [card view](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Card-Views) gets `$rows` and
  `$page`.

<br/>

## Common mistakes

+ **`paginate(20)` in a capability**: it reads the query string, which REST, agents and the command line do not send.
  Write `paginate(20, $input['page'] ?? 1)` with `'page' => 'integer'` in `$rules`.
+ **`page` missing from `$rules`**: undeclared inputs never reach `handle()`, so every page is page 1.
+ **Reading `$_GET` in the view**: the view gets the query string only through the `query` option of `Route::page`.

<br/>

## Related pages

+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries#paging)
+ [Getting the rows](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Getting-Rows)
+ [Views: a page of a list](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)
+ [API: Query::paginate()](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-paginate)
