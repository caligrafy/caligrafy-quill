# How to export a list as CSV

A link people click to download a list as a spreadsheet: one read-only capability that returns the rows, and one line
of `web.php`, `Route::download`. The same capability keeps serving the page, REST and agents. This guide exports the
authors of the Bookshelf, with a narrow set of columns and a dated file name.

Before you start: the Bookshelf tables of
[Routing, steps 1 to 3](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes). The commands run in
the project folder (with Docker: `docker compose exec dev-box php caligrafer.php ...`).

<br/>

## With Claude

```text
/caligrafy-quill:new-capability export_authors books:read read-only public: the authors (id, name) sorted by name, downloadable as authors-<date>.csv from the authors page
```

<br/>

## By hand

1. **The capability.** Run:

   ```bash
   docker compose exec dev-box php caligrafer.php service Bookshelf/ExportAuthors --scope books:read --read-only
   ```

   Make `application/services/Bookshelf/ExportAuthors.php` read:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Database;
   use Caligrafy\Service;

   #[Capability(
       name: 'export_authors',
       description: 'Lists the authors of the Bookshelf catalog, sorted by name: id and name.',
       scope: 'books:read',
       readOnly: true,
       public: true
   )]
   class ExportAuthors extends Service
   {
       public function handle(array $input, Context $context)
       {
           // arrays, not models: the columns of the file are the keys of the first row
           return Database::table('authors')->select('id', 'name')->orderBy('name')->get();
       }
   }
   ```

   It returns `get()`, every row; a `paginate()` page would export one page only.

2. **The route.** In `application/config/routing/web.php`, before `Route::run();`:

   ```php
       Route::download('/authors.csv', 'export_authors', [
           'columns' => ['id', 'name'],
           'filename' => fn ($rows, $input) => 'authors-' . date('Y-m-d') . '.csv',
           'bom' => true,
       ]);
   ```

   `columns` writes the header row even when there is no author yet; `bom` lets Excel read the accents.

3. **The link.** In the authors' page view:

   ```pug
   a.btn.btn-secondary(href=$home . 'authors.csv') Download as CSV
   ```

4. **Try it.** Run:

   ```bash
   curl -s -i http://localhost:8080/authors.csv
   ```

   It answers `200` with `Content-Type: text/csv; charset=utf-8`, `Content-Disposition: attachment;
   filename="authors-<today>.csv"`, the header row `id,name` and one line per author, lines ending with CRLF.

<br/>

## Variations

+ **Filtered**: the query string and the route variables are the capability's inputs. A capability that declares
  `public $rules = array('author_id' => 'integer');` and filters on it is exported for one author with
  `/books.csv?author_id=2`; `?author_id=abc` answers `422` with the error as JSON.
+ **For signed-in people only**: drop `public: true`. A visitor who opens the link is sent to `/login` (`signIn` sets
  another page).
+ **JSON instead**: `'as' => 'json'`.
+ **The files themselves**: `'as' => 'zip'` ([Downloads, ZIP](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Download#zip)).

<br/>

## Common mistakes

+ **A model query** (`Author::query()->select('id', 'name')->get()`): models carry every property, so every column of
  `Author` is in the file, empty. Return arrays (`Database::table(...)` or `->arrays()`).
+ **A capability that is not read-only**: `Route::download` refuses it.

## Related pages

+ [Downloads: `Route::download`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Download): every option
+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries)
+ [`Route::download()` in the API reference](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-download)
