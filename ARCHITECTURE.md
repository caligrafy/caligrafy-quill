# Caligrafy Quill architecture

How the framework is built and how a request runs through it, for contributors and coding agents. It describes
Caligrafy Quill 2.5.0, from the source. (The audit of 2.0.0 that led to this design is in the git history of this
file, commit `67ea265`.)

## Build once, experience everywhere

A **capability** (list the tasks, create a book) is written once as a **service**: plain PHP that validates its input,
checks who is asking, does the work and returns data. It never renders a page, redirects or prints. Every way people
and programs reach the application is an adapter around the same capabilities:

| Experience | Adapter | Who is acting |
| --- | --- | --- |
| Web pages (Pug) | `Route::page()`: the page controller calls read-only capabilities for the page's data; the declarative routes (2.19.0) call a capability for a form post (`Route::form`), an export (`Route::download`) and sign-in (`Route::auth`); a controller calls `$this->call()` only for custom HTTP | the signed-in person (session) |
| Other services' events | `Route::webhook()`: the signature checked (`'stripe'` or a function), then the capability (2.19.0) | the system (`Context::system('webhook', $path)`) |
| Vue apps, the Vite SPA, installable mobile pages | the generated JS client (`php caligrafer.php client`) calls `/capabilities` | the signed-in person (cookie + `X-CSRF-Token`) |
| REST | `Route::capability()` routes and `POST /capabilities/{name}` | a personal access token, the legacy key, or the session |
| AI agents | MCP: `Route::mcp('/mcp')` over HTTP, `php caligrafer.php mcp` over stdio | a personal access token |
| Scripts and the command line | `php caligrafer.php call <capability>`, `Capabilities::call()` | the CLI (full access, audited as `cli`) |

A rule written once (validation, permission, audit) behaves the same whichever way it is called; the QA suite proves
it with cross-channel tests.

**One rule keeps it true: only capabilities touch models.** Pages, controllers, scripts and the JS client get and change
data by calling capabilities, never `new Model(...)`. The framework follows it too: since 2.5.0 the user administration
pages and sign-up call the user capabilities. The one exception is signing in and out (`AuthController`, or
`AuthRoutes` behind `Route::auth`, 2.19.0), which is the web session. Capabilities never touch the session, except those
declared `webOnly: true` (2.21.0), which only `Route::action` reaches.

## Where the code lives

```
framework/
├── core/          Framework, Route, Request, Controller, PageController, Model, View, Auth, Errors
│                  capabilities: Service, ServiceError, Capability, Capabilities, Context, Tokens, Audit
│                  tools: Caligrafer, Quickstart, Doctor, Upgrader, Deployer, CloudClient
│                  services the apps use: Payment, OpenAI, Assistant, Agent, Watson, FaceLogin, Passkey, Mail...
├── interfaces/    how the world reaches a capability: Rest, Mcp/ (Server, Http), Client (the JS client generator)
├── database/      Database (PDO, bound parameters)
├── helpers/       initialize.php (session, CSRF token, URLs), corehelpers.php (view, api, redirect, guard...)
├── settings/      application/ (the project template), platform.sql, .env templates, template-history.json,
│                  examples/tasks (quickstart --example), service.php.template
├── librairies/    the Vite SPA template        plugins/    the client app templates (app, bot, facedetect, ml)
application/       (a project: copied from framework/settings/application by initialize, updated by upgrade)
├── services/      capabilities, one class per feature; Users/ holds the user capabilities (2.5.0)
├── models/        one model per table, and the table's create_db.sql
├── migrations/    changes to tables that have data: numbered SQL files, each run once (2.18.0)
├── views/         Pug pages built with the page components of public/css/tokens.css
├── controllers/   the older way, still working; core/ holds the framework's (Auth, Admin, AI, Client, Watson)
└── config/        config.php, routing/web.php (every route of the project), schedule.php (jobs), container.php (bindings)
tests/             the application's own tests (php caligrafer.php test, Caligrafy\Testing\TestCase); never loaded on a page
public/            css/tokens.css (the look), js/capabilities.js (the generated client), <app>/ (client apps)
Dockerfile         the production image; docker/Dockerfile is the development image of docker-compose.yaml
claude-plugin/     the Claude Code plugin: the documentation, skills, a reviewer agent
```
Agent tools are generated from the capabilities, so there is no separate tools folder: a feature is never written
twice. Classes load when they are first used, from a class map of `framework/` and `application/` kept between requests
(`ClassMap`, 2.18.0; no PSR-4 needed); a file that declares functions or runs top-level code is loaded at start, and
`CLASS_MAP=false` restores loading every file. Application classes are global or namespaced by hand, and their names
must be unique (the user capabilities are named `Caligrafy...` for that).

## How a request runs

1. `index.php` loads Composer's autoloader and `.env` (a file: plain environment variables are not read): from
   `.env.php` when `php caligrafer.php env cache` or the deploy step compiled it and `.env` still has the content it
   was compiled from (its sha1), else by parsing `.env` (2.19.0); then `Framework::run()`.
2. `Framework::run()` buffers the output (no fixed chunk size since 2.19.0), registers the error handlers and `init()`:
   `initialize.php` starts the PHP session when the request has a session cookie, is a POST, PUT or DELETE, or
   `SESSION_LAZY=false`; otherwise (a GET or HEAD without a cookie) `$_SESSION` is kept in memory and the session
   starts only when the CSRF token is read (`session('token')`), when the session is written after `config.php`
   (`caligrafySessionSnapshot()`), or at shutdown when the page carries the token (2.19.0). It creates the CSRF token
   (`$_SESSION['token']`) and writes the application URLs into the session;
   `corehelpers.php` defines the global helpers; every `.env` value becomes a constant; `coresettings.php`.
3. `autoload()` registers the class map (`ClassMap::register()`): each class of `framework/core`, `framework/database`,
   `framework/interfaces` and `application/` (not `config/` or `views/`) is loaded when first used; files with
   functions or top-level code are loaded now. `tests/` is never loaded.
4. `dispatch()` includes `application/config/config.php`, loads the container's bindings (`application/config/container.php`
   when it exists) and `application/config/routing/web.php`. The routes end with
   `Route::run()`.
5. `Route::run()` ages the flash data of the previous request (`Flash::age()`, 2.19.0), builds the `Request` (headers,
   parameters; for POST, PUT and DELETE it checks the CSRF token unless the request asks for JSON, its user agent is
   in `APPROVED_AGENTS`, or it posts to a `Route::webhook` address), keeps the routes with the same method and segment
   count, matches the path, binds the route variables (`{id}`; for GET the query string is merged in), then:
   - **no route matches**: 404 (`[ERROR 1005]`, or JSON to an API call), or 405 with `Allow` when the address exists
     with another method (2.16.0);
   - `'Controller@action'`: a missing class or method raises 1001 (404); a JSON request needs the session's CSRF token
     in `X-CSRF-Token` (a page, 2.7.0+) or, with `Auth::activateAPI()`, a bearer: a token granting `*` or the legacy
     key (else 1004, 401, with the reason); then `new Controller()` and the action;
   - a closure: called as is. `Route::page`, `Route::capability`, `Route::capabilities`, `Route::mcp` and the
     declarative routes (`Route::form` -> `Form`, `Route::auth` -> `AuthRoutes`, `Route::webhook` -> `Webhook`,
     `Route::download` -> `Download`, 2.19.0) register closures that hand the request to the page controller, `Rest`,
     `Mcp\Http` or those classes.
6. The answer is a view (`view()` renders the Pug file, compiled once and kept in a private folder of the temporary
   directory until it or a file it includes changes, 2.17.0; `VIEW_CACHE=false`; since 2.19.0 a compiled, up-to-date
   view runs through Phug's Optimizer without the compiler, `VIEW_OPTIMIZER=false`, and Phug's debug mode is on only
   with `APP_DEBUG=true`, its views kept apart), JSON (`api()`, the REST and MCP adapters), or a redirect.
   Errors go through `Errors::handle()`: the HTTP status of the error code, HTML text or `{"error": {...}}`.

## Calling a capability

`Capabilities::call($name, $input, $context)` is the single path for every experience:

1. find it (`Capabilities::all()` reads the `#[Capability]` attribute of every `Service` of `application/services` and
   of `framework/services` (notifications, `queue_work`); two services with the same name stop the application);
2. unless it is `public`: refuse a guest (`unauthenticated`, 401), then ask the context whether it `allows` the
   capability's `scope` and `level` (`forbidden`, 403);
3. `validate()` the input with the service's `$rules` and `$filters` (GUMP; `invalid`, 422, with field messages);
4. the service is built by the container (`Container::make()`: its constructor gets what it asks for, 2.18.0), then
   `handle($input, $context)`; the result is turned into plain arrays (`Capabilities::plain()`);
5. **audit** (`caligrafy_audit`): every refusal and error, and every success of a capability that is not `readOnly`.

A service refuses with `ServiceError::invalid()`, `unauthenticated()`, `forbidden()`, `notFound()` or `conflict()`.
The adapters turn the refusal into their own answer: a status and `{"error": {code, message, fields}}` for REST,
`isError` for MCP, a redirect, 403 or 404 page for `Route::page`, a `ServiceError` a controller catches to show a form
again.

## Who is acting: the Context

`Context` says who acts (`actorType()`: user, token, cli, key, guest), through which channel (`web`, `rest`, `mcp`,
`cli`), and answers `allows($scope, $level)`:

| Actor | Scopes | Level |
| --- | --- | --- |
| a signed-in person (session) | every scope | their `permissions` column (`Context::$levelAttribute`) |
| a token with an owner (`--user`) | the token's scopes (`books:read`, `books:*`, `*`) | the owner's level; refused if the owner no longer exists |
| a token without an owner | the token's scopes | passes any level: an application token. It is not someone, so it never manages users |
| the legacy API key (`LEGACY_API_KEY=true`) | full access | full: it is public by design (client app pages), so the user capabilities refuse it |
| the command line (`php caligrafer.php call`, `mcp` without a token) | full access | full, audited as `cli` |
| a guest | public capabilities only | 0 |

`Context::fromRequest()` builds it for HTTP: a bearer token, else the legacy key, else the session, else a guest.
Personal access tokens (`Tokens`: `cq_<id>_<secret>`, stored as a SHA-256 hash in `caligrafy_tokens`, with a name, an
owner, scopes, an expiry, the last use and a revocation date) are made with `php caligrafer.php token`, or by a
capability that calls `Tokens::create()` for the signed-in person (how an application online gives tokens to agents).

## The experiences

- **Pages: `Route::page($route, $view, $options)`** (`PageController`). Every page gets `authorized`, the signed-in
  `user` (id, username, level) and its route variables. `signedIn` and `level` protect a page (`Auth::guard`: a
  redirect to `/login` or `/notAuthorized`). `data` names read-only capabilities, called as the person who opens the
  page with the query string and the route variables as inputs (route variables win); a refusal sends a visitor to sign
  in, or answers 403 (1006), 404 (1005) or the `notFound` view. The view never receives the query string; `authorized`
  and `user` are reserved. A page without data does not connect to the database.
- **Declarative routes** (2.19.0, no controller): `Route::form` (`Form`: the form's fields, its files and the route
  variables go to a capability as the person who sent it; success redirects with a flash message; `invalid` and
  `conflict` go back with `errors()`, `old()` and `flash('error')` kept for one request by `Flash`; JSON requests get the
  REST answer and need `X-CSRF-Token` with the session), `Route::auth` (`AuthRoutes`: `/login`, `/logout`, `/register`,
  `/notAuthorized`, passkeys; failed sign-ins counted by `Throttle`; a new session id and CSRF token at sign-in and
  sign-out), `Route::webhook` (`Webhook`: no CSRF token, the signature checked before the capability runs as the
  system), `Route::download` (`Download`: a read-only capability as CSV or JSON). The `file` rule of a capability
  (`Uploads`) saves an uploaded file after the access check and gives `handle()` its URL.
- **`Route::action`** (`Action`, 2.21.0): HTTP none of these covers (a sign-in between two sites, a gate, a page that
  decides where to go): a capability declared `webOnly: true` answers with a `Caligrafy\Web`. Nothing else reaches it.
- **How a session signed in** (`AuthRoutes::signedInBy()`, 2.21.3): `password` or `passkey` through `Route::auth`, what the
  application recorded otherwise. A face alone must not change how the account signs in.
- **Controllers** still work; no application needs one since 2.21.0.
  They call capabilities with `$this->call()` (the inputs default to the request parameters and the route variables)
  and catch `ServiceError` to show the form again. The framework's own controllers: `AuthController` (sign-in, sign-up
  through `register_user`), `AdminController` (the user capabilities), `ClientController` (client app pages),
  `AIController`, `WatsonController`.
- **REST** (`Rest`): `Route::capability('POST', '/api/books', 'create_book')` maps a route to a capability (route
  variables become inputs and win over the body); `Route::capabilities('/capabilities')` lists what the caller may use
  (`GET`) and calls one (`POST /capabilities/{name}`). A session caller must send the CSRF token (`X-CSRF-Token`); a
  bearer token does not need it (a browser cannot send one on its own).
- **The JS client** (`Client`, `php caligrafer.php client`): one function per capability (`createBook({ title })`)
  that calls `/capabilities` with the session cookie and the CSRF token, and throws `CapabilityError` with the same
  fields as REST. Vue apps, the SPA and plain page scripts use it; no key ever reaches a page.
- **MCP** (`Mcp\Server`, `Mcp\Http`): JSON-RPC 2.0 over Streamable HTTP at `/mcp` (POST, JSON answers; GET and DELETE
  405) and over stdio (`php caligrafer.php mcp`). Tools are the capabilities the token may use, with their JSON Schema
  (from the GUMP rules) and read-only and destructive hints; a tool the token may not use is unknown to it. It needs a
  personal access token (never the legacy key) and refuses a browser `Origin` from another site (`MCP_ALLOWED_ORIGINS`
  allows one).

## The user capabilities (2.5.0)

`application/services/Users`: `list_users`, `get_user`, `update_user`, `delete_user` and `register_user`, shared code in
`CaligrafyUsers`. Only the command line or a real person at `CaligrafyUsers::ADMIN_LEVEL` manages users (never the
legacy key or a token without an owner); no one manages someone above their own level or grants more than it; an
administrator cannot delete their own account; deleting a user revokes their tokens; passwords are hashed and never
returned. `register_user` is public but answers only the web (the sign-up page) and the command line, so accounts
cannot be created in bulk over REST or MCP.

## Data

- **Models** (`Caligrafy\Model`): an active-record layer over PDO (`create`, `update`, `delete`, `find`, `all`,
  `search`, relationships). They hold no rules: rules live in the capabilities that use them. `Database::execute()`
  binds parameters and fetches rows as arrays (2.19.0; `Database::connection()` gives the PDO, whose transactions the
  app tests use).
- **Queries** (2.19.0, `Query`): `Database::table('books')` (arrays) and `Book::query()` / `Book::where()` /
  `Book::find()` (models, forwarded by `Model::__callStatic`; `$book->find()` by `__call`) build one statement with
  validated, quoted names and bound values: filters, `paginate`, `chunk`, `insert`/`update`/`delete` (a `where`
  required), eager loading (`with()`, `withCount()`: one query per relation, `WHERE key IN (...)`), and
  `Database::transaction()`. In development the same SELECT shape run 10 times in a request is logged as a probable N+1.
- **Tables** are written as `application/models/<Folder>/create_db.sql`, safe to run again (`CREATE TABLE IF NOT
  EXISTS`). Locally `php caligrafer.php sql <file>` runs one; the framework tables (`caligrafy_tokens`,
  `caligrafy_audit`, `caligrafy_job_runs`, the notifications tables, `caligrafy_jobs`, `caligrafy_cache`,
  `caligrafy_migrations`) come from `framework/settings/platform.sql` (`platform install`, `quickstart`).
- **Migrations** (2.18.0, `Migrations`): a change to a table that has data is a numbered SQL file of
  `application/migrations`, run once, in order, recorded in `caligrafy_migrations` (`php caligrafer.php migrate`).
- **Background jobs** (`Queue`, `caligrafy_jobs`): a capability and its input run later in a child process, with
  retries; the scheduler runs them through the framework capability `queue_work`, or `php caligrafer.php queue work`.
- **Cache** (`Cache`): the `caligrafy_cache` table by default with a database (shared by every process), else files.
- **In production**, `php caligrafer.php deploy` (`Deployer`) runs at every start: it waits for the database, runs
  `platform.sql`, then every `create_db.sql` that is new or changed (recorded with its hash in `caligrafy_scripts`), then the pending
  migrations.
  A changed script that drops, deletes or truncates is never run again on its own; `seed.sql` is never run.
- **Times**: the MySQL session follows `SERVER_TIMEZONE`, like PHP (`DB_TIMEZONE=server` keeps MySQL's own zone).

## Production and deployment

- `Dockerfile` (the production image): PHP 8.5 and Apache, the extensions Caligrafy uses, `composer.lock` without the
  development packages, the production `php.ini`, PHP sessions in `/var/lib/php/sessions`. `Dockerfile.dockerignore`
  keeps `.env`, `.git`, tests and backups out. The container gets its `.env` as a mounted file and runs
  `php caligrafer.php deploy` before Apache (2.19.0: it first compiles `.env` to `.env.php`). Volumes at `/var/www/html/public/uploads` and `/var/lib/php/sessions`
  keep uploads and sign-ins across deploys.
- **Caligrafy Cloud** (https://deploy.caligrafy.com, `php caligrafer.php cloud`, `CloudClient`) builds a project from its
  GitHub repository with that image, gives it a MySQL database and `https://<name>.caligrafy.com`. It is itself a
  Caligrafy application: its dashboard, the `cloud` commands and AI agents call the same capabilities.
- Any host that builds a Dockerfile and mounts a file runs the same image (Coolify, Docker).

## Tools around the framework

- `php caligrafer.php`: `quickstart` (a project in one command), `initialize`, `service` (a capability skeleton),
  `capabilities`, `call`, `client`, `sql`, `migrate`, `platform install`, `token`, `mcp`, `audit`, `schedule`, `queue`,
  `cache clear`, `env cache | clear` (2.19.0), `test`, `doctor` (a read-only health
  check), `upgrade` (`Upgrader`: compares `application/` with the templates and `template-history.json`; replaces
  untouched copies, keeps customized ones with a `.caligrafy-new` next to them, never replaces `web.php`, and holds
  the user capabilities when the project manages users its own way), `deploy`, `cloud`, `version`.
- The Claude Code plugin (`claude-plugin/`): the documentation, a source-verified API reference and gotchas, skills
  that build features capabilities first (`idea`, `new-capability`, `new-resource`, `new-app`, `deploy`), and a reviewer
  agent that checks the rules of this file.

## What existing projects depend on (must keep working)

- `web.php` with `Route::get/post/put/delete`, `'Controller@action'` and closures, `Auth::activateAPI()`,
  `Auth::authentication()`, `Route::run()`, `Errors::handle()`; the first-route fallback for unknown addresses.
- Controllers extending `Caligrafy\Controller` (`associate`, `all`, `find`, `save`, `delete`, `search`, `check`,
  `validate`, `view`, `api`), models extending `Caligrafy\Model`, the global helpers (`view`, `api`, `session`, `csrf`,
  `methodField`, `authorized`, `user`, `guard`, `redirect`...).
- The legacy API key (`generatekeys`, `generateapikey`) while `LEGACY_API_KEY` is not `false`, and
  `$_SESSION['base']` and the other URLs that views read.
- Customized `application/` files: `upgrade` never overwrites them.

## Design decisions

1. **Additive, not a rewrite.** Capabilities, tokens, MCP, `Route::page` and the user capabilities sit beside the MVC
   layer. A project adopts them one feature at a time; nothing existing changes behavior unless a setting or a new route
   turns it on. Released as 2.x, with semantic versioning (`Framework::VERSION`, the `v<version>` tag, `CHANGELOG.md`).
2. **Services return data.** A service declares its rules and permission, implements `handle(array $input, Context
   $context)` and returns data or throws `ServiceError`. It never renders, redirects, prints or reads the request or the
   session: that is what lets every experience use it.
3. **Only capabilities touch models.** Pages and controllers call capabilities; rules exist once.
4. **Pages go through one controller** (`Route::page`), so every page gets who is signed in the same way, and page
   data comes from read-only capabilities (opening a page never changes data).
5. **Identity is explicit** (`Context`). Tokens replace the shared key for programs and agents; browsers use the session
   with a CSRF token; the shared key and tokens without an owner are never treated as a person.
6. **Audit by default** for every change and every refusal.
7. **MCP tools are the capabilities**, called directly, never through the REST routes.
8. **One look** for every page and device: the page components of `public/css/tokens.css`, the look of the Caligrafy
   sign-in card.
9. **The database follows the code**: `create_db.sql` scripts safe to run again, run at every start in production.
10. **Upgrades respect the project**: untouched templates are replaced, customized ones kept, routes never touched, and
    new framework pieces that could clash with the project are held with an explanation.
