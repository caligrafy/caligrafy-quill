# Changelog

Every release of Caligrafy Quill is listed here. The project follows [semantic versioning](https://semver.org): a
major version can break existing projects, a minor version adds features, a patch version only fixes bugs. Each
release is a git tag `v<version>` (on [GitHub](https://github.com/caligrafy/caligrafy-quill/tags) and
[Packagist](https://packagist.org/packages/caligrafy/caligrafy-quill)), and `Caligrafy\Framework::VERSION` holds the
same number. Check the version of a project with `php caligrafer.php version`.

## [2.2.0] - 2026-10-31

One command from a fresh project to a working build-once application, and documentation that starts there.

### Added
- `.bin/caligrafer quickstart [--example]`, with Docker only (Composer runs in Docker): installs the libraries, starts
  the server, initializes the project (it asks before deleting `.git`), points `.env` at the Docker database, creates
  the database and the framework tables, and adds `Route::capabilities('/capabilities')` and `Route::mcp('/mcp')`.
  `php caligrafer.php quickstart` does the same on your own server. Every step can be run again.
- `--example`: the Tasks example (`framework/settings/examples/tasks`): five capabilities, a page built with the page
  components, REST routes, the table and the JS client; a web page, `curl` and an AI agent use the same tasks.
- `php caligrafer.php sql <file.sql>` runs a SQL script on the database of `.env`.
- Documentation: a new README and wiki Home, a sidebar organized by what you want to do, and three Start here pages
  (Quickstart, How Caligrafy works, Your first capability); every core page says where it fits.
- Claude Code plugin 1.13.0.

## [2.1.3] - 2026-10-31

### Added
- Page components in `public/css/tokens.css`: the look of the sign-in and face login screens for every new page
  (`.cq-page`, `.cq-card`, `.cq-card-head`, `.cq-form`, `.cq-fields`, `.cq-submit`, `.cq-error`, `.cq-tabs`,
  `.cq-count`, `.cq-list`, `.cq-item`, `.cq-actions`, `.cq-empty`, `.cq-foot`...), linked by the default layout, the
  Vue app template and the SPA template.
- Claude Code plugin 1.12.0: UI guidelines that every scaffolding skill, the reviewer and `AGENTS.md` follow, so new
  pages are built with the page components and checked at desktop and phone width; gotchas G59 and G60.

## [2.1.2] - 2026-10-31

### Changed
- The classes that expose capabilities to the world moved to `framework/interfaces/`: `Rest`, `Client` and
  `Mcp\Server`, `Mcp\Http`. `framework/core/` keeps the framework and what a capability is. Class names, routes
  and projects do not change.
- A framework copied over 2.1.0 or 2.1.1 keeps old copies in `framework/core/`: they are never loaded, and
  `php caligrafer.php doctor` asks to delete them.
- `ARCHITECTURE.md` describes where the code lives.

## [2.1.1] - 2026-10-31

### Fixed
- New projects get an empty `application/services/` folder, where capabilities live, as the documentation says.
  `php caligrafer.php upgrade --apply` adds it to existing projects.

## [2.1.0] - 2026-10-31

Build once, experience everywhere: a feature written once as a capability is served to web pages, Vue apps, REST and
AI agents. Additive: existing projects keep working unchanged. The
[Release notes: 2.1.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.1.0) have the upgrade checklist.

### Added
- Services and capabilities: `Caligrafy\Service` (GUMP rules and filters, `handle()` returns data), `ServiceError`
  (422, 401, 403, 404, 409 with field messages), the `#[Capability]` attribute (scope, permission level, read-only,
  destructive, public) and the `Capabilities` registry (JSON Schema from the rules, permission, validation and audit
  on every call).
- Channels: `Controller::call()` for Pug pages, `Route::capability()` and `Route::capabilities()` for REST (the session
  writes need `X-CSRF-Token`), MCP for AI agents with `Route::mcp()` (Streamable HTTP) and `php caligrafer.php mcp`
  (stdio), and a generated JS client (`php caligrafer.php client`).
- Personal access tokens (`Caligrafy\Tokens`): scoped, expiring, revocable, stored as SHA-256 hashes;
  `php caligrafer.php token create|list|revoke`. `LEGACY_API_KEY=false` turns the shared API key off.
- Audit log (`Caligrafy\Audit`, `php caligrafer.php audit`), framework tables (`php caligrafer.php platform install`).
- `php caligrafer.php service <Name>` and `capabilities [--json|--markdown]`.
- Design tokens (`public/css/tokens.css`, `tokens.json`) shared by the layout, the Vue app and the SPA templates.
- `ARCHITECTURE.md` and `AGENTS.md`; Claude Code plugin 1.11.0 with `/caligrafy-quill:new-capability`.

### Changed
- `Database::execute()` connects on first use, so closure and capability routes can use models.
- Requests with an `Authorization: Bearer` header skip the form CSRF check; JSON controller routes also accept
  personal access tokens with the scope `*`.
- `doctor` reports the framework tables and whether the legacy API key is allowed.

### Security
- The legacy API key is readable by anyone who opens an app page: move clients to tokens and the session, then set
  `LEGACY_API_KEY=false`.

## [2.0.0] - 2026-10-30

The first tagged release. Everything before it was only available as `dev-master`. The
[Release notes: October 2026](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026) explain
every change, what can break and the upgrade checklist for existing projects.

### Breaking changes
- PHP 8.3 or higher, and stable Composer releases instead of `dev-master` branches (stripe-php 22, Phug 2,
  PHPMailer 7, phpdotenv 5.8, GUMP 2.3, SimpleImage 4.4, groq-php 1.4).
- Errors return real HTTP status codes and JSON errors for API requests.
- Bootstrap 5 without jQuery (the Caligrafy look is kept), Vue 3.5, a Vite 8 + Pinia SPA template instead of Vuex.
- OpenAI on current models with images from `gpt-image-2` (base64), `Assistant` on the Responses API, the Stripe
  Payment Element and Checkout URLs, Coinbase Business, Watson on `WATSON_URL`, ML Core 2.0 and
  @vladmandic/face-api.
- The example controllers moved to the `Caligrafy\Controllers` namespace (short route names keep working).

### Added
- `php caligrafer.php upgrade` brings an existing project's `application/` up to date without overwriting
  customized files.
- `php caligrafer.php doctor` checks `.env`, the database and every configured service (read-only).
- `php caligrafer.php version` and `Caligrafy\Framework::VERSION`; `doctor` shows the version on its first line.
- Namespaced controllers in routes (`'Shop\OrderController@show'`).
- Install to home screen (web app manifest, optional service worker).
- Face login with liveness challenges and anti-replay checks, and passkeys (WebAuthn).
- Open model agents on Groq, Together, Fireworks, OpenRouter or Ollama (`AGENT_PROVIDER`).
- The `caligrafy-quill` Claude Code plugin in `claude-plugin/`.
- PHPUnit unit tests (`qa/unit`) and the Docker QA suite (`qa/run.sh`).

### Security
- `.env` and the server files are no longer served, uploads are limited to an allow-list of extensions, and
  scripts never run from `public/uploads`.

[2.2.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.2.0
[2.1.3]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.3
[2.1.2]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.2
[2.1.1]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.1
[2.1.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.0
[2.0.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.0.0
