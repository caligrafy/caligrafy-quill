# How Caligrafy works

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy is a PHP framework built on one idea: **build once, experience everywhere**. You write each feature of your application once, as a **capability**, and every way people and programs reach the application (web pages, Vue apps, a REST API, AI agents) calls that same capability. This page explains the few ideas behind it in about 5 minutes. To try it first, follow the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart).

You can build with Claude Code, which follows these ideas for you (`/caligrafy-quill:idea`, see the [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude)), or by hand, following [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability). Both produce the same project.

## A capability is a feature, written once

A **capability** is one thing your application can do: list the books, add a book, lend a book. It is a small PHP class in `application/services/` that says four things:

| What | Where in the class | Example |
| --- | --- | --- |
| its name and what it does | the `#[Capability]` attribute | `name: 'create_book'`, `description: 'Adds a book...'` |
| the inputs it accepts and their rules | `$rules` | `'title' => 'required\|max_len,200'` |
| who may use it | the `#[Capability]` attribute | `scope: 'books:write'`, `level: 1`, or `public: true` |
| the work | `handle()` | save the book, return it |

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

#[Capability(
    name: 'create_book',
    description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author.',
    scope: 'books:write'
)]
class CreateBook extends Service
{
    public $rules = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
        'author_id' => 'required|integer|exists,authors.id'
    );

    public function handle(array $input, Context $context)
    {
        $id = Book::insert($input);   // one INSERT, every value bound
        return Book::with('author')->find($id);
    }
}
```

A capability returns data, or refuses with a `ServiceError` (`invalid`, `unauthenticated`, `forbidden`, `not_found`, `conflict`). It does not draw a page, redirect or answer HTTP itself. That is what lets every experience use it. (A few requests only make sense in a browser, such as a sign-in between two sites: those are [web-only capabilities](#webonly).) Inside it, the [query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) reads the data: `Book::with('author')->where('author_id', 2)->paginate(20, $input['page'] ?? 1)`.

## Experiences call the capabilities

An **experience** is a way to reach your application. Each one is shaped for its audience, and each one calls the same capabilities. One line of `application/config/routing/web.php` connects each:

| Experience | Who uses it | How it reaches `create_book` |
| --- | --- | --- |
| Web pages (Pug) | people in a browser | the page gets its data from a read-only capability (`Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']])`), and its form posts to `Route::form('/books', 'create_book', ['success' => '/books/{id}'])` |
| Vue apps and page scripts | people on desktop, tablet and phone | `await createBook({ title, published_year, author_id })` from the generated JavaScript client (`php caligrafer.php client`, served by `Route::capabilities('/capabilities')`) |
| REST API | other programs | `POST /api/books` (`Route::capability('POST', '/api/books', 'create_book')`), or `POST /capabilities/create_book` |
| AI agents (MCP) | Claude, Cursor and other agents | the `create_book` tool (`Route::mcp('/mcp')`) |
| The command line and tests | you | `php caligrafer.php call create_book title=Kindred published_year=1979 author_id=2`, `$this->call('create_book', [...])` in a test |

The other needs of a web application are one line each, and call capabilities too ([Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes)): `Route::auth()` for sign-in, sign-out and sign-up, `Route::webhook()` for the events a payment provider posts, `Route::download()` for a CSV export, `Route::redirect()` for an address that moved, and `Route::action()` for whatever a page or a form cannot say. Every request of the application is declared this way: there is no controller to write. Work that runs later or on a timer is a capability too, pushed as a [background job](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) or named in a [schedule](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs).

### Web-only capabilities

Most features belong on every experience. A few belong to the browser: a sign-in between two sites, a face sign-in, a page that chooses where to go, a gate, a page that creates a payment. They need the session, or they answer with a page or a redirect. Such a capability is declared `webOnly: true`, and returns a `Caligrafy\Web`:

```php
#[Capability(name: 'open_author_books', description: 'Opens the books of an author.', readOnly: true, public: true, webOnly: true)]
class OpenAuthorBooks extends Service
{
    public $rules = array('author_id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        $books = $this->capability('list_books', array('author_id' => $input['author_id']), $context);
        return $books['total'] == 1 ? Web::redirect('/books/' . $books['data'][0]['id']) : Web::view('Bookshelf/books', array('books' => $books));
    }
}
```

`Route::action('/authors/{author_id}/books', 'open_author_books');` serves it, and **nothing else reaches it**: REST, MCP, the JavaScript client, the command line, `Route::page` and `Route::form` answer "There is no capability". It keeps the rules of every capability (who may call it, its inputs, its audit log). It may also read the session, sign people in, and change data on a `GET`, which is why it stays on the web. What other experiences need stays in plain capabilities, which it calls with `$this->capability()`.

## Who is calling

Every call knows who makes it, in its `Context`:

+ **People** sign in (password, passkey). A capability can ask for a minimum permission level, `level: 1`, read from the `permissions` column of `users`.
+ **Programs and AI agents** use a **personal access token** (`php caligrafer.php token create "my agent" --scopes books:read,books:write`). A token reaches only the capabilities its scopes allow, can expire, and can be revoked on its own.
+ **Public** capabilities (`public: true`) need neither: use it for data anyone may read.

## What happens on every call

Whatever the experience, every call goes through the same steps:

1. **Find** the capability (unknown: `404`).
2. **Check who calls**: signed in or a token (`401`), with the scope and the level (`403`), unless the capability is public.
3. **Check the inputs** against `$rules`, keeping only the declared inputs (`422`, with a message per field).
4. **Run** `handle()`.
5. **Record** it in the audit log: every change and every refusal (`php caligrafer.php audit`). Reads that succeed are not recorded.

So a book without an author gets `The Author Id field is required` on the web page, over REST and for an agent, and a token without `books:write` is refused everywhere.

## From your computer to the internet

The project you build is the project you put online, unchanged:

+ a production `Dockerfile` at its root builds it (PHP, Apache, your libraries, no development tools);
+ when the container starts, `php caligrafer.php deploy` creates the framework tables, runs every new or changed `create_db.sql`, then the [migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations) that never ran, and Apache starts;
+ `php caligrafer.php cloud deploy <name>` puts it at `https://<name>.caligrafy.com` with [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud), or you run the same image on [your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#ownserver).

Online, every experience calls the same capabilities with the same rules. With Claude, the whole path is three steps: **describe it** (the idea becomes a plan), **approve it** (your OK; Claude builds once and checks every experience) and **ship it** (GitHub and Caligrafy Cloud).

## Where things live

```text
application/
├── services/      the capabilities: each feature, once (Users/: the people who sign in)
├── models/        one class per table, with the create_db.sql that creates it
├── migrations/    changes to tables that hold data: numbered SQL files, each run once (php caligrafer.php migrate)
├── views/         the web pages (Pug), on the layout default/master/site
├── controllers/   the example controllers of a new project (core/); a new request never needs one
└── config/        routing/web.php: the routes; config.php: the site's settings;
                   schedule.php (scheduled jobs) and container.php (constructor bindings) when you add them
tests/             your tests (php caligrafer.php test)
Dockerfile         the production image (php caligrafer.php deploy runs when it starts)
public/
├── js/capabilities.js       the generated JavaScript client
├── css/                     Caligrafy's look: tokens.css (design tokens), layout.css, look.css, camera.css
└── <app>/                   Vue apps and single page applications
```

**One rule keeps every experience the same: only capabilities touch models.** Pages get their data from capabilities (`Route::page` with `data`, or the JavaScript client), forms post to capabilities (`Route::form`), custom requests are capabilities too (`Route::action`), REST and AI agents call capabilities. `Route::page` also gives every view who is signed in (`$authorized`, `$user`).

Caligrafy is also a classic MVC framework (routes, controllers, models, views). Existing controllers keep working: they call capabilities with `$this->call()`, and the [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers) page shows how to move one. New code does not need them: start with capabilities and the declarative routes, and use `Route::action` for what a page or a form cannot say.

## Related pages

+ [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) and [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude)
+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability): a complete feature, by hand
+ [Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): every option
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes) and [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries)
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)

>[Next: Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability)
