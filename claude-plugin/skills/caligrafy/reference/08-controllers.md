# 8. Controllers

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

# Controllers

A controller is a PHP class whose actions answer routes. In 2.19 it is the exception: the rules of a feature live in a
capability (`application/services/`), and one line of `web.php` serves it to the web (`Route::page`, `Route::form`,
`Route::auth`, `Route::webhook`, `Route::download`, `Route::redirect`, `Route::capability`). Write a controller only
for **custom HTTP**: an answer that none of those lines gives. It stays thin: it calls capabilities with
`$this->call()` and decides what to send back.

## When a controller is still the right tool

| You need | Write | Not a controller because |
| --- | --- | --- |
| A page, with or without data | `Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']]);` | the page controller does it |
| A page only signed-in people or administrators open | `Route::page(..., ['signedIn' => true])` or `['level' => 1]` | the same |
| A form that creates, changes or deletes, back to the form with its errors | `Route::form('/books', 'create_book', ['success' => '/books/{id}']);` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form) |
| Sign-in, sign-out, sign-up | `Route::auth(['home' => '/books']);` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#auth) |
| Events Stripe or another service posts | `Route::webhook('/stripe/events', 'record_event', ['verify' => 'stripe']);` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#webhook) |
| A CSV or JSON file to download | `Route::download('/books.csv', 'list_books');` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#download) |
| An address that moved | `Route::redirect('/old/books/{id}', '/books/{id}', 301);` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#redirect) |
| A JSON API | `Route::capability('GET', '/api/books', 'list_books');` | [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#capabilityroutes) |
| An action that calls several capabilities with conditions, or chooses between a redirect and a view | **a controller** | no route expresses the condition |
| Custom headers, streaming, a response that is neither a page, a redirect, JSON nor a file | **a controller** | |
| Password reset, face sign-in, an OAuth callback | **a controller** | not in `Route::auth` |

A controller that calls `new Book()`, `associate()`, `save()` or `all()` keeps its rules for the web only: the REST
API and AI agents would follow other rules. Those methods are under [Other ways](#controllercontext).

## With Claude

```text
"Add GET /authors/{author_id}/books: when the author has exactly one book, go straight to that book's page;
otherwise show the list."
```

The `caligrafy` skill writes a controller only when no declarative route fits; here the action chooses between a
redirect and a view. Claude writes `application/controllers/BookController.php` (an action that calls `list_books`
with `$this->call()`) and one `Route::get` line in `application/config/routing/web.php`. Ask the `caligrafy-reviewer`
agent to review it: it flags controllers where a declarative route fits.

**Check**

- [ ] The action calls capabilities with `$this->call()` and never touches a model (`new Book`, `associate()`, `save()`).
- [ ] It catches `ServiceError` and answers with its status (`$e->status()`).
- [ ] After `redirect()`, it returns.
- [ ] No form post, sign-in, export or webhook went into a controller.

## By hand

Before you start: the Bookshelf tables, the `Book` model, `list_books`, `get_book` and the views of
[Routing, steps 1 to 5](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes).

1. **Write the controller.** `application/controllers/BookController.php`:

   ```php
   <?php

   use Caligrafy\Controller;
   use Caligrafy\ServiceError;

   class BookController extends Controller
   {
       // GET /authors/{author_id}/books: one book goes straight to its page, otherwise the list
       public function byAuthor()
       {
           try {
               $books = $this->call('list_books', array('author_id' => $this->request->fetch('author_id')));
           } catch (ServiceError $e) {
               http_response_code($e->status());
               return view('Bookshelf/notfound', array('message' => $e->getMessage()));
           }
           // list_books answers a page: data (the books), total, page, per_page, pages
           if ($books['total'] == 1) {
               redirect('/books/' . $books['data'][0]['id']);
               return null;
           }
           return view('Bookshelf/books', array('books' => $books));
       }
   }
   ```

   Every class under `application/` (except `config/` and `views/`) is found without a `require`.

2. **Route it.** In `application/config/routing/web.php`, before `Route::run();`:

   ```php
       Route::get('/authors/{author_id}/books', 'BookController@byAuthor');
   ```

3. **Try it.** Run:

   ```bash
   curl -s -i http://localhost:8080/authors/3/books | grep -iE "^HTTP|^Location"
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/authors/9/books
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/authors/abc/books
   ```

   They print `HTTP/1.1 302 Found` with `Location: http://localhost:8080/books/5` (Italo Calvino has one book,
   *Invisible Cities*), `200` (the list page with "No books yet.": nobody has the id 9) and `422` (the "Not found"
   page with "Some of the inputs are invalid": `author_id` must be an integer). `/authors/2/books` shows the list
   page with Octavia E. Butler's two books.

   A JSON call to a controller route needs the page's `X-CSRF-Token` header or a personal access token that grants
   `*`:

   ```bash
   curl -s -H "Accept: application/json" http://localhost:8080/authors/3/books
   ```

   prints `{"error":{"code":1004,"message":"Access Denied: send the page's CSRF token in an X-CSRF-Token header (pages,
   with the session cookie) or Authorization: Bearer <personal access token> (programs and agents)"}}` with `401`.

**Check**

- [ ] `/authors/3/books` redirects to `/books/5`.
- [ ] `/authors/abc/books` answers `422`, not `500`.
- [ ] The controller has no `use` of a model and no SQL.

## Calling a capability: `$this->call()`

```php
$book = $this->call('create_book');                                  // inputs: the route variables and the request's fields
$books = $this->call('list_books', array('author_id' => 2));         // inputs you give
```

`$this->call($name, $input = null)` runs the capability as the person who sent the request (the signed-in user, a
token, or a visitor), with its validation, its permissions and its audit, exactly as REST and AI agents call it. It
returns plain data (arrays: `$book['id']`) or throws `Caligrafy\ServiceError`:

| `$e->kind` | `$e->status()` | When |
| --- | --- | --- |
| `invalid` | `422` | an input breaks a rule; `$e->fields` is `['title' => 'The Title field is required', ...]` |
| `unauthenticated` | `401` | the capability needs someone and nobody is signed in |
| `forbidden` | `403` | the person lacks the level, or the token the scope |
| `not_found` | `404` | the capability found nothing |
| `conflict` | `409` | a duplicate |

Without `$input`, the inputs are the route variables and the request's parameters (the form fields, the JSON body or,
for a `GET`, the query string); only the inputs the capability declares in `$rules` reach it. `$this->call()` does not
take uploaded files: a form with a file input posts to `Route::form` or a capability route. The framework's own user
administration works this way: `AdminController` calls `list_users`, `get_user`, `update_user` and `delete_user`
([Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#usercapabilities)).

## Namespaced controllers

A controller can live in a namespace. Declare it at the top of the file and use the full name in the route:

```php
<?php
// application/controllers/Shop/ReportController.php
namespace Shop;

use Caligrafy\Controller;

class ReportController extends Controller
{
    public function show()
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Report ' . $this->request->fetch('id');
    }
}
```

```php
Route::get('/reports/{id}', 'Shop\ReportController@show');
```

`curl -s -i http://localhost:8080/reports/7` answers `200` with `Content-Type: text/plain; charset=utf-8` and
`Report 7`.

How the router finds the class of `'Name@action'`:

1. a declared class named `Name` (global or fully qualified, with or without a leading `\`) is used: your own
   `AIController` wins over the example one;
2. otherwise, a short name (no backslash) falls back to the example controllers of `Caligrafy\Controllers`
   (`application/controllers/core/`: `AIController`, `AdminController`, `AuthController`, `ClientController`,
   `WatsonController`), so routes such as `'ClientController'` keep working;
3. otherwise the request answers `404` `[ERROR 1001] Invalid Controller or Action`, as for a missing action.

In a PHP single-quoted string `'Shop\ReportController'` and `'Shop\\ReportController'` are the same. The routes of a
new project use the full names (`'Caligrafy\\Controllers\\ClientController'`). Two classes with the same fully
qualified name in `application/` are an error.

## Reference

A controller extends `Caligrafy\Controller`. Without an action in the route (`'BookController'`), `index()` runs; the
base `index()` does nothing. Do not give your own methods the names below.

| Property | What it is |
| --- | --- |
| `$this->request` | the [Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request) |
| `$this->validator` | a `Caligrafy\Validator` (GUMP with the framework's rules) |
| `$this->model` | the model set by `associate()` (an empty `Model` otherwise) |
| `$this->connection` | the database connection (PDO), or `null` |
| `$this->payment` | the Stripe module after `activatePayment()`, else `null` |
| `$this->loader` | `Caligrafy\Loader`: `library($name)`, `helper($name)` include a file of `framework/librairies` or `framework/helpers` |

| Method | What it does |
| --- | --- |
| `call($name, $input = null)` | runs a capability as the person who sent the request ([above](#callcapability)) |
| `view($viewName, $viewData = [])` | renders a Pug view; a request that asks for JSON gets `$viewData` as JSON |
| `api($viewData = [])` | answers `$viewData` as JSON |
| `check($data, $rules = [], $filters = [])`, `validate($data, $rules)`, `filter($data, $filters)` | GUMP validation and filtering ([Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation#validationandfiltering)) |
| `activatePayment()` | sets `$this->payment` to a new `Caligrafy\Payment` |
| `associate($modelName, $table)`, `all()`, `find()`, `save()`, `delete()`, `search()`, `table()` | the classic model methods ([Other ways](#controllercontext)) |

`redirect('/path')` only sends a `Location` header: `return` right after it. Set a status with
`http_response_code($code)` before `view()` or `api()`.

## Other ways: controllers that read and write models

Before capabilities, a controller read and wrote its model itself. These methods still work in 2.19, for existing
projects; a new feature is a capability with `Route::form` or `Route::capability` instead, so the web, REST and agents
follow the same rules.

```php
<?php

use Caligrafy\Controller;

class LegacyBookController extends Controller
{
    public function show()
    {
        $this->associate('Book', 'books');   // the model class (or a Model object) and its table
        return api($this->find());           // the id comes from the {id} route variable
    }
}
```

| Method | What it does |
| --- | --- |
| `associate($modelName, $table)` | sets `$this->model`; call it again to work on another model in the same controller |
| `all($args = [])` | every row; `$args` adds `condition`, `order`, `limit` (raw SQL) and `bindings` |
| `find($id = null)` | one row; without `$id`, the `{id}` route variable |
| `save($inputs, $id = null)` | updates the row `$id` (or the `{id}` route variable), else creates one |
| `delete($id = null)` | deletes the row `$id` (or the `{id}` route variable) |
| `search($scope = null, $keywords = null)` | rows whose columns `$scope` (comma separated) match `$keywords` (bound; `%` and `_` are wildcards) |
| `table()` | the table of `$this->model` |

+ **`{id}` routes**: `find()`, `save()` and `delete()` take the `{id}` route variable when no id is given. On
  `POST /books/{id}/reviews`, `$this->save($review)` **updates** the review whose id is the book's id. Name the
  variable `{book_id}` or pass the id.
+ **`all($args)` clauses are SQL**: never put input in them; use `?` and `bindings`.
+ The replacement: a capability that uses the [query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries),
  served with `Route::page`, `Route::form` and `Route::capability`.

## Common mistakes

+ **A controller for a form post**: `Route::form` does it in one line, with `errors()`, `old()` and `flash()`.
+ **Code after `redirect()`** keeps running: `return` after it.
+ **A JSON call from a page without `X-CSRF-Token`** answers `401` `[ERROR 1004]`: send `apiHeaders()` (Vue apps)
  or the token in the header.
+ **A scoped token on a controller route** is refused: controller routes need a token that grants `*`. Give programs
  capability routes with scoped tokens instead.
+ **A short controller name that collides with an example controller** (`AuthController`): yours wins when it is
  declared; otherwise the example one answers.
+ **Overriding a base method** (`find`, `save`, `view`, `call`...) breaks the controller.

## Related pages

+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): what replaces most controllers
+ [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#controllerroute): controller and closure routes
+ [Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request): `$this->request`
+ [Build once: services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service)
+ [Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)

>[Next Section: Learn about Helpers, Forms & REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API)
