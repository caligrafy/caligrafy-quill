# 7. Views

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Views are the front-end layer of your application. This is what gets displayed on the screen for your users.
In an MVC structure, the `View` interfaces with the `Controller` and the `Controller` determines what gets displayed to the `View`.

## Introduction to `Phug`
This framework integrates tightly with PHP Pug (Phug). `Phug` is a pug template engine for PHP. It offers you a clean and easy way to write HTML templates.

Instead of writing verbose tag syntax, `Phug` is based on the indentation like Python for example.
```pug
body
    h1 Hello

```
The same code in HTML would look like this:
```html
<body>
    <h1>Hello</h1>
</body>
```

## `Pug` Templates
Every framework has a template system. Templates help separate the concerns in a an elegant way between logic and presentation. Such a mentality makes the code more maintainable and most importantly, more flexible for making more frequent user interface changes. 

`Phug` is a PHP library that uses the `Pug` language.
Make yourself familiar with its syntax. There is a slight learning curve in the beginning but once you get the hang of it, you will appreciate using it. 

> Learn Pug and Phug [here](https://www.phug-lang.com/#language-reference)

## Structure and Format
+ All the views that you create should be located in the `/application/views` folder. In that folder, you can create the structure that is the most convenient for you by creating subfolders at will. That folder should not contain any client-side scripts and sheets. Remember that this folder is not public and any client-side scripts that you link will not be read by the browser.

+ All the view files need to have a `.pug` extension. Remember, `Pug` is the language that `Phug` uses. `Pug` is a language that is very similar to HTML and that ends up rendering an HTML/CSS page. `Pug` files should be a replacement to the HTML pages you usually create.

+ Javascript files, script files, images and stylesheets that you would want to link to from your `Pug` view file must be in the `/public` folder. You could organize the `/public` folder to your convenience.  

## Getting Started with Views

Using templates can be overwhelming at first. We recommend that you first start creating `pug` pages individually and not worry about templates just yet. While you are developing your pages, you will notice blocks of code that start repeating. This should be a trigger for you to consider separating these blocks out in a separate `pug` file

Let's start with a basic "Hello World" example:
+ Create a new file called 'helloworld.pug'
```pug
head
    title Hello World Application
body
    h1 Hello World
    p This is our first view using pug
```
Now that we have a view created, we still need to define when to display it to the users. This view can be called from a `Controller` or directly from the `Route` if there is no extensive logic needed. 
We will show you how this can be done in the next sections.

## Simple `View`
Usually in web applications, the most common use case is to have a `Controller` for every presentation page. It is assumed that there is always a need to interface with the database and have a some sort of a logic that justifies the need to have a `Controller`. 

However, there are cases, especially for public pages, where they are just for presentations and no extensive logic nor database extensions are needed.

### `View` from `Route`
It is acceptable in such cases to return a `View` directly from a `Route`.
```php
Route::get('hello', function() { return view('helloworld'); }); 
// Not that you only enter the name of the template without the `.pug` extension
```
### `View` from `Controller`
When the view is called from a `Controller`:
```php
use Caligrafy\Controller;

class HelloController extends Controller {
   public function display()
   {
       return $this->view('helloworld');

       // Alternatively
       return view('helloworld');
   }
}
```

## `View` with parameters
Most of the time, you are going to need to send the data to be displayed to the `View`. This is the cornerstone of the separation of concerns. In an MVC architecture, the `Controller` is the interface that gets the data from the 'Model', validates it, filters it, formats it and then sends it to the `View`. 

The `view` method takes 2 arguments

`public function view(String $templateName, Array $parameters);`

The `templateName` is the name of the `pug` template (path from `/application/views`, without the `.pug` extension). When the request is an API call (`Accept: application/json`) or when the template name is `null`, the `view` method returns the `parameters` as JSON instead and ends the request: set any HTTP status code with `http_response_code()` before calling it. The `parameters` is a key/value pair array of the `template variable name` and the `value` that needs to be sent to the view.

Let's take the "Hello World" example from before and let's assume that we want the sentence in the body to change dynamically. In this example, we need to have a key/value pair that holds the name of the variable that will be used in the view and the value of it is the sentence that we want to display. 

We will illustrate how it could be done from both the `Route` and the `Controller`.

### Template with parameters
The first thing that needs to be done is prepare the template in the `View` to receive a variable `sentence`
```pug
head
    title Hello World Application
body
    h1 Hello World
    p= $sentence
```
### From the `Route`
We now need to make sure that when the view method is called to assign a value to the `sentence` variable that will be read by the template.
```php
Route::get('hello', function() {
  return view('helloworld', array('sentence' => 'This is our first view using a route');    
});
```

### From the `Controller`
Similarly, the `Controller` will need to assign a value to the `sentence` variable.
```php
use Caligrafy\Controller;

class HelloController extends Controller {
   public function display()
   {
       return $this->view('helloworld', array('sentence' => 'This is our first view using a route') );

       // Alternatively
       return view('helloworld', array('sentence' => 'This is our first view using a route'));
   }
```
> The use of the `View` in this framework is very straightforward. Most of the effort should be in understanding the syntax of the `Pug` language.It is a simple language to pick up especially if you are familiar with HTML. 
> Learn Pug and Phug [here](https://www.phug-lang.com/#language-reference)

>[Next Section: Learn about Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers)
