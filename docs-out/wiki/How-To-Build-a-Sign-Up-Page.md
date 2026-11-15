How to build a **sign-up page of your own**: a page for visitors only that asks for a username and a password typed
twice, asks to accept the terms, creates the account with the `register_user` capability the authentication module
ships, and signs the person in at once. One `Route::page`, one `Route::form` and a Pug view: no controller. Since
2.20.0 (`same`, `accepted`, `signsIn`, `visitorsOnly`).

`/register` of [`Route::auth`](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#routeauth) already
asks for a username and a password and sends the person to `/login`; this page is for when you want more.

<br/>

## With Claude

```text
"A sign-up page at /join with the password typed twice and a terms box; it signs people in and sends them to /books"
```

Claude writes the two routes and the view below.

**Check**

+ `/join` sends someone already signed in to `/books`.
+ Two different passwords and an empty box come back with a message under each, and the username stays.
+ A right form lands on `/books`, signed in.

<br/>

## By hand

Before you start: the authentication module (`Auth::authentication('User', 'users');` and `Route::auth(...)` in
`web.php`).

1. **The routes**, in `application/config/routing/web.php`, after `Route::auth(...)`:
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
   `register_user` answers the new account, and `'signsIn' => 'id'` signs in the person whose id is in that field (a new
   session id and a new CSRF token, as for `Route::auth`).
2. **The view**, `application/views/Bookshelf/join.pug`:
    ```pug
    extends ../default/master/site

    block content
      main.cq-auth
        h1 Join the Bookshelf
        if flash('error')
          .cq-alert.cq-alert--error(role='alert')= flash('error')
        form(method='POST', action=$home . 'join')
          =csrf()
          .cq-field
            label(for='username') Username
            input#username.form-control(type='text', name='username', value=old('username'), required)
            if errors('username')
              p.cq-field-error= errors('username')
          .cq-field
            label(for='passcode') Password
            input#passcode.form-control(type='password', name='passcode', required, autocomplete='new-password', maxlength='72')
            if errors('passcode')
              p.cq-field-error= errors('passcode')
          .cq-field
            label(for='confirmpasscode') The same password again
            input#confirmpasscode.form-control(type='password', name='confirmpasscode', required, autocomplete='new-password', maxlength='72')
            if errors('confirmpasscode')
              p.cq-field-error= errors('confirmpasscode')
          .cq-field
            label
              input(type='checkbox', name='terms', value='1')
              |  I accept the terms of use
            if errors('terms')
              p.cq-field-error= errors('terms')
          button.btn.btn-primary(type='submit') Join
    ```
3. **Try it.** Sign out and open `http://localhost:8080/join`. Send two different passwords and leave the box empty: the
   page comes back with "Some of the inputs are invalid", "The two passwords are not the same" under the second password
   and "Accept the terms of use to create an account" under the box, and the username is still there. Send the form
   right: you land on `/books`, signed in, with "Welcome to the Bookshelf" (when `books.pug` shows `flash('success')`).
   Open `/join` again: you are sent to `/books`.

<br/>

## <a name="reference"></a>Reference

| Option of `Route::form` | Here |
|---|---|
| `visitorsOnly` | people already signed in go to `/books`, and the capability does not run |
| `same` | `confirmpasscode` must equal `passcode`; checked before the capability, its message in `errors('confirmpasscode')` |
| `accepted` | the box `terms` must be ticked |
| `signsIn` | the field of the answer that holds the id of the person to sign in; `signsInWith` says how (`'password'` by default, for `AuthRoutes::signedInBy()`) |
| `success`, `back`, `flash` | where a good form and a refused one go, and the message |

The checks run in this order: `visitorsOnly`, then (JSON with the session) the CSRF header, then `same` and `accepted`,
then the capability. All the options: [Declarative routes: `Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form).

`register_user` accepts usernames of letters and digits (up to 100) and passwords of 8 to 72 characters, gives
`permissions` 0, and is refused over REST and MCP, so nobody can create accounts in bulk.

<br/>

## Common mistakes

+ **A username with a dot or an `@`** is refused: `register_user` takes letters and digits (G75). For an email, see
  [How to sign in with an email](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Sign-In-with-an-Email).
+ **The messages are gone on the next page** (G129): they last one request; `back` and `success` must be the pages that
  show them.
+ **Declaring `confirmpasscode` in `$rules`**: `same` reads it from what was sent; declare it only if the capability
  needs it.
+ **A field `register_user`'s `$rules` do not list** never reaches it (G133).

<br/>

## Related pages

+ [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#signupform)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form)
+ [How to sign in with an email](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Sign-In-with-an-Email)
+ [API: Caligrafy\Form](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#form) · [Caligrafy\AuthRoutes](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes)
