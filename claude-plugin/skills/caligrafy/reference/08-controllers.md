# 8. Controllers

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

## Getting Started with Controllers
A `Controller` is a key entity in an MVC architecture. It is the layer that is responsible for interfacing with both the `Model` and the `View`. It takes its input from a `View`, interfaces with the `Model` to either get information or add to the information, it calls upon any other logic that needs to be applied to the information and finally sends it back to the `View` to be displayed. 

> #### (video) [CRUD operations on a database](https://www.youtube.com/watch?v=kNdoaSi6pcE&list=PLsJG81fLk3_UNfTuqjCbcz1S_xbram35e)
>
> In this video, we will learn how to create a database model, how to link it to Caligrafy and how to perform CRUD operations using Controllers.

Most of the concepts that we covered thus far - `Routing`, `Request`, `Models`, `Relationships`, `Validation`, `Views` - come into play together in the context of a `Controller`.
### Creating a Controller
+ All the controllers that you create should be in the `/application/controllers` folder. In this folder, you can organize the controllers to your convenience. If you prefer to have your controllers in a different folder, you can always do that for as long as it is within the `/application` folder.

+ There is no formal naming convention for a controller. We do recommend that you remain consistent.
> Learn more about naming conventions [here](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#frameworkfundamentals)

+ All the controllers that you create should extend the `Controller` class
```php

use Caligrafy\Controller;

class BookController extends Controller {
    // your code goes here
}
```

+ The controllers that you create need to have at the very least, an `index` method. The `index` method gets called by default when no method is specified in the `Route`.
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

### Controller Context
Frameworks are meant to help organize and structure code. Some frameworks impose that a `Controller` be tied to a `Model` and imposes a naming convention that forces developers to have a single controller for a model.

Our framework provides the flexibility to create a controller that is independent of a model and it allows changing its context at any point using the `associate` method that takes a `Model` and a database `table name` arguments and returns a `Controller`:

`public function associate(Model $model, String $tablename);`

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
       // associate the controller with the Book model and the books table
       $this->associate('Publisher', 'publishers'); 

       // all controller methods executed below will be in the context of Publisher
 
     }
}
```

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

+ From the moment a `Controller` is a created as previously described, powerful built-in methods and properties are provided out of the box.

```php

protected $request; // retrieves the Request

protected $model; // retrieves the Model

protected $connection; // retrieves the database connection

protected $validator; // The GUMP validator can immediately be used from the controller

protected $payment; // The Stripe payment module can immediately be used from the controller once activated

public function associate(Model $modelname,  String $table); // allows the model to switch context

public function activatePayment(); // Activates the Stripe Payment module

public function all($args = null); // Returns all entries of a model unless an array of condition, order and limit are specified to complete the query

public function find($id = null); // returns a specific entry. When id is not provided it is fetched from the request URI

public function save($inputs, $id = null); // saves an input Array or Model to the database. When an id is provided it would update

public function delete($id = null); // delete a specific entry. When id is not provided it is fetched from the requested URI

public function search(String $scope = null, String $keywords = null); 
/* returns all entries in a Model with an attribute that matches the keyword search
 * There are two wildcards that can be passed on with keywords:
 * The percent sign (%) represents zero, one, or multiple characters
 * The underscore sign (_) represents one, single character
*/

public function table(); // returns the database table that the model is associated to at any point in time

public function check($data, $rules = array(), $filters = array()); // calls the GUMP validator for validation and /or filtering

public function validate($data, $rules); // calls the GUMP validation method

public function filter($data, $filters); // calls the GUMP filtering method

public function view($viewName, $viewData = array()); // returns a view that renders a Pug template with the data provided

public function api($viewData = array()); // returns a view for api rendering
```

>[Next Section: Learn about Helpers, Forms & REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API)
