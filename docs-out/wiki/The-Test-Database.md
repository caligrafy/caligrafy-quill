The tests of an app run on a **database of their own**, and each test runs in a **transaction that is rolled back** at
its end: the rows a test writes are gone after it, and tests never see each other's rows or yours. This page sets that
database up, and says what the test bootstrap does with the settings. Since 2.18.0.

<br/>

## With Claude

```text
/caligrafy-quill:db create the test database: caligrafy_test, with the framework tables, my tables and the migrations
```

**Check** before you say OK: `php caligrafer.php migrate status --env .env.testing` lists every migration as `ran`, and
`php caligrafer.php test` passes twice in a row.

<br/>

## By hand

Run the commands in the Docker server (`docker compose exec dev-box ...`; without Docker, drop it and use your MySQL).

1. **Its settings.** Copy `.env` and change one line:
    ```bash
    cp .env .env.testing
    ```
    ```text
    DB_DATABASE=caligrafy_test
    ```
    `.env.testing` holds the same secrets as `.env`: the project's `.gitignore` leaves out every `.env.*` file but
    `.env.example` (2.19.1).
2. **The database and its tables.**
    ```bash
    docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS caligrafy_test"
    docker compose exec dev-box php caligrafer.php platform install --env .env.testing
    docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql --env .env.testing
    docker compose exec dev-box php caligrafer.php migrate --env .env.testing
    ```
    ```text
     application/models/Bookshelf/create_db.sql run on caligrafy_test
     1 migration(s) run on caligrafy_test
    ```
    Run `sql` for each `application/models/<folder>/create_db.sql` of the app.
3. **Run the tests.**
    ```bash
    docker compose exec dev-box php caligrafer.php test
    ```

**Check:** after the tests, the rows they wrote are not in the test database:
`docker compose exec mysql mysql -uroot -proot caligrafy_test -e "SELECT COUNT(*) FROM books WHERE title = 'Tehanu'"`
gives `0`.

After every new migration, run `migrate --env .env.testing` again: the tests use the tables as they are.

<br/>

## <a name="settings"></a>Which settings the tests read

`php caligrafer.php test` runs PHPUnit with `framework/testing/bootstrap.php`, which:

1. loads `vendor/autoload.php`;
2. reads `.env`, then `.env.testing` when it exists: its settings win;
3. when `DB_TEST_DATABASE` is set (and not empty), puts it in place of `DB_DATABASE` for the tests;
4. sets `CALIGRAFY_TESTING=true`;
5. loads the framework as the command line does (`Caligrafer::run()`: the settings as constants, the helpers, the class
   map; not `application/config/config.php`), then `Caligrafy\Testing\TestCase`.

So there are two ways to point the tests at their database:

| Way | Where | For the commands that prepare it |
| --- | --- | --- |
| `.env.testing` with its own `DB_DATABASE` (and any other setting) | a file next to `.env` | `--env .env.testing` |
| `DB_TEST_DATABASE=caligrafy_test` | one line in `.env` | still a settings file: the commands read `DB_DATABASE`, so `--env` with a file that names it |

<br/>

## <a name="transactions"></a>The transaction of each test

With `DB_ACTIVATE=true`, before each test `TestCase` opens a transaction on the connection, and after it rolls it back.
Also before each test: the container is reset and booted, the cache's in-memory copies are cleared, and the query
shapes the N+1 warning counts are forgotten.

+ **A `CREATE`, `ALTER` or `DROP`** in a test ends the transaction: MySQL commits it at once (G121), and the rows the test
  wrote before stay. Create tables in `create_db.sql` and migrations, before the tests.
+ **A test without a transaction**: `protected $transactions = false;` in the class. It then cleans up after itself.
+ **The cache store** `database` (`caligrafy_cache`) is in the same transaction: values a test puts there are rolled back
  too.

<br/>

## Common mistakes

+ **Tests on your development database**: without `.env.testing` or `DB_TEST_DATABASE`, the tests run on the database
  of `.env`. The transactions keep its rows, but the tests pass or fail with what is there.
+ **The test database is behind**: a new migration ran on the development database only, and a test fails on a
  missing column. `migrate --env .env.testing`.
+ **`migrate "" --env .env.testing`**: the empty word was needed before 2.19.1.
+ **`.env.testing` in git**: a project made before 2.19.1 adds `/.env.*` and `!/.env.example` to its `.gitignore`.
+ **Snap or token tests fail on a missing table**: `platform install --env .env.testing` creates the framework tables
  (tokens, audit, jobs, cache, migrations, Snap) in the test database.

<br/>

## Related pages

+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)
+ [Testing capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Capabilities) · [Testing pages, forms and actions](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Pages-Forms-and-Actions)
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
+ [Caligrafer: data commands](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Data-Commands)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
