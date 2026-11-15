Three options of `#[Capability(...)]` say who may call a capability: `public`, `scope` and `level`. They are checked
once, in `Capabilities::call()`, for every channel: a page, a form, REST, the JS client, an agent over MCP, the command
line and the jobs all get the same answer. Since 2.1.0.

<br/>

## With Claude

Say the scope and who may call it:

```text
/caligrafy-quill:new-capability list_books books:read read-only public: the catalog, 20 per page
/caligrafy-quill:new-capability delete_book books:write destructive: only administrators (level 1)
```

**Check** before you say OK:
+ `php caligrafer.php capabilities` shows `public`, the scope, or `signed in` (no scope) in the second column;
+ `php caligrafer.php call <name> --guest` answers `unauthenticated` for a capability that is not public;
+ a token without the scope gets `403` over REST and does not see the tool in MCP `tools/list`.

<br/>

## By hand

```php
#[Capability(name: 'list_books', description: 'Lists the books of the catalog, 20 per page.', readOnly: true, public: true)]
#[Capability(name: 'create_book', description: 'Adds a book to the catalog and returns it.', scope: 'books:write')]
#[Capability(name: 'delete_book', description: 'Deletes a book for good. Returns its id.', scope: 'books:write', level: 1, destructive: true)]
```

(one attribute per class). Then try each caller:

```bash
php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2 --guest          # unauthenticated
php caligrafer.php token create "Reader" --scopes books:read
php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2 --token cq_...   # forbidden
```

The second call answers `{"error":{"code":"forbidden","message":"This needs the scope books:write"}}`; with a `level`
the message ends with `and permission level 1`.

<br/>

## <a name="order"></a>The checks, in order

`Capabilities::call()`:

1. **Find** it: no such name, or a `webOnly` capability reached by anything but `Route::action`, is `not_found`.
2. **`public: true`**: no check at all; anyone may call it, visitors included.
3. **Someone must act**: a guest (nobody signed in, no token) is `unauthenticated` (401).
4. **`$context->allows($scope, $level)`**, else `forbidden` (403).
5. Then the inputs are validated and `handle()` runs. A capability can still refuse more itself, with
   `ServiceError::forbidden()` ([Refusing a call](https://github.com/caligrafy/caligrafy-quill/wiki/Refusing-a-Call-ServiceError)).

<br/>

## <a name="scope"></a>`scope`: what a token may do

A scope is `<resource>:<action>`: lowercase letters, digits, `_`, `.` and `-` on each side (`books:read`,
`books:write`). The scopes a token was given cover a required scope when one of them is:

+ the same scope (`books:write`);
+ `<resource>:*` (`books:*` covers `books:read` and `books:write`);
+ `*` (everything).

`scope: ''` is met by **any** token. Scopes apply to tokens only: a signed-in person has every scope, and is checked by
the level.

<br/>

## <a name="level"></a>`level`: how much a person may do

`level` is the minimum value of the users' `permissions` column (`Context::$levelAttribute`, `permissions` by default).
`0`, the default, means anyone signed in. `$context->level()` is the user's value, `0` without a user.

| Caller | What `allows($scope, $level)` checks |
| --- | --- |
| the command line, scripts and jobs (`Context::cli()`, `Context::system()`), the legacy API key | nothing: always allowed |
| a signed-in person | `permissions >= level` |
| a token with an owner (`token create ... --user 7`) | the scope, and the owner's `permissions >= level` |
| a token without an owner | the scope only: the level does not apply |
| a token whose owner was deleted (with authentication on) | refused |
| a guest | refused (only public capabilities) |

<br/>

## <a name="public"></a>`public`: anyone

`public: true` skips the checks: a visitor's page, a REST call without a token and any MCP token reach it. Use it for
what anyone may read (the catalog), or for what a visitor must do before having an account. A public capability may still
look at `$context` and refuse: the framework's `register_user` is public but refuses every channel but web pages and the
command line (G81).

<br/>

## <a name="lists"></a>What each caller sees

`Capabilities::available($context)` keeps the capabilities this caller may call (public, or allowed), web-only ones left
out. MCP `tools/list` and `GET /capabilities` show only those, so a token never sees a tool it may not call.

Reference: [`Context::allows()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#context-allows),
[`Tokens::allows()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#tokens-allows),
[`Capabilities::available()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-available).

<br/>

## Common mistakes

+ **`service` without `--scope`** (G76): `scope: ''` lets any token call it.
+ **Trusting the level with an application token** (G53): a token without `--user` passes any level. Create agent tokens
  with `--user <id>` when the level matters.
+ **The legacy API key and tokens without an owner never manage users** (G85): the user capabilities need a real
  administrator, so give an agent that manages users a token of an administrator.

<br/>

## Related pages

+ [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/Personal-Access-Tokens) · [The context](https://github.com/caligrafy/caligrafy-quill/wiki/The-Context)
+ [Authentication: users and levels](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)
+ [The capability attribute](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Attribute)
