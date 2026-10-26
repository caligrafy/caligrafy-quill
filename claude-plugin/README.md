# caligrafy-quill - Claude Code plugin

Gives Claude Code full knowledge of the [Caligrafy Quill](https://github.com/caligrafy/caligrafy-quill) framework.

| Component | Name | What it does |
| - | - | - |
| Skill (auto) | `caligrafy` | Index of the complete Caligrafy wiki (all 35 pages in `skills/caligrafy/reference/`), a source-verified API reference and a list of verified gotchas/wiki errata (G1-G48) and end-to-end recipes (OpenAI/Groq chat apps, face login). Loads whenever Claude works in a Quill project |
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

The files in `skills/caligrafy/reference/0*.md`, `10-claude-code-plugin.md` and `wiki-sidebar.md` mirror the wiki
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
