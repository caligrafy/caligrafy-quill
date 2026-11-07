# Dependency injection

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Dependency injection** means a class asks for what it uses in its constructor, instead of creating it. Caligrafy's
container gives it. Your capabilities stay short, and a test can swap a real mailer or payment gateway for a fake.
Since 2.18.0.

## Asking for what a capability uses

```php
#[Capability(name: 'list_books', description: 'The books of the shelf', readOnly: true)]
class ListBooks extends Service
{
    public function __construct(private Shelf $shelf, private Clock $clock) {}

    public function handle(array $input, Context $context)
    {
        return $this->shelf->all($this->clock->today());
    }
}
```

Every capability is made by the container. A class type in its constructor (`Shelf`) is built the same way, with what
its own constructor asks for. A parameter with a default keeps it.

## Bindings

An interface, or a class that needs settings, is bound in **`application/config/container.php`**:

```php
<?php
use Caligrafy\Container;

Container::bind(Clock::class, SystemClock::class);                       // an interface -> a class
Container::singleton(PaymentGateway::class, fn () => new StripeGateway(getenv('STRIPE_SECRET')));   // one per request
```

| Method | What it does |
|---|---|
| `Container::bind($id, $factory)` | a new object each time; `$factory` is a class name or a function returning the object |
| `Container::singleton($id, $factory)` | one object, made the first time it is asked for |
| `Container::instance($id, $object)` | this object |
| `Container::get($id)` / `Container::make($class, $parameters)` | the object for `$id` / a new `$class`, some constructor parameters given by name |

The container says what is missing when it cannot build a class: an interface with no binding, a parameter with no
type and no default, or a class that needs itself.

## In tests

```php
$this->fake(Mailer::class, new FakeMailer());     // for this test only
$this->call('invite_member', ['email' => 'ana@example.com']);
```

See [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App).
