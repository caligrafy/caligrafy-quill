# 2. Routing

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - Unmatched URLs do not 404: they run the FIRST route registered in web.php (gotchas G4).

> **Where this fits.** Routes connect URLs to what answers them. For a feature written as a capability, one line is enough: `Route::capability('POST', '/books', 'create_book')` for REST, and `Route::capabilities()` and `Route::mcp()` (added by the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart)) for the Vue apps and AI agents: see [Capability Routes](#capabilityroutes). The rest of this page covers page routes and controller routes.

The very first thing that you need to do when building your application is structuring your URIs in a flexible and scalable way that is agnostic of how your application files are structured. This framework does it all for you. What you need to focus your effort on is planning where the URIs should go.

## Defining Routes
A route file is provided for you `/application/config/routing/web.php`. This is where your routes should be defined. There are 4 route methods available
```php
Route::get($uri, $callback);
Route::post($uri, $callback);
Route::put($uri, $callback);
Route::delete($uri, $callback);
```
Keep the following rules in mind when you define routes:
+ A request matches a route when the HTTP method is the same and the path has the same number of segments, each fixed segment being identical. `{variables}` match any single segment.
+ The routes are evaluated in the order they are defined and the first match wins.
+ When no route matches (an unknown URL or a wrong HTTP method), **the first route defined in the file is executed**. An unmatched URL does not return a 404: keep `Route::get('/', ...)` as the first route (the `/` route is only reached through this fallback).
+ `Route::run();` must always be the last statement inside the `try` block of the file.

Your first reaction might be that HTML `forms` do not support `PUT` and `DELETE`. True that and the framework is capable of doing that work for you. You can check [here](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#htmlformmethods) how it is done.

## "Hello World" Route
The most basic route provides a simple way to execute any method (through Closure) upon the definition of the route. `application/config/routing/web.php` already has one `try` block, and the template already defines this route: add yours inside the `try`, before `Route::run();`.
```php
try {
    // ...
    Route::get('helloworld', function() {
            echo 'Hello World';
    });

    Route::run();
} catch (Exception $e) {
    Errors::handle($e);   // HTTP status code (404, 401, 403, 500...) with the error text, or JSON for API calls
}
```
You should be able to access it by navigating to `http://your-app/helloworld` in your browser: `http://localhost/caligrafy-quill/helloworld` on a local server where the folder is `caligrafy-quill`, or `http://localhost:8080/helloworld` with Docker.

> #### (video) [Basic Routing](https://www.youtube.com/watch?v=VUxL2lVgs4E&list=PLsJG81fLk3_UNfTuqjCbcz1S_xbram35e)
>
> In this tutorial, we test if the Caligrafy framework has been installed properly, we explain how basic routing can be achieved and we create a couple of basic routes.

## Routing with parameters
It is not unusual to want to capture variables from the URI to act on them. For example if you want to access all the information on a specific book, you would want the URI to be `http://your-app/books/1` where `1` is the id of the book that you want.

In order to do that with this framework you need to use `{variablename}` syntax:
```php
Route::get('books/{id}', function() {
   // any code would go here
});
```
By doing so, any value that you put after `books/` will be captured and you will be able to use it in your code. So if you navigate to `http://your-app/books/1`, your code will be able to capture 1. Similarly, if you replace the one with another value, your code will be able to capture that value. Later you will learn how you can fetch this value in your code.

## Page Routes

Since 2.5.0 a **page** has its own route method. `Route::page` renders a view through the framework's page controller, so every page is built the same way:
```php
Route::page('/', 'default/index');                                             // a page
Route::page('/books', 'Books/index');                                          // its data comes from the JS client in the browser
Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book'], 'notFound' => 'Books/missing']);  // its data comes from a capability
Route::page('/account', 'Account/index', ['signedIn' => true]);                // signed-in people only
Route::page('/admin/books', 'Books/admin', ['level' => 1, 'data' => ['books' => 'list_books']]);
```

| Option | What it does |
| --- | --- |
| `data` | `name => capability`: calls each capability as the person who opens the page and passes the result to the view as `$name`. Its inputs are the query string and the route variables, the route variables winning (`/books/3?id=9` gives `get_book` the input `id = 3`). `name => ['capability', ['input' => 'value']]` gives fixed inputs instead. Only **read-only** capabilities (`readOnly: true`): opening a page never changes data, and a capability that does, or does not exist, stops the page with an error that says so |
| `signedIn` | `true`: visitors are sent to `/login` |
| `level` | people whose level (the `permissions` column) is lower are sent to `/notAuthorized` (visitors to `/login`) |
| `with` | fixed values for the view: `['with' => ['title' => 'Books']]` |
| `notFound` | a view shown, with a `404`, when a `data` capability finds nothing: `['notFound' => 'Books/missing']`. It gets the route variables, the `with` values, `$message`, `$authorized` and `$user` |

**What the view receives**, on every page: `$authorized` (true when someone is signed in), `$user` (`id`, `username`, `permissions`, or `null`), the route variables (`$id`) and the `data` and `with` values. Never the query string: a page reads it through a capability's inputs. `authorized` and `user` are reserved: a `data` or `with` entry with one of those names is an error. Capabilities return plain arrays: `$book['title']` in the view. A page on a closure route (`Route::get('/books', function () { return view('Books/index'); })`) gets none of this unless the closure passes it.

**When a capability refuses** the page's data: a visitor is sent to `/login`; a signed-in person gets `[ERROR 1006] Forbidden` (403) when the capability is not allowed to them, and `[ERROR 1005] Page Not Found` (404) otherwise (no record, or inputs the capability refuses), or the `notFound` view when the page has one.

`signedIn` and `level` need the authentication module (`Auth::authentication('User', 'users');` in `web.php`): without it, such a page fails with an error that says so.

**Pages never read models**: their data comes from capabilities, so a page, the REST API and AI agents follow the same rules. Use a [controller](#controllerroute) only when a page has logic of its own (a form post, several capabilities with conditions), and make it call capabilities too ([Calling a capability](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#callcapability)).

## Controller Routing

In an MVC structure, you most likely want to route to a controller to hand off the logic to a controller class that will handle executing that logic. In order to do this, you could define a route that will call the controller:
```php
Route::get('books', 'BookController');
```
This route will redirect all calls to `http://your-app/books` to the controller `BookController`. 

You could also specify a specific method from the `BookController` to execute:
```php
Route::get('books', 'BookController@read');
```
This route would call the method `read` from `BookController`.

Controllers can be namespaced: `Route::get('orders/{id}', 'Shop\OrderController@show');` (a leading backslash is accepted). A short name that is not a declared class falls back to the example controllers of `Caligrafy\Controllers` (`'ClientController'`, `'AuthController@login'`...), and a controller or action that does not exist returns a `404`. Learn more in [Namespaced controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#namespacedcontrollers).

> Routes that point to a controller (`'BookController'` or `'BookController@read'`) are protected for API calls: a request that asks for JSON (`Accept: application/json`) must come from a page with the session and its CSRF token in an `X-CSRF-Token` header (`apiHeaders()`, since 2.7.0), or from a program with a personal access token as a Bearer token (`php caligrafer.php token create ...`). The legacy `API_KEY` still works where `LEGACY_API_KEY` is not `false`, for old programs only: Caligrafy Cloud turns it off. The template enables the API with `Auth::activateAPI();` at the top of `web.php`: without it, every API call to a controller answers `401`. Routes defined with a closure (`function() {...}`) are not checked. Learn more about the [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#restapi).
Later on, you will learn how to create controllers.

> #### (video) [Advanced Routing](https://www.youtube.com/watch?v=32UvcK2bSzo&list=PLsJG81fLk3_UNfTuqjCbcz1S_xbram35e)
>
> This video explains how to create more advanced routes in Caligrafy. You will learn how to create routes with parameters and how to redirect a route to a controller or to a specific action in a controller.

## Capability Routes

A capability is a feature written once and served to every experience (build once, experience everywhere). Three route methods expose capabilities instead of a controller action:
```php
Route::capability('POST', '/books', 'create_book');   // REST: one route, one capability; {id} becomes the input "id"
Route::capabilities('/capabilities');                  // GET lists, POST /capabilities/{name} calls: used by the JS client of the Vue apps
Route::mcp('/mcp');                                    // AI agents: every capability their token allows becomes an MCP tool
```
They check who calls (a personal access token or the signed-in user), validate the inputs and answer JSON with real status codes. Learn more [here](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#rest).

## HTML Form Methods

HTML forms do not support `PUT` and `DELETE`. The framework does this for you from the moment you include a hidden `_method` field in the form with a value of `PUT` or `DELETE`. This is called **method spoofing**.

This can be achieved using regular HTML:
```html
<form action="/books" method="POST">
    <input type="hidden" name="_method" value="PUT">
</form>
```
Or, using Pug directives that will do it automatically for you:
```pug
form(action="/books", method="POST")
    =methodField('PUT')      
```
>[Next Section: Learn about Requests](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request)
