# 7. Views

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **view** is the HTML page a person sees. In Caligrafy a view is a [Pug](https://pugjs.org) file in `application/views/`, rendered on the server by Phug (Pug for PHP). A page route names the view and the capabilities that give it its data (`Route::page`); a form posts to a capability (`Route::form`) and comes back to the page with its errors. Every page is built from Caligrafy's [page components](#pagecomponents): a top bar, a title band, forms, rows and tiles, with no CSS to write, at every screen size.

## With Claude

With the [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin), ask for the page together with the feature it shows:

```text
/caligrafy-quill:new-capability books: a list of the books (title, author), a page for one book, and a form to add one
```

Claude writes the capabilities in `application/services/Bookshelf/`, the page routes and the form route in `application/config/routing/web.php` (`Route::page` with `data`, `Route::form`), and the views in `application/views/Bookshelf/`, built on the master `default/master/site` with the page components of [ui-guidelines](https://github.com/caligrafy/caligrafy-quill/tree/master/claude-plugin/skills/caligrafy/reference/ui-guidelines.md). To change only how pages look, use [design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles) (`/caligrafy-quill:design`).

**Check** before you say OK:
+ Open the list page and one item's page in the browser, at desktop and phone width.
+ Send the form empty: each field shows its own message under it, and what you typed is still there.
+ Send it filled: you land on the new item's page with the success message, once.
+ The views extend `../default/master/site`, join strings with `.` (never `+`), and every `form` has `method='POST'` and `=csrf()`.
+ `web.php` has no closure that returns a view, and no controller that only passes data to a view.

## By hand

You build the Bookshelf pages: the catalog with an add form at `/books`, and one page per book at `/books/<id>`. Commands run in the project folder with the Docker server of the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart); addresses start with `http://localhost:8080/`. If you followed the [course](https://github.com/caligrafy/caligrafy-quill-course/wiki) or [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability), the files of steps 1 to 3 are already there: check that they match and go to step 4.

**1. The tables.** Write `application/models/Bookshelf/create_db.sql` (the same file as [Routing, step 1](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes)):
```sql
/* Bookshelf: the first version of its tables, and sample books.
 * Run it with: docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
 * Safe to run again: CREATE TABLE IF NOT EXISTS skips a table that exists, INSERT IGNORE a row whose id exists.
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
Then give `books` its `isbn` column with a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations) (a table that holds rows is never changed in `create_db.sql`). Run `docker compose exec dev-box php caligrafer.php migrate make add_books_isbn`; it prints ` application/migrations/<number>_add_books_isbn.sql written: add its SQL, then php caligrafer.php migrate`. Make that file read:
```sql
-- add_books_isbn
-- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.
-- One statement: the column and its unique index are added together, or not at all.

ALTER TABLE books
    ADD COLUMN isbn VARCHAR(20) NULL AFTER summary,
    ADD UNIQUE KEY uq_books_isbn (isbn);
```
Run:
```bash
docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
docker compose exec dev-box php caligrafer.php migrate
```
Expected: ` application/models/Bookshelf/create_db.sql run on caligrafy`, then ` OK   <number>_add_books_isbn.sql` and ` 1 migration(s) run on caligrafy`.

**2. The models.** Write `application/models/Bookshelf/Author.php`:
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
Write `application/models/Bookshelf/Book.php`:
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
    public $isbn;
    public $author_id;
    public $created_at;
    public $modified_at;
}
```
The models need no relationship methods: `Book::with('author')` reads the author from `author_id` ([Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships)).

**3. The capabilities.** The pages never read the tables themselves: capabilities do, and the same ones serve REST, the JS client and AI agents. Create each file with `docker compose exec dev-box php caligrafer.php service Bookshelf/<Name> --scope books:read --read-only` (`--scope books:write` without `--read-only` for `CreateBook`), then make it read as below. `application/services/Bookshelf/ListBooks.php`:
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
`application/services/Bookshelf/GetBook.php`:
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
`application/services/Bookshelf/CreateBook.php`:
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// create_book: adds a book, with the same rules whoever calls it
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id. The ISBN is optional and unique.',
    scope: 'books:write',
    public: true   // for now: lesson 11 closes it
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
`application/services/Bookshelf/ListAuthors.php`:
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
Run `docker compose exec dev-box php caligrafer.php capabilities`: the list shows `create_book`, `get_book`, `list_authors` and `list_books`. (`public: true` opens them to every visitor while you try them; [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability#secure) shows how to close them.)

**4. The routes.** In `application/config/routing/web.php`, above `Route::run();`, write:
```php
    // BOOKSHELF PAGES: rendered on the server with the data of the capabilities; the form calls one capability
    Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books', 'authors' => 'list_authors'], 'query' => ['author_id']]);
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book'], 'notFound' => 'Bookshelf/notfound']);
    Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);   // the same capability, as a file
```
+ `/books` gives its view `$books`, the page of books of `list_books` (`?page=2` and `?author_id=2` reach it as its inputs), `$authors`, the answer of `list_authors`, and `$author_id`, the `?author_id=` of the address (`''` when absent), which the links to the next pages keep.
+ `/books/3` calls `get_book` with `id = 3` and gives the view `$book`, with its `author`; a missing book renders `Bookshelf/notfound` with status `404`.
+ A `POST /books` calls `create_book` with the form's fields, then goes to the new book's page (`{id}` is the id of the book it returns).
+ `/authors.csv` is the list of authors as a spreadsheet ([Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#download)).

**5. The catalog page.** Write `application/views/Bookshelf/books.pug`:
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
                        label(for='isbn') ISBN
                        input#isbn.form-control(type='text', name='isbn', value=old('isbn'), placeholder='Optional', maxlength='20')
                        if errors('isbn')
                            p.cq-field-error= errors('isbn')
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
                |  · 
                a(href="{$home}authors.csv") The authors as a spreadsheet (CSV)
```
`$home` is the address of the application (set by the master, ending with `/`), `csrf()` writes the hidden `_token` field every form needs, and a capability's answer reaches the view as plain arrays (`$b['author']['name']`). `#{...}` prints a PHP value inside text.

**6. The page of one book.** Write `application/views/Bookshelf/book.pug`:
```pug
extends ../default/master/site

//- One book: Route::page gives it $book (get_book: the book with its author).
//- Declarative routes adds its edit and delete forms (update_book, delete_book)

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
            if $book['isbn']
                p.cq-row-meta ISBN #{$book['isbn']}
            p.cq-auth-note
                a(href="{$home}books") All books
```
Write `application/views/Bookshelf/notfound.pug`:
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

**7. Try it.** Open `http://localhost:8080/books`:
+ Each row shows a title, its author and its year, sorted by title, and `Page 1 of 1, 5 books` under them.
+ Type `Dawn`, choose Octavia E. Butler, leave the year empty, press **Add book**: the page comes back with `The Published Year field is required` under the year, `Some of the inputs are invalid` under the form, `Dawn` still in the title and Octavia E. Butler still chosen.
+ Type `1987` as the year and press **Add book**: you land on `/books/6`, which shows `Book added` and `by Octavia E. Butler, 1987`. Reload it: the message is gone (it lasts one request).
+ Click *Octavia E. Butler* under a book: `/books?author_id=2` lists her three books.
+ Open `http://localhost:8080/books/999`: *Not found*, `There is no book #999`, status `404`.

**Check**
+ `/books` and `/books/6` look like Caligrafy's own pages (top bar, title band, rows) at desktop and phone width.
+ The form is refused without its `_token` field: `curl -X POST -d "title=x&author_id=1&published_year=2000" http://localhost:8080/books` answers `403`.
+ No controller and no SQL in a view: the routes name capabilities, and the views only print what they get.

## Reference

### Where the files go

| What | Where |
| --- | --- |
| Views (`.pug`), in any sub-folders | `application/views/`, for example `application/views/Bookshelf/books.pug` |
| Caligrafy's masters and modules | `application/views/default/master/` (`site.pug`, `layoutblocks.pug`), `application/views/default/modules/` |
| Stylesheets, scripts, images, fonts | `public/` (`public/css/`, `public/js/`, `public/images/`): `application/` is never served to browsers |

A view is named by its path from `application/views/`, without `.pug`: `'Bookshelf/books'`.

### A page without data

```php
Route::page('/about', 'about');
Route::page('/about', 'about', ['with' => ['sentence' => 'Built with Caligrafy']]);   // fixed values: $sentence
```

### A page with data: `Route::page`

`Route::page($route, $view, $options)` renders the view through the framework's page controller (`framework/core/PageController.php`):

| Option | Default | What it does |
| --- | --- | --- |
| `data` | none | `['books' => 'list_books']`: calls each capability and gives its answer to the view under that name. The capability must exist and be `readOnly`, otherwise every visit answers `500` (`LogicException`). Its inputs are the query string and the route variables, the route variables winning (`/books/3?id=9` gives `id = 3`); only the inputs in its `$rules` reach it. Fixed inputs: `['book' => ['get_book', ['id' => 3]]]` |
| `with` | none | fixed values for the view: `['with' => ['install' => true]]` |
| `signedIn` | `false` | `true` sends visitors to `/login` (needs `Auth::authentication(...)` in `web.php`) |
| `level` | `0` | sends people whose `permissions` are below it to `/notAuthorized` |
| `notFound` | none | a view shown with status `404` when a capability answers `not_found`, `invalid` or `conflict`; it gets the route variables, `with`, `$message`, `$authorized` and `$user` |
| `comeBack` | `false` | `true`, with `signedIn` or `level`: visitors go to `/login?return=<this page>` and come back after signing in |
| `visitorsOnly` | none | a path: people already signed in are sent there (a sign-up page) |
| `query` | none | what the view gets of the query string: `['show']` gives `$show` (a string, `''` when absent), `['sort' => 'title']` a default |

Every view rendered by `Route::page` gets:

| Variable | Value |
| --- | --- |
| the route variables | `$id` for `/books/{id}` |
| the `with` values and the `data` answers | `$books`, `$book`... (plain arrays) |
| `$authorized` | `true` when someone is signed in |
| the names of the `query` option | `$sort`, `$show`... as strings |
| `$user` | `['id', 'username', 'permissions']` of who is signed in, or `null` |

The view gets the query string only through `query`; the `data` capabilities get all of it as inputs. The names `authorized` and `user` are reserved (`LogicException`). When a capability refuses: a visitor (`unauthenticated`) is sent to `/login` when authentication is on; `forbidden` answers `[ERROR 1006]` (403); `not_found`, `invalid` and `conflict` answer `[ERROR 1005]` (404), or the `notFound` view.

### Forms: `errors()`, `old()` and `flash()`

A form that posts to [`Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form) comes back to the page with what the page needs to show:

| Helper | Returns |
| --- | --- |
| `errors('title')` | the message of one field, or `null` |
| `errors()` | every field's message, as an array |
| `old('title', $default = '')` | what the field held when the form was refused; never a field whose name matches `pass`, `secret`, `token`, `card`, `cvc`, `cvv` or `iban`, and never a file |
| `flash('success')` | the `flash` message of the `Route::form` that succeeded |
| `flash('error')` | the message of a refused form (`Some of the inputs are invalid` by default, or the route's `error` option) |
| `flash('saved', 'Your changes are saved')` | keeps a message for the next request |
| `csrf()` | the hidden `_token` field: write `= csrf()` in every form |
| `methodField('PUT')` | the hidden `_method` field, for a `Route::form` with `'method' => 'PUT'` or `'DELETE'` |

They last **one request**: the first page after the post reads them, then they are gone. Point `success` and `back` of `Route::form` at the page that shows them.

### Masters, blocks and Pug in Caligrafy

New pages extend the master **`default/master/site`** (`application/views/default/master/site.pug`): a light top bar with the Caligrafy mark, the page, a footer. Its blocks:

| Block | What goes in it |
| --- | --- |
| `title` | the text of the browser tab (default `Caligrafy`) |
| `brand` | the name after the mark in the top bar |
| `nav_links` | the links on the right of the top bar; on phones a **Menu** button opens them. By default *Sign in* / *Create account*, or *Sign out* when someone is signed in |
| `content` | the page |
| `footer` | the footer (default: the copyright of `copyright()`) |
| `additional_css` | more `link` tags, loaded after Caligrafy's stylesheets and before a design style |
| `additional_scripts` | more scripts, at the end of the page |

**The top bar stays put from page to page** (2.21.4). `layout.css` sets `html { scrollbar-gutter: stable }`, so a short page and a long one keep the same room for the scrollbar. To mark the current link in `nav_links`, change its color or underline it: never a heavier `font-weight`, because Inter is loaded at 400 and 500 only, and a heavier weight is faked by the browser and makes the text change width.

The master also links the web app manifest, the icons and the page's [social card](https://github.com/caligrafy/caligrafy-quill/wiki/9.3-Metadata-&-Social-Rich-Cards), sets `$home` (the application's address, ending with `/`), and links its stylesheets with their modification time (`?v=...`) so browsers fetch every change. Link your own files the same way: `script(src=$home . 'public/js/books.js?v=' . @filemtime(PUBLIC_PATH . 'js/books.js'))`.

**Pug in Caligrafy.** Pug writes HTML by indentation: `p.cq-lead Hello` is `<p class="cq-lead">Hello</p>`. Caligrafy runs Pug with **PHP expressions** ([Phug](https://phug-lang.com)):

| Write | Not |
| --- | --- |
| `'by ' . $author['name']` (PHP joins strings with `.`) | `'by ' + $author['name']` (an error: "Unsupported operand types") |
| `p= $book['title']` (escaped), `p!= $html` (not escaped: only for HTML you made) | `p= $book.title` |
| `if $book` / `each $book in $books['data']` with the lines under it | `- if ($book) echo 'x'` on one line (does not compile) |
| `p From &#36;40 a month` | `p From $40 a month` (`$40` is read as a variable and vanishes) |
| `- $position = sprintf('%d of %d', $i + 1, count($books))` then `figure(aria-label=$position)` | an attribute value with parentheses and `.` (`aria-label=($i + 1) . ' of ' . count($books)`): a parse error |
| `- $open = isset($signUp) ? $signUp : \Caligrafy\AuthRoutes::signUpOpen()` then `if $open` | a `::` on the `if` line after `??` or in a ternary (`if isset($signUp) ? $signUp : \Caligrafy\AuthRoutes::signUpOpen()`): it is read as a Pug filter, `Failed to compile: Unknown filter signUpOpen` |

`php caligrafer.php doctor` points to the `$` before digits and the one-line `- if` it finds in `application/views`.

### Compiled views, debug mode and the Optimizer

Phug turns each `.pug` view into PHP. Caligrafy keeps that PHP between requests (`framework/core/View.php`):

| Setting in `.env` | Effect |
| --- | --- |
| (default) | a view is compiled once, then again only when it, or a file it `extends` or `include`s, is saved with a newer modification time. Edit a view, reload the page: you see the change |
| `APP_DEBUG=true` | Phug's debug mode: an error names the `.pug` file and its line, in a page with the source. It makes every page about three times slower. Views compiled this way are kept in their own folder |
| `APP_DEBUG=false` (production) | no debug mode: an error in a view answers `[ERROR 500] Internal Server Error`. An up-to-date compiled view runs through Phug's Optimizer, which includes the compiled PHP without loading Phug's compiler |
| `VIEW_OPTIMIZER=false` | no Optimizer: every view goes through Phug's renderer (still cached) |
| `VIEW_CACHE=false` | no cache: every view is compiled on every request |

The compiled views live in the system's temporary folder, one folder per project and per system user, readable by that user only: `caligrafy-views-<project>-<user>` and, in debug mode, `caligrafy-views-debug-<project>-<user>`. A project that sets Phug's `cache_dir` itself keeps its folder. A new container starts with empty folders.

**When to clear them.** Copying files with tools that keep the old modification times (`rsync -a`, `cp -p`, an unzipped archive) can leave a compiled view newer than the file you copied, so the old page keeps showing. Run, as the system user of the web server (each user has its own folders):
```bash
docker compose exec dev-box php caligrafer.php cache clear
```
Expected: `Cache (<store>) emptied; <n> compiled view and class map file(s) removed`. It empties both view folders, the class map and the [cache](https://github.com/caligrafy/caligrafy-quill/wiki/Caching). Restarting the container does the same for a Docker server. Deploys from the production image and Caligrafy Cloud are not concerned.

### Mobile: install to home screen

Browsers can install a Caligrafy application on a phone, a tablet or a computer: it opens from its own icon, full screen, without the browser bar. Installation needs HTTPS (or `localhost`).

+ **The manifest.** Both masters link `public/manifest.webmanifest`: the name, the white colors and the icons of the app (`public/images/resources/icon-192x192.png` and `icon-512x512.png`, plus the Apple touch icons). Its `start_url` and `scope` are `../`, relative to the manifest: they point to the root of the application, whatever `APP_ROOT` is. Change `name`, `short_name` and the icons to brand your app; keep a 192x192 and a 512x512 PNG (Chrome needs both). New apps start with Caligrafy's black logo in `favicon.ico`, the Apple touch icons and these two: replace the files with your own, keeping their names (the pages link them with their modification time, so browsers fetch them again).
+ **The install button.** Give the page `install`: `Route::page('/', 'default/index', ['with' => ['install' => true]]);`. The master then includes `default/modules/install.pug`: on Chrome, Edge and Android an **Install** button (`btn-primary`) appears when the browser offers it, and **Not now** (`btn-secondary`) hides it; on iPhone and iPad Safari a short hint says to tap Share, then Add to Home Screen, and **Got it** hides it. Nothing shows once the app is installed or after the visitor dismissed it (`localStorage` key `caligrafy-install-dismissed`). The box is styled inside the module (`.caligrafy-install`): restyle it in your own CSS.
+ **The offline page.** `PWA_SERVICE_WORKER=true` in `.env` registers `public/sw.js` (off by default). It does one thing: when a page cannot load because the device is offline, it shows `public/offline.html`. It never caches pages, API routes, `POST` requests or signed-in pages. Edit `offline.html` (self-contained: no external files) and change `CACHE` in `sw.js` when you do. Setting it back to `false` unregisters it on the next visit. The root `.htaccess` sends `Service-Worker-Allowed` for `public/sw.js` and the `application/manifest+json` type for `.webmanifest` files. `php caligrafer.php doctor` checks the manifest and its icons, and, with the service worker on, its files and HTTPS in production.
+ **Client apps** of `public/<app>` link the manifest themselves in their `<head>`: `<link rel="manifest" href="<?php echo session('public').'manifest.webmanifest'; ?>">` and `<meta name="theme-color" content="#ffffff">` install the whole application. To install one app on its own, copy `public/manifest.webmanifest` to `public/<app>/manifest.webmanifest`, set `"start_url": "./"`, `"scope": "./"`, `"id": "./"` and its `name`, prefix the icons with `../` (`"../images/resources/icon-192x192.png"`) and link that copy. A Vite single page app puts the copy and its icons in its own `public/` folder and links it from `index.html` (`<link rel="manifest" href="manifest.webmanifest">`).

### One look on every device: the design tokens

`public/css/tokens.css` defines Caligrafy's look as CSS custom properties, generated from `public/css/tokens.json` (the two must match): colors (`--cq-color-ink`, `--cq-color-paper`, `--cq-color-text`, `--cq-color-text-muted`, `--cq-color-border`...), type (`--cq-font-size`, `--cq-font-line-height`), spacing (`--cq-space-1` to `--cq-space-5`, `--cq-space-gutter`), radius (`--cq-radius-control`) and breakpoints (`--cq-breakpoint-phone`, `-tablet`, `-desktop`, `-wide`). It also gives:
+ touch targets of at least `--cq-touch-target` (44px) on touch screens (`pointer: coarse`),
+ safe-area padding when the app is installed to the home screen (`display-mode: standalone`),
+ `.cq-split`: one column on phones, a list and its detail side by side from tablets up ([below](#sidebyside)).

`look.css` sets its own `--design-*` tokens (`--design-canvas`, `--design-ink`, `--design-primary`, `--design-font`...) and feeds the `--cq-*` ones from them. Use the tokens in your own styles (`color: var(--cq-color-text)`) so every experience of the application keeps one look: see [experiences](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#experiences).

### Caligrafy's look: the page components

Every page of Caligrafy shares one look: **a white canvas, a light top bar with the Caligrafy mark, one message per screen, full-width bands, narrow columns for forms, rows for lists, large tiles for links, black for the main action, Inter type, no cards and no shadows**, at every size. Three stylesheets give it; the master `site.pug`, the Vue app template and the single page app template link them:

| Stylesheet | What it holds |
| --- | --- |
| `public/css/layout.css` | the structure: top bar, title bands, bands, forms, rows, tiles, account area, footer |
| `public/css/look.css` | the look: its `--design-*` tokens, Inter, black buttons, fields, the mark |
| `public/css/camera.css` | full-screen camera pages: face detection, machine learning, face sign-in |

A page is a title band, then bands. The parts:

| Part | Classes |
| --- | --- |
| Full-screen message | `section.cq-hero > h1.cq-display + p.cq-lead + .cq-cta > .btn` (a landing, a welcome) |
| Title band | `section.cq-hero.cq-hero--page`, same children: the title of an ordinary page |
| Band | `section.cq-band > .cq-inner > h2.cq-heading + p.cq-lead + ...`; `.cq-band--surface` (ash), `.cq-band--tight`, `.cq-inner--narrow` (720px), `.cq-center` |
| Sign-in style form | `main.cq-auth > h1 + form > .cq-field (label + .form-control) + .btn`; `.cq-auth--wide`, `.cq-or`, `p.cq-auth-note` |
| Form on a page | `.cq-form-grid(.cq-form-grid--2) > .cq-field`: fields side by side from tablets up; `.cq-btn-block`, `label.cq-hidden-label` |
| Messages | `.cq-alert` (`.cq-alert--error`) above a form (`include ../default/modules/notice` shows `$error` and `$message`), `p.cq-field-error` under a field, `p.cq-empty-state` for an empty list |
| Choices | `.cq-segments > button` (`.active`) with `span.cq-count` |
| List | `ul.cq-rows > li.cq-row` (`.is-done` greys it) with `.cq-row-check`, `.cq-row-main` (`.cq-row-title`, `.cq-row-meta`), `.cq-row-actions` |
| Links | `.cq-tiles > a.cq-tile > h3 + p + span.cq-tile-link`: one, two or three columns by width |
| Side by side | `.cq-split > section.cq-split-main + aside.cq-split-detail` ([below](#sidebyside)) |
| Figures | `.cq-stats > .cq-stat > .cq-stat-value + .cq-stat-label`, `span.cq-badge` |
| Account area | `.cq-account > nav.cq-account-nav > a(href='#section')` + `.cq-account-main > section(id)`: a side menu on desktops that marks the section on screen |
| Text and code | `.cq-doc` (long text), `.cq-code`, `pre.cq-pre` |

The same classes work in Vue apps and single page apps. Keep to black and white (`btn-primary` is black, `btn-secondary` ash): no Bootstrap color classes. The top bar has its own button (Menu): scripts find their elements by id, never with `document.querySelector('button')`. In `site.pug`, `body` is a flex column (the footer waits one screen down): put content in a block (`section.cq-band`), never a bare `a` or `span` directly in `body`, and keep a page's own footer a `footer.cq-footer` directly in `body`.

### A list and its detail, side by side

A page that shows a list and the item opened (a finder, a catalog and its add form) puts them side by side right after its title band: the list on the left, the item in an ash panel on the right that stays in view while the list scrolls. On phones the panel comes after the list. A form in the panel takes one field a line.

```pug
section.cq-hero.cq-hero--page
  h1.cq-display Find a book
  p.cq-lead Type a few words: the books answer as you type.

.cq-split
  section.cq-split-main
    .cq-field
      label(for='query') Search
      input#query.form-control(type='search')
    ul#books.cq-rows
  aside#book.cq-split-detail(aria-live='polite')
    p.cq-empty-state Open a book to read about it.
```
The course's [finder and single page application](https://github.com/caligrafy/caligrafy-quill-course/wiki) are built this way.

**Camera pages.** Face detection, machine learning and face sign-in fill the screen with the camera (`camera.css`, `.cq-camera`): the title and the status float at the top, the actions at the bottom, an oval guides the face, a sheet holds a form over the camera. See [Face Detection](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition) and [Machine Learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning).

### Another look: design styles

Caligrafy's look is the default. To give the whole application another one, pick a [design style](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles) (`php caligrafer.php design use <name>`, `DESIGN_STYLE` in `.env`) and write its stylesheet (`/caligrafy-quill:design` in Claude Code). The masters load `public/css/designs/<name>.css` last, so the same markup takes the new look and your views do not change. An empty `DESIGN_STYLE` is the Caligrafy look again.

## Other ways and under the hood

These still work. `Route::page` with `data` replaces them for pages, and `Route::form` for forms. A page that depends on more than one capability's answer (a redirect or a view, say) is a web-only capability that returns `Web::view()`: [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action).

+ **`view()` from a controller.** `return view('Bookshelf/books', ['books' => $this->call('list_books'), 'authors' => $this->call('list_authors')]);` in a controller extending `Caligrafy\Controller` (`$this->view()` is the same). In a controller you keep, it must pass `$authorized` itself, or the top bar's signed-in parts follow only the session.
+ **A closure that returns a view.** `Route::get('/hello', function () { return view('hello'); });` gets only what the closure passes: no `$authorized`, no `$user`.
+ **`view()` answers JSON too.** `view($name, $data)` prints `$data` as JSON and ends the request when the request sends `Accept: application/json` (and not `text/html`), or when `$name` is `null`; `api($data)` is `view(null, $data)`. Set the status first: `http_response_code(422); return api([...]);`. Nothing runs after `view()` or `api()`.
+ **Hand-written forms** posted to a controller: `= csrf()` in the form, `= methodField('PUT')` for `PUT` and `DELETE`, and the controller validates with `$this->check()`. See [Helpers, Forms and REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#forms).

### The older master: `layoutblocks` and Bootstrap 5

Pages written before 2.11.0 extend `default/master/layoutblocks`. It keeps working and takes the new look from `look.css`. It loads Bootstrap 5.3 (`scripts('bootstrap_css')`, `scripts('bootstrap_script')`, no jQuery) and `public/css/theme.css`, which keeps the Caligrafy black and white on top of Bootstrap: black `btn-primary`, white `btn-secondary`, black focus rings, buttons in forms at most 150px wide. Its header is fixed (60px): content starts under it. Its body carries `class='caligrafy-layout'`, which scopes the Bootstrap 3 heading margins `theme.css` keeps; `theme.css` must load after Bootstrap. Bootstrap 5 breakpoints are one step lower than Bootstrap 3's: a Bootstrap 3 `col-sm-*` is a Bootstrap 5 `col-md-*`, `col-md-*` is `col-lg-*`, `hidden-xs` is `d-none d-md-block`. Its card components (`main.cq-page > .cq-card`, `form.cq-form > .cq-fields`, `ul.cq-list > li.cq-item`, `.cq-tabs`, `p.cq-error`, `p.cq-empty`) live in `tokens.css`. A project made before 2.11.0 gets `site.pug` with `php caligrafer.php upgrade --apply` and the stylesheets with `php caligrafer.php update`: see [Upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading).

## Common mistakes

+ **`+` to join strings in a view**: the page becomes a template error. Use `.`.
+ **A `data` capability that writes**: `Route::page` accepts only `readOnly` capabilities; every visit answers `500`.
+ **Reading `$_GET` in a view**: the view gets the query string only through the `query` option of `Route::page`; a capability gets it through its `$rules`.
+ **A `success` page that redirects again**: the flash messages and errors are read by the first page after the post, then gone.
+ **A `form` without `method='POST'`**: when its script does not run, the browser sends the fields in the address. Give every form `method='POST'`.
+ **A changed view that does not show after a copy**: `php caligrafer.php cache clear` as the web server's user.
+ **`document.querySelector('button')`** finds the top bar's Menu button: use ids.

## Related pages

+ [Declarative routes: `Route::form`, `Route::auth`, `Route::download`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes)
+ [Routing: page routes](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing)
+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability)
+ [Queries: the query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries)
+ [Design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles)
+ [Vue apps: the flow of information](https://github.com/caligrafy/caligrafy-quill/wiki/9.7-Understanding-the-flow-of-information)

>[Next Section: Learn about Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers)
