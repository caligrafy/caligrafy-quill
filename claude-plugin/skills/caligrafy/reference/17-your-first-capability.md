# Your first capability

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

In this tutorial you add a **Books** catalog to the application of the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart): a table, two capabilities (list the books, add a book), a REST API, a web page, and tools for an AI agent. About 20 minutes. Every command runs in your project folder, with the server started by the Quickstart.

## 1. The table and its model

A **model** is a PHP class for one table: one public property per column. Create the folder `application/models/Books` and two files in it.

**`application/models/Books/create_db.sql`**
```sql
CREATE TABLE IF NOT EXISTS books (
    id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT now(),
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```

**`application/models/Books/Book.php`**
```php
<?php

use Caligrafy\Model;

class Book extends Model
{
    public $id;
    public $title;
    public $author;
    public $created_at;
}
```

Create the table:
```bash
docker compose exec dev-box php caligrafer.php sql application/models/Books/create_db.sql
```
Expected: `application/models/Books/create_db.sql run on caligrafy`.

## 2. Two capabilities

Caligrafer writes the boilerplate:
```bash
docker compose exec dev-box php caligrafer.php service Books/ListBooks --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Books/CreateBook --scope books:write
```
Replace the content of the two files it created.

**`application/services/Books/ListBooks.php`**: read-only, it changes nothing.
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

#[Capability(
    name: 'list_books',
    description: 'Lists every book of the catalog, newest first, with its id, title and author.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListBooks extends Service
{
    public function handle(array $input, Context $context)
    {
        return (new Book('books'))->all(array('order' => 'ORDER BY id DESC'));
    }
}
```

**`application/services/Books/CreateBook.php`**: `$rules` lists the inputs it accepts and their rules; only these reach `handle()`.
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

#[Capability(
    name: 'create_book',
    description: 'Adds a book to the catalog and returns it with its id.',
    scope: 'books:write',
    public: true
)]
class CreateBook extends Service
{
    public $rules = array('title' => 'required|max_len,255', 'author' => 'required|max_len,255');
    public $filters = array('title' => 'trim', 'author' => 'trim');
    public $descriptions = array('title' => 'The title of the book', 'author' => 'Who wrote it');

    public function handle(array $input, Context $context)
    {
        return (new Book('books'))->create($input);
    }
}
```

+ The `description` is what an AI agent reads to choose the tool: say what it does and what it returns.
+ `public: true` lets anyone use them, like the Tasks example, so you can try them at once. Step 6 closes them.
+ `handle()` only returns data. It never draws a page: each experience shows the result its own way.

Check: `docker compose exec dev-box php caligrafer.php capabilities` now lists `list_books` and `create_book`.

## 3. AI agents can already use them

The Quickstart added `Route::mcp('/mcp')`: every capability is a tool. With the token of the Quickstart, ask your agent "add The Dispossessed by Ursula K. Le Guin to the catalog": it calls `create_book`. To give an agent only the books, create a token with just those scopes:
```bash
docker compose exec dev-box php caligrafer.php token create "books agent" --scopes books:read,books:write
```

## 4. A REST API

Add two routes to `application/config/routing/web.php`, above `Route::run();` (and its comment), together with the page route of the next step:
```php
    // BOOKS: two capabilities, a REST API and a page
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('POST', '/api/books', 'create_book');
    Route::get('/books', function () { return view('Books/index'); });
```
Try it:
```bash
curl -H "Accept: application/json" http://localhost:8080/api/books
curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "Kindred", "author": "Octavia E. Butler"}' http://localhost:8080/api/books
```
A book without an author gets `422` and `{"error": {"code": "invalid", ..., "fields": {"author": "The Author field is required"}}}`: the rules of `create_book`, the same on every experience.

## 5. A web page

The page is built from the [page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents), so it looks like the rest of Caligrafy without any CSS. Its script calls the capabilities through the generated JavaScript client.

**`application/views/Books/index.pug`**
```pug
extends ../default/master/layoutblocks

block content
    main.cq-page
        .cq-card
            .cq-card-head
                h2.cq-title Books
                p.cq-note The catalog, from the list_books and create_book capabilities.

            form#new-book.cq-form(novalidate)
                .cq-fields.cq-fields--2
                    div
                        label(for='book-title') * Title
                        input#book-title.form-control(type='text', name='title', maxlength='255')
                    div
                        label(for='book-author') * Author
                        input#book-author.form-control(type='text', name='author', maxlength='255')
                button.btn.btn-primary.cq-submit(type='submit') Add book

            p#error.cq-error(role='alert')
            ul#books.cq-list
            p#empty.cq-empty(hidden) No books yet: add the first one above.

block additional_scripts
    script(type='module', src="{$home}public/js/books.js")
```

**`public/js/books.js`**
```js
import { listBooks, createBook, CapabilityError } from './capabilities.js'

const list = document.getElementById('books')
const empty = document.getElementById('empty')
const form = document.getElementById('new-book')
const error = document.getElementById('error')

async function refresh () {
  const books = await listBooks()
  list.replaceChildren(...books.map((book) => {
    const li = document.createElement('li')
    li.className = 'cq-item'
    li.innerHTML = '<div class="cq-item-body"><p class="cq-item-title"></p><p class="cq-item-meta"></p></div>'
    li.querySelector('.cq-item-title').textContent = book.title
    li.querySelector('.cq-item-meta').textContent = book.author
    return li
  }))
  empty.hidden = books.length > 0
}

form.addEventListener('submit', async (event) => {
  event.preventDefault()
  error.textContent = ''
  try {
    await createBook(Object.fromEntries(new FormData(form)))
    form.reset()
    await refresh()
  } catch (e) {
    error.textContent = e instanceof CapabilityError ? Object.values(e.fields).join(' ') || e.message : 'Something went wrong.'
  }
})

refresh()
```

Generate the JavaScript client again, so that it has `listBooks` and `createBook` (run it after every change to a capability):
```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
```
Open **http://localhost:8080/books**: add a book, then submit the form empty to see the field messages.

## 6. Before you go online: close the capabilities

`public: true` lets anyone add books. Remove it from `CreateBook` (and from the Tasks example), and only these can still add a book:
+ **agents and programs** with a token that has the scope `books:write`;
+ **people** who are signed in: turn on the [authentication module](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication); the page then sends their session with each call;
+ add `level: 1` to the `#[Capability]` line to keep it for users whose `permissions` are 1 or more (administrators).

A visitor who is not signed in then gets "Sign in or send a personal access token" on the page, over REST and for an agent alike. `php caligrafer.php audit` lists every change and every refusal: who, through which experience, which capability.

## What you built

One feature, written once in two small classes, used from a web page, by other applications and by AI agents, with the same rules everywhere. Next:
+ [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works): the ideas behind it.
+ [Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): every option (levels, errors, the JS client in Vue apps, MCP over stdio, the audit log).
+ [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models) and [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships): richer data (authors with many books).
+ With [Claude Code](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin): `/caligrafy-quill:new-capability` builds all of this for you, the Caligrafy way.

>[Next: How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works)
