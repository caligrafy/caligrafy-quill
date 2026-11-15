# Sign-in pages: `Route::auth`

`Route::auth($options)` declares the sign-in, sign-out and sign-up pages in one line, with the views of the
authentication module, and no controller. It goes after `Auth::authentication('User', 'users');` in `web.php`. Since
2.19.0; `level` since 2.19.5, `attemptsPerUser` since 2.19.6, coming back after signing in since 2.20.0.

```php
Auth::authentication('User', 'users');
Route::auth(['home' => '/books']);
```

This page is about the declaration. Users, levels, tokens, password reset and the views are in
[Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication).

<br/>

## With Claude

```text
"Add sign-in pages: after signing in, people land on /books."
```

**Check** before you say OK:
+ `Auth::authentication('User', 'users');` is above `Route::auth(...)` and the `users` table exists;
+ a page only signed-in people open says `'signedIn' => true` (and `'comeBack' => true` to land back on it);
+ no controller and no closure was written for the sign-in.

<br/>

## By hand

1. **The `users` table.** Run:

   ```bash
   docker compose exec dev-box php caligrafer.php sql application/models/Auth/create_db.sql
   ```

2. **Turn on sign-in.** In `application/config/routing/web.php`, remove the `//` in front of
   `Auth::authentication('User', 'users');`, and add below it:

   ```php
       // sign-in pages: /login, /logout, /register, /notAuthorized
       Route::auth(['home' => '/books']);
   ```

3. **Try it.** Open `http://localhost:8080/register`, create an account (a username, a password typed twice), then
   sign in on `/login`: you land on `/books`.

<br/>

## <a name="routes"></a>What it declares

| Route | What it does |
|---|---|
| `GET /login` | the sign-in page (`Auth/login`); a signed-in person goes to `home`, or to `?return=` |
| `POST /login` | checks the identifier field and `passcode`; on success a new session id and a new CSRF token, then `home`, or the page of `/login?return=` |
| `GET /logout`, `POST /logout` | signs out (a new session id and CSRF token), then `logout` |
| `GET /notAuthorized` | the page of people below the level a page needs (403) |
| `GET /register`, `POST /register` | the sign-up page (`Auth/register`) and the `register_user` capability, then `/login` |
| `POST /passkey/register/options`, `/passkey/register`, `/passkey/login/options`, `/passkey/login` | with `'passkeys' => true` only |

<br/>

## <a name="options"></a>Options

| Option | Default | What it does |
|---|---|---|
| `home` | `'/'` | where people go after signing in (a signed-in person who opens `/login` goes there too) |
| `logout` | `'/'` | where people go after signing out |
| `register` | `true` | `true`: `/register` with the `register_user` capability; `false`: no sign-up; a capability name: yours |
| `identifier` | `'username'` | the `users` column people sign in with, and the name of the sign-in field (`'email'`) |
| `signInAfterRegister` | `false` | `true`: a new account is signed in and goes to `home` instead of the sign-in page |
| `attempts`, `lock` | `5`, `900` | failed sign-ins per username and address before a `429`, and the window in seconds |
| `attemptsPerUser` | `0` (off) | failed sign-ins of one username from any address before it is locked (2.19.6). Someone could lock a known username out on purpose: keep it high (20) |
| `level` | `0` | the lowest permission level that may sign in (2.19.5): below it, a sign-in is refused like a wrong password |
| `messages` | human words | what sign-in and sign-up say: `invalid`, `locked` (2.19.3, `{minutes}` is the wait), `mismatch`, `register_invalid`, `register_taken`, `registered` (2.19.4); the defaults are `AuthRoutes::MESSAGES` |
| `views` | `['login' => 'Auth/login', 'register' => 'Auth/register']` | your own views |
| `prefix` | `''` | `'/account'` gives `/account/login`... (the shipped views post to `/login`: write your own) |
| `passkeys` | `false` | `true` adds the passkey endpoints ([passkeys](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#passkeys)) |

```php
// a users table with an email column: people sign in with it, and are signed in right after signing up
Route::auth(['home' => '/books', 'identifier' => 'email', 'signInAfterRegister' => true]);

// only administrators may sign in, in your own words
Route::auth(['home' => '/admin', 'level' => 1, 'messages' => ['invalid' => 'This account cannot sign in here.']]);
```

<br/>

## <a name="behavior"></a>How it behaves

+ Signing in and signing out give the person a new session id and a new CSRF token.
+ The fifth failed sign-in for a username from one address within 15 minutes, and every one after it until the 15
  minutes end, answers `429` (2.19.3; `messages` sets your own words).
+ A JSON request with `X-CSRF-Token` gets `200` `{"data":{"user":{"id":1,"username":"ada","permissions":0}}}`, `401`
  or `429` in JSON: a Vue app signs in with the same routes.
+ Sign-up sends the register capability every input its `$rules` declare; nothing else of the form reaches it.
+ After signing in, a person goes to `home`, or to the page named by `/login?return=/books/3` (2.20.0). Only a path of
  this site is followed: another site, or `//evil.example`, is ignored. `Route::page` and `Route::action` with
  `'comeBack' => true` build that address for you.
+ `Route::auth` records how the session signed in, `password` or `passkey`, for `AuthRoutes::signedInBy()` (2.21.3).
+ **Not in `Route::auth`**: password reset and face sign-in. `'reset' => true` or `'face' => true` stops with an error.
  [Password reset](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#passwordreset) is `Route::page`
  and `Route::form`; [face sign-in](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#face-login)
  is `Route::action`.

<br/>

## Reference

+ [`Route::auth()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-auth)
+ [`Caligrafy\AuthRoutes`](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes#authroutes):
  [`signIn()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes#authroutes-signin),
  [`signedInBy()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes#authroutes-signedinby),
  [`signOut()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes#authroutes-signout)
+ [`Caligrafy\Auth`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Auth#auth)

<br/>

## Common mistakes

+ **`Route::auth` without `Auth::authentication('User', 'users')` above it**: there is no user model to sign in with.
+ **`'identifier' => 'email'` with the shipped `login.pug`**: the sign-in form posts a field named after the
  identifier, so the view needs an `email` field. For people who sign in with an email and no separate username, keep
  the address in `username` instead ([Signing in with an email](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#emailsignin)).
+ **A `prefix` with the shipped views**: they post to `/login`. Write your own views.
+ **`comeBack` with a `return=` you build by hand to another site**: it is ignored, the person goes to `home`.

## Related pages

+ [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication): users, levels, tokens, password reset
+ [Pages: `Route::page`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Page): `signedIn`, `level`, `comeBack`, `visitorsOnly`
+ [Forms: `Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Form): a sign-up of your own with `signsIn`
+ [Face detection and face login](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition)
