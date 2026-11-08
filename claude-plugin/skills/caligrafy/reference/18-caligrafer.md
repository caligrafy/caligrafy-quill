# Caligrafer: every command

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Caligrafer** is the command line tool of Caligrafy: `caligrafer.php` at the root of every project, and the shell launcher `.bin/caligrafer`. It sets a project up, creates capabilities and client apps, runs SQL and migrations, manages the tokens of programs and AI agents, runs jobs and tests, checks the project, updates it, and puts it online. This page lists every command, with the usage text the command prints.

## With Claude

The plugin's skills run Caligrafer for you: `/caligrafy-quill:server` (the Docker server), `/caligrafy-quill:db` (`sql`, `migrate`), `/caligrafy-quill:new-capability` (`service`, `client`, `token`, `call`), `/caligrafy-quill:update` (`update`, `upgrade`), `/caligrafy-quill:ship` and `/caligrafy-quill:deploy` (`cloud`). Ask in plain words, for example "list my capabilities" or "create a token for my agent with books:read": Claude runs the command and shows the output, never a secret it does not have to.

## By hand

Open a terminal in the project folder and run, for example:
```bash
docker compose exec dev-box php caligrafer.php capabilities
```
It prints one line per capability: its name, who may call it, whether it reads, writes or destroys, and its description.

## How to run it

| Where PHP runs | Run Caligrafer with |
| --- | --- |
| **Docker** (the Quickstart) | `docker compose exec dev-box php caligrafer.php <command>`; add `-T` when the output goes to a file or a script |
| Your own PHP 8.3+ | `php caligrafer.php <command>`, in the project folder |
| Either, for setting up and the server | `.bin/caligrafer <command>`: see [`.bin/caligrafer`](#bin) |

`php caligrafer.php` with no command, or with a command it does not know, prints the list of commands. Except `version` and `doctor`, every command first creates `.env` from `framework/settings/.env.example` when it is missing. Any command reads another environment file with `--env <file>` (for example `php caligrafer.php sql application/models/Bookshelf/create_db.sql --env .env.testing`); a file that does not exist prints `Environment file not found: <file>` and exits with `1`. In the rest of this page, `php caligrafer.php` means the first two forms.

## Every command

| Command | What it does |
| --- | --- |
| [`quickstart`](#quickstart) | Sets a project up in one step (Docker, MAMP, XAMPP or LAMP); `--example` adds the Tasks example |
| [`initialize`](#initialize) | Prepares a fresh copy of the framework: `application/`, `.env`, keys |
| [`created`](#created) | Run by Composer after `composer create-project`: says the next command |
| [`server`](#server) | Starts, stops or rebuilds the Docker development server |
| [`version`](#version) | Prints the version of Caligrafy Quill |
| [`doctor`](#doctor) | Read-only health check of the project and its services |
| [`design`](#design) | Picks a design style for every page (`DESIGN_STYLE`) |
| [`service`](#service) | Creates a capability in `application/services/` |
| [`capabilities`](#capabilities) | Lists the capabilities |
| [`call`](#call) | Calls a capability and prints its JSON answer |
| [`client`](#client) | Writes the JavaScript client of the capabilities |
| [`app`, `create`, `facedetect`, `ml`, `bot`](#apps) | Creates a client app in `public/` |
| [`sql`](#sql) | Runs a SQL script on the database |
| [`migrate`](#migrate) | Runs, lists or writes migrations |
| [`platform install`](#platform) | Creates the framework tables |
| [`token`](#token) | Creates, lists and revokes personal access tokens |
| [`mcp`](#mcp) | An MCP server over stdio, for AI agents on this computer |
| [`audit`](#audit) | The latest capability calls and refusals |
| [`schedule`](#schedule) | Scheduled jobs: list, run, history |
| [`queue`](#queue) | Background jobs: work, list, retry |
| [`notify`](#notify) | Web push keys, and a test notification |
| [`test`](#test) | Runs the app's tests, or writes one |
| [`cache clear`](#cache) | Empties the cache, the compiled views and the class map |
| [`env`](#env) | Compiles `.env` to `.env.php`, or removes it |
| [`update`](#update) | Moves the project to another release of Caligrafy Quill |
| [`upgrade`](#upgrade) | Updates the untouched files of `application/` to the framework's templates |
| [`plugin`](#plugin) | Connects the project's Claude Code plugin to Claude Code |
| [`deploy`](#deploy) | Prepares the database in production (the production image runs it) |
| [`cloud`](#cloud) | Puts the project online with Caligrafy Cloud |
| [`generatekeys`, `generateapikey`](#keys) | Generates application keys and legacy API keys |

## Set up a project

### `quickstart`
```text
quickstart [--example] [--yes]: sets up a fresh project in one step (database, framework tables, /capabilities and /mcp); --example adds the Tasks example. With Docker: .bin/caligrafer quickstart
```
```bash
.bin/caligrafer quickstart --docker --example        # Docker (the Quickstart)
.bin/caligrafer quickstart --local --example         # MAMP, XAMPP or LAMP
php caligrafer.php quickstart --example --yes --url http://localhost/myapp   # your own PHP (Windows too)
```
| Option | Effect |
| --- | --- |
| `--example` | adds the Tasks example: five capabilities, a model, a page, the `tasks` table, its routes, `public/js/capabilities.js` |
| `--yes` | allows `initialize` to delete the framework's `.git` (`.bin/caligrafer` asks instead) |
| `--docker`, `--local` | (`.bin/caligrafer` only) the server; without them: the local server when Apache (`httpd` or `apache2`) runs and PHP is installed, else Docker |
| `--url <address>` | the address of the project on a local server, when it is not in a known web folder |
| `--no-db` | skips the database steps |
| `--db-defaults` | without a terminal, uses the local server's MySQL defaults instead of skipping the database |

Steps, each run only when needed: `initialize` (refused without `--yes` when a `.git` exists); with Docker, `DB_ACTIVATE=true`, `DB_HOST=mysql` and `DB_DATABASE=caligrafy` in `.env`; on a local server, `APP_ROOT` from the project's place in the web folder (MAMP's settings are read) and the MySQL settings asked with the server's defaults; the database (`CREATE DATABASE IF NOT EXISTS`) and `platform install`; `Route::capabilities('/capabilities')`, `Route::mcp('/mcp')` and `Route::notifications()` above `Route::run()` in `web.php`; the example. A local MySQL that does not answer sets `DB_ACTIVATE=false` again. It never overwrites a file. The output is shown in the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart).

### `initialize`
```text
initialize: Initializes and signs your project
```
```bash
php caligrafer.php initialize            # APP_ROOT=<folder name>: the site is at http://localhost/<folder>/
php caligrafer.php initialize off        # APP_ROOT empty: the site is at the root (Docker)
php caligrafer.php initialize --yes      # also deletes .git without asking
```
```text
Preparing and signing the project for usage... 

 Contributor skill removed (.claude/skills/caligrafy-contributor: for the framework repository only)
 Claude Code plugin source: caligrafy-myapp-b6ea (enable caligrafy-quill@caligrafy-myapp-b6ea when Claude Code asks)
 .git kept (php caligrafer.php initialize --yes deletes it)
 Application initialized successfully
 APP_KEY=...
 API_KEY=...
```
It writes `APP_KEY` and `API_KEY` to `.env` (a project that has both keeps them: `Existing APP_KEY and API_KEY kept`), sets `APP_ROOT`, writes `framework/librairies/app/.env` for the Vue SPA template, copies `framework/settings/application` to `application/` when it does not exist (with `application/.caligrafy-manifest.json`, which [`upgrade`](#upgrade) reads), makes `public/uploads` writable, removes the contributor skill, and gives the project's Claude Code plugin source its own name. In a terminal it asks before deleting `.git`; without a terminal it keeps it. `quickstart` runs it for you.

### `created`
Run by Composer at the end of `composer create-project caligrafy/caligrafy-quill <folder>`. It prints the folder, the next command (`.bin/caligrafer quickstart --example`, or `php caligrafer.php quickstart --example` on Windows), the address on a running MAMP, XAMPP or LAMP, and whether Docker is running. You do not run it yourself.

### `server`
```text
server <start | stop | rebuild>: Starts a Caligrafy local LAMP server with phpmyadmin (requires PHP, Composer and Docker); rebuild updates the server image
```
```bash
.bin/caligrafer server start      # docker compose up -d dev-box scheduler
.bin/caligrafer server stop       # docker compose stop
.bin/caligrafer server rebuild    # docker compose build --pull dev-box, then up -d dev-box
```
```text
Caligrafy Server successfully started.

 Hostname: http://localhost:8080 
 phpmyadmin: http://localhost:8077/ 
 mysql username: root 
 mysql password: root 
```
The Docker server is `dev-box` (PHP 8.5 and Apache, your folder mounted in it), `mysql` (MySQL 8.4), `phpmyadmin` (5.2.3) and `scheduler` (runs [`schedule work`](#schedule)). The first start builds the PHP image; later starts take seconds; `rebuild` rebuilds it after a change to `docker/Dockerfile`. Run it through `.bin/caligrafer`: `php caligrafer.php server start` alone does nothing, and when a local Apache is running `.bin/caligrafer server start` does nothing either (it uses that server). Without `.bin/caligrafer`: `docker compose up -d dev-box` and `docker compose stop`.

### `.bin/caligrafer`
```bash
.bin/caligrafer quickstart [--docker | --local] [quickstart options]
.bin/caligrafer <command> [options]
```
+ `quickstart`: chooses the server, asks before deleting `.git`, installs the PHP libraries when `vendor/` is missing (your Composer, else the `composer:2` image), starts `dev-box` and runs `php caligrafer.php quickstart` in it (Docker), or runs it with your PHP (local). It then registers the project's Claude Code plugin when the `claude` command is installed.
+ Any other command: with PHP and Composer on the computer, it runs `./caligrafer.php <command>` with your arguments (`initialize` and `server` get `off` added when no local Apache runs). A folder without `application/` or `.env` only accepts `initialize`, `version`, `doctor` and `update`.
+ Without PHP or Composer, it runs `.bin/server start|stop`: the `prod-box` container, with the code copied into the image, at `http://localhost:8080/caligrafy-quill`.

### `version`
```bash
php caligrafer.php version        # also --version and -v
```
```text
Caligrafy Quill 2.19.5
```
It works before `composer install` and never creates `.env`. In code: `Caligrafy\Framework::VERSION`. The changes of every version are in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Build features

### `service`
```text
 Usage: php caligrafer.php service <Name> [--scope <resource:action>] [--read-only]
 Name in PascalCase, optionally in folders: Books/CreateBook
```
```bash
php caligrafer.php service Bookshelf/CreateBook --scope books:write
php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only
```
```text
 Created application/services/Bookshelf/CreateBook.php: capability 'create_book' (scope books:write)
 Fill in $rules and handle(), then serve it: a page (Route::page data), a form (Route::form), REST (Route::capability or Route::capabilities), MCP (Route::mcp)
```
The capability's name is the snake_case of the class name. Always give `--scope`: without it the capability needs no scope, and any token can call it. It refuses to overwrite a file, and needs `application/`. Fill the class as [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability#capabilities) shows.

### `capabilities`
```text
capabilities [--json | --markdown]: the capabilities of application/services/ (what REST, MCP and the JS client can call)
```
```bash
php caligrafer.php capabilities              # one line each
php caligrafer.php capabilities --markdown   # a table for your documentation
php caligrafer.php capabilities --json       # with the input schemas
```
```text
 create_book                  books:write    writes    Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id. The ISBN is optional and unique.
 get_book                     public         read      Returns one book of the catalog with its author (id, name, bio), by the id of the book.
```
The columns: the name; who may call it (`public`, the scope, or `signed in` when it has no scope); `read`, `writes` or `destroys`; the description. The framework's own capabilities (users, notifications, `queue_work`) are listed too.

### `call`
```text
 Usage: php caligrafer.php call <capability> ['{"key": "value"}' | key=value ...] [--token <token>] [--guest]
 Lists the capabilities: php caligrafer.php capabilities
```
```bash
php caligrafer.php call list_books
php caligrafer.php call get_book id=3
php caligrafer.php call create_book '{"title": "Dawn", "published_year": 1987, "author_id": 2}'
php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2 --guest          # as a visitor who is not signed in
php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2 --token cq_...   # as a token (or CALIGRAFY_TOKEN=cq_...)
```
It prints the same JSON as REST: `{"data": ...}` with exit code `0`, or `{"error": {"code": "invalid", "message": "...", "fields": {...}}}` with exit code `1`. Without `--token` or `--guest` it has full access, like any script you run (channel `cli` in the audit log).

### `client`
```text
client [<file>] [--prefix /capabilities]: writes the JS client of the capabilities (default public/js/capabilities.js)
```
```bash
php caligrafer.php client                                   # public/js/capabilities.js
php caligrafer.php client public/myspa/src/capabilities.js  # a single page application
php caligrafer.php client --prefix /api/capabilities        # Route::capabilities('/api/capabilities')
```
```text
 public/js/capabilities.js written: 21 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)
 import { createBook, CapabilityError } from './capabilities.js'
```
One function per capability (`create_book` becomes `createBook`), plus `call(name, input)`, `capabilities()`, `configure({ baseUrl, token, prefix, credentials })` and `CapabilityError` (`status`, `code`, `fields`). In a page of the application it sends the visitor's session and CSRF token. Run it again after every change to a capability. Learn more in [the JS client](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#client).

### `app`, `create`, `facedetect`, `ml`, `bot`
```text
create <project_name>: scaffolds a Vue project
app <project_name>: scaffolds a regular Client HTML/JS project
bot <bot_name>: creates a bot application
facedetect <app_name>: creates a face detection application
ml <app_name>: creates an ml powered application
```
```bash
php caligrafer.php app catalog         # Vue 3 as a library:            http://localhost:8080/apps/catalog
php caligrafer.php create myspa        # a Vite Vue single page application (Node.js 20.19+ or 22.12+)
php caligrafer.php facedetect faces    # face detection:                 http://localhost:8080/apps/faces
php caligrafer.php ml lab              # machine learning in the browser: http://localhost:8080/ml/lab
php caligrafer.php bot helper          # a Watson chatbot:               http://localhost:8080/bot/helper?botSkillId=<id>
```
```text
 Project created in the public folder.
 Type cd public/catalog to access it anytime
```
Each one copies a template into `public/<name>` (`create`: `framework/librairies/app`; the others: `framework/plugins/<type>`). The name cannot be `app`, `bot`, `__bots__`, `css`, `facedetect`, `fonts`, `images`, `js`, `ml`, `resources` or `uploads`. `create` (2.19.1) gives a single page application that calls the application with the visitor's session: no API key in the bundle, `apiHeaders()` of `src/common/app.js` adds the `X-CSRF-Token` header, hash history (`#/page`), `base: './'` in `vite.config.js`, and `dist/` is committed (the production image does not run `npm`). It also runs `npm install` when `npm` is available; the Docker `dev-box` has no Node.js, so it says the packages are not installed: run `npm install` in `public/<name>` on your computer, then `npm run dev`. Learn more: [Vue as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library), [the single page application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application), [face detection](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition), [machine learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning), [chatbots](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson).

## Data

### `sql`
```text
 Usage: php caligrafer.php sql <file.sql>
```
```bash
php caligrafer.php sql application/models/Bookshelf/create_db.sql
```
```text
 application/models/Bookshelf/create_db.sql run on caligrafy
```
Runs a SQL script on the database of `.env` (`DB_ACTIVATE=true`): `/* */` and `--` comments removed, the statements run one by one. A failure prints `<file> could not be run: <reason>` and exits with `1`. It creates the tables of a new database; a change to a table that holds data is a [migration](#migrate).

### `migrate`
```text
 Usage: php caligrafer.php migrate [status | make <name>] [--env <file>]
 Runs the migrations of application/migrations that never ran on this database
```
```bash
php caligrafer.php migrate make add_books_isbn   # writes application/migrations/<YmdHis>_add_books_isbn.sql
php caligrafer.php migrate                       # runs the ones that never ran, in order
php caligrafer.php migrate status                # ran, pending or changed
php caligrafer.php migrate --env .env.testing    # on the database of another environment file (2.19.1)
```
```text
 OK   20261107232335_add_books_isbn.sql

 1 migration(s) run on caligrafy
```
Then `Nothing to migrate: caligrafy is up to date`. `--env <file>` can come anywhere after `migrate` (`migrate status --env .env.testing`); before 2.19.1 it was read as the sub-command. Each file runs once and is recorded in `caligrafy_migrations`; a file edited after it ran shows `changed` and never runs again: write a new one. `make` needs no database. [`deploy`](#deploy) runs the pending ones online. Learn more in [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).

### `platform install`
```text
 Usage: php caligrafer.php platform install
 Creates the framework tables (tokens, audit log, job runs, notifications, jobs, cache, migrations) from framework/settings/platform.sql
```
```bash
php caligrafer.php platform install
```
```text
 Framework tables ready: caligrafy_tokens, caligrafy_audit, caligrafy_job_runs, the notifications tables, caligrafy_jobs, caligrafy_cache and caligrafy_migrations
```
Safe to run again. `quickstart` and `deploy` run it; `doctor` says when the tables are missing.

### `cache clear`
```text
 Usage: php caligrafer.php cache clear
 Empties the cache (Caligrafy\Cache, CACHE_STORE in .env), the compiled views and the class map
```
```text
 Cache (database) emptied; 2 compiled view and class map file(s) removed
```
See [Caching](https://github.com/caligrafy/caligrafy-quill/wiki/Caching).

### `env`
```text
 Usage: php caligrafer.php env <cache | clear>
```
```bash
php caligrafer.php env cache    # .env compiled to .env.php: pages read it without parsing .env (php caligrafer.php env clear removes it)
php caligrafer.php env clear    # .env.php removed: pages read .env again
```
Pages read `.env.php` instead of parsing `.env` on every request, as long as `.env` is exactly what it was compiled from (its content is compared, not its date): edit `.env` and pages read it again at once. [`deploy`](#deploy) runs `env cache` at every start in production. `.env.php` holds the same secrets as `.env` and is in `.gitignore`: never commit it. `env cache` exits with `1` when there is no `.env`.

## Programs and AI agents

### `token`
```text
 Usage:
 php caligrafer.php token create <name> --scopes <a:read,b:write | *> [--user <id>] [--expires <days>]
 php caligrafer.php token list
 php caligrafer.php token revoke <id>
```
```bash
php caligrafer.php token create "books agent" --scopes books:read,books:write
php caligrafer.php token create "Reporting job" --scopes books:read --expires 90
php caligrafer.php token create "Sam's agent" --scopes "books:*" --user 7
php caligrafer.php token list
php caligrafer.php token revoke 2
```
```text
 Token #2 "books agent" created with books:read books:write

 cq_2_...

 It is shown only now: store it as a secret (Authorization: Bearer <token>).
```
```text
 #2    active   books agent                    books:read books:write  last used 2026-11-07 23:17:49
```
+ `--scopes` (required): `resource:action` values separated by commas; `books:*` for every action on books; `*` for everything.
+ `--expires <days>`: the token stops working after that many days.
+ `--user <id>`: the token acts for that user, so a capability's `level` applies to them.

Only the token's hash is kept. `revoke` takes the number without `#` and stops the token at once. It needs the framework tables. Learn more in [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#tokens).

### `mcp`
```text
mcp [--token <token>]: MCP server over stdio for local AI agents (Claude Code, Cursor...): php caligrafer.php mcp
```
```bash
claude mcp add myapp -- php caligrafer.php mcp                                  # your own PHP
claude mcp add myapp -- docker compose exec -T dev-box php caligrafer.php mcp   # Docker
```
One JSON-RPC message per line on standard input, one answer per line on standard output. Without a token it has full access and prints `caligrafer mcp: no token, full local access (set CALIGRAFY_TOKEN to limit it)` on standard error; with `CALIGRAFY_TOKEN` (or `--token <token>`) it sees only what the token allows. For agents that reach the application over the network, use `Route::mcp('/mcp')` and a token: see [AI agents: MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp).

### `audit`
```text
audit [--limit 50] [--capability <name>]: the latest capability calls
```
```bash
php caligrafer.php audit
php caligrafer.php audit --capability create_book --limit 200
```
```text
 2026-11-07 23:17:49  rest  token  2            create_book                  ok
 2026-11-07 23:17:49  rest  guest  -            create_book                  unauthenticated  Sign in or send a personal access token
```
When, the channel (`web`, `rest`, `mcp`, `cli`), the caller (`user`, `token`, `key`, `cli`, `guest`, or the system of a job, with its id), the capability, the outcome and its detail. Every change and every refusal is recorded; successful reads and inputs are not.

## Jobs and tests

### `schedule`
```text
 Usage: php caligrafer.php schedule <list [--json] | run <job> | due | work | history [<job>] [--limit 50]>
 Jobs are declared in application/config/schedule.php
```
```bash
php caligrafer.php schedule list                # checks schedule.php and lists the jobs (--json: what Caligrafy Cloud reads)
php caligrafer.php schedule run check-prices    # one job now (--if-due: only when it is due this minute)
php caligrafer.php schedule due                 # the jobs due this minute
php caligrafer.php schedule work                # every minute until stopped (the scheduler container runs it)
php caligrafer.php schedule history             # the latest runs ([<job>] [--limit 50])
```
With no `application/config/schedule.php`: `No scheduled jobs (application/config/schedule.php declares them)`. Exit codes: `0` ok, `1` a job failed or timed out, `2` the file is invalid, `3` skipped (still running). Learn more in [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs).

### `queue`
```text
 Usage: php caligrafer.php queue <work [--queue default] [--for <seconds>] [--once] [--sleep 3] | status | list [--status failed] [--limit 50] | failed | retry <id | all>>
 Background jobs pushed with Caligrafy\Queue::push('<capability>', [...])
```
```bash
php caligrafer.php queue work --once      # what is due, then stops
php caligrafer.php queue work --for 300   # for 5 minutes; without --once or --for: until Ctrl+C
php caligrafer.php queue status
php caligrafer.php queue list --status failed
php caligrafer.php queue retry 42         # retry all: every failed job
```
```text
 Working the queue default until it is empty
 0 done, 0 to try again, 0 failed
```
`status` prints `0 pending, 0 running, 0 done, 0 failed`. Each job runs in its own process (`queue exec <id>`, internal), stopped at its timeout, and tried again when it fails. Online, a scheduled job on `queue_work` runs them. Learn more in [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs).

### `notify`
```text
 Usage: php caligrafer.php notify <keys [--subject mailto:you@example.com] | test <user id> | status <user id>>
```
```bash
php caligrafer.php notify keys --subject mailto:you@example.com   # the web push keys, once
php caligrafer.php notify test 7                                  # a test notification to user 7
php caligrafer.php notify status 7                                # user 7's channels
```
`keys` writes `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` and `VAPID_SUBJECT` to `.env` and refuses to replace them (new keys cut off every subscribed browser). Without `--subject` it uses `MAIL_FROM` or `MAIL_USERNAME` when it is an address, else `APP_URL`. Learn more in [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications).

### `test`
```text
 Usage: php caligrafer.php test make <Name>   (writes tests/<Name>Test.php)
```
```bash
php caligrafer.php test make Books   # tests/BooksTest.php written: php caligrafer.php test Books
php caligrafer.php test              # every test of tests/
php caligrafer.php test Books        # only the tests whose name matches
```
PHPUnit with `framework/testing/bootstrap.php` and `Caligrafy\Testing\TestCase`. Each test runs in a transaction that is rolled back; `DB_TEST_DATABASE` or `.env.testing` gives the tests a database of their own. With no test yet: `No tests yet: php caligrafer.php test make <Name> writes tests/<Name>Test.php`. Without PHPUnit (`composer install --no-dev`) it says so and exits with `1`. Learn more in [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App).

## Check and maintain

### `doctor`
```text
doctor [--only openai,groq] [--json] [--env <file>]: read-only health check of the project and of the services configured in .env
```
```bash
php caligrafer.php doctor
php caligrafer.php doctor --only database          # project, database, openai, groq, agent, stripe, coinbase, watson, mail
php caligrafer.php doctor --env .env.production    # another environment file
php caligrafer.php doctor --json                   # for scripts
```
```text
Caligrafy doctor (read-only): Caligrafy Quill 2.19.5

 Database
  OK    Database: connected to mysql://mysql:3306/caligrafy (server 8.4.13)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache and migrations installed
  OK    Migrations: 1 ran, none pending

 3 OK, 0 WARN, 0 FAIL, 0 SKIP
```
It checks PHP and its extensions, `vendor/`, `.env` and the keys, the legacy API key, the Claude Code plugin, `public/uploads`, the web app manifest, the design style, whether `application/` is up to date, the database, the framework tables and the migrations, and every service configured in `.env` (OpenAI, Groq and the other agent providers, Stripe, Coinbase, Watson, mail). Each line is `OK`, `WARN`, `FAIL` or `SKIP` (not configured), with `-> <fix>`. Secrets are never printed. It exits with `1` when a check fails, and an unknown `--only` name lists the valid ones. With Docker, run it in the container so that `mysql` resolves. Run it first whenever something does not work.

### `design`
```text
 Usage: php caligrafer.php design <list [--json] | show <name> | use <name> | off | status>
  list    the styles of application/design, framework/design/local and framework/design/vendor/* (the first one wins on a name clash)
  show    the path, description and stylesheet of a style
  use     selects a style: DESIGN_STYLE=<name> in .env (then /caligrafy-quill:design in Claude Code writes its stylesheet)
  off     back to Caligrafy's own look: DESIGN_STYLE= (stylesheets are kept)
  status  the selected style
```
`list` prints `85 styles (* selected, + stylesheet written)`: 84 from VoltAgent/awesome-design-md (MIT) in `framework/design/vendor/` and `interstellar` in `framework/design/local/`, plus yours in `application/design/<name>/DESIGN.md`. `status` with no style: `OK  Caligrafy's own look (DESIGN_STYLE is empty)`. Pages load `public/css/designs/<name>.css` once it exists; `/caligrafy-quill:design` writes it. Learn more in [Design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles).

### `update`
```text
update [<version>] [--check] [--yes] [--no-composer]: moves the project to another release of Caligrafy Quill (the latest by default) and applies everything: framework/, the release's other files (untouched ones replaced, changed ones kept with a .caligrafy-new), composer.json, .gitignore and new .env settings merged, then upgrade --apply; backups in backups/
```
```bash
php caligrafer.php update --check      # what it would change, nothing written
php caligrafer.php update              # to the latest release
php caligrafer.php update 2.19.0       # to one release
```
On the latest release: `Caligrafy Quill 2.19.0 is the latest: nothing to update. php caligrafer.php upgrade checks application/.` It downloads the project's release and the target from GitHub, then:

1. replaces `framework/` and `claude-plugin/` whole (keeping `framework/librairies/app/.env`);
2. compares every other file of the release (`caligrafer.php`, `.bin/`, `Dockerfile`, `docker-compose.yaml`, `index.php`, `public/css`, `public/js`...) with the project's release: a file you never changed is replaced, a missing one is added, a file you changed is kept with the new version next to it as `<file>.caligrafy-new`. Your documents (`README.md`...) are left alone; `application/`, `tests/`, `qa/`, `public/uploads/` and `composer.lock` are never touched here;
3. merges `composer.json` and runs `composer update` for the new or changed packages (`--no-composer`: prints the command), adds the new lines of `.gitignore` and the new settings of `.env` with their defaults;
4. runs `php caligrafer.php upgrade --apply` for `application/`, and refreshes Claude Code's copy of the plugin.

What it replaces is first copied to `backups/caligrafy-update-<from>-to-<to>-<time>/`. With uncommitted git changes it stops (commit first, or `--yes`). It refuses a lower version. Then merge each `.caligrafy-new` into your file, delete it, and run `doctor`; `/caligrafy-quill:update` does the merges with Claude. A project before 2.9.0 gets the command first:
```bash
curl -fsSL https://raw.githubusercontent.com/caligrafy/caligrafy-quill/master/framework/core/Updater.php -o framework/core/Updater.php
php -r "require 'framework/core/Updater.php'; exit((new Caligrafy\Updater('.'))->run());"
```

### `upgrade`
```text
upgrade [--apply] [--local] [--force <application/path>] [--keep <application/path>]: brings the project up to date (dry run by default). When a newer release of Caligrafy Quill is out, it moves the project to it like update; otherwise (or with --local) it compares application/ with the framework templates and updates the untouched files; also reports .env settings and Bootstrap 3 classes
```
```bash
php caligrafer.php upgrade                                      # dry run
php caligrafer.php upgrade --local                              # application/ against this release's templates only
php caligrafer.php upgrade --apply                              # replace the files you never changed
php caligrafer.php upgrade --force application/views/Auth/login.pug          # replace one file you changed
php caligrafer.php upgrade --apply --keep application/views/default/index.pug   # keep a template copy that is now yours
```
```text
 Caligrafy upgrade (dry run: nothing is changed, run 'php caligrafer.php upgrade --apply' to update)
 Templates: framework/settings/application (45 files) compared with application/ - manifest .caligrafy-manifest.json found

  routes      application/config/routing/web.php: your routes: never replaced
  identical  44 file(s)

 Nothing was changed.
```
Each template file is `missing`, `identical`, `outdated` (an untouched older copy: replaced by `--apply`, with a backup in `backups/caligrafy-upgrade-<time>/`), `customized` (kept, the new version written next to it as `.caligrafy-new`), `kept` (`--keep`, remembered in the manifest) or `routes` (`web.php`, never replaced). It also reports, without changing them: client apps in `public/` that differ from their template, settings of `.env.example` missing from `.env` and obsolete ones, and Bootstrap 3 classes in views and apps. Learn more in [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading).

### `plugin`
```text
plugin: gives this project's Claude Code plugin its own source name and says where Claude Code reads it
```
```bash
php caligrafer.php plugin
```
```text
 Plugin source: caligrafy-7df2, plugin caligrafy-quill@caligrafy-7df2
```
It renames the plugin source of `.claude-plugin/marketplace.json` and `.claude/settings.json` from the shared `caligrafy` to the project's own name, then, when the `claude` command is on this computer, registers the source (`claude plugin marketplace add`), installs the plugin for the project, and refreshes Claude Code's copy when the project's plugin is newer. In Docker, where there is no `claude`, it says to run it on your computer. Restart `claude` in the folder afterwards. Run it when a `/caligrafy-quill:...` command is unknown.

## Put it online

### `deploy`
```text
deploy [--wait <seconds>]: prepares the database in production: framework tables, every new or changed application/models/<folder>/create_db.sql (never one that drops or deletes data twice), then the pending migrations
```
```bash
php caligrafer.php deploy --wait 120      # wait up to 2 minutes for the database (default 60)
```
```text
 Caligrafy deploy
 caligrafy-schedule {"version":1,"jobs":[]}
  OK    Settings: .env compiled to .env.php (read without parsing on every request)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache, migrations, scripts
  OK    application/models/Auth/create_db.sql: run
  OK    application/models/Bookshelf/create_db.sql: run
  OK    application/models/Tasks/create_db.sql: run
  OK    Migrations: up to date
```
The `caligrafy-schedule` line is the list of scheduled jobs, for Caligrafy Cloud. A script that has not changed since its last run says `up to date`. The production `Dockerfile` runs it before Apache starts (a failure is printed, and the site starts anyway). It compiles `.env`, waits for the database, creates the framework tables, runs each `application/models/<folder>/create_db.sql` that is new or changed since its last run (recorded in `caligrafy_scripts`), then the pending [migrations](#migrate). A changed script that drops, deletes or truncates is not run again (run it with [`sql`](#sql) when you mean it). Without `DB_ACTIVATE=true` it skips the database. It exits with `1` when the database cannot be prepared. Learn more in [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#deploy).

### `cloud`
```text
 Caligrafy Cloud: put this project online at https://<name>.caligrafy.com (https://deploy.caligrafy.com)
  cloud login [--no-browser]            sign in: approve the code in your browser (--token cq_... or --paste: a dashboard token)
  cloud account                         your email and credits
  cloud create <name> [--repo <url>] [--branch <b>]   from this project's GitHub repository
  cloud key <name>                      the read-only deploy key of a private repository (gh repo deploy-key add)
  cloud deploy <name> [--no-wait]       build and start it (every new app: 30 free minutes from its first deploy)
  cloud apps | status <name> | logs <name> [--jobs]
  cloud jobs <name> [run <job> | history <job>]   its scheduled jobs: last and next runs, run one now, its runs
  cloud extend <name> week|month|year   2, 5 or 50 credits
  cloud credits 5|10|25|50              buy credits (1 credit = 1 USD) on Stripe
  cloud submit <name> [--no-deploy]     send the marketplace listing (.caligrafy/marketplace.json), then deploy
  cloud secret set <name> KEY=value | secret list <name> | secret delete <name> KEY
  cloud delete <name> --yes             the app, its database and files, for good
  cloud logout
```
`php caligrafer.php cloud` (or `cloud help`) prints this list. Each app is built from its GitHub repository with its `Dockerfile`, with its own MySQL database, at `https://<name>.caligrafy.com`.
+ `login` prints a code and an address; open it, approve the code, and the token is saved in `.caligrafy/cloud.json` (ignored by git). In Docker it does not open the browser itself. `CALIGRAFY_CLOUD_TOKEN` and `CALIGRAFY_CLOUD_URL` override the file.
+ `create` takes the repository from `git remote get-url origin` when `--repo` is missing. For a private repository it saves the read-only deploy key in `.caligrafy/<name>-deploy-key.pub`.
+ `deploy` follows the build (up to 15 minutes) unless `--no-wait`.
+ `secret set` adds a setting to the app's `.env`, applied at the next deploy.
+ `submit` sends the listing the `/caligrafy-quill:marketplace` skill wrote, then deploys.

Learn more in [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud).

### `generatekeys`, `generateapikey`
```text
generatekeys: generates an APP and an API pair of keys that you can put in your app's environment variable 
generateapikey: generates an API key that you can provide to any third party desiring to access your services
```
```bash
php caligrafer.php generatekeys       #  APP_KEY=...  API_KEY=...
php caligrafer.php generateapikey     #  API_KEY=... for the APP_KEY of .env
```
They print keys and write nothing. `initialize` already writes the keys: use `generatekeys` for another environment, or to replace a leaked `APP_KEY` (it signs everyone out). Legacy API keys are all the same key and cannot be revoked one by one: for programs and agents, use [`token`](#token).

## Related pages

+ [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart)
+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability)
+ [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)
+ [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
