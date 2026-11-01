# Caligrafer: every command

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Caligrafer** is the command line tool of Caligrafy. It sets up a project, creates capabilities and apps, runs SQL, manages the tokens of programs and AI agents, checks your setup and updates your project. This page lists every command in one place.

## How to run it

| Where your PHP runs | Run Caligrafer with |
| --- | --- |
| **Docker** (the Quickstart, the course) | `docker compose exec dev-box php caligrafer.php <command>` |
| Your own Apache, PHP and MySQL | `php caligrafer.php <command>`, from the project folder |
| Either, for the commands that manage the project itself | `.bin/caligrafer <command>`: `quickstart`, `server`, `version`, `doctor`, `update`, `upgrade` |

`php caligrafer.php` alone prints the list of commands. In the rest of this page, `php caligrafer.php` means either of the first two forms.

## Every command at a glance

| Command | What it does |
| --- | --- |
| [`quickstart`](#quickstart) | Sets up a project in one step, on MAMP, XAMPP, LAMP or Docker; `--example` adds the Tasks example |
| [`initialize`](#initialize) | Prepares a fresh copy of the framework: `application/`, `.env`, keys |
| [`server`](#server) | Starts, stops or rebuilds the Docker development server |
| [`version`](#version) | Prints the version of Caligrafy Quill |
| [`design`](#design) | Picks a design style for every page (`DESIGN_STYLE`), or the Caligrafy look |
| [`service`](#service) | Creates a capability in `application/services/` |
| [`capabilities`](#capabilities) | Lists the capabilities and their inputs |
| [`call`](#call) | Calls a capability and prints its JSON answer |
| [`client`](#client) | Writes the JavaScript client of the capabilities |
| [`app`, `create`, `facedetect`, `ml`, `bot`](#apps) | Creates a client app in `public/` |
| [`sql`](#sql) | Runs a SQL script on the database |
| [`platform install`](#platform) | Creates the framework tables (tokens, audit log) |
| [`token`](#token) | Creates, lists and revokes personal access tokens |
| [`mcp`](#mcp) | An MCP server over stdio for AI agents on your computer |
| [`audit`](#audit) | Shows the latest capability calls and refusals |
| [`doctor`](#doctor) | Checks the project, the database and every configured service |
| [`update`](#update) | Moves the project to another release of Caligrafy Quill and applies everything (2.9.0+) |
| [`upgrade`](#upgrade) | Updates the `application/` templates of a project to the framework's |
| [`deploy`](#deploy) | Prepares the database in production (what the production image runs at start) |
| [`cloud`](#cloud) | Puts the project online at `https://<name>.caligrafy.com` with Caligrafy Cloud |
| [`generatekeys`, `generateapikey`](#keys) | Generates application keys and legacy API keys |

## Set up a project

### `quickstart`
```bash
.bin/caligrafer quickstart [--example] [--yes] [--local | --docker] [--url <address>] [--no-db | --db-defaults]
php caligrafer.php quickstart [--example] [--yes] [--url <address>] [--no-db]   # on your own server (Windows too)
composer create-project caligrafy/caligrafy-quill myapp                         # then the command it prints
```
From a fresh copy of the framework to a working application, in one step. `.bin/caligrafer quickstart` picks the server like [`server`](#server): a running local Apache (MAMP, XAMPP, LAMP) with PHP installed, else Docker; `--local` or `--docker` chooses.
1. installs the PHP libraries when they are missing (`.bin/caligrafer` uses Composer in Docker when Composer is not installed) and, with Docker, starts the Docker server;
2. initializes the project (see [`initialize`](#initialize)). It deletes the `.git` folder of the framework, so it asks first; `--yes` answers for you;
3. with Docker, points `.env` at the database of the server (`DB_ACTIVATE=true`, `DB_HOST=mysql`, `DB_DATABASE=caligrafy`). On a local server: gives the project its address and `APP_ROOT` from its place in the web folder (MAMP's settings are read: its web folder and ports; `--url` gives the address of another folder), then asks for the MySQL settings with the server's defaults (without a terminal to confirm them it skips the database, unless `--db-defaults`; `--no-db` skips it);
4. creates the database and the framework tables (if the settings it wrote do not work, it switches `DB_ACTIVATE` off again so that the pages still open);
5. adds `Route::capabilities('/capabilities')` (for the JS client) and `Route::mcp('/mcp')` (for AI agents) to `application/config/routing/web.php`;
6. with `--example`: adds the Tasks example (five capabilities, a page, REST routes, the table, the JS client) at `<address>/tasks`.

After `composer create-project`, Composer runs `php caligrafer.php created`: it says where the project will be served and the command to run next.

Every step can be run again: it only does what is missing and never overwrites your files. Without a database configured, it skips the database steps and tells you what to set. Learn more in the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart).

### `initialize`
```bash
php caligrafer.php initialize            # site in a sub-folder: http://localhost/<folder>/
php caligrafer.php initialize off        # site at the root (Docker): http://localhost:8080/
```
Prepares a fresh copy of the framework: creates `.env` from the template if needed, generates `APP_KEY` and `API_KEY` (an existing pair is kept), sets `APP_ROOT`, copies `framework/settings/application` to `application/` and records the template versions for [`upgrade`](#upgrade). It can delete the `.git` folder (the framework's history) so that your project starts its own: since 2.9.2 it asks first in a terminal and keeps it otherwise; `initialize --yes` deletes it without asking. Run it once, right after downloading the framework. `quickstart` runs it for you, after its own question.

### `server`
```bash
.bin/caligrafer server start      # http://localhost:8080, phpMyAdmin http://localhost:8077 (root / root)
.bin/caligrafer server stop
.bin/caligrafer server rebuild    # after a change to docker/Dockerfile, or to update the system packages
```
Starts, stops or rebuilds the Docker development server (`dev-box`, MySQL 8.4, phpMyAdmin). The first start builds the PHP image; later starts take seconds. Use the `.bin/caligrafer` form: it detects your setup (a running local Apache, `httpd` for MAMP and XAMPP or `apache2` for LAMP, is used as it is; otherwise Docker) and runs `initialize` first when the project is not initialized. The equivalent Docker commands are `docker compose up -d dev-box` and `docker compose stop`. If a port is taken, see the [Quickstart troubleshooting](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart#trouble).

### `version`
```bash
php caligrafer.php version        # Caligrafy Quill 2.2.0
```
Prints the version of the framework in this project (also `--version` and `-v`). It works before `composer install` and creates nothing. In code: `Caligrafy\Framework::VERSION`. The changes of every version are in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

### `design`
```bash
php caligrafer.php design list [--json]   # every style and its source (application/design, framework/design)
php caligrafer.php design show <name>     # its DESIGN.md, its description, its stylesheet
php caligrafer.php design use <name>      # DESIGN_STYLE=<name> in .env
php caligrafer.php design off             # DESIGN_STYLE= : the Caligrafy look
php caligrafer.php design status          # the selected style
```
Picks a [design style](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles): a `DESIGN.md` of your `application/design/`, of `framework/design/local/` (`interstellar`...) or of `framework/design/vendor/` (84 styles from VoltAgent/awesome-design-md, MIT), the first one winning on a name clash. Pages load `public/css/designs/<name>.css` after the Caligrafy stylesheets once it exists; `/caligrafy-quill:design` in Claude Code writes it from the `DESIGN.md`. `use` and `off` only change `DESIGN_STYLE` in `.env`: stylesheets are kept.

## Build features

### `service`
```bash
php caligrafer.php service Books/CreateBook --scope books:write
php caligrafer.php service Books/ListBooks --scope books:read --read-only
```
Creates a capability, ready to fill in: `application/services/Books/CreateBook.php` with the class `CreateBook` and the capability `create_book` (the snake_case of the class name). The name is in PascalCase, optionally in folders. `--scope` is the scope a token needs; `--read-only` marks a capability that changes nothing. It refuses to overwrite an existing file. Then fill in `$rules` and `handle()`: see [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability).

### `capabilities`
```bash
php caligrafer.php capabilities              # a table
php caligrafer.php capabilities --markdown   # for your documentation
php caligrafer.php capabilities --json       # with the input schemas
```
Lists the capabilities of `application/services/`: name, who may use them (`public`, a scope, or `signed in`), whether they read, write or destroy, and their description.
```
 create_book                  books:write    writes    Adds a book to the catalog and returns it with its id.
 list_books                   books:read     read      Lists every book of the catalog, newest first, with its id, title and author.
```

### `call`
```bash
php caligrafer.php call list_books                          # no input
php caligrafer.php call get_book id=3                       # key=value inputs
php caligrafer.php call create_book '{"title": "Kindred", "published_year": 1979, "author_id": 2}'
php caligrafer.php call create_book title=Kindred --guest   # as a visitor who is not signed in
php caligrafer.php call create_book title=Kindred --token cq_...   # as a token (or CALIGRAFY_TOKEN=cq_...)
```
Calls one capability and prints the same JSON as the REST API: `{"data": ...}`, or `{"error": {"code": "invalid", "message": "...", "fields": {...}}}` with exit code `1`. It is the quickest way to try a capability you just wrote, before any route or page. Without `--token` or `--guest` it has full local access, like any script you run (channel `cli` in the audit log); `--guest` and `--token` show what a visitor or a token may do. With Docker: `docker compose exec dev-box php caligrafer.php call ...`. *(Since 2.3.0.)*

### `client`
```bash
php caligrafer.php client                                   # public/js/capabilities.js
php caligrafer.php client public/myspa/src/capabilities.js  # a single page application
php caligrafer.php client --prefix /api/capabilities        # when Route::capabilities() uses another prefix
```
Writes the JavaScript client of the capabilities: one function per capability (`create_book` becomes `createBook`), plus `call(name, input)`, `capabilities()` and `CapabilityError`. In a page of the application it uses the session of the signed-in user; elsewhere `configure({ baseUrl, token })` sends a token. Run it again after you add or change a capability. It needs `Route::capabilities('/capabilities')` in `web.php` (added by `quickstart`). Learn more in [Vue apps: the JS client](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#client).

### `app`, `create`, `facedetect`, `ml`, `bot`
```bash
php caligrafer.php app catalog            # a Vue app (Vue as a library):    http://localhost:8080/apps/catalog
php caligrafer.php create myspa           # a Vite single page application (needs Node.js 20.19+)
php caligrafer.php facedetect faces       # face detection and face login:  http://localhost:8080/apps/faces
php caligrafer.php ml lab                 # machine learning in the browser: http://localhost:8080/ml/lab
php caligrafer.php bot helper             # a Watson chatbot:                http://localhost:8080/bot/helper?botSkillId=<id>
```
Each one copies a template into `public/<name>`. The name must be new (the command does not replace an existing folder) and cannot be one of the folders Caligrafy uses in `public/` (`app`, `bot`, `css`, `facedetect`, `fonts`, `images`, `js`, `ml`, `resources`, `uploads`). `create` also runs `npm install` when Node.js is available; in the Docker `dev-box`, which has no Node.js, it says so and you run `npm install` in `public/<name>` on your computer. Start it with `npm run dev` in `public/<name>`. Learn more: [Vue as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library), [the single page application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application), [face detection](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition), [machine learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning), [chatbots](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson).

## Data

### `sql`
```bash
php caligrafer.php sql application/models/Books/create_db.sql
```
Runs a SQL script (usually the `create_db.sql` of a model) on the database of `.env`. Comments are removed and the statements run one by one. It needs `DB_ACTIVATE=true` and the `DB_*` settings.

### `platform install`
```bash
php caligrafer.php platform install
```
Creates the two framework tables: `caligrafy_tokens` (personal access tokens) and `caligrafy_audit` (the audit log). Safe to run again. `quickstart` runs it for you; `doctor` tells you when they are missing.

## Programs and AI agents

### `token`
```bash
php caligrafer.php token create "Claude Code" --scopes books:read,books:write
php caligrafer.php token create "Reporting job" --scopes books:read --expires 90
php caligrafer.php token create "Alice's agent" --scopes "books:*" --user 7
php caligrafer.php token list
php caligrafer.php token revoke 3
```
Personal access tokens give a program or an AI agent exactly the rights it needs, over REST and MCP. The token (`cq_<id>_<secret>`) is shown **only once**: store it as a secret. Only its hash is kept.
+ `--scopes` (required): `resource:action` values separated by commas, `books:*` for every action on books, `*` for everything;
+ `--expires <days>`: the token stops working after that many days;
+ `--user <id>`: the token acts for that user, so the permission level of a capability applies to them.

`list` shows each token's state (active, expired, revoked), scopes and last use, never the secret. `revoke` stops a token at once. Learn more in [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#tokens).

### `mcp`
```bash
claude mcp add myapp -- php caligrafer.php mcp                                             # own server
claude mcp add myapp -- docker compose exec -T dev-box php caligrafer.php mcp              # Docker
CALIGRAFY_TOKEN=cq_... php caligrafer.php mcp                                              # limited to a token
```
An MCP server over stdio, for AI agents that run on your computer (Claude Code, Cursor...): one JSON-RPC message per line in, one answer per line out. Without a token it has full access, like any script you run; with `CALIGRAFY_TOKEN` (or `--token <token>`) it sees only the capabilities the token allows. For agents that reach your application over the network, use `Route::mcp('/mcp')` and a token instead: see [AI agents: MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp).

### `audit`
```bash
php caligrafer.php audit                                        # the latest 50 calls
php caligrafer.php audit --capability create_book --limit 200
```
Shows the audit log: when, through which experience (`web`, `rest`, `mcp`, `cli`), who (`user`, `token`, `key`, `cli` or `guest`, with its id), which capability, and the outcome (`ok`, `invalid`, `forbidden`...). Every change and every refusal is recorded; inputs never are.

## Check and maintain

### `doctor`
```bash
php caligrafer.php doctor                          # everything
php caligrafer.php doctor --only database          # some checks: project, database, openai, groq, agent, stripe, coinbase, watson, mail
php caligrafer.php doctor --env .env.production    # another environment file
php caligrafer.php doctor --json                   # for scripts
```
A read-only health check: PHP and its extensions, `vendor/`, `.env` and the keys, the legacy API key setting, `APP_DEBUG` on a site served over HTTPS, `public/uploads`, the web app manifest, the selected design style (`DESIGN_STYLE`), whether `application/` is up to date, the database and the framework tables, and every service configured in `.env` (OpenAI, Groq and the open-model providers, Stripe, Coinbase, Watson, mail). Each line is `OK`, `WARN`, `FAIL` or `SKIP` (not configured), with a fix. Secrets are never printed. It exits with `1` when a check fails. With Docker, run it in the container so that the database host resolves. Run it first whenever something does not work.
+ Billing and credits cannot be checked without spending: an OpenAI `429 insufficient_quota` on the first request means the account has no credits.
+ Network errors are explained: `self-signed certificate in certificate chain` means a company TLS inspection proxy (the Claude plugin's `server` skill has the Docker fix).
+ Optional overrides for tests, proxies or regional endpoints: `OPENAI_API_BASE`, `GROQ_API_BASE`, `AGENT_API_BASE`, `STRIPE_API_BASE`, `COINBASE_API_BASE`; `WATSON_ASSISTANT_ID` lets doctor check the assistant.

### `update`
```bash
php caligrafer.php update              # to the latest release, everything applied
php caligrafer.php update --check      # what it would change, nothing written
php caligrafer.php update 2.8.0        # to one release
```
Moves the project to another release of Caligrafy Quill in one command (2.9.0+). It downloads the project's release (the version `version` prints) and the new one from GitHub, then:

1. replaces `framework/` and `claude-plugin/` whole (files the new release dropped go too; the files `initialize` wrote there stay);
2. compares every other file of the release with your project's release: `caligrafer.php`, `.bin/`, `Dockerfile`, `docker-compose.yaml`, `index.php`, `public/css`, `public/js`... A file you never changed is replaced, a missing one is added, a file you changed is kept with the new version next to it as `<file>.caligrafy-new`. When the release did not change a file, your version stays as it is. Your `README.md` and the other documents of your project are left alone;
3. merges `composer.json` (new packages, new versions of the packages you did not change), then runs `composer update` for them (`--no-composer`: it only prints the command); adds the new lines of `.gitignore`, and the new settings of `.env` with their defaults (your values never change);
4. runs `php caligrafer.php upgrade --apply` from the new release, for `application/` ([`upgrade`](#upgrade)).

Everything it replaces is first copied to `backups/caligrafy-update-<from>-to-<to>-<time>/`. With uncommitted git changes it stops (commit first, or `--yes`). `application/`, your `.env` values, `public/uploads` and `vendor/` are never replaced. Then merge the `.caligrafy-new` files into yours, delete them, and run `doctor`. With Claude Code, `/caligrafy-quill:update` does all of it, the merges included.

**A project before 2.9.0** does not have the command yet: get it once, then run it.
```bash
curl -fsSL https://raw.githubusercontent.com/caligrafy/caligrafy-quill/master/framework/core/Updater.php -o framework/core/Updater.php
php -r "require 'framework/core/Updater.php'; exit((new Caligrafy\Updater('.'))->run());"
```
In Docker, run each `php` line with `docker compose exec dev-box` in front.

### `upgrade`
```bash
php caligrafer.php upgrade                                     # dry run: what would change
php caligrafer.php upgrade --apply                             # update the files you never changed
php caligrafer.php upgrade --force application/views/Auth/login.pug   # replace one file you changed
php caligrafer.php upgrade --apply --keep application/views/default/index.pug   # keep a default page that is now yours
```
`update` runs it for you. After you update the framework of a project by hand (`git pull`, or a new release copied over `framework/`), `upgrade` compares your `application/` with the framework's templates. Files you never changed are updated (with a backup in `backups/caligrafy-upgrade-<time>/`); files you changed are kept, and the new version is written next to them as `<file>.caligrafy-new` for you to merge; your routes are never replaced. `--keep` keeps a page that is an untouched copy of an older template (a default page that became a page of your site, like the home page): the new version is written next to it, and later upgrades remember it. It also reports, without changing anything: client apps in `public/` that differ from their template, settings of `framework/settings/.env.example` missing from your `.env` and obsolete ones (`CRYPTO_PAY_KEY`), and views or apps that still use Bootstrap 3 classes (`col-xs-`, `pull-left`, `btn-default`...), which break under Bootstrap 5. Learn more in [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading).

## Put it online

### `deploy`
```bash
php caligrafer.php deploy                 # framework tables, then every new or changed create_db.sql
php caligrafer.php deploy --wait 120      # wait up to 2 minutes for the database (default 60)
```
What the production `Dockerfile` runs before Apache starts. It waits for the database, creates the framework tables, then runs every `application/models/<folder>/create_db.sql` that is new or changed since its last run, and records it in the `caligrafy_scripts` table. A changed script that drops, deletes or truncates is never run again on its own (run it with [`sql`](#sql) when you mean it); `seed.sql` is never run. It exits with `1` when the database cannot be prepared. Learn more in [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#deploy).

### `cloud`
```bash
php caligrafer.php cloud login                          # approve the code in the browser page it opens (2.7.0+)
php caligrafer.php cloud login --no-browser             # only prints the address to open (it does that by itself in Docker)
php caligrafer.php cloud login --token cq_...           # a token created on the dashboard, for scripts (--paste: a hidden prompt)
php caligrafer.php cloud account                        # your email and credits
php caligrafer.php cloud create myapp                   # from this project's GitHub repository (git origin), branch main
php caligrafer.php cloud create myapp --repo https://github.com/me/myapp --branch live
php caligrafer.php cloud key myapp                      # the read-only deploy key of a private repository (also in .caligrafy/myapp-deploy-key.pub)
php caligrafer.php cloud deploy myapp                   # build and start it, following the build (--no-wait: do not follow)
php caligrafer.php cloud apps                           # your apps, their status and time left
php caligrafer.php cloud status myapp
php caligrafer.php cloud logs myapp                     # the build while it deploys, then what the app prints
php caligrafer.php cloud extend myapp week              # week (2 credits), month (5) or year (50)
php caligrafer.php cloud credits 10                     # buy 5, 10, 25 or 50 credits on Stripe (1 credit = 1 USD)
php caligrafer.php cloud secret set myapp OPEN_AI_KEY=sk-...   # a setting of its .env, applied at the next deploy
php caligrafer.php cloud secret list myapp              # the names of its settings
php caligrafer.php cloud secret delete myapp OPEN_AI_KEY
php caligrafer.php cloud delete myapp --yes             # the app, its database and its files, for good
php caligrafer.php cloud logout
```
Puts the project online with [Caligrafy Cloud](https://deploy.caligrafy.com): `https://<name>.caligrafy.com`, built from its GitHub repository with its `Dockerfile`, with its own MySQL database. Every new app is free for its first 30 minutes online, from its first deploy; then credits keep it online. `login` is approved in the browser and keeps its token in `.caligrafy/cloud.json` (ignored by git); `CALIGRAFY_CLOUD_TOKEN` and `CALIGRAFY_CLOUD_URL` override it. Learn more in [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud).

### `generatekeys`, `generateapikey`
```bash
php caligrafer.php generatekeys       # a new APP_KEY and API_KEY pair
php caligrafer.php generateapikey     # another legacy API key for the current APP_KEY
```
`initialize` already writes the keys to `.env`: use `generatekeys` for another environment (production) or to replace a leaked `APP_KEY` (it signs everyone out). Legacy API keys are all equivalent and cannot be revoked one by one: for programs and agents, prefer [`token`](#token).

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
