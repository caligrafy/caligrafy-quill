# 9. Helpers, Forms & REST API

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `redirect()` does not exit (G5); `guard()` defaults are `$loginUrl='/login'`, `$notAuthorized='/notAuthorized'` and it exits.
> - API calls need BOTH `Authorization: Bearer <API_KEY>` and `Accept: application/json` (gotchas G1). The `API_KEY` is exposed to client apps (G27).

# Helpers, forms and REST API

Three things that every Caligrafy app uses: the **global helpers** (functions such as `view()`, `redirect()`,
`csrf()`, `old()`), **forms** (a form posts to a capability with `Route::form`, protected by a CSRF token), and the
**REST API** (each capability as a JSON endpoint with `Route::capability`, called with a personal access token). The
same capability answers the form, the API, the Vue apps and AI agents.

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write
"Give create_book a REST endpoint at POST /api/books, and a token for my import script."
```

Claude writes the capability, the `Route::capability` line in `application/config/routing/web.php`, and creates the
token with `php caligrafer.php token create "<what it is for>" --scopes books:write`. The token is shown once: store it
as a secret yourself; Claude never writes it into a file of the project. For a form, the same skill writes
`Route::form` and the view ([Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form)).

**Check**

- [ ] `curl` with the token answers `201` and `{"data": ...}`; without it, `401`.
- [ ] A token without the scope answers `403` "This needs the scope books:write".
- [ ] A missing input answers `422` with `fields`.
- [ ] No token, key or secret was written to a file of the project or printed in a commit.

## By hand: a REST API

Before you start: the Bookshelf tables, `list_books` and `get_book` of
[Routing, steps 1 to 3](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes), and `create_book`
of [Declarative routes, step 1](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form). The
framework tables must be installed (`docker compose exec dev-box php caligrafer.php platform install`, done by the
Quickstart).

1. **Declare the endpoints.** In `application/config/routing/web.php`, before `Route::run();`:

   ```php
       // the Bookshelf REST API: one route, one capability
       Route::capability('GET', '/api/books', 'list_books');
       Route::capability('GET', '/api/books/{id}', 'get_book');
       Route::capability('POST', '/api/books', 'create_book');
   ```

2. **Create a token for the program that calls it.** Run:

   ```bash
   docker compose exec dev-box php caligrafer.php token create "Bookshelf script" --scopes "books:read,books:write"
   ```

   It prints ` Token #<n> "Bookshelf script" created with books:read books:write`, the token (`cq_<id>_<40 hex
   characters>`) and ` It is shown only now: store it as a secret (Authorization: Bearer <token>).`

3. **Call the API.** Run:

   ```bash
   curl -s -H "Accept: application/json" http://localhost:8080/api/books/3
   ```

   It prints `{"data":{"id":3,"title":"Kindred","published_year":1979,"summary":"A young woman is pulled back in time to a plantation in Maryland.","author_id":2,"created_at":"...","modified_at":"...","author":{"id":2,"name":"Octavia E. Butler",...}}}`
   (`get_book` is public: no token needed). Create a book with the token:

   ```bash
   curl -s -i -X POST -H "Accept: application/json" -H "Authorization: Bearer cq_..." \
        -H "Content-Type: application/json" http://localhost:8080/api/books \
        -d '{"title":"The Dispossessed","published_year":1974,"author_id":1}'
   ```

   It answers `201` with `{"data":{"id":<new id>,"title":"The Dispossessed","published_year":1974,...,"author":{"id":1,"name":"Ursula K. Le Guin",...}}}`.
   The same call without the `Authorization` header answers `401` with
   `{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}}`; with
   `-d '{"title":"","published_year":1969,"author_id":9}'` it answers `422` with
   `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"title":"The Title field is required","author_id":"There is no such author"}}}`.

**Check**

- [ ] `GET /api/books` answers `{"data":{"data":[...],"total":6,"page":1,"per_page":20,"pages":1}}` (a page of
      `list_books`).
- [ ] `POST /api/books` with the token answers `201`; without it `401`; with a `books:read` token `403`.
- [ ] `docker compose exec dev-box php caligrafer.php audit --capability create_book` lists the call.

## REST API

A capability becomes a JSON endpoint in one line. The route variables become inputs (`{id}` is the input `id`).

```php
Route::capability('GET', '/api/books', 'list_books');
Route::capability('GET', '/api/books/{id}', 'get_book');
Route::capability('POST', '/api/books', 'create_book');
Route::capability('PUT', '/api/books/{id}', 'update_book');
Route::capability('DELETE', '/api/books/{id}', 'delete_book');
Route::capabilities('/capabilities');   // GET /capabilities lists what the caller may call; POST /capabilities/{name} calls one
```

**Who may call.** A personal access token (`Authorization: Bearer cq_...`) that grants the capability's scope
(`books:write`, `books:*` or `*`), or the signed-in person (a page of the app, with the session cookie). A session call
that changes data also sends the CSRF token in an `X-CSRF-Token` header (`GET /capabilities` returns it in `csrf`; the
Vue apps send it with `apiHeaders()`). A `public: true` capability answers anyone. Tokens:
`php caligrafer.php token create <name> --scopes <scopes> [--user <id>] [--expires <days>]`, `token list`,
`token revoke <id>` ([Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#tokens)).

**Inputs.** The JSON body (or the form fields, or the query string for a `GET`), and the route variables. On a `POST`,
`PUT` or `DELETE` the route variables win; on a `GET`, a query string value of the same name replaces them.
Only the inputs the capability declares in `$rules` reach it. A `file` input takes a multipart upload
(`curl -F "cover=@cover.png"`) ([File inputs](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#files)).

**Answers.**

| Status | Body | When |
| --- | --- | --- |
| `200` | `{"data": ...}` | the capability answered |
| `201` | `{"data": ...}` | a `POST` to a capability that changes data |
| `401` | `{"error":{"code":"unauthenticated",...}}` (with `WWW-Authenticate: Bearer`) | nobody signed in and no valid token |
| `403` | `{"error":{"code":"forbidden","message":"This needs the scope books:write"}}` | the token lacks the scope, the person the level, or a session write lacks `X-CSRF-Token` |
| `404` | `{"error":{"code":"not_found",...}}` | the capability found nothing |
| `409` | `{"error":{"code":"conflict",...}}` | a duplicate |
| `422` | `{"error":{"code":"invalid","message":"...","fields":{"title":"..."}}}` | an input breaks a rule |

**Errors of the framework** (not of a capability) answer with a real status too, and JSON to a JSON request:

| Status | Error | When |
| - | - | - |
| `401` | `1004` Access Denied | a JSON call to `Route::action` or a controller route without `X-CSRF-Token` (pages) or a token that grants `*`. The message ends with the reason |
| `403` | `3000` Cross Site Forgery | a form post without a valid `_token` |
| `403` | `1006` Forbidden | a `Route::page`, `Route::form`, `Route::action` or `Route::download` whose capability refuses the person |
| `404` | `1001` Invalid Controller or Action | a route names a controller or an action that does not exist |
| `404` | `1005` Page Not Found | no route matches, or a page's capability finds nothing |
| `405` | `1000` Invalid Http Request Method | the address exists with other methods (`Allow` header) |
| `500` | any other error | the message is shown only with `APP_DEBUG=true` for PHP fatal errors |

```json
{"error":{"code":1005,"message":"Page Not Found"}}
```

Test with `curl` or an API client (Postman, Bruno): set `Accept: application/json`, `Content-Type: application/json`
for a JSON body, and the `Authorization: Bearer cq_...` header.

### Other ways: controller APIs and the legacy API key

Before capabilities, an API was a controller whose actions returned `api($data)`:

```php
Route::get('/legacy/books/{id}', 'LegacyBookController@show');   // the controller of 8.-Controllers, Other ways
```

+ A JSON call to a controller route needs, with `Auth::activateAPI();` in `web.php` (the template has it), a token
  that grants **everything** (`--scopes "*"`), or the page's `X-CSRF-Token` header. Scoped tokens are refused there:
  give programs capability routes instead.
+ **The legacy API key** (`php caligrafer.php generatekeys`, `generateapikey`) is the `APP_KEY` encrypted with itself:
  every key of one `APP_KEY` is valid, and they cannot be revoked one by one. It still works while `LEGACY_API_KEY` is
  not `false` (Caligrafy Cloud sets `false`). Give each program a personal access token instead.
+ `api($data)` and `view(null, $data)` print `$data` as JSON; set a status first with `http_response_code()`. The
  replacement is a capability with `Route::capability`: the same rules for the web, REST and agents.

## Forms

A form posts to `Route::form`, which calls a capability and redirects
([Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#form) has the complete
example):

```php
Route::page('/books/new', 'Bookshelf/new', ['signedIn' => true, 'data' => ['authors' => 'list_authors']]);
Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
```

```pug
form(method='POST', action=$home . 'books')
  =csrf()
  .cq-field
    label(for='title') Title
    input#title.form-control(type='text', name='title', value=old('title'))
    if errors('title')
      p.cq-field-error= errors('title')
  button.btn.btn-primary(type='submit') Add book
```

| In the view | What it gives |
| --- | --- |
| `=csrf()` | `<input type='hidden' name='_token' value='<the session's CSRF token>'>`: every `POST`, `PUT` and `DELETE` form needs it |
| `=methodField('PUT')` | `<input type='hidden' name='_method' value='PUT'>`: the form reaches a `PUT` (or `DELETE`) route |
| `errors('title')` | the message of the field after a refused post, or `null`; `errors()` gives them all |
| `old('title', $default)` | what the field held after a refused post, else `$default` (never passwords or other secrets) |
| `flash('success')`, `flash('error')` | the message of the previous post; `flash('key', 'text')` keeps one for the next request |

**CSRF** (cross-site request forgery: another site making your visitor's browser post to your app). The framework
checks every `POST`, `PUT` and `DELETE` that is not JSON: its `_token` must be the session's token, else `403`
`[ERROR 3000] Security Error - Cross Site Forgery`. A script that sends JSON sends the token in an `X-CSRF-Token`
header instead. Read the token with `session('token')`, never `$_SESSION['token']`: since 2.19.0 a session starts
only when it is needed.

A form that does more than call one capability and redirect posts to a
[`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action) (`'method' => 'POST'`): same `=csrf()`, same `errors()`, `old()` and `flash()` after a
refusal, and the capability chooses what comes next. In plain HTML the field is
`<input type="hidden" name="_token" value="<?= session('token') ?>">`.

### Other ways: a hand-written form to a controller

A form in older code posts to a controller route (`Route::post('/books', 'BookController@store')`) whose action calls
`$this->call('create_book')` and renders the errors itself. It keeps working
([Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#callcapability)), and it needs `=csrf()` too.

## Helpers

Global functions of `framework/helpers/corehelpers.php`, available everywhere in `application/` and in the views.

**Views, requests and routing**

| Function | What it does |
| --- | --- |
| `view($viewName, $viewData = [])` | renders `application/views/<viewName>.pug` with the data; a request that asks for JSON gets the data as JSON |
| `api($viewData = [])` | answers the data as JSON |
| `redirect($url)` | sends a `Location` header: a path of the site, or a full `http(s)://` address as it is. It does not stop the script: `return` after it |
| `request()` | a new `Caligrafy\Request` |
| `currentRoute()`, `currentBind()` | the matched route pattern and its variables |
| `csrf()`, `methodField($method)` | print the hidden `_token` and `_method` inputs (`=csrf()` in Pug) |
| `errors($field = null)`, `old($field, $default = '')`, `flash($key, $value = null)` | the form helpers ([above](#forms)) |
| `connect()` | the database connection (PDO) |
| `import($filename)` | includes `framework/librairies/<name>.php` or `framework/helpers/<name>.php` |

**Session and page settings**

| Function | What it does |
| --- | --- |
| `session($key, $value = null)` | reads a session value, or saves one (any value but `null`, including `0`, `''` and `false`) |
| `saveInSession($data, $name)`, `clearFromSession($name)` | save or remove a session value |
| `scripts($key, $value = null)` | the URL of a front-end library (`bootstrap_css`, `bootstrap_script`, `vue`, `axios`, `jquery`, `wysiwyg_css`, `wysiwyg_js`...), pinned in `framework/settings/coresettings.php`; override one in `application/config/config.php` with `scripts('vue', '<url>')` |
| `metadata($pairs = [])` | merges `title`, `description`, `image`... into the page's metadata and returns it ([Metadata](https://github.com/caligrafy/caligrafy-quill/wiki/9.3-Metadata-&-Social-Rich-Cards)) |
| `keywords($value = null)`, `copyright($value = null)` | the page keywords and the copyright line |
| `design_stylesheet()` | the URL of the `DESIGN_STYLE` stylesheet, or `''` |
| `terminateSession($url)` | empties the session and redirects |
| `getGlobalInformation($superGlobal = 'session')` | `$_SESSION`, or `'post'`, `'get'`, `'files'` (those are then unset) |
| `isSessionExpired($time)` | whether the session was idle more than `$time` seconds |

**Files**

| Function | What it does |
| --- | --- |
| `uploadFile($file, $allowedExtensions = null)` | moves a `$_FILES` entry to `public/uploads/<random>.<ext>` and returns its public URL, or `''` when refused. In a capability, the `file` rule does this for you |
| `isAllowedUpload($filename, $allowedExtensions = null)` | whether `uploadFile()` would accept the name. Default list `DEFAULT_UPLOAD_EXTENSIONS`: jpg jpeg png gif webp bmp ico heic pdf txt csv md rtf doc docx xls xlsx ppt pptx odt ods odp mp3 wav ogg m4a mp4 mov webm avi zip json. Scripts and markup (php, phtml, phar, cgi, pl, py, sh, asp, jsp, htaccess, ini, svg, html, js, exe, bat) are always refused |
| `deleteFile($url)` | deletes a file of `public/uploads/` by its URL (never a hidden file) |
| `imageCanva()` | a new `claviska\SimpleImage` for image changes ([SimpleImage](https://github.com/claviska/SimpleImage)) |
| `rearrange($files)` | turns a multiple file input's `$_FILES` entry into one array per file |

**HTTP and mail**

| Function | What it does |
| --- | --- |
| `httpRequest($url, $method, $data, $headers, $username = null, $password = null)` | a cURL request; `$data` is sent as JSON for every method but `GET`; returns the decoded JSON |
| `httpFile($url, $filepath, $headers, $username = null, $password = null, $purpose = 'assistants')` | posts a file (multipart) |
| `sendMail($recipients, $emailContent, $from)` | sends an email through `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`: `['email' => ..., 'name' => ...]`, `['subject' => ..., 'body' => '<p>html</p>']`, `['email' => ..., 'name' => ...]`; returns `true` or `false` |

**Authentication** (need `Auth::authentication('User', 'users');` in `web.php`)

| Function | What it does |
| --- | --- |
| `authorize($user)`, `unauthorize()` | signs a user in or out of the session (`Route::auth` does this for you) |
| `authorized()` | whether someone signed in is still valid |
| `user()` | the signed-in user |
| `guard($attribute, $level, $loginUrl = '/login', $notAuthorized = '/notAuthorized')` | sends visitors to `$loginUrl` and people below `$level` to `$notAuthorized`, then stops |
| `logout($redirectUrl = '/')` | signs out, ends the session and redirects |

**Strings, arrays, time and keys**

| Function | What it does |
| --- | --- |
| `generateToken($size = 12)` | `2 × $size` random hexadecimal characters |
| `generateKey($size = 32)` | random bytes in base64 (the `APP_KEY` format) |
| `encryptDecrypt($action, $data, $key = APP_KEY)` | `'encrypt'` or `'decrypt'` with AES-256-CBC |
| `sanitize($string)` | `htmlentities()` in UTF-8 |
| `isJson($string)` | whether the string is valid JSON |
| `findKey($array, $key)`, `recursive_filter(&$array, $allowed)` | find a key at any depth; keep only allowed keys |
| `arraySort($array, $attribute, $sort = SORT_DESC)` | sorts rows by a column |
| `loadConstantFromArray($array)` | defines a constant for each key |
| `parsePath($path)`, `sanitizePath($path)`, `startWith($s, $p)`, `endWith($s, $p)` | path and string checks |
| `bindRouteToVar($route, $uri)`, `parseRoute($path)` | the variables of a route pattern |
| `singularize($word)` | drops the last letter (`books` -> `book`, but `categories` -> `categorie`) |
| `now()` | the current time as `Y-m-d H:i:s` |
| `getRelativeTime($datetime, $depth = 1)` | `3 days ago`, `2 hours left` |

## Common mistakes

+ **A form without `=csrf()`** answers `403` `[ERROR 3000]`.
+ **`fetch()` from the browser without `Accept: application/json`**: it is treated as a form post and needs `_token`.
+ **A JSON session call that writes, without `X-CSRF-Token`**: `403` "Missing or invalid CSRF token".
+ **A scoped token on a controller route**: `401` `[ERROR 1004]`; controller routes need `*`.
+ **Code after `redirect()`** keeps running.
+ **`$_SESSION['token']` read before the session started** is empty: use `session('token')`.
+ **Checking the status of a client call as `200` only**: errors answer with their real status (since the security
  release; earlier versions answered `200`).

## Related pages

+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): `Route::form` and the file rule
+ [Views: forms](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#formhelpers)
+ [Build once: REST, tokens, the JS client, MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#rest)
+ [Vue apps: requests](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.3-Requests): `apiHeaders()`
+ [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)

>[Next Section: Learn about Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)
