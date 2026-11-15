[MCP](https://modelcontextprotocol.io), the Model Context Protocol, is how AI agents such as Claude Code and Cursor use
tools. With `Route::mcp('/mcp')` every capability is a tool: its name, its description, its input schema, and hints that
say whether it only reads or destroys data. An agent sees only the tools its token allows, and its calls go through the
same checks, validation and audit as everyone else's. Since 2.1.0.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability list_books books:read read-only public: the catalog, for agents too
```

Claude adds `Route::mcp('/mcp')` to `web.php` when agents need it, creates a token with the scopes they need (shown once),
and checks `tools/list` and `tools/call`.

**Check** before you say OK:
+ `tools/list` with the agent's token lists the tools you expect, and not the ones its scopes do not allow;
+ an invalid `tools/call` answers `"isError": true` with a message per field.

<br/>

## By hand

1. **The route.** In `application/config/routing/web.php`, before `Route::run();`:
    ```php
    Route::mcp('/mcp');   // AI agents: personal access tokens only
    ```
2. **A token.** `docker compose exec dev-box php caligrafer.php token create "Bookshelf agent" --scopes books:read,books:write`
   ([Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/Personal-Access-Tokens)).
3. **Connect Claude Code** over HTTP:
    ```bash
    claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer cq_..."
    ```
   or over stdio, the agent starting the server itself in the project folder:
    ```bash
    claude mcp add bookshelf -- docker compose exec -T dev-box php caligrafer.php mcp
    ```
4. **Try it as an agent does**, one JSON-RPC message per request:
    ```bash
    curl -s -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
         -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' http://localhost:8080/mcp
    curl -s -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
         -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"create_book","arguments":{"title":"Dune"}}}' \
         http://localhost:8080/mcp
    ```
    ```json
    {"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"{\"error\":{\"code\":\"invalid\",\"message\":\"Some of the inputs are invalid\",\"fields\":{\"published_year\":\"The Published Year field is required\",\"author_id\":\"The Author Id field is required\"}}}"}],"isError":true}}
    ```

<br/>

## <a name="tools"></a>From capabilities to tools

`tools/list` gives one tool per capability the token may call (`Capabilities::available()`): public ones, and those whose
scope and level it passes. Web-only capabilities and those declared `mcp: false` are left out.

```json
{"name": "create_book", "title": "Create book", "description": "Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id.",
 "inputSchema": {"type": "object", "properties": {
    "title": {"type": "string", "maxLength": 200, "description": "The title of the book"},
    "published_year": {"type": "integer", "minimum": 1450, "maximum": 2026, "description": "The year it was first published, for example 1969 (not after this year)"},
    "summary": {"type": "string", "maxLength": 2000, "description": "Optional: one or two sentences about the book"},
    "author_id": {"type": "integer", "description": "The id of its author (list_authors)"}},
  "additionalProperties": false, "required": ["title", "published_year", "author_id"]},
 "annotations": {"readOnlyHint": false, "destructiveHint": false, "openWorldHint": false}}
```

| Tool field | From |
| --- | --- |
| `name` | the capability's `name` |
| `title` | the name with spaces, capitalized (`create_book`: Create book) |
| `description` | the capability's `description`: write it for an agent, with what it returns and where to find the ids it needs |
| `inputSchema` | its `$rules` and `$descriptions` ([Capability inputs](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Inputs#schema)) |
| `readOnlyHint` | `readOnly` |
| `destructiveHint` | `destructive`, when it is not also read-only |
| `openWorldHint` | always `false` |

`initialize` answers the server's name (`APP_NAME`), the framework version and these instructions for the agent: "Tools
are the capabilities of this application that your token allows. Tools marked destructive delete or overwrite data:
confirm with the user first."

**Calls.** `tools/call` runs `Capabilities::call()` with the arguments. A success is `"isError": false`, the data as JSON
in `content[0].text` and the object in `structuredContent` (a list is given as `{"result": [...]}`). A refusal is a tool
result with `"isError": true` and the same error as REST, so the agent can read the reason and correct itself. A tool the
token cannot see, or one that does not exist, answers the JSON-RPC error `-32602` `Unknown tool: <name>`.

<a name="mcpfalse"></a>**`mcp: false`** keeps a capability out of the tools: one only a browser can use, such as a push
subscription. REST still reaches it.

<br/>

## <a name="http"></a>Over HTTP: `Route::mcp`

+ `POST` only, one JSON-RPC 2.0 message per request (no batches), answered in JSON (no event stream): `initialize`,
  `ping`, `tools/list`, `tools/call`. A notification (no `id`) gets `202` and no body. `GET` and `DELETE` answer `405`.
+ Protocol versions `2025-11-25`, `2025-06-18`, `2025-03-26`, `2024-11-05`; any other request gets the first.
+ **Personal access tokens only**, never the session or the legacy key. Without a valid token: `401`,
  `WWW-Authenticate: Bearer error="invalid_token"` and `{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Unauthorized: send Authorization: Bearer <personal access token>"}}`.
+ A browser request from another site (an `Origin` header) is refused with `403 Origin not allowed`, against DNS
  rebinding. Allowed: no `Origin` (agents), the application's own host, and the origins of `MCP_ALLOWED_ORIGINS` in `.env`
  (comma separated, such as `MCP_ALLOWED_ORIGINS=https://inspector.example`).
+ A message that is not JSON, or not one JSON-RPC request, answers `400`.

<br/>

## <a name="stdio"></a>Over stdio: `php caligrafer.php mcp`

One JSON-RPC message per line on standard input, one answer per line on standard output. Without a token it has full
access, like any command you run yourself, and says so on standard error:
`caligrafer mcp: no token, full local access (set CALIGRAFY_TOKEN to limit it)`. With `CALIGRAFY_TOKEN=cq_...` (or
`--token cq_...`) it has the rights of that token. For agents that reach the application over the network, use
`Route::mcp` and a token.

Reference: [`Route::mcp()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-mcp),
[`Mcp\Server`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#mcp-server),
[`Mcp\Http`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#mcp-http).

<br/>

## Common mistakes

+ **Sending the legacy key to MCP** (G56): 401. MCP takes tokens only.
+ **`webOnly: true` on a feature agents need** (G143): it is not a tool. Keep the work in a plain capability.
+ **`register_user` over MCP** (G81): refused; sign-up is the sign-up page.
+ **No page to create tokens online** (G79): agents cannot connect to a deployed application without one.

<br/>

## Related pages

+ [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/Personal-Access-Tokens) · [Scopes and access](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access)
+ [Read-only and destructive capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Read-Only-and-Destructive-Capabilities)
+ [Services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp)
+ [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
