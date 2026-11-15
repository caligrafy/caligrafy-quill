The channels call capabilities for you: a page, a form, REST, MCP. Sometimes your own code calls one: a script, a
capability that builds on another, a controller you kept, a job, a test. This page shows each way, and what each one
checks. Since 2.1.0 (`$this->capability()` 2.21.0).

<br/>

## <a name="ways"></a>The ways, side by side

| From | Call | Who it runs as | Checks, validation, audit |
| --- | --- | --- | --- |
| a terminal | `php caligrafer.php call get_book id=3` | full access (`--token`: a token, `--guest`: a visitor) | all three |
| any PHP code | `Capabilities::call('get_book', ['id' => 3], $context)` | the context you give | all three |
| a capability | `$this->capability('list_books', $input, $context)` | the same person | all three |
| a controller | `$this->call('create_book')` | the signed-in person (`Context::fromRequest($request, 'web')`) | all three |
| later, in the background | `Queue::push('send_loan_receipt', ['loan_id' => $id])` | the queue's system context | all three, when the worker runs it |
| a test | `$this->call('create_book', $input, $this->user(7))` | the user you give, or `'guest'` | all three, rolled back after the test |
| PHP, without the checks | `Container::make(GetBook::class)->run(['id' => 3])` | the command line, unless you pass a context | validation only: no access check, no audit |

<br/>

## <a name="cli"></a>From the command line

```bash
php caligrafer.php call list_books
php caligrafer.php call get_book id=3
php caligrafer.php call create_book '{"title": "Dawn", "published_year": 1987, "author_id": 2}'
php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2 --guest          # as a visitor who is not signed in
php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2 --token cq_...   # as a token (or CALIGRAFY_TOKEN=cq_...)
```

It prints the same JSON as REST: `{"data": ...}` with exit code `0`, or `{"error": {...}}` with exit code `1`. Without
`--token` or `--guest` it has full access, like any script you run (channel `cli` in the audit log).
`php caligrafer.php capabilities` lists what there is to call.

<br/>

## <a name="php"></a>From PHP: `Capabilities::call()`

```php
use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\ServiceError;

try {
    $book = Capabilities::call('get_book', ['id' => 3], Context::cli());
} catch (ServiceError $e) {
    echo json_encode($e->toArray());
}
```

`Capabilities::call($name, $input, $context, $uploads = null)` does what every channel does, in order: finds the
capability, checks the context, keeps, filters and validates the inputs, runs `handle()`, writes the audit log, and
returns plain data (models become arrays of their columns). Extra input keys are ignored. Choose the context for who
acts ([The context](https://github.com/caligrafy/caligrafy-quill/wiki/The-Context)): `Context::cli()` for full access,
`Context::system('schedule', 'nightly-report')` for work the system does on its own, `Context::forToken($row)` or
`Context::guest()` to act as a token or a visitor.

<br/>

## <a name="capability"></a>From another capability: `$this->capability()`

```php
public function handle(array $input, Context $context)
{
    $books = $this->capability('list_books', array('author_id' => $input['author_id']), $context);
    // ...
}
```

It runs the other capability **as the same person**, with its checks, its validation and its audit, and throws its
`ServiceError`. This is how a [web-only capability](https://github.com/caligrafy/caligrafy-quill/wiki/Web-Only-Capabilities)
reuses the work of plain ones.

<br/>

## <a name="controller"></a>From a controller you kept: `$this->call()`

```php
try { $book = $this->call('create_book'); redirect('/books/' . $book['id']); }
catch (ServiceError $e) { return view('Books/form', ['errors' => $e->fields]); }
```

Without inputs it sends the route variables and the request's parameters (the parameters win on the same name). It runs
as the signed-in person, on the `web` channel. New code does not need a controller:
[Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers) shows how to move one to a declared route.

<br/>

## <a name="run"></a>Without the checks: `Service::run()`

```php
use Caligrafy\Container;

$book = Container::make(GetBook::class)->run(['id' => 3]);
```

`run($input, $context = null)` validates the inputs and calls `handle()`, with `Context::cli()` when you give none. It
checks no access and writes no audit, and it returns what `handle()` returns (a model stays a model). `Container::make()`
builds the class with what its constructor asks for
([Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection)).

Reference: [`Capabilities::call()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-call),
[`Service::run()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#service-run),
[`Controller::call()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Controller#controller-call),
[`Container::make()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#container-make).

<br/>

## Common mistakes

+ **A web-only capability from code** (G143): `Capabilities::call()`, `$this->call()` and `call` answer `not_found`; only
  `Route::action` reaches it.
+ **A job capability anyone may call**: the queue runs it with full access, so it checks `$context->actorType()`
  ([Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)).

<br/>

## Related pages

+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App): `$this->call()`, `$this->action()`, `assertCapabilityFails()`
+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) · [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [Caligrafer: `call`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#call)
