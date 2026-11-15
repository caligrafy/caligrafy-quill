# How to move a controller route to a declared route

Since 2.21.0 every request can be declared in one line of `web.php`, and a new application needs no controller.
Existing controllers keep working; this guide moves one action, `BookController@byAuthor`, to a web-only capability
served by `Route::action`, then removes the controller. The same steps move any action.

Before you start: the Bookshelf tables, the `Book` model, `list_books`, `get_book` and the views of
[Routing, steps 1 to 5](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes).

<br/>

## With Claude

```text
"Move application/controllers/BookController.php to declared routes."
```

Claude writes one capability per action, swaps the routes, removes the controller, and runs `php caligrafer.php test`.
Ask the `caligrafy-reviewer` agent to review it: it flags controllers where a declared route fits.

<br/>

## 1. Pick the route type

Most actions are not web-only. Choose the narrowest line that says what the action does:

| The action | Declare it with |
|---|---|
| shows a view, with data | [`Route::page`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Page) and read-only capabilities |
| saves a form, then redirects | [`Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Form) |
| sign-in, sign-out, sign-up | [`Route::auth`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Auth) |
| receives another service's events | [`Route::webhook`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Webhook) |
| sends a file | [`Route::download`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Download) |
| always sends to the same address | [`Route::redirect`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Redirect) |
| answers JSON to programs | [`Route::capability`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Capability-and-REST) |
| chooses between a view and a redirect, reads the session, signs someone in | [`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action) |

The action below chooses between a redirect and a view: a `Route::action`.

<br/>

## 2. The controller you have

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

<br/>

## 3. Write the capability

Run `docker compose exec dev-box php caligrafer.php service Bookshelf/OpenAuthorBooks`, and make
`application/services/Bookshelf/OpenAuthorBooks.php` read:

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

**What moves, and how:**

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

<br/>

## 4. Swap the route

In `application/config/routing/web.php`, replace the `Route::get` line with:

```php
    Route::action('/authors/{author_id}/books', 'open_author_books', ['back' => '/books']);
```

<br/>

## 5. Remove the controller and try it

Delete `application/controllers/BookController.php`, then run:

```bash
curl -s -i http://localhost:8080/authors/3/books | grep -iE "^HTTP|^Location"
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/authors/2/books
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/authors/9/books
curl -s -i http://localhost:8080/authors/abc/books | grep -iE "^HTTP|^Location"
```

They print `302` with `Location: http://localhost:8080/books/5` (one book), `200` (two books), `200` (the list with
"No books yet.": nobody has the id 9) and `302` to `/books` (`author_id` must be an integer: the `back` option, with
the message in `flash('error')`).

**Check**

- [ ] `/authors/3/books` redirects to `/books/5`, and `/authors/abc/books` to `/books`, not `500`.
- [ ] `php caligrafer.php call open_author_books author_id=3` answers `not_found`: only the route reaches it.
- [ ] `grep -n BookController application/config/routing/web.php` prints nothing.
- [ ] `php caligrafer.php test` passes; a test of the action uses `$this->action(...)`, not `$this->call(...)`.

<br/>

## Common mistakes

+ **Everything made web-only**: a web-only capability is reached by `Route::action` only. Work that REST, the JS client
  or agents also need stays a plain capability, called with `$this->capability()`.
+ **A JSON call without `X-CSRF-Token`**: `401` `[ERROR 1004]`, as on the controller route it replaces.
+ **Moving the examples that ship in `application/controllers/core/`** (`AdminController`, `AuthController`,
  `AIController`, `ClientController`, `WatsonController`): they work; leave them, or replace their lines with
  `Route::auth`, `Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']])` and `Route::bots()`.

## Related pages

+ [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers): the controllers you may still have
+ [Everything else: `Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#controller)
+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App#helpers)
