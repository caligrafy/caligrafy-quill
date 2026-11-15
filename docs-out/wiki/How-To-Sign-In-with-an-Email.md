How to let people **sign in with their email address**. The simplest way keeps the `users` table as it is: the
`username` column holds the address, in lower case. `Route::auth`, passkeys, the throttle and `$user['username']` keep
working, with no new column. It takes a wider column, two rules, two form fields and two messages. Since 2.19.0.

<br/>

## With Claude

Say it in your idea or your request:

```text
"people sign in with their email, kept in the username column"
```

`/caligrafy-quill:idea` puts it in the plan and writes the steps below.

**Check:** a sign-up with `Ada@Example.COM` lands in `users.username` as `ada@example.com`, and signing in as
`ADA@example.com` works.

<br/>

## By hand

1. **The column.** An address can be 254 characters. Before the table exists, write `username varchar(254) NOT NULL` in
   `application/models/Auth/create_db.sql`. For a table that exists, run
   `docker compose exec dev-box php caligrafer.php migrate make username_holds_email`, write in the file it names:
    ```sql
    -- username_holds_email
    ALTER TABLE users MODIFY username varchar(254) NOT NULL;
    ```
   and run `docker compose exec dev-box php caligrafer.php migrate`.
2. **The rules.** In `application/services/Users/CaligrafyRegisterUser.php`, replace the `$rules` line with:
    ```php
    public $rules = array('username' => 'required|valid_email|max_len,254', 'passcode' => 'required|min_len,8|max_len,72');
    public $filters = array('username' => 'trim|lower_case');
    ```
   and in `application/services/Users/CaligrafyUpdateUser.php` make the rule of `username` `'valid_email|max_len,254'`
   and add `public $filters = array('username' => 'trim|lower_case');`. A later `update` keeps these two files and writes
   the release's copies next to them as `.caligrafy-new`.
3. **The forms.** In `application/views/Auth/login.pug`, replace the `- $field`, `- $isEmail` lines and the `.cq-field`
   of the identifier with:
    ```pug
            .cq-field
              label(for='username') Email
              input#username.form-control(type='email', name='username', value=isset($user) && is_object($user) ? ($user->username ?? '') : '', required, autocomplete='email', autocapitalize='none', maxlength='254')
    ```
   In `application/views/Auth/register.pug`, change the `p.cq-lead` line to
   `p.cq-lead Your email address, and at least 8 characters for the password.` and the first `.cq-field` to:
    ```pug
          .cq-field
            label(for='username') Email
            input#username.form-control(type='email', name='username', value=$user->username?? '', required, autocomplete='email', autocapitalize='none', maxlength='254')
            if isset($errors['username'])
              p.cq-field-error= $errors['username']
    ```
   The field stays named `username`. A [sign-up page of your own](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Build-a-Sign-Up-Page)
   changes the same way.
4. **The words**, in the messages of `Route::auth`:
    ```php
    Route::auth(['home' => '/books', 'messages' => [
        'invalid' => 'Invalid email or password',
        'register_taken' => 'An account already uses this email',
    ]]);
    ```
5. **Try it.**

| You do | You get |
|---|---|
| *Create account* with `not an email` | *A few details need a fix: see the notes under the fields.*, with the field's message under it |
| *Create account* with `Ada@Example.COM` | `302` to `/login`; `SELECT username FROM users` shows `ada@example.com` |
| The same address again | *An account already uses this email* |
| Sign in as `ADA@example.com` | `302` to `home`: the `username` column ignores case |
| A wrong password | *Invalid email or password* |

<br/>

## <a name="column"></a>Another way: a separate `email` column

When you need both a username and an address, `Route::auth(['identifier' => 'email'])` signs in with the `email` column,
and the shipped `login.pug` names its field after `$identifier`. The shipped `users` table has no `email` column and
`register_user` asks for a username: add the column in a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
and write your own sign-up capability (`'register' => 'register_reader'`) whose `$rules` declare `email`. The lookup is
the model's `findBy($column, $value)` (the shipped `User` has it), else a bound query on its table.

<br/>

## Common mistakes

+ **`'identifier' => 'email'` with the shipped views and table** (G133): the POST field is named after the identifier, so
  a `username` field no longer signs in; and the table needs an `email` column.
+ **The sign-up refuses an address** (G75): the shipped `register_user` accepts letters and digits only, until its rules
  change as in step 2.
+ **A mixed-case address in the table**: the `lower_case` filter keeps them all in lower case.

<br/>

## Related pages

+ [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#emailsignin)
+ [How to reset a password](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Reset-a-Password): with the address in `username`, no new column
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
+ [API: Caligrafy\AuthRoutes](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes)
