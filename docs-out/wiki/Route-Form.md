# Forms: `Route::form`

`Route::form($route, $capability, $options)` answers a form's `POST` (or its `PUT` or `DELETE` through `_method`) by
calling a capability with the form's fields, its files and the route variables (the route variables win), as the
person who sent it, then redirects. The page that shows the form stays a
[`Route::page`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Page). Since 2.19.0; `visitorsOnly`, `same`,
`accepted`, `inputs` and `signsIn` since 2.20.0, `signsInWith` since 2.21.7.

```php
Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
```

<br/>

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write
"Add a 'New book' form at /books/new for signed-in people. After saving, go to the book's page and say 'Book added'.
If a field is wrong, come back to the form with the message under the field and what was typed."
```

Claude writes the capability, the `Route::page` of the form and its `Route::form`, and the Pug view with `=csrf()`,
`old()`, `errors()` and `flash()`.

**Check** before you say OK:
+ the form has `method='POST'`, `=csrf()`, and `=methodField('PUT')` or `'DELETE'` for those routes;
+ `success` and `back` point at the pages that show `flash()` and `errors()`;
+ sending the form empty shows the messages under the fields, and the typed values stay.

<br/>

## By hand

The `create_book` capability is the one of
[Declarative routes, step 1](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes).

1. **Declare the page and the form.** In `application/config/routing/web.php`:

   ```php
       Route::page('/books/new', 'Bookshelf/new', ['signedIn' => true, 'comeBack' => true, 'data' => ['authors' => 'list_authors']]);
       Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
       Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
       Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
   ```

2. **Write the form.** `application/views/Bookshelf/new.pug`:

   ```pug
   extends ../default/master/site

   block content
     section.cq-hero.cq-hero--page
       h1.cq-display New book

     section.cq-band.cq-band--tight
       .cq-inner.cq-inner--narrow
         if flash('error')
           .cq-alert.cq-alert--error(role='alert')= flash('error')
         form(method='POST', action=$home . 'books')
           =csrf()
           .cq-field
             label(for='title') Title
             input#title.form-control(type='text', name='title', value=old('title'))
             if errors('title')
               p.cq-field-error= errors('title')
           .cq-field
             label(for='author_id') Author
             select#author_id.form-select(name='author_id')
               each $a in $authors
                 option(value=$a['id'], selected=$a['id'] == old('author_id'))= $a['name']
             if errors('author_id')
               p.cq-field-error= errors('author_id')
           button.btn.btn-primary(type='submit') Add book
   ```

   A `PUT` or `DELETE` form adds `=methodField('PUT')` (or `'DELETE'`) after `=csrf()`.

3. **Try it.** On `/books/new`, send the form empty: the page comes back with "Some of the inputs are invalid" at the
   top and "The Title field is required" under the title. Fill it in: you land on `/books/<id>` with "Book added".

<br/>

## <a name="outcomes"></a>What happens after the post

| The capability | The person goes to | The next page has |
|---|---|---|
| succeeds | `success`, its `{name}` filled from the result, then from the route variables; without `success`, back to the form | `flash('success')` with the `flash` message |
| refuses the input (`invalid`) or finds a duplicate (`conflict`) | back: the `back` option, else the page it came from (the `Referer`, when it is a page of this site), else `/` | `errors('title')`, `old('title')`, `flash('error')` |
| needs someone signed in, nobody is | the sign-in page (`signIn`, default `/login`); without the authentication module, `403` | |
| refuses the person (`forbidden`) | `403` `[ERROR 1006] Forbidden` | |
| finds nothing, or anything else | `404` `[ERROR 1005] Page Not Found` | |

<br/>

## <a name="options"></a>Options

| Option | Default | What it does |
|---|---|---|
| `method` | `'POST'` | `'PUT'`, `'DELETE'`, or a list (`['POST', 'PUT']`); `GET` is refused (pages are `Route::page`) |
| `success` | back to the form | a path with `{placeholders}`, a full `https://` address, or `function ($result) { return '/books/' . $result['id']; }` |
| `flash` | none | the message for `flash('success')`, with `{placeholders}` as in `success`; or `['key' => 'message']` |
| `back` | the `Referer` of this site, else `/` | where invalid input goes, with the route's `{placeholders}`: `'back' => '/books/{id}'` |
| `error` | the capability's message | your message for `flash('error')`, or `['key' => 'message']` |
| `signIn` | `'/login'` | where a visitor goes when the capability needs someone |
| `visitorsOnly` | none | a path: people already signed in are sent there and the capability does not run; a JSON request gets `403` |
| `same` | none | fields that must match: `['password_again' => ['password', 'The two passwords are not the same']]`; without a message, "The two values are not the same" |
| `accepted` | none | boxes that must be ticked: `['terms' => 'Accept the terms of use']` |
| `inputs` | none | values only the server sets: an array, or `function ($input) { return [...]; }`. They replace what the form sent; the route variables still win |
| `signsIn` | none | the field of the capability's answer that holds the id of the person to sign in: `'signsIn' => 'id'`. A new session id and a new CSRF token, as for `Route::auth` |
| `signsInWith` | `'password'` | how `signsIn` signed the person in: what `AuthRoutes::signedInBy()` answers for that session (2.21.7) |

**The order of the checks.** `visitorsOnly`, then (JSON with the session) the CSRF header, then `same` and `accepted`,
then the capability. A form that breaks `same` or `accepted` never reaches the capability, and their messages arrive in
`errors('password_again')`, like the capability's. Both are read from what the person sent: declare `password_again` in
`$rules` only if the capability needs it.

**Values the form must not choose.** A form can send any field. `inputs` writes the ones only the server knows, over
anything the form sent: the address a payment page comes back to, the visitor's address, today's price.

**A form that leaves the site** for a payment page:

```php
Route::form('/orders/{order}/pay', 'start_checkout', [
    'inputs' => fn () => ['site' => session('home')],
    'success' => fn ($result) => $result['url'],
    'back' => '/orders',
]);
```

**A sign-up form that signs the person in**, for visitors only (`register_user` is the capability the authentication
module ships):

```php
Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);
Route::form('/join', 'register_user', [
    'visitorsOnly' => '/books',
    'same' => ['confirmpasscode' => ['passcode', 'The two passwords are not the same']],
    'accepted' => ['terms' => 'Accept the terms of use to create an account'],
    'signsIn' => 'id',
    'success' => '/books',
    'back' => '/join',
    'flash' => 'Welcome to the Bookshelf',
]);
```

`signsIn` is for a capability that creates or finds the person and answers their id: sign-up, an invitation link, a
confirmation code. A sign-in that decides how to sign in, or that has a state to check, is a
[`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action).

<br/>

## <a name="helpers"></a>The view helpers

| Helper | What it gives |
|---|---|
| `=csrf()` | the hidden `_token` field every form post needs (`403` `[ERROR 3000]` without it) |
| `=methodField('PUT')` | the hidden `_method` field of a `PUT` or `DELETE` form |
| `errors()`, `errors('title')` | all the messages, or one field's (`null` when it has none) |
| `old('title', $default)` | what the person typed, else `$default` |
| `flash('success')`, `flash('error')` | the message of the last post; `flash('saved', 'Your changes are saved')` in your own code keeps one for the next request |

+ **One request only.** The errors, the old values and the messages are read by the next page, then gone.
+ Passwords, card numbers, tokens (any field whose name contains `pass`, `secret`, `token`, `card`, `cvc`, `cvv` or
  `iban`), files and `_` fields are never kept for `old()`.
+ **JSON**: a request with `Accept: application/json` gets the REST answer instead of redirects: `{"data": ...}` with
  `201` (a `POST` that changes data) or `200`, or `{"error": ...}` with `401`, `403`, `404`, `409` or `422`. With the
  session cookie it needs the `X-CSRF-Token` header.

<br/>

## Reference

+ [`Route::form()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-form)
+ [`Caligrafy\Form`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#form): how the post is answered
+ [`Caligrafy\Flash`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#flash) and the helpers
  [`csrf()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers#fn-csrf),
  [`methodField()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers#fn-methodfield),
  [`errors()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers#fn-errors),
  [`old()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers#fn-old),
  [`flash()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers#fn-flash)

<br/>

## Common mistakes

+ **`success` redirects to a page that redirects again**: the message is read by the page in between and lost. Point
  `success` and `back` at the page that shows it.
+ **A JSON post with the session and no `X-CSRF-Token`** answers `403`: send the header (`apiHeaders()` in the Vue
  apps).
+ **A placeholder with no value** (`'success' => '/authors/{author}'` when neither the result nor the route has
  `author`) stops with an error that names it.
+ **A file form without `enctype='multipart/form-data'`**: no file arrives
  ([File uploads](https://github.com/caligrafy/caligrafy-quill/wiki/File-Uploads)).
+ **`Route::form` on a `webOnly` capability**: it answers "There is no capability". Use `Route::action`.

## Related pages

+ [Pages: `Route::page`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Page)
+ [File uploads: the `file` rule](https://github.com/caligrafy/caligrafy-quill/wiki/File-Uploads)
+ [Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation): the rules of `$rules`
+ [Views: forms](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#formhelpers)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): the complete Bookshelf example
