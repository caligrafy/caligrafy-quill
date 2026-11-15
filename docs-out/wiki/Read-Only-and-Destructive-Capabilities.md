Two options of `#[Capability(...)]` say what a capability does to the data: `readOnly: true` (it changes nothing) and
`destructive: true` (it deletes or overwrites). They are promises the channels act on: where a capability may be used,
what is audited, and whether an agent or the assistant asks the person first. A capability with neither **writes**.
Since 2.1.0.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability list_books books:read read-only public: the catalog, 20 per page
/caligrafy-quill:new-capability delete_book books:write destructive: only administrators (level 1)
```

**Check** before you say OK: `php caligrafer.php capabilities` shows `read`, `writes` or `destroys` in the third column,
as you expect.

<br/>

## By hand

`php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only` writes `readOnly: true`. There is no flag
for `destructive`: add it to the attribute.

```php
#[Capability(name: 'list_books', description: 'Lists the books of the catalog, sorted by title, 20 per page.', scope: 'books:read', readOnly: true)]
#[Capability(name: 'delete_book', description: 'Deletes a book of the catalog for good, with its loans and its tags. Returns the id of the deleted book.', scope: 'books:write', level: 1, destructive: true)]
```

(one attribute per class). Declare only what is true: `readOnly` on a capability that writes would let a page or a link
change data without a trace in the audit log.

<br/>

## <a name="readonly"></a>What `readOnly: true` changes

| Where | A read-only capability | Any other |
| --- | --- | --- |
| The audit log | written only when it is refused | every call: success and refusal |
| `Route::page(..., ['data' => [...]])` | can be page data | refused: `LogicException` "changes data, and opening a page must not" |
| `Route::download` | can be downloaded | refused: `LogicException` "changes data, and opening a link must not" |
| REST with the session cookie | no CSRF token needed | needs `X-CSRF-Token` (403 without it) |
| REST `POST` | `200` | `201` |
| MCP | `"readOnlyHint": true` | `"readOnlyHint": false` |
| Snap | a card with nothing to ask runs at once; the assistant may call it to look things up | comes back as an Ask: Confirm card first |

<br/>

## <a name="destructive"></a>What `destructive: true` changes

+ **MCP**: `"destructiveHint": true` on the tool (only when it is not also read-only), and the server's `initialize`
  instructions tell the agent: "Tools marked destructive delete or overwrite data: confirm with the user first."
+ **`php caligrafer.php capabilities`**: `destroys` instead of `writes`; `GET /capabilities` and `--json` give
  `"destructive": true`.
+ **The JS client**: the function's comment says `destructive`.
+ **Snap**: the card is a `destroy` risk (a `write` one without it); like every change, it runs only once the person has
  confirmed its Ask: Confirm card.

Nothing else differs: the checks, the validation and the audit are those of every capability that writes.

<br/>

## <a name="snap"></a>Snap and the assistant

In [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant), nothing that changes data runs
from a card until the person confirms it: a capability that is not read-only answers an **Ask: Confirm** card with a
one-time token bound to that person, that capability and those inputs, for 10 minutes. The confirmed change is written to
the audit log as `snap_confirm`. The assistant (Claude) may call only the read-only capabilities the person may call, to
look things up; a change it proposes comes back as its Confirm card. A slow read-only capability declared
[`async: true`](https://github.com/caligrafy/caligrafy-quill/wiki/Async-Capabilities) is proposed as a task instead of
being run in the turn.

Reference: [`Capabilities::call()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-call),
[`Mcp\Server::tools()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#mcp-server-tools),
[`Snap\Consent`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-consent).

<br/>

## Common mistakes

+ **A capability that is not read-only as page data** (G83): refused. Pages show read-only capabilities; forms use
  `Route::form`.

<br/>

## Related pages

+ [The capability attribute](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Attribute) · [The audit log](https://github.com/caligrafy/caligrafy-quill/wiki/The-Audit-Log)
+ [MCP for AI agents](https://github.com/caligrafy/caligrafy-quill/wiki/MCP-for-AI-Agents) · [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/REST-API)
+ [Declarative routes: downloads](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#download)
