# Pages: `Route::page`

`Route::page($route, $view, $options)` answers a `GET` with a Pug view, through the framework's page controller. The
page gets its data from **read-only capabilities**, never from models: the same capabilities REST, the JS client and
AI agents call. Since 2.5.0; `visitorsOnly`, `comeBack` and `query` since 2.20.0, `query` defaults since 2.21.0.

```php
Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book'], 'notFound' => 'Bookshelf/notfound']);
```

<br/>

## With Claude

```text
/caligrafy-quill:new-capability get_book books:read read-only public: one book with its author, shown at /books/{id}
```

Claude writes the capability in `application/services/<Folder>/`, the `Route::page` line in
`application/config/routing/web.php` and the Pug view in `application/views/`.

**Check** before you say OK:
+ the page's `data` names read-only capabilities only;
+ fixed addresses (`/books/new`) are declared before the ones with a variable (`/books/{id}`);
+ `/books/abc` and `/books/999` show the `notFound` view with a `404`, not a `500`.

<br/>

## By hand

Before you start: the Bookshelf tables, the `Book` model and the capabilities `list_books` and `get_book` of
[Routing, steps 1 to 3](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes).

1. **Declare the pages.** In `application/config/routing/web.php`, before `Route::run();`:

   ```php
       Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books']]);
       Route::page('/books/new', 'Bookshelf/new', ['signedIn' => true, 'comeBack' => true]);
       Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book'], 'notFound' => 'Bookshelf/notfound']);
   ```

   `/books/new` comes before `/books/{id}`: the first route that matches wins.

2. **Write the view.** `application/views/Bookshelf/books.pug` reads `$books`, the result of `list_books` (a page of
   `paginate()`: `data` and `total`), as plain arrays:

   ```pug
   extends ../default/master/site

   block content
     section.cq-hero.cq-hero--page
       h1.cq-display Books

     section.cq-band.cq-band--tight
       .cq-inner
         if $books['total'] == 0
           p.cq-empty-state No books yet.
         else
           ul.cq-rows
             each $book in $books['data']
               li.cq-row
                 a(href=$home . 'books/' . $book['id'])= $book['title']
                 span= ' (' . $book['author']['name'] . ', ' . $book['published_year'] . ')'
   ```

   Pug in Caligrafy is PHP: strings are joined with `.`, never `+`.

3. **Open it.** `http://localhost:8080/books` lists the books, `/books/3` shows one, `/books/abc` shows the
   `notFound` view with a `404` (`get_book`'s `integer` rule refuses `abc`).

<br/>

## <a name="options"></a>Options

| Option | Default | What it does |
| --- | --- | --- |
| `data` | none | `name => 'capability'`: calls each capability as the person who opens the page and gives the result to the view as `$name`. Its inputs are the query string and the route variables; the route variables win (`/books/3?id=9` gives `id = 3`). `name => ['capability', ['author_id' => 2]]` gives fixed inputs instead. Only read-only capabilities |
| `signedIn` | `false` | `true`: visitors are sent to `/login` |
| `level` | `0` | people whose level (the `permissions` column of `users`) is lower are sent to `/notAuthorized`, visitors to `/login` |
| `comeBack` | `false` | `true`, with `signedIn` or `level`, or when a `data` capability needs someone: visitors go to `/login?return=<this page, with its query string>`, and `Route::auth` brings them back after signing in (2.20.0) |
| `visitorsOnly` | none | a path: people already signed in are sent there (a sign-up or landing page) (2.20.0) |
| `query` | none | what the view gets of the query string: `['show']` gives `$show`, `''` when absent (2.20.0); `['app' => 'index']` gives `$app`, `'index'` when absent (2.21.0). A route variable of the same name wins |
| `with` | none | fixed values for the view: `['with' => ['title' => 'Books']]` |
| `notFound` | none | a view shown with a `404` when a `data` capability refuses (not found, or inputs it refuses). It gets the route variables, the `with` values, `$message`, `$authorized` and `$user` |

`signedIn`, `level` and `visitorsOnly` need `Auth::authentication('User', 'users');` in `web.php`; without it, opening
the page stops with an error that says so.

More examples, each one line:

```php
Route::page('/', 'default/index');
Route::page('/admin/books', 'Bookshelf/admin', ['level' => 1, 'data' => ['books' => 'list_books']]);
Route::page('/about', 'default/about', ['with' => ['title' => 'About the Bookshelf']]);
Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);
Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books'], 'query' => ['sort' => 'title']]);
Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]);   // a Vue, bot or ML app
```

<br/>

## <a name="view"></a>What the view receives

+ the route variables (`$id`), the names listed in `query`, the `with` values;
+ the `data` results, as plain arrays (`$book['title']`, `$book['author']['name']`);
+ `$authorized` (true when someone is signed in) and `$user` (`id`, `username`, `permissions`, or `null`).

The rest of the query string stays out of the view: a `data` capability gets it as inputs. `authorized` and `user` are
reserved names: a `data`, `with` or `query` entry with one of them is an error.

A request with `Accept: application/json` gets the view's data as JSON instead of the page.

<br/>

## <a name="refusals"></a>When a capability refuses the page's data

| The capability | The answer |
| --- | --- |
| needs someone signed in, nobody is | a redirect to `/login` (`comeBack`: with `?return=`), with the authentication module; else `403` |
| refuses the person (`forbidden`) | `403` `[ERROR 1006] Forbidden` |
| finds nothing, or refuses the inputs | the `notFound` view with `404`, else `404` `[ERROR 1005] Page Not Found` |

<br/>

## Reference

+ [`Route::page()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-page) and
  [`Route::pages()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-pages) (the pages declared so far,
  2.24.0: Snap shows a capability through the page made for it)
+ [`PageController::render()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Pages#pagecontroller-render): the
  controller every `Route::page` goes through

<br/>

## Common mistakes

+ **A page whose data capability writes**: `Route::page` refuses it (opening a page must not change data). Use
  [`Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Form) for writes, or
  [`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action) when opening an address really has to
  change something.
+ **`/books/{id}` before `/books/new`**: `/books/new` shows the book `new` (a 404). Declare fixed addresses first.
+ **`signedIn` without `Auth::authentication(...)`** above the page: every visit stops with an error.
+ **Reading `$_GET` in the view**: only the names of `query` reach it. Add the name to `query`, or make it an input of
  the `data` capability.
+ **A `data` or `with` entry named `user` or `authorized`**: those names are reserved.

## Related pages

+ [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing): how a request finds its route, route variables
+ [Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views): Pug pages and the page components
+ [Forms: `Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Form)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): every route type in one page
+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries): what the `data` capabilities read with
