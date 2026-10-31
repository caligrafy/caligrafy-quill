# Release notes: 2.1.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.1.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.1.0** adds the build-once layer: write a feature once as a **capability** and serve it to Pug pages, Vue apps, a REST API and AI agents over MCP, with personal access tokens and an audit log. Everything is additive: an existing project keeps working unchanged until it adopts a capability. The full guide is [11. Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

## Upgrade checklist for existing projects

Do these steps in order, on a branch, and commit after each one.

1. **Update the framework**: `git pull` (or download the release), then check: `php caligrafer.php version` prints `Caligrafy Quill 2.1.0` or newer. If you copied a newer framework (2.1.2+) over 2.1.0 or 2.1.1 instead of pulling it, `php caligrafer.php doctor` lists the old copies of `framework/core/Rest.php`, `Client.php` and `Mcp/` to delete: they moved to `framework/interfaces/`.
2. **Update your `application` folder**: `php caligrafer.php upgrade`, then `php caligrafer.php upgrade --apply` (the default layout now links `public/css/tokens.css`, and since 2.1.1 it adds the empty `application/services/` folder of your capabilities).
3. **Create the framework tables**: `php caligrafer.php platform install` (with Docker: `docker compose exec dev-box php caligrafer.php platform install`).
4. **Optional, when you adopt capabilities**: add `Route::capabilities('/capabilities');` and `Route::mcp('/mcp');` to `web.php`, create your first service with `php caligrafer.php service <Name>`, generate the JS client with `php caligrafer.php client`.
5. **Move API clients to tokens**: `php caligrafer.php token create <name> --scopes <scopes>` for each program or agent; browser apps call the capabilities through the JS client and the session instead of the API key.
6. **Turn the legacy key off** once nothing uses it: `LEGACY_API_KEY=false` in `.env`.
7. **Check**: `php caligrafer.php doctor` shows `Framework tables ... installed` and the state of the legacy key.

## What is new

| Change | What it gives you | Learn more |
| --- | --- | --- |
| Services and `#[Capability]` | business logic written once, with validation, permission and audit applied the same on every channel | [Write a capability](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service) |
| `Controller::call()` | Pug pages use the same capabilities | [From a Pug page](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#web) |
| `Route::capability()`, `Route::capabilities()` | REST routes and a generic endpoint, real status codes, JSON errors with field messages | [REST](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#rest) |
| Personal access tokens | scoped, expiring, revocable credentials for programs and agents, stored as hashes | [Tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#tokens) |
| `php caligrafer.php client` | a JS client for Vue apps and the SPA that uses the session, never a key in the bundle | [The JS client](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#client) |
| MCP (`Route::mcp()`, `php caligrafer.php mcp`) | AI agents such as Claude use your capabilities as tools, limited by their token | [MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp) |
| Audit log | who did what, through which channel, and every refusal | [Audit](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#audit) |
| Design tokens | the Caligrafy look as CSS properties, touch-first and installed-app adjustments | [One look](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#experiences) |
| Caligrafer | `platform install`, `token`, `audit`, `service`, `capabilities`, `client`, `mcp` | [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#doctor) |
| Claude Code plugin 1.11.0 | the `/caligrafy-quill:new-capability` command, gotchas G50-G57, reviewer checks | [Claude Code Plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin#commands) |

## Behavior changes

Nothing breaks, but these behave differently:

| Before 2.1 | Now |
| --- | --- |
| `Database::execute()` threw `[ERROR 2003]` when no controller had connected yet (closure routes) | it connects on first use when `DB_ACTIVATE=true` |
| A form `POST` without `_token` was refused even when it carried `Authorization: Bearer ...` | requests with a bearer header skip the form CSRF check (a cross-site form cannot set that header) |
| Only the API key was accepted on JSON controller routes | the API key, or a personal access token with the scope `*` |
| `doctor` | two more lines: the framework tables, and whether the legacy API key is allowed |

**Security note.** The legacy API key is given to every client page and built into the Vue SPA bundle, and JSON requests skip the CSRF check: anyone who can open an app page can call your JSON routes with it. Move clients to the JS client (session) and to tokens, then set `LEGACY_API_KEY=false`.

>[Back to Home](https://github.com/caligrafy/caligrafy-quill/wiki)
