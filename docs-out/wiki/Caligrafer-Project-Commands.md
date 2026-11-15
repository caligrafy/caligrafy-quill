The **project commands** of [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) set a project
up and run its development server: `quickstart`, `initialize`, `created`, `server`, the shell launcher `.bin/caligrafer`,
and `version`. This page says what each one does, step by step, and what it prints.

<br/>

## With Claude

`/caligrafy-quill:idea` and the [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude)
run `quickstart` for you; `/caligrafy-quill:server` starts, stops or checks the Docker server, and looks for a port
another project already holds before it starts it.

```text
/caligrafy-quill:server start
```

**Check** before you say OK: the address it prints opens the home page, and `php caligrafer.php doctor` has no `FAIL`.

<br/>

## By hand

In a fresh copy of the framework (`composer create-project caligrafy/caligrafy-quill bookshelf`, or a clone):

```bash
cd bookshelf
.bin/caligrafer quickstart --example
```

With Docker running, it installs the PHP libraries, starts the `dev-box` container, and runs
`php caligrafer.php quickstart --example` in it. Then open `http://localhost:8080`. The whole walk-through, with its
output, is the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart).

<br/>

## <a name="quickstart"></a>`quickstart`

```text
quickstart [--example] [--yes]: sets up a fresh project in one step (database, framework tables, /capabilities and /mcp); --example adds the Tasks example. With Docker: .bin/caligrafer quickstart
```

| Option | Effect |
| --- | --- |
| `--example` | adds the Tasks example: its capabilities, a model, a page, the `tasks` table, its routes and `public/js/capabilities.js` |
| `--yes` | allows the `initialize` step to delete the framework's `.git` |
| `--url <address>` | the address of the project on a local server, when it is not in a known web folder |
| `--no-db` | skips the database steps |
| `--db-defaults` | without a terminal, uses the local server's MySQL defaults instead of skipping the database |
| `--docker`, `--local` | `.bin/caligrafer` only: which server runs it (see [below](#bin)) |

Each step runs only when it is needed, so `quickstart` can be run again: it only does what is missing and never
overwrites a file.

1. `initialize`, when `application/` does not exist yet (refused without `--yes` when a `.git` folder is there).
2. The database settings: with Docker, `DB_ACTIVATE=true`, `DB_HOST=mysql` and `DB_DATABASE=caligrafy` in `.env`; on a
   local server (MAMP, XAMPP, LAMP), `APP_ROOT` from the project's place in the web folder and the MySQL settings,
   asked with the server's defaults.
3. The database itself (`CREATE DATABASE IF NOT EXISTS`) and the framework tables (`platform install`). A local MySQL
   that does not answer sets `DB_ACTIVATE=false` again.
4. `Route::capabilities('/capabilities')`, `Route::mcp('/mcp')` and `Route::notifications()` above `Route::run()` in
   `application/config/routing/web.php`.
5. With `--example`, the Tasks example.

The class behind it is [`Caligrafy\Quickstart`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#quickstart).

<br/>

## <a name="initialize"></a>`initialize`

```text
initialize: Initializes and signs your project
```

```bash
php caligrafer.php initialize            # APP_ROOT=<folder name>: the site is at http://localhost/<folder>/
php caligrafer.php initialize off        # any word after initialize: APP_ROOT empty, the site is at the root (Docker)
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

What it does, in order:

1. **The keys.** It writes `APP_KEY` and `API_KEY` at the top of `.env`. A project that already has both keeps them
   (` Existing APP_KEY and API_KEY kept`): new keys would revoke the API keys, sign everyone out and make encrypted
   data unreadable.
2. **`APP_ROOT`.** Always rewritten: the folder's name, or empty when a word follows `initialize` (`.bin/caligrafer`
   adds `off` when no local Apache runs, because Docker serves the project at the root).
3. **The Vue SPA template.** It writes `framework/librairies/app/.env` with `VITE_API_ROUTE=` and no key: that build
   is public.
4. **`application/`.** When it does not exist, it copies `framework/settings/application` there and writes
   `application/.caligrafy-manifest.json`, which [`upgrade`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Maintenance-Commands#upgrade)
   reads to tell your files from untouched copies. A failed copy prints
   ` Initialization failed: framework/settings/application could not be copied to ./application.` and exits with `1`.
5. **Uploads.** `public/uploads` is made writable (`chmod -R 777`).
6. **The contributor skill.** `.claude/skills/caligrafy-contributor` is for the framework's own repository: it is
   removed.
7. **The Claude Code plugin.** The project's plugin source gets its own name (`caligrafy-<folder>-<4 hex>`), so that
   Claude Code does not give every project the first one's plugin.
8. **`.git`.** Deleted only with `--yes`, or a `y` typed at the question in a terminal:
   ` This folder has a .git folder (the framework's history, or your project's). Delete it so that your project starts its own history? [y/N]`.
   Without a terminal (a script, Claude) and without `--yes`, it is kept.

> **Ask before `initialize --yes` in a git repository.** It deletes `.git`, and the history with it, unless it was
> pushed somewhere.

<br/>

## <a name="created"></a>`created`

Composer runs it at the end of `composer create-project caligrafy/caligrafy-quill <folder>`; you never run it yourself.
It prints the folder, the one next command, and what it found on this computer:

```text
 Caligrafy Quill 2.25.0 is in /Users/me/www/bookshelf. One more command sets it up:

   cd bookshelf
   .bin/caligrafer quickstart --example
```

On Windows the command is `php caligrafer.php quickstart --example`. It then says the address when the folder is in the
web folder of a MAMP, XAMPP or LAMP server, that a running local server cannot see a folder outside its web folder, and
whether Docker is running (`quickstart uses it`, or `add --docker to use it instead`).

<br/>

## <a name="server"></a>`server`

```text
server <start | stop | rebuild>: Starts a Caligrafy local LAMP server with phpmyadmin (requires PHP, Composer and Docker); rebuild updates the server image
```

| Command | Runs |
| --- | --- |
| `server start` | `docker compose up -d dev-box scheduler` |
| `server stop` | `docker compose stop` |
| `server rebuild` | `docker compose build --pull dev-box`, then `docker compose up -d dev-box scheduler` |

```text
Caligrafy Server successfully started.

 Hostname: http://localhost:8080
 phpmyadmin: http://localhost:8077
 mysql: localhost:3306 (from your computer; mysql:3306 from the containers)
 mysql username: root 
 mysql password: root 
```

+ **The containers** (`docker-compose.yaml`): `dev-box` (the PHP image of `docker/Dockerfile`, your folder mounted at
  `/var/www/html`), `scheduler` (the same image, running `php caligrafer.php schedule work`), `mysql` (MySQL 8.4) and
  `phpmyadmin` (5.2.3).
+ **The ports** it prints are the ones Docker published, so a `docker-compose.override.yml` that moves them (another
  project holds 8080) is followed; when the containers are not running, it reads the compose files and their override
  (2.24.0).
+ **The first start** builds the image and takes minutes; later starts take seconds. Run `rebuild` after a change to
  `docker/Dockerfile`, or to update the system packages.
+ **A local Apache.** Through `.bin/caligrafer`, `server` gets a last word: `local` when a local web server (MAMP,
  XAMPP, LAMP) runs, and then it only says that this server serves the project (turn it off to use Docker). Run by hand,
  `php caligrafer.php server start` always uses Docker (2.24.0).
+ **When it fails**: ` The Docker server could not start: is Docker Desktop running? (or a port is taken: docker-compose.override.yml moves them)`,
  exit code `1`.

<br/>

## <a name="bin"></a>`.bin/caligrafer`

The shell launcher, for macOS and Linux:

```bash
.bin/caligrafer quickstart [--docker | --local] [quickstart options]
.bin/caligrafer <command> [options]
```

+ **`quickstart`** chooses the server: `--docker` or `--local`, else the local server when Apache (`httpd` or
  `apache2`) runs and PHP is installed, else Docker when it runs; with neither it says what to start. In a folder with a
  `.git` and no `application/` it asks before going on (the `initialize` step deletes `.git`) and adds `--yes` when you
  say `y`. It installs the PHP libraries when `vendor/autoload.php` is missing (your Composer, else the `composer:2`
  image with Docker), starts `dev-box` and runs `php caligrafer.php quickstart` in it, or runs it with your PHP. Then it
  registers the project's Claude Code plugin when the `claude` command is installed.
+ **Any other command**, with PHP and Composer on the computer: it runs `./caligrafer.php <command>` with your
  arguments as typed. `initialize` and `server` get the word that says whether a local server runs. A folder without
  `application/` or `.env` only accepts `initialize`, `version`, `doctor` and `update`, and otherwise prints
  ` This folder is not a Caligrafy project yet (no application/ or .env): start it with .bin/caligrafer quickstart`.
+ **Without PHP or Composer**, it runs `.bin/server start|stop`: the `prod-box` container, with the code copied into
  the image.

<br/>

## <a name="version"></a>`version`

```bash
php caligrafer.php version        # also --version and -v
```

```text
Caligrafy Quill 2.25.0
```

It is handled before anything else is loaded: it works before `composer install` and never creates `.env`. In code, the
version is [`Caligrafy\Framework::VERSION`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#framework).

<br/>

## Common mistakes

+ **`.git` gone after setting up** (G25, G95): `initialize` deletes it only with `--yes` or a typed `y` since 2.9.2;
  before, `.bin/caligrafer` ran `initialize` on its way to other commands and deleted it without asking.
+ **The ports clash with another project** (G12): `server start` fails. A `docker-compose.override.yml` remaps the host
  side (`"8081:80"`, `"8078:80"`, `"3307:3306"`), and `server` then prints the new ports.
+ **`.env` replaces the Docker settings** (G11): `.env` values win over the `environment` of `docker-compose.yaml`. Under
  Docker keep `DB_HOST=mysql` and `APP_ROOT=` (empty) in `.env`.
+ **`server start` does nothing** through `.bin/caligrafer` while a local Apache runs (G25): it serves the project
  itself. Stop it, or run `php caligrafer.php server start`.

<br/>

## Related pages

+ [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) · [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude)
+ [Installation and configuration](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)
+ [API reference: framework and tooling](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
