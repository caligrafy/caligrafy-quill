# Migrations: database changes, in order

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Migrations
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **migration** is one change to your database, in a numbered SQL file that runs once. Your app's tables start in
`create_db.sql`; every change after that (a new column, a new table, an index, data to fix) goes in a migration, so
every computer and every server ends up with the same database, in the same order. Since 2.18.0.

## Writing one

```bash
php caligrafer.php migrate make add_books_isbn
```

This writes `application/migrations/20261107120000_add_books_isbn.sql` (the date and time is its number). Put the
change in it:

```sql
-- add_books_isbn
ALTER TABLE books ADD COLUMN isbn VARCHAR(20) NULL;
CREATE INDEX books_isbn ON books (isbn);
UPDATE books SET isbn = '' WHERE isbn IS NULL;
```

+ A file is named `<number>_<name>.sql`; files run in number order. Two files with the same number are refused.
+ Statements are split on `;` outside quotes and comments, so text such as `'a;b'` is safe. `DELIMITER` (triggers,
  procedures) is not supported: put those in `create_db.sql` or run them with `php caligrafer.php sql <file>`.

## Running them

```bash
php caligrafer.php migrate          # runs the ones that never ran on this database
php caligrafer.php migrate status   # ran, pending or changed, for each file
```

Each one that ran is recorded in the `caligrafy_migrations` table (its name, its checksum, when).

**In production, nothing to do:** the deploy step (`php caligrafer.php deploy`, run by the production image at every
start, and so by Caligrafy Cloud) runs the pending migrations after the `create_db.sql` scripts. A migration that fails
stops the deploy step with its name and the statement that failed.

`php caligrafer.php doctor` says when migrations are pending, and when one was edited after it ran.

## Three rules

1. **Never edit a migration that ran.** It will not run again (`status` shows it as `changed`). Write a new one.
2. **Forward only.** To undo a change, write a migration that undoes it.
3. **One change per file, or files that can run again.** MySQL applies a `CREATE` or an `ALTER` at once: when the third
   statement of a file fails, the first two stay applied and the file is not recorded. Keep one change per file, or
   write it so that it can run twice (`CREATE TABLE IF NOT EXISTS`).

## Migrations and create_db.sql

`create_db.sql` still creates your tables on a new database, and the deploy step still runs it again when it changes
(without its destructive statements). Migrations are for what happens to a database that already has data: they run
exactly once, in order, everywhere. A new table can start in either; a change to an existing one belongs in a
migration.
