The **audit log** keeps one row per capability call that changes data, and per refused call: when, through which
channel, who (a person, a token, the command line, a job), which capability and the outcome. Inputs are never stored.
It is written by `Capabilities::call()`, so every channel is in it: pages, forms, REST, the JS client, MCP, the command
line, the queue and the scheduler. Since 2.1.0.

<br/>

## By hand

1. **The table.** `docker compose exec dev-box php caligrafer.php platform install` creates `caligrafy_audit`. Without it
   nothing is written and nothing fails.
2. **Read it.**
    ```bash
    docker compose exec dev-box php caligrafer.php audit --capability create_book --limit 5
    ```
    ```text
     2026-11-07 23:18:39  mcp   token  1            create_book                  invalid  Some of the inputs are invalid
     2026-11-07 23:18:25  rest  token  1            create_book                  invalid  Some of the inputs are invalid
     2026-11-07 23:18:25  rest  token  1            create_book                  ok
     2026-11-07 23:18:25  rest  token  2            create_book                  forbidden  This needs the scope books:write
     2026-11-07 23:18:25  rest  guest  -            create_book                  unauthenticated  Sign in or send a personal access token
    ```
    `audit` alone shows the latest 50 calls of every capability.

<br/>

## <a name="what"></a>What is written

| A call | Written |
| --- | --- |
| succeeds, and the capability is not `readOnly` | yes, outcome `ok` |
| succeeds, and the capability is `readOnly` | no |
| is refused with a `ServiceError` | yes, outcome its code (`invalid`, `unauthenticated`, `forbidden`, `not_found`, `conflict`) and its message |
| throws any other exception | yes, outcome `error`, detail the exception's class |

The columns of `caligrafy_audit`:

| Column | Value |
| --- | --- |
| `occurred_at` | when (PHP's clock, `SERVER_TIMEZONE`) |
| `actor_type` | `user`, `token`, `key` (the legacy API key), `cli`, `guest`, or the system of a job or an event (`schedule`, `queue`, `webhook`) |
| `actor_id` | the token id or the user id, or the job's name; empty for the command line, the legacy key and guests |
| `channel` | `web`, `rest`, `mcp` or `cli` |
| `capability` | its name |
| `outcome` | `ok` or the refusal's code, or `error` |
| `detail` | a short note (the refusal's message), 500 characters at most |

A change confirmed in [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant) is also
written as `snap_confirm`, its detail what was confirmed.

<br/>

## <a name="php"></a>From PHP

```php
use Caligrafy\Audit;

$rows = Audit::latest(200, 'delete_book');   // newest first, 1 to 1000 rows; the capability is optional
```

`Audit::record($context, $capability, $outcome, $detail)` writes a row yourself (it returns `false` instead of failing
when the table is missing). Show the log to administrators with a read-only capability of `level: 1` that returns
`Audit::latest()`.

Reference: [`Caligrafy\Audit`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#audit),
[`Audit::latest()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#audit-latest).

<br/>

## Common mistakes

+ **Looking for the successful reads**: a read-only capability is written only when it is refused.
+ **The audit time and the `created_at` of rows differ by hours** (G65): before 2.3.0 the audit used PHP's clock and the
  `DEFAULT CURRENT_TIMESTAMP` columns MySQL's. Since 2.3.0 `Database::connect()` sets MySQL's session time zone to PHP's
  (`DB_TIMEZONE=server` keeps MySQL's); rows written before the upgrade keep their old times.

<br/>

## Related pages

+ [Read-only and destructive capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Read-Only-and-Destructive-Capabilities)
+ [The context](https://github.com/caligrafy/caligrafy-quill/wiki/The-Context): the actor types
+ [Caligrafer: `audit`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#audit)
