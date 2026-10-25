# 9. Helpers, Forms & REST API

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `redirect()` does not exit (G5); `guard()` defaults are `$loginUrl='/login'`, `$notAuthorized='/notAuthorized'` and it exits.
> - API calls need BOTH `Authorization: Bearer <API_KEY>` and `Accept: application/json` (gotchas G1). The `API_KEY` is exposed to client apps (G27).

This framework provides a certain number of capabilities that are meant to accelerate, to some extent, some of the basic logic that is redundant in every project. Some of those are global helper methods that can be used across the framework, others are specific to HTML Forms. Last but not least, we will show how this framework is already prebuilt to handle api calls without any extra coding.

## Helpers
+ Framework helper methods
```php
function view($viewName, $viewData = array()); // returns a view that uses the Pug template viewName with the data sent to it `viewData`

function redirect($url); // redirects to another local route. It only sends a Location header: add exit; after it when code follows

function api($viewData = array()); // returns a view suitable for api result

function request(); // retrieves the Request object and you perform any Request operation on it

function connect(); // connects to the database with the credentials defined in the settings

function currentRoute(); // returns the current Route

function httpRequest($url, $method, $data, $headers, $username = null, $password = null); // performs an http request, mainly used to send http requests to external apis. $data is sent as JSON for every method except GET. Returns the decoded JSON response

function httpFile($url, $filepath, $headers, $username = null, $password = null, $purpose = 'assistants'); // performs an http request to upload file

function sendMail($recipients, $emailContent, $from); // sends an email through the SMTP server defined by MAIL_HOST, MAIL_PORT, MAIL_USERNAME and MAIL_PASSWORD in the env file. Returns true or false
// $recipients: array('email' => 'someone@example.com' or array of emails, 'name' => 'Someone')
// $emailContent: array('subject' => 'Subject', 'body' => '<p>HTML content</p>')
// $from: array('email' => 'me@example.com', 'name' => 'Me')

function uploadFile($file, $allowedExtensions = null); // moves an uploaded file ($_FILES entry, for example $this->request->files('image')) to public/uploads under a random name and returns its public URL, or '' when the file is refused
// Only the extensions in $allowedExtensions are accepted. The default list (DEFAULT_UPLOAD_EXTENSIONS) covers images, documents, audio, video, zip and json.
// Scripts and markup (php, phtml, phar, cgi, html, js, svg...) are always refused

function isAllowedUpload($filename, $allowedExtensions = null); // tells whether uploadFile() would accept this file name

function deleteFile($url); // deletes a file of public/uploads given its public URL

function import($filename); // requires a library from framework/librairies or a helper from framework/helpers
```

+ Global Variables & Session helper methods
```php
function saveInSession($input, String $sessionVariableName); // saves any data type into a define session variable

function clearFromSession(String $sessionVariableName); // clears a session variable

function session($key, $value = null); // Gets or sets a session variable. When a value is specified (including 0, '' or false) it saves it. Otherwise, it retrieves it. Use clearFromSession() to remove it

function scripts($key, $value = null); // Gets or sets a script stored in the session. When a value is specified it saves it. Otherwise, it retrieves it.

function copyright($value = null); // Gets or sets the copyright for your application to be used on any page. When a value is specified it saves it. Otherwise, it retrieves it.

function keywords($value = null); // Gets or sets the keywords associated with your application to be used on any page. When a value is specified it saves it. Otherwise, it retrieves it.

function metadata($pairs = null); // Gets or sets the metadata of a page. When a key/value pair is specified it will make the change and return the full metadata array

function terminateSession($url); // Destroys a session and redirects to a specified url when the session is destroyed

function getGlobalInformation($superGlobal = 'session'); // Gets PHP Super Globals $_GET, $_POST, $_FILES . By default, it gets $_SESSION
```

+ String & Array helper methods
```php

function generateToken($size = 12); // generates a random string of hexadecimals of a specified size

function findKey($array, $keySearch); // finds a key in a multi-dimensional array

function recursive_filter(Array &$array, Array $allowed); // filters an array based on a set of allowed keys

function loadConstantFromArray($array); // create PHP constants out of an array of key/value pairs

function isJson($string); // Checks if a string is JSON format

function sanitize($string); // performs a basic html sanitization of a string

function parsePath($path); // returns an array of the different parts of a path

function startWith($haystack, $needle); // returns true if the haystack starts with the needle

function endWith($haystack, $needle); // returns true if the haystack ends with the needle

function sanitizePath($path); // removes the leading and trailing slashes of a path

function singularize($word); // returns the singular of a word by simply getting rid of the last character (books -> book, but categories -> categorie)

function rearrange($arr); // returns a new array arranged by index 

function arraySort($array, $attribute, $sort = SORT_DESC); // sorts a multi-dimensional associative array by a specified key

```

+ Time Methods
```php
function getRelativeTime($datetime, $depth=1); //returns relative time such as 1 day ago, 11 hours ago etc.
function now(); // returns current time in a mysql datetime format
```

+ Encrypting Methods
```php
function encryptDecrypt($action, $data, $key = APP_KEY); // action is 'encrypt' or 'decrypt' data based on a specified key. The default key is the APP_KEY defined in the .env

function generateKey($size = 32); // returns a random base64encoded string of a specified size

```
+ Authentication Methods
```php
function authorize($user); // use this method upon your login mechanism to authorize user

function authorized(); // checks if the user within a session is valid and authorized to perform actions

function unauthorize(); // use this method upon your logout mechanism to unauthorize all users

function user(); // returns the logged in user

function guard($permissionAttribute, $acceptedPermission, $loginUrl = '/login', $notAuthorizedUrl = '/notAuthorized'); // redirects to $loginUrl when nobody is logged in and to $notAuthorizedUrl when the user's permission is lower than $acceptedPermission, then stops the script

function logout($redirectUrl = '/'); // logs out an authenticated user and redirects to the specified url

```
+ Image Manipulations

This framework uses a powerful third-party library for simple server-side image manipulations called SimpleImage by Claviska. This library can be immediately used in this framework by calling the `imageCanva()` method:
```php
$image = imageCanva(); // returns a SimpleImage object
```
A SimpleImage object gets returned by this method and all the powerful features of the library can now be used.
> Check the full SimpleImage library [here](https://github.com/claviska/SimpleImage)

## HTML Forms

This framework provides 2 major capabilities that allow your HTML forms to 1) Send `PUT` and `DELETE` requests
and 2) Secure requests again Cross-Site Request Forgery (CSRF)

### 'PUT' and 'DELETE' Form Requests
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

### Cross-Site Request Forgery (CSRF)
Cross-Site Request Forgery is a type of attack that forces the user to execute an unwanted action on a web application where they are already authenticated. This usually comes from the fact that the server does not validate if the form from which the request was made is coming from the same session that the user is currently using. 

In order to remedy to this, this framework provides the capability of signing the HTML `Form` and it makes sure that on the server-side the signature is validated.

This can be achieved using regular HTML:
```html
<form action="/books" method="POST">
    <input type="hidden" name="_token" value="<session ID goes here">
</form>
```
Or, using a Pug directive that will do it automatically for you:
```pug
form(action="/books", method="POST")
    =csrf()   
```

## REST API
A REST API or more appropriately, a RESTful API uses the fundamentals of a REST (Representational State Transfer) communication protocol to break down a `transaction` received through HTTP and translate them into small modules that your application can make sense of and act on.

RESTful APIs take advantage of HTTP methodologies. They use `GET` to retrieve data. They use `POST` to create a resource. They use `DELETE` to delete a resource. They use `PUT` to update a resource.

In the `Routing` section, we explained how this framework deeply relies on such a protocol to route a `Request` to a corresponding `Controller` action. It is therefore already built-in and ready to receive API calls using the same routes that your application uses.

> #### (video) [Create REST-APIs with Caligrafy](https://youtu.be/VYCraK7786c)
>
> In this video, we show how we REST APIs can be created quickly and efficiently with Caligrafy Quill

### API in 4 steps
The most effective way to test the API is to use a service like [Postman](https://www.getpostman.com/). Postman is a great tool that allows to create `GET`, `POST`, `PUT` and `DELETE` requests without any extra coding.
We will use the example of the book application throughout.

**1. API Authorization:** API access must be authorized. The purpose of an API is to open up your services for third-parties to use them. There needs to be a way in your RESt-API to provide authorization for access. Caligrafy does this for you in a seamless way. By providing you with the tools to generate API tokens that sign all HTTP requests to your server even if you want your services to be openly accessible to any party.

+ Single API key for public use
Caligrafy provides you with an easy way to generate an APP_KEY and an API_KEY from the terminal using Caligrafer.
From the terminal, run ```./caligrafer.php generatekeys``` or ```php caligrafer.php generatekeys```. Caligrafer will give you a pair of key/value pairs to put in your ```.env``` file.

+ Generate Multiple API keys for distribution
If you have an APP_KEY and API_KEY already defined in your ```.env``` file or if you created them through Caligrafer, you might need to generate more API_KEY(s) to hand over to third-parties. You can do this with Caligrafer. From the terminal, run ```./caligrafer.php generateapikey``` or ```php caligrafer.php generateapikey```. This will generate a new API_KEY for you to give away.

> **How API keys work:** an `API_KEY` is the `APP_KEY` encrypted with itself, so every key generated from the same `APP_KEY` is valid and they cannot be revoked one by one: changing the `APP_KEY` revokes all of them (and logs every user out). Also, the `API_KEY` is given to the browser in the `env` of the Vue apps served by Caligrafy, so treat it as a public identifier of your application rather than a secret: protect private data with authentication (`authorized()`, `guard()`) as well.

**2. Enabling API Access:**
In the `/application/config/routing/web.php`, you will need to activate API access and define the routes. By default the API access is enabled through ```Auth::activateAPI()```. By commenting out the statement, you disable all API accesses.

```php
try {
    // Activate the API access
    Auth::activateAPI();

    Route::get('/books', 'BookController@readAll'); // returns all books in the database books table
    Route::post('/books', 'BookController@create'); // expects a form with all book attributes to create a book record
    Route::put('/books/{id}', 'BookController@update'); // expects a form with all the book attributes to update for book with specified id
    Route::delete('/books/{id}', 'BookController@remove'); // deletes book with specified id

    Route::run();

} catch(Exception $e) {
  // error handling goes here
}
```

**3.Create a Model:** Create a `books` table in your database and a `Book` model.

> If you need to know more about creating a `Model`, you can read further about it in the respective section of this documentation

**4.Create a Controller:** 

In order to output a result, whether an HTML page or a JSON output, you need to return a `View` from the `Controller`. This example has been tested end to end with the Caligrafy QA suite (`qa/run.sh`). When you first start your project, you most likely don't have any HTML pages or Pug pages created. You however might want to test your api service. In order to help you achieve that, you can return a view that has a `null` template name. 
```php
use Caligrafy\Controller;

class BookController extends Controller {
    public function readAll()
    {
       $this->associate('Book', 'books'); // define the context
       return api($this->all()); // or you can use view instead of api with 'null' template
    }

    public function create()
    {
      $this->associate('Book', 'books'); // define the context
      $parameters = $this->request->parameters(); // get all the parameters posted
      $book = new Book();
      $book->title = isset($parameters['title'])? $parameters['title'] : '';
      $book->author = isset($parameters['author'])? $parameters['author'] : '';
      $book->publisher_id = isset($parameters['publisher_id'])? $parameters['publisher_id'] : null;
      return api($this->save($book));
    }

    public function update()
    {
      $this->associate('Book', 'books'); // define the context
      $book = $this->find(); // finds the book from id specified in the URI

      $parameters = $this->request->parameters(); // get all the parameters posted
      $book->title = isset($parameters['title'])? $parameters['title'] : $book->title; // take the title only if it is set in the post
      $book->author = isset($parameters['author'])? $parameters['author']: $book->author;
      return api($this->save($book));

    }

    public function remove()
    {
      $this->associate('Book', 'books'); // define the context
      return api($this->delete());
    }

}
```
In the future, when you have Pug template created, you just replace the `null` with the name of the template. And guess what? this does not impact the output of your api.

> If you need to know more about creating a `Controller`, you can read further about it in the respective section of this documentation

### Testing the API

Several ways can be used to test the API. We recommend using [Postman](https://www.getpostman.com/) as an API client to avoid extra coding.

+ **Headers:** For all requests through `Postman`, you need to do the following:
In the `Headers` tab, add an 'Accept' key and give it a value 'application/json'. 
This instructs the server that the client needs a JSON output.

+ **Authorization:** For all requests, you need to add an `Authorization` header:
In Postman, you can go to the `Authorization` tab and select `Bearer Token` from the `Type` selector. You will need to enter the `API_KEY` in the `Token` field provided.

+ **GET and DELETE:** For `GET` and `DELETE`, you can test them without any additional settings. All you need to do is enter the respective URI for each.

+ **POST:** For `POST` you need to provide inputs through the `Body` of the request. There are different ways to do this in Postman
    + **Option 1** - In the `Body` tab, select `form-data`: this option allows you to mimic an HTML form that could support both text and files. You need to make sure that all the required inputs for the model are entered appropriately. If your application supports file uploading, then you would probably want to test the API using this option. 

         > After you add your `form-data` inputs, verify that in the `Headers` section, you have another key `Content-Type` that got added with value either `multipart/form-data` or `application/json`. If not, you should add it manually.

    + **Option 2** - In the `Body` tab, select `x-www-form-urlencoded`: this option allows you to send the entire content as a giant query string. This option could be used if you don't have large inputs (such as files) that need to be transferred to the server. Similarly, you need to make sure that all the required inputs for the model are entered appropriately. If your application supports file uploading, this option will not work. 

         > After you add your `x-www-form-urlencoded` inputs, verify that in the `Headers` section, you have another key `Content-Type` that got added with value `application/x-www-form-urlencoded`. If not, you should add it manually.

    - **Option 3** - In the `Body` tab, select `raw`: this option allows you to send a raw file using the format of your choice. The format could be changed to `Text` or `JSON` or `XML` etc. by clicking on the `Text` dropdown.
Then you could type directly in the body the content you want to send in the desired format. 

        > After you add your `raw` inputs in a format, verify that in the `Headers` section, you have another key `Content-Type` that got added with value `application/json` (if the format you chose was JSON). 

+ **PUT:** For `PUT`, since our framework spoofs a `POST` method, options 1 and 2 above won't work. Only option 3 could work. If your application supports file uploading, then you should test the API as a `POST` and add a `_method` key in the `Body` that has the value `PUT`. 

>[Next Section: Learn about Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)
