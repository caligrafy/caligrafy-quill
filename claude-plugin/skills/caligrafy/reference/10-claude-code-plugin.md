# 10. Claude Code Plugin

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill ships with its own **[Claude Code](https://code.claude.com) plugin**, `caligrafy-quill`, in the [`claude-plugin/`](https://github.com/caligrafy/caligrafy-quill/tree/master/claude-plugin) folder of every project. When you open a project in Claude Code, Claude knows the framework from its source: capabilities, the declarative routes, the query builder, models, Pug views, authentication, payments, Vue apps, bots, face detection, machine learning and the AI integrations. It builds the Caligrafy way, **build once, experience everywhere**: a feature becomes a capability, and every experience (pages, Vue apps, REST, AI agents) calls it. With `/caligrafy-quill:idea` it goes from your idea to an app online: **Describe it. Approve it. Ship it.**

Current version: **1.34.0**, for Caligrafy Quill v2.22.0.

## What the plugin gives Claude

| Component | Name | What it does |
| --- | --- | --- |
| Skill (automatic) | `caligrafy` | The framework knowledge: every page of this wiki, an API reference and 148 verified gotchas (G1-G148) written from the framework source, UI guidelines for every new page, and recipes for AI chat apps and face login. Claude Code loads it whenever it works in a Caligrafy project |
| Command | `/caligrafy-quill:idea` | Your idea turned into a complete app online, with one confirmation: the plan, the project, every feature as a capability, the checks, then GitHub and Caligrafy Cloud |
| Command | `/caligrafy-quill:new-capability` | One capability, served to the channels you need (REST, the JavaScript client, pages and forms, MCP), with a token and tests |
| Command | `/caligrafy-quill:new-resource` | A table, its model, its five CRUD capabilities, their REST routes, pages and forms |
| Command | `/caligrafy-quill:new-app` | A client app in `public/<name>` (Vue as a library, a Vite SPA, a Watson bot, face detection, machine learning) and its routes |
| Command | `/caligrafy-quill:server` | Starts, stops or checks the Docker development server |
| Command | `/caligrafy-quill:db` | Runs SQL scripts and migrations, lists tables, runs queries |
| Command | `/caligrafy-quill:design` | Restyles every page after a design style (`DESIGN_STYLE`), with one stylesheet |
| Command | `/caligrafy-quill:ship` | Puts the project on GitHub and Caligrafy Cloud by itself |
| Command | `/caligrafy-quill:deploy` | Checks the project is ready for production, then deploys it to Caligrafy Cloud or prepares it for your own server |
| Command | `/caligrafy-quill:marketplace` | Packages an app on Caligrafy Cloud for the marketplace of caligrafy.com and submits it |
| Command | `/caligrafy-quill:update` | Updates the project to the latest release and merges every `.caligrafy-new` file |
| Agent | `caligrafy-reviewer` | Reviews your Caligrafy code for framework bugs and security issues; it never edits files |
| Hook | `php -l` | After Claude writes or edits a `.php` file, checks its syntax and sends an error back to Claude (skipped when PHP is not installed) |

Claude also runs `php caligrafer.php doctor` first when something does not work. The [plugin changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/claude-plugin/README.md#changelog) lists what each version added, and each [release note](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.19.0) names the plugin version that follows it.

## Installing the plugin

### With a project (recommended)

Every project has a `.claude/settings.json` that registers the plugin's source (a "marketplace": the project folder, through `.claude-plugin/marketplace.json`) and enables the plugin. `initialize` (and so `quickstart`) gives that source the project's own name, `caligrafy-<folder>-<4 letters>`, and `.bin/caligrafer quickstart` registers it with Claude Code when the `claude` command is installed. Open the project and accept the **"trust this folder"** prompt:

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
.bin/caligrafer quickstart --docker
claude
```

**Check:** type `/caligrafy-quill:` in Claude Code: the commands of the table above are listed. `php caligrafer.php doctor` has a line `Claude Code plugin: caligrafy-quill@caligrafy-<name>`.

### From GitHub, in any folder

In Claude Code:
```text
/plugin marketplace add caligrafy/caligrafy-quill
/plugin install caligrafy-quill@caligrafy
```
or in a terminal:
```bash
claude plugin marketplace add caligrafy/caligrafy-quill
claude plugin install caligrafy-quill@caligrafy
```
The source from GitHub is named `caligrafy` and the plugin `caligrafy-quill`, hence `caligrafy-quill@caligrafy`.

### When a command such as `/caligrafy-quill:design` is unknown

Claude Code keeps one plugin source per name for the whole computer. Projects made before 2.10.1 all named their source `caligrafy`: the first project opened won, the others got its (older) plugin, and none got one once that folder was deleted. In the project, on your computer:
```bash
php caligrafer.php plugin
```
It gives the source the project's own name, registers it (`claude plugin marketplace add`), installs the plugin for the project (`claude plugin install ... --scope project`), and prints `Plugin source: caligrafy-<name>, plugin caligrafy-quill@caligrafy-<name>`. Restart `claude` in the folder. `doctor` warns when a project still shares the name or its plugin is read from another folder.

### When a new skill is missing after an update

Claude Code runs a copy of the plugin made when it was installed, not the project's `claude-plugin/` folder. `php caligrafer.php update` and `php caligrafer.php plugin` refresh that copy when the project's plugin is newer, and `doctor` warns while it is older (`Claude Code runs caligrafy-quill X, this project has Y`). When the update ran in Docker, where `claude` is not installed, run `php caligrafer.php plugin` on your computer in the project folder. Then restart `claude`.

## Commands

### `/caligrafy-quill:idea <your idea>`
The [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude). Claude:
1. **Describe it**: turns the idea into a plan (a name, 1 to 3 tables, 4 to 12 capabilities, who may do what, one page per main table, sample data; jobs, alerts and background work only when the idea needs them) and waits for your OK.
2. **Approve it**: sets the project up (`.bin/caligrafer quickstart`, after checking Docker and the ports), writes each table's `create_db.sql` and model, each capability with `php caligrafer.php service`, sign-in with `Route::auth` (a username by default; an email when the plan says so, [kept in the `username` column](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#emailsignin)), the pages with `Route::page` and `Route::form`, the REST routes, the JavaScript client, a card where signed-in people create a token for their AI agents, and tests; then checks every capability (`call`), REST, the page at desktop and phone width, MCP, `test`, `doctor` and a review by `caligrafy-reviewer`, and shows you the app locally.
3. **Ship it**: follows `/caligrafy-quill:ship`.

Your OK at the plan covers everything after it, the framework's `.git` included. You only approve sign-ins in the browser pages Claude opens, and say yes before credits are spent. "Stop before putting it online" makes it wait after the checks.
```text
/caligrafy-quill:idea A reading list: I add books I want to read, mark them as read, and rate them from 1 to 5.
```

### `/caligrafy-quill:new-capability <capability_name> [scope] [read-only|destructive]`
Scaffolds the class with `php caligrafer.php service <Folder>/<Name> --scope <scope>`, writes its description for agents, its `$rules`, `$filters` and `$descriptions`, and a `handle()` that reads with the query builder and refuses with `ServiceError`. It adds the channels you ask for in `web.php`: `Route::capability`, `Route::capabilities` and the JavaScript client, `Route::page`, `Route::form`, `Route::download`, `Route::webhook`, `Route::action` (a web-only capability, for what a page or a form cannot say), `Route::mcp`. It creates a token when an agent needs one, checks every channel (`capabilities`, `call`, REST with and without the scope, MCP `tools/list` and `tools/call`, `audit`), writes a test, and asks `caligrafy-reviewer` to review. Learn more in [Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).
```text
/caligrafy-quill:new-capability create_book books:write
```

### `/caligrafy-quill:new-resource <Entity> [field:type ...]`
Capabilities first, every request declared:
+ `application/models/<Entity>/create_db.sql`: the table, `CREATE TABLE IF NOT EXISTS`, an `id` primary key, `<model>_id` foreign keys with unique constraint names;
+ `application/models/<Entity>/<Entity>.php`: the model, one public property per column, its relationships;
+ `list_`, `get_`, `create_`, `update_` and `delete_` capabilities in `application/services/<Entities>/`, the only code that touches the model;
+ their REST routes (`Route::capability`), the pages (`Route::page` with their data from the capabilities) and the forms (`Route::form`) in `web.php`, and the views when you want HTML pages.

Then it offers to run the SQL (`/caligrafy-quill:db`), calls each capability, writes a test and asks for a review. Field types: `string`, `text`, `int`, `decimal`, `bool`, `date`, `datetime`, `json`; `name:fk` is a foreign key to the model `Name`.
```text
/caligrafy-quill:new-resource Book title:string author:string publisher:fk
```

### `/caligrafy-quill:new-app <app | create | bot | facedetect | ml> <name>`
Runs the Caligrafer scaffold into `public/<name>`, adds its page route, prefers capabilities and the JavaScript client for its data, and opens it to check the browser console.
+ `app`: Vue 3 as a library
+ `create`: a Vite Vue single page application (Node.js 20.19+ or 22.12+ on your computer)
+ `bot`: an IBM Watson bot
+ `facedetect`: face detection and recognition (`@vladmandic/face-api` 1.7.15)
+ `ml`: ML Core (ml5 and p5)

The names `app`, `bot`, `__bots__`, `css`, `facedetect`, `fonts`, `images`, `js`, `ml`, `resources` and `uploads` are reserved. The `caligrafy` skill carries two complete recipes Claude follows: **AI chat apps** (OpenAI, Groq, or an open model on OpenRouter or Ollama) and **face login** (face prints checked on the server, with a lockout; a photo can pass, so use it as a convenience or a second factor).
```text
/caligrafy-quill:new-app facedetect faceauth
```

### `/caligrafy-quill:server <start | stop | status>`
Checks that Docker runs, that the ports (8080, 8077, 3306) are free, and that `.env` has the Docker settings (`DB_HOST=mysql`, an empty `APP_ROOT`), then starts or stops the server and checks the site answers. When a port is taken it gives the project other ports with a `docker-compose.override.yml`; behind a company proxy that inspects TLS ("self-signed certificate in certificate chain"), it gives the container the company's root certificate. See [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#using-docker).
```text
/caligrafy-quill:server start
```

### `/caligrafy-quill:db <script.sql | tables | query "SQL">`
Runs SQL on the project's MySQL: in the Docker `mysql` container, or a local MySQL with the `DB_*` settings of `.env` (never printing the password).
+ `<script.sql>`: a script such as `application/models/Book/create_db.sql` (the one `new-resource` writes) or `application/models/Bookshelf/create_db.sql`. Scripts with `DROP`, `TRUNCATE` or `DELETE` are shown first and need your OK. A change to a table that holds data becomes a migration (`php caligrafer.php migrate make <name>`, then `migrate`).
+ `tables`: the tables and their row counts.
+ `query "SQL"`: read-only queries; Claude asks before a statement that writes.
```text
/caligrafy-quill:db application/models/Book/create_db.sql
```

### `/caligrafy-quill:design [style | off]`
Reads a whole `DESIGN.md` (84 styles from VoltAgent/awesome-design-md in `framework/design/vendor/`, `interstellar` in `framework/design/local/`, or yours in `application/design/<name>/`), writes one stylesheet, `public/css/designs/<style>.css`, loaded after the Caligrafy stylesheets, sets `DESIGN_STYLE` in `.env`, and checks the sign-in pages and your pages in a browser at desktop and phone width. Caligrafy's files and your views are not edited. `off` brings the Caligrafy look back. Without a style it asks which one. See [Design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles).
```text
/caligrafy-quill:design linear.app
```

### `/caligrafy-quill:ship [app-name] [--public]`
Checks the project is ready (no `.env` in git, the scripts safe to run again, the tests), commits, then creates the GitHub repository (private unless `--public`) and pushes it: with your SSH key when GitHub knows it, else over HTTPS with the GitHub CLI, else after installing the GitHub CLI and a code you approve on GitHub's page. It signs in to Caligrafy Cloud with a code you approve on deploy.caligrafy.com (sign up there the first time), creates the app, adds its read-only deploy key to the repository, deploys, and checks the address answers. It never asks for or prints a password, token or key, and asks before spending credits. Run it again after a change: it pushes and deploys again.
```text
/caligrafy-quill:ship myapp
```

### `/caligrafy-quill:deploy [app-name] [--own-server]`
First a read-only check, point by point: Caligrafy Quill 2.4.0 or later, `Dockerfile` and `composer.lock`, no secrets in git, `create_db.sql` scripts safe to run again, no changed migration, the tests, the payment keys, everything committed and pushed. Then it deploys to [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud) with `php caligrafer.php cloud` (`cloud login` approved in your browser), or, with `--own-server`, prepares the project for [Coolify or Docker](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#ownserver). For a deploy without any hand-off, use `/caligrafy-quill:ship`.
```text
/caligrafy-quill:deploy myapp
```

### `/caligrafy-quill:marketplace [app-name]`
For an app on Caligrafy Cloud that you offered on its page. Claude reads the whole code, runs the app on your computer as a separate Docker project with invented data, and takes screenshots of its key features (5 at most, all different, 1280x800). It writes the title, the description, the prompt that recreates the app with Caligrafy and an estimated price window from a short market study into one private file, `.caligrafy/marketplace.json`, then sends it with `php caligrafer.php cloud submit` and redeploys the app. It often takes 15 to 30 minutes. Caligrafy reviews the listing, sets the final price and publishes it; you earn 25% of every sale. See [the marketplace](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#marketplace).
```text
/caligrafy-quill:marketplace myapp
```

### `/caligrafy-quill:update [version]`
Asks you to commit uncommitted work first, shows what [`php caligrafer.php update`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#update) will change, applies it, then merges every `.caligrafy-new` file into your version: your changes kept, the release's added, and a note where both rewrote the same lines. It ends with `version`, `doctor` and `upgrade --local`. A project before 2.9.0 gets the command first.
```text
/caligrafy-quill:update
```

## The `caligrafy-reviewer` agent

It reads the files you name (or your latest changes under `application/`, `tests/`, `public/<app>/` and the SQL scripts), with the framework source and the gotchas as ground truth, and reports findings by severity, each with `file:line`, the failure, the gotcha and a fix. It checks, among others: SQL built from input, CSRF and request header mistakes, route problems, unsafe uploads, outdated templates and libraries, foreign key and pivot schemas, exposed secrets, model code outside capabilities, and since 2.19 a new controller where a declarative route fits, `where` strings the query builder should write, and N+1 loops. It only reads (tools: Read, Grep, Glob, Bash). Ask in plain words:
```text
Review the Books capabilities and web.php with the caligrafy-reviewer agent
```

## Try it

A first session in a project set up with the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart):
```text
/caligrafy-quill:server status
/caligrafy-quill:new-resource Author name:string bio:text
/caligrafy-quill:new-resource Book title:string published_year:int summary:text author:fk
/caligrafy-quill:db application/models/Author/create_db.sql
/caligrafy-quill:db application/models/Book/create_db.sql
Turn on sign-in with Route::auth, and let only signed-in people add books
Review the Books capabilities with the caligrafy-reviewer agent
```
`new-resource` writes one folder per entity (`application/models/Book/`, `application/services/Books/`, `application/views/Books/`). The by-hand pages and the [course](https://github.com/caligrafy/caligrafy-quill-course) keep the same Bookshelf in `application/models/Bookshelf/`, `application/services/Bookshelf/` and `application/views/Bookshelf/`: both work, the folders are only for you.

**Check** what Claude wrote before you say OK: the capabilities in `php caligrafer.php capabilities` with the scopes you expect, no `public: true` on a capability that changes data unless you want it, `Route::form` for each form and `Route::action` for what a page or a form cannot say, no new controller, and the page in your browser.

## How the plugin's knowledge is maintained

For contributors to the framework:
+ The numbered files of `skills/caligrafy/reference/` and `wiki-sidebar.md` mirror the pages of this wiki, with a link to the source page at the top of each. Refresh them with `python3 claude-plugin/scripts/sync-wiki.py <wiki clone>`; `--check` exits with `1` when a mirror is stale.
+ `api-reference.md` and `gotchas.md` are written by hand from the framework source. When they and the wiki disagree, the source-verified files win.
+ After a change to `framework/`: update them, run the [QA suite](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy#qa), bump `version` in `claude-plugin/.claude-plugin/plugin.json` and the "Current version" line of this page, and follow the [release process](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy#release).
+ Load the plugin from a folder with `claude --plugin-dir ./claude-plugin`; validate it with `claude plugin validate ./claude-plugin --strict`.

> **Safety notes**
> + `php caligrafer.php initialize --yes` deletes the `.git` folder (without `--yes` it asks in a terminal and keeps it otherwise). Claude asks before running it in a git repository.
> + Claude never prints or commits the secrets of your `.env`, and approvals for GitHub and Caligrafy Cloud happen in your browser.

## Related pages

+ [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude)
+ [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
+ [Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy)

**This is the end of the documentation and the beginning of your journey!**
