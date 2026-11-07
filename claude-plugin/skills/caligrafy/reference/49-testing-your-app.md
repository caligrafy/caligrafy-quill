# Testing your app

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

Your app's **tests** check what it does through its capabilities, the way its pages, REST API, Vue apps and AI agents
reach them: with the permission checks, the validation and the audit. Since 2.18.0.

## Your first test

```bash
php caligrafer.php test make Books      # writes tests/BooksTest.php
php caligrafer.php test                 # runs every test of tests/
php caligrafer.php test Books           # only those whose name matches
```

```php
<?php

use Caligrafy\Testing\TestCase;

final class BooksTest extends TestCase
{
    public function testAReaderAddsABook(): void
    {
        $book = $this->call('create_book', ['title' => 'Dune'], $this->user(7));
        $this->assertSame('Dune', $book['title']);
        $this->assertContains('Dune', array_column($this->call('list_books', [], 'guest'), 'title'));
    }

    public function testRefusals(): void
    {
        $this->assertCapabilityFails('invalid', 'create_book', [], $this->user(7));
        $this->assertCapabilityFails('unauthenticated', 'create_book', ['title' => 'Dune'], 'guest');
    }
}
```

The tests use PHPUnit, which every project has with its development packages (`composer install`). In Docker:
`docker compose exec dev-box php caligrafer.php test`.

## What a test can do

| Helper | What it does |
|---|---|
| `$this->call($capability, $input, $as)` | calls a capability; `$as` is `null` (the command line: full access), `'guest'`, a user, or a `Context` |
| `$this->user($id, $level, $attributes)` | a signed-in user for `$as`: an id, a permission level, other attributes the capability reads |
| `$this->assertCapabilityFails($kind, $capability, $input, $as)` | the capability refuses: `invalid`, `unauthenticated`, `forbidden`, `not_found` or `conflict`; returns the error |
| `$this->render($view, $data)` | the HTML of a Pug view, to check a page |
| `$this->fake($class, $object)` | the [container](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection) gives `$object` instead of `$class` (a mailer, a payment gateway) |
| `$this->assertQueued($capability)` | a [background job](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) was pushed; returns its input |

## The database

With `DB_ACTIVATE=true`, each test runs in a transaction that is rolled back at its end: tests leave the database as
they found it, and do not see each other's rows.

+ Give the tests a database of their own: `DB_TEST_DATABASE=caligrafy_test` in `.env` (create it, then run
  `php caligrafer.php platform install` and your migrations on it), or a `.env.testing` file with the settings the
  tests use instead of `.env`.
+ A `CREATE`, `ALTER` or `DROP` inside a test ends its transaction (MySQL commits it): create tables with migrations,
  not in tests. A test that must not run in a transaction sets `protected $transactions = false;`.

## Where tests live

`tests/` at the top of the project, one class per file named `*Test.php`. The production image leaves `tests/` out, and
the framework never loads it on a page.
