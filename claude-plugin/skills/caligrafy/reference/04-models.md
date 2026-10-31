# 4. Models

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `create()/update()/save()` keep `0`, `''` and `false` and skip only `null`; `search()` binds the keywords; `all()` clauses are raw SQL (include the keywords) and accept `'bindings'` for `?` placeholders (gotchas G7, G2, G8). Projects on an older framework still have the old behavior.
> - FK constraint names must be unique per database and FK columns must match the parent id type (G10).

> **Where this fits.** A model is the data layer under your capabilities: one class per table. Capabilities use models in `handle()` (`(new Book('books'))->create($input)`), and every experience reaches the data through them. Create a table with its `create_db.sql` and `php caligrafer.php sql`, as in [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability#table).

This framework is agnostic and does not engage in creating databases and database tables. The database for your application should be created using the language of your choice. This framework uses a PDO adapter to interface with your database tables. We recommend using MySQL given its tight integration with PHP but other databases can be used at your own discretion.

> #### (video) [CRUD operations on a database](https://www.youtube.com/watch?v=kNdoaSi6pcE&list=PLsJG81fLk3_UNfTuqjCbcz1S_xbram35e)
>
> In this video, we will learn how to create a database model, how to link it to Caligrafy and how to perform CRUD operations using Controllers.

## Activating Databases
In order to start using the database, you need to activate it. The `.env` file is where the database credentials need to be entered. Note that the `DB_ACTIVATE` variable in the `.env` file needs to be set to true to activate the connection.

```php

DB_ACTIVATE=true
DB_CONNECTION=mysql
DB_HOST=localhost # use `mysql` instead with Docker
DB_PORT=3306
DB_DATABASE=your-db-name
DB_USERNAME=your-db-username
DB_PASSWORD=your-db-password

```

> `DB_PORT` is added to the connection unless `DB_HOST` already contains a `;port=` setting. With Docker, use `DB_HOST=mysql` and `DB_PORT=3306` (the port inside the Docker network).

## Relational Databases
Creating databases and database tables is achieved through the database language that you normally use. This framework does not dictate how your database should be created. The examples in this documentation are all using MySQL. MySQL is the SQL language that we recommend using.

+ Your database needs to be created with all its tables before creating the `Models` in this framework:
```mysql
/* Example of a MySQL script without relationships */

CREATE TABLE IF NOT EXISTS publishers (
    id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,
    publishername varchar(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS books (
    id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    author varchar(255) NOT NULL,
    publisher_id INT(25) UNSIGNED NOT NULL, 
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
```

+ The `One-to-One` and `One-to-Many` relationships need to have the appropriate foreign keys created in the database:
```mysql
/* Example of mySQL script with relationships */

CREATE TABLE IF NOT EXISTS books (
    id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    author varchar(255) NOT NULL,
    publisher_id INT(25) UNSIGNED NOT NULL, 
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    PRIMARY KEY (id), 
    CONSTRAINT fk_books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
```
+ Foreign key constraint names must be unique in the whole database (`fk_<table>_<column>` is a good habit), and a foreign key column must have exactly the same type as the `id` it references (`INT(25) UNSIGNED` here).
+ `Many-to-Many` relationships that require creating a pivot table in relational databases **DO NOT** need to be created in the database. The framework will automatically do that upon the definition of the relationship in the model.
> Learn more about creating MySQL relational databases [here](https://www.mysql.com/)

## Model Fundamentals
The `Model` is the entity that interfaces directly with the database tables.
Every table from your database that represents an object or an entity in your application that a user could act on needs to have a `Model` in the framework. For example, the table `books` needs to be represented by a `Book` Model in the framework. This framework dictates some conventions around the naming of the primary and foreign keys.
> Learn more about naming conventions [here](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#fundamentals)
+ All the models that you create should be in the `/application/models` folder. In this folder, you can organize the models to your convenience. If you prefer to have your models in a different folder, you can always do that for as long as it is within the `/application` folder.
+ There is no formal naming convention for the `Model`. We do recommend that you remain consistent. If you choose to name the models in singular or plural then make sure to do that for all your models.
+ All the models that you create should extend the `Model` class
```php
use Caligrafy\Model;

class Book extends Model {
    // your code goes here
}
```
+ The models that you create need to have all the equivalent table attributes represented as properties
```php
use Caligrafy\Model;

class Book extends Model {
    // properties
    public $id;
    public $title;
    public $author;
    public $publisher_id;
    public $created_at;
}
```

## Interfacing with the Model
The `Model` class exists in the context of a `Controller`. In other words, you can interface with a model from within a `Controller`.

> Learn more about `Controllers` [here](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers)

### Instantiating a Model
In order to instantiate the model that was created in the previous section, it needs to be done from the controller by associating the controller with the model and its respective table.

```php
/* Notice that this needs to be done in the Controller */

use Caligrafy\Controller;

class BookController extends Controller {
   public function index()
   {
      $this->associate('Book', 'books'); // associate the controller with a model and a table 

      // the controller could act on the model using the model property
      dump($this->model);

   }
}
```

### Model Methods
There are several built-in methods and properties that can be used from the moment you instantiate a model

```php

/* Associations */
public $table; // retrieves the table name associated with the model
public function associate($table); // returns the Model with a database table associated to it

/* CRUD methods */
public function all($args = array()); // Returns all the records. $args can complete the query with raw SQL clauses: 'condition', 'order', 'limit' and 'bindings' (see below)
public function find($id); // returns a Model of the specific entry if available, otherwise null
public function search($scope, $keywords); // returns the records where one of the columns listed in $scope (comma separated) is LIKE $keywords. % and _ are wildcards
public function create($input); // takes an array of values or an object and adds an entry to the respective table in the database. Returns the created Model
public function update($inputs, $id); // updates an entry in the database with the new values from array or Model. Returns the updated Model
public function delete($id); // deletes a specific entry from the database
public function save(); // saves a the model into the database. If the model has an id defined then it will update otherwise it will create a new entry in the table
public function toArray(); // returns the model as an array

/* Relationships definition */

// One-to-One relationship 
public function hasOne($modelName, $joinedTableName, $foreignKey = null, $localKey = null)

// One-to-Many relationship
public function hasMany($modelName, $joinedTableName, $foreignKey = null, $localKey = null)
public function belongsTo($modelName, $joinedTableName, $foreignKey = null, $localKey = null)

// Many-to-Many relationship in both directions
public function belongsToMany($modelName, $joinedTableName, $foreignKeyLocal = null, $foreignKeyJoin = null)

```

#### Values that are saved
`create()`, `update()` and `save()` send every property of the Model (or key of the array) that is **not `null`**. `0`, `''` and `false` are saved (`false` is saved as `0`). A property left to `null` is not sent, so the column keeps its current value or its default value.

#### Queries with `all()`
The clauses given to `all()` are added to the SQL query as they are, so they must contain their SQL keywords. Never concatenate user input in them: use `?` placeholders and give the values in `bindings`.
```php
$this->all(array(
    'condition' => 'WHERE author = ? AND created_at > ?',
    'order' => 'ORDER BY title',
    'limit' => 'LIMIT 10',
    'bindings' => array($author, '2024-01-01')
));
```

#### Searching with `search()`
```php
$this->search('title,author', 'Dune%'); // title or author starting with "Dune"
$this->search('title', '%' . $keyword . '%'); // title containing $keyword
```
The keywords are sent as a bound parameter, so quotes in user input are harmless. The column names must be made of letters, digits and underscores, otherwise an exception is thrown.

#### Custom queries
For anything more specific, write a method in the Model that uses the `Database` class with bound parameters:
```php
use Caligrafy\Database;
use Caligrafy\Model;

class Book extends Model {
    // properties ...

    public function findByIsbn($isbn)
    {
        $rows = Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE isbn = ? LIMIT 1', [$isbn]));
        return !empty($rows[0])? $this->arrayToModel($rows[0]) : null;
    }
}
```

>[Next Section: Learn about Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships)
