# 2. Routing

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - Unmatched URLs do not 404: they run the FIRST route registered in web.php (gotchas G4).

# Routing

A route connects an address (`GET /books/3`) to what answers it. Every route of a Caligrafy project is one line of
`application/config/routing/web.php`. A route names a capability (a feature written once, in `application/services/`)
and says how the web reaches it: a page that shows its data (`Route::page`), a form that calls it (`Route::form`), a file
to download (`Route::download`), a JSON API (`Route::capability`), or anything else a browser asks (`Route::action`).
Since 2.21.0 every request can be declared this way, so a new application has no controller. This page covers how routes
match, pages, redirects and capability routes. Forms, sign-in, webhooks, downloads and `Route::action` have their own
page: [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes).

## With Claude

Ask for the feature, not the route: the `caligrafy-quill` plugin writes the capability and the routes together.

```text
/caligrafy-quill:new-resource Book title:string published_year:int author:fk
```

or, for one feature:

```text
/caligrafy-quill:new-capability get_book books:read read-only
"Show each book on its own page at /books/{id}, with a 'Book not found' page when the id does not exist."
```

What Claude writes: the capabilities in `application/services/<Folder>/`, the routes in
`application/config/routing/web.php` (`Route::page` for pages, `Route::form` for forms, `Route::capability` for the
REST API, inside the `try` block and before `Route::run();`) and the Pug views in `application/views/`.

**Check** before you say OK:

- [ ] Every new route is in the `try` block of `web.php`, before `Route::run();`.
- [ ] Pages are `Route::page`, forms are `Route::form`, a custom request is `Route::action`: no controller and no closure
      that reads or writes data.
- [ ] A fixed address such as `/books/new` comes before `/books/{id}` (the first route that matches wins).
- [ ] Each page's `data` names a read-only capability (`readOnly: true`).
- [ ] `curl -i` of each new address answers the status you expect (200, 302, 404).

## By hand

You need a project from the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) with its
database. The commands below run in the project folder; with the Docker dev server, run them as
`docker compose exec dev-box php caligrafer.php ...`. The addresses use `http://localhost:8080`, the Docker dev server.

1. **Create the Bookshelf tables.** Write `application/models/Bookshelf/create_db.sql` (it can run more than once: it
   never drops anything):

   ```sql
   /* Bookshelf: the first version of its tables, and sample books.
    * Run it with: docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
    * Safe to run again: CREATE TABLE IF NOT EXISTS skips a table that exists, INSERT IGNORE a row whose id exists.
    */

   CREATE TABLE IF NOT EXISTS authors (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT,
       name VARCHAR(150) NOT NULL,
       bio TEXT NULL,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       PRIMARY KEY (id)
   ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

   CREATE TABLE IF NOT EXISTS books (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT,
       title VARCHAR(200) NOT NULL,
       published_year SMALLINT UNSIGNED NOT NULL,
       summary TEXT NULL,
       author_id INT UNSIGNED NOT NULL,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       PRIMARY KEY (id),
       KEY idx_books_author_id (author_id),
       CONSTRAINT fk_books_author_id FOREIGN KEY (author_id) REFERENCES authors (id)
           ON UPDATE CASCADE ON DELETE RESTRICT
   ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

   -- Sample data, with fixed ids so that book 3 is Kindred everywhere
   INSERT IGNORE INTO authors (id, name, bio) VALUES
       (1, 'Ursula K. Le Guin', 'American author of science fiction and fantasy, known for the Earthsea books.'),
       (2, 'Octavia E. Butler', 'American science fiction author, the first to receive a MacArthur fellowship.'),
       (3, 'Italo Calvino', 'Italian writer of playful, inventive novels and short stories.');

   INSERT IGNORE INTO books (id, title, published_year, summary, author_id) VALUES
       (1, 'A Wizard of Earthsea', 1968, 'A gifted boy learns magic and must face the shadow he set loose.', 1),
       (2, 'The Left Hand of Darkness', 1969, 'An envoy visits a planet whose people have no fixed sex.', 1),
       (3, 'Kindred', 1979, 'A young woman is pulled back in time to a plantation in Maryland.', 2),
       (4, 'Parable of the Sower', 1993, 'In a collapsing California, a teenager starts a new belief system.', 2),
       (5, 'Invisible Cities', 1972, 'Marco Polo describes imaginary cities to the emperor Kublai Khan.', 3);
   ```

   Run `docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql`. It prints
   ` application/models/Bookshelf/create_db.sql run on caligrafy`. A later change to these tables, once they hold
   data, is a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).

2. **Describe an author and a book.** Write `application/models/Bookshelf/Author.php`:

   ```php
   <?php

   use Caligrafy\Model;

   // One Author = one row of the authors table (Author::query() reads authors: the class name in lower case, plus s)
   class Author extends Model {

       // one public property per column of the table
       public $id;
       public $name;
       public $bio;
       public $created_at;
       public $modified_at;
   }
   ```

   and `application/models/Bookshelf/Book.php`:

   ```php
   <?php

   use Caligrafy\Model;

   // One Book = one row of the books table. Book::with('author') adds the row of authors whose id is author_id
   class Book extends Model {

       // one public property per column of the table
       public $id;
       public $title;
       public $published_year;
       public $summary;
       public $author_id;
       public $created_at;
       public $modified_at;
   }
   ```

3. **Write the two capabilities the pages read.** Run:

   ```bash
   docker compose exec dev-box php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only
   docker compose exec dev-box php caligrafer.php service Bookshelf/GetBook --scope books:read --read-only
   ```

   Each prints ` Created application/services/Bookshelf/<Name>.php: capability '<name>' (scope books:read)`. Replace
   the content of `application/services/Bookshelf/ListBooks.php` with:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Service;

   // list_books: the catalog, sorted by title, 20 books per page, each with its author. Anyone may read it
   #[Capability(
       name: 'list_books',
       description: 'Lists the books of the Bookshelf catalog, sorted by title, 20 per page, each with its author (id, name, bio). Give author_id to list only the books of one author, and page for the next pages. Returns data (the books), total, page, per_page and pages.',
       scope: 'books:read',
       readOnly: true,
       public: true
   )]
   class ListBooks extends Service
   {
       public $rules = array('author_id' => 'integer', 'page' => 'integer');
       public $descriptions = array(
           'author_id' => 'Optional: only the books of this author',
           'page' => 'Optional: the page, 1 by default (20 books per page)'
       );

       public function handle(array $input, Context $context)
       {
           // two queries for the page (the count, then the books) and one for all their authors
           $query = Book::with('author')->orderBy('title');
           if (!empty($input['author_id'])) {
               $query->where('author_id', $input['author_id']);   // the value is bound, never pasted into the SQL
           }
           return $query->paginate(20, $input['page'] ?? 1);
       }
   }
   ```

   and the content of `application/services/Bookshelf/GetBook.php` with:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Service;
   use Caligrafy\ServiceError;

   // get_book: one book with its author, or "not found"
   #[Capability(
       name: 'get_book',
       description: 'Returns one book of the catalog with its author (id, name, bio), by the id of the book.',
       scope: 'books:read',
       readOnly: true,
       public: true
   )]
   class GetBook extends Service
   {
       public $rules = array('id' => 'required|integer');
       public $descriptions = array('id' => 'The id of the book');

       public function handle(array $input, Context $context)
       {
           $book = Book::with('author')->find($input['id']);
           if (!$book) {
               throw ServiceError::notFound("There is no book #{$input['id']}");
           }
           return $book;
       }
   }
   ```

   Run `docker compose exec dev-box php caligrafer.php capabilities`: the list shows `get_book` and `list_books`, both
   `public` and `read`.

4. **Declare the routes.** In `application/config/routing/web.php`, inside the `try` block, after
   `Route::page('/', 'default/index');` and before `Route::run();`, write:

   ```php
       // the Bookshelf pages: their data comes from read-only capabilities
       Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books']]);
       Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book'], 'notFound' => 'Bookshelf/notfound']);
       Route::redirect('/catalog', '/books');
   ```

5. **Write the views.** `application/views/Bookshelf/books.pug` (the [Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)
   page adds the form that creates a book):

   ```pug
   extends ../default/master/site

   block title
     | Bookshelf

   block content
     section.cq-hero.cq-hero--page
       h1.cq-display Books

     section.cq-band.cq-band--tight
       .cq-inner.cq-inner--narrow
         if $books['total'] == 0
           p.cq-empty-state No books yet.
         else
           ul.cq-rows
             each $b in $books['data']
               li.cq-row
                 .cq-row-main
                   p.cq-row-title
                     a(href=$home . 'books/' . $b['id'])= $b['title']
                   p.cq-row-meta= $b['author']['name'] . ', ' . $b['published_year']
   ```

   `application/views/Bookshelf/book.pug`:

   ```pug
   extends ../default/master/site

   block title
     = $book['title'] . ' · Bookshelf'

   block content
     section.cq-hero.cq-hero--page
       h1.cq-display= $book['title']
       p.cq-lead= 'by ' . $book['author']['name'] . ', ' . $book['published_year']
   ```

   `application/views/Bookshelf/notfound.pug`:

   ```pug
   extends ../default/master/site

   block title
     | Not found · Bookshelf

   block content
     section.cq-hero
       h1.cq-display Not found
       p.cq-lead= $message
       .cq-cta
         a.btn.btn-primary(href=$home . 'books') All books
   ```

6. **Try every route.** Run:

   ```bash
   curl -s http://localhost:8080/helloworld
   ```

   It prints `Hello World` (the template's own closure route). Open `http://localhost:8080/books`: the five books,
   *A Wizard of Earthsea* first, each with its author and year. Open `http://localhost:8080/books/3`: the title
   "Kindred" and "by Octavia E. Butler, 1979". Then:

   ```bash
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/books/999
   curl -s -i http://localhost:8080/catalog | grep -i "^location"
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/nothing
   ```

   They print `404` (the "Not found" page with "There is no book #999"), `Location: http://localhost:8080/books`
   and `404`.

**Check**

- [ ] `/books` lists the books and `/books/3` shows one.
- [ ] `/books/999` and `/books/abc` show "Not found" with a 404 status.
- [ ] `/catalog` answers `302` with `Location: .../books`.
- [ ] An address no route declares answers `404`.

## How a request finds its route

`Route::run()`, the last line of the `try` block, compares the request with the routes in the order they are declared:

1. The HTTP method must be the same. `HEAD` is answered as `GET`. A form sends `PUT` or `DELETE` with a hidden
   `_method` field ([HTML form methods](#htmlformmethods)).
2. The path must have the same number of segments, and each fixed segment must be identical. A `{name}` segment
   matches any one segment. Leading and trailing slashes do not count, nor does the query string.
3. The **first** route that matches wins: declare `/books/new` before `/books/{id}`, or `/books/{id}` answers
   `/books/new` with the id `new`.
4. Nothing matches: `404` (`[ERROR 1005] Page Not Found`, or `{"error":{"code":1005,"message":"Page Not Found"}}` to a
   JSON request). The address exists with other methods: `405` with an `Allow` header
   (`Invalid Http Request Method: this address answers GET`).

A `POST`, `PUT` or `DELETE` that is not JSON is checked for its CSRF token before any route runs: without it the answer
is `403` `[ERROR 3000] Security Error - Cross Site Forgery`, whatever the address. `Route::webhook` addresses (checked by
their signature) and `Route::mcp` addresses (2.19.1: a post without a personal access token answers `401` with
`WWW-Authenticate`) skip it.

## Route variables

`{name}` in a route captures one segment of the address:

```php
Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']]);
```

+ In a `Route::page`, the variables become the inputs of the `data` capabilities (`get_book` gets `id`) and variables
  of the view (`$id`).
+ In `Route::form`, `Route::download`, `Route::capability` and `Route::redirect`, they become inputs or fill the
  `{name}` of the target, the same way.
+ In `Route::action`, they become inputs too. Everywhere the route variable wins over a query string value of the same
  name: `/books/3?id=9` gives `id = 3` (2.21.2; in 2.21.0 and 2.21.1, `Route::action` and `Route::capability` gave `9`).
+ In a controller you keep, read them with `$this->request->fetch('id')`
  ([Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request)). Since 2.21.7, on a `GET`, a query string value of
  the same name never replaces it.

A variable is always a string, and the route does not check it: `/books/abc` reaches `get_book`, whose `integer` rule
refuses it.

## Page routes: `Route::page`

`Route::page($route, $view, $options)` answers a `GET` with a Pug view, through the framework's page controller.

```php
Route::page('/', 'default/index');
Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books']]);
Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book'], 'notFound' => 'Bookshelf/notfound']);
Route::page('/books/new', 'Bookshelf/new', ['signedIn' => true, 'comeBack' => true]);
Route::page('/admin/books', 'Bookshelf/admin', ['level' => 1, 'data' => ['books' => 'list_books']]);
Route::page('/about', 'default/about', ['with' => ['title' => 'About the Bookshelf']]);
Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);
Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books'], 'query' => ['sort' => 'title']]);
```

| Option | Default | What it does |
| --- | --- | --- |
| `data` | none | `name => 'capability'`: calls each capability as the person who opens the page and gives the result to the view as `$name`. Its inputs are the query string and the route variables; the route variables win (`/books/3?id=9` gives `id = 3`). `name => ['capability', ['author_id' => 2]]` gives fixed inputs instead. Only read-only capabilities: a capability that changes data, or does not exist, stops the page with an error that names it |
| `signedIn` | `false` | `true`: visitors are sent to `/login` |
| `comeBack` | `false` | `true`, with `signedIn` or `level`, or when a `data` capability needs someone: visitors go to `/login?return=<this page, with its query string>`, and `Route::auth` brings them back after signing in (2.20.0) |
| `visitorsOnly` | none | a path: people already signed in are sent there (a sign-up or landing page) (2.20.0) |
| `query` | none | what the view gets of the query string: `'query' => ['show']` gives `$show`, a string, `''` when absent (2.20.0); `'query' => ['app' => 'index']` gives `$app`, `'index'` when absent; the two can be mixed (2.21.0). A route variable of the same name wins |
| `level` | `0` | people whose level (the `permissions` column of `users`) is lower are sent to `/notAuthorized`, visitors to `/login` |
| `with` | none | fixed values for the view: `['with' => ['title' => 'Books']]` |
| `notFound` | none | a view shown with a `404` when a `data` capability refuses (not found, or inputs it refuses). It gets the route variables, the `with` values, `$message`, `$authorized` and `$user` |

`signedIn`, `level` and `visitorsOnly` need `Auth::authentication('User', 'users');` in `web.php`; without it, opening
the page stops with an error that says so.

**What the view receives**: the route variables, the names listed in `query`, the `with` values, the `data` results
(plain arrays: `$book['title']`), `$authorized` (true when someone is signed in) and `$user` (`id`, `username`,
`permissions`, or `null`). The rest of the query string stays out of the view: a `data` capability gets it as inputs.
`authorized` and `user` are reserved names: a `data`, `with` or `query` entry with one of them is an error.

**When a capability refuses the page's data**:

| The capability | The answer |
| --- | --- |
| needs someone signed in, nobody is | a redirect to `/login` (`comeBack`: with `?return=`), with the authentication module; else `403` |
| refuses the person (`forbidden`) | `403` `[ERROR 1006] Forbidden` |
| finds nothing, or refuses the inputs | the `notFound` view with `404`, else `404` `[ERROR 1005] Page Not Found` |

A request with `Accept: application/json` gets the view's data as JSON instead of the page.

## Forms, sign-in, webhooks, downloads, redirects and everything else

One line each, calling a capability:

```php
Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);   // a form's POST
Route::auth(['home' => '/books']);                                                          // /login, /logout, /register
Route::webhook('/stripe/events', 'record_payment', ['verify' => 'stripe']);                  // events another service posts
Route::download('/books.csv', 'list_books', ['filename' => 'books.csv']);                   // a read-only capability as a file
Route::download('/photos.zip', 'list_photo_files', ['as' => 'zip', 'root' => 'public/uploads/photos']);   // its files as one ZIP (2.22.0)
Route::redirect('/old/books/{id}', '/books/{id}', 301);                                     // an address that moved
Route::action('/authors/{author_id}/books', 'open_author_books');                           // what a page or a form cannot say
```

Every option, with a complete example of each:
[Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes).

## Capability routes: REST, the JS client and agents

```php
Route::capability('GET', '/api/books', 'list_books');          // one route, one capability, JSON
Route::capability('GET', '/api/books/{id}', 'get_book');       // {id} becomes the input "id"
Route::capability('POST', '/api/books', 'create_book');
Route::capabilities('/capabilities');                          // GET lists, POST /capabilities/{name} calls: the JS client of the Vue apps
Route::mcp('/mcp');                                            // AI agents: the capabilities their token allows, as MCP tools
```

They check who calls (a personal access token, or the signed-in person with the `X-CSRF-Token` header for writes),
validate the inputs and answer JSON: `{"data": ...}` with `200` (`201` for a `POST` that changes data), or
`{"error": {"code", "message", "fields"}}` with `401`, `403`, `404`, `409` or `422`.

```bash
curl -s -H "Accept: application/json" http://localhost:8080/api/books/2
```

prints `{"data":{"id":2,"title":"The Left Hand of Darkness","published_year":1969,"summary":"...","author_id":1,"created_at":"...","modified_at":"...","author":{"id":1,"name":"Ursula K. Le Guin",...}}}`
(with `Route::capability('GET', '/api/books/{id}', 'get_book');` in `web.php`).
More: [REST](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#rest)
and [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#restapi).

## Reference

All are static methods of `Caligrafy\Route` (`use Caligrafy\Route;` at the top of `web.php`).

| Method | Answers | What it does |
| --- | --- | --- |
| `page($route, $view, $options = [])` | GET | a Pug view with data from read-only capabilities ([above](#pageroutes)) |
| `form($route, $capability, $options = [])` | POST (or PUT, DELETE) | a form's post calls a capability, then redirects |
| `auth($options = [])` | several | `/login`, `/logout`, `/register`, `/notAuthorized` (needs `Auth::authentication(...)` before it) |
| `webhook($route, $capability, $options)` | POST | events another service posts, signature checked, no CSRF token |
| `download($route, $capability, $options = [])` | GET | a read-only capability's result as a CSV or JSON file |
| `redirect($from, $to, $status = 302, $options = [])` | GET | sends the visitor to `$to` (`301`, `302`, `303`, `307` or `308`); `['keepQuery' => true]` takes the query string along |
| `action($route, $capability, $options = [])` | GET, or `method` | a web-only capability answers: a view, a redirect, JSON or text ([Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action)) |
| `capability($method, $route, $name)` | the method given | one capability as JSON (`GET`, `POST`, `PUT` or `DELETE`) |
| `capabilities($prefix = '/capabilities')` | GET, POST | the listing and `POST {prefix}/{name}` for the JS client |
| `mcp($route = '/mcp')` | POST (GET, DELETE: 405) | the MCP endpoint for AI agents |
| `notifications()` | GET, POST | the confirm and unsubscribe pages of notification emails |
| `bots()` | POST, DELETE | the data routes of the Watson bot apps (`/__bots__/...`) |
| `get`, `post`, `put`, `delete($route, $method)` | that method | a closure, `'Controller'` or `'Controller@action'`, for what existing projects declare that way ([Other ways](#controllerroute)) |
| `run()` | | matches the request and runs its route: the last line of the `try` block |
| `routes()` | | the routes declared so far: `[['method' => 'GET', 'route' => 'books/{id}'], ...]` (tests) |
| `handler($method, $route)` | | the closure or `'Controller@action'` of a declared route (tests) |
| `reset()` | | forgets every route (tests) |
| `Route::$currentRoute`, `Route::$currentBind` | | the matched pattern (`books/{id}`) and its variables (`currentRoute()`, `currentBind()`) |

Errors of the router (each one also answers JSON to a JSON request):

| Status | Error | When |
| --- | --- | --- |
| `404` | `1005` Page Not Found | no route matches, or a page's data is not found |
| `405` | `1000` Invalid Http Request Method | the address exists with other methods (`Allow` header) |
| `403` | `3000` Cross Site Forgery | a form post without the session's `_token` |
| `403` | `1006` Forbidden | a page's capability refuses the person |
| `404` | `1001` Invalid Controller or Action | a `'Controller@action'` route whose class or method does not exist |
| `401` | `1004` Access Denied | a JSON request to `Route::action` or a controller route without `X-CSRF-Token` or a token that grants `*` |

## Other ways: closures and controller routes

`Route::get`, `post`, `put` and `delete` take a closure or a controller. A new request does not need them: the routes
above cover every case, with `Route::action` for what is neither a page nor a form. They stay for existing projects and
for the routes a new project's `web.php` ships: see
[Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#when).

```php
Route::get('/helloworld', function () { echo 'Hello World'; });          // a closure
Route::get('/authors/{author_id}/books', 'BookController@byAuthor');      // a controller and its action
Route::get('/reports/{id}', 'Shop\ReportController@show');                // a namespaced controller
Route::get('/books', 'BookController');                                   // no action: index()
```

+ `'Name@action'` finds a declared class `Name` first (global or fully qualified, a leading `\` is accepted), then
  the example controllers of `Caligrafy\Controllers` (`'ClientController'`); a class or action that does not exist
  answers `404` `[ERROR 1001]`. More: [Namespaced controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#namespacedcontrollers).
+ A **controller route** refuses a JSON request (`Accept: application/json`) unless it comes from a page of the app
  (the session cookie and the `X-CSRF-Token` header) or, with `Auth::activateAPI();` in `web.php`, from a program
  with a personal access token that grants `*` (`401` `[ERROR 1004] Access Denied: <why>` otherwise).
+ A **closure route** has no such check, and a JSON request skips the form's CSRF check. Never read or write data in
  a closure: use `Route::page`, `Route::form`, `Route::action` or a capability route.

## HTML form methods

HTML forms only send `GET` and `POST`. A `POST` with a hidden `_method` field set to `PUT` or `DELETE` is routed as
that method (method spoofing). In a Pug form, `=methodField('PUT')` writes the field:

```pug
form(method='POST', action=$home . 'books/' . $book['id'])
  =csrf()
  =methodField('PUT')
  button.btn.btn-primary(type='submit') Save
```

It reaches `Route::put('/books/{id}', ...)` or `Route::form('/books/{id}', 'update_book', ['method' => 'PUT'])`.
`_method` is read from the form fields or the query string, never from a JSON body: a script that sends JSON uses the
real method (`fetch(url, {method: 'PUT', ...})`).

## Common mistakes

+ **A route after `Route::run();`** is never declared. Every route goes before it, in the `try` block.
+ **`/books/{id}` before `/books/new`**: `/books/new` shows the book `new` (a 404). Declare fixed addresses first.
+ **A page whose data capability writes**: `Route::page` refuses it (opening a page must not change data). Use
  `Route::form` for writes, or `Route::action` when opening an address really has to change something.
+ **Data work in a closure route**: it has no permission rules and no CSRF check for JSON calls. Write a capability.
+ **`redirect()` in a closure without `return`**: `redirect()` only sends a `Location` header; code after it still
  runs. `Route::redirect` has no such problem.
+ **Before 2.16.0** an unknown address ran the first route of the file instead of answering 404.

## Related pages

+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): forms, sign-in, webhooks, downloads, redirects, `Route::action`
+ [Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request): what a web-only capability or a controller reads from the request
+ [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers): existing ones, and how to move one
+ [Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views): Pug pages and `errors()`, `old()`, `flash()`
+ [Build once: services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries): the query builder the capabilities use

>[Next Section: Learn about Requests](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request)
