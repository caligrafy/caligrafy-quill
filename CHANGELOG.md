# Changelog

Every release of Caligrafy Quill is listed here. The project follows [semantic versioning](https://semver.org): a
major version can break existing projects, a minor version adds features, a patch version only fixes bugs. Each
release is a git tag `v<version>` (on [GitHub](https://github.com/caligrafy/caligrafy-quill/tags) and
[Packagist](https://packagist.org/packages/caligrafy/caligrafy-quill)), and `Caligrafy\Framework::VERSION` holds the
same number. Check the version of a project with `php caligrafer.php version`.

## [2.5.0] - 2026-11-01

Pages go through one controller, and only capabilities touch the data: the rules of a feature live once, whether a
page, REST, an AI agent or the command line uses it.

### Added
- `Route::page($route, $view, $options)`: a page rendered through the framework's page controller
  (`Caligrafy\PageController`). Every page gets `authorized`, the signed-in `user` (id, username, permissions) and its
  route variables, so no page shows a signed-in person as a visitor. Options: `signedIn` (visitors go to `/login`),
  `level` (people below it go to `/notAuthorized`), `data` (`['book' => 'get_book']`: capabilities called as the person
  who opens the page, with the route variables as inputs; a refusal sends a visitor to sign in, or answers 403 or 404)
  and `with` (fixed values).
- The user capabilities in `application/services/Users` (framework class names: `CaligrafyListUsers`...): `list_users`, `get_user`, `update_user` and `delete_user`
  (administrators: `CaligrafyUsers::ADMIN_LEVEL`, 1 by default, which `AdminController` uses too; never a password; an administrator cannot delete their own account) and
  `register_user` (the sign-up page and the command line only: refused over REST and MCP). AI agents and programs with
  an administrator's token can manage users, with the audit log.
- Error codes 1005 (page not found, 404) and 1006 (forbidden, 403).
- `Route::page` option `notFound`: a view shown, with a 404, when a data capability finds nothing.
- `upgrade` holds the user capabilities (status `held`, with the reason) when the project manages users its own way: a
  customized `AdminController`, or a class or capability of the same name in `application/`. The controllers that
  call them are held too; `--force` adds a file anyway.

### Security
- The user capabilities only answer the command line or a real person at `CaligrafyUsers::ADMIN_LEVEL`: never the
  shared legacy API key or a token without an owner. No one manages someone above their own level or grants more than
  it; an administrator cannot delete their own account; deleting a user revokes their tokens.
- A token whose owner no longer exists is refused (with `Auth::authentication` on), instead of keeping the rights of a
  token without an owner.
- `Route::page` only calls read-only capabilities (opening a page never changes data), takes route variables from the
  route pattern (they win over the query string, which never reaches the view), and reserves `authorized` and `user`.

### Changed
- The user administration pages (`AdminController`) and sign-up (`AuthController@register`) call those capabilities
  instead of the `User` model: the same pages and messages, one set of rules. Signing in and out stay in
  `AuthController`: they are the web session, which capabilities never touch.
- The welcome page (`/` in the template `web.php`) and the Tasks example of `quickstart --example` use `Route::page`.

## [2.4.0] - 2026-11-01

Putting an application online: a production image in every project, the database prepared at every start, and
Caligrafy Cloud.

### Added
- `Dockerfile` and `Dockerfile.dockerignore`: the production image of a project (PHP 8.5 and Apache, the extensions
  Caligrafy uses, `composer.lock` without development packages, the production `php.ini`). `.env` stays out of the
  image: give the container its `.env` as a file at `/var/www/html/.env`. `docker/Dockerfile` remains the development
  image of `docker-compose.yaml`.
- `php caligrafer.php deploy [--wait <seconds>]`: what the production image runs before Apache starts. It waits for the
  database, creates the framework tables, then runs every `application/models/<folder>/create_db.sql` that is new or
  changed since its last run, recorded in the new `caligrafy_scripts` table. A changed script that drops, deletes or
  truncates is never run again on its own; `seed.sql` is never run.
- `php caligrafer.php cloud <login | account | create | deploy | apps | status | logs | extend | credits | secret |
  delete | logout>`: puts the project online at `https://<name>.caligrafy.com` with Caligrafy Cloud
  (https://deploy.caligrafy.com): built from its GitHub repository, its own MySQL database, the first 30 minutes free,
  then credits (a week 2, a month 5, a year 50; 1 credit = 1 USD). The token is kept in `.caligrafy/cloud.json`, now
  ignored by git; `CALIGRAFY_CLOUD_TOKEN` and `CALIGRAFY_CLOUD_URL` override it.
- The production image keeps PHP sessions in `/var/lib/php/sessions`: a volume there keeps people signed in across
  deploys.

### Changed
- The page components of `public/css/tokens.css` take the look of the Caligrafy sign-in card: 600px cards with the
  soft shadow and no grey outline, light labels, the 150px primary button on the left, a white footer section, the
  page just under the header. No markup changes: copy `public/css/tokens.css` from the release.

### Fixed
- With `APP_DEBUG=true`, the SMTP conversation of the mailer was printed into the page or into the JSON answer of an
  API call; it now goes to the server log.

## [2.3.0] - 2026-10-31

What upgrading a real site (caligrafy.com, from a September 2025 project) and building the course on 2.2.0 uncovered.

### Added
- `php caligrafer.php call <capability> ['{"json"}' | key=value ...] [--token <token>] [--guest]`: calls a capability
  from the command line and prints the same JSON as REST. The quickest way to try a capability before any route.
- `upgrade --keep <application/path>`: keeps a page that is an untouched copy of an older template (a default page that
  became a page of your site, such as the home page). The new version is written next to it, and the choice is
  remembered in the manifest.
- `upgrade` also reports, without changing anything: the settings of `framework/settings/.env.example` missing from
  `.env` and the obsolete ones (`CRYPTO_PAY_KEY`), and the views and client apps that still use Bootstrap 3 classes
  (`col-xs-`, `pull-left`, `btn-default`...) that break under Bootstrap 5.
- `doctor` warns about `APP_DEBUG=true` on a site served over HTTPS, and about a `CRYPTO_PAY_KEY` left from Coinbase
  Commerce (closed on March 31, 2026).
- `DB_TIMEZONE`: by default the MySQL session now uses `SERVER_TIMEZONE`, like PHP, so `NOW()`, `CURRENT_TIMESTAMP`
  columns and the audit log share one clock; `DB_TIMEZONE=server` keeps the database server's own time zone.

### Fixed
- The payment page template (`views/default/pages/payment.pug`): the Stripe Payment Element overflowed its box and
  `stripe_global.css` styled the whole page. The payment and checkout templates are now built with the page components.
- Several `.cq-card` in one `.cq-page` now stack (they sat side by side on wide screens).
- The Vue app template (`php caligrafer.php app`) calls the capabilities through the generated JS client instead of
  axios with the shared API key, which `LEGACY_API_KEY=false` refuses.
- `php caligrafer.php create` no longer says the packages were installed when `npm` is missing (the Docker `dev-box`
  has no Node.js) or failed: it says how to install them.
- The bot template says when the assistant cannot be reached, or that the address needs `?botSkillId=`.
- The face detection template shows its buttons at once (enabled when the models are loaded) and says when the camera
  is blocked.
- Claude Code plugin 1.14.0.

### Upgrading
`php caligrafer.php upgrade` (read its new reports), then `upgrade --apply`. Copy `public/css/tokens.css` and
`public/js/services/detect.js` from the release: they are not templates. Existing rows keep their times; new rows use
`SERVER_TIMEZONE` unless you set `DB_TIMEZONE=server`.

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
  (Quickstart, How Caligrafy works, Your first capability); a Caligrafer page with every command in one place;
  every core page says where it fits.
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

[2.3.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.3.0
[2.2.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.2.0
[2.1.3]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.3
[2.1.2]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.2
[2.1.1]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.1
[2.1.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.0
[2.0.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.0.0
