# 1. Getting Started

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - Under Docker, `.env` overrides docker-compose environment (Dotenv `createUnsafeMutable`): set `DB_HOST=mysql` and `APP_ROOT=` in `.env`. Ports 8080/8077/3306 may clash with other projects (gotchas G11/G12).
> - `caligrafer initialize` keeps the keys of a signed project but still deletes `.git` (G25). `app` = Vue as a library, `create` = Vite SPA.

This page is the reference for installing Caligrafy Quill: what it needs, the recommended setup with Docker, the other setups (MAMP, XAMPP, LAMP, your own Apache), the address of a project (`APP_ROOT`), the server rules that protect your files, and the concepts and folders of a project. **New to Caligrafy?** Follow the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart): with Git and Docker, one command sets everything up.

## Requirements

| | Needed |
| --- | --- |
| With Docker (recommended) | Git and Docker Desktop. PHP 8.5, Apache, MySQL 8.4 and phpMyAdmin 5.2.3 run in containers |
| On your own server | PHP 8.3 or higher (8.5 recommended) with `curl`, `json`, `mbstring`, `openssl` and `pdo_mysql` (and `gd`, `sodium`, `intl`, `zip`, `bcmath` for the features that use them); Apache with `mod_rewrite`, `mod_headers` and `mod_mime`, and `AllowOverride FileInfo` (or `All`); MySQL 8.4 (or another database PDO supports); Composer |
| For the Vue single page application template | Node.js 20.19+ or 22.12+ on your computer |

`php caligrafer.php doctor` checks the PHP version and extensions of a project.

## With Claude

Open the project folder in Claude Code and ask, or use the skill:
```text
/caligrafy-quill:server start
```
The `server` skill checks that Docker is running, that the ports 8080, 8077 and 3306 are free (it proposes other ports with a `docker-compose.override.yml` when one is taken) and that `.env` has the Docker settings (`DB_HOST=mysql`, an empty `APP_ROOT`), then starts the server and checks that the site answers. For a project from an idea, `/caligrafy-quill:idea` sets it up for you ([Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude)).

**Check:** `http://localhost:8080` opens, and `docker compose exec dev-box php caligrafer.php doctor` ends with `0 FAIL`.

## Installation

### With Docker (recommended)

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
.bin/caligrafer quickstart --docker
```
Answer `y` when it asks to delete the framework's `.git`. Add `--example` for the Tasks example. The command installs the PHP libraries, builds and starts the Docker server, initializes the project, points `.env` at the Docker database, creates the database and the framework tables, and adds the `/capabilities` and `/mcp` routes. The full output and the troubleshooting are in the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart#start).

The Docker server (`docker-compose.yaml`):

| Service | Address | What it is |
| --- | --- | --- |
| `dev-box` | http://localhost:8080 | PHP 8.5 with Apache, your project folder mounted in it: a change to a file is live |
| `phpmyadmin` | http://localhost:8077 | phpMyAdmin, user `root`, password `root` |
| `mysql` | `127.0.0.1:3306` from your computer, `mysql:3306` from `dev-box` | MySQL 8.4, user `root`, password `root` |
| `scheduler` | | runs the scheduled jobs every minute (started by `server start`) |

Every day: `.bin/caligrafer server start` and `.bin/caligrafer server stop`. Run Caligrafer in the container: `docker compose exec dev-box php caligrafer.php <command>`. The first start builds the PHP image (it compiles the PHP extensions: from under a minute to about 5 minutes); later starts take seconds; `.bin/caligrafer server rebuild` builds it again after a change to `docker/Dockerfile`. Stop with `stop`, not `docker compose down`: the `mysql` container has no volume, and removing it removes the data.

**Without PHP on your computer**, `.bin/caligrafer server start` on a project that is not set up runs `.bin/server`: the `prod-box` container, with the code copied into the image and rebuilt when files change, at `http://localhost:8080/caligrafy-quill`. Use `quickstart --docker` instead: it serves your folder as it is.

### With Composer, on MAMP, XAMPP or LAMP

> #### (video) [Installing Caligrafy](https://youtu.be/21Lazd38FfQ)
>
> A video of the installation on a local Apache, MySQL and PHP server.

In the web folder of your running local server (MAMP: the folder of its Apache settings, `htdocs` by default; XAMPP: `htdocs`; LAMP: `/var/www/html`):
```bash
composer create-project caligrafy/caligrafy-quill myapp
cd myapp
.bin/caligrafer quickstart --local
```
`composer create-project` installs the latest release (see the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md); `-s dev` takes the unreleased `master` branch) and prints the next command. `quickstart --local` gives the project its address (`APP_ROOT`, from its place in the web folder; MAMP's own settings are read), asks for the MySQL settings with the server's defaults, and creates the database, the framework tables and the routes. On Windows: `php caligrafer.php quickstart`. Details: [Quickstart, with Composer](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart#composer).

**Check:** open the address it prints, for example `http://localhost:8888/myapp` with a default MAMP.

### By hand, on your own Apache

1. Get the code in the web folder of your server (or in a folder a virtual host points to):
    ```bash
    git clone https://github.com/caligrafy/caligrafy-quill.git myapp
    cd myapp
    composer install
    ```
2. Initialize the project:
    ```bash
    php caligrafer.php initialize --yes
    ```
    ```text
     .git deleted: git init starts your project's own history
     Application initialized successfully
    ```
    It creates `.env` from `framework/settings/.env.example`, writes a new `APP_KEY` and `API_KEY`, sets `APP_ROOT=myapp` (the folder name), copies the templates to `application/` and makes `public/uploads` writable. For a site at the root of its address (a virtual host), run `php caligrafer.php initialize off --yes` instead: `APP_ROOT` is then empty.
3. Point `.env` at your database:
    ```text
    DB_ACTIVATE=true
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=myapp
    DB_USERNAME=root
    DB_PASSWORD=<your password>
    ```
    Use `127.0.0.1`, not `localhost`: with `localhost`, PDO uses the MySQL socket and ignores `DB_PORT`.
4. Create the database, the framework tables and the routes:
    ```bash
    php caligrafer.php quickstart --url http://localhost/myapp
    ```
    It finds the database settings already set, so it asks nothing, and ends with `Ready. Next:`.
5. Check:
    ```bash
    php caligrafer.php doctor
    ```
    and open `http://localhost/myapp` (your server's port and path).

`php caligrafer.php generatekeys` prints a new key pair when you need one for another environment.

### The project's address: `APP_ROOT`

`APP_ROOT` in `.env` is the path of the project under the address of the server, without slashes:

| The project is served at | `APP_ROOT` |
| --- | --- |
| `http://localhost:8080/` (Docker), or a virtual host | empty |
| `http://localhost/myapp/` | `myapp` |
| `http://localhost/clients/myapp/` | `clients/myapp` |

`initialize` sets it to the folder name (`initialize off`: empty), and `quickstart` sets it from the project's place in the web folder of MAMP, XAMPP or LAMP. Rename the folder, or move it, and change `APP_ROOT` to match.

### Virtual hosts

A virtual host is optional. It must allow the project's `.htaccess` files:
```text
<VirtualHost *:80>
    ServerName bookshelf.test
    DocumentRoot "/path/to/myapp"
    <Directory "/path/to/myapp">
       Options FollowSymLinks
       AllowOverride All
       Require all granted
    </Directory>
</VirtualHost>
```
Add `127.0.0.1 bookshelf.test` to `/etc/hosts`, restart Apache, and set `APP_ROOT=` (empty).

## Protecting your server files

Only `index.php` and the `public` folder are meant to be reached from a browser. The `.htaccess` file at the root of the project answers `403` for existing:
+ dotfiles and dot folders, such as `.env`, `.env.php`, `.git` and `.claude` (`.well-known` stays reachable);
+ the folders `application`, `framework`, `vendor`, `tests`, `qa`, `docker`, `claude-plugin`, `archives`, `backups` (2.19.1) and `node_modules`;
+ the files `caligrafer.php`, `composer.json`, `composer.lock`, `docker-compose.yaml` (or `.yml`), `package.json` and `package-lock.json`, and any `.sql`, `.log`, `.bak`, `.ini`, `.sh` or `.env` file.

Every other address goes to `index.php?uri=<path>`, the front controller. A second `.htaccess` in `public/uploads` stops any script that ends up there from running or being downloaded. The rules are case-insensitive (macOS and Windows file systems).

Keep these files when you deploy. They need `mod_rewrite`, `mod_headers` (CORS, cookies, the service worker scope) and `mod_mime` (the web app manifest type), and `AllowOverride FileInfo` (or `All`): without `mod_headers`, Apache answers `500`. Check a server with `curl -I http://<your-host>/.env`: the answer must be `403` or `404`.

**nginx** ignores `.htaccess`. Use equivalent rules, for example:
```text
location ~ /\.(?!well-known) { deny all; }
location ~ ^/(application|framework|vendor|tests|qa|docker|claude-plugin|archives|node_modules|backups)/ { deny all; }
location ~ ^/(caligrafer\.php|composer\.(json|lock)|docker-compose\.ya?ml|package(-lock)?\.json)$ { deny all; }
location ~* \.(sql|log|bak|ini|sh|env)$ { deny all; }
location = /public/sw.js { add_header Service-Worker-Allowed "/"; }
types { application/manifest+json webmanifest; }
location ~* ^/public/uploads/.*\.(php\d*|phtml|phar|cgi|pl|py|sh)$ { deny all; }
location / { try_files $uri $uri/ /index.php?uri=$uri&$args; }
```

PHP's built-in server (`php -S`) reads no `.htaccess`: it neither routes the pages nor protects these files. Use Docker or Apache.

## Caligrafer, the command line tool

`php caligrafer.php <command>` (with Docker: `docker compose exec dev-box php caligrafer.php <command>`). **Every command is on one page: [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer).** The ones you use most:

| Command | What it does |
| --- | --- |
| `quickstart [--example]` | sets a project up in one step ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#quickstart)) |
| `service <Folder/Name> --scope <scope>` | creates a capability ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#service)) |
| `call <capability> key=value` | calls a capability and prints its answer ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#call)) |
| `sql <file.sql>` | runs a SQL script, such as a model's `create_db.sql` ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#sql)) |
| `migrate [status \| make <name>] [--env <file>]` | changes tables that hold data ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#migrate)) |
| `client` | writes the JavaScript client of the capabilities ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#client)) |
| `token create <name> --scopes ...` | a token for a program or an AI agent ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#token)) |
| `test [make <Name>]` | runs your app's tests, or writes one ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#test)) |
| `doctor` | checks the project and its services ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#doctor)) |
| `update` | moves the project to a newer release ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#update)) |

+ The version of a project: `php caligrafer.php version` prints `Caligrafy Quill 2.21.4` ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#version)).
+ Capabilities, tokens, MCP and the audit log: `service`, `capabilities`, `call`, `client`, `platform install`, `token`, `mcp`, `audit` ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#service)).
+ Something does not work: `php caligrafer.php doctor` checks the project, the database and every service of `.env`, with a fix for each problem ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#doctor)).

## How a project is built

Caligrafy is a Model-View-Controller framework with one more layer on top, so that a feature is **built once and experienced everywhere**:

| Layer | What it holds | Where |
| --- | --- | --- |
| **Capabilities** | the features, written once: inputs and their rules, who may use them, the work, the result | `application/services/` (a `Caligrafy\Service` with `#[Capability]`) |
| **Experiences** | every way to reach a capability | Pug pages (`Route::page`, `Route::form`, `Route::action`), Vue apps (the JavaScript client), REST (`Route::capability`, `Route::capabilities`), AI agents (`Route::mcp`) |
| **Models** | one class per table, read and written by capabilities only | `application/models/` |
| **Views** | the Pug pages | `application/views/` |
| **Controllers** | older code and the examples a new project ships; a new request never needs one | `application/controllers/` |

### Build once, experience everywhere

A capability is called the same way by every experience, so its rules never drift between the web pages, the apps, the API and the agents. [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) explains it in 5 minutes; [Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP) has every option.

### The `Caligrafy` namespace

The framework's classes are in the namespace `Caligrafy`. Your classes are global (no namespace) unless you give them one, and `use` the framework classes they extend:
```php
<?php

use Caligrafy\Model;

class Book extends Model
{
    public $id;
    public $title;
}
```
Two classes of `application/` cannot have the same fully qualified name: name them distinctly (`Book`, `ListBooks`, `BookFormController`) or give them a namespace.

## Fundamentals

The Bookshelf (books, authors, tags, loans) is the example of this wiki.

+ **Capabilities**: one feature of the application (list the books, add a book) written once as a class: it declares its inputs with [validation rules](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation), the scope and permission level it needs, does the work and returns data. Learn more [here](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service).
+ **Routes**: the lines of `application/config/routing/web.php` that connect an address to what answers it. `Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books']])` shows a page with the result of a capability; `Route::form('/books', 'create_book')` takes a form; `Route::capability('GET', '/api/books', 'list_books')` is a REST address. Learn more in [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing) and [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes).
+ **Experiences**: web pages, Vue apps and single page applications on any device, REST clients and AI agents (MCP) all call the same capabilities, each shaped for its audience.
+ **Identity**: people sign in (`Route::auth`, passkeys); programs and AI agents use personal access tokens with scopes, which can expire and be revoked. Every change and every refusal goes to the audit log.
+ **Models**: one class per table, with one public property per column. The [query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) of a model (`Book::where(...)`, `Book::insert(...)`) reads the table named after the class (`Book` -> `books`) unless the model declares `protected static $tableName`. Learn more in [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models).
+ **Queries**: lists, filters, paging and related rows with every value bound: `Database::table('books')`, `Book::where('author_id', 3)->with('author')->paginate(20, $page)`.
+ **Relationships**: a book's author, an author's books, a book's tags through a pivot table: a capability loads them with `with()` (`Book::with('author')`), which reads them from the names of the tables and columns, with no code in the model. Learn more in [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships).
+ **Naming conventions**:
    1. Every table's primary key is `id`.
    2. A foreign key is `<model name in lower case>_id` (`author_id` for the model `Author`), of the same type as the `id` it references (`INT UNSIGNED` for an `INT UNSIGNED` id), and every foreign key constraint has a name unique in the database (`fk_books_author_id`: `fk_<table>_<column>`).
    3. A many-to-many pivot table is `<table1>_<table2>` (`books_tags`, with `book_id` and `tag_id`), written in `create_db.sql` like any table; `with(['tags' => ['pivot' => true]])` on `books` reads `books_tags`, or `tags_books` when only that one exists.
+ **Tables**: a new table is a `create_db.sql` next to its model (`php caligrafer.php sql ...`); a change to a table that holds data is a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).
+ **Views**: Pug pages rendered on the server ([Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)), or Vue apps in `public/` that call the capabilities through the JavaScript client.
+ **Validation**: the rules of [GUMP](https://github.com/Wixel/GUMP), in each capability's `$rules`.

## File structure

| Folder | What goes there |
| --- | --- |
| `application/` | your application: `services/` (capabilities), `models/`, `views/`, `controllers/`, `config/` (`routing/web.php`, `config.php`, and `schedule.php` or `container.php` when you add them), and `migrations/` once you write one. Each class is loaded the first time it is used, from a class map Caligrafy keeps up to date: no `require` |
| `framework/` | the framework. `core/` holds the framework classes (`Service`, `Capability`, `Context`, `Route`, `Model`...), `interfaces/` the REST, MCP and JavaScript client channels, `settings/` the templates `initialize` copies. Change it only to change the framework |
| `public/` | what the browser loads: scripts, stylesheets, images, client apps, and `public/uploads/` for uploaded files |
| `tests/` | your app's tests, run with `php caligrafer.php test` ([Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)). Never loaded by a page; the production image leaves it out |
| `vendor/` | the libraries Composer installs |

## Related pages

+ [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart)
+ [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works)
+ [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)

> [Next Section: Learn about Routes](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing)
