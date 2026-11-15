The **REST API** of a Caligrafy application is its capabilities, answered in JSON. One line maps a route to a
capability (`Route::capability`), or one line serves them all by name (`Route::capabilities`). Programs send a personal
access token; pages of the application send the session and its CSRF token. Since 2.1.0.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write: add a book and return it; serve it as POST /api/books
```

Claude adds the `Route::capability` line to `web.php` and checks it with `curl`: without a token, with a token that lacks
the scope, and with a good one.

**Check** before you say OK: the calls answer `401`, `403` and `201` as below.

<br/>

## By hand

1. **The routes.** In `application/config/routing/web.php`, before `Route::run();`:
    ```php
    // BOOKSHELF: REST
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    ```
    The method is `GET`, `POST`, `PUT` or `DELETE`. The route variables (`{id}`) become inputs.
2. **A token** for the program: `php caligrafer.php token create "Bookshelf agent" --scopes books:read,books:write`
   ([Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/Personal-Access-Tokens)).
3. **Call it.**
    ```bash
    curl -s http://localhost:8080/api/books/3
    # {"data":{"id":3,"title":"Kindred","published_year":1979,...}}            200: get_book is public

    curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -d '{"title": "Dune"}' http://localhost:8080/api/books
    # {"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}}   401

    curl -s -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
         -d '{"title": "The Lathe of Heaven", "published_year": 1971, "author_id": 1}' http://localhost:8080/api/books
    # {"data":{"id":7,"title":"The Lathe of Heaven",...}}   201
    ```
    A `POST` with neither a token nor `Accept: application/json` is taken for a form and answers `403` `[ERROR 3000]`.

<br/>

## <a name="all"></a>Every capability by name: `Route::capabilities`

```php
Route::capabilities('/capabilities');   // GET lists, POST /capabilities/{name} calls
```

+ `GET /capabilities` answers what the caller may call, with the input schemas, and the session's CSRF token:
  `{"data": [{"name", "description", "scope", "level", "readOnly", "destructive", "public", "input"}, ...], "csrf": "..."}`.
  Web-only capabilities are left out.
+ `POST /capabilities/get_book` with `{"id": 3}` calls `get_book`. The
  [JS client](https://github.com/caligrafy/caligrafy-quill/wiki/The-JS-Client) uses these two routes.

<br/>

## <a name="reference"></a>Requests and responses

**Who calls** (`Context::fromRequest`): `Authorization: Bearer cq_...` (a token), else the legacy API key, else the
session of a signed-in person, else a visitor.

**Inputs**: the JSON body, or the form fields, or the query string for `GET`; then the route variables, which win
(`/api/books/3?id=9` is the book 3). `_token`, `_method` and `uri` are never inputs. Of the inputs, the capability keeps
only those of its `$rules`.

**Files**: a capability with a `file` input takes a multipart upload; the file is saved once the caller is allowed, and
deleted again when the call fails.

**CSRF**: a call made with the session cookie that is not read-only needs the session's CSRF token in an `X-CSRF-Token`
header (or `_token`), else `403` "Missing or invalid CSRF token: send the X-CSRF-Token header". A token or the legacy key
is not a cookie, so it needs none.

**Responses**:

| Status | Body | When |
| --- | --- | --- |
| `200` | `{"data": ...}` | a success |
| `201` | `{"data": ...}` | a `POST` to a capability that is not read-only |
| `401` | `{"error": {"code": "unauthenticated", "message": ...}}`, and `WWW-Authenticate: Bearer` | nobody, or an unknown, revoked or expired token |
| `403` | `{"error": {"code": "forbidden", ...}}` | the scope or the level is missing, or the CSRF token |
| `404` | `{"error": {"code": "not_found", ...}}` | no such capability, or the thing does not exist |
| `409` | `{"error": {"code": "conflict", ...}}` | it clashes with the current state |
| `422` | `{"error": {"code": "invalid", "message": ..., "fields": {...}}}` | an input is wrong, a message per field |

The data is plain: models become arrays of their columns. All the refusals:
[Refusing a call](https://github.com/caligrafy/caligrafy-quill/wiki/Refusing-a-Call-ServiceError).

Reference: [`Route::capability()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-capability),
[`Route::capabilities()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-capabilities),
[`Rest`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#rest).

<br/>

## Common mistakes

+ **A write over the session without `X-CSRF-Token`** (G54): 403. Use the JS client, or send the `csrf` of `GET /capabilities`.
+ **A JSON call from a page without `Accept: application/json`** (G1): it is taken for a form and fails CSRF, `[ERROR 3000]`.
+ **`register_user` over REST** (G81): refused; sign-up is the sign-up page.
+ **A closure route for JSON** (G51): `Route::post('/x', function () {...})` is not protected for JSON. Use a capability route.

<br/>

## Related pages

+ [The JS client](https://github.com/caligrafy/caligrafy-quill/wiki/The-JS-Client) · [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/Personal-Access-Tokens)
+ [Services, capabilities and MCP: REST](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#rest)
+ [Controller APIs](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#restapi): the older way
