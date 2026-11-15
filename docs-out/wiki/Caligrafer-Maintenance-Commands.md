The **maintenance commands** of [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) check and
keep a project current: `doctor` checks it, `design` picks its look, `update` and `upgrade` move it to a new release,
`plugin` connects its Claude Code plugin, and `snap` says what Snap Cards and the assistant need.

<br/>

## With Claude

| You want | Ask |
| --- | --- |
| the latest release, with your changes merged | `/caligrafy-quill:update` |
| another look for every page | `/caligrafy-quill:design linear.app` |
| card views for the assistant | `/caligrafy-quill:assistant` |
| to know what is wrong | "run doctor and fix what fails" |

**Check** before you say OK: `php caligrafer.php doctor` has no `FAIL`, and after an update no `.caligrafy-new` file is
left.

<br/>

## By hand

```bash
docker compose exec dev-box php caligrafer.php doctor
docker compose exec dev-box php caligrafer.php update --check
docker compose exec dev-box php caligrafer.php design list
```

(without Docker, drop `docker compose exec dev-box`). Run `doctor` first whenever something does not work.

<br/>

## <a name="doctor"></a>`doctor`

```text
doctor [--only openai,groq] [--json] [--env <file>]: read-only health check of the project and of the services configured in .env
```

```bash
php caligrafer.php doctor
php caligrafer.php doctor --only database,mail
php caligrafer.php doctor --env .env.production
php caligrafer.php doctor --json
```

```text
Caligrafy doctor (read-only): Caligrafy Quill 2.25.0

 Database
  OK    Database: connected to mysql://mysql:3306/caligrafy (server 8.4.13)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache and migrations installed
  OK    Migrations: 1 ran, none pending

 3 OK, 0 WARN, 0 FAIL, 0 SKIP
```

+ **The groups** (`--only`, separated by commas): `project`, `database`, `openai`, `groq`, `agent`, `stripe`,
  `coinbase`, `watson`, `mail`. An unknown name lists the valid ones.
+ **`project`** checks PHP (8.3 or later) and its extensions, `vendor/`, the keys (`APP_KEY`, `API_KEY`), `APP_DEBUG`,
  the legacy API key and the pages that still send it, the Claude Code plugin, `public/uploads`, the web app manifest and
  the service worker, the design style, forms without a method and Pug pitfalls in views, the schedule file,
  notifications, Snap's card views, changed framework files, and whether the templates of `application/` are up to
  date. **`database`** checks the connection, the framework tables, Snap's tables and the migrations. The others check each service configured in `.env`; a service that is not configured is
  `SKIP`.
+ **Each line** is `OK`, `WARN`, `FAIL` or `SKIP`, with `-> <fix>` under it when there is something to do.
+ **Read-only**: it lists, reads or connects, and never creates, charges or sends anything (Watson only opens and
  closes a session when its assistants cannot be listed). It never creates `.env`.
+ **Secrets** are never printed: at most a known prefix and the last 4 characters.
+ **Exit code** `1` when a check fails. `--json` prints the results for a script.
+ **With Docker**, run it in the container (`docker compose exec dev-box ...`) so that the host `mysql` resolves.

The class behind it is [`Caligrafy\Doctor`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#doctor).

<br/>

## <a name="design"></a>`design`

```text
 Usage: php caligrafer.php design <list [--json] | show <name> | use <name> | off | status>
```

| Command | What it does |
| --- | --- |
| `design list` | the styles of `application/design`, `framework/design/local` and `framework/design/vendor/*` (the first wins on a name clash): ` 85 styles (* selected, + stylesheet written)` |
| `design list --json` | `{"selected": ..., "styles": [...]}` |
| `design show <name>` | its source, description, `DESIGN.md` and stylesheet, and the copies a clash hides |
| `design use <name>` | `DESIGN_STYLE=<name>` in `.env` |
| `design off` | `DESIGN_STYLE=` in `.env`: Caligrafy's own look; the stylesheets are kept |
| `design status` | the selected style, and whether its stylesheet exists |

`use` changes nothing on the pages until `public/css/designs/<name>.css` exists (G92):
` DESIGN_STYLE=<name> in .env. Its stylesheet public/css/designs/<name>.css is not written yet: pages keep Caligrafy's own look until it is.`
`/caligrafy-quill:design` writes it. Learn more in [Design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles).

<br/>

## <a name="update"></a>`update`

```text
update [<version>] [--check] [--yes] [--no-composer]: moves the project to another release of Caligrafy Quill (the latest by default) and applies everything
```

| Option | Effect |
| --- | --- |
| `<version>` | a release (`2.21.6` or `v2.21.6`); the latest by default. A lower version than the project's is refused |
| `--check` | says what it would replace, add and keep, the new settings and packages; writes nothing |
| `--yes` | goes on although git has uncommitted changes |
| `--no-composer` | prints the `composer update` command instead of running it |

On the latest release: ` Caligrafy Quill 2.25.0 is the latest: nothing to update. php caligrafer.php upgrade checks application/.`
What it replaces, keeps and merges, and how to finish an update, is in
[Updating Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Updating-Caligrafy).

<br/>

## <a name="upgrade"></a>`upgrade`

```text
upgrade [--apply] [--local] [--force <application/path>] [--keep <application/path>]: brings the project up to date (dry run by default)
```

| Option | Effect |
| --- | --- |
| (none) | a dry run: when a newer release is out, the same as `update --check`; else what it would change in `application/` |
| `--apply` | when a newer release is out, `update` to it; else replaces the files of `application/` you never changed |
| `--local` | compares `application/` with this release's templates only, without looking for a newer release |
| `--force <application/path>` | replaces one file you changed (a backup is kept) |
| `--keep <application/path>` | keeps an untouched template copy that is now yours (remembered in the manifest); can be given more than once |

Each template file is reported as `missing`, `identical`, `outdated`, `customized`, `kept`, `held` or `routes`. The
states, and what to do with each, are in [Updating Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Updating-Caligrafy#upgrade).

<br/>

## <a name="plugin"></a>`plugin`

```text
plugin: gives this project's Claude Code plugin its own source name and says where Claude Code reads it
```

```text
 Plugin source: caligrafy-7df2, plugin caligrafy-quill@caligrafy-7df2
 Claude Code reads the plugin from /Users/me/www/bookshelf/claude-plugin: restart claude in this folder to load it
```

1. It renames the plugin source of `.claude-plugin/marketplace.json` and `.claude/settings.json` from the shared
   `caligrafy` to the project's own name (` (renamed from the shared "caligrafy")`). One name for every project gave
   them all the first one's plugin (G97).
2. When the `claude` command is on this computer, it registers the source (`claude plugin marketplace add`), installs
   `caligrafy-quill@<name>` for the project (`--scope project`), and, when Claude Code's copy of the plugin is older than
   the project's, refreshes it: ` Claude Code's copy of the plugin: <old>, now <new>`.
3. Without `claude` (in Docker) it says to run it on your computer.

A project with no `.claude-plugin/marketplace.json`: ` No .claude-plugin/marketplace.json: this project has no Claude Code plugin source (php caligrafer.php update brings it)`.
Run it when a `/caligrafy-quill:...` command is unknown, then restart `claude` in the folder. The class behind it is
[`Caligrafy\PluginSource`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#pluginsource).

<br/>

## <a name="snap"></a>`snap`

```text
 Usage: php caligrafer.php snap [install | status | views]
```

```bash
php caligrafer.php snap install   # Snap's tables, and application/config/snap.php when there is none
php caligrafer.php snap           # what is ready (snap status)
php caligrafer.php snap views     # how each card that shows data looks (2.25.0)
```

```text
 Snap Cards and the assistant
  [ok] Tables            caligrafy_snap_*
  [ok] Route             Route::snap(); in application/config/routing/web.php
  [ok] Card hints        application/config/snap.php (optional: cards work without it)
  [--] Assistant model   ANTHROPIC_API_KEY in .env (without it the assistant answers from the cards' names)

 Then open /assistant (signed in), and /snap for every card.
```

`snap views` lists every read-only capability's card:

```text
 Card views (application/views/snap/<capability>.pug)
  [ok] get_book                   from Bookshelf/book.pug
  [!!] list_books                 Bookshelf/books.pug changed since its card view was made: write it again
  [--] get_user                   no card view: a card designed from its data
  [??] old_report                 no read-only capability of that name: the file is not used
```

It exits with `1` when a card view is stale. `install` and `status` need the database. Learn more in
[Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#looks),
[`Snap\Views`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-views) and
[`Snap\Setup`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-setup).

<br/>

## Common mistakes

+ **`doctor | tail` says nothing failed** (G89): a pipe hides the exit code. Read the summary line, or use
  `set -o pipefail`.
+ **A new skill is missing after an update** (G103): Claude Code runs its own copy of the plugin. `php caligrafer.php plugin`
  on your computer refreshes it.
+ **`upgrade` says nothing changes on an old project** (G96): before 2.10.0 it never looked for a newer release. Run
  `update`.
+ **`design use` and the pages look the same** (G92): the style's stylesheet is not written yet.

<br/>

## Related pages

+ [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Updating Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Updating-Caligrafy)
+ [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)
+ [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
+ [API reference: framework and tooling](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
