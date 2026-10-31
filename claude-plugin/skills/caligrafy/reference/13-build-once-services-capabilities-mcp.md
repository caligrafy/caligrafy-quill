# 11. Build Once: Services, Capabilities and MCP

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Build once, experience everywhere** is what Caligrafy is for. Write a feature once and use it everywhere: from your Pug pages, your Vue apps, a REST API and AI agents. In Caligrafy a feature written this way is a **capability**: a small PHP class (a **service**) that validates its inputs, checks who is asking, does the work and returns data. Every way of reaching your application is a **channel** that calls the same capability, so a rule you write once behaves the same for a person, a phone or an agent.

## How it fits together

| Channel | How it calls a capability | Who is calling |
| --- | --- | --- |
| Pug pages | `$this->call('create_book')` in a controller action | the signed-in user |
| Vue apps and the Vite SPA | the generated JS client (`php caligrafer.php client`) | the signed-in user (session cookie + CSRF token) |
| REST | `Route::capability('POST', '/books', 'create_book')` | a personal access token, or the signed-in user |
| AI agents | MCP: `Route::mcp('/mcp')` over HTTP, or `php caligrafer.php mcp` over stdio | a personal access token |
| Scripts | `Caligrafy\Capabilities::call('create_book', $input, Context::cli())` | the command line |

Every call goes through the same steps, in this order:
1. **Find** the capability (`not_found`, 404).
2. **Check who calls**: public, or signed in or a token (`unauthenticated`, 401), with the right scope and level (`forbidden`, 403).
3. **Keep, filter and validate** the declared inputs (`invalid`, 422, with a message per field).
4. **Run** the service.
5. **Audit**: every refusal, and every success of a capability that changes data, is written to the audit log.

Your existing controllers, models and routes keep working: capabilities are added beside them, one at a time.

## Set up a project

1. Create the framework tables (personal access tokens and the audit log). It is safe to run again:
    ```bash
    php caligrafer.php platform install
    ```
    With Docker, run it in the container: `docker compose exec dev-box php caligrafer.php platform install`.
2. Add the channels you want to `application/config/routing/web.php`, before `Route::run()`:
    ```php
    Route::capabilities('/capabilities');   // GET lists, POST /capabilities/{name} calls (the JS client uses it)
    Route::mcp('/mcp');                     // AI agents (MCP over HTTP)
    ```
3. Check: `php caligrafer.php doctor` reports `Framework tables: caligrafy_tokens and caligrafy_audit installed`.

## Write a capability

Create it with Caligrafer:
```bash
php caligrafer.php service Books/CreateBook --scope books:write
```
This writes `application/services/Books/CreateBook.php`. Fill in its inputs and its work:
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'create_book',
    description: 'Adds a book to the catalog and returns it with its id.',
    scope: 'books:write'
)]
class CreateBook extends Service
{
    public $rules = array('title' => 'required|max_len,255', 'author' => 'required|max_len,255', 'publisher_id' => 'required|integer');
    public $filters = array('title' => 'trim', 'author' => 'trim');
    public $descriptions = array('title' => 'The title', 'author' => 'The author', 'publisher_id' => 'The id of the publisher');

    public function handle(array $input, Context $context)
    {
        if (!(new Publisher('publishers'))->find($input['publisher_id'])) {
            throw ServiceError::invalid(array('publisher_id' => 'There is no such publisher'));
        }
        return (new Book('books'))->create($input);
    }
}
```

+ **`#[Capability]`** declares it:

    | Argument | Meaning |
    | --- | --- |
    | `name` | snake_case, unique in the application: how every channel calls it |
    | `description` | one or two sentences: what it does and what it returns. AI agents read it to choose the tool: write it for them |
    | `scope` | the scope a token needs, `resource:action` (`books:write`). Empty: any token |
    | `level` | the minimum value of the `permissions` column of the user (0: any signed-in user) |
    | `readOnly` | `true` when it changes nothing: not audited when it succeeds, and agents may call it freely |
    | `destructive` | `true` when it deletes or overwrites data: agents ask the user before calling it |
    | `public` | `true` when anyone may call it, without signing in or a token |

+ **`$rules`** are [GUMP validation rules](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation). Only the inputs listed there reach `handle()`; use `''` for an input that has no rule. They also give the JSON Schema that clients and agents see: `required`, `integer`, `numeric`, `boolean`, `valid_email`, `max_len`, `min_len`, `min_numeric`, `max_numeric`, `contains_list` and `date` are translated.
+ **`$filters`** are GUMP filters applied before validation, **`$descriptions`** describe each input in the schema.
+ **`handle()`** returns data: an array, a model, a list of models or a value (models are returned with their columns only). To refuse, throw a `ServiceError`:

    | Error | Status | When |
    | --- | --- | --- |
    | `ServiceError::invalid(['field' => 'message'])` | 422 | an input is wrong |
    | `ServiceError::notFound('No book #12')` | 404 | the thing does not exist |
    | `ServiceError::forbidden()` | 403 | this caller may not do it (beyond the scope and level checks) |
    | `ServiceError::conflict('That title is taken')` | 409 | it clashes with the current state |
    | `ServiceError::unauthenticated()` | 401 | nobody is signed in |

+ **A service never renders a view, redirects, prints or reads the request or the session.** The channel does that. The `Context` tells it who calls: `$context->user` (the signed-in user or the owner of the token), `$context->token`, `$context->channel` (`web`, `rest`, `mcp`, `cli`) and `$context->allows('books:write', 1)`.

List what the application offers:
```bash
php caligrafer.php capabilities              # a table
php caligrafer.php capabilities --markdown   # for your documentation
php caligrafer.php capabilities --json       # with the input schemas
```

## From a Pug page

A controller action calls the capability as the signed-in user and renders the result. The inputs default to the posted fields and the route variables:
```php
use Caligrafy\Controller;
use Caligrafy\ServiceError;

class BookController extends Controller
{
    public function store()
    {
        try {
            $book = $this->call('create_book');
            redirect('/books/' . $book['id']);
        } catch (ServiceError $e) {
            return view('Books/form', array('errors' => $e->fields, 'message' => $e->getMessage()));
        }
    }
}
```
The form still sends `csrf()` as usual.

## REST

Map routes to capabilities in `web.php`. Route variables become inputs:
```php
Route::capability('GET', '/v2/books', 'list_books');
Route::capability('GET', '/v2/books/{id}', 'get_book');
Route::capability('POST', '/v2/books', 'create_book');
Route::capability('PUT', '/v2/books/{id}', 'update_book');
Route::capability('DELETE', '/v2/books/{id}', 'delete_book');
```
`Route::capabilities('/capabilities')` adds two more: `GET /capabilities` lists what the caller may call (with the input schemas, and the session's CSRF token in `csrf`), and `POST /capabilities/{name}` calls any capability by name.

+ **Requests**: send `Accept: application/json` and a JSON body (or form fields; the query string for `GET`).
+ **Responses**: `200` with `{"data": ...}`, `201` when a `POST` creates, or an error:
    ```json
    {"error": {"code": "invalid", "message": "Some of the inputs are invalid", "fields": {"title": "The Title field is required"}}}
    ```
+ **Who calls**: `Authorization: Bearer <personal access token>`, the legacy API key, or the session of a signed-in user. A call made with the session that changes data needs the CSRF token in an `X-CSRF-Token` header (the JS client does it). Without any of these: `401` with `WWW-Authenticate: Bearer`.

```bash
curl -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" http://localhost:8080/v2/books
curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
     -d '{"title": "Dune", "author": "Frank Herbert", "publisher_id": 1}' http://localhost:8080/v2/books
```

## Personal access tokens

A token gives a program or an agent exactly the rights it needs, and can be revoked on its own.
```bash
php caligrafer.php token create "Claude Code on my laptop" --scopes books:read,books:write
php caligrafer.php token create "Reporting job" --scopes books:read --expires 90
php caligrafer.php token create "Alice's agent" --scopes "books:*" --user 7
php caligrafer.php token list
php caligrafer.php token revoke 3
```
+ The token (`cq_<id>_<secret>`) is shown **only once**: store it as a secret. Only its SHA-256 hash is kept, in `caligrafy_tokens`, with its name, scopes, owner, expiry, last use and revocation date.
+ **Scopes** are `resource:action`: `books:read`, `books:write`, `books:*` (every action on books) or `*` (everything).
+ **`--user`**: the token acts for that user, so the `level` of a capability applies to that user too. Without `--user` it is an application token: only its scopes count.
+ Controller routes of `web.php` (the REST API of [9. Helpers, Forms & REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API)) accept tokens with the scope `*`, like the old key.

**The legacy API key.** Keys made with `generatekeys` and `generateapikey` all encrypt the same `APP_KEY`: each one gives full access, and none can be revoked without changing `APP_KEY`. They keep working so that existing clients do not break. Once your clients use tokens, turn them off in `.env`:
```
LEGACY_API_KEY=false
```
`doctor` reminds you while they are allowed. Never put a token or a key in a page or a Vue bundle: browser clients use the session (next section).

## Vue apps and the Vite SPA: the JS client

Generate a JavaScript module with one function per capability:
```bash
php caligrafer.php client public/<app>/src/capabilities.js     # Vite SPA
php caligrafer.php client public/js/capabilities.js            # Vue as a library (the default path)
```
```js
import { createBook, listBooks, CapabilityError } from './capabilities.js'

try {
  const book = await createBook({ title: this.title, author: this.author, publisher_id: 1 })
  this.books = await listBooks()
} catch (e) {
  if (e instanceof CapabilityError && e.code === 'invalid') this.errors = e.fields   // { title: '...' }
  else throw e
}
```
+ In a page of the application it uses the **session**: the cookie and the CSRF token, which it reads once from `GET /capabilities`. No key is ever in the bundle.
+ From Node or another origin, give it a token: `configure({ baseUrl: 'https://app.example.com', token: process.env.CALIGRAFY_TOKEN })`.
+ Run the command again after you add or change a capability. It needs `Route::capabilities('/capabilities')` (or `--prefix` with your own uri).

## AI agents: MCP

[MCP](https://modelcontextprotocol.io) (the Model Context Protocol) is how AI agents such as Claude use tools. With `Route::mcp('/mcp')` every capability becomes a tool: its name, its description, its input schema, and hints that tell the agent whether it only reads (`readOnlyHint`) or destroys data (`destructiveHint`). An agent sees only the tools its token allows, and its calls go through the same checks, validation and audit as everyone else's. A refused call comes back as a tool error with the reason and the field messages, so the agent can correct itself.

**Over HTTP** (a deployed application): create a token, then add the server to the agent. For Claude Code:
```bash
php caligrafer.php token create "Claude Code" --scopes books:read,books:write
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer cq_..."
```
+ Answers JSON-RPC 2.0 `POST` requests with JSON (`initialize`, `ping`, `tools/list`, `tools/call`); `GET` answers `405`.
+ Only personal access tokens are accepted (not the legacy key): `401` otherwise.
+ A browser request from another site is refused (`403`). To allow a browser-based client such as an inspector, list its origin in `.env`: `MCP_ALLOWED_ORIGINS=https://inspector.example`.

**Over stdio** (a local agent on your machine, in the project folder):
```bash
claude mcp add bookshelf -- php caligrafer.php mcp
claude mcp add bookshelf -- docker compose exec -T dev-box php caligrafer.php mcp    # with the Docker server
CALIGRAFY_TOKEN=cq_... php caligrafer.php mcp      # limited to a token's scopes
```
Without a token, stdio has full access, like any script you run yourself.

## The audit log

```bash
php caligrafer.php audit                              # the latest 50 calls
php caligrafer.php audit --capability delete_book --limit 200
```
Each row says when, who (`user`, `token`, `key`, `cli` or `guest` and its id), through which channel (`web`, `rest`, `mcp`, `cli`), which capability and the outcome (`ok`, `invalid`, `forbidden`...). Inputs are never stored. Read-only capabilities are only logged when they are refused.

## One look on every device

`public/css/tokens.css` defines the Caligrafy look as CSS custom properties (`--cq-color-ink`, `--cq-space-3`, `--cq-touch-target`...), from `public/css/tokens.json`, so that Pug pages, Vue apps and the SPA share it. It is included by the default layout, the Vue app template and the SPA template, and it adds:
+ larger touch targets on touch screens (`pointer: coarse`),
+ safe-area padding when the app is installed to the home screen,
+ `.cq-split`: one column on phones, a list and its detail side by side from tablets up.

Combine it with [install to home screen](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install) for a mobile app that uses the same capabilities as the web.

## Testing a capability

A service can be run directly, without the channels, in a PHPUnit test or a script:
```php
$book = (new CreateBook())->run(array('title' => 'Dune', 'author' => 'Frank Herbert', 'publisher_id' => 1));
$book = Caligrafy\Capabilities::call('create_book', $input, Caligrafy\Context::cli());   // with the checks and the audit
```
The Caligrafy QA suite proves the model end to end with the Bookshelf capabilities: a book created on a web page is the same through REST, MCP and the JS client, and invalid input, a missing scope or a too-low level are refused the same way everywhere ([Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy)).

>[Next Section: Claude Code Plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
