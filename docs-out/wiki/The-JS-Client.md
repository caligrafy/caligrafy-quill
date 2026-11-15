The **JS client** is one ES module, with no dependency, that `php caligrafer.php client` writes from your capabilities:
one async function per capability (`create_book` becomes `createBook`), plus `call()`, `capabilities()`, `configure()`
and `CapabilityError`. Vue apps, the Vite SPA and Node scripts call the capabilities through it, with the session in a
page of the application and a token anywhere else. It talks to `Route::capabilities()`. Since 2.1.0.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write: add a book from the Bookshelf Vue app
```

Claude adds `Route::capabilities('/capabilities')` to `web.php`, runs `php caligrafer.php client` and calls `createBook`
from the app.

**Check** before you say OK:
+ every call of the app goes through a function of `capabilities.js`: no `axios`, `fetch` or `Authorization` header
  written by hand for capabilities;
+ `capabilities.js` was generated again after the last capability change.

<br/>

## By hand

1. **The route.** In `application/config/routing/web.php`, before `Route::run();`:
    ```php
    Route::capabilities('/capabilities');   // GET lists, POST /capabilities/{name} calls: the JS client uses it
    ```
2. **Generate the client.**
    ```bash
    docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
    ```
    ```text
     public/js/capabilities.js written: 17 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)
     import { createBook, CapabilityError } from './capabilities.js'
    ```
    For a Vite SPA, write it in `public/<app>/src/capabilities.js`. With another prefix:
    `php caligrafer.php client --prefix /api/capabilities` for `Route::capabilities('/api/capabilities')`.
3. **Use it in a Vue app.**
    ```js
    import { createBook, listBooks, CapabilityError } from './capabilities.js'

    try {
      const book = await createBook({ title: this.title, published_year: this.year, author_id: this.authorId })
      this.books = (await listBooks({ author_id: book.author_id })).data
    } catch (e) {
      if (e instanceof CapabilityError && e.code === 'invalid') this.errors = e.fields   // { published_year: '...' }
      else throw e
    }
    ```
    A Vue app loaded as a library imports it from the page:
    `this.api = await import(env.home + 'public/js/capabilities.js')`, then
    `this.api.configure({ baseUrl: env.home.replace(/\/$/, '') })`.
4. **From Node**, with a personal access token:
    ```js
    import { configure, listBooks } from './capabilities.mjs'

    configure({ baseUrl: 'http://localhost:8080', token: process.env.CALIGRAFY_TOKEN })
    const page = await listBooks({ page: 1 })
    ```
5. Run `client` again after you add or change a capability.

<br/>

## <a name="api"></a>What the module exports

| Export | What it does |
| --- | --- |
| one function per capability, `createBook(input = {})` | calls it and returns its result (the `data` of the answer) |
| `call(name, input = {})` | calls any capability by name: `POST <prefix>/<name>` |
| `capabilities()` | what the caller may call, with the input schemas: `GET <prefix>` |
| `configure({ baseUrl, token, prefix, credentials })` | the application root (default: this origin), a personal access token, the prefix, the `fetch` credentials (default `same-origin`) |
| `CapabilityError` | thrown by a refused call: `status`, `code` (`invalid`, `forbidden`...), `message`, `fields` |

Each function carries a comment from the capability: its description, its flag (`read-only`, `destructive` or `writes`),
who may call it (`public`, the scope, or `signed in`) and its inputs, typed from the schema (`number`, `boolean` or
`string`; optional ones with `?`).

**In a page of the application** it uses the session: the cookie, and the CSRF token it reads once from `GET <prefix>`
and sends as `X-CSRF-Token` on every call that is not a `GET`. No key is ever in the bundle. **Elsewhere** (Node, another
origin) `configure({ token })` sends `Authorization: Bearer <token>` instead.

Left out of the module: [web-only capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Web-Only-Capabilities)
and Snap's own capabilities (`snap_*`).

Reference: [`Client::generate()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#client-generate),
[`Route::capabilities()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-capabilities).

<br/>

## Common mistakes

+ **Forgetting `php caligrafer.php client` after a change**: the Vue app calls the old functions.
+ **A write over the session without `X-CSRF-Token`** (G54): 403. The client sends it; hand-written calls must too.
+ **A token or a key in a page or a Vue bundle**: browser code uses the session; tokens are for programs.
+ **The legacy API key in a client page** (G27, G50): while `LEGACY_API_KEY` is on, client pages get the key. Call
  capabilities with the JS client and set `LEGACY_API_KEY=false`.

<br/>

## Related pages

+ [Vue: requests and capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.3-Requests#capabilities)
+ [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/REST-API) · [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/Personal-Access-Tokens)
+ [Caligrafer: `client`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#client)
