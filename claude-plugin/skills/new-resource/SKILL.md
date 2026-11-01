---
name: new-resource
description: Scaffold a Caligrafy Quill resource capabilities-first - MySQL create_db.sql script, Caligrafy\Model, the CRUD capabilities (list, get, create, update, delete) written once with php caligrafer.php service, their REST routes (Route::capability), and pages with Route::page - following the framework conventions (only capabilities touch models).
when_to_use: The user asks to add an entity, table, model, CRUD, REST endpoint or resource to a Caligrafy Quill project, e.g. "/caligrafy-quill:new-resource Book title:string author:string publisher:fk".
argument-hint: "<Entity> [field:type ...] [parent:fk]"
---

# Scaffold a Caligrafy resource

Arguments: `$ARGUMENTS` (entity name, then optional `field:type` pairs; `name:fk` = foreign key to model `Name`).
If fields are missing, infer sensible ones from the conversation or ask once.

Read first: the `caligrafy` skill's [api-reference.md](../caligrafy/reference/api-reference.md) (Model, Route, Build once)
and [gotchas.md](../caligrafy/reference/gotchas.md) G2, G4, G6, G7, G10, G19, G23, G52, G76, G83.

**Only capabilities touch models**: the model is read and written by the capabilities of this resource, never by a
controller or a page. Pages get their data with `Route::page`; a controller is written only for a classic form post,
and it calls the capabilities.

## Preconditions
1. Confirm this is an initialized Quill project: `application/config/routing/web.php` exists. If only `framework/settings/application` exists, tell the user to run `php caligrafer.php initialize` (it deletes `.git` - ask first).
2. Choose names. Entity `Book` -> model class `Book`, table `books` (lowercase plural, ask if irregular), capabilities `list_books`, `get_book`, `create_book`, `update_book`, `delete_book` in `application/services/Books/`, scopes `books:read` / `books:write`. Grep `application/` for `class Book\b`, `class ListBooks\b`... and `php caligrafer.php capabilities` for the names: class names are global (G19) and capability names unique (G80) - pick another name on a clash.

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

### 3. The capabilities: `application/services/<Entities>/`
One command per capability (the scope is required, G76):
```bash
php caligrafer.php service Books/ListBooks --scope books:read --read-only
php caligrafer.php service Books/GetBook --scope books:read --read-only
php caligrafer.php service Books/CreateBook --scope books:write
php caligrafer.php service Books/UpdateBook --scope books:write
php caligrafer.php service Books/DeleteBook --scope books:write
```
Fill each one as `/caligrafy-quill:new-capability` says: a `description` written for an AI agent, `$rules` (+ `$filters`,
`$descriptions`) for every input, bound queries only, `ServiceError` refusals, `destructive: true` on delete, `level: 1`
when only administrators may do it. For example:
```php
#[Capability(name: 'get_book', description: 'Returns one book by its id, with its title, author and publisher id.', scope: 'books:read', readOnly: true)]
class GetBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book');

    public function handle(array $input, Context $context)
    {
        $book = (new Book('books'))->find($input['id']);
        if (!$book) {
            throw ServiceError::notFound("No book #{$input['id']}");
        }
        return $book;
    }
}
```
`create_book`: `(new Book('books'))->create($input)`; `update_book`: refuse a missing id with `notFound`, then
`->update($changes, $input['id'])` with only the inputs given; `list_books`: `->all(['order' => 'ORDER BY created_at DESC'])`
(values in `'bindings'`, G8); `delete_book`: `->delete($input['id'])`, return `['deleted' => (int) $input['id']]`.

### 4. Routes in `application/config/routing/web.php`
Insert inside the `try` block, after the existing `/` route and before `Route::run();`, with a comment header:
```php
    // BOOKS: one set of capabilities for the pages, REST, the JS client and AI agents
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('PUT', '/api/books/{id}', 'update_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    Route::page('/books', 'Books/index', ['data' => ['books' => 'list_books']]);
    Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);
```
Keep one `Route::capabilities('/capabilities');` and one `Route::mcp('/mcp');` in the file (add them if missing), then
`php caligrafer.php client` for the JS client. A capability that refuses a page's data sends a visitor to sign in or
answers 404/403 (G83); add `'notFound' => 'Books/missing'` to show your own 404 view. Only `readOnly` capabilities can
be page data, and the view never gets the query string.

### 5. Views (only if the user wants HTML pages)
`application/views/Books/index.pug` and `show.pug`, built with the page components of [ui-guidelines.md](../caligrafy/reference/ui-guidelines.md) (`main.cq-page > .cq-card`: `.cq-card-head`, `.cq-form` with labeled fields and `btn btn-primary cq-submit`, `.cq-list > .cq-item` rows with `.cq-actions`, `.cq-empty`; follow its checklist), `extends ../default/master/layoutblocks`, `block content`. They receive the `data` of their route as plain arrays (`each $book in $books`, `$book['title']`), `$authorized` and `$user`. Changes go through the capabilities: from the page's script with the JS client (`createBook({...})`, like the Tasks example of `quickstart --example`), or, for a classic form, a thin controller:
```php
use Caligrafy\Controller;
use Caligrafy\ServiceError;

class BookFormController extends Controller
{
    public function store()
    {
        try {
            $book = $this->call('create_book');   // the posted fields; never new Book() here
            redirect('/books/' . $book['id']);   // capabilities return plain arrays
        } catch (ServiceError $e) {
            return view('Books/index', array('errors' => $e->fields, 'books' => $this->call('list_books', array())));
        }
    }
}
```
with `Route::post('/books', 'BookFormController@store');` and `=csrf()` in the form.

## Finish
1. `php -l` every new PHP file (the plugin hook also does it on save).
2. Offer to run the SQL with `/caligrafy-quill:db application/models/<Entity>/create_db.sql`.
3. Check every capability: `php caligrafer.php capabilities`, then `php caligrafer.php call list_books` and `php caligrafer.php call create_book title=...` (an invalid input gives the field messages). Show REST calls with a token: `php caligrafer.php token create "test" --scopes books:read,books:write`, then `curl -H "Accept: application/json" -H "Authorization: Bearer <token>" <base>/api/books` (base: `http://localhost:8080` under Docker, else `http://localhost/<APP_ROOT>`); revoke it after (`token revoke <number>`, G77).
4. Ask the `caligrafy-reviewer` agent to review the new files.
