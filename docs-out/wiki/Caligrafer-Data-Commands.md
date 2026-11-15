The **data commands** of [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) work on the
database and the files the framework keeps for speed: `sql` runs a script, `migrate` runs and writes migrations,
`platform install` creates the framework tables, `cache clear` empties the cache, and `env` compiles `.env`.

<br/>

## With Claude

```text
/caligrafy-quill:db add an isbn column to books
```

The `db` skill finds the database of `.env` (the Docker `mysql` container or a local MySQL), writes a migration with
`migrate make`, runs it, and shows the tables. It also runs `create_db.sql` scripts and fixes `[ERROR 2003]` and
"table doesn't exist" errors.

**Check** before you say OK: `php caligrafer.php migrate status` shows the new migration as `ran`, and
`php caligrafer.php doctor --only database` has no `FAIL`.

<br/>

## By hand

A new project's tables, then a change to a table that holds data:

```bash
docker compose exec dev-box php caligrafer.php platform install
docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
docker compose exec dev-box php caligrafer.php migrate make add_books_isbn
# write the ALTER TABLE in application/migrations/<YmdHis>_add_books_isbn.sql, then:
docker compose exec dev-box php caligrafer.php migrate
```

(without Docker, drop `docker compose exec dev-box`). Every command here reads the database of `.env`
(`DB_ACTIVATE=true` and the `DB_*` settings), or of another file with `--env <file>`.

<br/>

## <a name="sql"></a>`sql`

```text
 Usage: php caligrafer.php sql <file.sql>
```

```bash
php caligrafer.php sql application/models/Bookshelf/create_db.sql
php caligrafer.php sql application/models/Bookshelf/create_db.sql --env .env.testing
```

```text
 application/models/Bookshelf/create_db.sql run on caligrafy
```

+ It removes the `/* ... */` comments and the lines that start with `--`, splits the rest on `;` and runs each statement.
+ A failure prints ` <file> could not be run: <reason>` and exits with `1`; the statements before it stay applied.
+ Without `DB_ACTIVATE=true`: `The database is not active: set DB_ACTIVATE=true and the DB_* settings in .env`.
+ Use it for the tables of a new database, and for a script the deploy step refused to run again because it drops or
  deletes data. A change to a table that holds data is a migration.

<br/>

## <a name="migrate"></a>`migrate`

```text
 Usage: php caligrafer.php migrate [status | make <name>] [--env <file>]
 Runs the migrations of application/migrations that never ran on this database
```

```bash
php caligrafer.php migrate make add_books_isbn   # writes application/migrations/<YmdHis>_add_books_isbn.sql
php caligrafer.php migrate                       # runs the ones that never ran, in order
php caligrafer.php migrate status                # ran, pending or changed
php caligrafer.php migrate --env .env.testing    # on the database of another settings file (2.19.1)
```

**`make <name>`** needs no database. The name is lower-cased, and anything but letters and digits becomes `_`. The file
starts with two comment lines:

```sql
-- add_books_isbn
-- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.
```

**`migrate`** runs, in number order, every file that never ran on this database, and records it in
`caligrafy_migrations` (its name, its sha256 and when):

```text
 OK   20261107232335_add_books_isbn.sql

 1 migration(s) run on caligrafy
```

With nothing to run: ` Nothing to migrate: caligrafy is up to date`. A file edited after it ran is not run again:
` WARN <file>: changed after it ran, not run again (write a new migration for the change)`.

**`status`** prints one line per file:

```text
 ran      20261107232335_add_books_isbn.sql  (2026-11-07 23:23:35)
 pending  20261108090000_add_loans_due_on.sql
```

| State | Means |
| --- | --- |
| `ran` | it ran on this database, and the file is what ran |
| `pending` | it never ran: the next `migrate` runs it |
| `changed` | it ran, and the file was edited since: it never runs again |

+ A file is named `<number>_<name>.sql` (up to 14 digits); any other `.sql` file in the folder stops the command with
  the name it expects. Two files with the same number stop it too.
+ The statements of a file are split on `;` outside quotes and comments. A file stops at its first failing statement,
  which the error names (`<file>, statement 2: ...`): it is not recorded, and what ran before it stays (MySQL commits a
  `CREATE` or `ALTER` at once). Keep one change per file, or write it so that it can run again.
+ Forward only: undo a change with a new migration.
+ `--env <file>` can come anywhere after `migrate`. Errors go to standard error, with exit code `1`.
+ The deploy step runs the pending ones at each start in production.

Learn more in [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations) and
[`Caligrafy\Migrations`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Data#migrations).

<br/>

## <a name="platform"></a>`platform install`

```text
 Usage: php caligrafer.php platform install
 Creates the framework tables (tokens, audit log, job runs, notifications, jobs, cache, migrations, Snap) from framework/settings/platform.sql
```

```text
 Framework tables ready: caligrafy_tokens, caligrafy_audit, caligrafy_job_runs, the notifications tables, caligrafy_jobs, caligrafy_cache, caligrafy_migrations and Snap's tables
```

It runs `framework/settings/platform.sql`, which creates each table only when it is missing: safe to run again, and
needed after an update that adds tables. `quickstart` and the deploy step run it. On failure:
` The framework tables could not be created: <reason>` and `Check DB_ACTIVATE and the DB_* settings in .env (php caligrafer.php doctor --only database)`,
exit code `1`.

<br/>

## <a name="cache"></a>`cache clear`

```text
 Usage: php caligrafer.php cache clear
 Empties the cache (Caligrafy\Cache, CACHE_STORE in .env), the compiled views and the class map
```

```text
 Cache (database) emptied; 2 compiled view and class map file(s) removed
```

+ The cache of [`Caligrafy\Cache`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Data#cache) in its store
  (`CACHE_STORE`: the `caligrafy_cache` table by default with `DB_ACTIVATE=true`, else private files). With the file
  store it says how many values it removed (`Cache (file) emptied: 3 value(s); ...`).
+ The compiled Pug views and the class map of this user, in the temporary folder.
+ Run it after copying files that keep their old modification times (`rsync -a`, `cp -p`): a view can look older than
  its compiled copy (G117).
+ The web server and the command line are different users: with `CACHE_STORE=file` each has its own folder, and the
  command line empties only its own (G119).

Learn more in [Caching](https://github.com/caligrafy/caligrafy-quill/wiki/Caching).

<br/>

## <a name="env"></a>`env`

```text
 Usage: php caligrafer.php env <cache | clear>
```

```bash
php caligrafer.php env cache    # .env compiled to .env.php
php caligrafer.php env clear    # .env.php removed: pages read .env again
```

```text
 .env compiled to .env.php: pages read it without parsing .env (php caligrafer.php env clear removes it)
```

+ `.env.php` is a PHP array that `index.php` includes (OPcache keeps it) instead of parsing `.env` on every request.
  It holds the sha1 of the `.env` it was compiled from, and is used only while `.env` still has that content: edit
  `.env` and pages read it again at once.
+ It is written next to `.env` with the same permissions (and owner, when run as root), through a temporary file.
+ It holds the same secrets as `.env` and is in `.gitignore`: never commit it.
+ `env cache` with no `.env` prints ` env cache: no .env to compile, or .env.php could not be written` and exits with `1`.
+ The deploy step runs it at every start in production
  ([`Deployer::cacheEnv()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#deployer-cacheenv)).

<br/>

## Common mistakes

+ **Editing a migration after it ran** (G120): nothing happens, `status` says `changed` and `doctor` warns. Restore it
  and write a new one.
+ **A `;` inside a string of a `create_db.sql`**: `sql` and the deploy step split a script on every `;`, quotes
  included (migrations split outside quotes). Put such values in a migration, or avoid the `;`.
+ **`create_db.sql` that drops a table**: the deploy step runs a changed script again, but never one that drops or
  deletes (G68). Keep scripts safe to run twice (`CREATE TABLE IF NOT EXISTS`, added `ALTER TABLE ... ADD` lines).
+ **`migrate "" --env .env.testing`**: the empty word was needed before 2.19.1; write `migrate --env .env.testing`.
+ **A changed view still shows the old page after copying files** (G117): `cache clear`.
+ **`.env.php` in git**: it holds your secrets. The project's `.gitignore` leaves it out; keep it that way.

<br/>

## Related pages

+ [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations) · [Caching](https://github.com/caligrafy/caligrafy-quill/wiki/Caching)
+ [The deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step)
+ [API reference: data](https://github.com/caligrafy/caligrafy-quill/wiki/API-Data)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
