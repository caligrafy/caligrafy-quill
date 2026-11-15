# Everything else: `Route::action` and web-only capabilities

`Route::page` shows a view and `Route::form` calls a capability and redirects. A few requests are neither: a sign-in
between two sites, a page that chooses between a view and a redirect, a gate, a page that creates a payment. Since
2.21.0 they are capabilities too, declared `webOnly: true` and served by one line, `Route::action($route, $capability,
$options)`. This is what replaces the controller of a new application.

```php
Route::action('/authors/{author_id}/books', 'open_author_books', ['back' => '/books']);
```

<br/>

## With Claude

```text
"Add GET /authors/{author_id}/books: when the author has exactly one book, go straight to that book's page;
otherwise show the list."
```

**Check** before you say OK:
+ `webOnly: true` is on a capability that needs the session, signs someone in, or chooses between a page and a
  redirect; everything else stays a plain capability, so REST and agents can reach it;
+ the web-only capability calls the plain ones with `$this->capability()`;
+ no controller and no closure was written.

<br/>

## By hand

1. **The capability.** Run `docker compose exec dev-box php caligrafer.php service Bookshelf/OpenAuthorBooks` and make
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

2. **The route.** In `web.php`:

   ```php
       Route::action('/authors/{author_id}/books', 'open_author_books', ['back' => '/books']);
   ```

3. **Try it.**

   ```bash
   curl -s -i http://localhost:8080/authors/3/books | grep -iE "^HTTP|^Location"
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/authors/2/books
   curl -s -i http://localhost:8080/authors/abc/books | grep -iE "^HTTP|^Location"
   ```

   They print `302` to `/books/5` (one book), `200` (a list of two), and `302` to `/books` (the `back` option), where
   `flash('error')` says "Some of the inputs are invalid".
   `docker compose exec dev-box php caligrafer.php call open_author_books author_id=3` prints
   `{"error":{"code":"not_found","message":"There is no capability 'open_author_books'"}}`: only the route reaches it.

<br/>

## <a name="options"></a>Options

```php
Route::action('/unlock/{name}', 'unlock_shelf', ['method' => 'POST']);
Route::action('/shelf/report', 'shelf_report', ['level' => 1, 'comeBack' => true]);
```

| Option | Default | What it does |
|---|---|---|
| `method` | `'GET'` | `'POST'`, `'PUT'`, `'DELETE'`, or a list (`['GET', 'POST']`) |
| `signedIn`, `level` | none | as in `Route::page`: visitors go to `/login`, people below the level to `/notAuthorized` |
| `comeBack` | `false` | with `signedIn` or `level`, or when the capability needs someone: visitors go to `/login?return=<this page>` |
| `visitorsOnly` | none | a path: people already signed in are sent there |
| `back` | the `Referer` of this site, else `/` | where invalid input or a conflict goes, with the route's `{placeholders}` |
| `error` | the capability's message | your message for `flash('error')` |
| `signIn` | `'/login'` | where a visitor goes when the capability needs someone (`comeBack` replaces it) |

<br/>

## <a name="answers"></a>What it answers: `Caligrafy\Web`

| The capability returns | The browser gets |
|---|---|
| `Web::view('Bookshelf/book', $data, $status = 200)` | a Pug view, with `$authorized` and `$user` as on `Route::page` (`$data` wins). A request that asks for JSON gets the data as JSON |
| `Web::redirect('/books', $status = 302)` | a redirect: a path of this site or a full `http(s)` address |
| `Web::json($data, $status = 200)` | JSON, the data as it is, with no `{"data": ...}` around it |
| `Web::text($body, $status = 200)` | the text as it is |
| any of them `->header('Cache-Control', 'no-store')` | one more response header |
| plain data (an array, a model) | JSON, as with `Web::json` |

**When it refuses.** A capability that answers a refusal itself returns a `Web`. One that throws a `ServiceError` gets
the answers of `Route::form`:

| It throws | A browser | A JSON request |
|---|---|---|
| `invalid` or `conflict` | back (`back`, the `Referer`, or `/`) with `errors()`, `old()`, `flash('error')` | `422` or `409` and the REST error |
| `unauthenticated` | the sign-in page (`comeBack`: and back here) | `401` |
| `forbidden` | `403` `[ERROR 1006]` | `403` |
| `not_found` or anything else | `404` `[ERROR 1005]` | `404` |

**What it receives.** The inputs its `$rules` declare, from the form, the JSON body or the query string, and the route
variables, which win (`/books/3?id=9` gives `id = 3`, 2.21.2). Its files are saved and given as URLs, as for
`Route::form`. It runs as the person who sent the request, with its `public`, `scope` and `level` checks, its
validation and its audit log, like any capability.

<br/>

## <a name="webonly"></a>What a web-only capability may do

**Only capabilities touch models**: a web-only capability is a capability. What sets it apart is what it may do besides:

| A plain capability | A web-only capability |
|---|---|
| returns data, or refuses | may also return a view, a redirect, JSON or text |
| never reads the session or the request: `$context` says who calls | may read `session()`, `request()`, `$_SERVER`, `$_COOKIE` |
| never signs anyone in | may sign people in and out |
| on a page, only `readOnly` | a `GET` may change data |
| reached by REST, MCP, the JS client, the command line, `Route::page`, `Route::form`, `$this->call()` | reached by **`Route::action` only** |

Everywhere else it answers `not_found` "There is no capability", and it is left out of `GET /capabilities`, MCP,
`php caligrafer.php capabilities` and `php caligrafer.php client`. So keep the work several channels share in plain
capabilities, and call them from the web-only one:

```php
$books = $this->capability('list_books', array('author_id' => 2), $context);   // as the same person, with its rules
```

<br/>

## <a name="signin"></a>Signing someone in from an action

+ **A page that leaves or reloads**: `Caligrafy\AuthRoutes::signIn($user, null, 'partner')` gives a new session id, a
  new CSRF token, and records how the session signed in (the third argument, 2.21.3, read by
  `AuthRoutes::signedInBy()`).
+ **A page that stays open and keeps sending JSON**: `session_regenerate_id(true); Auth::authorize($user);
  AuthRoutes::rememberSignIn('face');`. The page keeps its token.
+ `$context` was built before the sign-in and does not change: to call a capability as the person just signed in,
  build a new one with `Context::fromRequest(request(), 'web')`.

A complete sign-in through another site is in
[Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#betweensites).

<br/>

## <a name="csrf"></a>CSRF and JSON

+ A form's `POST`, `PUT` or `DELETE` needs its `_token` (`=csrf()`): `403` `[ERROR 3000]` without it.
+ A JSON call (`Accept: application/json`), `GET` included, needs the page's `X-CSRF-Token` header (`apiHeaders()` of
  the Vue apps) or, with `Auth::activateAPI();` in `web.php`, a token that grants everything (`--scopes "*"`), signed
  in or not: `401` `[ERROR 1004]` without it (2.21.1). A scoped token is refused: programs use the capability routes.
+ A plain `GET` opened by a link has no token, and can be triggered by a link on another site: keep a `GET` that
  changes data to what is harmless when that happens, and use `'method' => 'POST'` with a form for the rest.

**Tests.** `$this->action('open_author_books', ['author_id' => 3], $user)` calls it as `Route::action` does and returns
what it answered; `->describe()` gives `type`, `target`, `data`, `status` and `headers`. `$this->call()` answers
`not_found`, as every other channel does. See [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App#helpers).

<br/>

## Reference

+ [`Route::action()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-action)
+ [`Caligrafy\Action`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#action) and
  [`Caligrafy\Web`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#web)
+ [`AuthRoutes::signIn()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes#authroutes-signin),
  [`AuthRoutes::rememberSignIn()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes#authroutes-remembersignin)
+ [`Context::fromRequest()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#context-fromrequest)

<br/>

## Common mistakes

+ **`Route::action` on a capability without `webOnly: true`**, or `webOnly: true` behind `Route::page`, `Route::form` or
  REST: the first stops with an error that names it, the others answer "There is no capability".
+ **A web-only capability for work other channels need**: agents and programs cannot reach it. Put the work in a plain
  capability and call it with `$this->capability()`.
+ **A JSON call without `X-CSRF-Token`**: `401` `[ERROR 1004]`, even for a `GET` and for a visitor. Send `apiHeaders()`.
+ **`AuthRoutes::signIn()` on a page that stays open**: it gives the session a new CSRF token, and the page's next JSON
  call answers `401`. Use `session_regenerate_id(true); Auth::authorize($user);` there.
+ **Calling a capability as the person just signed in with the old `$context`**: it still says who called before. Build
  a new one with `Context::fromRequest(request(), 'web')`.

## Related pages

+ [How to move a controller route to a declared route](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Move-a-Controller-Route-to-a-Declared-Route)
+ [Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request): what a web-only capability reads from the request
+ [Authentication: a sign-in through another site](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#betweensites)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action)
+ [Release notes 2.21.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.21.0)
