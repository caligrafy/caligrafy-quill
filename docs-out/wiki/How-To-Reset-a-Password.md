How to add a **password reset by email**: a `/forgot` page that emails a link, valid one hour, that works once, and a
`/reset/{token}` page that sets the new password. `Route::auth` has no password reset (the shipped `users` table has no
email address), so it takes two capabilities, four routes and two views, and no controller. The database never holds
the link: only its SHA-256 hash. Since 2.19.0.

<br/>

## With Claude

```text
"Add a password reset by email: a /forgot page, a link valid one hour that works once"
```

**Check**

+ `/forgot` says the same for an address that exists and one that does not.
+ The link works once, for one hour.

<br/>

## By hand

**1. The tables.** They hold data: add them with a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations). Run
`docker compose exec dev-box php caligrafer.php migrate make add_password_resets`, and write in the file it names:

```sql
-- add_password_resets
ALTER TABLE users ADD COLUMN email VARCHAR(191) NULL, ADD UNIQUE KEY users_email_unique (email);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(15) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token_hash (token_hash),
    CONSTRAINT fk_password_resets_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```

Run `docker compose exec dev-box php caligrafer.php migrate`. People need an address in `users.email`: ask for it at
sign-up (`Route::auth`'s `register` option names your own capability) or on an account page. When the `username` column holds
the address ([Signing in with an email](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Sign-In-with-an-Email)), leave out the `ALTER TABLE` statement and read
`->where('username', strtolower($input['email']))` in `request_password_reset` below.

**2. The capabilities.** `application/services/Account/RequestPasswordReset.php`:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Mail;
use Caligrafy\Service;
use Caligrafy\ServiceError;
use Caligrafy\Throttle;

// request_password_reset: emails a link to choose a new password. The answer is the same whether the address exists or not
#[Capability(
    name: 'request_password_reset',
    description: 'Emails a link to choose a new password, valid one hour. Used by the page /forgot.',
    public: true,
    mcp: false
)]
class RequestPasswordReset extends Service
{
    // link and ip come from the route ('inputs'): the form cannot set them
    public $rules = array('email' => 'required|valid_email', 'link' => 'required', 'ip' => '');

    public function handle(array $input, Context $context)
    {
        if (!in_array($context->channel, array('web', 'cli'), true)) {
            throw ServiceError::forbidden('Ask for a new password on the page /forgot');
        }
        $throttle = new Throttle(5, 3600);
        $key = Throttle::key('password-reset', $input['ip'] ?? '', $input['email']);
        if (!$throttle->locked($key)) {
            $throttle->fail($key);
            $user = Database::table('users')->where('email', $input['email'])->first();
            if ($user) {
                $token = bin2hex(random_bytes(32));
                Database::table('password_resets')->where('user_id', $user['id'])->delete();
                Database::table('password_resets')->insert(array(
                    'user_id' => $user['id'],
                    'token_hash' => hash('sha256', $token),       // the database never holds the link
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                ));
                (new Mail())->sendMail(
                    array('email' => $input['email']),
                    array(
                        'subject' => 'Choose a new password',
                        'body' => '<p><a href="' . htmlspecialchars($input['link'] . $token) . '">Choose a new password</a>. This link works once, for one hour.</p>',
                    ),
                    array('email' => getenv('MAIL_FROM') ?: getenv('MAIL_USERNAME'), 'name' => 'Bookshelf')
                );
            }
        }
        return array('sent' => true);
    }
}
```

`application/services/Account/ResetPassword.php`:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// reset_password: a valid link sets a new password, once
#[Capability(
    name: 'reset_password',
    description: 'Sets a new password from the link of a password reset email. The link works once, for one hour.',
    public: true,
    mcp: false
)]
class ResetPassword extends Service
{
    public $rules = array('token' => 'required|alpha_numeric|exact_len,64', 'passcode' => 'required|min_len,8|max_len,72');
    public $descriptions = array('token' => 'The token of the link', 'passcode' => 'The new password: 8 to 72 characters');

    public function handle(array $input, Context $context)
    {
        $reset = Database::table('password_resets')->where('token_hash', hash('sha256', $input['token']))->first();
        if (!$reset || $reset['expires_at'] < gmdate('Y-m-d H:i:s')) {
            throw ServiceError::invalid(array('token' => 'This link no longer works: ask for a new one'), 'This link no longer works: ask for a new one');
        }
        Database::table('users')->where('id', $reset['user_id'])->update(array('passcode' => User::hashPassword($input['passcode'])));
        Database::table('password_resets')->where('id', $reset['id'])->delete();
        return array('reset' => true);
    }
}
```

**3. The routes**, in `application/config/routing/web.php`, after `Route::auth(...)`:

```php
    Route::page('/forgot', 'Account/forgot', ['visitorsOnly' => '/books']);
    Route::form('/forgot', 'request_password_reset', [
        'inputs' => fn () => ['link' => session('home') . 'reset/', 'ip' => Caligrafy\AuthRoutes::ip()],
        'success' => '/forgot',
        'back' => '/forgot',
        'flash' => 'If this address has an account, a link is on its way. It works for one hour.',
    ]);
    Route::page('/reset/{token}', 'Account/reset', ['visitorsOnly' => '/books']);
    Route::form('/reset/{token}', 'reset_password', [
        'same' => ['passcode_again' => ['passcode', 'The two passwords are not the same']],
        'success' => '/login',
        'back' => '/reset/{token}',
        'flash' => 'Your password is changed. Sign in with it.',
    ]);
```

**4. The views.** `application/views/Account/forgot.pug`:

```pug
extends ../default/master/site

block content
  main.cq-auth
    h1 Forgot your password?
    if flash('success')
      .cq-alert.cq-alert--success(role='status')= flash('success')
    if flash('error')
      .cq-alert.cq-alert--error(role='alert')= flash('error')
    form(method='POST', action=$home . 'forgot')
      =csrf()
      .cq-field
        label(for='email') Your email address
        input#email.form-control(type='email', name='email', value=old('email'), required)
        if errors('email')
          p.cq-field-error= errors('email')
      button.btn.btn-primary(type='submit') Email me a link
```

`application/views/Account/reset.pug`:

```pug
extends ../default/master/site

block content
  main.cq-auth
    h1 Choose a new password
    if flash('error')
      .cq-alert.cq-alert--error(role='alert')= flash('error')
    form(method='POST', action=$home . 'reset/' . $token)
      =csrf()
      .cq-field
        label(for='passcode') New password
        input#passcode.form-control(type='password', name='passcode', required, autocomplete='new-password', maxlength='72')
        if errors('passcode')
          p.cq-field-error= errors('passcode')
      .cq-field
        label(for='passcode_again') The same password again
        input#passcode_again.form-control(type='password', name='passcode_again', required, autocomplete='new-password', maxlength='72')
        if errors('passcode_again')
          p.cq-field-error= errors('passcode_again')
      button.btn.btn-primary(type='submit') Save the new password
```

Add a link to `/forgot` in `application/views/Auth/login.pug`.

**5. Try it.** With `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM` in `.env`, the link arrives by email. To try
the page with no mail server, make a link by hand, in phpMyAdmin (`http://localhost:8077`):

```sql
INSERT INTO password_resets (user_id, token_hash, expires_at)
VALUES (1, SHA2(REPEAT('a', 64), 256), UTC_TIMESTAMP() + INTERVAL 1 HOUR);
```

Open `http://localhost:8080/reset/` followed by 64 times the letter `a`. Two different passwords come back with "The two
passwords are not the same"; two equal ones land on `/login` with "Your password is changed. Sign in with it.", and the
new password signs in. Open the link again and send it: "This link no longer works: ask for a new one".

**Check**

+ `/forgot` says the same for an address that exists and one that does not.
+ A `link` field added to the form changes nothing: `inputs` writes it.
+ The link works once. Changing the password also ends the person's open sessions (their row changed).
+ `php caligrafer.php capabilities` lists both, and neither is an MCP tool (`mcp: false`).

<br/>

## <a name="reference"></a>Reference

| Piece | Why |
|---|---|
| `new Throttle(5, 3600)`, `Throttle::key('password-reset', $ip, $email)` | 5 requests an hour per address and email, then nothing more is sent ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#throttle)) |
| the same answer whether the address exists or not | nobody learns which addresses have an account |
| `hash('sha256', $token)` | the database never holds the link |
| `'inputs' => fn () => [...]` | `link` and `ip` come from the server; a field the form adds is replaced ([`Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form)) |
| `$context->channel` web or cli, `mcp: false` | the reset is asked for on the page, never by an agent |
| `User::hashPassword()` | the new password is stored with `password_hash()` |
| `same` | the two passwords must match before the capability runs |

`AuthRoutes::ip()` is the visitor's address ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes#authroutes-ip)).

<br/>

## Common mistakes

+ **The email never arrives**: `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM` must be in `.env`.
+ **A `link` field in the form**: it changes nothing, `inputs` writes it; never build the link from what the form sent.
+ **The flash message is gone** (G129): it lasts one request; `success` and `back` must be the pages that show it.
+ **The tables in `create_db.sql` of an app that runs**: add them with a migration, which runs once (G120).

<br/>

## Related pages

+ [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#passwordreset)
+ [How to sign in with an email](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Sign-In-with-an-Email)
+ [Declarative routes: `Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form)
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
