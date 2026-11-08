# 4. Models

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `create()/update()/save()` keep `0`, `''` and `false` and skip only `null`; `search()` binds the keywords; `all()` clauses are raw SQL (include the keywords) and accept `'bindings'` for `?` placeholders (gotchas G7, G2, G8). Projects on an older framework still have the old behavior.
> - FK constraint names must be unique per database and FK columns must match the parent id type (G10).

A **model** is a PHP class that stands for one table: one `Book` object is one row of `books`. Your capabilities
read and write rows through models and the [query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries)
(`Book::where('author_id', 1)->get()`, `Book::find(3)`, `Book::insert($input)`), and every page, REST
call, Vue app and AI agent reaches the data through those capabilities. This page makes the tables of the Bookshelf
(authors, books, tags, loans) and their models.

> #### (video) [CRUD operations on a database](https://www.youtube.com/watch?v=kNdoaSi6pcE&list=PLsJG81fLk3_UNfTuqjCbcz1S_xbram35e)
>
> An older video: it uses a controller. Today the same work goes in a capability, as below.

## With Claude

Ask for the resource with the `new-resource` skill of the Claude Code plugin, with its fields:

```text
/caligrafy-quill:new-resource Book title:string published_year:int summary:text author:fk
```

Claude writes:
+ `application/models/Book/create_db.sql`: the table, `CREATE TABLE IF NOT EXISTS`, its foreign key named
  `fk_<table>_<column>`;
+ `application/models/Book/Book.php`: the model, one public property per column;
+ the capabilities `list_books`, `get_book`, `create_book`, `update_book` and `delete_book` in
  `application/services/Books/`, which use the query builder;
+ their routes in `application/config/routing/web.php` (REST, pages, forms), and the views when you want pages.

It then offers to run the SQL (`/caligrafy-quill:db application/models/Book/create_db.sql`). When the table already
holds data, it writes a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations) instead.

**Check** before you say OK:
+ the primary key is `id`, each foreign key column is `<parent>_id` with the same type as the parent's `id`
  (`INT UNSIGNED` for `INT UNSIGNED`);
+ the model has a property for every column, and no `find()` method of its own;
+ the table name is the class name in lower case plus `s` (`Book` -> `books`), or the model declares
  `protected static $tableName`;
+ `php caligrafer.php capabilities` lists the new capabilities, and `php caligrafer.php call get_book id=1` answers.

## By hand

You have a project made with the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) and its
Docker server. Run every command in the container: `docker compose exec dev-box php caligrafer.php ...` (without
Docker, drop `docker compose exec dev-box`).

1. **Check the database settings.** Open `.env`. The Quickstart wrote them; they must read:
    ```text
    DB_ACTIVATE=true
    DB_CONNECTION=mysql
    DB_HOST=mysql
    DB_PORT=3306
    DB_DATABASE=caligrafy
    DB_USERNAME=root
    DB_PASSWORD=root
    ```
    `DB_HOST=mysql` and `DB_PORT=3306` are the database inside the Docker network. Without Docker, use your MySQL's
    host and port (`127.0.0.1`, not `localhost`, so that the port is used). Then run
    `docker compose exec dev-box php caligrafer.php doctor --only database`: every line must be `OK`.

2. **Write the tables.** Create `application/models/Bookshelf/create_db.sql`:
    ```sql
    /* The Bookshelf tables. CREATE TABLE IF NOT EXISTS: safe to run again, it never drops anything.
     * Run it with: php caligrafer.php sql application/models/Bookshelf/create_db.sql
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

    CREATE TABLE IF NOT EXISTS tags (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(60) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_tags_name (name)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

    CREATE TABLE IF NOT EXISTS books_tags (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        book_id INT UNSIGNED NOT NULL,
        tag_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_books_tags (book_id, tag_id),
        CONSTRAINT fk_books_tags_book_id FOREIGN KEY (book_id) REFERENCES books (id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_books_tags_tag_id FOREIGN KEY (tag_id) REFERENCES tags (id) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

    CREATE TABLE IF NOT EXISTS loans (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        book_id INT UNSIGNED NOT NULL,
        borrower VARCHAR(100) NOT NULL,
        due_on DATE NOT NULL,
        returned_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_loans_book_id (book_id),
        CONSTRAINT fk_loans_book_id FOREIGN KEY (book_id) REFERENCES books (id) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
    ```
    The names follow the conventions the [relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships)
    read: a primary key `id`, a foreign key `<parent in the singular>_id` (`author_id`) of the same type as the
    parent's `id`, and the table that links books and tags named `books_tags` with `book_id` and `tag_id`. Each
    constraint name is unique in the database (`fk_<table>_<column>`).

3. **Create them.** Run:
    ```bash
    docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
    ```
    ```text
     application/models/Bookshelf/create_db.sql run on caligrafy
    ```
    Online, the deploy step runs this file on its own when it is new or changed. From now on, a change to one of
    these tables is a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations): never edit the
    file once it ran.

4. **Add sample rows.** Create `application/models/Bookshelf/seed.sql`:
    ```sql
    /* Sample rows for the Bookshelf. INSERT IGNORE skips the rows whose id exists, so it can run twice.
     * Run it with: php caligrafer.php sql application/models/Bookshelf/seed.sql
     */

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

    INSERT IGNORE INTO tags (id, name) VALUES (1, 'fantasy'), (2, 'science fiction'), (3, 'classic');

    INSERT IGNORE INTO books_tags (id, book_id, tag_id) VALUES
        (1, 1, 1), (2, 1, 3), (3, 2, 2), (4, 2, 3), (5, 3, 2), (6, 4, 2), (7, 5, 3);

    INSERT IGNORE INTO loans (id, book_id, borrower, due_on, returned_at) VALUES
        (1, 1, 'Ana', '2026-11-20', NULL),
        (2, 3, 'Ben', '2026-10-30', '2026-10-28 10:00:00'),
        (3, 3, 'Chloe', '2026-11-25', NULL);
    ```
    Run it: `docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/seed.sql`
    (it prints ` application/models/Bookshelf/seed.sql run on caligrafy`). The deploy step never runs a `seed.sql`.

5. **Write the models.** One file per table, in `application/models/Bookshelf/`, one public property per column.

    `application/models/Bookshelf/Author.php`:
    ```php
    <?php

    use Caligrafy\Model;

    // One Author = one row of the authors table
    class Author extends Model
    {
        public $id;
        public $name;
        public $bio;
        public $created_at;
        public $modified_at;
    }
    ```
    `application/models/Bookshelf/Book.php`:
    ```php
    <?php

    use Caligrafy\Model;

    // One Book = one row of the books table
    class Book extends Model
    {
        public $id;
        public $title;
        public $published_year;
        public $summary;
        public $author_id;
        public $created_at;
        public $modified_at;
    }
    ```
    `application/models/Bookshelf/Tag.php`:
    ```php
    <?php

    use Caligrafy\Model;

    // One Tag = one row of the tags table
    class Tag extends Model
    {
        public $id;
        public $name;
        public $created_at;
        public $modified_at;
    }
    ```
    `application/models/Bookshelf/Loan.php`:
    ```php
    <?php

    use Caligrafy\Model;

    // One Loan = one row of the loans table: who borrowed which book, until when
    class Loan extends Model
    {
        public $id;
        public $book_id;
        public $borrower;
        public $due_on;
        public $returned_at;
        public $created_at;
        public $modified_at;
    }
    ```
    `Book::query()` and `Book::find()` read the table `books`: the class name in lower case plus `s`. The models need
    no relationship methods: the [relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships)
    are loaded with `with()`.

6. **Read a row in a capability.** Only capabilities touch models. Create the
   capability `get_book` with `docker compose exec dev-box php caligrafer.php service Bookshelf/GetBook --scope books:read --read-only`,
   then make `application/services/Bookshelf/GetBook.php` read:
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
            }
        }
    }
    ```
    (Your dates differ.) The capability returns a `Book` with its `author` ([Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships));
    every channel gets it as plain data. `id=99` gives
    `{"error": {"code": "not_found", "message": "There is no book #99"}}`. Writing a capability, and serving it to
    pages, REST and agents, is on [Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service).

**Check:**
+ `docker compose exec mysql mysql -uroot -proot caligrafy -e "SHOW TABLES"` lists `authors`, `books`, `books_tags`,
  `loans` and `tags` (beside the `caligrafy_*` framework tables);
+ `docker compose exec dev-box php caligrafer.php call get_book id=3` returns *Kindred*;
+ each model has exactly the columns of its table.

## Reference

**Reading: the query builder on the model class.** Every call returns models of the class (`Book`), on its table.
The full list is on [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries).

| Call | Returns |
|---|---|
| `Book::find(3)` | the `Book` of id 3, or `null` |
| `Book::where('author_id', 1)->get()` | a list of `Book` (empty when none) |
| `Book::where('title', 'Kindred')->first()` | the first `Book`, or `null` |
| `Book::with('author')->find(3)` | the book, with `author` (an array) |
| `Book::where('author_id', 1)->count()` | a number |
| `Book::query()->orderBy('title')->paginate(20, $page)` | `['data', 'total', 'page', 'per_page', 'pages']` |
| `Book::query()` | the query builder on `books`, to build on |

The calls a model class forwards to the query builder: `query`, `find`, `select`, `where`, `orWhere`, `whereIn`,
`whereNotIn`, `whereNull`, `whereNotNull`, `whereBetween`, `orderBy`, `latest`, `oldest`, `limit`, `offset`, `with`,
`withCount`, `first`, `value`, `pluck`, `count`, `exists`, `paginate`, `chunk`, `get`, `insert`, `input`, `arrays`.
`search()` is not one of them: write `Book::query()->search([...], $words)`.

**Writing: the query builder on the model class.** The full list is on
[Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries#write).

| Call | What it does | Returns |
|---|---|---|
| `Book::insert($input)` | one `INSERT` of the values of `$input` (an array; arrays and objects in it are written as JSON) | the id of the new row |
| `Book::where('id', $id)->update($changes)` | `UPDATE ... WHERE id = ?` | how many rows changed |
| `Book::where('id', $id)->delete()` | `DELETE ... WHERE id = ?` | how many rows were deleted (`0`: no such row) |
| `Book::where('id', $id)->increment('stock', 2)` | adds to a number column (`decrement()` too) | how many rows changed |

`update()`, `delete()`, `increment()` and `decrement()` refuse to run without a `where()`, so a missing condition never
changes every row. Every value is bound. A capability that creates a book returns it read back:
`$id = Book::insert($input); return Book::with('author')->find($id);`.

**The table of a model**

| The model | `Book::query()` reads |
|---|---|
| `class Book` | `books` (the class name in lower case + `s`) |
| `class Category` | `categories` (consonant + `y` -> `ies`; `s`, `x`, `z`, `ch`, `sh` -> `es`) |
| `class BookReview` | `bookreviews` (no `_`): declare `protected static $tableName = 'book_reviews';` |
| `class Person` with `protected static $tableName = 'people';` | `people` |

**Database settings** (`.env`)

| Setting | Default in `.env.example` | Meaning |
|---|---|---|
| `DB_ACTIVATE` | `false` | `true` connects; without it every query fails with `[ERROR 2003]` |
| `DB_CONNECTION` | `mysql` | the PDO driver |
| `DB_HOST` | `localhost` | `mysql` with the Docker server; `127.0.0.1` to reach a port over TCP |
| `DB_PORT` | `3306` | added to the connection unless `DB_HOST` already has `;port=` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `databasename`, `root`, `root` | the database and its account |
| `DB_TIMEZONE` | empty | empty: MySQL uses PHP's `SERVER_TIMEZONE`; `server` keeps MySQL's own |

## Other ways

These still work. Use them only in older code; the prescriptive way is in brackets.

+ **A model in a controller** [a capability]: `$this->associate('Book', 'books');` then `$this->model->find(1)`. See
  [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers).
+ **The methods of a model object** [`Book::insert()`, `Book::where('id', $id)->update()` and `->delete()` above]:
  `new Book('books')` gives an object on the table you name, and its methods write one row:

    | Method | What it does | Returns |
    |---|---|---|
    | `(new Book('books'))->create($input)` | `INSERT` of the values of `$input` (an array or an object) | the new `Book`, read back |
    | `(new Book('books'))->update($changes, $id)` | `UPDATE ... WHERE id = ?`; nothing when the row does not exist or nothing is left to save | the `Book` read back, or `null` |
    | `(new Book('books'))->delete($id)` | `DELETE ... WHERE id = ?` | an empty array |
    | `$book->save()` | `update()` when `$book->id` is set, otherwise `create()` | the `Book` read back |
    | `$book->toArray()` | the object as an array | array |
    | `(new Book('books'))->find($id)` | `SELECT * ... WHERE id = ?` (the older form of `Book::find`) | `Book` or `null` |

    `create()`, `update()` and `save()` write only the columns the table has (a helper property such as
    `public $errors` is left out), and only values that are not `null` (`0`, `''` and `false` are written; `false` as
    `0`). A property left `null` keeps the column's current value or its default. Arrays and objects are written as
    JSON. Every value is bound; the column names must be letters, digits and `_`. On such an object,
    `$book->where(...)` uses the table it was given.
+ **`all()` with SQL clauses** [`Book::where(...)->orderBy(...)->limit(...)->get()`]: the clauses are added to the SQL as
  they are, so they hold their keywords, and values go in `bindings`, never in the text:
    ```php
    $books = (new Book('books'))->all(array(
        'condition' => 'WHERE author_id = ?',
        'order' => 'ORDER BY title',
        'limit' => 'LIMIT 10',
        'bindings' => array(1)
    ));
    ```
+ **`search($columns, $pattern)`** [`Book::query()->search(['title', 'summary'], $words)`]:
  `(new Book('books'))->search('title,summary', '%shadow%')` takes a `LIKE` pattern as it is (`%` and `_` are
  wildcards); the columns must be letters, digits and `_`.
+ **SQL the query builder does not write** (a join with `GROUP BY`, a subquery, a database function): a method of the
  model with `Database::execute()` and `?` placeholders:
    ```php
    public function findByIsbn($isbn)
    {
        $rows = Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE isbn = ? LIMIT 1', [$isbn]));
        return !empty($rows[0]) ? $this->arrayToModel($rows[0]) : null;
    }
    ```
+ **Relationship methods in the model** (`hasOne()`, `hasMany()`, `belongsToMany()`) [`with()`]: see
  [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships#methods).

## Common mistakes

+ **A `find()` method in your model** (G122): `$book->find()` then runs yours, and `Book::find(3)` fails with
  "Non-static method cannot be called statically". Leave `find()` out, or call `Book::query()->find(3)`.
+ **A table name the model cannot guess** (G123): `BookReview` reads `bookreviews`. Declare
  `protected static $tableName = 'book_reviews';` (protected or public; a private one is not seen).
+ **A value written into SQL** (G2, G8): `all(['condition' => "WHERE title = '$title'"])` lets a visitor change the
  query. Use the query builder, or `?` and `bindings`.
+ **`DB_HOST=localhost` with a port** (G3): PDO then uses the MySQL socket and ignores `DB_PORT`. Use `127.0.0.1`.
+ **A foreign key of another type** (G10): `INT` toward `INT UNSIGNED` fails with "incompatible". Give it the parent's type.
+ **Editing `create_db.sql` after it ran**: a database that has the table keeps the old one. Write a
  [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).

## Related pages

+ [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships): authors, books, tags, loans together
+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries): filters, paging, counts, writes, transactions
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations): changing a table that holds data
+ [Build once: services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
+ [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#sql): `sql`, `migrate`, `call`
