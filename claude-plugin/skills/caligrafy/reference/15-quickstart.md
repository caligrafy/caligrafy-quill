# Quickstart

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

In five minutes you have a Caligrafy application running, with one feature (Tasks) that you use from a web page, from another application over REST, and from an AI agent such as Claude or Cursor. One command does the setup.

## What you need

+ [Git](https://git-scm.com/)
+ [Docker Desktop](https://www.docker.com/products/docker-desktop/), running

That is all: PHP 8.5, Apache, MySQL 8.4 and Composer run in Docker. (No Docker? See [without Docker](#nodocker).)

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

The Tasks feature is five small files in `application/services/Tasks/` (one per capability: list, get, create, update, delete). The page, the REST API and the agent all call them, so the rules are the same everywhere: try adding a task without a title from the page, from `curl` and from the agent, and you get the same message each time. [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) explains the idea in a few minutes.

> **The example is open to everyone** (`public: true` on its capabilities), so it works without signing in. Before you put an application online, remove `public: true` and let people sign in or use tokens: [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability#secure) shows how.

## Next

+ **Build your own feature:** [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) adds a Books catalog with its page, its API and its agent tools.
+ **Keep the example or remove it:** delete `application/services/Tasks`, `application/models/Tasks`, `application/views/Tasks`, `public/js/tasks.js` and the `TASKS EXAMPLE` routes of `application/config/routing/web.php`.
+ **Check your setup at any time:** `docker compose exec dev-box php caligrafer.php doctor`.
+ **Every day:** `docker compose up -d dev-box` starts the server, `docker compose stop` stops it. phpMyAdmin is at http://localhost:8077 (user `root`, password `root`).

## If something does not work

| You see | Do this |
| --- | --- |
| `Docker is not running` | Start Docker Desktop, wait until it says it is running, run the command again |
| `port is already allocated` (8080, 8077 or 3306) | Another server uses the port: stop it, or change the left number of `ports` in `docker-compose.yaml` (for example `"8081:80"`) and use that port |
| The PHP image build fails or stops | A slow or filtered network: run the command again; behind a company proxy see the [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started) Docker notes |
| `Database: could not reach mysql` | MySQL takes a few seconds on its first start: run `.bin/caligrafer quickstart --example` again |
| The page is empty or shows an error | `docker compose exec dev-box php caligrafer.php doctor` lists what is wrong, with a fix for each line |

## Without Docker

With your own Apache, PHP 8.3+ and MySQL (MAMP, LAMP, XAMPP): put the project in your web folder, then
```bash
composer install
php caligrafer.php quickstart --example
```
The first run creates `application/` and `.env` and stops at the database (`SKIP Database`). Set `DB_ACTIVATE=true` and the `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` of your MySQL in `.env`, then run the same command again: it creates the database and finishes. The site is at `http://localhost/<your folder>/tasks`. More options are in [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started).

>[Next: How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works)
