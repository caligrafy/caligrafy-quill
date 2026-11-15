The **agent commands** of [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) are for programs
and AI agents that call your capabilities: `token` creates, lists and revokes personal access tokens, `mcp` serves the
capabilities to an agent on this computer, and `audit` shows what was called and refused.

<br/>

## With Claude

```text
create a token for my reporting agent with books:read, valid 90 days
connect Claude Code to this app's capabilities
```

The `new-capability` skill runs `token create` (and shows the token once, without keeping it), and adds the app to
Claude Code with `claude mcp add ... php caligrafer.php mcp`.

**Check** before you say OK: `php caligrafer.php token list` shows the token `active` with only the scopes you asked
for, and `php caligrafer.php audit` shows the agent's calls.

<br/>

## By hand

```bash
docker compose exec dev-box php caligrafer.php platform install        # the token and audit tables, once
docker compose exec dev-box php caligrafer.php token create "books agent" --scopes books:read,books:write
claude mcp add bookshelf -- docker compose exec -T dev-box php caligrafer.php mcp
docker compose exec dev-box php caligrafer.php audit --limit 20
```

(without Docker, drop `docker compose exec dev-box`, and `-T`).

<br/>

## <a name="token"></a>`token`

```text
 Usage:
 php caligrafer.php token create <name> --scopes <a:read,b:write | *> [--user <id>] [--expires <days>]
 php caligrafer.php token list
 php caligrafer.php token revoke <id>
```

### `token create`

```bash
php caligrafer.php token create "books agent" --scopes books:read,books:write
php caligrafer.php token create "Reporting job" --scopes books:read --expires 90
php caligrafer.php token create "Sam's agent" --scopes "books:*" --user 7
```

```text
 Token #2 "books agent" created with books:read books:write

 cq_2_...

 It is shown only now: store it as a secret (Authorization: Bearer <token>).
```

| Option | Effect |
| --- | --- |
| `--scopes` (required) | `resource:action` values, separated by commas or spaces; `books:*` for every action on books; `*` for everything. A value of another shape is left out; with none left: ` A token needs scopes: --scopes books:read,books:write (or * for everything)` |
| `--expires <days>` | the token stops working after that many days (at least 1); the line says `, expires <date>` |
| `--user <id>` | the token acts for that user, so a capability's `level` applies to them and `$context->user` is that user. A user that does not exist: ` There is no user #<id>: no token was created.` |

Only the token's SHA-256 hash is stored: the token itself is printed once. A program sends it as
`Authorization: Bearer cq_...`.

### `token list`

```text
 #2    active   books agent                    books:read books:write  last used 2026-11-07 23:17:49
 #3    expired  Reporting job                  books:read  user 7  never used
```

The number, the state (`active`, `expired` or `revoked`), the name, the scopes, the user it acts for, and when it was
last used. With none: ` No tokens yet: php caligrafer.php token create <name> --scopes <scopes>`.

### `token revoke`

```bash
php caligrafer.php token revoke 2
```

```text
 Token #2 revoked
```

It takes the number without `#`, and stops the token at once. A number with no active token:
` No active token #<id>`.

Every `token` command needs the database and the framework tables: without them,
` The token table does not exist yet: run php caligrafer.php platform install`. The class behind them is
[`Caligrafy\Tokens`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#tokens).

<br/>

## <a name="mcp"></a>`mcp`

```text
mcp [--token <token>]: MCP server over stdio for local AI agents (Claude Code, Cursor...): php caligrafer.php mcp
```

```bash
claude mcp add myapp -- php caligrafer.php mcp                                  # your own PHP
claude mcp add myapp -- docker compose exec -T dev-box php caligrafer.php mcp   # Docker
CALIGRAFY_TOKEN=cq_... php caligrafer.php mcp                                   # only what the token allows
```

+ **The protocol.** One JSON-RPC message per line on standard input, one answer per line on standard output; empty
  lines are skipped, notifications get no answer. The messages are those of
  [`Caligrafy\Mcp\Server`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#mcp-server): `initialize`,
  `ping`, `tools/list` and `tools/call`. PHP errors go to standard error, so they never break the stream.
+ **The tools** are the capabilities, with their input schemas and the hints agents read before acting
  (`readOnlyHint`, `destructiveHint`). A call goes through the same checks, validation and audit as every other
  channel (channel `mcp`).
+ **Without a token** it has full access, like any script you run, and says so on standard error:
  `caligrafer mcp: no token, full local access (set CALIGRAFY_TOKEN to limit it)`.
+ **With a token** (`--token <token>`, or `CALIGRAFY_TOKEN`) it sees and calls only what the token's scopes allow. It
  needs the database (`caligrafer mcp: A token needs the database: set DB_ACTIVATE=true`), and a token that is unknown,
  revoked or expired stops it with exit code `1`.
+ **Agents on another computer** reach the application over HTTP: `Route::mcp('/mcp')` and a token. See
  [AI agents: MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp).

<br/>

## <a name="audit"></a>`audit`

```text
audit [--limit 50] [--capability <name>]: the latest capability calls
```

```bash
php caligrafer.php audit
php caligrafer.php audit --capability create_book --limit 200
```

```text
 2026-11-07 23:17:49  rest  token  2            create_book                  ok
 2026-11-07 23:17:49  rest  guest  -            create_book                  unauthenticated  Sign in or send a personal access token
```

| Column | What it says |
| --- | --- |
| when | `occurred_at`, in the server's time zone |
| channel | `web`, `rest`, `mcp` or `cli` (scheduled and background jobs run on `cli`) |
| who | `user`, `token`, `key`, `cli`, `guest`, or the system that ran it (`schedule`, `queue`) |
| id | the user's or the token's id, the job's name for a system, `-` for nobody |
| capability | its name |
| outcome | `ok`, `invalid`, `unauthenticated`, `forbidden`, `not_found`, `conflict` or `error`, and a short detail |

Every call that changes data and every refusal is recorded; a successful read is not, and inputs never are. With no
rows: ` The audit log is empty (or php caligrafer.php platform install was not run)`. The class behind it is
[`Caligrafy\Audit`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#audit).

<br/>

## Common mistakes

+ **`token revoke #2`** (G77): `#2` is read as `0` and nothing is revoked (` No active token #0`). Write
  `token revoke 2`.
+ **Tokens for an app online** (G79): an app on Caligrafy Cloud or your own server has no shell for `token create`.
  Give the app a page where signed-in people create their own tokens (a capability that calls `Tokens::create()` and
  refuses token contexts).
+ **A wrong token is a visitor** (G140): REST treats an unknown, revoked or expired `Bearer` token as no token, so a
  public capability still answers `200` (MCP over HTTP answers `401`). Check with `token list` that it is `active`.
+ **A token without `--user` passes any `level`** (G140): the level is a check on people. Keep the scopes of
  administrator capabilities off application tokens, or check the person in `handle()`.
+ **`mcp` with full access for an agent you do not control**: give it `CALIGRAFY_TOKEN` with the scopes it needs.
+ **A scope with a typo**: `--scopes book:read` creates a token for `book:read`, which no `books:read` capability
  accepts. Read the scopes `token create` prints.

<br/>

## Related pages

+ [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#tokens) · [the audit log](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#audit)
+ [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#agents)
+ [API reference: interfaces](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
