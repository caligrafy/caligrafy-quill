# Testing your app

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Your app's **tests** check what it does through its capabilities, the way its pages, REST API, Vue apps and AI agents
reach them: with the permission checks, the validation and the audit. Each test runs in a database transaction that
is rolled back at its end, so tests leave the database as they found it. Since 2.18.0.

## With Claude

The `new-capability` skill writes a test with each capability. To test what exists, ask:

```text
/caligrafy-quill:new-capability write the tests of create_book, get_book and lend_book: the rule cases as a user, the refusals as a guest
```

Claude runs `php caligrafer.php test make <Name>`, fills `tests/<Name>Test.php` (extends `Caligrafy\Testing\TestCase`)
with `$this->call(...)` and `$this->assertCapabilityFails(...)`, and runs `php caligrafer.php test <Name>`.

**Check** before you say OK:
+ every refusal kind the capability can give has its assertion (`invalid`, `unauthenticated`, `forbidden`, `not_found`, `conflict`);
+ the tests create their own rows: they do not count on the rows of your development database;
+ `php caligrafer.php test` ends with `OK (...)`.

## By hand

The tests of the Bookshelf capabilities (`create_book`, `list_books`, `get_book`, `delete_book` of
[Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service)
and `lend_book` of [Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection), on
the `loans` table of [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)). `create_book` is closed
there (signed in or a token) and `delete_book` has `level: 1`.
PHPUnit comes with the development packages of every project (`composer install`). Run the commands in the Docker
container (`docker compose exec dev-box php caligrafer.php ...`; without Docker, drop `docker compose exec dev-box`).

1. **A database for the tests.** Copy the settings: `cp .env .env.testing`. In `.env.testing`, change one line:
    ```text
    DB_DATABASE=caligrafy_test
    ```
    `.env.testing` holds the same secrets as `.env`: the project's `.gitignore` already leaves it out of git (every
    `.env.*` file but `.env.example`, 2.19.1). Then create the database and its tables:
    ```bash
    docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS caligrafy_test"
    docker compose exec dev-box php caligrafer.php platform install --env .env.testing
    docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql --env .env.testing
    docker compose exec dev-box php caligrafer.php migrate --env .env.testing
    ```
    `--env .env.testing` runs the command on the test database (` application/models/Bookshelf/create_db.sql run on
    caligrafy_test`, ` 1 migration(s) run on caligrafy_test`). The tests
    read `.env`, then `.env.testing`, whose settings win.
2. **Create the test file.** Run:
    ```bash
    docker compose exec dev-box php caligrafer.php test make Bookshelf
    ```
    ```text
     tests/BookshelfTest.php written: php caligrafer.php test Bookshelf
    ```
3. **Write the tests.** Make `tests/BookshelfTest.php` read:
    ```php
    <?php

    use Caligrafy\Database;
    use Caligrafy\Testing\TestCase;

    /**
     * php caligrafer.php test Bookshelf
     */
    final class BookshelfTest extends TestCase
    {
        private function author(): int
        {
            // a row for this test only: the transaction of the test is rolled back at its end
            return (int) Database::table('authors')->insert(array('name' => 'Ursula K. Le Guin'));
        }

        public function testAReaderAddsABookAndEveryoneSeesIt(): void
        {
            $authorId = $this->author();
            $book = $this->call('create_book', array('title' => 'Tehanu', 'published_year' => 1990, 'author_id' => $authorId), $this->user(7));

            $this->assertSame('Tehanu', $book['title']);
            $this->assertSame('Ursula K. Le Guin', $book['author']['name']);
            $page = $this->call('list_books', array('author_id' => $authorId), 'guest');
            $this->assertSame(1, $page['total']);
            $this->assertSame('Tehanu', $page['data'][0]['title']);
        }

        public function testRefusals(): void
        {
            $error = $this->assertCapabilityFails('invalid', 'create_book', array('title' => 'Tehanu'), $this->user(7));
            $this->assertArrayHasKey('published_year', $error->fields);

            $this->assertCapabilityFails('unauthenticated', 'create_book', array('title' => 'Tehanu'), 'guest');
            $this->assertCapabilityFails('forbidden', 'delete_book', array('id' => 1), $this->user(7, 0));
            $this->assertCapabilityFails('not_found', 'get_book', array('id' => 999999), 'guest');
        }

        public function testALoanIsDueIn14Days(): void
        {
            $this->fake(Clock::class, new class implements Clock {
                public function today(): DateTimeImmutable
                {
                    return new DateTimeImmutable('2026-11-01');
                }
            });
            $book = $this->call('create_book', array('title' => 'Tehanu', 'published_year' => 1990, 'author_id' => $this->author()));

            $loan = $this->call('lend_book', array('book_id' => $book['id'], 'borrower' => 'Ana'), $this->user(7));

            $this->assertSame('2026-11-15', $loan['due_on']);   // 14 days after the fixed today
            $this->assertCapabilityFails('conflict', 'lend_book', array('book_id' => $book['id'], 'borrower' => 'Ben'), $this->user(7));
        }

        public function testTheBookPage(): void
        {
            // the data Route::page gives application/views/Bookshelf/book.pug: get_book and list_authors
            $html = $this->render('Bookshelf/book', array(
                'book' => array('id' => 1, 'title' => 'Tehanu', 'published_year' => 1990, 'summary' => 'The fourth Earthsea book.',
                    'isbn' => null, 'author_id' => 1, 'author' => array('name' => 'Ursula K. Le Guin')),
                'authors' => array(array('id' => 1, 'name' => 'Ursula K. Le Guin'))
            ));

            $this->assertStringContainsString('Tehanu', $html);
            $this->assertStringContainsString('by Ursula K. Le Guin, 1990', $html);
        }
    }
    ```
    `$this->user(7)` is a signed-in user of id 7 and level 0: `delete_book` (level 1) refuses them.
    `$this->call(..., null)` (no third argument) is the command line, with full access.
4. **Run them.**
    ```bash
    docker compose exec dev-box php caligrafer.php test
    ```
    ```text
    PHPUnit 12.5.40 by Sebastian Bergmann and contributors.

    ....                                                                4 / 4 (100%)

    OK (4 tests, 13 assertions)
    ```
    (between them, the PHP version and the time it took). `php caligrafer.php test Loan` runs only the tests whose name matches `Loan` (`testALoanIsDueIn14Days`).

**Check:**
+ `OK (4 tests, 13 assertions)`;
+ the test database has no `Tehanu` afterwards:
  `docker compose exec mysql mysql -uroot -proot caligrafy_test -e "SELECT COUNT(*) FROM books WHERE title = 'Tehanu'"`
  gives `0` (the five sample books of `create_db.sql` stay);
+ break a rule on purpose (remove `required` from `published_year` in `CreateBook`): `testRefusals` fails. Put it back.

## Reference

| Helper | What it does |
|---|---|
| `$this->call($capability, $input, $as)` | calls the capability with its checks, validation and audit; returns its answer as plain data |
| `$this->user($id = 1, $level = 0, $attributes = [])` | a signed-in user for `$as`: an id, a permission level (`permissions`), other attributes the capability reads |
| `$this->assertCapabilityFails($kind, $capability, $input, $as)` | the capability refuses with `$kind` (`invalid`, `unauthenticated`, `forbidden`, `not_found`, `conflict`); returns the `ServiceError` (`->fields`, `->getMessage()`) |
| `$this->render($view, $data)` | the HTML of a Pug view of `application/views`, as a page shows it |
| `$this->fake($class, $object)` | the [container](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection) gives `$object` for `$class` in this test |
| `$this->assertQueued($capability, $where = null)` | a [background job](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) of that capability was pushed in this test (whose input passes the function `$where`); returns its input |

**Who calls (`$as`)**

| `$as` | The test calls as |
|---|---|
| `null` (or nothing) | the command line: full access |
| `'guest'` | a visitor: only public capabilities |
| `$this->user(7)`, an object or an array | that user, over REST |
| a `Caligrafy\Context` | that context (a token: `Context::forToken($row, 'mcp')`) |

**Commands**

| Command | What it does |
|---|---|
| `php caligrafer.php test make <Name>` | writes `tests/<Name>Test.php` (refuses an existing file) |
| `php caligrafer.php test` | runs every `tests/*Test.php` and `tests/*/*Test.php` with PHPUnit; exit 0 when they pass |
| `php caligrafer.php test <filter>` | only the tests whose class or method name matches |

Without PHPUnit it says ` PHPUnit is not installed: composer install (with the development packages), then php caligrafer.php test`.
A warning fails the run (`--fail-on-warning`).

## The database

+ With `DB_ACTIVATE=true`, each test runs in a transaction that is rolled back at its end: the rows a test writes are
  gone after it, and tests never see each other's rows.
+ The settings are those of `.env`, then of `.env.testing` (whose settings win). `DB_TEST_DATABASE=caligrafy_test` in
  `.env` also works: it replaces `DB_DATABASE` for the tests only; the commands that prepare that database still need
  a settings file (`--env`).
+ A test that must not run in a transaction sets `protected $transactions = false;` (and cleans up after itself).
+ Between tests, the container's bindings, the cache's in-memory copies and the counts of the N+1 warning are reset.

## Where tests live

`tests/` at the top of the project, one class per file named `*Test.php`, extending `Caligrafy\Testing\TestCase`.
The production image leaves `tests/` out, and the framework never loads it on a page.

## Common mistakes

+ **A `CREATE`, `ALTER` or `DROP` in a test** (G121): MySQL commits it and ends the test's transaction, so the rows
  of the test stay. Create tables in `create_db.sql` and migrations, before the tests.
+ **Tests on your development database**: they pass or fail with the rows you have. Give them `.env.testing`.
+ **`.env.testing` in git**: it holds your secrets. The project's `.gitignore` leaves out every `.env.*` but
  `.env.example` (2.19.1); a project made before 2.19.1 adds `/.env.*` and `!/.env.example` to its `.gitignore`.
+ **`migrate "" --env .env.testing`**: the empty `""` was needed before 2.19.1. Since 2.19.1, write
  `migrate --env .env.testing`.
+ **A test that reads `$_SESSION` or the request**: a capability never does; give the user with `$this->user()`.

## Related pages

+ [Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#test): capabilities
+ [Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection#test): fakes
+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs#test): `assertQueued()`
+ [Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy): the framework's own QA suite
+ [Caligrafer: test](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#test)
