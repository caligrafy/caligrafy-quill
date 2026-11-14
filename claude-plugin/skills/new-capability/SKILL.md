---
name: new-capability
description: Build a Caligrafy Quill capability once and serve it to every channel - a Caligrafy\Service with #[Capability] in application/services/, its REST routes (Route::capability), the generic endpoint and the JS client for Vue apps (Route::capabilities, php caligrafer.php client), web pages (Route::page, Route::form, Route::download, Route::action for what a page or a form cannot say; Route::webhook for a service's events) and AI agents over MCP (Route::mcp, personal access tokens) - with tests that prove every channel behaves the same.
when_to_use: The user wants a feature available to several channels (web pages, a Vue app or SPA, a REST API, AI agents), asks for MCP tools or to let Claude/agents use their app, asks for "build once" or capabilities, e.g. "/caligrafy-quill:new-capability create_book books:write" or "let agents add books".
argument-hint: "<capability_name> [scope] [read-only|destructive]"
---

# Build a capability once, serve it everywhere

Arguments: `$ARGUMENTS` (snake_case capability name, optional scope `resource:action`, optional `read-only` or
`destructive`). If the inputs are not clear from the conversation, ask once.

Read first: the `caligrafy` skill's [13-build-once-services-capabilities-mcp.md](../caligrafy/reference/13-build-once-services-capabilities-mcp.md),
the Build once, Queries and Declarative routes sections of [api-reference.md](../caligrafy/reference/api-reference.md) and
[gotchas.md](../caligrafy/reference/gotchas.md) G50-G57 (and G2, G8 for any SQL; G122-G128 for queries, G129-G133 for
the declarative routes, G134-G135 for the rules `exists`, `unique` and `current_year`; 2.19.0+).

## Preconditions
1. An initialized Quill 2.1+ project: `application/config/routing/web.php` exists and `framework/core/Capabilities.php`
   exists. On an older framework, say so and suggest updating the framework first (`git pull` + `composer update` +
   `php caligrafer.php upgrade`).
2. The framework tables: `php caligrafer.php doctor --only database` shows `Framework tables: ... installed`; if not,
   run `php caligrafer.php platform install` (in Docker: `docker compose exec dev-box php caligrafer.php platform install`),
   or `.bin/caligrafer quickstart` for a project that was never set up (database, tables, `/capabilities` and `/mcp` routes).
   Run a model's `create_db.sql` with `php caligrafer.php sql application/models/<Folder>/create_db.sql`.
3. The model and table the capability works on exist (else use `/caligrafy-quill:new-resource` first, models only).
   `php caligrafer.php capabilities` lists the existing names: never reuse one.

## Steps
1. **Scaffold**: `php caligrafer.php service <Folder>/<PascalName> --scope <scope> [--read-only]`
   (create_book -> `Books/CreateBook`), one command per capability; without `--scope` the attribute gets `scope: ''` (G76). Never hand-write the attribute boilerplate.
2. **Fill the service** (`application/services/<Folder>/<Name>.php`):
   - `description`: what it does and returns, written for an AI agent choosing a tool (one or two sentences).
   - `$rules` for EVERY input `handle()` reads (`''` when there is no rule, G52), `$filters` (trim), `$descriptions`.
     Several parameters of a GUMP rule are separated with `;` (`word_count,min;10;max;500`; with `,` it never fails,
     G139); a custom `GUMP::add_validator()` rule goes in `application/rules/<name>.php`, not `config.php` (G141).
   - `handle()`: the query builder for reads (2.19.0+, [53-queries.md](../caligrafy/reference/53-queries.md)): lists,
     filters, sorting, paging and related rows are `Book::where(...)->orderBy(...)->with('author')->paginate(20, $input['page'] ?? 1)`
     (`'page' => 'integer'` in `$rules`; `paginate()` alone reads `$_GET`, which REST, MCP and the CLI do not have),
     never a `where` string built from input and never a loop that queries per row (`with()` instead, G124);
     several writes that belong together in `Database::transaction(fn () => ...)`; `update()`/`delete()` of a query
     need a `where` (G125). Writes with the builder too: `$id = Book::insert($row); return Book::find($id);`,
     `Book::where('id', $input['id'])->update($changes)` (the older model calls `create`, `update`, `find` still work).
     Return data (selected columns: never password hashes or secrets; `select(...)` on `Book::query()` still gives models
     with the other columns `null`: add `->arrays()`, G138), refuse with `ServiceError::invalid([...])`, `notFound()`, `forbidden()`, `conflict()`.
     No `view()`, `api()`, `redirect()`, `echo`, `$_SESSION` or request access (G52); use `$context->user`. The one exception
     is a capability the web alone can serve: see `Route::action` below.
   - Flags: `readOnly: true` for reads; `destructive: true` for deletes and overwrites; `level: N` when only users
     with `permissions >= N` may call it; `public: true` only for data anyone may read; `webOnly: true` only for a
     `Route::action` (G143).
3. **Channels** in `web.php`, before `Route::run()` (add only what the user needs, once per project for the last two):
   - REST: `Route::capability('POST', '/books', 'create_book');` (route variables become inputs)
   - Generic endpoint + JS client: `Route::capabilities('/capabilities');` then `php caligrafer.php client public/<app>/src/capabilities.js`
     (Vite SPA) or `public/js/capabilities.js`; use `createBook(...)` and `CapabilityError` in the Vue code.
   - MCP: `Route::mcp('/mcp');`
   - Pug page that shows the result: `Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);` (the
     view gets `$book` as a plain array, `$authorized`, `$user`; only `readOnly` capabilities; a refusal sends a visitor to
     sign in or answers 404/403, or renders the page's `notFound` view, G83). Options: `signedIn`, `level`, `comeBack` (a
     visitor signs in and comes back here), `visitorsOnly` (a path for people signed in), `query` (`['sort' => 'title', 'q']`:
     what the view gets of the query string).
   - A form post (2.19.0+): `Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);`
     (`'method' => 'PUT'|'DELETE'` with `=methodField('PUT')` in the form), and in the view `=csrf()`,
     `value=old('title')`, `p.cq-field-error= errors('title')`, `flash('success')` / `flash('error')`; `back` and
     `success` point at the page that shows them (flash lasts one request, G129). Never a controller for it. Also:
     `same` (two fields that must match), `accepted` (a box to tick), `inputs` (values only the server sets, such as
     `fn () => ['site' => session('home')]`), `signsIn` (the field of the answer with the id of the person to sign in),
     `visitorsOnly`; `success` may return a full address (a payment page).
   - A CSV or JSON export: `Route::download('/books.csv', '<read-only capability>', ['filename' => 'books.csv']);`
     (the capability returns a list of rows).
   - Many files as one ZIP (2.22.0+): `Route::download('/photos.zip', '<read-only capability>', ['as' => 'zip', 'root' => 'public/uploads/photos']);`
     (the capability returns paths relative to `root`, or `['file' => ..., 'name' => 'folder/name']`; G151).
   - Events another service posts: `Route::webhook('/stripe/events', '<capability>', ['verify' => 'stripe']);` with
     `$rules = ['event' => 'required']`, idempotent, and `STRIPE_WEBHOOK_SECRET` (G132). Generate the guard as the
     first line of its `handle()`: `if ($context->actorType() !== 'webhook') { throw ServiceError::forbidden('Only the
     webhook calls this'); }`, and `mcp: false` in its attribute: the capability is also served by `/capabilities`,
     `/mcp` and `call` to anyone its scope and level allow (G137). A capability only the scheduler or the queue runs
     checks `schedule` / `queue` the same way.
   - Sign-in pages: `Route::auth([...])` after `Auth::authentication('User', 'users')`, not a controller. To sign in with an email,
     the `username` column holds the lower-cased address (`09.1-authentication.md#emailsignin`). Password reset:
     `Route::page` and `Route::form` on two capabilities (`09.1-authentication.md`). Face sign-in: `recipes-face-login.md`.
     What changes how an account signs in checks `AuthRoutes::signedInBy()` first (G146).
   - A file input: the `file` rule, `'cover' => 'required|file|extension,png;jpg|max_size,2mb'`; `handle()` gets its URL (G131).
   - Anything else a browser asks (several capabilities with conditions, a choice between a view and a redirect, the
     session, a sign-in between two sites, a gate, a payment page, custom headers, streaming): `Route::action('/x', '<name>')`
     (`'method' => 'POST'`, `signedIn`, `level`, `comeBack`, `visitorsOnly`, `back`) on a capability declared
     `webOnly: true`, which returns `Web::view()`, `Web::redirect()`, `Web::json()` or `Web::text()` (`use Caligrafy\Web;`), and
     calls plain capabilities with `$this->capability($name, $input, $context)`. Nothing but that route reaches it (G143),
     so the work REST or agents need stays in a plain capability. A JSON call to it needs the page's `X-CSRF-Token`
     (`apiHeaders()`) or a token that grants `*`, signed in or not (G144); a route variable wins over the query string.
     Test it with `$this->action()`.
     **Never write a controller.** Never data work in a closure route (G130). Never `new <Model>()`, `associate()` or `save()` outside `application/services/`: only capabilities touch models.
   - Any page you build for it (Pug, or a page calling the JS client) uses the page components and the worked example of
     [ui-guidelines.md](../caligrafy/reference/ui-guidelines.md): `extends ../default/master/site`, a title band, labeled
     `.cq-field`s, field errors in `.cq-field-error` under their own field, `.cq-rows` with `.cq-row-actions`, an empty state;
     every `form` has `method='POST'`, also when a script sends it (a form with no method falls back to a GET with its fields in the address when the script does not run, G104), and the page loads that script. Check it at desktop and phone width, and send each form once: the address must not get a `?`.
4. **A token for agents** (only when MCP or a program needs it): `php caligrafer.php token create "<what it is for>" --scopes <scopes>`
   (add `--user <id>` so the user's level applies, `--expires <days>`). Show the token once and tell the user to
   store it as a secret; never write it into a file of the project. Connect Claude Code:
   `claude mcp add --transport http <app> <url>/mcp --header "Authorization: Bearer <token>"`, or locally
   `claude mcp add <app> -- php caligrafer.php mcp` (Docker: `-- docker compose exec -T dev-box php caligrafer.php mcp`).
5. **Verify every channel** with the server running (`/caligrafy-quill:server status`):
   - `php caligrafer.php capabilities` lists it with the right scope and flags.
   - `php caligrafer.php call <name> key=value` (2.3.0+) returns its `{"data": ...}`; an invalid input returns the `invalid` error with field messages; `--guest` is refused unless the capability is public.
   - REST: `curl -s -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" ...` -> 200/201 `{"data": ...}`;
     the same call with a missing input -> 422 with `fields`; with a token without the scope -> 403.
   - MCP: `tools/list` shows it for a token with the scope only; `tools/call` returns `isError: false`, and invalid
     input returns `isError: true` with the same `fields` as REST.
   - `php caligrafer.php audit --capability <name>` shows the calls.
6. **Tests** (2.18.0+): `php caligrafer.php test make <Name>`, then in `tests/<Name>Test.php` (extends
   `Caligrafy\Testing\TestCase`) `$this->call('<name>', [...], $this->user(1))` for the rule cases and
   `$this->assertCapabilityFails('invalid' | 'unauthenticated' | 'forbidden', ...)` for the refusals; run
   `php caligrafer.php test <Name>` (reference/49-testing-your-app.md). Each test is rolled back. Before 2.18.0: a
   PHPUnit test that runs `(new <Name>())->run([...])`.
   What the capability uses (a client of another service, a mailer) comes through its constructor
   (`public function __construct(private Mailer $mailer) {}`), bound in `application/config/container.php` when it
   is an interface or needs settings, so a test can `$this->fake()` it (reference/50-dependency-injection.md).
7. Ask the `caligrafy-reviewer` agent to review the new files.

## Later, outside the request (2.18.0+)

When the work is slow or may fail (an email, an AI call, a report, an image) and the page should answer at once, the
capability that takes the request pushes a **background job** on a second capability that does the work:
`Caligrafy\Queue::push('<work_capability>', ['id' => $id])` (reference/47-background-jobs.md). The job runs with full
access (G121): check who may ask in the first capability, pass ids. The project needs one scheduled job on
`queue_work` (below: `array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55)`).
Check: `php caligrafer.php queue work --once` prints `done`; in a test, `$this->assertQueued('<work_capability>')`.
Slow reads that change rarely: `Caligrafy\Cache::remember('<key>', <seconds>, fn () => ...)`, forgotten in the
capabilities that change the data (reference/48-caching.md).

**In an app with Snap** (`Route::snap()`, 2.23.0): a slow capability can instead declare `async: true` in its
`#[Capability(...)]`: Snap's cards run it in the background as a Track card: Task (REST, MCP and pages still run it
directly, G155). Give the new capability its hints in `application/config/snap.php` when it needs them (a `title`, a
list's `columns`, a change's `done` line, `applies` when it only fits some records; the `assistant` skill, G157).

## On a timer (2.14.0+)

When the feature must run with no page open ("every minute", "every morning", "check the due ones"), it is a
**scheduled job**: the capability above, named in `application/config/schedule.php`
(reference/39-scheduled-jobs.md):
```php
<?php
return array(
    array('name' => 'check-prices', 'capability' => 'check_prices', 'input' => array('due_only' => true), 'every' => '1m'),
);
```
+ A job has nobody signed in (G108): write a capability that finds the users and rows it works for itself; never one
  that reads `$context->userId()`. Fixed inputs only.
+ `every` (`1m` to `1d`, UTC) or `cron` with a `timezone`; `timeout` (default 55 s, at most 300) above its longest run.
+ Check: `php caligrafer.php schedule list` (and `doctor`), then `php caligrafer.php schedule run <job>` and
  `schedule history <job>`: the outcome must be `ok`. The `scheduler` container of `server start` then runs it every
  minute; on Caligrafy Cloud the deploy reads the schedule. Never add a hand-written loop container for it.

## Telling the user (2.15.0+)

When the feature must reach someone with no page open ("alert me", "remind them", "notify when"), call
`Caligrafy\Notify::user($userId, ['title' => ..., 'body' => ..., 'url' => '/...', 'tag' => ...])` from the capability
(reference/42-notifications.md): never your own email or alert sender. A job notifies each user it works for. Then:
+ `Route::capabilities('/capabilities');` and `Route::notifications();` in `web.php`, `php caligrafer.php platform
  install`, and `php caligrafer.php notify keys --subject mailto:<the owner>` locally (Caligrafy Cloud makes them);
  `APP_URL` for emails sent by jobs (G110).
+ A **Turn on alerts** button on the page that matters: `script(src=$home . 'public/js/notify.js')`, then
  `Notify.enable()` from its click (G109); keep the in-page banner for when a page is open.
+ Check: `php caligrafer.php notify status <user>`, then `notify test <user>`: the result shows each channel.
