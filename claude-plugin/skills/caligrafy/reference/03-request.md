# 3. Request

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `parameters` is a property AND `parameters()` a method; `fetch` is an object property (`$this->request->fetch->id`) AND a method (`fetch('id')`). See api-reference.md > Request.
> - JSON/API calls must send `Accept: application/json` (without text/html) to skip CSRF; see gotchas G1.

# Request

`Caligrafy\Request` holds what a request brings: its method, its address, its route variables, its fields or JSON body,
its files and its headers. In 2.19 you rarely read it yourself: a capability receives its inputs already collected and
checked by its route (`Route::page`, `Route::form`, `Route::capability`...), and a `Context` that says who is calling.
You read the request in a controller or a closure, for HTTP that no declarative route covers.

## With Claude

Describe the behavior; the `caligrafy` skill knows the request API and uses it only where a controller is needed.

```text
"Add GET /authors/{author_id}/books: when the author has one book, go straight to its page; otherwise list them."
```

Claude writes a thin controller in `application/controllers/` that reads `$this->request->fetch('author_id')`, calls a
capability with `$this->call()`, and one `Route::get` line in `application/config/routing/web.php`.

**Check**

- [ ] The controller reads route variables with `$this->request->fetch()`, and passes them to a capability: it never
      reads or writes a model itself.
- [ ] Nothing reads `$_GET`, `$_POST` or `$_FILES` directly where `$this->request` gives the same value.
- [ ] A capability's `handle()` never reads the request (its inputs come from the route).

## By hand

1. **Write a controller that shows what it reads.** `application/controllers/EchoController.php`:

   ```php
   <?php

   use Caligrafy\Controller;

   class EchoController extends Controller
   {
       public function show()
       {
           return api(array(
               'method' => $this->request->method,
               'uri' => $this->request->uri,
               'id' => $this->request->fetch('id'),
               'sort' => $this->request->fetch('sort'),
               'parameters' => $this->request->parameters(),
               'wantsJson' => $this->request->wantsJson(),
           ));
       }
   }
   ```

2. **Route it.** In `application/config/routing/web.php`, before `Route::run();`:

   ```php
       Route::get('/echo/{id}', 'EchoController@show');
       Route::put('/echo/{id}', 'EchoController@show');
   ```

3. **Call it.** Run:

   ```bash
   curl -s "http://localhost:8080/echo/3?sort=title"
   ```

   It prints:

   ```json
   {"method":"GET","uri":"echo/3","id":"3","sort":"title","parameters":{"uri":"echo/3","sort":"title"},"wantsJson":false}
   ```

   `uri` is in the parameters of a `GET` because the server's rewrite rule passes the path as `?uri=` (`.htaccess`).

4. **Send a JSON body.** A JSON call to a controller route needs a personal access token that grants everything
   (`php caligrafer.php token create "echo test" --scopes "*"` prints it once) or, from a page, the `X-CSRF-Token` header:

   ```bash
   curl -s -X PUT -H "Accept: application/json" -H "Authorization: Bearer cq_..." \
        -H "Content-Type: application/json" http://localhost:8080/echo/3 -d '{"title":"Dune"}'
   ```

   It prints:

   ```json
   {"method":"PUT","uri":"echo/3","id":"3","sort":"","parameters":{"title":"Dune"},"wantsJson":true}
   ```

   Without the token it answers `401` with `{"error":{"code":1004,"message":"Access Denied: send the page's CSRF token in
   an X-CSRF-Token header (pages, with the session cookie) or Authorization: Bearer <personal access token> (programs
   and agents)"}}`.

**Check**

- [ ] `fetch('id')` returns the route variable as a string (`"3"`), `fetch('sort')` the query string value, and `''`
      for a name the request does not have.
- [ ] A form post's parameters include `_token` and `_method`; a JSON body's parameters are the decoded body.

## Getting the request

+ In a controller: `$this->request`, built once when the controller is created.
+ Anywhere else in `application/` (a closure route, a helper): `request()`, which builds a new `Request` each time.
+ In a capability: never. Its inputs come from the route (the fields, the query string and the route variables, the
  route variables winning), and `$context` says who calls (`$context->user`, `$context->userId()`).

## Properties

| Property | What it holds | Example for `PUT /echo/3` with `{"title":"Dune"}` |
| --- | --- | --- |
| `$method` | the HTTP method in upper case, after `_method` (a `POST` with `_method=PUT` is `PUT`) | `PUT` |
| `$uri` | the path without its leading and trailing slashes, `/` for the home page | `echo/3` |
| `$fullUri` | the path, `?`, and the parameters as a query string | `echo/3?title=Dune` |
| `$uriComponents` | the segments of the path | `['echo', '3']` |
| `$parsedURI` | the same segments, from `parsePath()` | `['echo', '3']` |
| `$uriParameters` | `GET`: the query string (`$_GET`); `POST`, `PUT`, `DELETE`: the form fields, else the decoded JSON body | `['title' => 'Dune']` |
| `$parameters` | `$uriParameters`, or the route variables when there are none | `['title' => 'Dune']` |
| `$currentRoute` | the pattern of the matched route | `echo/{id}` |
| `$currentBind` | the route variables, plus the query string for a `GET` route | `['id' => '3']` |
| `$fetch` | `$currentBind` as an object: `$this->request->fetch->id` | `{id: "3"}` |
| `$host` | the `Host` header | `localhost:8080` |
| `$authorization` | the `Authorization` header | `Bearer cq_...` |
| `$accept` | the `Accept` header | `application/json` |
| `$acceptEncoding`, `$acceptLanguage`, `$cache`, `$cookie`, `$userAgent` | those headers | |
| `$token` | the personal access token row, once `authorizeApiRequest()` checked it (`null` before) | |

## Methods

| Method | Returns |
| --- | --- |
| `fetch($name)` | a route variable (or, for a `GET` route, a query string value) by name; by position when `$name` is an integer (`fetch(1)` is the first); `''` when there is none |
| `parameters()` | the request's parameters again (as `$uriParameters`) |
| `files($field)` | the `$_FILES` entry of a file input (`name`, `type`, `tmp_name`, `error`, `size`), `[]` when there is none |
| `wantsJson()` | `true` when `Accept` contains `application/json` and not `text/html` (an API call) |
| `bearer()` | the value after `Bearer ` in `Authorization`, else `null` |
| `authorizeSessionRequest()` | `true` when the `X-CSRF-Token` header (or a `_token` field) equals the session's CSRF token: a page of this app |
| `authorizeApiRequest($scope = '*')` | `true` for a personal access token that grants `$scope`, or the legacy API key while `LEGACY_API_KEY` is not `false` |
| `apiRefusal()` | why a JSON call to a controller route was refused (the text after `Access Denied:`) |
| `isLegacyApiKey($key)`, `Request::legacyKeysDisabled()` | whether a value is this app's legacy API key; whether `LEGACY_API_KEY=false` turned those keys off |
| `forClient()` | what a client app's page may see of the request (2.19.1): `method`, `host`, `uri`, `fullUri`, `uriComponents`, `uriParameters` (the query string, without `_token`), `parameters`, `parsedURI`, `currentRoute`, `fetch` and `acceptLanguage`. Never the `Cookie` (the session id), the `Authorization` header or a `POST` body. The `Client/app` view gives it to the app's script as `env.request` |

**The CSRF check.** Building a `Request` for a `POST`, `PUT` or `DELETE` checks the CSRF token, and stops with `403`
`[ERROR 3000] Security Error - Cross Site Forgery` when the `_token` field is missing or wrong. It is skipped when the
request carries a bearer token, sends the session's token in `X-CSRF-Token`, asks for JSON (`wantsJson()`), posts to a
`Route::webhook` address, a `Route::mcp` address (2.19.1: without a personal access token it answers `401` JSON-RPC
with `WWW-Authenticate`, never as the session's user) or the notifications unsubscribe page, or comes from a `User-Agent` listed in
`APPROVED_AGENTS`. The token is read from the form fields only, never from a JSON body.

## Common mistakes

+ **`fetch('id')` is a string.** Compare with `==` or cast it: `(int) $this->request->fetch('id')`. Give it to a
  capability with an `integer` rule rather than checking it yourself.
+ **`fetch()` of a query string value on a `POST` route** returns `''`: the query string is only merged for `GET`
  routes. Read the body with `parameters()`.
+ **A JSON body without `Accept: application/json`** fails the CSRF check (`[ERROR 3000]`): the token is only read
  from form fields. Send `Accept: application/json` and `X-CSRF-Token` (pages) or a bearer token (programs).
+ **`fetch()` from the browser sends `Accept: */*`**, which is not JSON: set `Accept: application/json` yourself.
  `axios` sends `application/json, text/plain, */*`, which is.
+ **Reading the request in a capability**: it works for the web only. REST, MCP, the command line and jobs have no
  such request; declare the input in `$rules` instead.

## Related pages

+ [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing): how a request finds its route
+ [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers): `$this->request` and `$this->call()`
+ [Helpers, forms and REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API): `request()`, `csrf()`, `methodField()`
+ [Build once: services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#tokens): personal access tokens

>[Next Section: Learn about Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)
