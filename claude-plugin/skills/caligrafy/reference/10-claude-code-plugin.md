# 10. Claude Code Plugin

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill ships with its own **[Claude Code](https://code.claude.com) plugin**. When you open a Quill project in Claude Code, Claude knows the framework inside out: routes, controllers, models, relationships, validation, Pug views, the REST API, authentication, payments, Vue apps, bots, FaceDetect, machine learning and the OpenAI/Groq integrations.

The plugin lives in the [`claude-plugin/`](https://github.com/caligrafy/caligrafy-quill/tree/master/claude-plugin) folder of the Caligrafy Quill repository.

## What the plugin gives Claude

| Component | Name | What it does |
| - | - | - |
| Skill (automatic) | `caligrafy` | The complete Caligrafy documentation (every page of this wiki), a source-verified API reference and a list of verified gotchas, security notes and documentation errata. Loads automatically whenever Claude works in a Quill project |
| Command | `/caligrafy-quill:new-resource` | Creates the SQL script, Model, Controller (validated CRUD actions) and REST routes for a new entity |
| Command | `/caligrafy-quill:new-app` | Scaffolds a client app with Caligrafer (Vue as a library, Vue SPA, bot, face detection or machine learning) and wires its routes |
| Command | `/caligrafy-quill:server` | Starts, stops or checks the Docker development server after checking ports and `.env` |
| Command | `/caligrafy-quill:db` | Runs your `create_db.sql` scripts in the MySQL container (or a local MySQL) and inspects tables |
| Agent | `caligrafy-reviewer` | Reviews your Caligrafy code against the framework's real behavior |
| Hook | `php -l` | Syntax-checks every `.php` file Claude writes or edits (skipped when PHP is not installed) |

## Installing the plugin

### Automatic (recommended)
Every Quill project ships a `.claude/settings.json` that registers the plugin's marketplace and enables the plugin. Create or clone a Quill project, open it in Claude Code and accept the **"trust this folder"** prompt:

```bash
composer create-project -s dev caligrafy/caligrafy-quill my-app
# or: git clone https://github.com/caligrafy/caligrafy-quill.git my-app

cd my-app
claude
```

### Manual
From any Claude Code session:

```
/plugin marketplace add caligrafy/caligrafy-quill
/plugin install caligrafy-quill@caligrafy
```

Or from your terminal:

```bash
claude plugin marketplace add caligrafy/caligrafy-quill
claude plugin install caligrafy-quill@caligrafy
```

> The marketplace is named `caligrafy` and the plugin `caligrafy-quill`, which is why the install target is `caligrafy-quill@caligrafy`.

## Commands

### `/caligrafy-quill:new-resource <Entity> [field:type ...]`
Scaffolds a complete resource following the framework conventions (learn more about [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models) and [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers)):
+ `application/models/<Entity>/create_db.sql`: the MySQL table, with an `id` primary key, `<model>_id` foreign keys and unique constraint names
+ `application/models/<Entity>/<Entity>.php`: the `Model` with one public property per column
+ the `Controller` with filtered and validated CRUD actions
+ the REST routes in `application/config/routing/web.php`

Field types: `string`, `text`, `int`, `decimal`, `bool`, `date`, `datetime`, `json`. Use `name:fk` for a foreign key to the model `Name`.

```
/caligrafy-quill:new-resource Book title:string author:string publisher:fk
```

### `/caligrafy-quill:new-app <app | create | bot | facedetect | ml> <name>`
Scaffolds a client app in `public/<name>` using Caligrafer and wires its page and data routes (learn more about [Vue as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library), the [Vue SPA](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application), [Bots](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson), [FaceDetect](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition) and [Machine Learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning)).

+ `app`: Vue 3 as a library (default)
+ `create`: Vite Vue SPA with vue-router and vuex (requires Node.js 16 or higher)
+ `bot`: IBM Watson bot
+ `facedetect`: face detection and recognition
+ `ml`: ML Core (ml5 and p5)

App names cannot use the reserved folder names `app`, `bot`, `__bots__`, `css`, `facedetect`, `fonts`, `images`, `js`, `ml`, `resources` and `uploads`.

```
/caligrafy-quill:new-app facedetect faceauth
```

### `/caligrafy-quill:server <start | stop | status>`
Runs the Docker development server through `.bin/caligrafer` (app on `8080`, phpMyAdmin on `8077`, MySQL on `3306`). Before starting, it checks that Docker is running, that those ports are free (and proposes remapping them if they are not) and that `.env` has the Docker settings (`DB_HOST=mysql`, an empty `APP_ROOT`). Learn more about [Using Docker](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#using-docker).

```
/caligrafy-quill:server start
```

### `/caligrafy-quill:db <script.sql | tables | query "SQL">`
Runs SQL against the project's MySQL: inside the Docker `mysql` container, or a local MySQL using the `DB_*` settings of `.env`.
+ `<script.sql>`: runs a script such as `application/models/Book/create_db.sql`. Scripts that contain `DROP`, `TRUNCATE` or `DELETE` are checked first and need your confirmation
+ `tables`: lists the tables with their row counts
+ `query "SQL"`: runs read-only queries; Claude asks before any statement that writes

```
/caligrafy-quill:db application/models/Book/create_db.sql
```

## The `caligrafy-reviewer` agent
The reviewer checks your controllers, models, routes, Pug views, SQL scripts and Vue clients for framework-specific bugs and security issues: SQL built from user input, CSRF and API header mistakes, `check()` misuse, route fallbacks, unsafe uploads, `constant()` fed with user input, authentication and client-app templates older than the framework, Vue 2 syntax in Vue 3 apps, foreign key and pivot schema problems, and exposed secrets. It only reviews and never edits your files. Ask for it in plain language:

```
> Review my BookController with the caligrafy-reviewer agent
```

## Try it
A typical first session:

```
/caligrafy-quill:server start
/caligrafy-quill:new-resource Book title:string author:string publisher:fk
/caligrafy-quill:db application/models/Book/create_db.sql
/caligrafy-quill:new-app facedetect faceauth
> Add login with the Caligrafy authentication module and protect /books with guard()
> Review my BookController with the caligrafy-reviewer agent
```

## How the plugin's knowledge is maintained
+ `skills/caligrafy/reference/0*.md` mirror the pages of this wiki, with a link to the source page at the top of each. They are refreshed with `python3 claude-plugin/scripts/sync-wiki.py <wiki clone>` (`--check` reports stale mirrors).
+ `api-reference.md` and `gotchas.md` are written from the framework source. When the two disagree, the source-verified files take precedence over the wiki.
+ When `framework/` changes, these files need to be updated, the [QA suite](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#qa) run (`./qa/run.sh`) and the `version` in `claude-plugin/.claude-plugin/plugin.json` bumped. Claude also knows that projects created with an older framework keep older copies of the authentication module and client-app files in `application/` (see [Upgrading an existing project](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading)).

For plugin development, load it locally with `claude --plugin-dir ./claude-plugin` and validate it with `claude plugin validate ./claude-plugin --strict`.

> **Safety notes**
> + `php caligrafer.php initialize` deletes the `.git` folder. Claude asks before running it in a git repository.
> + Claude never prints or commits the secrets in your `.env` file.

**This the end of the documentation and the beginning of your journey!**
