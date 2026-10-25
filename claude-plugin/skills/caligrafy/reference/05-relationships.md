# 5. Relationships

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `belongsTo()` is implemented as `hasMany()` (returns an array of rows whose FK points at this model) - for the inverse of one-to-many use `hasOne()` (gotchas G9).
> - `hasOne()` reads `<model>_id` on the CALLING model's table. `belongsToMany()` pivots copy the referenced id types and use unique constraint names (G10, fixed in the security release).

In relational databases, tables are related to one another. There are several types of relationships that can be defined. This framework makes it easy to define and use relationships without having to write extensive code.
There are 3 types of relationships defined in this framework: `One-to-One`, `One-to-Many` and `Many-to-Many`.

> #### (video) [Relationships](https://www.youtube.com/watch?v=o6knV88iCkw&list=PLsJG81fLk3_UNfTuqjCbcz1S_xbram35e)
>
> In this video, we will learn how to define one-to-one, one-to-many and many-to-many relationships among models in Caligrafy.

## One-To-One Relationship

A `One-to-One` relationship is the simplest relationship between two entities. For example, we can have an authorization module where every `User` has one `Account` and reciprocally every `Account` has one `User`. This is a typical `One-to-One` relationship.

### Declaration
In order to create such a relationship, you first need to make sure that the database tables that you create for the two entities have the foreign keys that link them. `hasOne()` reads the foreign key **on the table of the model that calls it**: `$user->account()` uses `users.account_id` and `$account->user()` uses `accounts.user_id`. If you only need one direction, you only need that foreign key.

> Learn more about `Relational Databases` [here](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)

Once your database tables are created, one of the models `User`, for example, will add a new method that invokes the `hasOne()` method to define that relationship. 
```php
use Caligrafy\Model;

class User extends Model {

    // properties go here
    //...

    //new method that defines a One-to-One relationship
    public function account()
    {
       return $this->hasOne('Account','accounts');
       // the first argument is the model name to link to
       // the second argument is the database table name of to link to

    }
}
```
### Inverse Relationship
In the `Account` model, you will need to do the same using the `hasOne` method.
```php
use Caligrafy\Model;

class Account extends Model {

    // properties go here
    //...

    //new method that defines the inverse of a One-to-One relationship
    public function user()
    {
       return $this->hasOne('User','users');
       // the first argument is the model name to link to
       // the second argument is the database table name of to link to

    }
}
```
> **Notice:** In both the examples, there is no restriction as to the name of the method used to define the relationships. We recommend using a name that is representative enough of the type of relationship.

### Usage
After the relationship has been defined in the respective models, making use of that relationship takes place in the context of the `Controller` or in the 'Model' itself in the constructor or the invoke functions. For example, in order to get the `Account` associated with a `User`, you would do the following in the `UserController`:
```php
use Caligrafy\Controller;

class UserController extends Controller {

    public function getAccount() 
    {
        $this->associate('User', 'users'); // create the association
        $account = $this->find(1)->account(); // returns account for user with id 1
    }
}
```
## One-To-Many Relationship
A `One-to-Many` relationship is a common type of relationship between two entities. Taking the same example as before, you could allow one `User` to have multiple `Account` but an `Account` to belong to one user. For that you would want to use a `One-to-Many` relationship.

### Declaration
In order to create such a relationship, you first need to make sure that the entity that could exist many times has a reference in the table of the other entity. In our example, that means that there should be a `foreign_key` in the `accounts` table that references the `users` table.

> Learn more about `Relational Databases` [here](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)

Once your database tables are created, the `User` model needs a new method that invokes the `hasMany()` method to define that relationship. 
```php
use Caligrafy\Model;

class User extends Model {

    // properties go here
    //...

    //new method that defines a One-to-Many relationship
    public function accounts()
    {
       return $this->hasMany('Account','accounts');
       // the first argument is the model name to link to
       // the second argument is the database table name of to link to

    }
}
```
### Inverse Relationship
In the `Account` model, the account belongs to one `User`. Use the `hasOne` relationship: it reads `accounts.user_id` and returns the `User`.

> **Notice:** `belongsTo()` is not the inverse of `hasMany()` in Caligrafy: it behaves exactly like `hasMany()` (it looks for `<thismodel>_id` in the joined table and returns an array). Use `hasOne()` for the inverse of a One-to-Many relationship.
```php
use Caligrafy\Model;

class Account extends Model {

    // properties go here
    //...

    //new method that defines the One to relationship
    public function user()
    {
       return $this->hasOne('User','users');
       // the first argument is the model name to link to
       // the second argument is the database table name of to link to

    }
}
```
> **Notice:** In both the examples, there is no restriction as to the name of the method used to define the relationships. We recommend using a name that is representative enough of the type of relationship.

### Usage
After the relationship has been defined in the respective models, making use of that relationship takes place in the context of the `Controller` or in the 'Model' itself in the constructor or the invoke functions. For example, in order to get all the accounts associated with a `User`, you would do the following in the `UserController`:
```php
use Caligrafy\Controller;

class UserController extends Controller {

    public function getAccounts() 
    {
        $this->associate('User', 'users'); // create the association
        $accounts = $this->find(1)->accounts(); // returns accounts for user with id 1
    }
}
```
## Many-to-Many Relationship
A `Many-to-Many` relationship ensures that two entities could have multiple instances of each other. Taking the same example as before, you could allow one `User` to have multiple `Account` and one `Account` to have multiple `User`. For that you would want to use a `Many-to-Many` relationship.

### Declaration
In order to create such a relationship, you do not need to do anything in the database other than making sure to follow the naming conventions.

> Learn more about Naming Conventions [here](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)

The `User` model needs a new method that invokes the `belongsToMany()` method to define that relationship. 
```php
use Caligrafy\Model;

class User extends Model {

    // properties go here
    //...

    //new method that defines a Many-to-Many relationship
    public function accounts()
    {
       return $this->belongsToMany('Account','accounts');
       // the first argument is the model name to link to
       // the second argument is the database table name of to link to

    }
}
```
### Inverse Relationship
Similarly, the `Account` model needs to define a `belongsToMany` relationship.
```php
use Caligrafy\Model;

class Account extends Model {

    // properties go here
    //...

    //new method that defines a Many-to-Many relationship
    public function users()
    {
       return $this->belongsToMany('User','users');
       // the first argument is the model name to link to
       // the second argument is the database table name of to link to

    }
}
```
> **Notice:** In both the examples, there is no restriction as to the name of the method used to define the relationships. We recommend using a name that is representative enough of the type of relationship.

### Usage
After the relationship has been defined in the respective models, making use of that relationship takes place in the context of the `Controller` or in the 'Model' itself in the constructor or the invoke functions. For example, in order to get all the accounts associated with a `User`, you would do the following in the `UserController`:
```php
use Caligrafy\Controller;

class UserController extends Controller {

    public function getAccounts() 
    {
        $this->associate('User', 'users'); // create the association
        $user = $this->find(1);
        $accounts = $user->accounts(); // returns all accounts for user with id 1

        // Alternatively, you can use the pivot property to access all the pivots associated with a user
        $accounts = $user->pivot->accounts->all; // returns all accounts for user with id 1
    }
}
```

### The pivot table
The pivot table is created the first time the relationship method is called:
+ it is named `<table1>_<table2>` (`users_accounts` when called from `User`), or `<table2>_<table1>` if that table already exists
+ it has the columns `id`, `user_id`, `account_id`, `created_at` and `modified_at`. The foreign key columns copy the type of the `id` columns they reference, so `INT` and `INT UNSIGNED` ids both work
+ its foreign key constraints are named `fk_<pivot>_<column>`, which keeps them unique in the database

If you prefer to create the pivot table yourself (to add columns for example), create it with the same name and columns before calling the relationship: the framework will leave it untouched.

### Attaching and detaching
`attach()` and `detach()` add and remove links in the pivot table. Call the relationship method first so that the model knows its pivot, then give the **joined table name** and the id to link. Both return the refreshed list of related models.
```php
$user = $this->find(1);
$user->accounts();                       // loads the relationship and its pivot
$accounts = $user->attach('accounts', 5); // links account 5 to user 1
$accounts = $user->detach('accounts', 5); // removes the link
```

## Overriding Naming Convention
Relationships work under the premise that all tables have and 'id' primary key. This convention cannot be overridden. Also, the relationship methods assume that all foreign keys have the format `modelname_id` with the model name being in small caps. Taking the `User` and `Account` example again, the foreign keys are by default assumed to be `user_id` (in the `accounts` table) and `account_id` (in the `users` table).

This framework provides the flexibility to override the foreign keys naming.
```php
// One-to-One relationship 
public function hasOne($modelName, $joinedTableName, $foreignKey = null, $localKey = null)

// One-to-Many relationship
public function hasMany($modelName, $joinedTableName, $foreignKey = null, $localKey = null)
public function belongsTo($modelName, $joinedTableName, $foreignKey = null, $localKey = null)

// Many-to-Many relationship in both directions
public function belongsToMany($modelName, $joinedTableName, $foreignKeyLocal = null, $foreignKeyJoin = null)

```
## Model Methods
The core `Model` class comes prepackaged with a set of properties and methods that help establish the relationships and interfacing with joined tables.
```php
// One-to-One relationship 
public function hasOne($modelName, $joinedTableName, $foreignKey = null, $localKey = null)

// One-to-Many relationship
public function hasMany($modelName, $joinedTableName, $foreignKey = null, $localKey = null)
public function belongsTo($modelName, $joinedTableName, $foreignKey = null, $localKey = null)

// Many-to-Many relationship in both directions
public function belongsToMany($modelName, $joinedTableName, $foreignKeyLocal = null, $foreignKeyJoin = null)
public function attach($joinedTableName, $id) // attaches an ID to a joined many-to-many table
public function detach($joinedTableName, $id) // detaches and ID from a joined many-to-many table 

```

>[Next Section: Learn about Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)
