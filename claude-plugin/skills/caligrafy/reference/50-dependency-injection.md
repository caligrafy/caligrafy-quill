# Dependency injection

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Dependency injection** means a class asks for what it uses in its constructor instead of creating it, and
Caligrafy's **container** gives it. A capability then stays short, the real thing (a clock, a mailer, a payment
gateway) is chosen in one file, and a test can swap it for a fake. Since 2.18.0.

## With Claude

Say what the capability uses from outside, and that a test must be able to replace it:

```text
/caligrafy-quill:new-capability lend_book loans:write: lend a book for a number of days (14 by default) from today; take the date from a Clock so a test can fix it
```

Claude gives the capability a constructor (`public function __construct(private Clock $clock) {}`), binds the
interface in `application/config/container.php`, and writes a test that replaces it with `$this->fake()`.

**Check** before you say OK:
+ `handle()` never writes `new SystemClock()` (or `new` of anything with settings): it uses what the constructor got;
+ every interface the constructor asks for is bound in `application/config/container.php`;
+ `php caligrafer.php call lend_book book_id=2 borrower=Dan` works, and `php caligrafer.php test` passes.

## By hand

A loan is due a number of days after today, 14 by default (`lend_book` of the course, with a clock). "Today" comes
from a `Clock`, so a test can choose the date. Run the commands in
the Docker container (`docker compose exec dev-box php caligrafer.php ...`; without Docker, drop
`docker compose exec dev-box`).

1. **The interface.** Create `application/support/Clock.php`:
    ```php
    <?php

    // What time it is: the capabilities ask for a Clock, so a test can give them a fixed one
    interface Clock
    {
        public function today(): DateTimeImmutable;
    }
    ```
2. **The real one.** Create `application/support/SystemClock.php`:
    ```php
    <?php

    // The real clock: today, in the time zone of the application (SERVER_TIMEZONE)
    class SystemClock implements Clock
    {
        public function today(): DateTimeImmutable
        {
            return new DateTimeImmutable('today');
        }
    }
    ```
    Any folder of `application/` but `config/` and `views/` is found by the class map: no `require` to write.
3. **The binding.** Create `application/config/container.php`:
    ```php
    <?php

    use Caligrafy\Container;

    // What the container gives a class that asks for an interface (or for a class that needs settings)
    Container::bind(Clock::class, SystemClock::class);
    ```
4. **The capability asks for it.** `lend_book` is the capability of the course's loans
   ([lesson 13](https://github.com/caligrafy/caligrafy-quill-course/wiki/13-Capstone-Loans): the `loans` table and the
   `Loan` model). Without it, run
   `docker compose exec dev-box php caligrafer.php service Loans/LendBook --scope loans:write`. Then make
   `application/services/Loans/LendBook.php` read (the course's, with the date from the clock):
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Service;
    use Caligrafy\ServiceError;

    // lend_book: records that someone borrowed a book, for a number of days counted from the Clock's today
    #[Capability(
        name: 'lend_book',
        description: 'Lends a book of the catalog to someone for a number of days (14 by default) and returns the loan. Refused when the book is already lent.',
        scope: 'loans:write'
    )]
    class LendBook extends Service
    {
        public $rules = array('book_id' => 'required|integer', 'borrower' => 'required|max_len,100', 'days' => 'integer|min_numeric,1|max_numeric,60');
        public $filters = array('borrower' => 'trim');
        public $descriptions = array(
            'book_id' => 'The id of the book (list_books)',
            'borrower' => 'The name of the person who borrows it',
            'days' => 'For how many days: 1 to 60, 14 by default'
        );

        // the container gives the Clock bound in application/config/container.php
        public function __construct(private Clock $clock)
        {
        }

        public function handle(array $input, Context $context)
        {
            if (!Book::find($input['book_id'])) {
                throw ServiceError::notFound("There is no book #{$input['book_id']}");
            }
            $open = Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->first();
            if ($open) {
                throw ServiceError::conflict("This book is already lent to {$open->borrower}");
            }
            $days = (int) ($input['days'] ?? 14);
            $id = Loan::insert(array(
                'book_id' => $input['book_id'],
                'borrower' => $input['borrower'],
                'due_on' => $this->clock->today()->modify("+$days days")->format('Y-m-d')
            ));
            return Loan::find($id);
        }
    }
    ```
5. **Run it.** `docker compose exec dev-box php caligrafer.php call lend_book book_id=2 borrower=Dan` returns the loan
   with `"due_on"` 14 days from today (`"2026-11-21"` on 7 November 2026). Run it again: the book is out,
   `{"error": {"code": "conflict", "message": "This book is already lent to Dan"}}`.
6. **In a test, a fixed clock.** See step 3 of [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App#first):
    ```php
    $this->fake(Clock::class, new class implements Clock {
        public function today(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-11-01');
        }
    });
    $loan = $this->call('lend_book', ['book_id' => $bookId, 'borrower' => 'Ana'], $this->user(7));
    $this->assertSame('2026-11-15', $loan['due_on']);   // 14 days after the fixed today
    ```

**Check:**
+ `call lend_book` works with the binding;
+ remove `application/config/container.php` for a moment: `call lend_book ...` then says
  `The container cannot build Clock: it is an interface. Bind it to a class in application/config/container.php`.
  Put it back.

## Reference

Every capability is built by the container, with what its constructor asks for. A class type in a constructor is
built the same way, with what its own constructor asks for; a parameter with a default keeps it.

| Method (in `application/config/container.php`) | What it does |
|---|---|
| `Container::bind($id, $factory)` | a new object each time; `$factory` is a class name or a function that returns the object |
| `Container::singleton($id, $factory)` | one object for the whole request, made the first time it is asked for |
| `Container::instance($id, $object)` | this object |

```php
Container::bind(Clock::class, SystemClock::class);                                               // an interface -> a class
Container::singleton(PaymentGateway::class, fn () => new StripeGateway(getenv('PAY_PRIVATE_KEY_TEST')));   // your own class, with settings
```

| Method (anywhere) | What it does |
|---|---|
| `Container::get($id)` | the object for `$id`: its instance, its binding, or the class built with what it asks for |
| `Container::make($class, ['name' => $value])` | a new `$class` (never a shared one), some constructor parameters given by name |
| `Container::has($id)` | whether `$id` has a binding or an instance |
| `Container::reset()` | forgets every binding and instance (the tests do it between tests) |

How a constructor parameter is given: by name from `make()`'s parameters, else by its class or interface type, else
its default, else `null` when it accepts `null`. The file `application/config/container.php` is read once: by the web
front controller after `config.php`, by the command line and the tests the first time the container is used.

**What it says when it cannot build a class:**

| Cause | Message |
|---|---|
| an interface with no binding | `The container cannot build Clock: it is an interface. Bind it to a class in application/config/container.php` |
| a parameter with no type and no default | `The container cannot build NeedsName: nothing gives its parameter $name. Give it a default, a class type, or bind it in application/config/container.php` |
| a class that needs itself | `The container cannot build A: it needs itself (A -> B -> A)` |
| an abstract class, or a constructor that is not public | `The container cannot build X: it is abstract or its constructor is not public` |

Over REST the call answers 500, over MCP a JSON-RPC `Internal error` (-32603); the audit log records `error  LogicException`.

## In tests

`$this->fake(Clock::class, $object)` gives `$object` to every class that asks for `Clock`, for this test only: the
container is reset before and after each test, and the bindings of `application/config/container.php` are read
again first, so the fake replaces them. See [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App#helpers).

## Common mistakes

+ **`new` in `handle()`** for something with settings or side effects (a mailer, a payment client): a test cannot
  replace it. Ask for it in the constructor.
+ **An interface with no binding**: the capability fails at its first call. Bind it in `application/config/container.php`.
+ **A binding written in `web.php`**: the command line, the queue and the tests never read `web.php`. Write bindings in
  `application/config/container.php`.

## Related pages

+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App): `$this->fake()`
+ [Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service): capabilities
+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs): jobs are capabilities too, built the same way
