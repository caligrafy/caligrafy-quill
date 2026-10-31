# 3. Request

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `parameters` is a property AND `parameters()` a method; `fetch` is an object property (`$this->request->fetch->id`) AND a method (`fetch('id')`). See api-reference.md > Request.
> - JSON/API calls must send `Accept: application/json` (without text/html) to skip CSRF; see gotchas G1.

> **Where this fits.** Controllers and closure routes read the request directly. A [capability](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service) never does: it receives its inputs already checked and a `Context` that says who is calling, which is what lets the same capability answer pages, REST and AI agents.

The `Request` class provides you with the flexibility to get all the information needed about a request made to your application. The `Request` class exists in the context of a successful route. In other words, if the routing is not successful then there is no easy way to have access to the `Request` class and to leverage its powerful functionalities.
> Learn more about `Routing` [here](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing)

## Accessing the Request
From the moment a route is successful, the `Request` class is available for use.
+ It can be accessed from the context of any `Class` created in the `/application` folder by invoking a global helper method:
```php
request();
```
+ In the context of a `Controller`, the `Request` is a property of these classes:
```php
use Caligrafy\Controller;

class BookController extends Controller {
   public function test()
   {
       dump($this->request); // will return the entire request object
   }
}
```
## Request Properties
```php
    public $method; // retrieves the request method (a POST with a _method field reports PUT or DELETE)
    public $uri; // retrieves the URI
    public $fullUri; // retrieves the the full URI including the query parameters
    public $uriComponents; // retrieves an array of the URI segments
    public $host; // retrieves the Host header
    public $authorization; // retrieves the Authorization header (Bearer API key for API calls)
    public $accept; // retrieves the Accept header
    public $userAgent; // retrieves the User Agent of the request
    public $cookie; // retrieves the Cookie headers
    public $currentRoute; // retrieves the current route 
    public $currentBind; // retrieves an array of the route variables and, for GET requests, the query parameters
    public $fetch; // retrieves an object of all the parameters fetched from the request and the URI
    public $parameters; // retrieves an array of all the request parameters (the body for POST/PUT/DELETE, the query for GET)
```

## Request Methods
```php
   public function fetch($argument); //returns the value of a route variable or query parameter by name, or by position when $argument is an integer (1 for the first). Returns '' when not found
   public function files($HTMLFileName); //returns the uploaded file array ($_FILES entry) of a FORM input
   public function wantsJson(); //returns true if the request is an API call (Accept contains application/json and not text/html)
   public function parameters(); //returns an array of all the request parameters (form fields or JSON body)
```

> **Example:** for the route `Route::put('books/{id}', 'BookController@update')` and a request `PUT /books/3` with the JSON body `{"title": "Dune"}`, `$this->request->fetch('id')` returns `'3'` and `$this->request->parameters()` returns `['title' => 'Dune']`.

>[Next Section: Learn about Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)
