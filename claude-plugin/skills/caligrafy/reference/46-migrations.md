# Migrations: database changes, in order

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Migrations
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **migration** is one change to a table that already holds data, written as a numbered SQL file that runs once. Every
computer and every server runs the same files in the same order, so they all end up with the same tables. Since 2.18.0.

**Which file to write:**

| You want to... | Write | Run it with |
|---|---|---|
| create a new table (its first version) | `CREATE TABLE IF NOT EXISTS` in `application/models/<Folder>/create_db.sql` ([Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)) | `php caligrafer.php sql application/models/<Folder>/create_db.sql` |
| change a table that holds data: add a column or an index, rename, fix rows | a migration in `application/migrations/` (this page) | `php caligrafer.php migrate` |

A `create_db.sql` that already ran is never edited: once a table has rows, every change to it goes in a new migration.

## With Claude

Ask for the change with the `db` skill of the Claude Code plugin:

```text
/caligrafy-quill:db add an isbn column (20 characters, optional, unique) to the books table
```

Claude writes `application/migrations/<number>_<name>.sql` with `php caligrafer.php migrate make <name>`, puts the SQL
in it, runs `php caligrafer.php migrate` (in the Docker container when the project uses the Docker server) and checks
`php caligrafer.php migrate status`. It never edits a migration that already ran.

**Check** before you say OK:
+ the new file is in `application/migrations/` and holds one change;
+ `php caligrafer.php migrate status` shows every file as `ran`;
+ the model of the table has a property for each new column (`public $isbn;`).

## By hand

The Bookshelf `books` table exists and holds rows ([Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)).
Add an `isbn` column to it. With the Docker server of the
[Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart), run every command in the container:
`docker compose exec dev-box php caligrafer.php ...` (without Docker, drop `docker compose exec dev-box`).

1. **Create the file.** Run:
    ```bash
    docker compose exec dev-box php caligrafer.php migrate make add_books_isbn
    ```
    It prints the file it wrote (the number is the date and time):
    ```text
     application/migrations/20261107120200_add_books_isbn.sql written: add its SQL, then php caligrafer.php migrate
    ```
2. **Write the change.** Open that file and make it read:
    ```sql
    -- add_books_isbn
    -- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.
    -- One statement: the column and its unique index are added together, or not at all.

    ALTER TABLE books
        ADD COLUMN isbn VARCHAR(20) NULL AFTER summary,
        ADD UNIQUE KEY uq_books_isbn (isbn);
    ```
    Two books never share an ISBN, and a book without one has `NULL`, which the unique index allows on many rows.
3. **See what will run.** Run:
    ```bash
    docker compose exec dev-box php caligrafer.php migrate status
    ```
    ```text
     pending  20261107120200_add_books_isbn.sql
    ```
4. **Run it.** Run:
    ```bash
    docker compose exec dev-box php caligrafer.php migrate
    ```
    ```text
     OK   20261107120200_add_books_isbn.sql

     1 migration(s) run on caligrafy
    ```
    Run it again and it says ` Nothing to migrate: caligrafy is up to date`.
5. **Give the model the new column.** In `application/models/Bookshelf/Book.php`, add `public $isbn;` after
   `public $summary;` (the whole model is on the [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models) page).
6. **Commit the file.** It runs online on its own: the deploy step (`php caligrafer.php deploy`, which the production
   image runs at every start, and so Caligrafy Cloud) runs the pending migrations after the `create_db.sql` scripts.

**Check:**
+ `docker compose exec dev-box php caligrafer.php migrate status` shows ` ran      20261107120200_add_books_isbn.sql  (<date and time>)`;
+ `docker compose exec dev-box php caligrafer.php doctor --only database` shows `OK    Migrations: 1 ran, none pending`;
+ the column is there: `docker compose exec mysql mysql -uroot -proot caligrafy -e "DESCRIBE books"` lists
  `isbn  varchar(20)  YES  UNI  NULL`.

## Reference

**Commands**

| Command | What it does |
|---|---|
| `php caligrafer.php migrate make <name>` | writes `application/migrations/<YYYYMMDDHHMMSS>_<name>.sql` with a comment header (the name becomes lower case, letters, digits and `_`). Needs no database |
| `php caligrafer.php migrate` | runs, in number order, every file that never ran on this database, and records each one |
| `php caligrafer.php migrate status` | each file with its state: `pending`, `ran` (with when) or `changed` |
| `php caligrafer.php migrate --env .env.testing` | the same on the database of another `.env` file, here the test database; `--env` goes with `migrate` and `migrate status` (2.19.1; before, write `migrate "" --env .env.testing`) |
| `php caligrafer.php doctor` | `WARN Migrations: 1 pending: ...` or `... changed after it ran: it will not run again`; `OK Migrations: N ran, none pending` |
| `php caligrafer.php deploy` | (production start) runs the `create_db.sql` scripts, then the pending migrations: `OK Migrations: 1 run: <name>` or `up to date`. A failing migration stops it with exit 1 |

**Files**

+ Named `<number>_<name>.sql`: the number is 1 to 14 digits, the name letters, digits and `_`. Another `.sql` name in
  the folder stops every command: `notes.sql: a migration is named <number>_<name>.sql, e.g. 20261107120000_add_books_isbn.sql`.
+ Two files with the same number (as a number: `5_a.sql` and `005_b.sql`) are refused.
+ The statements are split on `;` outside quotes (`'...'`, `"..."`, `` `...` ``) and comments (`-- `, `#`, `/* */`),
  so text such as `'a;b'` is safe. `DELIMITER` is not supported: write triggers and procedures in a script you run
  with `php caligrafer.php sql <file>`.
+ An empty file runs and is recorded: write its SQL before you run `migrate`.

**The table `caligrafy_migrations`** (made by `php caligrafer.php platform install`, or by the first `migrate`): `name`
(the file name), `sha256` (of its content when it ran) and `ran_at`. A file whose content no longer matches its
`sha256` is `changed`: it is never run again.

## Three rules

1. **Never edit a migration that ran.** It will not run again: `status` shows `changed`, and `migrate` prints
   ` WARN <name>: changed after it ran, not run again (write a new migration for the change)`. Put the file back as it
   was and write a new one.
2. **Forward only.** There is no "down": to undo a change, write a migration that undoes it.
3. **One change per file, or files that can run twice.** MySQL saves a `CREATE` or an `ALTER` at once. When the second
   statement of a file fails, the first one stays applied and the file is not recorded:
   ```text
    migrate: 20261107120300_add_more.sql, statement 2: SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'isbn'
   ```
   Keep one change per file, or write it so that it can run again (`CREATE TABLE IF NOT EXISTS`).

## Migrations and create_db.sql

`create_db.sql` gives a new database its tables: `php caligrafer.php sql <file>` runs it by hand, and the deploy step
runs it when it is new or changed (a changed script that drops or deletes data is not run again: it says so in a `WARN`
line). A migration runs exactly once on each database, in order, after the scripts. So:

+ a new table: `create_db.sql` (its first version, `CREATE TABLE IF NOT EXISTS`);
+ any change to a table that holds data: a migration;
+ sample rows for your computer: a `seed.sql` you run with `php caligrafer.php sql` (the deploy step never runs it).

## Common mistakes

+ **Editing the file after it ran** (G120): nothing happens on any database. Restore it and write a new migration.
+ **Changing `create_db.sql` instead of writing a migration**: a database that already has the table keeps the old
  one (`CREATE TABLE IF NOT EXISTS` skips it).
+ **A table change inside a transaction or a test**: MySQL ends the transaction at the first `CREATE` or `ALTER`.
  Keep table changes in migrations.
+ **Forgetting the model**: the new column is still read and written (the rows carry it), but the model no longer
  says what the table holds. Add its property in the same change.

## Related pages

+ [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models): the first version of a table, and its model
+ [Caligrafer: migrate](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#migrate)
+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App): migrations on a test database
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online): the deploy step
