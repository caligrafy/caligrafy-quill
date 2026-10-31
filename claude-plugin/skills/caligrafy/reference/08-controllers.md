# 8. Controllers

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Where this fits.** In Caligrafy, the rules of a feature live in a [capability](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service), and a controller is the web experience of it: it calls the capability (`$this->call('create_book')`, see [Calling a capability](#callcapability)) and chooses the view. Write logic in a controller only when it belongs to one page.

## Getting Started with Controllers
A `Controller` is a key entity in an MVC architecture. It is the layer that is responsible for interfacing with both the `Model` and the `View`. It takes its input from a `View`, interfaces with the `Model` to either get information or add to the information, it calls upon any other logic that needs to be applied to the information and finally sends it back to the `View` to be displayed. 

> #### (video) [CRUD operations on a database](https://www.youtube.com/watch?v=kNdoaSi6pcE&list=PLsJG81fLk3_UNfTuqjCbcz1S_xbram35e)
>
> In this video, we will learn how to create a database model, how to link it to Caligrafy and how to perform CRUD operations using Controllers.

Most of the concepts that we covered thus far - `Routing`, `Request`, `Models`, `Relationships`, `Validation`, `Views` - come into play together in the context of a `Controller`.
### Creating a Controller
+ All the controllers that you create should be in the `/application/controllers` folder. In this folder, you can organize the controllers to your convenience. If you prefer to have your controllers in a different folder, you can always do that for as long as it is within the `/application` folder.

+ There is no formal naming convention for a controller. We do recommend that you remain consistent.
> Learn more about naming conventions [here](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#fundamentals)

+ Every PHP file of `/application` is loaded on each request, so two classes with the same fully qualified name are a fatal error. The example controllers that come with Caligrafy (`application/controllers/core/`: `AIController`, `AdminController`, `AuthController`, `ClientController`, `WatsonController`) live in the `Caligrafy\Controllers` namespace, so you can give one of your controllers the same short name without a collision.

+ Controllers can be namespaced. Declare the namespace at the top of the file and use the fully qualified name in the routes (see [Namespaced controllers](#namespacedcontrollers)):
```php
<?php
// application/controllers/Shop/OrderController.php
namespace Shop;

use Caligrafy\Controller;

class OrderController extends Controller {
    public function show()
    {
       // your code goes here
    }
}
```

+ All the controllers that you create should extend the `Controller` class
```php

use Caligrafy\Controller;

class BookController extends Controller {
    // your code goes here
}
```

+ The `index` method gets called by default when no method is specified in the `Route`. The base `Controller` has an empty `index()`: override it in your controller.
```php

use Caligrafy\Controller;

class BookController extends Controller {
    // index method
    public function index()
    {
       // your code goes here
    }
}
```
+ Controllers usually cover all the actions that can be taken on the `Model` that it controls. It is therefore common to have several other methods within a controller.
```php

use Caligrafy\Controller;

class BookController extends Controller {
    // index method
    public function index()
    {
       // your code goes here
    }
    public function read()
    {
       // your code goes here

    }
}
```

### Routing to the Controller
In the `Routing` section, we covered the basics of how to route a request to a `Controller` or to a specific method within the controller. We will summarize them herewith.

The following route will redirect all calls to `http://your-app/books` to the `index` method in the `BookController`:
```php
Route::get('books', 'BookController');
```

The following route will redirect all calls to `http://your-app/books` to the `read` method in `BookController`:
```php
Route::get('books', 'BookController@read');
```

### Namespaced controllers
A route can name a controller by its fully qualified class name, with or without a leading backslash. In a PHP single-quoted string, write the backslash once or twice (`'Shop\OrderController'` and `'Shop\\OrderController'` are the same string):
```php
Route::get('/orders/{id}', 'Shop\OrderController@show');
Route::get('/orders', '\Shop\OrderController');
```

How the router finds the class of `'Name@action'`:
1. a declared class named `Name` (global or fully qualified) is used: your own `AIController` wins over the example one;
2. otherwise, a short name (no backslash) falls back to the example controllers namespace, `Caligrafy\Controllers\Name`. Routes written before the example controllers were namespaced, such as `'AuthController@login'` or `'ClientController'`, keep working;
3. otherwise the request ends with a `404` ([ERROR 1001] Invalid Controller or Action), as for a missing action.

The routes that come with a new project use the full names (`'Caligrafy\Controllers\ClientController'`), which keep pointing to the examples even if you declare a controller with the same short name.

> The models given to `associate()`, `Auth::authentication()` and the relationships (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`) can be namespaced too (`'Shop\Order'`). The default foreign keys use the short name in lower case (`order_id`).

### Controller Context
Frameworks are meant to help organize and structure code. Some frameworks impose that a `Controller` be tied to a `Model` and imposes a naming convention that forces developers to have a single controller for a model.

Our framework provides the flexibility to create a controller that is independent of a model and it allows changing its context at any point using the `associate` method that takes a `Model` and a database `table name` arguments and returns a `Controller`:

`public function associate($modelName, $tablename);` (the model is given by its class name, for example `'Book'`, or as a `Model` object)

Let's consider the books example again. Assume you have a `Book` and a `Publisher` models in an online library system. In a model-constrained framework, if you need to allow the users to make changes to the `Publisher` model, you will need to have a dedicated `PublisherController`. 

In this framework, you could change the context in methods or even within the same method: 
```php

use Caligrafy\Controller;

class MyController extends Controller {

    public function readBook()
    {
       // associate the controller with the Book model and the books table
       $this->associate('Book', 'books'); 

       // all controller methods executed below will be in the context of Book
     }
     public function addPublisher()
     {
       // associate the controller with the Publisher model and the publishers table
       $this->associate('Publisher', 'publishers'); 

       // all controller methods executed below will be in the context of Publisher

     }
}
```

### Calling a capability

When a feature is also needed by a Vue app, an API or an AI agent, write it once as a [capability](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service) and let the controller be the web experience of it: `$this->call()` runs the capability as the signed-in user, with its validation and permissions, and the action only decides what to show.
```php
use Caligrafy\Controller;
use Caligrafy\ServiceError;

class BookController extends Controller {

    public function store()
    {
        try {
            $book = $this->call('create_book');   // inputs: the posted fields and the route variables
            redirect('/books/' . $book['id']);
        } catch (ServiceError $e) {
            return view('Books/form', array('errors' => $e->fields, 'message' => $e->getMessage()));
        }
    }
}
```
The same `create_book` then answers `POST /books` over REST, `createBook()` in a Vue app and the `create_book` tool of an AI agent, with the same rules.

## Controller Methods & Properties

+ Methods can be created as needed in a controller provided they don't override the built-in `Controller` methods. From the moment you override these methods, the framework could behave abnormally.

+ Despite the fact that there is no prescriptive way on how to organize the logic within a method, we recommend the following logical structure:
```php

use Caligrafy\Controller;

class MyController extends Controller {

    public function myMethod()
    {
       // Context: set the context of the controller by association with model and table 

       // Retrieve & Validate: fetch any parameters needed from the request when applicable and validate it

       // Model: Create a model when applicable based on the retrieved data or the data to be changed

       // Act: perform the action on the model

       // View: return a view with the output of the action
    }

}

```

> **Be careful with `{id}` routes:** `find()`, `save()` and `delete()` take the id from the `{id}` route variable when no id is passed. On a route such as `POST /books/{id}/reviews`, `$this->save($review)` would **update** the review whose id is the book id. Name the variable differently (`{book_id}`) or pass the id explicitly.

> **Redirects:** `redirect('/path')` only sends a `Location` header. Add `exit;` (or `return`) right after it when code follows.

+ From the moment a `Controller` is a created as previously described, powerful built-in methods and properties are provided out of the box.

```php

protected $request; // retrieves the Request

protected $model; // retrieves the Model

protected $connection; // retrieves the database connection

protected $validator; // The GUMP validator can immediately be used from the controller

protected $payment; // The Stripe payment module can immediately be used from the controller once activated

public function associate($modelname, $table); // allows the model to switch context: associate('Book', 'books')

public function activatePayment(); // Activates the Stripe Payment module

public function all($args = array()); // Returns all entries of a model unless an array of condition, order, limit and bindings is specified to complete the query (see Models)

public function find($id = null); // returns a specific entry. When id is not provided it is fetched from the request URI

public function save($inputs, $id = null); // saves an input Array or Model to the database. It updates when an id is given OR when the route has an {id} variable, otherwise it creates

public function delete($id = null); // delete a specific entry. When id is not provided it is fetched from the requested URI

public function search($scope = null, $keywords = null); // defaults to the scope and keywords of the request
/* returns all entries in a Model with an attribute that matches the keyword search
 * $scope lists the columns, separated by commas. The keywords are bound, never inserted in the SQL
 * There are two wildcards that can be passed on with keywords:
 * The percent sign (%) represents zero, one, or multiple characters
 * The underscore sign (_) represents one, single character
*/

public function table(); // returns the database table that the model is associated to at any point in time

public function check($data, $rules = array(), $filters = array()); // calls the GUMP validator for validation and /or filtering (see Validation for the return values)

public function validate($data, $rules); // calls the GUMP validation method

public function filter($data, $filters); // calls the GUMP filtering method

public function view($viewName, $viewData = array()); // returns a view that renders a Pug template with the data provided

public function api($viewData = array()); // returns a view for api rendering
```

>[Next Section: Learn about Helpers, Forms & REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API)
