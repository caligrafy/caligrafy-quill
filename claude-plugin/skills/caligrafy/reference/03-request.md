# 3. Request

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `parameters` is a property AND `parameters()` a method; `fetch` is an object property (`$this->request->fetch->id`) AND a method (`fetch('id')`). See api-reference.md > Request.
> - JSON/API calls must send `Accept: application/json` (without text/html) to skip CSRF; see gotchas G1.

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
    public $method; // retrieves the request method
    public $uri; // retrieves the URI
    public $fullUri; // retrieves the the full URI including the query parameters
    public $userAgent; // retrieves the User Agent of the request
    public $cookie; // retrieves the Cookie headers
    public $currentRoute; // retrieves the current route 
    public $fetch; // retrieves an array of all the parameters fetched from the request and the URI
    public $parameters; // retrieves an object model of all the request parameters
```

## Request Methods
```php
   public function fetch($argument); //returns the value of a specific parameter
   public function files($HTMLFileName); //returns an array of all the uploaded files through a FORM
   public function wantsJson(); //returns true if the request is an API call
   public function parameters(); //returns an array of all the request parameters
```

>[Next Section: Learn about Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)
