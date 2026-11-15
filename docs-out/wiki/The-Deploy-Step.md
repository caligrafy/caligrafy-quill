**The deploy step** is `php caligrafer.php deploy`: what a project runs each time it starts in production, before
Apache serves it. The [production image](https://github.com/caligrafy/caligrafy-quill/wiki/The-Production-Image) runs it
for you. It compiles the settings and brings the database up to date: the framework tables, your `create_db.sql`
scripts when they are new or changed, then your migrations. Since 2.4.0 (migrations 2.18.0, `.env.php` 2.19.0).

<br/>

## With Claude

```text
/caligrafy-quill:deploy
```

Before a deploy, the skill checks what the deploy step will run: every `create_db.sql` safe to run twice, the
migrations in order, the schedule file valid. After one, `/caligrafy-quill:ship` reads the start-up log.

**Check** before you say OK: the log of the start has only `OK` and `SKIP` lines, and no `FAIL`.

<br/>

## By hand

On a server without Docker (files copied to PHP and Apache), run it yourself after each copy:

```bash
php caligrafer.php deploy
php caligrafer.php deploy --wait 120      # wait up to 2 minutes for the database
```

On your computer, run it against a production-like settings file to see what it would do:

```bash
docker compose exec dev-box php caligrafer.php deploy --env .env.staging
```

```text
 Caligrafy deploy
 caligrafy-schedule {"version":1,"jobs":[]}
  OK    Settings: .env compiled to .env.php (read without parsing on every request)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache, migrations, scripts
  OK    application/models/Auth/create_db.sql: up to date
  OK    application/models/Bookshelf/create_db.sql: changed: run again
  OK    Migrations: 1 run: 20261107232335_add_books_isbn.sql
```

> `--env` reads the database settings of that file; the `Settings` line always compiles the project's own `.env`.

<br/>

## <a name="steps"></a>What it does, in order

From [`Caligrafy\Deployer::run()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#deployer-run):

1. **The scheduled jobs line.** `caligrafy-schedule <json>`: the listing of `schedule list --json`, which Caligrafy
   Cloud reads from the log to run the jobs. A schedule file with problems prints
   `caligrafy-schedule-invalid <the problems>` instead, and its jobs do not run. Without the file the list is empty.
2. **The settings.** `.env` compiled to `.env.php`
   ([`Deployer::cacheEnv()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#deployer-cacheenv), the same
   as `env cache`): pages read the settings without parsing `.env` while `.env` keeps that content. Without `.env`:
   `SKIP  Settings: no .env to compile`.
3. **`DB_ACTIVATE`.** Not `true`: `SKIP  Database: DB_ACTIVATE is not true: nothing to prepare`, and it stops there with
   exit code `0`.
4. **The database.** It tries to connect every 2 seconds until `--wait` seconds have passed (60 by default; the image
   passes `CALIGRAFY_DEPLOY_WAIT`). Never reached:
   `FAIL  Database: not reachable after 60 s: check the DB_* settings`, exit code `1`.
5. **The framework tables.** `framework/settings/platform.sql` (every table `CREATE TABLE IF NOT EXISTS`: tokens, the
   audit log, job runs, notifications, jobs, the cache, migrations, Snap), and `caligrafy_scripts`, which records the
   scripts that ran.
6. **The `create_db.sql` scripts.** Every `application/models/<folder>/create_db.sql`, in folder order:

    | The script | Line | What happens |
    | --- | --- | --- |
    | never ran here | `OK  <path>: run` | it runs, and its SHA-256 is recorded |
    | ran, and has not changed | `OK  <path>: up to date` | nothing |
    | changed since it ran | `OK  <path>: changed: run again` | it runs again, and the new SHA-256 is recorded |
    | changed, and drops, deletes or truncates | `WARN  <path>: changed, but it drops or deletes data: not run (php caligrafer.php sql <path> runs it)` | nothing: run it by hand when you mean it |

    "Drops or deletes" is `DROP TABLE`, `DROP DATABASE`, `DROP SCHEMA`, `DROP VIEW`, `DROP COLUMN`, `DELETE FROM` or
    `TRUNCATE`, comments left aside ([`Deployer::destructive()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#deployer-destructive)).
    A statement that fails only because what it adds is already there (a duplicate column, key name or foreign key:
    MySQL errors 1060, 1061, 1826) is skipped, so a script keeps its `ALTER TABLE ... ADD` lines and runs again.
    Other files (`seed.sql`...) are never run.
7. **The migrations.** When `application/migrations` has files, the ones that never ran, in order:
   `OK  Migrations: 1 run: <file>`, or `OK  Migrations: up to date`. A migration edited after it ran:
   `WARN  Migrations: <file>: changed after it ran, not run again (write a new migration for the change)`.

Any SQL error in steps 5 to 7 prints `FAIL  Database: <the error>` (a migration names its file and statement) and the
exit code is `1`. The image starts Apache anyway, after `caligrafer deploy: the database is not prepared (see above)`.

<br/>

## <a name="scripts"></a>Writing scripts it can run again

Because a changed script runs again on the next start, each `create_db.sql` must be safe to run twice:

```sql
CREATE TABLE IF NOT EXISTS `books` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- added later: skipped once the column is there
ALTER TABLE `books` ADD COLUMN `isbn` VARCHAR(20) NULL;
```

For a change to a table that holds data, prefer a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations):
it runs once, in order, and says so.

<br/>

## Common mistakes

+ **A script that drops its table first** (G68): it ran once; changed, it is never run again (`WARN`), and run by hand it
  deletes the data. Use `CREATE TABLE IF NOT EXISTS` and added statements.
+ **`caligrafer deploy: the database is not prepared`**: the line above it says why: the database is not reachable (the
  `DB_*` settings, or a longer `--wait`), or a SQL error that names the statement.
+ **`Host ... is not allowed to connect`** (G71): the database was created but its user was not, often a MySQL killed for
  lack of memory while it initialized. Give it more memory, and recreate its volume while it holds no data.
+ **A setting changed on your own server is ignored**: `.env.php` was compiled from the old `.env`, and is used only
  while `.env` has that content; an edited `.env` is read again. Restart the container, or run `env cache`.
+ **Scheduled jobs do not run on Cloud**: the log has `caligrafy-schedule-invalid`: run `php caligrafer.php schedule list`
  to see the problems.

<br/>

## Related pages

+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)
+ [The production image](https://github.com/caligrafy/caligrafy-quill/wiki/The-Production-Image)
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations) · [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [Caligrafer: deploy commands](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Deploy-Commands)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
