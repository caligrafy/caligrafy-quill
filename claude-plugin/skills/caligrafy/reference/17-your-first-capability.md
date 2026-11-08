# Your first capability

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

In this tutorial you add a **Bookshelf** to the project of the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart): an `authors` and a `books` table, four capabilities (list the books, get one, list the authors, add a book), a page with a form, a REST API, tools for AI agents, sign-in, a test and a migration. It is the Bookshelf of the [course](https://github.com/caligrafy/caligrafy-quill-course), with the same tables and the same code. About 30 minutes. Every command runs in the project folder, with the Docker server of the Quickstart running.

A **capability** is one feature of the application, written once as a PHP class: its inputs and their rules, who may use it, and the work. Pages, REST, the JavaScript client and AI agents all call the same capability.

## With Claude

```text
/caligrafy-quill:new-resource Author name:string bio:text
/caligrafy-quill:new-resource Book title:string published_year:int summary:text author:fk
```

For each entity, the `new-resource` skill writes the table script (`application/models/Book/create_db.sql`, the parent table first, with the foreign key `author_id`), the model, the five capabilities `list_books`, `get_book`, `create_book`, `update_book` and `delete_book` in `application/services/Books/` (scopes `books:read` and `books:write`), their REST routes, the pages with `Route::page` and the forms with `Route::form`. It then offers to run the SQL (`/caligrafy-quill:db`), calls each capability and asks the `caligrafy-reviewer` agent to review the files. The skill keeps one folder per entity (`models/Book/`, `services/Books/`, `views/Books/`); the steps below keep the whole Bookshelf in `Bookshelf/` folders, as the course does: both work. For one feature at a time, `/caligrafy-quill:new-capability create_book books:write` does the same for one capability and checks that every channel answers and refuses the same way.

**Check** before you say OK:
+ `docker compose exec dev-box php caligrafer.php capabilities` lists the new capabilities with the scopes you expect (`books:read`, `books:write`) and `read` only on the reads.
+ `author_id` has the rule `exists,authors.id`: a book with an unknown author is refused with a message, not a database error.
+ No capability that changes data is `public: true`, unless you asked for it.
+ `web.php` has no controller: the forms are `Route::form` lines.
+ The page opens, and an empty form shows a message under each required field.

## By hand

In Docker, every `php caligrafer.php` command runs as `docker compose exec dev-box php caligrafer.php ...`. On your own PHP, run `php caligrafer.php ...` in the project folder.

### 1. The tables and their models

A **model** is a PHP class for one table, with one public property per column. Create the folder `application/models/Bookshelf` and three files in it.

**`application/models/Bookshelf/create_db.sql`**
```sql
/* Bookshelf: the first version of its tables, and sample books.
 * Run it with: docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
 * Safe to run again: CREATE TABLE IF NOT EXISTS skips a table that exists, INSERT IGNORE a row whose id exists.
 * Never edit it once it ran on a database with data: change a table with a migration (step 9).
 */

CREATE TABLE IF NOT EXISTS authors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    bio TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    published_year SMALLINT UNSIGNED NOT NULL,
    summary TEXT NULL,
    author_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_books_author_id (author_id),
    CONSTRAINT fk_books_author_id FOREIGN KEY (author_id) REFERENCES authors (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Sample data, with fixed ids so that book 3 is Kindred everywhere
INSERT IGNORE INTO authors (id, name, bio) VALUES
    (1, 'Ursula K. Le Guin', 'American author of science fiction and fantasy, known for the Earthsea books.'),
    (2, 'Octavia E. Butler', 'American science fiction author, the first to receive a MacArthur fellowship.'),
    (3, 'Italo Calvino', 'Italian writer of playful, inventive novels and short stories.');

INSERT IGNORE INTO books (id, title, published_year, summary, author_id) VALUES
    (1, 'A Wizard of Earthsea', 1968, 'A gifted boy learns magic and must face the shadow he set loose.', 1),
    (2, 'The Left Hand of Darkness', 1969, 'An envoy visits a planet whose people have no fixed sex.', 1),
    (3, 'Kindred', 1979, 'A young woman is pulled back in time to a plantation in Maryland.', 2),
    (4, 'Parable of the Sower', 1993, 'In a collapsing California, a teenager starts a new belief system.', 2),
    (5, 'Invisible Cities', 1972, 'Marco Polo describes imaginary cities to the emperor Kublai Khan.', 3);
```

**`application/models/Bookshelf/Author.php`**
```php
<?php

use Caligrafy\Model;

// One Author = one row of the authors table (Author::query() reads authors: the class name in lower case, plus s)
class Author extends Model {

    // one public property per column of the table
    public $id;
    public $name;
    public $bio;
    public $created_at;
    public $modified_at;
}
```

**`application/models/Bookshelf/Book.php`**
```php
<?php

use Caligrafy\Model;

// One Book = one row of the books table. Book::with('author') adds the row of authors whose id is author_id
class Book extends Model {

    // one public property per column of the table
    public $id;
    public $title;
    public $published_year;
    public $summary;
    public $author_id;
    public $created_at;
    public $modified_at;
}
```

Run the script:
```bash
docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
```
```text
 application/models/Bookshelf/create_db.sql run on caligrafy
```
The primary key is always `id`. `Book::query()` reads the table `books` and `Author::query()` the table `authors`: the class name in lower case plus `s`. The foreign key `author_id` links a book to its author, and `Book::with('author')` reads that author with the book. Online, `php caligrafer.php deploy` runs every new or changed `create_db.sql` when the app starts. A later change to a table that holds data is a migration: see [step 9](#migration).

### 2. Four capabilities

Caligrafer writes each class, ready to fill:
```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Bookshelf/GetBook --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Bookshelf/ListAuthors --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Bookshelf/CreateBook --scope books:write
```
Each prints:
```text
 Created application/services/Bookshelf/ListBooks.php: capability 'list_books' (scope books:read)
 Fill in $rules and handle(), then serve it: a page (Route::page data), a form (Route::form), REST (Route::capability or Route::capabilities), MCP (Route::mcp)
```
The capability's name is the snake_case of the class name. Replace the content of the four files.

**`application/services/Bookshelf/ListBooks.php`**
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_books: the catalog, sorted by title, 20 books per page, each with its author. Anyone may read it
#[Capability(
    name: 'list_books',
    description: 'Lists the books of the Bookshelf catalog, sorted by title, 20 per page, each with its author (id, name, bio). Give author_id to list only the books of one author, and page for the next pages. Returns data (the books), total, page, per_page and pages.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListBooks extends Service
{
    public $rules = array('author_id' => 'integer', 'page' => 'integer');
    public $descriptions = array(
        'author_id' => 'Optional: only the books of this author',
        'page' => 'Optional: the page, 1 by default (20 books per page)'
    );

    public function handle(array $input, Context $context)
    {
        // two queries for the page (the count, then the books) and one for all their authors
        $query = Book::with('author')->orderBy('title');
        if (!empty($input['author_id'])) {
            $query->where('author_id', $input['author_id']);   // the value is bound, never pasted into the SQL
        }
        return $query->paginate(20, $input['page'] ?? 1);
    }
}
```

**`application/services/Bookshelf/GetBook.php`**
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// get_book: one book with its author, or "not found"
#[Capability(
    name: 'get_book',
    description: 'Returns one book of the catalog with its author (id, name, bio), by the id of the book.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class GetBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book');

    public function handle(array $input, Context $context)
    {
        $book = Book::with('author')->find($input['id']);
        if (!$book) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        return $book;
    }
}
```

**`application/services/Bookshelf/ListAuthors.php`**
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_authors: every author, sorted by name. Anyone may read it
#[Capability(
    name: 'list_authors',
    description: 'Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListAuthors extends Service
{
    public function handle(array $input, Context $context)
    {
        return Author::query()->orderBy('name')->get();
    }
}
```

**`application/services/Bookshelf/CreateBook.php`**
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// create_book: adds a book, with the same rules whoever calls it
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id.',
    scope: 'books:write',
    public: true   // for now: step 7 closes it
)]
class CreateBook extends Service
{
    public $rules = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
        'summary' => 'max_len,2000',
        'author_id' => 'required|integer|exists,authors.id'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim');
    public $descriptions = array(
        'title' => 'The title of the book',
        'published_year' => 'The year it was first published, for example 1969 (not after this year)',
        'summary' => 'Optional: one or two sentences about the book',
        'author_id' => 'The id of its author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        $id = Book::insert($input);   // one INSERT, every value bound; returns the id of the new book
        return Book::with('author')->find($id);
    }
}
```

What each part does:
+ `description` is what an AI agent reads to choose the tool: say what it does and what it returns.
+ `$rules` lists every input the capability accepts, with its [validation rules](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation). Only these inputs reach `handle()`. `exists,authors.id` refuses an author that is not in `authors`; `max_numeric,current_year` refuses a year after this one. `$filters` clean the inputs first; `$descriptions` describe them to clients and agents.
+ `scope` is what a token needs. `readOnly: true` marks a capability that changes nothing (only those can feed a page). `public: true` lets anyone call it without signing in: right for the reads, and for `create_book` only until [step 7](#secure).
+ `handle()` reads and writes with the [query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) (`Book::with('author')`, `Book::find()`, `Book::insert()`), every value bound, and returns data. It never draws a page, redirects or prints. It refuses with a `ServiceError` (`notFound`, `invalid`, `forbidden`, `conflict`).
+ `paginate(20, $input['page'] ?? 1)` returns `data` (the rows), `total`, `page`, `per_page` and `pages`. Always pass the page: REST, MCP and the command line have no query string.

Try them before any route:
```bash
docker compose exec dev-box php caligrafer.php capabilities
```
```text
 create_book                  public         writes    Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id.
 get_book                     public         read      Returns one book of the catalog with its author (id, name, bio), by the id of the book.
 list_authors                 public         read      Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.
 list_books                   public         read      Lists the books of the Bookshelf catalog, sorted by title, 20 per page, each with its author (id, name, bio). Give author_id to list only the books of one author, and page for the next pages. Returns data (the books), total, page, per_page and pages.
```
(with the Tasks, Users and notification capabilities around them).
```bash
docker compose exec dev-box php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2
```
```json
{
    "data": {
        "id": 6,
        "title": "Dawn",
        "published_year": 1987,
        "summary": null,
        "author_id": 2,
        "created_at": "2026-11-08 00:13:48",
        "modified_at": "2026-11-08 00:13:48",
        "author": {
            "id": 2,
            "name": "Octavia E. Butler",
            "bio": "American science fiction author, the first to receive a MacArthur fellowship.",
            "created_at": "2026-11-08 00:13:35",
            "modified_at": "2026-11-08 00:13:35"
        }
    }
}
```
```bash
docker compose exec dev-box php caligrafer.php call create_book title=Dawn
```
```json
{
    "error": {
        "code": "invalid",
        "message": "Some of the inputs are invalid",
        "fields": {
            "published_year": "The Published Year field is required",
            "author_id": "The Author Id field is required"
        }
    }
}
```
`call create_book title=Dawn published_year=1987 author_id=9` answers the field `"author_id": "There is no such author"`, `call get_book id=99` answers `{"error": {"code": "not_found", "message": "There is no book #99"}}`, and `call list_books author_id=2` lists the books of Octavia E. Butler, Dawn included.

### 3. A page with a form

Add these lines to `application/config/routing/web.php`, inside the `try` block, above the comment `// MUST NOT BE REMOVED - Routes need to be run after being defined` and its `Route::run();`:
```php
    // BOOKSHELF PAGES: rendered on the server with the data of the capabilities; the form calls create_book
    Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books', 'authors' => 'list_authors'], 'query' => ['author_id']]);
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book'], 'notFound' => 'Bookshelf/notfound']);
```
+ `Route::page` shows a view with the results of read-only capabilities: in the view, `$books` is the answer of `list_books` and `$authors` the answer of `list_authors`, as arrays. The query string becomes inputs (`/books?author_id=2`, `?page=2`), and `query` gives the view `$author_id` (`''` when absent). On `/books/{id}`, the `id` of the address is the input of `get_book`; an unknown id renders the `notFound` view with status `404`.
+ `Route::form` takes the form's `POST`, checks its CSRF token, calls `create_book` with its fields as the person who sent it, and redirects to `success` (`{id}` comes from the new book) with the `flash` message. Refused input goes back to the form with a message per field ([Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form)).

Create the folder `application/views/Bookshelf` and three views. Views are [Pug](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views) in PHP mode: variables start with `$`, and strings join with `.`.

**`application/views/Bookshelf/books.pug`**
```pug
extends ../default/master/site

//- The catalog: Route::page gives it $books (list_books: a page of books) and $authors (list_authors).
//- The form posts to Route::form('/books', 'create_book'): a refusal comes back here with errors(), old() and flash('error')

block title
    | Bookshelf

block content
    section.cq-hero.cq-hero--page
        h1.cq-display Bookshelf
        p.cq-lead The books we love. The same catalog that the REST API and AI agents use.

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            if flash('success')
                p.cq-alert(role='status')= flash('success')

            form(method='POST', action="{$home}books", novalidate)
                =csrf()
                .cq-form-grid.cq-form-grid--2
                    .cq-field
                        label(for='title') * Title
                        input#title.form-control(type='text', name='title', value=old('title'), maxlength='200')
                        if errors('title')
                            p.cq-field-error= errors('title')
                    .cq-field
                        label(for='author_id') * Author
                        select#author_id.form-select(name='author_id')
                            each $a in $authors
                                option(value=$a['id'], selected=$a['id'] == old('author_id'))= $a['name']
                        if errors('author_id')
                            p.cq-field-error= errors('author_id')
                    .cq-field
                        label(for='published_year') * Year
                        input#published_year.form-control(type='number', name='published_year', value=old('published_year'), placeholder='1969')
                        if errors('published_year')
                            p.cq-field-error= errors('published_year')
                .cq-field
                    label(for='summary') Summary
                    input#summary.form-control(type='text', name='summary', value=old('summary'), placeholder='Optional', maxlength='2000')
                button.btn.btn-primary(type='submit') Add book
            if flash('error')
                p.cq-field-error(role='alert')= flash('error')

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            h2.cq-heading Books
            if $books['total'] == 0
                p.cq-empty-state No books here yet: add the first one above.
            else
                ul.cq-rows
                    each $b in $books['data']
                        li.cq-row
                            .cq-row-main
                                p.cq-row-title
                                    a(href="{$home}books/{$b['id']}")= $b['title']
                                p.cq-row-meta
                                    a(href="{$home}books?author_id={$b['author_id']}")= $b['author']['name']
                                    | , #{$b['published_year']}
                //- the next and previous pages keep the author of the address (?author_id=2)
                - $filter = $author_id !== '' ? '&author_id=' . (int) $author_id : '';
                p.cq-auth-note
                    | Page #{$books['page']} of #{$books['pages']}, #{$books['total']} books
                    if $books['page'] > 1
                        |  · 
                        a(href="{$home}books?page=" . ($books['page'] - 1) . $filter) Previous
                    if $books['page'] < $books['pages']
                        |  · 
                        a(href="{$home}books?page=" . ($books['page'] + 1) . $filter) Next
            p.cq-auth-note
                a(href="{$home}books") All books
```

**`application/views/Bookshelf/book.pug`**
```pug
extends ../default/master/site

//- One book: Route::page gives it $book (get_book: the book with its author)

block title
    = $book['title'] . ' · Bookshelf'

block content
    section.cq-hero.cq-hero--page
        h1.cq-display= $book['title']
        p.cq-lead
            | by #{$book['author']['name']}, #{$book['published_year']}

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            if flash('success')
                p.cq-alert(role='status')= flash('success')
            p= $book['summary'] ?: 'No summary yet.'
            p.cq-auth-note
                a(href="{$home}books") All books
```

**`application/views/Bookshelf/notfound.pug`**
```pug
extends ../default/master/site

//- The notFound view of Route::page: $message is the message of the capability that refused (get_book)

block title
    | Not found · Bookshelf

block content
    section.cq-hero
        h1.cq-display Not found
        p.cq-lead= $message
        .cq-cta
            a.btn.btn-primary(href="{$home}books") All books
```

Open **http://localhost:8080/books**:
+ The five sample books and Dawn, added in step 2, are listed by title, each with its author: `Page 1 of 1, 6 books`.
+ Click an author: `/books?author_id=2` lists only the books of Octavia E. Butler.
+ Submit the form with an empty title and year: the page comes back with `Some of the inputs are invalid` under the button, `The Title field is required` and `The Published Year field is required` under their fields, and the author you chose still selected.
+ Add *The Dispossessed*, Ursula K. Le Guin, 1974: you land on `/books/7`, which shows the title, `by Ursula K. Le Guin, 1974` and `Book added`.
+ `http://localhost:8080/books/99` shows *Not found* and `There is no book #99`, with status `404`.

### 4. A REST API

The Quickstart's `Route::capabilities('/capabilities')` already serves every capability to programs: `POST /capabilities/<name>` with the inputs as JSON, and `GET /capabilities` lists what the caller may use.
```bash
curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" \
     -d '{"title": "Wild Seed", "published_year": 1980, "author_id": 2}' \
     http://localhost:8080/capabilities/create_book
```
```json
{"data":{"id":8,"title":"Wild Seed","published_year":1980,"summary":null,"author_id":2,"created_at":"2026-11-08 00:14:45","modified_at":"2026-11-08 00:14:45","author":{"id":2,"name":"Octavia E. Butler","bio":"American science fiction author, the first to receive a MacArthur fellowship.","created_at":"2026-11-08 00:13:35","modified_at":"2026-11-08 00:13:35"}}}
```
For addresses of your own, add one `Route::capability` per address under the `BOOKSHELF PAGES` lines of `web.php`:
```php
    // BOOKSHELF API: the same capabilities, for other applications (JSON)
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('GET', '/api/authors', 'list_authors');
```
The query string and the `{id}` of the address become inputs:
```bash
curl -H "Accept: application/json" "http://localhost:8080/api/books?author_id=3"
curl -H "Accept: application/json" http://localhost:8080/api/books/3
curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" \
     -d '{"title": "Mind of My Mind", "published_year": 1977, "author_id": 2}' http://localhost:8080/api/books
```
The first answers `{"data":{"data":[{"id":5,"title":"Invisible Cities",...,"author":{"id":3,"name":"Italo Calvino",...}}],"total":1,"page":1,"per_page":20,"pages":1}}`, the second `{"data":{"id":3,"title":"Kindred",...,"author":{"id":2,"name":"Octavia E. Butler",...}}}`, the third `201` with the new book.

| Request | Status | Body |
| --- | --- | --- |
| a read, or a write of an existing record | `200` | `{"data": ...}` |
| a new record (`POST` on a capability that is not read-only) | `201` | `{"data": ...}` |
| refused input (`{"title": "No author", "published_year": 1990}`) | `422` | `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"author_id":"The Author Id field is required"}}}` |
| unknown id | `404` | `{"error":{"code":"not_found","message":"There is no book #99"}}` |
| no sign-in or token for a capability that is not public | `401` | `{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}}` |
| a token without the scope | `403` | `{"error":{"code":"forbidden","message":"This needs the scope books:write"}}` |

### 5. Tools for AI agents

The Quickstart's `Route::mcp('/mcp')` makes every capability a tool. Give an agent a token with only the books scopes:
```bash
docker compose exec dev-box php caligrafer.php token create "books agent" --scopes books:read,books:write
```
```text
 Token #2 "books agent" created with books:read books:write

 cq_2_...

 It is shown only now: store it as a secret (Authorization: Bearer <token>).
```
Connect Claude Code with it:
```bash
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer cq_2_..."
```
Ask "add The Lathe of Heaven by Ursula K. Le Guin, 1971, to my shelf": the agent calls `list_authors` to find her id, then `create_book`. The same call with `curl`:
```bash
curl -H "Authorization: Bearer cq_2_..." -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"create_book","arguments":{"title":"The Lathe of Heaven","published_year":1971,"author_id":1}}}' \
     http://localhost:8080/mcp
```
The answer has `"isError":false` and the new book, with its author, in `structuredContent`. Without `author_id`, it has `"isError":true` and the same `fields` as REST. `tools/list` shows the books tools and the public ones; the user administration tools stay hidden from this token. Without a token, `/mcp` answers `401` with a `WWW-Authenticate: Bearer` header and the JSON-RPC error `{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Unauthorized: send Authorization: Bearer <personal access token>"}}` (2.19.1; before, a browser's session cookie got a `403` CSRF page instead).

### 6. The JavaScript client, for Vue apps and page scripts

Generate the client again after every change to a capability:
```bash
docker compose exec dev-box php caligrafer.php client
```
```text
 public/js/capabilities.js written: 22 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)
 import { createBook, CapabilityError } from './capabilities.js'
```
Each capability is a function (`list_books` becomes `listBooks`). In a page of the application it sends the visitor's session; elsewhere, `configure({ baseUrl, token })`:
```js
import { listBooks, createBook, CapabilityError } from './capabilities.js'

const shelf = await listBooks({ author_id: 2, page: 1 })
console.log(shelf.total, shelf.data[0].title, shelf.data[0].author.name)
try {
  await createBook({ title: 'No author', published_year: 1990 })
} catch (e) {
  if (e instanceof CapabilityError) console.log(e.status, e.fields.author_id)   // 422 The Author Id field is required
}
```
Vue apps: see [Vue apps: the JS client](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#client).

### 7. Before you go online: sign-in

`public: true` on `create_book` lets anyone add books. Close it: readers keep the catalog, and only people who signed in, or a program with a token, add books.

1. Create the `users` table of the authentication module:
    ```bash
    docker compose exec dev-box php caligrafer.php sql application/models/Auth/create_db.sql
    ```
2. In `application/config/routing/web.php`, remove the `// ` in front of `Auth::authentication('User', 'users');` near the top, and add `Route::auth` as the first line of the `BOOKSHELF PAGES` lines:
    ```php
        Auth::authentication('User', 'users');
    ```
    ```php
        // BOOKSHELF PAGES: rendered on the server with the data of the capabilities; the form calls create_book
        Route::auth(['home' => '/books']);
    ```
    `Route::auth` declares `/login`, `/logout`, `/register` and `/notAuthorized`, with the sign-in pages of the [authentication module](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication). After signing in, people land on `/books`.
3. In `application/services/Bookshelf/CreateBook.php`, remove `public: true` and its comment (and the comma before it):
    ```php
    #[Capability(
        name: 'create_book',
        description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id.',
        scope: 'books:write'
    )]
    ```

Now:
+ A visitor who submits the form on `/books` is sent to `/login`.
+ `POST /api/books` without a token gets `401` and `Sign in or send a personal access token`.
+ The `books agent` token still adds books (`201`): it has `books:write`.
+ Create an account at `/register` (usernames are letters and digits, for example `sam42`; to sign in with an email instead, see [Signing in with an email](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#emailsignin)); you are sent to `/login`. Sign in: you land on `/books`, and the form adds the book.

`docker compose exec dev-box php caligrafer.php audit` lists every change and every refusal: when, through which channel, who, which capability:
```text
 2026-11-08 00:15:31  web   user   1            create_book                  ok
 2026-11-08 00:15:30  web   guest  -            register_user                ok
 2026-11-08 00:15:18  rest  token  2            create_book                  ok
 2026-11-08 00:15:18  rest  guest  -            create_book                  unauthenticated  Sign in or send a personal access token
 2026-11-08 00:15:18  web   guest  -            create_book                  unauthenticated  Sign in or send a personal access token
```
To keep a capability for administrators (`permissions` of 1 or more in `users`), add `level: 1` to its `#[Capability]`. The Tasks example is still public: remove its `public: true` the same way, or remove the example.

### 8. A test

```bash
docker compose exec dev-box php caligrafer.php test make Bookshelf
```
```text
 tests/BookshelfTest.php written: php caligrafer.php test Bookshelf
```
Replace the content of **`tests/BookshelfTest.php`**:
```php
<?php

use Caligrafy\Testing\TestCase;

/**
 * php caligrafer.php test Bookshelf
 */
final class BookshelfTest extends TestCase
{
    public function testASignedInPersonAddsABook(): void
    {
        $book = $this->call('create_book', ['title' => 'Tehanu', 'published_year' => 1990, 'author_id' => 1], $this->user(1));
        $this->assertSame('Tehanu', $book['title']);
        $this->assertSame('Ursula K. Le Guin', $this->call('get_book', ['id' => $book['id']])['author']['name']);
    }

    public function testAVisitorCannotAddABook(): void
    {
        $this->assertCapabilityFails('unauthenticated', 'create_book', ['title' => 'Tehanu', 'published_year' => 1990, 'author_id' => 1], 'guest');
    }

    public function testABookNeedsAnAuthorThatExists(): void
    {
        $error = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Tehanu', 'published_year' => 1990], $this->user(1));
        $this->assertArrayHasKey('author_id', $error->fields);
        $error = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Tehanu', 'published_year' => 1990, 'author_id' => 999], $this->user(1));
        $this->assertSame('There is no such author', $error->fields['author_id']);
    }
}
```
```bash
docker compose exec dev-box php caligrafer.php test
```
```text
PHPUnit 12.5.40 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12

...                                                                 3 / 3 (100%)

Time: 00:00.077, Memory: 20.00 MB

OK (3 tests, 7 assertions)
```
Each test runs in a transaction that is rolled back: the books it adds do not stay. More in [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App).

### 9. Later: change the table with a migration

Once `books` holds data (on your computer or online), never edit its `create_db.sql` to change it: write a migration, a numbered SQL file that runs once. Give books an optional ISBN that two books never share:
```bash
docker compose exec dev-box php caligrafer.php migrate make add_books_isbn
```
```text
 application/migrations/20261108001545_add_books_isbn.sql written: add its SQL, then php caligrafer.php migrate
```
Add the SQL at the end of the file it wrote (one statement: the column and its unique index are added together, or not at all):
```sql
-- add_books_isbn
-- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.

ALTER TABLE books
    ADD COLUMN isbn VARCHAR(20) NULL AFTER summary,
    ADD UNIQUE KEY uq_books_isbn (isbn);
```
```bash
docker compose exec dev-box php caligrafer.php migrate
```
```text
 OK   20261108001545_add_books_isbn.sql

 1 migration(s) run on caligrafy
```
Then give the code the new column:
1. In `application/models/Bookshelf/Book.php`, add `public $isbn;` after `public $summary;`.
2. Make `application/services/Bookshelf/CreateBook.php` read (the `isbn` input, its filter and its description, and an empty ISBN saved as `NULL`, which the unique index allows on many books):
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Service;

    // create_book: adds a book, with the same rules whoever calls it
    #[Capability(
        name: 'create_book',
        description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id. The ISBN is optional and unique.',
        scope: 'books:write'
    )]
    class CreateBook extends Service
    {
        public $rules = array(
            'title' => 'required|max_len,200',
            'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
            'summary' => 'max_len,2000',
            'isbn' => 'alpha_numeric_dash|max_len,20|unique,books.isbn',
            'author_id' => 'required|integer|exists,authors.id'
        );
        public $filters = array('title' => 'trim', 'summary' => 'trim', 'isbn' => 'trim');
        public $descriptions = array(
            'title' => 'The title of the book',
            'published_year' => 'The year it was first published, for example 1969 (not after this year)',
            'summary' => 'Optional: one or two sentences about the book',
            'isbn' => 'Optional: its ISBN, digits and dashes, for example 978-0-441-47812-5; two books never share one',
            'author_id' => 'The id of its author (list_authors)'
        );

        public function handle(array $input, Context $context)
        {
            // an empty ISBN is no ISBN: NULL, which the unique index allows on many books
            $input['isbn'] = ($input['isbn'] ?? '') === '' ? null : $input['isbn'];
            $id = Book::insert($input);   // one INSERT, every value bound; returns the id of the new book
            return Book::with('author')->find($id);
        }
    }
    ```
3. In `application/views/Bookshelf/books.pug`, add the field after the year's `.cq-field`, at the same depth:
    ```pug
                        .cq-field
                            label(for='isbn') ISBN
                            input#isbn.form-control(type='text', name='isbn', value=old('isbn'), placeholder='Optional', maxlength='20')
                            if errors('isbn')
                                p.cq-field-error= errors('isbn')
    ```

A second book with the same ISBN is now refused on every channel with `"isbn": "This isbn is already used"`. Online, the deploy runs the migrations that never ran. More in [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).

### Check

+ `php caligrafer.php capabilities` lists `list_books`, `get_book` and `list_authors` as `public` and `read`, and `create_book` as `books:write` and `writes`.
+ `/books` lists the books with their authors; an empty form shows a message under each required field; a new book opens its own page with `Book added`.
+ `curl http://localhost:8080/api/books` answers `200`; a `POST` without a token answers `401`; with the `books agent` token, `201`.
+ `php caligrafer.php test` ends with `OK`.
+ `php caligrafer.php migrate status` shows `add_books_isbn` as run.

## Common mistakes

+ **A capability without `--scope`.** `service` then writes `scope: ''`: the capability needs no scope, so any token can call it. Give every capability a scope.
+ **A foreign key without `exists`.** Without `exists,authors.id` in `$rules`, a book with an unknown author reaches the database, which refuses it with a foreign key error instead of a message under the field.
+ **`paginate()` without a page in a capability.** It then reads `$_GET`, which REST, MCP and the command line do not have: pass `$input['page'] ?? 1`, with `'page' => 'integer'` in `$rules`.
+ **A page fed by a capability that writes.** `Route::page` data accepts only `readOnly` capabilities: it stops with an error otherwise.
+ **`flash()`, `errors()` and `old()` last one request.** Point `success` (and `back`) at the page that shows them.
+ **A `POST` with `curl` without `Accept: application/json`.** It is treated as a form without a CSRF token: `[ERROR 3000]` (403).
+ **Editing a `create_db.sql` that already ran online** to change a table: the change is a migration (step 9).
+ **Model code outside a capability.** Pages, forms, custom requests (`Route::action`) and scripts call capabilities; only capabilities read and write models.

## What you built

One feature, written once in four small classes, used from a web page, by other programs and by AI agents, with the same rules everywhere. Next:
+ [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works): the ideas behind it.
+ [Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): every option of a capability, tokens, MCP over stdio, the audit log.
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): forms that change and delete (`update_book`, `delete_book`), sign-in options, webhooks and downloads.
+ [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models), [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships) and [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries): loans, tags, filters and search.
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online).

>[Next: Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
