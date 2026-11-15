A **web-only capability** is a capability declared `webOnly: true`. It is for a request that belongs to the browser: a
sign-in between two sites, a page that chooses between a view and a redirect, a gate, a page that creates a payment.
It keeps everything a capability has (its checks, its validation, its audit) and may do what a plain capability never
does: answer with a view or a redirect, read the session, sign people in. The price: `Route::action` is the only way to
it. Since 2.21.0.

This page is about the capability. The route that serves it, its options and how it answers are on
[`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action).

<br/>

## With Claude

```text
/caligrafy-quill:new-capability open_author_books web-only public: open the books of an author, the book itself when there is only one
```

Claude writes the capability with `webOnly: true`, keeps the shared work in plain capabilities that it calls with
`$this->capability()`, and adds the `Route::action` line to `web.php`.

**Check** before you say OK:
+ `php caligrafer.php capabilities` does not list it (web-only capabilities are left out);
+ the plain capabilities it calls are listed, so agents and programs can still do the work;
+ a test calls it with `$this->action()`.

<br/>

## By hand

`php caligrafer.php service Bookshelf/OpenAuthorBooks` writes the class; the command has no flag for web-only, so add
`webOnly: true` to the attribute:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\Web;

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

and serve it in `application/config/routing/web.php`, before `Route::run();`:

```php
Route::action('/authors/{author_id}/books', 'open_author_books', ['back' => '/books']);
```

<br/>

## <a name="may"></a>What it may do

The rule of the framework still holds: **only capabilities touch models**. A web-only capability is a capability; what
sets it apart is what it may do besides:

| A plain capability | A web-only capability |
|---|---|
| returns data, or refuses | may also return a view, a redirect, JSON or text (`Caligrafy\Web`) |
| never reads the session or the request: `$context` says who calls | may read `session()`, `request()`, `$_SERVER`, `$_COOKIE` |
| never signs anyone in | may sign people in and out |
| on a page, only `readOnly` | a `GET` may change data |
| reached by REST, MCP, the JS client, the command line, `Route::page`, `Route::form`, `$this->call()` | reached by **`Route::action` only** |

What it answers:

| It returns | The browser gets |
|---|---|
| `Web::view('Bookshelf/book', $data, $status = 200)` | a Pug view, with `$authorized` and `$user` as on `Route::page` |
| `Web::redirect('/books', $status = 302)` | a redirect: a path of this site or a full `http(s)` address |
| `Web::json($data, $status = 200)` | JSON, the data as it is |
| `Web::text($body, $status = 200)` | the text as it is |
| any of them `->header('Cache-Control', 'no-store')` | one more response header |
| plain data | JSON |

<br/>

## <a name="elsewhere"></a>Everywhere else: "There is no capability"

`Capabilities::call()` answers `not_found` `There is no capability '<name>'` to any context that does not come from
`Route::action` (`$context->viaAction`). So REST, MCP, the JS client, `php caligrafer.php call`, `Route::page`,
`Route::form`, `Route::download` and a controller's `$this->call()` cannot reach it, and it is left out of
`GET /capabilities`, MCP `tools/list`, `php caligrafer.php capabilities` and the generated JS client.

Keep the work several channels share in plain capabilities, and call them from the web-only one, as the same person:

```php
$books = $this->capability('list_books', array('author_id' => 2), $context);   // its checks, validation and audit
```

<br/>

## <a name="signin"></a>Signing someone in

+ For a page that leaves or reloads: `Caligrafy\AuthRoutes::signIn($user, null, 'partner')`: a new session id, a new CSRF
  token and how it signed in (the third argument, 2.21.3, read by `AuthRoutes::signedInBy()`).
+ For a page that stays open and keeps sending JSON: `session_regenerate_id(true); Auth::authorize($user);
  AuthRoutes::rememberSignIn('face');`: the page keeps its token.
+ `$context` was built before the sign-in and does not change: to call a capability as the person just signed in, build a
  new one with `Context::fromRequest(request(), 'web')`.

A whole example, a sign-in through another site, is on
[Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#betweensites).

<br/>

## <a name="test"></a>Testing

`$this->action('open_author_books', ['author_id' => 3], $user)` calls it as `Route::action` does and returns what it
answered; `->describe()` gives `type`, `target`, `data`, `status` and `headers`. `$this->call()` answers `not_found`, as
every other channel does ([Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App#helpers)).

Reference: [`Caligrafy\Web`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#web),
[`Caligrafy\Action`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#action),
[`AuthRoutes::signIn()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes#authroutes-signin).

<br/>

## Common mistakes

+ **`webOnly: true` on a feature agents or programs need** (G143): they get "There is no capability". Keep the work in a
  plain capability and let the web-only one call it with `$this->capability()`.
+ **Reading the session in a plain capability** (G52): only a web-only capability may; the others read `$context`.
+ **Snap's own `snap_ask` and `snap_run` in a test** (G158): they are web-only, so call them with `$this->action()`.

<br/>

## Related pages

+ [`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action): the route, its options, CSRF and JSON
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#webonly)
+ [The capability attribute](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Attribute) · [The context](https://github.com/caligrafy/caligrafy-quill/wiki/The-Context)
