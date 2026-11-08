# 11. Build Once: Services, Capabilities and MCP

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Build once, experience everywhere** is what Caligrafy is for. A feature is written once, as a **capability**: a small
PHP class (a **service**) that declares its inputs, checks who is asking, does the work and returns data. Every way of
reaching your application is a **channel** that calls the same capability: your Pug pages (`Route::page`,
`Route::form`), a REST API, the JavaScript client of your Vue apps, and AI agents over MCP. A rule written once
behaves the same for a person, a phone, a program and an agent.

## How it fits together

| Channel | How it calls a capability | Who is calling |
| --- | --- | --- |
| Pug pages | `Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']])`; forms with `Route::form('/books', 'create_book', [...])` | the person who opens the page (signed in, or a visitor) |
| REST | `Route::capability('POST', '/api/books', 'create_book')`, or every capability under `Route::capabilities('/capabilities')` | a personal access token, or the signed-in user |
| Vue apps, the Vite SPA, Node | the generated JS client (`php caligrafer.php client`), on `Route::capabilities('/capabilities')` | the signed-in user (session and CSRF token), or a token |
| AI agents | MCP: `Route::mcp('/mcp')` over HTTP, or `php caligrafer.php mcp` over stdio | a personal access token (stdio: also your own full access) |
| The command line, scripts, jobs | `php caligrafer.php call create_book ...`, `Capabilities::call()`, the queue and the scheduler | full access, or a token |

Every call goes through the same steps, in this order:

1. **Find** the capability (`not_found`, 404).
2. **Check who calls**: public, or signed in or a token (`unauthenticated`, 401), with the right scope and level
   (`forbidden`, 403).
3. **Keep, filter and validate** the declared inputs (`invalid`, 422, with a message per field).
4. **Run** `handle()`.
5. **Audit**: every refusal, and every success of a capability that changes data, is written to the audit log.

**Only capabilities touch models**: pages, REST, agents and the rare controller all go through them, so the rules
exist once.

## With Claude

Ask for the feature with the `new-capability` skill of the Claude Code plugin: its name, its scope, and what it does.

```text
/caligrafy-quill:new-capability create_book books:write: add a book (title, published year, summary, author) and return it
/caligrafy-quill:new-capability delete_book books:write destructive: only administrators (level 1)
```

Claude:
+ runs `php caligrafer.php service <Folder>/<Name> --scope <scope>` and fills
  `application/services/<Folder>/<Name>.php`: a `description` written for an AI agent, `$rules` for every input,
  `handle()` with the query builder, `ServiceError` refusals, `readOnly` / `destructive` / `level` / `public`;
+ adds the channels you need to `application/config/routing/web.php` (`Route::page`, `Route::form`,
  `Route::capability`, `Route::capabilities`, `Route::mcp`) and runs `php caligrafer.php client` for a Vue app;
+ creates a token only when an agent or a program needs one, shows it once, and never writes it in a file;
+ checks every channel (`capabilities`, `call`, REST with `curl`, MCP `tools/list` and `tools/call`, `audit`), and
  writes a test in `tests/` (`php caligrafer.php test`).

**Check** before you say OK:
+ `php caligrafer.php capabilities` lists it with the scope and the flag you expect (`read`, `writes`, `destroys`);
+ the description says what it does and what it returns;
+ an invalid input gives a message per field, on the command line, over REST and over MCP alike;
+ a token without the scope gets `403` over REST, and does not see the tool in MCP `tools/list`;
+ `php caligrafer.php audit --capability <name>` shows the calls.

## By hand

This builds the Bookshelf capabilities and serves them on every channel. It starts from the tables and models of
[Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models), `get_book` of
[Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships) and `list_books` of
[Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries#capability). The server is the Docker server of the
[Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart), at `http://localhost:8080`; run each
`php caligrafer.php` command in its container, as written (without Docker, drop `docker compose exec dev-box` and use
your own address).

### 1. The framework tables

Run:
```bash
docker compose exec dev-box php caligrafer.php platform install
```
```text
 Framework tables ready: caligrafy_tokens, caligrafy_audit, caligrafy_job_runs, the notifications tables, caligrafy_jobs, caligrafy_cache and caligrafy_migrations
```
It is safe to run again. `docker compose exec dev-box php caligrafer.php doctor --only database` then shows
`OK    Framework tables: tokens, audit log, job runs, jobs, cache and migrations installed`.

### 2. Write the capabilities

1. Run:
    ```bash
    docker compose exec dev-box php caligrafer.php service Bookshelf/CreateBook --scope books:write
    ```
    ```text
     Created application/services/Bookshelf/CreateBook.php: capability 'create_book' (scope books:write)
    ```
2. Make `application/services/Bookshelf/CreateBook.php` read:
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Service;

    #[Capability(
        name: 'create_book',
        description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id.',
        scope: 'books:write'
    )]
    class CreateBook extends Service
    {
        public $rules = array(
            'title' => 'required|max_len,200',
            'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
            'summary' => 'max_len,2000',
            'author_id' => 'required|integer|exists,authors.id'
        );

        public $filters = array(
            'title' => 'trim',
            'summary' => 'trim'
        );

        public $descriptions = array(
            'title' => 'The title of the book',
            'published_year' => 'The year it was first published, for example 1969 (not after this year)',
            'summary' => 'Optional: one or two sentences about the book',
            'author_id' => 'The id of its author (list_authors)'
        );

        public function handle(array $input, Context $context)
        {
            $id = Book::insert($input);   // one INSERT, every value bound; returns the id of the new book
            return Book::with('author')->find($id);
        }
    }
    ```
3. Run `docker compose exec dev-box php caligrafer.php service Bookshelf/DeleteBook --scope books:write`, then make
   `application/services/Bookshelf/DeleteBook.php` read:
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Service;
    use Caligrafy\ServiceError;

    #[Capability(
        name: 'delete_book',
        description: 'Deletes a book of the catalog for good, with its loans and its tags. Returns the id of the deleted book.',
        scope: 'books:write',
        level: 1,
        destructive: true
    )]
    class DeleteBook extends Service
    {
        public $rules = array('id' => 'required|integer');

        public $descriptions = array('id' => 'The id of the book to delete');

        public function handle(array $input, Context $context)
        {
            if (Book::where('id', $input['id'])->delete() === 0) {
                throw ServiceError::notFound('There is no book #' . $input['id']);
            }
            return array('deleted' => (int) $input['id']);
        }
    }
    ```
    `level: 1`: a signed-in person needs `permissions` 1 or more in the `users` table (an administrator).
    `destructive: true`: agents ask the user before they call it.
4. Run `docker compose exec dev-box php caligrafer.php capabilities`. Among the framework's own capabilities
   (users, notifications, the queue), it lists:
    ```text
     create_book                  books:write    writes    Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id.
     delete_book                  books:write    destroys  Deletes a book of the catalog for good, with its loans and its tags. Returns the id of the deleted book.
     get_book                     public         read      Returns one book by its id, with its author, its tags and its loans.
     list_books                   public         read      Lists the books of the Bookshelf catalog, sorted by title, 20 per page, ... (cut here)
    ```
5. Call it from the command line (full access, with the checks and the audit):
    ```bash
    docker compose exec dev-box php caligrafer.php call create_book '{"title": "The Dispossessed", "published_year": 1974, "author_id": 1}'
    ```
    ```json
    {
        "data": {
            "id": 6,
            "title": "The Dispossessed",
            "published_year": 1974,
            "summary": null,
            "author_id": 1,
            "created_at": "2026-11-07 23:17:13",
            "modified_at": "2026-11-07 23:17:13",
            "author": {
                "id": 1,
                "name": "Ursula K. Le Guin",
                "bio": "American author of science fiction and fantasy, known for the Earthsea books.",
                "created_at": "2026-11-07 23:16:40",
                "modified_at": "2026-11-07 23:16:40"
            }
        }
    }
    ```
    `call create_book title=Dune` answers the field messages:
    ```json
    {
        "error": {
            "code": "invalid",
            "message": "Some of the inputs are invalid",
            "fields": {
                "published_year": "The Published Year field is required",
                "author_id": "The Author Id field is required"
            }
        }
    }
    ```
    `call create_book title=Dune --guest` (as a visitor) answers `"code": "unauthenticated"`.

### 3. Pages

In `application/config/routing/web.php`, inside the `try` block and before `Route::run();`, add:
```php
    // BOOKSHELF: the pages
    Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books']]);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book'], 'notFound' => 'Bookshelf/notfound']);
```
Write the three views. `application/views/Bookshelf/books.pug`:
```pug
extends ../default/master/site

block title
  | Books · Bookshelf

block content
  section.cq-hero.cq-hero--page
    h1.cq-display Books
    p.cq-lead= $books['total'] . ' books in the catalog'

  section.cq-band
    .cq-inner
      if flash('success')
        .cq-alert.cq-alert--success= flash('success')
      if $books['data']
        ul.cq-rows
          each $book in $books['data']
            li.cq-row
              a(href=$home . 'books/' . $book['id'])= $book['title']
              span.cq-muted= ' by ' . $book['author']['name']
      else
        .cq-empty-state No books yet.
```
`application/views/Bookshelf/book.pug`:
```pug
extends ../default/master/site

block title
  | #{$book['title']} · Bookshelf

block content
  section.cq-hero.cq-hero--page
    h1.cq-display= $book['title']
    p.cq-lead= $book['author']['name'] . ', ' . $book['published_year']

  section.cq-band
    .cq-inner
      if flash('success')
        .cq-alert.cq-alert--success= flash('success')
      p= $book['summary']
      p= 'Tags: ' . implode(', ', array_column($book['tags'], 'name'))
```
`application/views/Bookshelf/notfound.pug`, the page of a book that does not exist (`$message` is the refusal of
`get_book`):
```pug
extends ../default/master/site

block title
  | Not found · Bookshelf

block content
  section.cq-hero
    h1.cq-display Not found
    p.cq-lead= $message
    .cq-cta
      a.btn.btn-primary(href=$home . 'books') All books
```
Open `http://localhost:8080/books`: the books, each with its author. `http://localhost:8080/books/3` shows *Kindred*,
*Octavia E. Butler, 1979*; `/books/99` shows *Not found*, *There is no book #99*, with status 404.

+ A page's `data` capabilities must be read-only. They run as the person who opens the page, with the route
  variables (`{id}`) and the query string as inputs, the route variables winning. The view gets plain arrays
  (`$book['title']`), plus `$authorized` and `$user`; never the query string.
+ A capability that refuses a visitor sends them to sign in (with the authentication module), or answers 403 or 404.
+ A form posts to a capability with no controller:
  `Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Added {title}'])`. It calls the
  capability as the signed-in person; on invalid input it goes back, and the view shows `errors('title')`,
  `old('title')` and `flash('error')`. Sign-in pages come from `Route::auth()`. Both are on
  [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form) and
  [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#routeauth).

### 4. A personal access token

A program or an agent proves who it is with a **personal access token**: it has a name, **scopes** (what it may do)
and, optionally, an owner and an expiry, and it can be revoked on its own. Run:
```bash
docker compose exec dev-box php caligrafer.php token create "Bookshelf agent" --scopes books:read,books:write
```
```text
 Token #1 "Bookshelf agent" created with books:read books:write

 cq_1_<40 hexadecimal characters>

 It is shown only now: store it as a secret (Authorization: Bearer <token>).
```
Keep it in your terminal for the next steps: `TOKEN=cq_1_...` (the whole token). Make a second one that can only read,
to see the refusals: `token create "Reader" --scopes books:read --expires 90` (it prints `..., expires <date in 90 days>`).

`docker compose exec dev-box php caligrafer.php token list` shows them, never their secret:
```text
 #2    active   Reader                         books:read  never used
 #1    active   Bookshelf agent                books:read books:write  never used
```

### 5. REST

In `web.php`, before `Route::run();`, add:
```php
    // BOOKSHELF: REST
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
```
The route variables (`{id}`) become inputs. Then run (a `POST` with neither a token nor `Accept: application/json`
is taken for a form and answers `403` `[ERROR 3000]`):
```bash
curl -s http://localhost:8080/api/books/3
# {"data":{"id":3,"title":"Kindred","published_year":1979,...}}            200: get_book is public

curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -d '{"title": "Dune"}' http://localhost:8080/api/books
# {"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}}   401

curl -s -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
     -d '{"title": "The Lathe of Heaven", "published_year": 1971, "author_id": 1}' http://localhost:8080/api/books
# {"data":{"id":7,"title":"The Lathe of Heaven","published_year":1971,"summary":null,"author_id":1,...,"author":{"id":1,"name":"Ursula K. Le Guin",...}}}   201

curl -s -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
     -d '{"title": "X", "published_year": 1971, "author_id": 42}' http://localhost:8080/api/books
# {"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"author_id":"There is no such author"}}}   422
```
With the Reader token, the same `POST` answers
`{"error":{"code":"forbidden","message":"This needs the scope books:write"}}` (403).

+ **Requests**: a JSON body, form fields, or the query string for `GET`. Then the route variables, which win.
+ **Responses**: `200` with `{"data": ...}`, `201` when a `POST` changes data, or `{"error": {"code", "message",
  "fields"}}` with 401 (and `WWW-Authenticate: Bearer`), 403, 404, 409 or 422.
+ **Who calls**: `Authorization: Bearer <token>`, or the session of a signed-in person. A call made with the session
  that changes data needs the session's CSRF token in an `X-CSRF-Token` header (403 `Missing or invalid CSRF token:
  send the X-CSRF-Token header` otherwise); the JS client sends it.

### 6. AI agents: MCP

[MCP](https://modelcontextprotocol.io) (the Model Context Protocol) is how AI agents such as Claude use tools. With
`Route::mcp('/mcp')` every capability is a tool: its name, its description, its input schema, and hints that say
whether it only reads (`readOnlyHint`) or destroys data (`destructiveHint`). An agent sees only the tools its token
allows, and its calls go through the same checks, validation and audit as everyone else's.

In `web.php`, before `Route::run();`, add:
```php
    Route::mcp('/mcp');   // AI agents: personal access tokens only
```
Without a token it answers `401` and a JSON-RPC error (2.19.1):
```bash
curl -s -i -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp
```
```text
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Bearer error="invalid_token", error_description="Send a personal access token: php caligrafer.php token create"
Content-Type: application/json

{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Unauthorized: send Authorization: Bearer <personal access token>"}}
```
Talk to it as an agent does, with `curl` and the token. Each request is one JSON-RPC message:
```bash
curl -s -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
     -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}' \
     http://localhost:8080/mcp
```
```json
{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{"listChanged":false}},"serverInfo":{"name":"caligrafy-app","title":"caligrafy-app","version":"2.19.1"},"instructions":"Tools are the capabilities of this application that your token allows. Tools marked destructive delete or overwrite data: confirm with the user first."}}
```
(`serverInfo.name` is your `APP_NAME`.) Then the tools:
```bash
curl -s -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
     -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' http://localhost:8080/mcp
```
The list has `create_book`, `delete_book` (`"destructiveHint": true`), `get_book` and `list_books`
(`"readOnlyHint": true`), and the framework's public `register_user` (which refuses every channel but web pages and
the command line). Each tool looks like this one:
```json
{"name": "create_book", "title": "Create book", "description": "Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id.",
 "inputSchema": {"type": "object", "properties": {
    "title": {"type": "string", "maxLength": 200, "description": "The title of the book"},
    "published_year": {"type": "integer", "minimum": 1450, "maximum": 2026, "description": "The year it was first published, for example 1969 (not after this year)"},
    "summary": {"type": "string", "maxLength": 2000, "description": "Optional: one or two sentences about the book"},
    "author_id": {"type": "integer", "description": "The id of its author (list_authors)"}},
  "additionalProperties": false, "required": ["title", "published_year", "author_id"]},
 "annotations": {"readOnlyHint": false, "destructiveHint": false, "openWorldHint": false}}
```
With the Reader token, `create_book` and `delete_book` are not in the list. Call a tool:
```bash
curl -s -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
     -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"create_book","arguments":{"title":"Dune"}}}' \
     http://localhost:8080/mcp
```
```json
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"{\"error\":{\"code\":\"invalid\",\"message\":\"Some of the inputs are invalid\",\"fields\":{\"published_year\":\"The Published Year field is required\",\"author_id\":\"The Author Id field is required\"}}}"}],"isError":true}}
```
A refused call is a tool result with `"isError": true` and the same fields as REST, so the agent can correct itself.
A successful one has `"isError": false`, the data as JSON in `content[0].text`, and the object in `structuredContent`.

**Connect Claude Code** to the server:
```bash
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer cq_..."
```
**Over stdio**, a local agent starts the server itself, in the project folder:
```bash
claude mcp add bookshelf -- docker compose exec -T dev-box php caligrafer.php mcp
```
Without a token, stdio has full access, like any command you run yourself (it says so on stderr:
`caligrafer mcp: no token, full local access (set CALIGRAFY_TOKEN to limit it)`). With `CALIGRAFY_TOKEN=cq_...` (or
`--token cq_...`) it has the rights of that token.

### 7. Vue apps and the Vite SPA: the JS client

In `web.php`, before `Route::run();`, add:
```php
    Route::capabilities('/capabilities');   // GET lists, POST /capabilities/{name} calls: the JS client uses it
```
Generate the client: one JavaScript function per capability. Run:
```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
```
```text
 public/js/capabilities.js written: 17 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)
 import { createBook, CapabilityError } from './capabilities.js'
```
(For a Vite SPA, write it in `public/<app>/src/capabilities.js`.) Use it in your app:
```js
import { createBook, listBooks, CapabilityError } from './capabilities.js'

try {
  const book = await createBook({ title: this.title, published_year: this.year, author_id: this.authorId })
  this.books = (await listBooks({ author_id: book.author_id })).data
} catch (e) {
  if (e instanceof CapabilityError && e.code === 'invalid') this.errors = e.fields   // { published_year: '...' }
  else throw e
}
```
+ In a page of the application it uses the **session**: the cookie, and the CSRF token it reads once from
  `GET /capabilities`. No key is ever in the bundle.
+ From Node or another site, give it a token: `configure({ baseUrl: 'http://localhost:8080', token: process.env.CALIGRAFY_TOKEN })`.
+ Run `client` again after you add or change a capability.

`GET /capabilities` answers what the caller may call: `{"data": [{"name", "description", "scope", "level", "readOnly",
"destructive", "public", "input"}, ...], "csrf": "<the session's CSRF token>"}`. `POST /capabilities/get_book` with
`{"id": 3}` calls `get_book`.

### 8. The audit log

Run:
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
Each row: when, the channel (`web`, `rest`, `mcp`, `cli`), who (`user`, `token`, `key`, `cli`, `guest`, or `schedule`
and `queue` for jobs) and its id, the capability, the outcome and a short note. Inputs are never stored. A read-only
capability is written only when it is refused.

**Check** the whole chapter:
+ `capabilities` lists your four capabilities with the right flags;
+ the same invalid `create_book` gives the same `fields` from `call`, REST and MCP;
+ the Reader token gets 403 from REST and does not see `create_book` in `tools/list`;
+ `audit` shows the calls of every channel.

## One look on every device

`public/css/tokens.css` defines the Caligrafy look as CSS custom properties (`--cq-color-ink`, `--cq-space-3`,
`--cq-touch-target`...), from `public/css/tokens.json`, so Pug pages, Vue apps and the SPA share it. The default
layout, the Vue app template and the SPA template include it. It gives larger touch targets on touch screens
(`pointer: coarse`), safe-area padding when the app is installed to the home screen, and `.cq-split` (one column on
phones, a list and its detail side by side from tablets up). Build each screen from the
[page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents), and combine it with
[install to home screen](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install) for a mobile app on the
same capabilities.

## Testing a capability

Your tests call capabilities the way the channels do, with the checks, the validation and the audit, in a
transaction that is rolled back:
```php
$book = $this->call('create_book', ['title' => 'Tehanu', 'published_year' => 1990, 'author_id' => $authorId], $this->user(7));
$this->assertCapabilityFails('unauthenticated', 'create_book', ['title' => 'Tehanu'], 'guest');
```
Run them with `php caligrafer.php test`: [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App).
A capability asks for what it uses in its constructor ([Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection)),
and can run later as a [background job](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) or on a
[schedule](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs).

## Reference

### `#[Capability(...)]`

| Argument | Default | Meaning |
| --- | --- | --- |
| `name` | (required) | snake_case, letters, digits and `_`, starting with a letter, at most 64 characters; unique in the application. How every channel calls it |
| `description` | `''` | one or two sentences: what it does and what it returns. Agents read it to choose the tool |
| `scope` | `''` | the scope a token needs, `resource:action` (`books:write`). `''`: any token |
| `level` | `0` | the minimum `permissions` of the user (signed in, or the owner of the token). `0`: anyone signed in |
| `readOnly` | `false` | it changes nothing: not audited when it succeeds; can be page data and a download; `readOnlyHint` for agents |
| `destructive` | `false` | it deletes or overwrites data: `destructiveHint`, agents ask the user first |
| `public` | `false` | anyone may call it, without signing in or a token |
| `mcp` | `true` | `false` keeps it out of MCP (a capability only a browser can use) |

`php caligrafer.php service <Folder>/<Name> [--scope <scope>] [--read-only]` writes the class in
`application/services/<Folder>/<Name>.php`, the name in snake_case (`CreateBook` -> `create_book`). Without
`--scope` it writes `scope: ''` (G76). There is no `--destructive`: write `destructive: true` yourself.

### The class

| Part | Meaning |
| --- | --- |
| `public $rules` | input => [validation rules](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation). **Only these inputs reach `handle()`**: an input with no rule is listed with `''` |
| `public $filters` | input => filters applied before validation (`trim`) |
| `public $descriptions` | input => what it is, for the schema agents and clients see |
| `handle(array $input, Context $context)` | does the work and returns data: an array, a model, a list of models, a value (models become arrays of their columns) |
| `__construct(...)` | what it uses, given by the [container](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection) |

A service never renders a view, redirects, prints, or reads the request or the session: the channel does that.

**From the rules to the input schema** (what MCP and `GET /capabilities` show):

| Rule | Schema |
| --- | --- |
| `required` | listed in `required` |
| `integer` / `numeric`, `float` / `boolean` | `"type": "integer"` / `"number"` / `"boolean"` (otherwise `"string"`) |
| `valid_email`, `valid_url`, `uuid`, `date` | `"format": "email"`, `"uri"`, `"uuid"`, `"date"` |
| `max_len,N`, `min_len,N`, `exact_len,N` | `maxLength`, `minLength` |
| `min_numeric,N`, `max_numeric,N` | `minimum`, `maximum` (`current_year` is this year) |
| `contains_list,a;b` | `"enum": ["a", "b"]` |
| `file` | `"description": "The URL or the path of a file"` (web pages and REST upload the file: [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#files)) |

### Refusals: `ServiceError`

| Throw | Code | Status | When |
| --- | --- | --- | --- |
| `ServiceError::invalid(['field' => 'message'])` | `invalid` | 422 | an input is wrong |
| `ServiceError::unauthenticated()` | `unauthenticated` | 401 | nobody is signed in |
| `ServiceError::forbidden()` | `forbidden` | 403 | this caller may not do it (beyond the scope and level checks) |
| `ServiceError::notFound('There is no book #12')` | `not_found` | 404 | the thing does not exist |
| `ServiceError::conflict('This book is already lent to Ana')` | `conflict` | 409 | it clashes with the current state |

`$e->kind`, `$e->fields`, `$e->status()`, `$e->toArray()` (`{"error": {"code", "message", "fields"}}`).

### `Context`: who calls

| Member | Meaning |
| --- | --- |
| `$context->user` | the signed-in user, or the owner of the token (`null` otherwise) |
| `$context->token` | the token row (`id`, `name`, `scopes`, `user_id`...), or `null` |
| `$context->channel` | `web`, `rest`, `mcp` or `cli` |
| `$context->allows('books:write', 1)` | whether this caller has that scope and level |
| `$context->userId()`, `$context->level()` | the user's id, and `permissions` (0 without a user) |
| `$context->authenticated()` | anyone but a guest |

**Scopes and levels** (`allows()`): the command line and the legacy key pass everything. A signed-in person needs
`permissions >= level` (no scopes). A token needs the scope (exact, `books:*` or `*`); when it has an owner
(`--user <id>`), the owner needs the level too. A token **without an owner** is limited by its scopes only: the level
does not apply to it, so give it destructive scopes with care (G53).

### Tokens

| Command | What it does |
| --- | --- |
| `php caligrafer.php token create "<name>" --scopes a:read,b:write [--user <id>] [--expires <days>]` | creates it and prints it once (`cq_<id>_<40 hex>`); only its SHA-256 hash is kept, in `caligrafy_tokens` |
| `php caligrafer.php token list` | `#<id> active/revoked/expired <name> <scopes> [user <id>] last used <when>/never used` |
| `php caligrafer.php token revoke <id>` | `Token #2 revoked`; the number alone (`revoke #2` revokes nothing, G77) |

A revoked, expired or unknown token is a visitor: `401`. From PHP: `Caligrafy\Tokens::create($name, $scopes, $userId,
time() + 86400 * 90)` returns `['id', 'token']`. An application online has no shell: give signed-in people a page
that creates their token (G79).

### MCP over HTTP

+ `POST` only, one JSON-RPC 2.0 message per request (no batches), answered in JSON: `initialize`, `ping`,
  `tools/list`, `tools/call`. A notification (no `id`) gets `202` and no body. `GET` and `DELETE` answer `405`.
+ Protocol versions `2025-11-25`, `2025-06-18`, `2025-03-26`, `2024-11-05` (another request gets the first).
+ Personal access tokens only: without one, `401` with `WWW-Authenticate: Bearer error="invalid_token"` and the
  JSON-RPC body `{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Unauthorized: send Authorization: Bearer
  <personal access token>"}}`, whatever the headers (2.19.1: before, a post with no token and no
  `Accept: application/json` was taken for a form and answered `403` `[ERROR 3000]`). The session is never used there.
+ A browser request from another site (`Origin` header) is refused with `403 Origin not allowed`. To allow a
  browser-based client, list its origin in `.env`: `MCP_ALLOWED_ORIGINS=https://inspector.example`.
+ A tool the token cannot see, or one that does not exist, answers `{"error": {"code": -32602, "message": "Unknown tool: create_book"}}`.

### Commands

| Command | What it does |
| --- | --- |
| `php caligrafer.php platform install` | the framework tables (tokens, audit, jobs, cache, migrations, notifications) |
| `php caligrafer.php capabilities [--json \| --markdown]` | the capabilities: a table, with the input schemas, or Markdown for your documentation |
| `php caligrafer.php call <name> ['{"key": "value"}' \| key=value ...] [--token <token>] [--guest]` | calls one, prints `{"data"}` or `{"error"}` (exit 1) |
| `php caligrafer.php client [<file>] [--prefix /capabilities]` | the JS client (default `public/js/capabilities.js`) |
| `php caligrafer.php mcp [--token <token>]` | MCP over stdio |
| `php caligrafer.php audit [--capability <name>] [--limit <n>]` | the latest calls, 50 by default |

All of them are on [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#service).

## Other ways

+ **A controller** [`Route::form`, `Route::page`, the declarative routes]: only for HTTP none of the routes covers
  (several capabilities with conditions, custom headers). It stays thin and calls the capability as the signed-in
  person, the inputs being the posted fields and the route variables:
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
                return view('Bookshelf/books', array('errors' => $e->fields, 'message' => $e->getMessage()));
            }
        }
    }
    ```
    See [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers).
+ **A script** [`php caligrafer.php call`]: `Caligrafy\Capabilities::call('get_book', ['id' => 3], Caligrafy\Context::cli())`
  (with the checks and the audit), or `Caligrafy\Container::make(GetBook::class)->run(['id' => 3])` (validation only:
  no checks, no audit; it returns the model).
+ **The legacy API key** [tokens]: keys made with `generatekeys` and `generateapikey` all encrypt the same `APP_KEY`:
  each one gives full access, and none can be revoked without changing `APP_KEY`. They keep working for old programs
  (`Authorization: Bearer <key>` on REST, never on MCP). Once your programs use tokens, set `LEGACY_API_KEY=false` in
  `.env`; `doctor` reminds you while they are allowed. Controller routes of `web.php` accept tokens with the scope
  `*`. Never put a token or a key in a page or a Vue bundle: browser code uses the session.

## Common mistakes

+ **An input with no rule** (G52): it never reaches `handle()`. List it in `$rules`, with `''` when it has no rule.
+ **`service` without `--scope`** (G76): `scope: ''` lets any token call it.
+ **A write over the session without `X-CSRF-Token`** (G54): 403. Use the JS client, or send the `csrf` of `GET /capabilities`.
+ **A capability that is not read-only as page data** (G83): refused. Pages show read-only capabilities; forms use `Route::form`.
+ **Sending the legacy key to MCP** (G56): 401. MCP takes tokens only.
+ **Trusting the level with an application token** (G53): a token without `--user` passes any level. Create agent
  tokens with `--user <id>` when the level matters.
+ **Using `register_user` over REST or MCP** (G81): refused; sign-up is the sign-up page.
+ **Forgetting `php caligrafer.php client` after a change**: the Vue app calls the old functions.

## Related pages

+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability): the short tour
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): `Route::form`, `Route::auth`, `Route::download`, `Route::webhook`
+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) and [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships): reading data in `handle()`
+ [Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation): the rules of `$rules`
+ [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication): users, levels, tokens for agents
+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App), [Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection), [Caching](https://github.com/caligrafy/caligrafy-quill/wiki/Caching)
+ [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer): every command

>[Next Section: Claude Code Plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
