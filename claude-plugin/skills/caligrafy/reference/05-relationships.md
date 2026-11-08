# 5. Relationships

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `belongsTo()` is implemented as `hasMany()` (returns an array of rows whose FK points at this model) - for the inverse of one-to-many use `hasOne()` (gotchas G9).
> - `hasOne()` reads `<model>_id` on the CALLING model's table. `belongsToMany()` pivots copy the referenced id types and use unique constraint names (G10, fixed in the security release).

Tables point at each other: a book has one author (`books.author_id`), an author has many books, a book has many
loans, and books and tags are linked many to many through `books_tags`. A capability loads those **related rows** with
`with()` of the [query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries): one query for the books,
one more per relation, never one query per book. The relations follow the names of your tables, so the models need no
code for them. This page uses the Bookshelf tables of [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models).

```php
$book    = Book::with('author', 'loans', ['tags' => ['pivot' => true]])->find(3);   // its author, its loans, its tags
$authors = Author::withCount('books')->orderBy('name')->get();                       // each author with books_count
```

> #### (video) [Relationships](https://www.youtube.com/watch?v=o6knV88iCkw&list=PLsJG81fLk3_UNfTuqjCbcz1S_xbram35e)
>
> An older video: it shows the relationship methods of the model (under "Other ways" below).

## With Claude

Ask for the capability with the `new-capability` skill, and say which related rows it returns:

```text
/caligrafy-quill:new-capability get_book books:read read-only: one book with its author, its loans and its tags
```

Claude writes `application/services/Bookshelf/GetBook.php` (or the folder of your resource), reads the rows with
`Book::with(...)`, and never queries in a loop. For a resource with a parent (`/caligrafy-quill:new-resource Book ... author:fk`),
it writes the foreign key `author_id` and its constraint in `create_db.sql`.

**Check** before you say OK:
+ the capability reads related rows with `with()` or `withCount()`, not a query inside a `foreach`;
+ many-to-many relations say `['pivot' => true]` (or the pivot's name);
+ `php caligrafer.php call get_book id=3` shows the related rows under their names (`author`, `loans`, `tags`).

## By hand

Run every command in the Docker container: `docker compose exec dev-box php caligrafer.php ...` (without Docker, drop
`docker compose exec dev-box`).

1. **A book with its author, its loans and its tags.** Make `application/services/Bookshelf/GetBook.php` read:
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Service;
    use Caligrafy\ServiceError;

    #[Capability(
        name: 'get_book',
        description: 'Returns one book by its id, with its author, its tags and its loans.',
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
            $book = Book::with('author', 'loans', array('tags' => array('pivot' => true)))->find($input['id']);
            if (!$book) {
                throw ServiceError::notFound('There is no book #' . $input['id']);
            }
            return $book;
        }
    }
    ```
    Run `docker compose exec dev-box php caligrafer.php call get_book id=3`:
    ```json
    {
        "data": {
            "id": 3,
            "title": "Kindred",
            "published_year": 1979,
            "summary": "A young woman is pulled back in time to a plantation in Maryland.",
            "author_id": 2,
            "created_at": "2026-11-07 23:23:48",
            "modified_at": "2026-11-07 23:23:48",
            "author": {
                "id": 2,
                "name": "Octavia E. Butler",
                "bio": "American science fiction author, the first to receive a MacArthur fellowship.",
                "created_at": "2026-11-07 23:23:48",
                "modified_at": "2026-11-07 23:23:48"
            },
            "loans": [
                {"id": 2, "book_id": 3, "borrower": "Ben", "due_on": "2026-10-30", "returned_at": "2026-10-28 10:00:00", "created_at": "2026-11-07 23:23:48", "modified_at": "2026-11-07 23:23:48"},
                {"id": 3, "book_id": 3, "borrower": "Chloe", "due_on": "2026-11-25", "returned_at": null, "created_at": "2026-11-07 23:23:48", "modified_at": "2026-11-07 23:23:48"}
            ],
            "tags": [
                {"id": 2, "name": "science fiction", "created_at": "2026-11-07 23:23:48", "modified_at": "2026-11-07 23:23:48"}
            ]
        }
    }
    ```
    (Your dates differ.) Four queries ran: the book, then its author, its loans and its tags, each with
    `WHERE ... IN (...)`.

2. **Every author with the number of their books.** Run
   `docker compose exec dev-box php caligrafer.php service Bookshelf/ListAuthors --scope books:read --read-only`,
   then make `application/services/Bookshelf/ListAuthors.php` read:
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Service;

    #[Capability(
        name: 'list_authors',
        description: 'Lists every author of the Bookshelf, sorted by name, with books_count: how many books each one has in the catalog.',
        scope: 'books:read',
        readOnly: true,
        public: true
    )]
    class ListAuthors extends Service
    {
        public function handle(array $input, Context $context)
        {
            return Author::withCount('books')->orderBy('name')->get();
        }
    }
    ```
    `docker compose exec dev-box php caligrafer.php call list_authors` returns the three authors, each with
    `books_count`: Italo Calvino `1`, Octavia E. Butler `2`, Ursula K. Le Guin `2`. `withCount()` ran one
    `GROUP BY` query for all of them.

3. **Link and unlink a tag.** The rows of a many-to-many relation live in the pivot table `books_tags`: add and remove
   them with the query builder. Run
   `docker compose exec dev-box php caligrafer.php service Bookshelf/TagBook --scope books:write`, then make
   `application/services/Bookshelf/TagBook.php` read:
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Database;
    use Caligrafy\Service;

    #[Capability(
        name: 'tag_book',
        description: 'Adds a tag to a book (nothing changes when the book has it already) and returns the book with all its tags.',
        scope: 'books:write'
    )]
    class TagBook extends Service
    {
        public $rules = array(
            'book_id' => 'required|integer|exists,books.id',
            'tag_id' => 'required|integer|exists,tags.id'
        );

        public function handle(array $input, Context $context)
        {
            $link = array('book_id' => $input['book_id'], 'tag_id' => $input['tag_id']);
            if (!Database::table('books_tags')->where($link)->exists()) {
                Database::table('books_tags')->insert($link);
            }
            return Book::with(array('tags' => array('pivot' => true)))->find($input['book_id']);
        }
    }
    ```
    Run `docker compose exec dev-box php caligrafer.php service Bookshelf/UntagBook --scope books:write`, then make
    `application/services/Bookshelf/UntagBook.php` read:
    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Database;
    use Caligrafy\Service;

    #[Capability(
        name: 'untag_book',
        description: 'Removes a tag from a book and returns the book with the tags it has left.',
        scope: 'books:write'
    )]
    class UntagBook extends Service
    {
        public $rules = array(
            'book_id' => 'required|integer|exists,books.id',
            'tag_id' => 'required|integer'
        );

        public function handle(array $input, Context $context)
        {
            Database::table('books_tags')->where(array('book_id' => $input['book_id'], 'tag_id' => $input['tag_id']))->delete();
            return Book::with(array('tags' => array('pivot' => true)))->find($input['book_id']);
        }
    }
    ```
    Run `docker compose exec dev-box php caligrafer.php call tag_book book_id=3 tag_id=3`: *Kindred* now has the tags
    `science fiction` and `classic`. Running it again changes nothing. `call untag_book book_id=3 tag_id=3` leaves
    `science fiction`. `call tag_book book_id=3 tag_id=9` answers `"tag_id": "There is no such tag"`.

**Check:**
+ `call get_book id=3` has `author`, `loans` and `tags`;
+ `call list_authors` gives each author a `books_count`;
+ `docker compose exec mysql mysql -uroot -proot caligrafy -e "SELECT * FROM books_tags WHERE book_id = 3"` shows
  the links you made.

## Reference

### How `with()` reads a name

`with()` follows the name you give and the table it is called on:

| Name | Called on | Kind | It reads | You get |
|---|---|---|---|---|
| `author` (singular) | `books` | belongs to | `books.author_id` -> `authors.id` | one row, or `null` |
| `books` (plural) | `authors` | has many | `books.author_id` = `authors.id` | a list, or `[]` |
| `loans` (plural) | `books` | has many | `loans.book_id` = `books.id` | a list, or `[]` |
| `['tags' => ['pivot' => true]]` | `books` | many to many | `books_tags.book_id` = `books.id`, `books_tags.tag_id` = `tags.id` | a list, or `[]` |
| `book.author` | `loans` | nested: the relation of a relation | `loans.book_id` -> `books.id`, then `books.author_id` -> `authors.id` | `book` with its `author` |
| `['current_loan' => ['type' => 'hasOne', 'table' => 'loans']]` | `books` | has one | `loans.book_id` = `books.id` | the first row, or `null` |

+ Singular and plural: `author` -> `authors`, `category` -> `categories`, `box` -> `boxes`. An irregular plural
  (`person`/`people`) needs `'table'`.
+ The related rows are **arrays**, also under models: `$book->author['name']`, `$book->tags[0]['name']`.
+ The key must be in the rows: `Book::select('id', 'title')->with('author')` has no `author_id`, so `author` is `null`.
+ Several names at once: `with('author', 'loans')`; one name's settings: `with(['author' => [...]])`; both:
  `with('author', ['tags' => ['pivot' => true]])`.

### Many to many: the pivot

The pivot of `books` and `tags` is `books_tags` (`<this table>_<related table>`), with `book_id` and `tag_id`. When
that table does not exist but `tags_books` does, `with()` uses `tags_books` (it tries `books_tags` first, then
remembers). `['pivot' => 'book_tag_links']` names another pivot. Write the pivot in `create_db.sql`, with a
`UNIQUE KEY` on its two columns, as on [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models). Add and
remove links with `Database::table('books_tags')->insert([...])` and `->where([...])->delete()`, as in step 3.

### When your names differ: the settings

```php
Book::with(['writer' => ['table' => 'authors', 'foreign_key' => 'author_id']])->find(3);   // $book->writer
Book::with(['current_loan' => ['type' => 'hasOne', 'table' => 'loans', 'query' => function ($q) { $q->whereNull('returned_at'); }]])->get();
```

| Setting | Meaning | Default |
|---|---|---|
| `type` | `belongsTo`, `hasOne`, `hasMany` or `belongsToMany` | singular name: `belongsTo`; plural: `hasMany`; with `pivot`: `belongsToMany` |
| `table` | the related table | the name in the plural (`belongsTo`, `hasOne`), the name itself (`hasMany`, `belongsToMany`) |
| `foreign_key` | belongs to: the column of this table; has one/many: the column of the related table; many to many: the pivot's column toward this table | `<name>_id` (belongs to), `<this table in the singular>_id` (the others) |
| `local_key` | the column of this table the others point at | `id` |
| `owner_key` | the column of the related table that is pointed at | `id` |
| `pivot` | `true` (the conventional pivot) or its name | none |
| `related_key` | the pivot's column toward the related table | `<name in the singular>_id` |
| `query` | a function that narrows the related rows: `function ($q) { $q->where('published_year', '<', 1970); }` | none |

A function alone is the `query` setting: `with(['loans' => function ($q) { $q->whereNull('returned_at'); }])`. An unknown
setting, or a name that is not letters, digits and `_`, is refused before any query runs.

### Counting: `withCount()`

```php
Author::withCount('books')->get();                                   // books_count on each author
Database::table('tags')->withCount(['books' => ['pivot' => true]])->pluck('books_count', 'name');
// ['fantasy' => 1, 'science fiction' => 3, 'classic' => 3]
Author::withCount(['books' => function ($q) { $q->where('published_year', '<', 1970); }])->pluck('books_count', 'name');
// ['Ursula K. Le Guin' => 2, 'Octavia E. Butler' => 0, 'Italo Calvino' => 0]
```

`withCount()` counts plural relations only (has many, many to many): `withCount('author')` is refused ("counts a
plural relation"). A row with nothing to count gets `0`.

## Other ways: the relationship methods of a model

Before 2.19, relations were methods of the model. They still work, for one model object at a time; `with()` replaces
them, for one row or a thousand.

```php
class Book extends Model
{
    // ... the properties

    public function author()   // the author: books.author_id -> authors.id
    {
        return $this->hasOne('Author', 'authors');
    }

    public function loans()    // the loans: loans.book_id = books.id
    {
        return $this->hasMany('Loan', 'loans');
    }

    public function tags()     // the tags, through books_tags (book_id, tag_id)
    {
        return $this->belongsToMany('Tag', 'tags');
    }
}

$book = (new Book('books'))->find(1);
$book->author()->name;           // 'Ursula K. Le Guin' (a model, or null)
$book->loans();                  // a list of Loan
$book->tags();                   // a list of Tag
$book->attach('tags', 2);        // adds the link 1-2 (call tags() first); returns the tags again
$book->detach('tags', 2);        // removes it
```

| Method | It reads | Returns |
|---|---|---|
| `hasOne($model, $table, $foreignKey = null, $localKey = null)` | the foreign key **on this model's table**: `books.author_id` -> `authors.id` (`$foreignKey` names this table's column, `$localKey` the other table's) | a model, or `null` |
| `hasMany($model, $table, $foreignKey = null, $localKey = null)` | `<table>.<this class>_id` = this id (`$foreignKey` names that column, `$localKey` a property of this model to use instead of `id`) | a list of models |
| `belongsTo(...)` | the same as `hasMany()`: it is **not** the inverse of `hasMany()`. For a child's parent, use `hasOne()` | a list |
| `belongsToMany($model, $table, $foreignKeyLocal = null, $foreignKeyJoin = null)` | the pivot `<this table>_<table>` (or `<table>_<this table>` when that one exists); its columns are `<this class>_id` and `<model>_id`. It **creates the pivot** with `CREATE TABLE IF NOT EXISTS` the first time | a list of models; `$book->pivot->tags->all` holds the rows |
| `attach($table, $id)`, `detach($table, $id)` | add or remove a pivot row; call the relationship method first | the relationship again |

The method's name is up to you; it is never saved as a column. The foreign key names come from the class name in lower
case (`Book` -> `book_id`).

## Common mistakes

+ **A query inside a `foreach`** (the N+1 problem): 50 books and their authors become 51 queries. In development the
  PHP error log says "Probable N+1 query" with the file and line (G127). Load them with `with('author')`.
+ **`with('tags')` without `pivot`** (G124): `tags` is plural, so it is read as has many: `tags.book_id`, which fails
  with "Unknown column 'book_id'". Write `with(['tags' => ['pivot' => true]])`.
+ **`belongsTo()` as the inverse of `hasMany()`** (G9): it returns the children, like `hasMany()`. Use `with('author')`
  (or `hasOne()`).
+ **A foreign key of another type than the `id` it points at** (G10): MySQL refuses the constraint. Give both
  `INT UNSIGNED`.
+ **Reading `$book->author->name`**: related rows are arrays. Write `$book->author['name']`.

## Related pages

+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries#eager): `with()` among the other calls
+ [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models): the tables and the models
+ [Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation#database-rules): `exists` and `unique`
+ [Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service): writing a capability
