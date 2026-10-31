# Caligrafer: every command

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Caligrafer** is the command line tool of Caligrafy. It sets up a project, creates capabilities and apps, runs SQL, manages the tokens of programs and AI agents, checks your setup and updates your project. This page lists every command in one place.

## How to run it

| Where your PHP runs | Run Caligrafer with |
| --- | --- |
| **Docker** (the Quickstart, the course) | `docker compose exec dev-box php caligrafer.php <command>` |
| Your own Apache, PHP and MySQL | `php caligrafer.php <command>`, from the project folder |
| Either, for the commands that manage the project itself | `.bin/caligrafer <command>`: `quickstart`, `server`, `version`, `doctor`, `upgrade` |

`php caligrafer.php` alone prints the list of commands. In the rest of this page, `php caligrafer.php` means either of the first two forms.

## Every command at a glance

| Command | What it does |
| --- | --- |
| [`quickstart`](#quickstart) | Sets up a project in one step; `--example` adds the Tasks example |
| [`initialize`](#initialize) | Prepares a fresh copy of the framework: `application/`, `.env`, keys |
| [`server`](#server) | Starts, stops or rebuilds the Docker development server |
| [`version`](#version) | Prints the version of Caligrafy Quill |
| [`service`](#service) | Creates a capability in `application/services/` |
| [`capabilities`](#capabilities) | Lists the capabilities and their inputs |
| [`client`](#client) | Writes the JavaScript client of the capabilities |
| [`app`, `create`, `facedetect`, `ml`, `bot`](#apps) | Creates a client app in `public/` |
| [`sql`](#sql) | Runs a SQL script on the database |
| [`platform install`](#platform) | Creates the framework tables (tokens, audit log) |
| [`token`](#token) | Creates, lists and revokes personal access tokens |
| [`mcp`](#mcp) | An MCP server over stdio for AI agents on your computer |
| [`audit`](#audit) | Shows the latest capability calls and refusals |
| [`doctor`](#doctor) | Checks the project, the database and every configured service |
| [`upgrade`](#upgrade) | Updates the `application/` templates of a project to the framework's |
| [`generatekeys`, `generateapikey`](#keys) | Generates application keys and legacy API keys |

## Set up a project

### `quickstart`
```bash
.bin/caligrafer quickstart [--example] [--yes]          # with Docker: nothing else needed on your computer
php caligrafer.php quickstart [--example] [--yes]       # on your own server
```
From a fresh copy of the framework to a working application, in one step:
1. installs the PHP libraries (`.bin/caligrafer` uses Composer in Docker when Composer is not installed) and starts the Docker server;
2. initializes the project (see [`initialize`](#initialize)). It deletes the `.git` folder of the framework, so it asks first; `--yes` answers for you;
3. with Docker, points `.env` at the database of the server (`DB_ACTIVATE=true`, `DB_HOST=mysql`, `DB_DATABASE=caligrafy`);
4. creates the database and the framework tables;
5. adds `Route::capabilities('/capabilities')` (for the JS client) and `Route::mcp('/mcp')` (for AI agents) to `application/config/routing/web.php`;
6. with `--example`: adds the Tasks example (five capabilities, a page, REST routes, the table, the JS client) at http://localhost:8080/tasks.

Every step can be run again: it only does what is missing and never overwrites your files. Without a database configured, it skips the database steps and tells you what to set. Learn more in the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart).

### `initialize`
```bash
php caligrafer.php initialize            # site in a sub-folder: http://localhost/<folder>/
php caligrafer.php initialize off        # site at the root (Docker): http://localhost:8080/
```
Prepares a fresh copy of the framework: creates `.env` from the template if needed, generates `APP_KEY` and `API_KEY` (an existing pair is kept), sets `APP_ROOT`, copies `framework/settings/application` to `application/` and records the template versions for [`upgrade`](#upgrade). **It deletes the `.git` folder**: run it once, right after downloading the framework, never in a project with your own history. `quickstart` runs it for you.

### `server`
```bash
.bin/caligrafer server start      # http://localhost:8080, phpMyAdmin http://localhost:8077 (root / root)
.bin/caligrafer server stop
.bin/caligrafer server rebuild    # after a change to docker/Dockerfile, or to update the system packages
```
Starts, stops or rebuilds the Docker development server (`dev-box`, MySQL 8.4, phpMyAdmin). The first start builds the PHP image; later starts take seconds. Use the `.bin/caligrafer` form: it detects your setup and runs `initialize` first when the project is not initialized. The equivalent Docker commands are `docker compose up -d dev-box` and `docker compose stop`. If a port is taken, see the [Quickstart troubleshooting](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart#trouble).

### `version`
```bash
php caligrafer.php version        # Caligrafy Quill 2.2.0
```
Prints the version of the framework in this project (also `--version` and `-v`). It works before `composer install` and creates nothing. In code: `Caligrafy\Framework::VERSION`. The changes of every version are in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

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
Each one copies a template into `public/<name>`. The name must be new (the command does not replace an existing folder) and cannot be one of the folders Caligrafy uses in `public/` (`app`, `bot`, `css`, `facedetect`, `fonts`, `images`, `js`, `ml`, `resources`, `uploads`). `create` also runs `npm install`; start it with `npm run dev` in `public/<name>`. Learn more: [Vue as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library), [the single page application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application), [face detection](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition), [machine learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning), [chatbots](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson).

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
A read-only health check: PHP and its extensions, `vendor/`, `.env` and the keys, the legacy API key setting, `public/uploads`, the web app manifest, whether `application/` is up to date, the database and the framework tables, and every service configured in `.env` (OpenAI, Groq and the open-model providers, Stripe, Coinbase, Watson, mail). Each line is `OK`, `WARN`, `FAIL` or `SKIP` (not configured), with a fix. Secrets are never printed. It exits with `1` when a check fails. With Docker, run it in the container so that the database host resolves. Run it first whenever something does not work.
+ Billing and credits cannot be checked without spending: an OpenAI `429 insufficient_quota` on the first request means the account has no credits.
+ Network errors are explained: `self-signed certificate in certificate chain` means a company TLS inspection proxy (the Claude plugin's `server` skill has the Docker fix).
+ Optional overrides for tests, proxies or regional endpoints: `OPENAI_API_BASE`, `GROQ_API_BASE`, `AGENT_API_BASE`, `STRIPE_API_BASE`, `COINBASE_API_BASE`; `WATSON_ASSISTANT_ID` lets doctor check the assistant.

### `upgrade`
```bash
php caligrafer.php upgrade                                     # dry run: what would change
php caligrafer.php upgrade --apply                             # update the files you never changed
php caligrafer.php upgrade --force application/views/default/index.pug   # replace one file you changed
```
After you update the framework of a project (`git pull`, or a new release copied over `framework/`), `upgrade` compares your `application/` with the framework's templates. Files you never changed are updated (with a backup in `backups/caligrafy-upgrade-<time>/`); files you changed are kept, and the new version is written next to them as `<file>.caligrafy-new` for you to merge; your routes are never replaced. It also reports client apps in `public/` that differ from their template, without changing them. Learn more in [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading).

### `generatekeys`, `generateapikey`
```bash
php caligrafer.php generatekeys       # a new APP_KEY and API_KEY pair
php caligrafer.php generateapikey     # another legacy API key for the current APP_KEY
```
`initialize` already writes the keys to `.env`: use `generatekeys` for another environment (production) or to replace a leaked `APP_KEY` (it signs everyone out). Legacy API keys are all equivalent and cannot be revoked one by one: for programs and agents, prefer [`token`](#token).

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
