# Downloads: `Route::download`

`Route::download($route, $capability, $options)` answers a `GET` with the result of a **read-only** capability as a
file: a CSV, a JSON file, or the files it lists in one ZIP. The capability stays plain data, so the same list also
reaches REST, the JS client and agents. Since 2.19.0; `columns` since 2.19.5, ZIP since 2.22.0.

```php
Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);
Route::download('/books.json', 'list_books', ['as' => 'json']);   // a page of list_books, ?page=2 for the next
Route::download('/photos.zip', 'list_photo_files', ['as' => 'zip', 'root' => 'public/uploads/photos']);
```

<br/>

## With Claude

```text
"Let people export the authors as CSV from a link on the authors page."
```

**Check** before you say OK:
+ the route names a read-only capability;
+ the export returns arrays with the columns it means to show (not models: they carry every property);
+ a ZIP's `root` is the folder that holds the files, relative to the project.

A step-by-step CSV export: [How to export a list as CSV](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Export-a-List-as-CSV).

<br/>

## <a name="options"></a>Options

| Option | Default | What it does |
|---|---|---|
| `as` | `'csv'` | `'csv'`, `'json'` (pretty-printed) or `'zip'` (the files the capability lists, 2.22.0); anything else stops with an error when `web.php` runs |
| `root` | `'public/uploads'` | for `zip`: the folder of the project the files are read from; a file outside it is never sent. A `root` with `..` stops with an error when `web.php` runs |
| `columns` | the first row's keys | the header row and its order, written even when there is no row: `['title', 'year']` (2.19.5) |
| `filename` | `<capability>.<as>` | a name, or `function ($result, $input) { return 'books-' . date('Y-m-d') . '.csv'; }` |
| `bom` | `false` | `true` starts the CSV with a UTF-8 BOM (Excel) |
| `formulas` | `false` | `true` keeps cells that start with `=`, `+`, `-`, `@` as they are |
| `signIn` | `'/login'` | where a visitor goes when the capability needs someone |

<br/>

## <a name="inputs"></a>Inputs and refusals

+ The capability must be read-only: opening a link must not change data.
+ It gets the query string and the route variables (`/books.csv?author_id=2`) as the person who opens the link; the
  route variables win.
+ Refusals are those of `Route::page`: a visitor goes to sign in, `forbidden` is `403`, nothing found is `404`. An input
  to fix (`?author_id=abc`) answers `422` with the capability's error as JSON, as REST does (2.19.5; before, `404`).
+ The answer carries `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`.

<br/>

## <a name="csv"></a>CSV

+ The header row comes from `columns`, else from the first row's keys (then an empty result is an empty file).
+ Values are quoted where needed (RFC 4180, lines end with CRLF), UTF-8.
+ A text cell that starts with `=`, `+`, `-`, `@` or a tab gets a `'` first, so a spreadsheet does not run it as a
  formula (`formulas` turns that off).
+ Arrays in a cell are written as JSON, booleans as `true` / `false`. A related row (`with('author')`) is one cell, in
  JSON.
+ Each row of the list is a line. A `paginate()` page exports that page only: return `get()` to export every row; a
  single record gives one line.
+ **The columns are the keys of the first row.** A model carries every one of its properties:
  `Book::query()->select('id', 'title')->get()` exports `id`, `title` and every other column of `Book`, empty. Return
  arrays for a narrow export: `Database::table('books')->select('id', 'title')->get()` or
  `Book::query()->select('id', 'title')->arrays()->get()`.

<br/>

## <a name="zip"></a>Many files as one ZIP (2.22.0)

The capability lists the files; `Route::download` puts them in one archive and sends it.

```php
#[Capability(name: 'list_photo_files', description: 'The files of the photos, oldest first', scope: 'photos:read', level: 1, readOnly: true)]
class ListPhotoFiles extends Service
{
    public function handle(array $input, Context $context)
    {
        return array_map(fn ($photo) => [
            'file' => $photo['image'],                                        // relative to 'root'
            'name' => 'pictures/' . date('Y-m-d_H-i-s', strtotime($photo['created_at'])) . '.jpg',   // in the archive
        ], Photo::query()->arrays()->select('image', 'created_at')->orderBy('created_at')->get());
    }
}
```

```php
Route::download('/photos.zip', 'list_photo_files', ['as' => 'zip', 'root' => 'public/uploads/photos',
    'filename' => fn ($files, $input) => 'photos-' . date('Y-m-d') . '.zip']);
```

+ **Each item** is a path (the file keeps its own name) or `['file' => path, 'name' => 'folder/name.jpg']`. Paths are
  relative to `root`; a `paginate()` page gives its rows.
+ **Only files under `root` go in.** A path that leaves it (`../../.env`), a folder, or a missing file is left out
  without an error. Names are cleaned (no `..`, no leading `/`), and a name used twice becomes `name (2).jpg`.
+ **No file at all answers `404`.** Hide the link when there is nothing to download, or throw
  `ServiceError::notFound('No photo yet')` in the capability.
+ Images, video, audio and archives are stored as they are; other files are compressed. The archive is written to a
  temporary file, sent with its `Content-Length`, then removed.
+ It needs PHP's **zip** extension: Caligrafy's Docker images and Caligrafy Cloud have it.
+ In a test, `Download::archive($files, ['root' => ...])` builds the same archive (`['path', 'count', 'names']`;
  remove `path` afterwards).

<br/>

## Reference

+ [`Route::download()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-download)
+ [`Caligrafy\Download`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#download):
  [`archive()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#download-archive),
  [`csv()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#download-csv),
  [`rows()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#download-rows)

<br/>

## Common mistakes

+ **A download of a capability that writes**: `Route::download` refuses it when the link is opened.
+ **A model query for a narrow export**: every column of the model is in the file. Return arrays.
+ **A ZIP whose files live outside `root`**: they are left out, and an empty list answers `404`. Give the folder that
  holds them, relative to the project (`public/uploads/photos`), and paths relative to it.
+ **A web-only capability that streams its own ZIP** (before 2.22.0): return the list and use `'as' => 'zip'`.

## Related pages

+ [How to export a list as CSV](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Export-a-List-as-CSV)
+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries): `select()`, `arrays()`, `paginate()`
+ [Pages: `Route::page`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Page): the same refusals
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#download)
