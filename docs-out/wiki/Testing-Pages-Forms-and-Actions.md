Pages, forms, downloads and web-only actions are thin in Caligrafy: a declared route hands the request to a capability
and renders, redirects or sends what it answers. So a test checks the two parts on their own: the **capability** that
does the work, and the **view** or the **answer** the browser gets. This page shows how, with the helpers that
`Caligrafy\Testing\TestCase` really has. Since 2.18.0 (`action()` since 2.21.0).

> `TestCase` sends no HTTP request: there is no `get('/books')` helper. What a route adds (the sign-in redirect, the CSRF
> check, the flash messages) is the framework's, tested by its own QA suite; check your wiring once in the browser.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability write the tests of the book page: get_book as a guest, then the view with its data
```

**Check** before you say OK: the test renders the view with the same data the page's `data` option gives it, and
`php caligrafer.php test` ends with `OK (...)`.

<br/>

## By hand

The database of the tests is set up once: [The test database](https://github.com/caligrafy/caligrafy-quill/wiki/The-Test-Database).

<br/>

## <a name="page"></a>A page: `Route::page`

```php
Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors']]);
```

The page calls `get_book` and `list_authors` as the visitor, then renders `application/views/Bookshelf/book.pug` with
`book` and `authors`. Test the same two steps:

```php
public function testTheBookPage(): void
{
    $authorId = (int) Database::table('authors')->insert(array('name' => 'Ursula K. Le Guin'));
    $book = $this->call('create_book', array('title' => 'Tehanu', 'published_year' => 1990, 'author_id' => $authorId));

    $data = array(
        'book' => $this->call('get_book', array('id' => $book['id']), 'guest'),
        'authors' => $this->call('list_authors', array(), 'guest'),
    );
    $html = $this->render('Bookshelf/book', $data);

    $this->assertStringContainsString('Tehanu', $html);
    $this->assertStringContainsString('by Ursula K. Le Guin, 1990', $html);
}
```

+ `$this->render($view, $data)` returns the HTML of `application/views/<view>.pug`, as `View::display()` prints it.
+ On a real page the view also gets `authorized` (`true` when someone is signed in) and `user` (`id`, `username`,
  `permissions`) from the page controller, and the route's variables. A view that reads them needs them in `$data`:
  `$this->render('Bookshelf/book', $data + ['authorized' => false, 'user' => null])`.

<br/>

## <a name="form"></a>A form: `Route::form`

```php
Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
```

`Route::form` gives the capability the form's inputs and the route variables, as the person who sent it. Test the
capability with what the form sends (strings, as a form posts them), as the person who sends it:

```php
private function author(): int
{
    return (int) Database::table('authors')->insert(array('name' => 'Ursula K. Le Guin'));
}

public function testTheBookForm(): void
{
    $book = $this->call('create_book', array('title' => 'Tehanu', 'published_year' => '1990', 'author_id' => (string) $this->author()), $this->user(7));
    $this->assertSame('Tehanu', $book['title']);                 // the form redirects to /books/<this id>

    $error = $this->assertCapabilityFails('invalid', 'create_book', array('title' => ''), $this->user(7));
    $this->assertArrayHasKey('title', $error->fields);           // the form shows it with errors('title')
}
```

What the form then does with the answer, by kind, is the route's: `invalid` and `conflict` go back with `errors()`,
`old()` and `flash('error')`, `unauthenticated` goes to the sign-in page. Test the view's error state with `render()`
and the data the view reads. The options are on
[Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form).

<br/>

## <a name="download"></a>A download: `Route::download`

`Route::download('/books.csv', 'list_books', ['as' => 'csv'])` sends the result of a read-only capability as a file. The
file is made by public functions of [`Caligrafy\Download`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#download),
so a test can make the same CSV:

```php
use Caligrafy\Download;

public function testTheCatalogExport(): void
{
    $this->call('create_book', array('title' => 'Tehanu', 'published_year' => 1990, 'author_id' => $this->author()));
    $csv = Download::csv(Download::rows($this->call('list_books', array(), 'guest')));

    $this->assertStringContainsString('Tehanu', $csv);   // the header row is the keys of the first row
}
```

`Download::rows()` takes what the capability returns (a list, one row, a page of `paginate()`), `Download::csv()` writes
RFC 4180 lines with CRLF; its arguments are the `bom`, `formulas` and `columns` options of the route.

<br/>

## <a name="action"></a>A web-only capability: `Route::action`

A capability declared `webOnly: true` answers only `Route::action`: `$this->call()` gets `not_found`, as every other
channel. `$this->action($capability, $input, $as)` calls it as `Route::action` does, and returns what it answered: a
[`Caligrafy\Web`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#web), or plain data.

`open_author_books` of [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action)
redirects to the book when an author has one, and shows the list otherwise:

```php
public function testAnAuthorWithOneBookOpensIt(): void
{
    $authorId = $this->author();
    $book = $this->call('create_book', array('title' => 'Tehanu', 'published_year' => 1990, 'author_id' => $authorId));

    $answer = $this->action('open_author_books', array('author_id' => $authorId), 'guest')->describe();

    $this->assertSame('redirect', $answer['type']);
    $this->assertSame('/books/' . $book['id'], $answer['target']);
    $this->assertSame(302, $answer['status']);
}
```

`->describe()` gives `type` (`view`, `redirect`, `json` or `text`), `target` (the view, or where a redirect goes),
`data`, `status` and `headers`.
`action()` runs `handle()` without a request: a capability that calls `request()`, reads the session or signs someone
in is tested through the plain capabilities it calls, and in the browser.

<br/>

## <a name="snap"></a>Snap Cards and the assistant

Snap's capabilities are capabilities: the tables must be in the test database (`platform install` on it). The
assistant's planner is faked with `Caligrafy\Snap\Planner::$transport`, a function that gets the request and returns
the model's answer:

```php
Caligrafy\Snap\Planner::$transport = function (array $request) {
    return (object) array('stopReason' => 'tool_use', 'content' => array((object) array('type' => 'tool_use', 'id' => 't1',
        'name' => 'show_cards', 'input' => array('caption' => 'Here it is.', 'cards' => array(array('capability' => 'list_books'))))));
};
$answer = $this->action('snap_ask', array('text' => 'show me the catalog'), $this->user(1))->describe()['data'];
```

The designed cards have their own, `Caligrafy\Snap\Designer::$transport`, or keep to the rules with
`Caligrafy\Snap\Designer::$model = false` (2.24.1). See
[Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#test).

<br/>

## Common mistakes

+ **Testing the HTML of a whole page for a rule**: the rule is in the capability. Assert it there; keep `render()` for
  what the view shows.
+ **A view that reads `user` or `authorized` fails in a test**: give them in `$data`, as the page controller does.
+ **`$this->call()` on a web-only capability**: `not_found`. Use `$this->action()`.
+ **Form values as numbers**: a form sends strings. Test with strings when the rules must accept them.

<br/>

## Related pages

+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)
+ [Testing capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Capabilities) · [The test database](https://github.com/caligrafy/caligrafy-quill/wiki/The-Test-Database)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes)
+ [API reference: controllers and views](https://github.com/caligrafy/caligrafy-quill/wiki/API-Pages)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
