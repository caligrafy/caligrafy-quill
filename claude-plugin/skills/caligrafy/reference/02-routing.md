# 2. Routing

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - Unmatched URLs do not 404: they run the FIRST route registered in web.php (gotchas G4).

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

> Routes that point to a controller (`'BookController'` or `'BookController@read'`) are protected for API calls: a request that asks for JSON (`Accept: application/json`) must carry the `API_KEY` as a Bearer token (the template enables the API with `Auth::activateAPI();` at the top of `web.php`: without it, every API call to a controller answers `401`). Routes defined with a closure (`function() {...}`) do not check the API key. Learn more about the [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#restapi).
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
