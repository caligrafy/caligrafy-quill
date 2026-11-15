A program or an AI agent proves who it is with a **personal access token**: `cq_<id>_<40 hexadecimal characters>`,
sent as `Authorization: Bearer cq_...`. A token has a name, **scopes** (what it may do), and optionally an owner (the
user it acts for) and an expiry. It is shown once, only its SHA-256 hash is kept, and it can be revoked on its own.
REST, MCP, the JS client outside the browser and `php caligrafer.php call --token` all take it. Since 2.1.0.

<br/>

## With Claude

```text
Create a token for my Claude Code on this laptop that can read and write books, for 90 days
```

Claude runs `php caligrafer.php token create` with the scopes, shows the token once, and never writes it in a file.

**Check** before you say OK: `php caligrafer.php token list` shows it `active` with the scopes you asked for.

<br/>

## By hand

1. **The framework tables.** `docker compose exec dev-box php caligrafer.php platform install` creates
   `caligrafy_tokens` (and the other framework tables; safe to run again).
2. **Create it.**
    ```bash
    docker compose exec dev-box php caligrafer.php token create "Bookshelf agent" --scopes books:read,books:write
    ```
    ```text
     Token #1 "Bookshelf agent" created with books:read books:write

     cq_1_<40 hexadecimal characters>

     It is shown only now: store it as a secret (Authorization: Bearer <token>).
    ```
3. **Use it.**
    ```bash
    curl -s -H "Authorization: Bearer cq_1_..." http://localhost:8080/api/books
    ```
4. **List and revoke.**
    ```bash
    docker compose exec dev-box php caligrafer.php token list
    docker compose exec dev-box php caligrafer.php token revoke 1
    ```
    ```text
     #1    active   Bookshelf agent                books:read books:write  never used
    ```

<br/>

## <a name="command"></a>The `token` command

```text
 php caligrafer.php token create <name> --scopes <a:read,b:write | *> [--user <id>] [--expires <days>]
 php caligrafer.php token list
 php caligrafer.php token revoke <id>
```

+ `--scopes` (required): `resource:action` values separated by commas; `books:*` for every action on books; `*` for everything.
+ `--user <id>`: the token acts for that user, so a capability's `level` applies to them.
+ `--expires <days>`: the token stops working after that many days.
+ `token list` shows `#<id>`, `active`, `revoked` or `expired`, the name, the scopes, the owner and the last use, never the
  secret. `token revoke` takes the number alone and stops the token at once.

<br/>

## <a name="scopes"></a>Scopes

A scope is `<resource>:<action>` in lowercase (`books:read`, `loans:write`). A capability declares the one it needs with
`scope: 'books:write'`; a token reaches it when it has that scope, `books:*`, or `*`. A capability with `scope: ''` is
reached by any token. A token with an owner also needs the owner's level; a token **without** an owner is limited by its
scopes only. All the rules: [Scopes and access](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access).

A revoked, expired or unknown token is a visitor: `401` on REST and MCP. A token whose owner was deleted is refused.

<br/>

## <a name="php"></a>From PHP

```php
use Caligrafy\Tokens;

$created = Tokens::create('Sam\'s agent', ['books:read', 'books:write'], $userId, time() + 86400 * 90);
// ['id' => 3, 'token' => 'cq_3_...']: the token is never shown again

Tokens::revoke(3);       // true when a token was revoked
Tokens::all();           // the rows, newest first, without their secret
```

`Tokens::verify('cq_...')` returns the token row (its scopes as a list) and records its use, or `null`. The expiry is a
Unix time (or a local `'Y-m-d H:i:s'`, stored as given); a Unix time is stored so that the token never stops early when
the clocks fall back.

<br/>

## <a name="online"></a>Tokens for an application online

An application on Caligrafy Cloud or your own server has no shell for `token create`. Give signed-in people a page that
creates their own token: a capability that calls `Tokens::create($name, $scopes, $context->user->id, $expiresAt)`, refuses
when `$context->token !== null` (a token must not create tokens), and a page that shows the token once with the
`claude mcp add` line.

Reference: [`Caligrafy\Tokens`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#tokens),
[`Tokens::create()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#tokens-create).

<br/>

## Common mistakes

+ **`token revoke #2`** (G77): it reads `#2` as 0 and revokes nothing. Use `token revoke 2`.
+ **No page to create tokens online** (G79): agents cannot connect to a deployed application without one.
+ **An expiry as a date string near a clock change** (G91): give it as a Unix time (`time() + 86400 * 90`).
+ **Trusting the level with an application token** (G53): create agent tokens with `--user <id>` when the level matters.
+ **A token or a key in a page or a Vue bundle**: browser code uses the session; tokens are for programs and agents.

<br/>

## Related pages

+ [MCP for AI agents](https://github.com/caligrafy/caligrafy-quill/wiki/MCP-for-AI-Agents) · [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/REST-API)
+ [Authentication: users, tokens and agents](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#agents)
+ [Caligrafer: `token`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#token)
