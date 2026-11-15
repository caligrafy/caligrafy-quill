# Capability routes: REST, the JS client, MCP, notifications and bots

Five lines of `web.php` serve the capabilities to programs instead of browsers: one capability at one REST address
(`Route::capability`), all of them for the JS client (`Route::capabilities`), all of them as tools for AI agents
(`Route::mcp`), and two framework sets of routes, the pages of notification emails (`Route::notifications`) and the data
routes of the Watson bot apps (`Route::bots`).

```php
Route::capability('GET', '/api/books', 'list_books');          // one route, one capability, JSON
Route::capability('GET', '/api/books/{id}', 'get_book');       // {id} becomes the input "id"
Route::capability('POST', '/api/books', 'create_book');
Route::capabilities('/capabilities');                          // GET lists, POST /capabilities/{name} calls
Route::mcp('/mcp');                                            // AI agents: the capabilities their token allows
Route::notifications();                                        // the confirm and unsubscribe pages of notification emails
Route::bots();                                                 // the data routes of the Watson bot apps
```

<br/>

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write: also as POST /api/books, for the JS client, and for AI agents
```

**Check** before you say OK:
+ each REST route names a capability, never a closure;
+ `Route::mcp` is there when agents should use the app, and a token is created with only the scopes they need;
+ `php caligrafer.php client` was run again after adding or changing a capability.

<br/>

## <a name="what"></a>What each one declares

| Line | Declares | Who calls |
|---|---|---|
| `Route::capability($method, $route, $name)` | `$method $route` (`GET`, `POST`, `PUT` or `DELETE`; anything else stops with an error) | programs with a personal access token, or a page of the app with the session |
| `Route::capabilities($prefix = '/capabilities')` | `GET {prefix}` (what the caller may call, with the input schemas and the session's CSRF token) and `POST {prefix}/{name}` | the JS client of the Vue apps and the SPA |
| `Route::mcp($route = '/mcp')` | `POST {route}` (one JSON-RPC message per request); `GET` and `DELETE` answer `405` | AI agents, with a personal access token only |
| `Route::notifications()` | `GET /notify/confirm/{token}`, `GET` and `POST /notify/unsubscribe` | the links of notification emails (2.15.0) |
| `Route::bots()` | `POST /__bots__/{appName}/communicate`, `POST /__bots__/{appName}/{appId}`, `DELETE /__bots__/{appName}` | the Watson bot apps of `public/` (2.21.0) |

<br/>

## <a name="rest"></a>REST: `Route::capability`

```bash
curl -s http://localhost:8080/api/books/3
# {"data":{"id":3,"title":"Kindred","published_year":1979,...}}            200: get_book is public

curl -s -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
     -d '{"title": "X", "published_year": 1971, "author_id": 42}' http://localhost:8080/api/books
# {"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"author_id":"There is no such author"}}}   422
```

+ **Requests**: a JSON body, form fields, or the query string for `GET`. Then the route variables, which win
  (`/books/3?id=9` gives `id = 3`, 2.21.2).
+ **Responses**: `200` with `{"data": ...}`, `201` when a `POST` changes data, or `{"error": {"code", "message",
  "fields"}}` with `401` (and `WWW-Authenticate: Bearer`), `403`, `404`, `409` or `422`.
+ **Who calls**: `Authorization: Bearer <token>`, or the session of a signed-in person. A session call that changes data
  needs the `X-CSRF-Token` header (`403` "Missing or invalid CSRF token" otherwise).
+ A `POST` with neither a token nor `Accept: application/json` is taken for a form and answers `403` `[ERROR 3000]`.
+ Files: a `file` rule takes the uploaded file ([File uploads](https://github.com/caligrafy/caligrafy-quill/wiki/File-Uploads)).

<br/>

## <a name="client"></a>The JS client: `Route::capabilities`

```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
```

writes one JavaScript function per capability, calling `/capabilities`. In a page of the app it uses the session (the
cookie, and the CSRF token it reads once from `GET /capabilities`); from Node or another site, a token:
`configure({ baseUrl: 'http://localhost:8080', token: process.env.CALIGRAFY_TOKEN })`.
`GET /capabilities` answers `{"data": [{"name", "description", "scope", "level", "readOnly", "destructive", "public",
"input"}, ...], "csrf": "<the session's CSRF token>"}`. More:
[The JS client](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#client).

<br/>

## <a name="mcp"></a>AI agents: `Route::mcp`

Every capability the token allows is a tool, with its description, its input schema and the hints `readOnlyHint` and
`destructiveHint`. A refused call is a tool result with `"isError": true`, so the agent can correct itself.

```bash
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer cq_..."
```

Without a token it answers `401` with `WWW-Authenticate` (2.19.1). MCP addresses skip the form CSRF check: the token is
the proof. More: [MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp).

<br/>

## Reference

+ [`Route::capability()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-capability),
  [`Route::capabilities()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-capabilities),
  [`Route::mcp()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-mcp),
  [`Route::notifications()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-notifications),
  [`Route::bots()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-bots)
+ [`Caligrafy\Rest`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#rest),
  [`Caligrafy\Client`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#client),
  [`Caligrafy\Mcp\Http`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#mcp-http),
  [`Caligrafy\Mcp\Server`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#mcp-server)

<br/>

## Common mistakes

+ **A JSON write with the session and no `X-CSRF-Token`**: `403`. The JS client sends it; `fetch()` by hand must too.
+ **Data work in a closure route** (`Route::post('/x', function () {...})`): it has no permission rules and no CSRF
  check for JSON calls. Use `Route::capability`.
+ **MCP with the legacy API key**: refused (`401`); agents use personal access tokens.
+ **A `webOnly` capability behind these routes**: it answers "There is no capability"; only `Route::action` reaches it.
+ **A webhook's capability** is served by these routes too: guard it with `$context->actorType() !== 'webhook'`
  ([Webhooks](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Webhook)).

## Related pages

+ [Build once: services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
+ [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)
+ [Creating bots with Watson](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson)
+ [Vue: calling capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.3-Requests#capabilities)
+ [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#capabilityroutes)
