Every capability gets a `Caligrafy\Context` as the second argument of `handle()`. It says **who calls, through which
channel, with which rights**: a signed-in person, a token, the command line, a job or a visitor. The channels build it
(a page, REST, MCP, the command line); a capability reads it instead of the request, the session or the headers, so the
same code works for every caller. Since 2.1.0.

<br/>

## With Claude

Say what depends on the caller:

```text
/caligrafy-quill:new-capability my_loans loans:read read-only: the loans of the person who asks, newest first
```

Claude reads `$context->userId()` in `handle()`, and refuses with `ServiceError::unauthenticated()` when there is none.

**Check** before you say OK: `php caligrafer.php call my_loans --guest` is refused, and two people each see only their own
rows.

<br/>

## By hand

```php
#[Capability(name: 'my_loans', description: 'Lists the loans of the person who asks, newest first.', scope: 'loans:read', readOnly: true)]
class MyLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        if (!$context->userId()) {
            throw ServiceError::unauthenticated();
        }
        return Loan::where('user_id', $context->userId())->latest()->get();
    }
}
```

A capability only a job may run (it works with full access) refuses everyone else by the actor type:

```php
if (!in_array($context->actorType(), array('queue', 'cli'), true)) {
    throw ServiceError::forbidden();
}
```

<br/>

## <a name="members"></a>What it holds

| Member | Meaning |
| --- | --- |
| `$context->channel` | `web`, `rest`, `mcp` or `cli` |
| `$context->user` | the user model: signed in, or the owner of the token (`null` otherwise) |
| `$context->token` | the token row (`id`, `name`, `scopes`, `user_id`...), or `null` |
| `$context->viaAction` | `true` when the call comes from `Route::action` (2.21.0): the only way to a [web-only capability](https://github.com/caligrafy/caligrafy-quill/wiki/Web-Only-Capabilities) |
| `$context->authenticated()` | anyone but a guest |
| `$context->allows('books:write', 1)` | whether this caller has that scope and level ([Scopes and access](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access)) |
| `$context->userId()` | the user's id, signed in or owner of the token |
| `$context->level()` | the user's `permissions` column (`Context::$levelAttribute`), `0` without a user |
| `$context->actorType()` | `user`, `token`, `key`, `cli`, `guest`, or the system of a job or an event (`schedule`, `queue`, `webhook`), as written to the audit log |
| `$context->actorId()` | the token id or the user id as a string, or the job's name; `null` for the command line, the legacy key and guests |

<br/>

## <a name="who"></a>Who the channels say calls

| Caller | How the context is made | Rights |
| --- | --- | --- |
| a request with `Authorization: Bearer cq_...` | `Context::fromRequest($request, $channel)`: the token, and its owner when it has one and authentication is on | the token's scopes (an unknown, revoked or expired token is a guest) |
| a request with the legacy API key | `fromRequest`: a full-access context | everything (actor `key`) |
| a signed-in person (the session) | `fromRequest`: the user | their level |
| nobody | `Context::guest($channel)` | public capabilities only |
| the command line and scripts | `Context::cli()` | everything (actor `cli`) |
| `php caligrafer.php mcp` without a token | `Context::local('mcp')` | everything (actor `cli`) |
| a scheduled job or another system task | `Context::system('schedule', $jobName)` | everything; the audit log records the type and the name |
| a personal access token in code | `Context::forToken($tokenRow, $channel)` | the token's scopes |

`fromRequest()` looks in that order: a token, then the legacy key, then the session, else a guest. The context is made
once, before the capability runs: when a [web-only capability](https://github.com/caligrafy/caligrafy-quill/wiki/Web-Only-Capabilities#signin)
signs someone in, it builds a new one with `Context::fromRequest(request(), 'web')` to call as that person.

Reference: [`Caligrafy\Context`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#context).

<br/>

## Common mistakes

+ **Reading the session or the request in a capability** (G52): the channel does that, and `$context` says who calls. Only a
  web-only capability may.
+ **A job capability that anyone may call**: it runs with full access, so check `actorType()`
  ([Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)).
+ **Trusting the level with an application token** (G53): a token without an owner passes any level.

<br/>

## Related pages

+ [Scopes and access](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access) · [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/Personal-Access-Tokens)
+ [Calling a capability from code](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Call-a-Capability-from-Code)
+ [The audit log](https://github.com/caligrafy/caligrafy-quill/wiki/The-Audit-Log)
