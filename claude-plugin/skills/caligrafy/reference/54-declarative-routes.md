# Declarative routes: forms, sign-in, webhooks, downloads

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **declarative route** is one line of `web.php` that connects a web need to a capability: a form, the sign-in pages,
the events another service posts, a file to download. No controller: the capability holds the rules, the route says
where to go next. Since 2.19.0.

| You need | One line |
|---|---|
| A page, with its data | `Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);` |
| A form that creates, changes or deletes | `Route::form('/books', 'create_book', ['success' => '/books/{id}']);` |
| Sign-in, sign-out, sign-up | `Route::auth(['home' => '/books']);` |
| Events Stripe (or another service) posts | `Route::webhook('/stripe/events', 'record_payment', ['verify' => 'stripe']);` |
| A CSV or JSON export | `Route::download('/books.csv', 'list_books');` |
| An address that moved | `Route::redirect('/', '/books');` |
| A file in a form | the `file` rule of the capability: `'cover' => 'required\|file\|extension,png;jpg\|max_size,2mb'` |

## Forms: `Route::form`

The page shows the form (`Route::page`), the form posts to `Route::form`, which calls the capability with the form's
fields as the person who sent it.

```php
// application/config/routing/web.php
Route::page('/books/new', 'Books/new', ['signedIn' => true]);
Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);

Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
```

**`application/views/Books/new.pug`**
```pug
extends ../default/master/site

block content
  section.cq-hero.cq-hero--page
    h1.cq-display New book

  section.cq-band.cq-band--tight
    .cq-inner.cq-inner--narrow
      if flash('error')
        .cq-alert.cq-alert--error(role='alert')= flash('error')
      form(method='POST', action="{$home}books")
        =csrf()
        .cq-field
          label(for='title') Title
          input#title.form-control(type='text', name='title', value=old('title'))
          if errors('title')
            p.cq-field-error= errors('title')
        .cq-field
          label(for='author') Author
          input#author.form-control(type='text', name='author', value=old('author'))
          if errors('author')
            p.cq-field-error= errors('author')
        button.btn.btn-primary(type='submit') Add book
```

What happens:

| The capability | The person goes to | The next page has |
|---|---|---|
| succeeds | `success` (its `{id}` filled from the result, then the route), else back to the form | `flash('success')` |
| refuses the input (`invalid`) or finds a duplicate (`conflict`) | back to the form (`back`, else the page they came from) | `errors('title')`, `old('title')`, `flash('error')` |
| needs someone signed in | the sign-in page (`signIn`, default `/login`) | |
| refuses them (`forbidden`) or finds nothing | the error page (403 or 404) | |

+ **The CSRF token** of the form (`=csrf()`) is checked, as for every form.
+ **One request only.** The errors, the old values and the messages are for the next page: point `success` and
  `back` at the page that shows them. Passwords, card numbers and tokens are never kept for `old()`.
+ **PUT and DELETE** come from a form with a hidden `_method` field: `=methodField('PUT')` in the form.
+ Options: `method` (`POST`, `PUT`, `DELETE`, or a list), `success` (a path, or `function ($result) { return ...; }`),
  `flash` (a message, `{name}` filled as in `success`; or `['key' => 'message']`), `back`, `error` (your message for
  `flash('error')`), `signIn`.
+ **A Vue app or a script** that posts with `Accept: application/json` gets the REST answer instead of redirects:
  `{"data": ...}` (201 for a new record), or `{"error": ...}` with 401, 403, 404, 409 or 422. With the session cookie
  it sends the `X-CSRF-Token` header.

`flash('saved', 'Your changes are saved')` in your own code keeps a message for the next request, too.

## File inputs: the `file` rule

```php
#[Capability(name: 'set_cover', description: 'Sets the cover image of a book.', scope: 'books:write')]
class SetCover extends Service
{
    public $rules = array('id' => 'required|integer', 'cover' => 'required|file|extension,png;jpg;webp|max_size,2mb');

    public function handle(array $input, Context $context)
    {
        Database::table('books')->where('id', $input['id'])->update(array('cover' => $input['cover']));
        return array('id' => $input['id'], 'cover' => $input['cover']);
    }
}
```
```php
Route::form('/books/{id}/cover', 'set_cover', ['success' => '/books/{id}', 'flash' => 'Cover saved']);
```
```pug
form(method='POST', action="{$home}books/{$book['id']}/cover", enctype='multipart/form-data')
    =csrf()
    input(type='file', name='cover', accept='.png,.jpg,.webp')
    if errors('cover')
        p.cq-field-error= errors('cover')
    button.btn.btn-primary(type='submit') Save
```

+ Web pages (`Route::form`) and REST (`Route::capability`) save the uploaded file under a random name, once the
  person is allowed, and give `handle()` its **URL**. A text sent in that field is ignored.
+ `extension,png;jpg` lists the types allowed (executable files are always refused); `max_size,2mb` (`kb`, `mb`, `gb`).
+ When the capability fails, the file is deleted.
+ AI agents (MCP) and the command line send a URL or a path instead; its extension is checked. Never open it without
  checking where it points.

## Sign-in: `Route::auth`

```php
Auth::authentication('User', 'users');
Route::auth(['home' => '/books']);
// people sign in with their email address and are signed in right after signing up
Route::auth(['home' => '/books', 'identifier' => 'email', 'signInAfterRegister' => true]);
```

This declares `GET`/`POST /login`, `GET`/`POST /logout`, `GET`/`POST /register` and `GET /notAuthorized`, with the
views of the [authentication module](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#routeauth).

+ Signing in gives the person a **new session id and a new CSRF token**; signing out does too.
+ **Failed sign-ins are limited**: 5 per username and address within 15 minutes, then `429` until the 15 minutes end
  (`attempts`, `lock` in seconds). A username that does not exist takes as long to refuse as one that does.
+ Options: `home` (`/`), `logout` (`/`), `register` (`true`, `false`, or your own capability), `identifier`
  (`username`), `signInAfterRegister` (`false`), `views`, `prefix`
  (`/account/login`...: the shipped views post to `/login`, so write your own), `passkeys` (`true` adds
  `/passkey/register/options`, `/passkey/register`, `/passkey/login/options`, `/passkey/login`).
+ **`identifier`**: the `users` column people sign in with, `username` by default. `'identifier' => 'email'` signs in
  with the email address: the sign-in form sends a field named `email` (change its name in your `login.pug`; the view
  gets `$identifier`), looked up with your User model's `findBy('email', $value)` (the shipped model has it) or, without
  one, a bound query on the model's table. `findByUsername()` keeps working for `username`.
+ **Sign-up sends every input the register capability declares**: add `email`, `display_name`... to the `$rules` of
  your `register_user` and to the form; nothing else of the form reaches the capability. The page shown again after a
  mistake keeps them in `$user`, never the passwords.
+ **`'signInAfterRegister' => true`**: a new account is signed in at once (a new session id and a new CSRF token, as at
  sign-in) and goes to `home`, instead of the sign-in page.
+ A JSON request (with `X-CSRF-Token`) gets `200`, `401` or `429` in JSON: a Vue app signs in with the same routes.
+ **Not in `Route::auth`:** password reset (the `users` table has no email address) and face sign-in (the face login
  recipe has its own controller). `'reset' => true` or `'face' => true` stops with an error.
+ Pages that need someone keep `Route::page(..., ['signedIn' => true])` or `['level' => 1]`; they send visitors to
  `/login` and people below the level to `/notAuthorized`.

## Webhooks: `Route::webhook`

Stripe, GitHub and other services post events to an address of yours. `Route::webhook` checks that the event really
comes from them, then calls a capability with it.

```php
Route::webhook('/stripe/events', 'record_payment', ['verify' => 'stripe']);   // STRIPE_WEBHOOK_SECRET in .env
```
```php
#[Capability(name: 'record_payment', description: 'Records a paid Stripe checkout session.')]
class RecordPayment extends Service
{
    public $rules = array('event' => 'required');

    public function handle(array $input, Context $context)
    {
        $event = $input['event'];
        if (($event['type'] ?? '') !== 'checkout.session.completed') {
            return array('ignored' => true);
        }
        $id = $event['data']['object']['id'];
        if (!Database::table('payments')->where('stripe_session', $id)->exists()) {
            Database::table('payments')->insert(array('stripe_session' => $id, 'status' => 'paid'));
        }
        return array('recorded' => true);
    }
}
```

| The event | The answer |
|---|---|
| signature checked, capability done | `200 {"received": true}` |
| bad or missing signature, a body that is not JSON | `400` |
| the capability refuses (`ServiceError`) | its status |
| no secret set, an error in the capability | `500` (the message goes to the PHP error log) |

+ `verify` is required: `'stripe'` (with `STRIPE_WEBHOOK_SECRET`, the endpoint's `whsec_...`, or `'secret_env' =>
  'OTHER_NAME'`), or a function for other services: `'verify' => function ($rawBody, $headers) { return
  hash_equals('sha256=' . hash_hmac('sha256', $rawBody, getenv('GITHUB_SECRET')), $headers['x-hub-signature-256'] ?? ''); }`
  (header names in lower case).
+ No session and no CSRF token: the signature is the only proof. The capability runs as the system, with full access.
+ Services send an event again until they get a 2xx: make the capability safe to run twice, as above. Before trusting
  an amount, ask Stripe again ([Stripe](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment#webhook)).

## Downloads: `Route::download`

```php
Route::download('/books.csv', 'list_books', ['filename' => 'books.csv']);
Route::download('/books.json', 'list_books', ['as' => 'json']);
```

+ The capability must be read-only (opening a link must not change data). It gets the query string and the route
  variables (`/books.csv?author_id=3`), as the person who opens the link; refusals are those of `Route::page`.
+ **CSV**: a header row from the first row's columns, values quoted where needed, UTF-8 (`'bom' => true` for Excel).
  A cell that starts with `=`, `+`, `-` or `@` gets a `'` first, so a spreadsheet does not run it as a formula
  (`'formulas' => true` keeps it).
+ Each row of the list the capability returns is a line. A `paginate()` page exports its rows (that page only:
  return `get()` to export every row); a single record gives one line.
+ `filename`: a name, or `function ($result, $input) { return 'books-' . date('Y-m-d') . '.csv'; }`.

## Redirects: `Route::redirect`

```php
Route::redirect('/', '/books');                          // 302
Route::redirect('/old/books/{id}', '/books/{id}', 301);  // moved for good
```

+ A `GET` that sends the visitor elsewhere, without a closure. `{name}` in the target takes the route variable of
  the same name (url-encoded); the query string never fills it.
+ The target is a path of the site or a full `https://` address; the status is `301`, `302` (default), `303`, `307`
  or `308`, anything else stops with an error when `web.php` runs.

## Do you still need a controller?

Rarely. With `Route::page`, `Route::form`, `Route::auth`, `Route::webhook`, `Route::download`, `Route::redirect` and capability routes,
a controller is for HTTP that is truly custom:

+ an action that calls several capabilities with conditions, or chooses between views;
+ custom headers, streaming, a response that is neither a page, a redirect, JSON nor a file;
+ password reset, face sign-in, an OAuth callback.

Existing controllers keep working, and a controller calls capabilities with `$this->call()`
([Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#callcapability)). Never write data in
a closure route (`Route::post('/x', function () {...})`): it has no CSRF check for JSON calls; use `Route::form` or a
capability route.
