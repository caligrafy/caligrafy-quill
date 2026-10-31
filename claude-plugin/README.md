# caligrafy-quill - Claude Code plugin

Gives Claude Code full knowledge of the [Caligrafy Quill](https://github.com/caligrafy/caligrafy-quill) framework.

| Component | Name | What it does |
| - | - | - |
| Skill (auto) | `caligrafy` | Index of the complete Caligrafy wiki (all 37 pages in `skills/caligrafy/reference/`), a source-verified API reference and a list of verified gotchas/wiki errata (G1-G49) and end-to-end recipes (OpenAI/Groq chat apps, face login). Loads whenever Claude works in a Quill project |
| Skill | `/caligrafy-quill:new-resource <Entity> [field:type ...]` | SQL `create_db.sql` + Model + Controller (filter/validate CRUD) + REST routes |
| Skill | `/caligrafy-quill:new-app <app\|create\|bot\|facedetect\|ml> <name>` | Scaffolds a client app with caligrafer and wires its page and data routes |
| Skill | `/caligrafy-quill:server <start\|stop\|status>` | Docker dev server via `.bin/caligrafer`, with port-clash and `.env` checks, and troubleshooting (port overrides, corporate TLS proxies) |
| Skill | `/caligrafy-quill:db <script.sql\|tables\|query "...">` | Runs SQL scripts in the `mysql` container (or local MySQL), with safety checks for destructive scripts |
| Agent | `caligrafy-reviewer` | Reviews Caligrafy code against the framework's real behavior |
| Hook | PostToolUse `php -l` | Syntax-checks every `.php` file Claude writes or edits (skipped when PHP is not installed) |

## Install

It installs itself: the Quill repository ships `.claude/settings.json`, which registers this folder's marketplace
(`.claude-plugin/marketplace.json` at the repo root) and enables `caligrafy-quill@caligrafy`. Open the project in
Claude Code and accept the folder trust prompt.

Manual install from GitHub:
```
/plugin marketplace add caligrafy/caligrafy-quill
/plugin install caligrafy-quill@caligrafy
```
or from a shell: `claude plugin marketplace add caligrafy/caligrafy-quill && claude plugin install caligrafy-quill@caligrafy`.

Local development: `claude --plugin-dir ./claude-plugin`, and `claude plugin validate ./claude-plugin --strict`.

## Updating the knowledge

The files in `skills/caligrafy/reference/0*.md`, `1*.md` and `wiki-sidebar.md` mirror the wiki
(https://github.com/caligrafy/caligrafy-quill/wiki) with a source link at the top of each. Refresh them from a wiki clone:
```
git clone https://github.com/caligrafy/caligrafy-quill.wiki.git ../caligrafy-quill.wiki
python3 claude-plugin/scripts/sync-wiki.py ../caligrafy-quill.wiki            # rewrite the mirrors
python3 claude-plugin/scripts/sync-wiki.py ../caligrafy-quill.wiki --check    # exit 1 when a mirror is stale
```
The script keeps each mirror's "Verified against framework source" note. `api-reference.md` and `gotchas.md` are
written from the framework source: update them when `framework/` changes (and run `./qa/run.sh`), then bump
`version` in `.claude-plugin/plugin.json`.

## Changelog
- **1.10.0** - Caligrafy Quill v2.0.0, the first tagged release: `Caligrafy\Framework::VERSION`, `php caligrafer.php version`
  (works without `vendor/` or `.env`), the version on the first line of `doctor` (`"version"` in `--json`), `CHANGELOG.md`
  and semantic versioning; `composer create-project` without `-s dev`. API reference, SKILL.md and the wiki mirrors updated.
- **1.9.3** - Face login: the `blink` liveness challenge is removed (unreliable on real cameras; `liveness()` refuses it);
  the server picks 2 of `turn-left`, `turn-right`, `smile` in a random order (`FaceLogin::$count` 2) and `liveness()`
  passes only with the 3 prints `FaceLogin::verify()` needs. New face login UI in the recipe and the QA fixture, in the
  Caligrafy black and white look: Sign in / Enrol tabs, oval face guide with placement hints, prompts on the video,
  liveness started when the face is in the oval, password sign-in and account creation on the page
  (`/facelogin/password`, `/facelogin/register`, with the lockout). Recipe, API reference, G42, new-app skill, wiki
  9.9.2 and the October 2026 release notes updated.
- **1.9.2** - Fast Docker server: `server start` no longer forces a rebuild (`docker compose up -d`), new `server rebuild`,
  slim Dockerfile (5 system libraries over HTTPS with HTTP fallback, `--no-install-recommends`, no rebuilt built-in
  extensions), `.dockerignore`; `initialize` stops when the application template cannot be copied. Server skill,
  API reference and Getting Started updated.
- **1.9.1** - Documentation of the October 2026 changes: new wiki pages "Release notes: October 2026" (upgrade
  checklist, every change with what to do in new and existing projects, compatibility summary) and "Testing Caligrafy"
  (QA phases, `qa/unit`, mocks, adding tests, release process), mirrored as `11-release-notes-october-2026.md` and
  `12-testing-caligrafy.md` and listed in the skill index. Wiki pages reviewed against the source (installation commands,
  Vue 3 examples, AI method signatures, error status codes, payments, authentication paths) and mirrors synced.
  G25: `initialize` always rewrites `APP_ROOT`; server skill: `APP_ROOT=` for dev-box. Skill: PHP >= 8.3.
- **1.9.0** - Install to home screen: web app manifest, optional offline service worker (`PWA_SERVICE_WORKER`),
  `modules/install.pug` (Chrome/Edge/Android install button, iOS hint), doctor web app check. Face login security:
  `FaceDetector.liveness()` challenges, `Caligrafy\FaceLogin` single-use nonces and anti-replay checks, passkeys
  (`Caligrafy\Passkey` on lbuchs/webauthn, `public/js/services/passkey.js`); recipe rewritten. Agent providers:
  Groq, Together, Fireworks, OpenRouter, Ollama and custom OpenAI-compatible endpoints (`AGENT_PROVIDER`,
  `AGENT_MODEL`...), `Agent::models()`, doctor `agent` check; Llama examples. G40, G42, G17/G33, API reference,
  recipes, new-app skill and reviewer updated.
  G38 `QA_NO_BUILD=1`; G49 `.env` comments on their own line; server skill: image build killed during apt-get.
- **1.8.1** - G38: the QA suite runs the new PHPUnit unit tests of the framework core (`qa/unit`, `vendor/bin/phpunit`).
- **1.8.0** - `php caligrafer.php doctor` (`.bin/caligrafer doctor`): read-only health check of the project and of the
  services in `.env` (database, OpenAI and Groq keys and models, Stripe mode, Coinbase, Watson, SMTP EHLO) with masked
  secrets, key-format warnings, TLS/DNS/timeout hints, `--only`, `--json` and `--env`. Documented in the api-reference
  Caligrafer CLI table, the server skill troubleshooting (run doctor first), the caligrafy workflow rules and the AI recipe.
  Troubleshooting (gotchas), db and new-app skills point to `doctor`.
- **1.7.1** - The signed-in header keeps the Bootstrap 3 column padding and the title next to the menu icon on phones
  (`theme.css`, QA check); G40 and the Views page document it.
- **1.7.0** - Explicit layout classes: the Pug layout body is `body(id='gesturize' class='caligrafy-layout')` and the
  Bootstrap 5 compatibility layer of `theme.css` scopes its heading margins to `body:where(#gesturize, .caligrafy-layout)`
  (the id stays an alias for older layouts). Client apps that reuse the Caligrafy header add `header.caligrafy-header`
  instead of a custom title margin (G40, new-app). Compat rules that override a same-selector Bootstrap rule are
  prefixed with `html`, so they no longer depend on the stylesheet order; the look is unchanged (measured).
- **1.6.0** - Namespaced controllers: G47 is fixed. The example controllers of `application/controllers/core/` are in
  `Caligrafy\Controllers`, routes accept fully qualified controllers (`'Shop\OrderController@show'`), and a short name that
  is not a declared class falls back to `Caligrafy\Controllers` so old routes keep working; relationships with namespaced
  models use the short name for default foreign keys. API reference (Route, Controller), G19, reviewer item 15,
  new-resource, new-app and the recipes updated; wiki 2, 8 and 9.5 updated.
- **1.5.0** - `php caligrafer.php upgrade [--apply] [--force <path>]`: API reference Caligrafer CLI section (statuses,
  manifest `application/.caligrafy-manifest.json`, `framework/settings/template-history.json`, backups, routes `catch`
  patch, client app report), G39 and the skill workflow recommend it for older projects; wiki 9.5 and 1 updated.
- **1.4.1** - Framework errors use real HTTP status codes: G48 is fixed (401 for 1004, 403 for 3000, 404 for 1001,
  405 for 1000, 500 for other errors, PHP fatals and uncaught exceptions with the message only when `APP_DEBUG=true`;
  JSON body `{"error": {"code", "message"}}` for API calls, unchanged `[ERROR ...]` text for HTML). API reference
  error section (`Errors::handle()`, `Errors::status()`, `Errors::raise()`), AI chat recipe and reviewer item 15
  updated; upgrade note for the `catch` of older `web.php` files.
- **1.4.0** - End-to-end recipes: `reference/recipes-ai-apps.md` (OpenAI chat with filtered and capped history,
  OpenAI errors returned as `response['error']` with `api_success` true, live OpenAI and Groq model lists validated
  by regex, gpt-image images as data URLs and the 30 s `httpRequest()` timeout, Groq chat and vision from a validated
  data URL, Vue chat UI in the Caligrafy look) and `reference/recipes-face-login.md` (encrypted face prints,
  enrolment checks, server-side matching, lockout in UTC, `Auth::authorize`, FaceDetector client, security limits),
  linked from the caligrafy and new-app skills. server skill: troubleshooting for port clashes
  (`docker-compose.override.yml` with `ports: !override`) and corporate TLS inspection proxies (corporate root CA
  mounted into the container). new-app description names @vladmandic/face-api 1.7.15 instead of face-api.js.
- **1.3.0** - `FaceDetector` camera control and face prints (`autoStart: false`, `startStream()` with camera errors,
  `streamError`, `stopStream()`, `faceprint()`) documented in G42 and the API reference; Bootstrap 5 buttons in the
  Caligrafy black and white look and the 150px form buttons (G40); new gotchas G47 (controller names that collide with
  the framework examples) and G48 (framework errors served with HTTP 200); reviewer item 15. Bootstrap 5 layout compatibility in G40 (full-width container, header title, grid breakpoints,
  card borders and colors), SPA template on Bootstrap 5. new-app skill: FaceDetect camera errors and face prints, Caligrafy
  black and white look for client apps. FaceDetector streams into the live video element (`resolveMedia()`).
- **1.2.0** - Follows the library upgrade: gotchas G40-G46 (Bootstrap 5 without jQuery, ML Core 2.0 on ml5 1.4 / p5 2.3,
  @vladmandic/face-api, Coinbase Business, PHP 8.3+ / Docker PHP 8.5, Pinia, integration `.env` keys) and updated
  G16, G22, G31-G33, G36, G37; API reference updated (Assistant 2.0 on Responses + Conversations, OpenAI GPT-6 and
  gpt-image-2, CryptoPayment 2.0, Payment Element and checkout `url`, Groq defaults, watsonx `WATSON_URL`, Docker
  images, pinned front-end libraries); new-app skill uses Node 20.19+ and Pinia; reviewer checks for Bootstrap 3
  classes, jQuery, Vuex and retired APIs; all changed wiki pages mirrored.
- **1.1.0** - Follows the framework security release: gotchas G34-G39 (server-file protection, client-app template,
  Watson bot on Vue 2, payments, QA suite, upgrading old projects), fixed items marked **Fixed**, API reference
  updated (Model bindings, `Database::dsn`, `uploadFile` allow-list, `OpenAI::command/type`, Assistant polling),
  wiki pages 9.5 Security and Upgrading and 10 Claude Code Plugin mirrored, `scripts/sync-wiki.py`, reviewer checks
  for uploads, `constant()` on user input, outdated templates and Vue 2 syntax.
- **1.0.0** - Initial release.
