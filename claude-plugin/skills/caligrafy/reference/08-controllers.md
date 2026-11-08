# 8. Controllers

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

# Controllers

A controller is a PHP class whose actions answer routes. It is how Caligrafy applications answered custom requests
before 2.21.0, and **a new application does not need one**: every request can be declared in one line of `web.php`
([Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes)), the rules of a feature live in a capability
(`application/services/`), and what a page or a form cannot say is a web-only capability served by `Route::action`.

Existing controllers keep working, with nothing to change. This page says what replaces each use of a controller, shows
how to move one, and documents the controllers you may still have.

## What replaces a controller

| You need | Write | Where |
| --- | --- | --- |
| A page, with or without data | `Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']]);` | [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#pageroutes) |
| A page only signed-in people or administrators open | `Route::page(..., ['signedIn' => true])` or `['level' => 1]` | the same |
| A page that sends people back after signing in | `Route::page(..., ['signedIn' => true, 'comeBack' => true])` | the same |
| A page for visitors only | `Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);` | the same |
| A page that reads the query string | `Route::page(..., ['query' => ['sort' => 'title']])` | the same |
| A Vue, bot or machine learning app | `Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]);` | [Routes for client apps](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.2-Routes) |
| A form that creates, changes or deletes | `Route::form('/books', 'create_book', ['success' => '/books/{id}']);` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form) |
| A sign-up with a confirmation field, a box to tick, that signs in | `Route::form` with `same`, `accepted`, `signsIn` | the same |
| A form that leaves for a payment page | `Route::form` with `'success' => fn ($r) => $r['url']` | [Payments](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment) |
| Password reset | `Route::page` and `Route::form` on two capabilities | [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#passwordreset) |
| Sign-in, sign-out, sign-up | `Route::auth(['home' => '/books']);` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#auth) |
| Events Stripe or another service posts | `Route::webhook('/stripe/events', 'record_event', ['verify' => 'stripe']);` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#webhook) |
| A CSV or JSON file to download | `Route::download('/books.csv', 'list_books');` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#download) |
| An address that moved | `Route::redirect('/old/books/{id}', '/books/{id}', 301);` | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#redirect) |
| A JSON API | `Route::capability('GET', '/api/books', 'list_books');` | [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#capabilityroutes) |
| Several capabilities with conditions, a choice between a redirect and a view | `Route::action` and a web-only capability | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action) |
| The session, a sign-in of your own (face, another site), a gate, a payment page, custom headers | the same | [the same](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#webonly) |

`AdminController`, `AuthController`, `AIController`, `ClientController` and `WatsonController` ship in
`application/controllers/core/`, and the routes of a new project's `web.php` still name them. They are examples that work:
leave them, or replace their lines with the routes above.

## With Claude

```text
"Move application/controllers/BookController.php to declared routes."
"Add GET /authors/{author_id}/books: when the author has exactly one book, go straight to that book's page;
otherwise show the list."
```

For a new request, the `caligrafy` skill never writes a controller: Claude writes a capability in
`application/services/<Folder>/`, `webOnly: true` only when the request needs it, and one line of
`application/config/routing/web.php`. To move an existing controller, it writes one capability per action, swaps the
routes, removes the controller, and runs `php caligrafer.php test`. Ask the `caligrafy-reviewer` agent to review it: it
flags new controllers where a declared route fits.

**Check**

- [ ] `application/controllers/` got no new file.
- [ ] A capability with `webOnly: true` is one that returns a view or a redirect, reads the session, or signs someone
      in. The rest stays a plain capability, which REST and agents can reach.
- [ ] The web-only capability calls other capabilities with `$this->capability()`.
- [ ] `/authors/3/books` redirects and `/authors/2/books` shows the list.

## By hand: move a controller to a declared route

Before you start: the Bookshelf tables, the `Book` model, `list_books`, `get_book` and the views of
[Routing, steps 1 to 5](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes). You have this controller, and want it gone:

```php
<?php
// application/controllers/BookController.php

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
        if ($books['total'] == 1) {
            redirect('/books/' . $books['data'][0]['id']);
            return null;
        }
        return view('Bookshelf/books', array('books' => $books));
    }
}
```

```php
Route::get('/authors/{author_id}/books', 'BookController@byAuthor');
```

1. **Write the capability.** Run
   `docker compose exec dev-box php caligrafer.php service Bookshelf/OpenAuthorBooks`, and replace the content of
   `application/services/Bookshelf/OpenAuthorBooks.php` with:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Service;
   use Caligrafy\Web;

   // open_author_books: one book goes straight to its page, otherwise the list
   #[Capability(
       name: 'open_author_books',
       description: 'Opens the books of an author: the book itself when there is one, otherwise the list.',
       readOnly: true,
       public: true,
       webOnly: true
   )]
   class OpenAuthorBooks extends Service
   {
       public $rules = array('author_id' => 'required|integer');

       public function handle(array $input, Context $context)
       {
           $books = $this->capability('list_books', array('author_id' => $input['author_id']), $context);
           if ($books['total'] == 1) {
               return Web::redirect('/books/' . $books['data'][0]['id']);
           }
           return Web::view('Bookshelf/books', array('books' => $books));
       }
   }
   ```

   Each action becomes one capability. `$this->call()` becomes `$this->capability($name, $input, $context)`, `view()`
   `Web::view()`, `redirect()` `Web::redirect()`, `api()` `Web::json()`, and `$this->request->fetch('author_id')` an input
   declared in `$rules`.

2. **Swap the route.** In `application/config/routing/web.php`, replace the
   `Route::get` line with:

   ```php
       Route::action('/authors/{author_id}/books', 'open_author_books', ['back' => '/books']);
   ```

3. **Remove the controller.** Delete `application/controllers/BookController.php`.

4. **Try it.** Run:

   ```bash
   curl -s -i http://localhost:8080/authors/3/books | grep -iE "^HTTP|^Location"
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/authors/2/books
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/authors/9/books
   curl -s -i http://localhost:8080/authors/abc/books | grep -iE "^HTTP|^Location"
   ```

   They print `HTTP/1.1 302 Found` with `Location: http://localhost:8080/books/5` (Italo Calvino has one book, *Invisible
   Cities*), `200` (Octavia E. Butler's two books), `200` (the list with "No books yet.": nobody has the id 9) and
   `302` with `Location: http://localhost:8080/books` (`author_id` must be an integer: the `back` option, with the message
   in `flash('error')`).

   A JSON call needs the page's `X-CSRF-Token` header or a token that grants `*`, as on a controller route:

   ```bash
   curl -s -H "Accept: application/json" http://localhost:8080/authors/3/books
   ```

   prints `{"error":{"code":1004,"message":"Access Denied: send the page's CSRF token in an X-CSRF-Token header (pages,
   with the session cookie) or Authorization: Bearer <personal access token> (programs and agents)"}}` with `401`.

**Check**

- [ ] `/authors/3/books` redirects to `/books/5`, and `/authors/abc/books` to `/books`, not `500`.
- [ ] `php caligrafer.php call open_author_books author_id=3` answers `not_found`: only the route reaches it.
- [ ] `application/controllers/` no longer has the class, and `grep -n BookController application/config/routing/web.php`
      prints nothing.

**What moves, and what does not**

| In the controller | In the web-only capability |
| --- | --- |
| `$this->call('x', $input)` | `$this->capability('x', $input, $context)` |
| `$this->request->fetch('id')`, `$this->request->parameters()` | an input declared in `$rules` |
| `view('X', $data)`, with `'authorized' => authorized()` by hand | `Web::view('X', $data)`: `$authorized` and `$user` are given |
| `redirect('/x'); return;` | `return Web::redirect('/x');` |
| `http_response_code(422); return api($data);` | `return Web::json($data, 422);` |
| `header('X: y');` | `Web::text('...')->header('X', 'y')`, on any `Web` |
| `guard('permissions', 1);` | `'level' => 1` on the route, or `level: 1` on the capability |
| `try { ... } catch (ServiceError $e)` | nothing, unless you answer it yourself: `Route::action` sends it back like a form |
| `$_SESSION`, `authorized()`, `user()` | the same, a web-only capability may read them |
| a form and `=csrf()` | the same: a form's `POST` needs its token |
| a file: `uploadFile($this->request->files('x'))` | a `file` rule in `$rules`: `handle()` gets the URL |

## In a controller you keep: `$this->call()`

```php
$book = $this->call('create_book');                                  // inputs: the route variables and the request's fields
$books = $this->call('list_books', array('author_id' => 2));         // inputs you give
```

`$this->call($name, $input = null)` runs the capability as the person who sent the request (the signed-in user, a
token, or a visitor), with its validation, its permissions and its audit, exactly as REST and AI agents call it. It
returns plain data (arrays: `$book['id']`) or throws `Caligrafy\ServiceError`. It answers `not_found` for a capability
declared `webOnly: true`.

| `$e->kind` | `$e->status()` | When |
| --- | --- | --- |
| `invalid` | `422` | an input breaks a rule; `$e->fields` is `['title' => 'The Title field is required', ...]` |
| `unauthenticated` | `401` | the capability needs someone and nobody is signed in |
| `forbidden` | `403` | the person lacks the level, or the token the scope |
| `not_found` | `404` | the capability found nothing |
| `conflict` | `409` | a duplicate |

Without `$input`, the inputs are the route variables and the request's parameters (the form fields, the JSON body or,
for a `GET`, the query string); only the inputs the capability declares in `$rules` reach it. `$this->call()` does not
take uploaded files: a form with a file input posts to `Route::form`, `Route::action` or a capability route. The
framework's own user administration works this way: `AdminController` calls `list_users`, `get_user`, `update_user` and `delete_user`
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

Before capabilities, a controller read and wrote its model itself. These methods still work, for existing projects; a
new feature is a capability with `Route::form` or `Route::capability` instead, so the web, REST and agents follow the
same rules.

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

+ **A new controller**: every request can be declared. A form post is `Route::form`, in one line, with `errors()`,
  `old()` and `flash()`; the rest is `Route::action`.
+ **Code after `redirect()`** keeps running: `return` after it.
+ **A JSON call from a page without `X-CSRF-Token`** answers `401` `[ERROR 1004]`: send `apiHeaders()` (Vue apps)
  or the token in the header.
+ **A scoped token on a controller route** is refused: controller routes, like `Route::action`, need a token that grants
  `*`. Give programs capability routes with scoped tokens instead.
+ **A short controller name that collides with an example controller** (`AuthController`): yours wins when it is
  declared; otherwise the example one answers.
+ **Overriding a base method** (`find`, `save`, `view`, `call`...) breaks the controller.

## Related pages

+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): what replaces controllers
+ [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#controllerroute): controller and closure routes
+ [Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request): `$this->request`
+ [Build once: services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service)
+ [Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)

>[Next Section: Learn about Helpers, Forms & REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API)
