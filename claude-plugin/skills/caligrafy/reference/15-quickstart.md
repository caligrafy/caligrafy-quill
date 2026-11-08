# Quickstart

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

The Quickstart creates a Caligrafy project on your computer and shows one feature, a list of tasks, used three ways: from a web page, from another program over REST, and from an AI agent such as Claude or Cursor. It takes about 5 minutes. Every other page of this wiki starts from the project it creates.

## With Claude

To build your own idea instead of the Tasks example, with Claude Code doing the work, follow the [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude):

```text
/caligrafy-quill:idea A reading list: I add books with their author, mark them as read and rate them from 1 to 5.
```

Claude sets the project up with the same command as step 2 below (without the example), then builds, checks and ships the app.

## By hand

### What you need

+ [Git](https://git-scm.com/)
+ [Docker Desktop](https://www.docker.com/products/docker-desktop/), running
+ The ports `8080` (the site), `8077` (phpMyAdmin) and `3306` (MySQL) free on your computer. If one is taken, see [Troubleshooting](#trouble) first.

PHP 8.5, Apache, MySQL 8.4 and phpMyAdmin run in Docker: you do not install them. Without Docker, see [Other ways](#composer).

### 1. Get the framework

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
```

`myapp` is the folder of your project. Choose any name.

### 2. Set the project up

```bash
.bin/caligrafer quickstart --docker --example
```

It asks first:
```text
quickstart initializes the project, which deletes the .git folder of the framework (your project starts its own history).
Continue? [y/N]
```
Answer `y`. The `.git` folder is the history of the framework; your project starts its own in [Put it online](#online).

The command installs the PHP libraries (with your Composer, or Composer in Docker), builds and starts the Docker server (the first build takes from under a minute to a few minutes), then sets the project up inside it. It ends with:
```text
Caligrafy quickstart (Docker server)
  OK    Project: application/ and .env created, keys generated
  OK    .env: the database of the Docker server (caligrafy)
  OK    Database: caligrafy ready on mysql
  OK    Framework tables: tokens, audit log, job runs, jobs, cache, migrations
  OK    Routes: /capabilities (JS client), /mcp (AI agents), /notify (notification emails)
  OK    Example: 9 file(s) added (application/services/Tasks, models/Tasks, views/Tasks, public/js/tasks.js)
  OK    Example table: tasks
  OK    Example client: public/js/capabilities.js generated

Ready. Next:
  - open http://localhost:8080/tasks: the same tasks over REST (http://localhost:8080/api/tasks) and MCP (http://localhost:8080/mcp)
  - write a capability: php caligrafer.php service Books/CreateBook --scope books:write
  - let an AI agent use it: php caligrafer.php token create "my agent" --scopes "*"
    claude mcp add --transport http myapp http://localhost:8080/mcp --header "Authorization: Bearer <token>"
  - check everything: php caligrafer.php doctor
```

| Line | What it did |
| --- | --- |
| Project | ran `php caligrafer.php initialize`: copied the templates to `application/`, created `.env` with a new `APP_KEY` and `API_KEY`, deleted `.git` |
| .env | set `DB_ACTIVATE=true`, `DB_HOST=mysql` and `DB_DATABASE=caligrafy` |
| Database, Framework tables | created the database and the tables of personal access tokens, the audit log, jobs, the cache and migrations (`php caligrafer.php platform install`) |
| Routes | added `Route::capabilities('/capabilities')`, `Route::mcp('/mcp')` and `Route::notifications()` to `application/config/routing/web.php` |
| Example | added the Tasks example (`--example`): five capabilities, a model, a page, the `tasks` table, its routes and the JavaScript client |

Leave out `--example` for an empty project. Every step can be run again: `quickstart` only does what is missing and never overwrites your files.

When Claude Code is installed on your computer, the command also registers the project's Claude Code plugin and prints `Claude Code plugin: caligrafy-quill@caligrafy-<name>, read from this folder`.

### 3. Use the feature on a web page

Open **http://localhost:8080/tasks**. Add a task, tick it done, rename it, delete it. Submit an empty task: the page shows `The Title field is required`.

### 4. Use it from another program, over REST

```bash
curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" \
     -d '{"title": "Try the quickstart"}' http://localhost:8080/api/tasks
```
```json
{"data":{"id":1,"title":"Try the quickstart","notes":"","done":0,"created_at":"2026-11-07 23:14:07","modified_at":"2026-11-07 23:14:07"}}
```
```bash
curl -H "Accept: application/json" http://localhost:8080/api/tasks
```
```json
{"data":[{"id":1,"title":"Try the quickstart","notes":"","done":0,"created_at":"2026-11-07 23:14:07","modified_at":"2026-11-07 23:14:07"}]}
```
A task without a title gets `422` and the same message as the page:
```bash
curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{}' http://localhost:8080/api/tasks
```
```json
{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"title":"The Title field is required"}}}
```
Always send `Accept: application/json`: without it, a `POST` is treated as a form and refused with `[ERROR 3000] Security Error - Cross Site Forgery` (403).

### 5. Use it from an AI agent

Agents sign in with a **personal access token**. Create one that may read and change tasks:
```bash
docker compose exec dev-box php caligrafer.php token create "my agent" --scopes tasks:read,tasks:write
```
```text
 Token #1 "my agent" created with tasks:read tasks:write

 cq_1_...

 It is shown only now: store it as a secret (Authorization: Bearer <token>).
```
Copy the `cq_...` value. Give it to **Claude Code**:
```bash
claude mcp add --transport http myapp http://localhost:8080/mcp --header "Authorization: Bearer cq_1_..."
```
or to **Cursor**, in `.cursor/mcp.json`:
```json
{ "mcpServers": { "myapp": { "url": "http://localhost:8080/mcp", "headers": { "Authorization": "Bearer cq_1_..." } } } }
```
Ask the agent "add a task to buy milk". It calls the `create_task` tool. The open `/tasks` page shows the new task within 3 seconds, without a reload.

To check the endpoint without an agent:
```bash
curl -H "Authorization: Bearer cq_1_..." -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp
```
The answer lists the tools this token may use: `create_task`, `delete_task`, `get_task`, `list_tasks`, `register_user`, `update_task` (`register_user` is the public sign-up of the project).

### 6. Check the project

```bash
docker compose exec dev-box php caligrafer.php doctor
```
The last line is:
```text
 15 OK, 0 WARN, 0 FAIL, 6 SKIP
```
The `SKIP` lines are the services you have not configured yet (OpenAI, Groq, Stripe, Coinbase, Watson, mail).

### Check

+ `http://localhost:8080/tasks` opens, and a task added there is listed by `curl http://localhost:8080/api/tasks`.
+ An empty title gets `The Title field is required` on the page, over REST and from the agent.
+ `doctor` ends with `0 FAIL`.

## What you just used

The Tasks feature is written once, as five **capabilities** (one class per action) in `application/services/Tasks/`: `list_tasks`, `get_task`, `create_task`, `update_task` and `delete_task`. The page, the REST API and the agent all call them, so the rules are the same everywhere. These lines of `application/config/routing/web.php` serve them:

```php
    // BUILD ONCE: every capability, for the JS client and AI agents
    Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps
    Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)
    Route::notifications();                 // the pages notification emails link to (confirm, unsubscribe)

    // TASKS EXAMPLE: one set of capabilities (application/services/Tasks) served to every channel
    Route::page('/tasks', 'Tasks/index');   // the web page (public/js/tasks.js)
    Route::capability('GET', '/api/tasks', 'list_tasks');                // REST
    Route::capability('GET', '/api/tasks/{id}', 'get_task');
    Route::capability('POST', '/api/tasks', 'create_task');
    Route::capability('PUT', '/api/tasks/{id}', 'update_task');
    Route::capability('DELETE', '/api/tasks/{id}', 'delete_task');
```

| File | What it is |
| --- | --- |
| `application/services/Tasks/*.php` | the five capabilities |
| `application/models/Tasks/Task.php`, `create_db.sql` | the model of the `tasks` table, and the script that created it |
| `application/views/Tasks/index.pug` | the page |
| `public/js/tasks.js` | the page's script: it calls the capabilities through `public/js/capabilities.js`, the generated JavaScript client |

[How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) explains the idea in a few minutes. [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) builds a feature of your own.

> **The example is open to everyone.** Its capabilities are `public: true`: anyone who reaches the site can add and delete tasks, without signing in. Before you put an application online, remove `public: true` from the capabilities that change data and turn on sign-in: [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability#secure) shows how.

## Every day

| To | Run (in the project folder) |
| --- | --- |
| start the server | `.bin/caligrafer server start` (or `docker compose up -d dev-box`) |
| stop it | `.bin/caligrafer server stop` (or `docker compose stop`) |
| run a Caligrafer command | `docker compose exec dev-box php caligrafer.php <command>` ([every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)) |
| look at the database | phpMyAdmin at http://localhost:8077, user `root`, password `root` |

`server start` prints:
```text
Caligrafy Server successfully started.

 Hostname: http://localhost:8080 
 phpmyadmin: http://localhost:8077/ 
 mysql username: root 
 mysql password: root 
```
Stop the server with `stop`, never `docker compose down`: the MySQL container keeps its data only until it is removed.

## Put it online

The same project runs online with [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud) at `https://<name>.caligrafy.com`, built from its GitHub repository. Every new app is free for its first 30 minutes online.

1. **Your code on GitHub.** `quickstart` removed the framework's history, so start yours, then create a repository on GitHub (private works too) and push to it:
    ```bash
    git init -b main
    git add -A
    git commit -m "My app"
    git remote add origin https://github.com/<you>/myapp.git
    git push -u origin main
    ```
2. **Sign in to Caligrafy Cloud.** Create an account at [deploy.caligrafy.com](https://deploy.caligrafy.com/signup) and confirm your email. Then:
    ```bash
    docker compose exec dev-box php caligrafer.php cloud login
    ```
    It prints a code and an address: open the address, check the code and approve it. In Docker it does not open the browser for you.
3. **Create the app and deploy it:**
    ```bash
    docker compose exec dev-box php caligrafer.php cloud create myapp --repo https://github.com/<you>/myapp
    docker compose exec dev-box php caligrafer.php cloud deploy myapp
    ```
    For a private repository, `cloud create` prints a read-only deploy key (also saved in `.caligrafy/myapp-deploy-key.pub`): add it to the repository on GitHub (*Settings > Deploy keys*) before `cloud deploy`. `cloud deploy` follows the build, which takes a few minutes, and ends with the address.

Open `https://myapp.caligrafy.com/tasks`: the production image ran `php caligrafer.php deploy`, which created the tables, when it started. `/caligrafy-quill:ship` in Claude Code does all three steps for you.

> **Online, the Tasks example is open to everyone too.** That is fine for a 30-minute try; for longer, remove `public: true` first. When the free time ends the app is paused; `php caligrafer.php cloud extend myapp week` (2 credits) brings it back.

## Next

+ **Build your own feature:** [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) adds a Bookshelf: its table, its capabilities, a page with a form, a REST API, agent tools, sign-in and a test.
+ **Remove the example:** delete `application/services/Tasks`, `application/models/Tasks`, `application/views/Tasks` and `public/js/tasks.js`, the `TASKS EXAMPLE` lines of `application/config/routing/web.php`, then run `docker compose exec dev-box php caligrafer.php client` again.
+ **Every command:** the [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) page.

## Troubleshooting

| You see | Do this |
| --- | --- |
| `Docker is not running: start Docker Desktop, then run .bin/caligrafer quickstart again.` | Start Docker Desktop, wait until it says it is running, run the command again |
| `port is already allocated` (8080, 8077 or 3306) | Another server uses the port. Stop it, or give this project other ports with a `docker-compose.override.yml` next to `docker-compose.yaml` (below), and use those ports in every address. `quickstart` still prints `8080` |
| The PHP image build fails or stops (`Killed`) | A slow or filtered network, or too little memory for Docker: run the command again, or give Docker more memory |
| `FAIL  Database: could not reach mysql` | MySQL takes a few seconds on its first start: run `.bin/caligrafer quickstart --docker --example` again |
| `STOP  initialize deletes the .git folder of the framework` | You answered no: run the command again and answer `y` |
| The page is empty or shows an error | `docker compose exec dev-box php caligrafer.php doctor` lists what is wrong, with a fix for each line |

**Other ports.** The site, phpMyAdmin and MySQL on 8081, 8078 and 3307 (Docker Compose 2.24 or later reads `!override`):
```yaml
# docker-compose.override.yml
services:
  dev-box:
    ports: !override
      - "8081:80"
  phpmyadmin:
    ports: !override
      - "8078:80"
  mysql:
    ports: !override
      - "3307:3306"
```

## Other ways

### With Composer, on MAMP, XAMPP or LAMP

Use this when you already run a local Apache and MySQL (MAMP, XAMPP, or Apache and MySQL on Linux) and have PHP 8.3+ and [Composer](https://getcomposer.org). Create the project **in the web folder** of that server (MAMP: the folder of its Apache settings, `htdocs` by default; XAMPP: `htdocs`; LAMP: `/var/www/html`):
```bash
composer create-project caligrafy/caligrafy-quill myapp
cd myapp
.bin/caligrafer quickstart --local --example
```
`composer create-project` downloads the latest release and its libraries, then prints where the project will be served and the next command. `quickstart --local`:

1. initializes the project (there is no `.git` to delete in a release);
2. gives the project its address (`APP_ROOT`, its path in the web folder). With MAMP it reads MAMP's own settings: its web folder, its Apache port and its MySQL port;
3. asks for the MySQL settings, with the server's defaults (MAMP: `127.0.0.1`, port `8889`, `root` / `root`; XAMPP: `127.0.0.1`, `3306`, `root` and no password; LAMP: `localhost`, `3306`, `root` and no password) and a database named after the folder;
4. creates the database, the framework tables, the routes and the example, like the Docker quickstart.

Then open the address it prints, for example `http://localhost:8888/myapp/tasks` with a default MAMP.

+ The project is elsewhere: add `--url http://localhost/<folder>` to give its address.
+ No terminal to answer the questions (a script): it skips the database. `--db-defaults` takes the server's defaults; `--no-db` skips the database on purpose.
+ The MySQL settings do not work: it sets `DB_ACTIVATE=false` again, so that the pages still open, and says what to fix in `.env`.
+ On **Windows** (XAMPP, MAMP for Windows): `php caligrafer.php quickstart --example`.
+ Without `--local` or `--docker`, `.bin/caligrafer quickstart` uses the local server when Apache (`httpd` or `apache2`) is running and PHP is installed, else Docker.

### Your own PHP and MySQL, settings first

When you know the MySQL settings, write them before `quickstart` and nothing is asked:
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
composer install
cp framework/settings/.env.example .env
```
In `.env`, set `DB_ACTIVATE=true`, `DB_HOST` (`127.0.0.1`, not `localhost`, to use `DB_PORT`), `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`. Then:
```bash
php caligrafer.php quickstart --example --yes --url http://localhost/myapp
```
`--yes` deletes the framework's `.git` without asking; `--url` is the address your Apache serves the folder at. The output is the one of step 2, with `OK    Local server: your server, http://localhost/myapp (APP_ROOT=myapp)` and `OK    Database: <DB_DATABASE> ready on 127.0.0.1` in place of the Docker lines. Apache must allow the project's `.htaccess` (`AllowOverride All`) with `mod_rewrite` and `mod_headers`: see [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#protecting-your-server-files).

## Related pages

+ [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude)
+ [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works)
+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability)
+ [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started): every way to install, the configuration and the server rules
+ [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)

>[Next: How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works)
