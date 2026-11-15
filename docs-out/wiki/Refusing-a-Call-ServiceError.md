A capability refuses a call by throwing a `Caligrafy\ServiceError`. It never prints an error, redirects or picks an
HTTP status itself: each channel turns the same refusal into its own form, a status and a JSON error for REST, a tool
error for an agent, the errors of a form for a page. Since 2.1.0.

<br/>

## With Claude

Say when the capability must refuse:

```text
/caligrafy-quill:new-capability lend_book loans:write: lend a book to a reader; refuse when the book is already out
```

Claude throws `ServiceError::notFound()` for a book that does not exist and `ServiceError::conflict()` when it is lent.

**Check** before you say OK: `php caligrafer.php call <name> ...` with the case that must be refused prints
`{"error": {"code": ...}}` with the code you expect, and exits with 1.

<br/>

## By hand

```php
use Caligrafy\ServiceError;

public function handle(array $input, Context $context)
{
    $book = Book::find($input['book_id']);
    if (!$book) {
        throw ServiceError::notFound('There is no book #' . $input['book_id']);
    }
    if (Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->exists()) {
        throw ServiceError::conflict('This book is already lent');
    }
    // ...
}
```

The invalid inputs of `$rules` are refused before `handle()` runs, with a message per field: you throw `invalid` only
for what the rules cannot say.

```php
throw ServiceError::invalid(['due_at' => 'The due date must be after today']);
```

<br/>

## <a name="kinds"></a>The five kinds

| Throw | Code | Status | Default message | When |
| --- | --- | --- | --- | --- |
| `ServiceError::invalid(['field' => 'message'], $message)` | `invalid` | 422 | Some of the inputs are invalid | an input is wrong |
| `ServiceError::unauthenticated($message)` | `unauthenticated` | 401 | Sign in or send a personal access token | nobody is signed in |
| `ServiceError::forbidden($message)` | `forbidden` | 403 | You are not allowed to do this | this caller may not do it |
| `ServiceError::notFound($message)` | `not_found` | 404 | Not found | the thing does not exist |
| `ServiceError::conflict($message)` | `conflict` | 409 | (a message is required) | it clashes with the current state |

`new ServiceError($kind, $message = '', $fields = [])` does the same; any other kind throws `InvalidArgumentException`.
On the error: `$e->kind`, `$e->fields`, `$e->getMessage()`, `$e->status()` and `$e->toArray()`, which is
`{"error": {"code": "invalid", "message": "...", "fields": {...}}}` (`fields` only when there are some).

The framework throws the same errors itself: `not_found` for an unknown capability, `unauthenticated` and `forbidden`
("This needs the scope books:write") from the [access checks](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access),
`invalid` from the [input rules](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Inputs).

<br/>

## <a name="channels"></a>What each channel makes of it

| Channel | What the caller gets |
| --- | --- |
| REST (`Route::capability`, `Route::capabilities`) | the status of the kind and `$e->toArray()` as JSON; `401` also sends `WWW-Authenticate: Bearer` |
| MCP | a tool result with `"isError": true` and `$e->toArray()` as its text, so the agent can read the reason and correct itself |
| `php caligrafer.php call` | `$e->toArray()` as JSON, exit code `1` |
| The JS client | a `CapabilityError` with `status`, `code`, `message` and `fields` |
| `Route::form`, `Route::action` (a browser) | `invalid` or `conflict`: back, with `errors()`, `old()`, `flash('error')`; `unauthenticated`: the sign-in page; `forbidden`: `403` `[ERROR 1006]`; `not_found` or anything else: `404` `[ERROR 1005]` |
| `Route::form`, `Route::action` (a JSON request) | the status of the kind and the REST error |
| `Route::page` data | a visitor goes to sign in (with the authentication module), otherwise `403` or `404`; with `'notFound' => 'Folder/view'` that view, its `$message` the refusal |
| Snap | an Ask card for `invalid` (the fields to fix), a notice for the others |

Every refusal is written to the [audit log](https://github.com/caligrafy/caligrafy-quill/wiki/The-Audit-Log), its outcome
the code and its detail the message. Any other exception is written with the outcome `error` and the exception's class,
then thrown on: it is a bug, not a refusal.

Reference: [`ServiceError`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#serviceerror),
[`Capabilities::call()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-call).

<br/>

## Related pages

+ [Declarative routes: forms](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form): `errors()`, `old()` and `flash()` in the view
+ [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/REST-API) · [MCP for AI agents](https://github.com/caligrafy/caligrafy-quill/wiki/MCP-for-AI-Agents) · [The JS client](https://github.com/caligrafy/caligrafy-quill/wiki/The-JS-Client)
+ [The context](https://github.com/caligrafy/caligrafy-quill/wiki/The-Context): who calls, to decide what to refuse
