# Caligrafy Quill architecture

This file describes how a request runs through the framework today, what that means for building one capability and
serving it to every channel (web, tablet, mobile, REST, AI agents through MCP), and the design the framework follows to
get there. It is written from the source of v2.0.0.

## Build once, experience everywhere

A **capability** (create a book, list the authors) is written once as a **service**: plain PHP that validates its
input, checks who is asking, does the work and returns data. It never renders a page, redirects or prints. Every
channel is an adapter around the same services:

| Channel | Adapter | Identity |
| --- | --- | --- |
| Web pages (Pug) | a controller action calls the service, then renders a view | the session user |
| Vue app and Vite SPA | the generated JS client calls the REST adapter | the session user (cookie + CSRF header) |
| REST | `Route::capability()` routes | a personal access token, or the session user |
| AI agents | the MCP server (`/mcp`, or `php caligrafer.php mcp` over stdio) | a personal access token |
| Scripts | `Capabilities::call()` from PHP or the CLI | the CLI context |

So a rule written once (validation, permission, an event) behaves the same whether a person, a device or an agent
invokes it, and the QA suite proves it with cross-channel tests.

## Where the code lives

```
framework/
├── core/          the framework: Route, Request, Controller, Model, View, Auth, Errors, Caligrafer...
│                  and what a capability is: Service, ServiceError, Capability, Capabilities, Context, Tokens, Audit
├── interfaces/    how the world reaches a capability: Rest (REST routes), Mcp/ (Server, Http: AI agents),
│                  Client (the generated JS client of the Vue apps)
├── database/      Database (PDO)
├── helpers/       initialize.php, corehelpers.php
├── settings/      the application/ template, platform.sql, .env templates
├── librairies/    the Vite SPA template        plugins/    the client app templates (app, bot, facedetect, ml)
application/
├── services/      capabilities: each feature once (empty in a new project)
├── controllers/   models/   views/   config/
public/<app>/      one app per experience, responsive across devices, sharing public/css/tokens.css
```
There is no `application/mcp/tools/` folder: agent tools are generated from the capabilities, so a feature is never
written twice. There are no runtime adapters either: PHP is the only runtime, and contracts for a second one would
be maintained without anyone using them.

## How a request runs today (v2.0.0)

1. `index.php` loads `framework/core/Framework.php`, Composer's autoloader and `.env`, then `Framework::run()`.
2. `Framework::run()` buffers the output, registers the error handlers, then `init()`:
   - `framework/helpers/initialize.php` starts the PHP session on every request, creates the CSRF token
     (`$_SESSION['token']`) and writes the application URLs into the session (`base`, `home`, `public`...).
   - `corehelpers.php` (the global helpers), every `.env` value becomes a constant, `coresettings.php`.
3. `autoload()` registers one autoloader that, on the first unknown class, requires every PHP file of
   `framework/core`, `framework/database`, `framework/interfaces`, `application/` and `tests/` (5 levels deep). There is no PSR-4 mapping:
   application classes are global or namespaced by hand.
4. `dispatch()` includes `application/config/config.php` and `application/config/routing/web.php`, whose routes end
   with `Route::run()`.
5. `Route::run()` builds a `Request`, keeps the routes with the same method and segment count, matches the path, then:
   - **no route matches: the first route runs** (`_currentRouteKey` falls back to 0), so an unknown URL shows the home
     page with HTTP 200 instead of a 404;
   - `'Controller@action'`: a missing class or method raises 1001 (404). **When the request asks for JSON**
     (`Accept: application/json` without `text/html`), the API key is checked (1004, 401). Then
     `new Controller()` and `$controller->$action()`;
   - a closure route is called as is: **no API key check**.
6. `Request::__construct()` reads the headers and the parameters; for POST, PUT and DELETE it checks the CSRF token
   **unless the request asks for JSON** (or its user agent is in `APPROVED_AGENTS`).
7. The action returns `view($name, $data)` or `api($data)`. `View`'s constructor prints JSON (and `exit`s) when the
   request wants JSON or the view name is null, else renders the Pug file. Errors go through `Errors::handle()`:
   status code from the error code, HTML text or `{"error": {...}}`.

## Findings

### Where business logic lives
- In controllers. `Controller` gives each action a request, a model, a validator (GUMP: `check()` filters then
  validates, `validate()`) and the view helpers. The example `AuthController::register()` mixes validation, a
  uniqueness check, password hashing, persistence, error views and a redirect in one method. Nothing of it can be
  called from another channel.
- Models (`Caligrafy\Model`) are an active-record layer over PDO: `create`, `update`, `delete`, `find`, `all`,
  `search`, relationships (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `attach`, `detach`). They hold no
  rules. `Database::execute()` binds parameters; there are no transactions or migrations.
- Responses are side effects: `view()`, `api()`, `redirect()` print and `exit`. A reusable layer must return data and
  leave rendering to the adapter.

### Identity and permissions
- **API key.** `Request::authorizeApiRequest()` accepts any bearer token that decrypts to `APP_KEY`
  (`encryptDecrypt`). Every key generated for a project is equivalent: no name, no scope, no expiry, no audit, and the
  only revocation is a new `APP_KEY`, which also ends every session and makes data encrypted with the old key
  unreadable.
- **The key is public.** `Client/app.pug` passes `API_KEY` to every client page, and the SPA template builds
  `VITE_API_KEY` into its bundle. Since JSON requests skip the CSRF check, anyone who opens an app page can call every
  JSON route of the project, writes included, from anywhere.
- **Closure routes are unprotected** for JSON: no API key check and no CSRF check.
- **Sessions.** `Auth::authorize()` stores the whole user record, encrypted, in the session; `authorized()` reloads it
  from the database and compares every column, so any change to the user's row (including a timestamp column
  updated automatically) signs the user out. `guard($attribute, $level)` compares one integer column
  (`permissions`) and **redirects** (`/login`, `/notAuthorized`): an API caller gets a redirect, not a 401 or 403.
- Passkeys (`Passkey`) and face login (`FaceLogin`) end in the same session login.

### Clients
- Pug views render on the server; the Vue app (`php caligrafer.php app`) and the Vite SPA (`create`) call the JSON
  routes with axios and the public API key.
- The bot, FaceDetect and ML apps are browser apps with their own data routes (`__bots__`, face login endpoints).

### AI pieces already in Quill
- `OpenAI`, `Assistant` (Responses API, file search), `Agent` (open models on Groq, Together, Fireworks, OpenRouter,
  Ollama) and `Watson` call model providers. `Agent` accepts OpenAI-format tool definitions but has no tool loop.
  They are clients of AI services, not a way for agents to use the application: MCP is new.

### What existing projects depend on (must keep working)
- `web.php` with `Route::get/post/put/delete`, `'Controller@action'` and closures, `Auth::activateAPI()`,
  `Auth::authentication()`, `Route::run()`, `Errors::handle()`.
- Controllers extending `Caligrafy\Controller` (`associate`, `all`, `find`, `save`, `delete`, `search`, `check`,
  `validate`, `view`, `api`), models extending `Caligrafy\Model`, the global helpers (`view`, `api`, `session`,
  `csrf`, `methodField`, `authorized`, `user`, `guard`, `redirect`...).
- The legacy API key (generated with `generatekeys` and `generateapikey`) used by existing clients, and
  `$_SESSION['base']` and the other URLs that views read.
- `php caligrafer.php upgrade` manages the `application/` templates (`template-history.json`).

## Design decisions

1. **Additive, not a rewrite.** Services, the registry, tokens and MCP sit beside the MVC layer. A project adopts them
   one capability at a time; nothing existing changes behavior unless a setting turns it on. Released as 2.x.
2. **Services** (`Caligrafy\Service`) live in `application/services/` (loaded like the rest of `application/`). A
   service declares its rules and permission, implements `handle(array $input, Context $context)` and returns data
   (arrays, models). It throws `Caligrafy\ServiceError` (`invalid` 422 with field errors, `unauthenticated` 401,
   `forbidden` 403, `not_found` 404, `conflict` 409). It never renders, redirects or prints.
3. **Context** (`Caligrafy\Context`) says who is acting (a user, a token, the CLI, a guest), through which channel
   (`web`, `rest`, `mcp`, `cli`) and with which scopes. The adapters build it; services only read it.
4. **Capabilities** are services with a `#[Capability]` attribute: a name (`create_book`), a description, a scope
   (`books:write`), the minimum user permission level, read-only or not, public or not. `Caligrafy\Capabilities`
   finds them, generates their JSON Schema from the GUMP rules, calls them (validation, permission, audit) and lists
   them (`php caligrafer.php capabilities`).
5. **Personal access tokens** (`Caligrafy\Tokens`) replace the shared API key for machines and agents: `cq_<id>_<secret>`,
   stored as a SHA-256 hash in `caligrafy_tokens` with a name, an owner, scopes, an expiry, the last use and a
   revocation date. `php caligrafer.php token create|list|revoke`. The legacy key keeps working while
   `LEGACY_API_KEY` is not `false`; `doctor` warns about it.
6. **Browser clients use the session**, not a key: same-origin JSON calls carry the session cookie and an
   `X-CSRF-Token` header. The generated JS client does this for the Vue app and the SPA.
7. **Audit.** Every capability call that changes data, and every refused call, is written to `caligrafy_audit`: when,
   the actor (user or token), the channel, the capability, the outcome.
8. **MCP** (`Caligrafy\Mcp\Server`): JSON-RPC 2.0 over Streamable HTTP at `/mcp` (POST, JSON responses) and over stdio
   (`php caligrafer.php mcp`). Tools are the capabilities a token may use, with their schema and read-only and
   destructive hints. MCP calls the services directly, never the REST routes. Requires a token; checks the `Origin`
   header.
9. **Experiences** share design tokens (`public/css/tokens.css`, `tokens.json`) and the generated client; layouts stay
   specific to each channel (dense web pages, touch-first tablet, mobile PWA).
10. **Framework tables** (`caligrafy_tokens`, `caligrafy_audit`) are created by `php caligrafer.php platform install`
    from `framework/settings/platform.sql`; `doctor` checks that they exist when tokens or MCP are used.
