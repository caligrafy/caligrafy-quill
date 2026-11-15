How to let people **sign in through another site**: a partner, a company directory, an OAuth provider. The browser goes
to the other site with a value only it knows (`state`), comes back with a code, the server trades the code for the
account, server to server, and signs the person in. That needs the session, sends the browser away and signs someone in:
two web-only capabilities and two [`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action)
lines, no controller. Since 2.21.0 (`webOnly`, `Route::action`).

<br/>

## With Claude

```text
"Add 'Sign in with Partner': the code comes back to /auth/partner"
```

**Check**

+ `/signin/partner` answers `302` to the other site, with a `state`.
+ `/auth/partner` with a wrong or missing `state` goes to `/login`.

<br/>

## By hand

`application/services/Account/PartnerSignIn.php` (`PARTNER_URL`, `PARTNER_CLIENT_ID` and `PARTNER_CLIENT_SECRET` in `.env`):

```php
<?php

use Caligrafy\AuthRoutes;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;
use Caligrafy\Web;

// GET /signin/partner: off to the other site, with a value only this browser knows
#[Capability(name: 'partner_sign_in', description: 'Sends the visitor to sign in on the partner site.', public: true, webOnly: true)]
class PartnerSignIn extends Service
{
    public function handle(array $input, Context $context)
    {
        $state = bin2hex(random_bytes(16));
        session('partner_state', $state);
        return Web::redirect(getenv('PARTNER_URL') . '/authorize?' . http_build_query(array(
            'client_id' => getenv('PARTNER_CLIENT_ID'),
            'redirect_uri' => session('home') . 'auth/partner',
            'state' => $state,
        )));
    }
}

// GET /auth/partner?code=&state=: back from the other site
#[Capability(name: 'partner_callback', description: 'Finishes a sign-in on the partner site.', public: true, webOnly: true)]
class PartnerCallback extends Service
{
    public $rules = array('code' => '', 'state' => '');

    public function handle(array $input, Context $context)
    {
        $expected = (string) session('partner_state');
        clearFromSession('partner_state');
        if ($expected === '' || !hash_equals($expected, (string) ($input['state'] ?? ''))) {
            return Web::redirect('/login');      // a sign-in that did not start in this browser
        }
        // the code is traded for the account, server to server, with the secret
        $answer = httpRequest(getenv('PARTNER_URL') . '/token', 'POST',
            array('code' => $input['code'] ?? '', 'client_secret' => getenv('PARTNER_CLIENT_SECRET')),
            array('Content-Type: application/json', 'Accept: application/json'));
        $username = 'partner' . preg_replace('/[^0-9]/', '', (string) ($answer['id'] ?? ''));
        if ($username === 'partner') {
            return Web::redirect('/login');
        }
        $users = new User('users');
        if (!$users->findByUsername($username)) {
            Database::table('users')->insert(array('username' => $username, 'passcode' => User::hashPassword(bin2hex(random_bytes(32)))));
        }
        AuthRoutes::signIn($users->findByUsername($username));
        return Web::redirect('/books');
    }
}
```

```php
    Route::action('/signin/partner', 'partner_sign_in');
    Route::action('/auth/partner', 'partner_callback');
```

`/signin/partner` answers `302` to the other site with a `state`. Back at `/auth/partner`, a wrong or missing `state` goes
to `/login`; the right one signs the person in and goes to `/books`. To remember how, for what only some sign-ins may do,
pass it as the third argument: `AuthRoutes::signIn($user, null, 'partner')`, then read it with `AuthRoutes::signedInBy()`
([below](#signedinby)). `AuthRoutes::signIn()` gives a new session id and a new
CSRF token. A page that stays open and keeps calling JSON uses `session_regenerate_id(true); Auth::authorize($user);`, as the
[face login recipe](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#face-login) does, so its token stays valid.

<br/>

## <a name="signedinby"></a>How a session signed in

Since 2.21.3, `AuthRoutes::signedInBy()` says how the current session signed in. `Route::auth` records `password` or
`passkey`. When you sign someone in yourself, say how:

```php
AuthRoutes::signIn($user, null, 'face');        // a new session id, a new CSRF token, and how it signed in
AuthRoutes::rememberSignIn('face');             // a page that stays open and signs in with Auth::authorize()
```

Use it for anything that changes how the account signs in. A face without a liveness check does not prove a person (a good
photo can pass), so a session opened that way must not be able to replace the face or add a passkey:

```php
if (AuthRoutes::signedInBy() !== 'password') {
    return Web::json(['error' => 'Sign in with your password to change your face'], 403);
}
```

It answers `null` when nobody is signed in or the sign-in was not recorded: treat that as not allowed. Signing out forgets it.

| Function of `Caligrafy\AuthRoutes` | What it does |
|---|---|
| `signIn($user, null, $how = null)` | a new session id, a new CSRF token, `Auth::authorize($user)`, and records `$how` |
| `rememberSignIn($how)` | records `$how`, for a page that signs in with `Auth::authorize()` (`null` forgets it) |
| `signedInBy()` | `password` or `passkey` through `Route::auth`, what you recorded otherwise, `null` when nobody recorded it or nobody is signed in |
| `signOut()` | `Auth::unauthorize()`, forgets `$how`, a new session id and CSRF token |

`Route::form` with `signsIn` records `password` (2.21.7); give another with `'signsInWith' => 'face'`.

<br/>

## Common mistakes

+ **A web-only capability answers "There is no capability" over REST, MCP or `call`** (G143): only `Route::action`
  reaches it. That is what keeps the session and the redirects out of the other channels.
+ **`$context` is still the visitor after `AuthRoutes::signIn()`** (G144): it was built from the request. To call a
  capability as the person just signed in, build a new one: `Context::fromRequest(request(), 'web')`.
+ **A sign-in that did not start in this browser**: always check `state` with `hash_equals()` and forget it at once.
+ **Secrets in the code**: `PARTNER_CLIENT_SECRET` lives in `.env`, and the code is traded server to server.

<br/>

## Related pages

+ [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#betweensites)
+ [Declarative routes: `Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action)
+ [API: Caligrafy\AuthRoutes](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes) · [Caligrafy\Web](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#web)
