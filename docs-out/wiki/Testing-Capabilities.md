A capability is the one place where your app's rules live, so it is the first thing to test. A test calls it the way
a page, REST, the JS client or an agent does, with its permission checks, its validation and its audit, as a user, a
visitor, a token or the command line, and checks what it answers or how it refuses. Since 2.18.0.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability write the tests of create_book: the rule cases as a user, the refusals as a guest
```

Claude runs `php caligrafer.php test make <Name>`, fills `tests/<Name>Test.php` with `$this->call(...)` and
`$this->assertCapabilityFails(...)`, and runs `php caligrafer.php test <Name>`.

**Check** before you say OK:
+ every refusal the capability can give has its assertion (`invalid`, `unauthenticated`, `forbidden`, `not_found`,
  `conflict`);
+ each test creates the rows it needs;
+ `php caligrafer.php test` ends with `OK (...)`.

<br/>

## By hand

The tests run on a database of their own: set it up once as [The test database](https://github.com/caligrafy/caligrafy-quill/wiki/The-Test-Database)
shows. Then:

```bash
docker compose exec dev-box php caligrafer.php test make Bookshelf
```

and make `tests/BookshelfTest.php` call the capabilities of the Bookshelf (`create_book` needs someone signed in or a
token, `delete_book` has `level: 1`, `get_book` is public):

```php
<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;

final class BookshelfTest extends TestCase
{
    private function author(): int
    {
        // a row for this test only: the test's transaction is rolled back at its end
        return (int) Database::table('authors')->insert(array('name' => 'Ursula K. Le Guin'));
    }

    public function testAReaderAddsABook(): void
    {
        $book = $this->call('create_book', array('title' => 'Tehanu', 'published_year' => 1990, 'author_id' => $this->author()), $this->user(7));

        $this->assertSame('Tehanu', $book['title']);
        $this->assertSame('Ursula K. Le Guin', $book['author']['name']);
    }

    public function testRefusals(): void
    {
        $error = $this->assertCapabilityFails('invalid', 'create_book', array('title' => 'Tehanu'), $this->user(7));
        $this->assertArrayHasKey('published_year', $error->fields);

        $this->assertCapabilityFails('unauthenticated', 'create_book', array('title' => 'Tehanu'), 'guest');
        $this->assertCapabilityFails('forbidden', 'delete_book', array('id' => 1), $this->user(7, 0));
        $this->assertCapabilityFails('not_found', 'get_book', array('id' => 999999), 'guest');
    }
}
```

```bash
docker compose exec dev-box php caligrafer.php test Bookshelf
```

```text
OK (2 tests, 7 assertions)
```

(PHPUnit's header, the PHP version and the time it took are printed too.)

<br/>

## <a name="as"></a>Who calls: the third argument

`$this->call($capability, $input, $as)` builds the caller from `$as`:

| `$as` | The capability is called by | It may call |
| --- | --- | --- |
| `null`, or nothing | the command line (`Context::cli()`) | everything: full access |
| `'guest'` | a visitor over REST (`Context::guest('rest')`) | only `public: true` capabilities |
| `$this->user(7)` | the user 7 over REST, level 0 | what a signed-in user of that level may call |
| `$this->user(7, 1)` | the user 7, level 1 | also the capabilities with `level: 1` |
| an array or an object | that user (`id`, `permissions` and any attribute the capability reads) | as above |
| a `Caligrafy\Context` | exactly that context | what it allows |

`$this->user($id = 1, $level = 0, $attributes = [])` is a plain object: `id`, the level under
`Context::$levelAttribute` (`permissions` by default), and the attributes you add
(`$this->user(7, 0, ['username' => 'sam@example.com'])`). It is not read from the `users` table.

**As a token.** A token's scopes are checked only when the caller is a token. Create one in the test (it is rolled back
with the rest), then call as it:

```php
use Caligrafy\Context;
use Caligrafy\Tokens;

public function testATokenNeedsItsScope(): void
{
    $readOnly = Tokens::verify(Tokens::create('reports', array('books:read'))['token']);

    $this->assertCapabilityFails('forbidden', 'create_book', array('title' => 'Tehanu', 'published_year' => 1990, 'author_id' => 1), Context::forToken($readOnly, 'mcp'));
}
```

This needs the framework tables in the test database (`platform install --env .env.testing`).

<br/>

## <a name="refusals"></a>Refusals

`$this->assertCapabilityFails($kind, $capability, $input = [], $as = null)` passes when the capability throws a
`Caligrafy\ServiceError` of that kind, and returns it:

| Kind | Thrown when | Check also |
| --- | --- | --- |
| `invalid` | an input breaks a rule of `$rules`, or `ServiceError::invalid([...])` | `$error->fields`: one message per field |
| `unauthenticated` | nobody is signed in and the capability is not public | |
| `forbidden` | the level or the scope is missing, or `ServiceError::forbidden()` | |
| `not_found` | `ServiceError::notFound()`, or a capability this caller cannot reach (a web-only one) | `$error->getMessage()` |
| `conflict` | `ServiceError::conflict('...')`: the input is valid, the state refuses it | `$error->getMessage()` |

A capability that does not fail makes the test fail with ` <capability> did not fail (expected '<kind>')`; one that
fails with another kind says which: ` <capability> failed with 'forbidden' (...), not 'invalid'`. What each kind becomes on
each channel is in [`Caligrafy\ServiceError`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#serviceerror).

<br/>

## <a name="fakes"></a>Fakes and background jobs

**A fake.** A capability that asks the [container](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection)
for a service in its constructor (a clock, a mailer, a payment gateway) gets your object in this test:

```php
$this->fake(Clock::class, new class implements Clock {
    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-01');
    }
});
$loan = $this->call('lend_book', array('book_id' => $bookId, 'borrower' => 'Ana'), $this->user(7));
$this->assertSame('2026-11-15', $loan['due_on']);
```

The container's bindings are reset before each test, so a fake never leaks into the next one.

**A background job.** `$this->assertQueued($capability, $where = null)` passes when a job of that capability is pending
in `caligrafy_jobs` (pushed in this test, since the table is rolled back), and returns its input:

```php
// a capability whose handle() runs Queue::push('send_welcome_email', ['user_id' => $id]) was called above
$input = $this->assertQueued('send_welcome_email', fn ($input) => $input['user_id'] === 7);
```

The job does not run in the test: call its capability yourself to test what it does
([Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs#test)).

<br/>

## <a name="reference"></a>Reference

| Helper of `Caligrafy\Testing\TestCase` | What it does |
| --- | --- |
| `$this->call($capability, $input = [], $as = null)` | calls it through `Capabilities::call()`: checks, validation, audit; returns its answer |
| `$this->assertCapabilityFails($kind, $capability, $input = [], $as = null)` | the capability refuses with `$kind`; returns the `ServiceError` |
| `$this->user($id = 1, $level = 0, $attributes = [])` | a signed-in user for `$as` |
| `$this->fake($id, $object)` | the container gives `$object` for `$id` in this test |
| `$this->assertQueued($capability, $where = null)` | a job of the capability is pending; returns its input |
| `$this->action(...)`, `$this->render(...)` | web-only capabilities and views: [Testing pages, forms and actions](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Pages-Forms-and-Actions) |
| `protected $transactions = true` | `false` runs the test without a transaction ([The test database](https://github.com/caligrafy/caligrafy-quill/wiki/The-Test-Database)) |

Before each test, the container is reset and booted, the cache's in-memory copies and the query shapes of the N+1
warning are forgotten. The capability registry itself is in the
[API reference](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-call).

<br/>

## Common mistakes

+ **A test that counts on your rows**: it passes or fails with the data you have. Each test creates its rows.
+ **`$this->call()` on a web-only capability**: it answers `not_found`, as every other channel does. Use
  `$this->action()`.
+ **A test that reads `$_SESSION` or the request**: a plain capability never does; give the user with `$this->user()`.
+ **A token without `--user` passes any `level`** (G140): test administrator capabilities with a user of the right level
  and with a token, both.
+ **A custom validator of `config.php`** (G141): the tests, like the command line, do not load
  `application/config/config.php`.

<br/>

## Related pages

+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)
+ [Testing pages, forms and actions](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Pages-Forms-and-Actions) · [The test database](https://github.com/caligrafy/caligrafy-quill/wiki/The-Test-Database)
+ [Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection#test)
+ [Caligrafer: test](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Job-Commands#test)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
