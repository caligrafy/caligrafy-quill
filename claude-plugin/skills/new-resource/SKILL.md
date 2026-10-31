---
name: new-resource
description: Scaffold a Caligrafy Quill resource - MySQL create_db.sql script, Caligrafy\Model, Caligrafy\Controller with validated CRUD actions, and REST routes in application/config/routing/web.php - following the framework conventions.
when_to_use: The user asks to add an entity, table, model, CRUD, REST endpoint or resource to a Caligrafy Quill project, e.g. "/caligrafy-quill:new-resource Book title:string author:string publisher:fk".
argument-hint: "<Entity> [field:type ...] [parent:fk]"
---

# Scaffold a Caligrafy resource

Arguments: `$ARGUMENTS` (entity name, then optional `field:type` pairs; `name:fk` = foreign key to model `Name`).
If fields are missing, infer sensible ones from the conversation or ask once.

Read first: the `caligrafy` skill's [api-reference.md](../caligrafy/reference/api-reference.md) (Model, Controller, Route)
and [gotchas.md](../caligrafy/reference/gotchas.md) G2, G4, G6, G7, G10, G19, G23.

## Preconditions
1. Confirm this is an initialized Quill project: `application/config/routing/web.php` exists. If only `framework/settings/application` exists, tell the user to run `php caligrafer.php initialize` (it deletes `.git` - ask first).
2. Choose names. Entity `Book` -> model class `Book`, table `books` (lowercase plural, ask if irregular), controller `BookController`, folder `Book`. Grep `application/` for `class Book\b` and `class BookController\b`: class names are global (G19) - pick another name on a clash, or put the controller in a namespace when the project already groups controllers that way (`namespace Shop;`, route `'Shop\\BookController@read'`, G47). Names of the example controllers (`AIController`...) are fine once they are namespaced in `Caligrafy\Controllers`.

## Files to create

### 1. `application/models/<Entity>/create_db.sql`
```sql
/* Run with /caligrafy-quill:db application/models/Book/create_db.sql */
CREATE TABLE IF NOT EXISTS books (
    id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    publisher_id INT(25) UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT now(),
    modified_at DATETIME NOT NULL DEFAULT now() ON UPDATE now(),
    PRIMARY KEY (id),
    CONSTRAINT fk_books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```
Rules: primary key always `id`; FK column `<lowercase parent model>_id` with the same type/signedness as the parent id; unique constraint names `fk_<table>_<column>`; `CREATE TABLE IF NOT EXISTS` (never `DROP TABLE` in a new script); parent tables first. Map types: string -> VARCHAR(255), text -> TEXT, int -> INT, decimal/money -> DECIMAL(10,2), bool -> TINYINT(1) DEFAULT 0, date -> DATE, datetime -> DATETIME, json -> JSON.

### 2. `application/models/<Entity>/<Entity>.php`
```php
<?php

use Caligrafy\Model;
use Caligrafy\Database;

class Book extends Model {

    // properties: one per column
    public $id;
    public $title;
    public $author;
    public $publisher_id;
    public $created_at;
    public $modified_at;

    // Book has one Publisher (books.publisher_id -> publishers.id)
    public function publisher()
    {
        return $this->hasOne('Publisher', 'publishers');
    }

    // Exact match with a bound query (search() is a LIKE search)
    public function findByTitle($title)
    {
        $rows = Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE title = ?', [$title]));
        return array_map(function($row) { return $this->arrayToModel($row); }, $rows);
    }
}
```
Relationships: child->parent `hasOne('Parent','parents')`; parent->children `hasMany('Child','children')`; many-to-many `belongsToMany('Tag','tags')` (the auto-created pivot copies the id types and uses unique constraint names, G10) or create the pivot table yourself in the SQL script.

### 3. `application/controllers/<Entity>/<Entity>Controller.php`
```php
<?php

use Caligrafy\Controller;

class BookController extends Controller {

    private $rules = array('title' => 'required|max_len,255', 'author' => 'required|max_len,255');
    private $filters = array('title' => 'trim|sanitize_string', 'author' => 'trim|sanitize_string');

    // GET /books
    public function index()
    {
        $this->associate('Book', 'books');
        return view('Book/index', array('books' => $this->all(array('order' => 'ORDER BY created_at DESC'))));
    }

    // GET /books/{id}
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) { http_response_code(404); return api(array('error' => 'Book not found')); }
        return view('Book/read', array('book' => $book));
    }

    // POST /books
    public function create()
    {
        $this->associate('Book', 'books');
        $data = $this->input();
        if (isset($data['__errors'])) { http_response_code(422); return api(array('errors' => $data['__errors'])); }
        $book = new Book();
        $book->title = $data['title'];
        $book->author = $data['author'];
        http_response_code(201);
        return api($this->save($book));
    }

    // PUT /books/{id}
    public function update()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) { http_response_code(404); return api(array('error' => 'Book not found')); }
        $data = $this->input();
        if (isset($data['__errors'])) { http_response_code(422); return api(array('errors' => $data['__errors'])); }
        $book->title = $data['title'];
        $book->author = $data['author'];
        return api($this->save($book));
    }

    // DELETE /books/{id}
    public function remove()
    {
        $this->associate('Book', 'books');
        if (!$this->find()) { http_response_code(404); return api(array('error' => 'Book not found')); }
        $this->delete();
        return api(array('deleted' => true));
    }

    // filter, then validate (Controller::check returns arrays on success AND failure - gotcha G6)
    private function input()
    {
        $data = $this->filter($this->request->parameters(), $this->filters);
        $valid = $this->validate($data, $this->rules);
        return $valid === true ? $data : array('__errors' => $valid);
    }
}
```
Rules:
- each action calls `associate()` first; never override built-in names (`find`, `save`, `delete`, `all`, `search`, `check`, `validate`, `filter`, `view`, `api`, `associate`, `table`), hence `remove()` not `delete()`;
- set the HTTP status with `http_response_code()` before `api()`; code after `view()`/`api()` never runs on JSON requests (G21);
- nested routes: name the parent variable `{book_id}`, not `{id}`, or `save()` will update instead of insert (G23);
- `save()` writes `0`, `''` and `false` and skips only `null` (G7); in a project still on an older framework falsy values are dropped (G39) - use `Database::execute` there;
- add `guard('permissions', <level>)` or an `authorized()` check if the resource is private (API key alone is public, G27).

### 4. Routes in `application/config/routing/web.php`
Insert inside the `try` block, after the existing `/` route and before `Route::run();`, with a comment header:
```php
    // BOOK RESOURCE
    Route::get('/books', 'BookController');
    Route::get('/books/{id}', 'BookController@read');
    Route::post('/books', 'BookController@create');
    Route::put('/books/{id}', 'BookController@update');
    Route::delete('/books/{id}', 'BookController@remove');
```
Make sure `Auth::activateAPI();` is present if the resource is consumed as JSON.

### 5. Views (only if the user wants HTML pages)
`application/views/Book/index.pug` and `read.pug`, built with the page components of [ui-guidelines.md](../caligrafy/reference/ui-guidelines.md) (`main.cq-page > .cq-card`: `.cq-card-head`, `.cq-form` with labeled fields and `btn btn-primary cq-submit`, `.cq-list > .cq-item` rows with `.cq-actions`, `.cq-empty`; follow its checklist), `extends ../default/master/layoutblocks`, `block content`, PHP variables (`each $book in $books`, `#{$book->title}`), forms with `=csrf()` and `=methodField('PUT')` (see the `caligrafy` skill). Without views, `view('Book/index', ...)` still works for JSON clients but browsers get a Phug error - use `api()` for JSON-only resources.

## Finish
1. `php -l` every new PHP file (the plugin hook also does it on save).
2. Offer to run the SQL with `/caligrafy-quill:db application/models/<Entity>/create_db.sql`.
3. Show test calls: `curl -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" <base>/books` and a POST with `-H "Content-Type: application/json" -d '{...}'` (base: `http://localhost:8080` under Docker, else `http://localhost/<APP_ROOT>`). Read `API_KEY` from `.env` into the shell variable; do not print it.
