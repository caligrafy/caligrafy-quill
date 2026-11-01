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
| Web pages (Pug) | `Route::page()`: the page controller calls read-only capabilities for the page's data; a controller calls `$this->call()` when a page has its own logic (a form post) | the signed-in person (session) |
| Vue apps, the Vite SPA, installable mobile pages | the generated JS client (`php caligrafer.php client`) calls `/capabilities` | the signed-in person (cookie + `X-CSRF-Token`) |
| REST | `Route::capability()` routes and `POST /capabilities/{name}` | a personal access token, the legacy key, or the session |
| AI agents | MCP: `Route::mcp('/mcp')` over HTTP, `php caligrafer.php mcp` over stdio | a personal access token |
| Scripts and the command line | `php caligrafer.php call <capability>`, `Capabilities::call()` | the CLI (full access, audited as `cli`) |

A rule written once (validation, permission, audit) behaves the same whichever way it is called; the QA suite proves
it with cross-channel tests.

**One rule keeps it true: only capabilities touch models.** Pages, controllers, scripts and the JS client get and change
data by calling capabilities, never `new Model(...)`. The framework follows it too: since 2.5.0 the user administration
pages and sign-up call the user capabilities. The one exception is signing in and out (`AuthController`), which is the
web session, and capabilities never touch the session.

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
├── views/         Pug pages built with the page components of public/css/tokens.css
├── controllers/   pages with their own logic; core/ holds the framework's (Auth, Admin, AI, Client, Watson)
└── config/        config.php, routing/web.php (every route of the project)
public/            css/tokens.css (the look), js/capabilities.js (the generated client), <app>/ (client apps)
Dockerfile         the production image; docker/Dockerfile is the development image of docker-compose.yaml
claude-plugin/     the Claude Code plugin: the documentation, skills, a reviewer agent
```
Agent tools are generated from the capabilities, so there is no separate tools folder: a feature is never written
twice. Every PHP file of `framework/` and `application/` is loaded by one autoloader (no PSR-4): application classes are
global or namespaced by hand, and their names must be unique (the user capabilities are named `Caligrafy...` for that).

## How a request runs

1. `index.php` loads Composer's autoloader and `.env` (a file: plain environment variables are not read), then
   `Framework::run()`.
2. `Framework::run()` buffers the output, registers the error handlers and `init()`: `initialize.php` starts the PHP
   session, creates the CSRF token (`$_SESSION['token']`) and writes the application URLs into the session;
   `corehelpers.php` defines the global helpers; every `.env` value becomes a constant; `coresettings.php`.
3. `autoload()` registers the autoloader: on the first unknown class it requires every PHP file of `framework/core`,
   `framework/database`, `framework/interfaces`, `application/` and `tests/`.
4. `dispatch()` includes `application/config/config.php` and `application/config/routing/web.php`. The routes end with
   `Route::run()`.
5. `Route::run()` builds the `Request` (headers, parameters; for POST, PUT and DELETE it checks the CSRF token unless the
   request asks for JSON or its user agent is in `APPROVED_AGENTS`), keeps the routes with the same method and segment
   count, matches the path, binds the route variables (`{id}`; for GET the query string is merged in), then:
   - **no route matches: the first route runs** (an unknown address shows the first page with 200, not a 404);
   - `'Controller@action'`: a missing class or method raises 1001 (404); a JSON request needs the session's CSRF token
     in `X-CSRF-Token` (a page, 2.7.0+) or, with `Auth::activateAPI()`, a bearer: a token granting `*` or the legacy
     key (else 1004, 401, with the reason); then `new Controller()` and the action;
   - a closure: called as is. `Route::page`, `Route::capability`, `Route::capabilities` and `Route::mcp` register
     closures that hand the request to the page controller, `Rest` or `Mcp\Http`.
6. The answer is a view (`view()` renders the Pug file), JSON (`api()`, the REST and MCP adapters), or a redirect.
   Errors go through `Errors::handle()`: the HTTP status of the error code, HTML text or `{"error": {...}}`.

## Calling a capability

`Capabilities::call($name, $input, $context)` is the single path for every experience:

1. find it (`Capabilities::all()` reads the `#[Capability]` attribute of every `Service` of `application/services`;
   two services with the same name stop the application);
2. unless it is `public`: refuse a guest (`unauthenticated`, 401), then ask the context whether it `allows` the
   capability's `scope` and `level` (`forbidden`, 403);
3. `validate()` the input with the service's `$rules` and `$filters` (GUMP; `invalid`, 422, with field messages);
4. `handle($input, $context)`; the result is turned into plain arrays (`Capabilities::plain()`);
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
- **Controllers** remain for pages with their own logic: a classic form post, several capabilities with conditions.
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
  binds parameters; there are no transactions or migrations.
- **Tables** are written as `application/models/<Folder>/create_db.sql`, safe to run again (`CREATE TABLE IF NOT
  EXISTS`). Locally `php caligrafer.php sql <file>` runs one; the framework tables (`caligrafy_tokens`,
  `caligrafy_audit`) come from `framework/settings/platform.sql` (`platform install`, `quickstart`).
- **In production**, `php caligrafer.php deploy` (`Deployer`) runs at every start: it waits for the database, runs
  `platform.sql`, then every `create_db.sql` that is new or changed (recorded with its hash in `caligrafy_scripts`).
  A changed script that drops, deletes or truncates is never run again on its own; `seed.sql` is never run.
- **Times**: the MySQL session follows `SERVER_TIMEZONE`, like PHP (`DB_TIMEZONE=server` keeps MySQL's own zone).

## Production and deployment

- `Dockerfile` (the production image): PHP 8.5 and Apache, the extensions Caligrafy uses, `composer.lock` without the
  development packages, the production `php.ini`, PHP sessions in `/var/lib/php/sessions`. `Dockerfile.dockerignore`
  keeps `.env`, `.git`, tests and backups out. The container gets its `.env` as a mounted file and runs
  `php caligrafer.php deploy` before Apache. Volumes at `/var/www/html/public/uploads` and `/var/lib/php/sessions`
  keep uploads and sign-ins across deploys.
- **Caligrafy Cloud** (https://deploy.caligrafy.com, `php caligrafer.php cloud`, `CloudClient`) builds a project from its
  GitHub repository with that image, gives it a MySQL database and `https://<name>.caligrafy.com`. It is itself a
  Caligrafy application: its dashboard, the `cloud` commands and AI agents call the same capabilities.
- Any host that builds a Dockerfile and mounts a file runs the same image (Coolify, Docker).

## Tools around the framework

- `php caligrafer.php`: `quickstart` (a project in one command), `initialize`, `service` (a capability skeleton),
  `capabilities`, `call`, `client`, `sql`, `platform install`, `token`, `mcp`, `audit`, `doctor` (a read-only health
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
