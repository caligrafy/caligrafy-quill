---
name: new-capability
description: Build a Caligrafy Quill capability once and serve it to every channel - a Caligrafy\Service with #[Capability] in application/services/, its REST routes (Route::capability), the generic endpoint and the JS client for Vue apps (Route::capabilities, php caligrafer.php client), web pages (Controller::call) and AI agents over MCP (Route::mcp, personal access tokens) - with tests that prove every channel behaves the same.
when_to_use: The user wants a feature available to several channels (web pages, a Vue app or SPA, a REST API, AI agents), asks for MCP tools or to let Claude/agents use their app, asks for "build once" or capabilities, e.g. "/caligrafy-quill:new-capability create_book books:write" or "let agents add books".
argument-hint: "<capability_name> [scope] [read-only|destructive]"
---

# Build a capability once, serve it everywhere

Arguments: `$ARGUMENTS` (snake_case capability name, optional scope `resource:action`, optional `read-only` or
`destructive`). If the inputs are not clear from the conversation, ask once.

Read first: the `caligrafy` skill's [13-build-once-services-capabilities-mcp.md](../caligrafy/reference/13-build-once-services-capabilities-mcp.md),
the Build once section of [api-reference.md](../caligrafy/reference/api-reference.md) and
[gotchas.md](../caligrafy/reference/gotchas.md) G50-G57 (and G2, G8 for any SQL).

## Preconditions
1. An initialized Quill 2.1+ project: `application/config/routing/web.php` exists and `framework/core/Capabilities.php`
   exists. On an older framework, say so and suggest updating the framework first (`git pull` + `composer update` +
   `php caligrafer.php upgrade`).
2. The framework tables: `php caligrafer.php doctor --only database` shows `Framework tables: ... installed`; if not,
   run `php caligrafer.php platform install` (in Docker: `docker compose exec dev-box php caligrafer.php platform install`).
3. The model and table the capability works on exist (else use `/caligrafy-quill:new-resource` first, models only).
   `php caligrafer.php capabilities` lists the existing names: never reuse one.

## Steps
1. **Scaffold**: `php caligrafer.php service <Folder>/<PascalName> --scope <scope> [--read-only]`
   (create_book -> `Books/CreateBook`). Never hand-write the attribute boilerplate.
2. **Fill the service** (`application/services/<Folder>/<Name>.php`):
   - `description`: what it does and returns, written for an AI agent choosing a tool (one or two sentences).
   - `$rules` for EVERY input `handle()` reads (`''` when there is no rule, G52), `$filters` (trim), `$descriptions`.
   - `handle()`: model calls with bound queries only, return data (selected columns: never password hashes or
     secrets), refuse with `ServiceError::invalid([...])`, `notFound()`, `forbidden()`, `conflict()`.
     No `view()`, `api()`, `redirect()`, `echo`, `$_SESSION` or request access (G52); use `$context->user`.
   - Flags: `readOnly: true` for reads; `destructive: true` for deletes and overwrites; `level: N` when only users
     with `permissions >= N` may call it; `public: true` only for data anyone may read.
3. **Channels** in `web.php`, before `Route::run()` (add only what the user needs, once per project for the last two):
   - REST: `Route::capability('POST', '/books', 'create_book');` (route variables become inputs)
   - Generic endpoint + JS client: `Route::capabilities('/capabilities');` then `php caligrafer.php client public/<app>/src/capabilities.js`
     (Vite SPA) or `public/js/capabilities.js`; use `createBook(...)` and `CapabilityError` in the Vue code.
   - MCP: `Route::mcp('/mcp');`
   - Pug page: `try { $book = $this->call('create_book'); ... } catch (ServiceError $e) { ... $e->fields ... }`
4. **A token for agents** (only when MCP or a program needs it): `php caligrafer.php token create "<what it is for>" --scopes <scopes>`
   (add `--user <id>` so the user's level applies, `--expires <days>`). Show the token once and tell the user to
   store it as a secret; never write it into a file of the project. Connect Claude Code:
   `claude mcp add --transport http <app> <url>/mcp --header "Authorization: Bearer <token>"`, or locally
   `claude mcp add <app> -- php caligrafer.php mcp` (Docker: `-- docker compose exec -T dev-box php caligrafer.php mcp`).
5. **Verify every channel** with the server running (`/caligrafy-quill:server status`):
   - `php caligrafer.php capabilities` lists it with the right scope and flags.
   - REST: `curl -s -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" ...` -> 200/201 `{"data": ...}`;
     the same call with a missing input -> 422 with `fields`; with a token without the scope -> 403.
   - MCP: `tools/list` shows it for a token with the scope only; `tools/call` returns `isError: false`, and invalid
     input returns `isError: true` with the same `fields` as REST.
   - `php caligrafer.php audit --capability <name>` shows the calls.
6. **Tests**: a PHPUnit test that runs `(new <Name>())->run([...])` for the rule cases, and, when the project has
   HTTP tests, one check per channel that the result and the refusals are the same.
7. Ask the `caligrafy-reviewer` agent to review the new files.
