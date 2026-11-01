# Quickstart

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Build Once, Experience Everywhere](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/build-once-banner.png)

In five minutes you have a Caligrafy application running, with one feature (Tasks) that you use from a web page, from another application over REST, and from an AI agent such as Claude or Cursor. One command does the setup, and three more [put it online](#online). To start from your own idea instead, with Claude building it, see the [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude).

## What you need

+ [Git](https://git-scm.com/)
+ [Docker Desktop](https://www.docker.com/products/docker-desktop/), running

That is all: PHP 8.5, Apache, MySQL 8.4 and Composer run in Docker. (Composer, MAMP, XAMPP or LAMP instead? See [with Composer](#composer).)

## Start

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
.bin/caligrafer quickstart --example
```
When it asks to delete the `.git` folder, answer `y`: the folder was the history of the framework, and your project starts its own.

The first start builds the PHP image (from under a minute to a few minutes, depending on your network). Then `quickstart` prints what it did:
```
Caligrafy quickstart (Docker server)
  OK    Project: application/ and .env created, keys generated
  OK    .env: the database of the Docker server (caligrafy)
  OK    Database: caligrafy ready on mysql
  OK    Framework tables: personal access tokens and the audit log
  OK    Routes: /capabilities (JS client) and /mcp (AI agents)
  OK    Example: 9 file(s) added (application/services/Tasks, models/Tasks, views/Tasks, public/js/tasks.js)
  OK    Example table: tasks
  OK    Example client: public/js/capabilities.js generated
```
Every step can be run again: `quickstart` only does what is missing, and never overwrites your files.

## Use the same feature three ways

**1. On the web.** Open **http://localhost:8080/tasks**. Add a task, tick it done, rename it, delete it.

**2. From another application, over REST.**
```bash
curl -H "Accept: application/json" http://localhost:8080/api/tasks
```
```json
{"data":[{"id":1,"title":"Try the quickstart","notes":"","done":1,"created_at":"2026-10-31 16:16:45","modified_at":"2026-10-31 16:16:46"}]}
```

**3. From an AI agent.** Agents use a **personal access token**. Create one (it is shown only once):
```bash
docker compose exec dev-box php caligrafer.php token create "my agent" --scopes "*"
```
Give it to **Claude Code**:
```bash
claude mcp add --transport http myapp http://localhost:8080/mcp --header "Authorization: Bearer <the token>"
```
or to **Cursor**, in `.cursor/mcp.json`:
```json
{ "mcpServers": { "myapp": { "url": "http://localhost:8080/mcp", "headers": { "Authorization": "Bearer <the token>" } } } }
```
Ask the agent "add a task to buy milk". It calls the `create_task` tool, and the task appears on the open web page within a few seconds, without reloading.

## What you just used

The Tasks feature is five small files in `application/services/Tasks/` (one per capability: list, get, create, update, delete). The page (`Route::page('/tasks', 'Tasks/index')`), the REST API and the agent all call them, so the rules are the same everywhere: try adding a task without a title from the page, from `curl` and from the agent, and you get the same message each time. [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) explains the idea in a few minutes.

> **The example is open to everyone** (`public: true` on its capabilities), so it works without signing in. Before you put an application online, remove `public: true` and let people sign in or use tokens: [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability#secure) shows how.

## Put it online

The same project runs online with [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud), at `https://<name>.caligrafy.com`, built from its GitHub repository. The first 30 minutes are free.

1. **Your code on GitHub.** `quickstart` removed the framework's history, so start yours: `git init`, `git add -A`, `git commit -m "My app"`, then create a repository on GitHub and push to it (a private repository works too: Caligrafy Cloud gives you a read-only deploy key to add on GitHub).
2. **An account and a token.** Sign up at [deploy.caligrafy.com](https://deploy.caligrafy.com/signup), open the confirmation email, then create a token on the dashboard (*Command line and AI agents*).
3. **Online:**
```bash
docker compose exec dev-box php caligrafer.php cloud login                 # paste the token (it is not shown)
docker compose exec dev-box php caligrafer.php cloud create myapp --repo https://github.com/<you>/myapp
docker compose exec dev-box php caligrafer.php cloud deploy myapp          # follows the build: a few minutes
```
Open `https://myapp.caligrafy.com/tasks`: the tasks table was created when the app started. The dashboard does the same without the command line, and `/caligrafy-quill:deploy` does it with Claude.

> **Online, the Tasks example is open to everyone** too: anyone with the address can add and delete tasks. That is fine for a 30-minute try; for longer, remove `public: true` first (see the note above). When the time ends the app is paused, and `php caligrafer.php cloud extend myapp week` (2 credits) brings it back.

## Next

+ **Build your own feature:** [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) adds a Books catalog with its page, its API and its agent tools.
+ **Keep the example or remove it:** delete `application/services/Tasks`, `application/models/Tasks`, `application/views/Tasks`, `public/js/tasks.js` and the `TASKS EXAMPLE` routes of `application/config/routing/web.php`.
+ **Check your setup at any time:** `docker compose exec dev-box php caligrafer.php doctor`. Every command of Caligrafer, the command line tool, is on the [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) page.
+ **Every day:** `docker compose up -d dev-box` starts the server, `docker compose stop` stops it. phpMyAdmin is at http://localhost:8077 (user `root`, password `root`).

## If something does not work

| You see | Do this |
| --- | --- |
| `Docker is not running` | Start Docker Desktop, wait until it says it is running, run the command again |
| `port is already allocated` (8080, 8077 or 3306) | Another server uses the port: stop it, or change the left number of `ports` in `docker-compose.yaml` (for example `"8081:80"`) and use that port |
| The PHP image build fails or stops | A slow or filtered network: run the command again; behind a company proxy see the [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started) Docker notes |
| `Database: could not reach mysql` | MySQL takes a few seconds on its first start: run `.bin/caligrafer quickstart --example` again |
| The page is empty or shows an error | `docker compose exec dev-box php caligrafer.php doctor` lists what is wrong, with a fix for each line |

## With Composer: MAMP, XAMPP, LAMP or Docker

If you have PHP 8.3+ and [Composer](https://getcomposer.org) on your computer:
```bash
composer create-project caligrafy/caligrafy-quill myapp
cd myapp
.bin/caligrafer quickstart --example
```
`composer create-project` downloads the latest release and its libraries, then tells you where the project will be served and the command to run next. `quickstart` then picks the server, like `.bin/caligrafer server` does:

| What runs on your computer | What quickstart does |
| --- | --- |
| **MAMP, XAMPP, or Apache and MySQL (LAMP)** | uses them. Create the project **in their web folder** (MAMP: the folder of its Apache settings, `htdocs` by default; XAMPP: `htdocs`; LAMP: `/var/www/html`). It gives the project its address (`APP_ROOT`): with MAMP it reads MAMP's own settings (its web folder, its Apache and MySQL ports). Then it asks for your MySQL settings, with the server's defaults (MAMP: `root` / `root`; XAMPP and LAMP: `root` and no password), creates the database, the framework tables and the example. If the settings do not work, it switches the database off again so that the pages still open, and says what to fix in `.env` |
| **Docker Desktop** | the same as the 5-minute quickstart above: the Docker server at `http://localhost:8080` |
| **Both** | the local server; add `--docker` to use Docker |
| **Neither** | it says what to start |

Options: `--local` or `--docker` chooses the server; `--url http://localhost/<folder>` gives the address when the project is in another web folder; `--no-db` skips the database. Run it in a terminal: without one to confirm the MySQL settings, it skips the database rather than guess (`--db-defaults` takes the server's defaults), because another MySQL may answer on the same port (a Docker project's, for example). On **Windows** (XAMPP, MAMP for Windows), run `php caligrafer.php quickstart --example`: it uses the local server. Then open the address quickstart prints, for example `http://localhost:8888/myapp/tasks` with a default MAMP.

>[Next: How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works)
