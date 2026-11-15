The **build commands** of [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) make features:
`service` writes a capability, `capabilities` lists them, `call` runs one from the terminal, `client` writes their
JavaScript client, and `app`, `create`, `facedetect`, `ml` and `bot` create client apps in `public/`.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write
/caligrafy-quill:new-app facedetect faces
```

`new-capability` runs `service`, fills the class, serves it, runs `client` and checks it with `call`;
`new-app` runs the app command and writes its routes.

**Check** before you say OK: `php caligrafer.php capabilities` lists the new capability with the scope you asked for,
and `php caligrafer.php call <name> ...` answers `{"data": ...}`.

<br/>

## By hand

```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/CreateBook --scope books:write
docker compose exec dev-box php caligrafer.php capabilities
docker compose exec dev-box php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2
docker compose exec dev-box php caligrafer.php client
```

(without Docker, drop `docker compose exec dev-box`). [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability)
walks through the whole feature.

<br/>

## <a name="service"></a>`service`

```text
 Usage: php caligrafer.php service <Name> [--scope <resource:action>] [--read-only]
 Name in PascalCase, optionally in folders: Books/CreateBook
```

```bash
php caligrafer.php service Bookshelf/CreateBook --scope books:write
php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only
```

```text
 Created application/services/Bookshelf/CreateBook.php: capability 'create_book' (scope books:write)
 Fill in $rules and handle(), then serve it: a page (Route::page data), a form (Route::form), REST (Route::capability or Route::capabilities), MCP (Route::mcp)
```

+ **The name** must be PascalCase, optionally in folders (`Books/CreateBook`). The capability is the snake_case of the
  class: `CreateBook` gives `create_book`.
+ **The file** is `application/services/<Name>.php`, written from `framework/settings/service.php.template`: a class
  that extends `Caligrafy\Service`, a `#[Capability(...)]` attribute with `name`, a placeholder `description`, `scope` and
  `readOnly`, empty `$rules`, `$filters` and `$descriptions`, and a `handle(array $input, Context $context)` that
  returns `array()`.
+ **`--scope`** fills `scope:`. Without it the capability needs no scope: anyone signed in, and any token, may call it.
+ **`--read-only`** sets `readOnly: true` (a page may read it; agents see it as safe).
+ It refuses an existing file (` <file> already exists`) and a project without `application/`
  (` application/ not found: run php caligrafer.php initialize first`), both with exit code `1`.
+ The other options of the attribute (`public`, `level`, `destructive`, `async`, `webOnly`) are added by hand.

What each part of the class means is in the API reference:
[`Caligrafy\Service`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#service) and
[`Caligrafy\Capability`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capability).

<br/>

## <a name="capabilities"></a>`capabilities`

```text
capabilities [--json | --markdown]: the capabilities of application/services/ (what REST, MCP and the JS client can call)
```

```bash
php caligrafer.php capabilities              # one line each
php caligrafer.php capabilities --markdown   # a table for your documentation
php caligrafer.php capabilities --json       # with the input schemas
```

```text
 create_book                  books:write    writes    Adds a book to the Bookshelf catalog and returns it with its id and its author.
 get_book                     public         read      Returns one book of the catalog with its author (id, name, bio), by the id of the book.
```

| Column | What it says |
| --- | --- |
| 1 | the capability's name |
| 2 | who may call it: `public`, its scope, or `signed in` when it has no scope |
| 3 | `read` (`readOnly`), `destroys` (`destructive`) or `writes` |
| 4 | its description |

+ `--markdown` prints `| Capability | What it does | Scope | Inputs |`, with `(read-only)` or `(destructive)` after the
  name and `(required)` after each required input.
+ `--json` prints, for each one, `name`, `description`, `scope`, `level`, `readOnly`, `destructive`, `public` and
  `input`, its JSON Schema
  ([`Capabilities::describe()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-describe)).
+ The framework's own capabilities (users, notifications, `queue_work`...) are listed too. Web-only capabilities are
  not: only `Route::action` reaches them.
+ With none: ` No capabilities yet: php caligrafer.php service <Name> creates one in application/services/`.

<br/>

## <a name="call"></a>`call`

```text
 Usage: php caligrafer.php call <capability> ['{"key": "value"}' | key=value ...] [--token <token>] [--guest]
 Lists the capabilities: php caligrafer.php capabilities
```

```bash
php caligrafer.php call list_books
php caligrafer.php call get_book id=3
php caligrafer.php call create_book '{"title": "Dawn", "published_year": 1987, "author_id": 2}'
php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2 --guest          # a visitor who is not signed in
php caligrafer.php call create_book title=Dawn published_year=1987 author_id=2 --token cq_...   # as a token
```

+ **Inputs**: one JSON object, `key=value` pairs, or both (merged in order). Anything else stops with
  `caligrafer call: '<arg>' is neither JSON nor key=value`. A `key=value` value is a string; the capability's rules
  check it as they check a form's values.
+ **Who calls**: without `--token` or `--guest`, the command line, with full access, like any script you run
  (`cli` in the audit log). `--guest` is a visitor: only public capabilities. `--token <token>`, or the
  `CALIGRAFY_TOKEN` environment variable, calls as that token, with its scopes; it needs the database, and a token that
  is unknown, revoked or expired stops with `caligrafer call: The token is unknown, revoked or expired`.
+ **The answer** is the JSON of REST, printed with indentation: `{"data": ...}` and exit code `0`, or
  `{"error": {"code": "invalid", "message": "...", "fields": {...}}}` and exit code `1`.
+ It never loads `application/config/config.php` (G141): a validator added there with `GUMP::add_validator()` does not
  exist on the command line.

<br/>

## <a name="client"></a>`client`

```text
client [<file>] [--prefix /capabilities]: writes the JS client of the capabilities (default public/js/capabilities.js)
```

```bash
php caligrafer.php client                                   # public/js/capabilities.js
php caligrafer.php client public/myspa/src/capabilities.js  # a single page application
php caligrafer.php client --prefix /api/capabilities        # Route::capabilities('/api/capabilities')
```

```text
 public/js/capabilities.js written: 21 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)
 import { createBook, CapabilityError } from './capabilities.js'
```

+ One ES module with no dependency: one `export const` per capability (`create_book` becomes `createBook`), each with a
  doc comment made of the capability's description, its access and its inputs; plus `call(name, input)`,
  `capabilities()`, `configure({ baseUrl, token, prefix, credentials })` and `CapabilityError` (`status`, `code`,
  `fields`).
+ Web-only capabilities and Snap's own (`snap_*`) are left out.
+ `--prefix` must match the `Route::capabilities(...)` of `web.php`.
+ Run it again after every change to a capability.
+ ` Could not write <file>` and exit `1` when the folder cannot be written.

Learn more in [the JS client](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#client)
and [`Caligrafy\Client`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces#client).

<br/>

## <a name="apps"></a>`app`, `create`, `facedetect`, `ml`, `bot`

```text
create <project_name>: scaffolds a Vue project
app <project_name>: scaffolds a regular Client HTML/JS project
bot <bot_name>: creates a bot application
facedetect <app_name>: creates a face detection application
ml <app_name>: creates an ml powered application
```

| Command | Copies | Into | What it is |
| --- | --- | --- | --- |
| `app catalog` | `framework/plugins/app` | `public/catalog` | Vue 3 as a library |
| `create myspa` | `framework/librairies/app` | `public/myspa` | a Vite Vue single page application; `npm install` when `npm` is there |
| `facedetect faces` | `framework/plugins/facedetect` | `public/faces` | face detection and recognition |
| `ml lab` | `framework/plugins/ml` | `public/lab` | machine learning in the browser (ml5, p5) |
| `bot helper` | `framework/plugins/bot` | `public/helper` | a Watson chatbot |

```text
 Project created in the public folder.
 Type cd public/catalog to access it anytime
```

+ **The page route** of the app is not written by the command: add it to `web.php` (the
  [Vue pages](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library) show it), or let
  `/caligrafy-quill:new-app` do it.
+ **Names that are refused**: `app`, `bot`, `__bots__`, `css`, `facedetect`, `fonts`, `images`, `js`, `ml`, `resources`,
  `uploads` (folders of `public/` the framework uses). A folder that exists already prints
  ` public/<name> already exists: nothing was created.` and exits with `1`.
+ **`create` and Node.js.** It runs `npm install` only where `npm` exists. The Docker `dev-box` has no Node.js, so there it
  prints ` VueJS project created, but its packages are NOT installed: npm is not available here (the Docker dev-box has no Node.js).`
  (G62): run `npm install`, then `npm run dev`, in `public/<name>` on your computer, with Node.js 20.19+ or 22.12+.
+ **A Vite app online** is served from its committed `dist/`: run `npm run build` and commit it, because the production
  image has no `npm`.

Learn more: [Vue as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library),
[the single page application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application),
[face detection](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition),
[machine learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning),
[chatbots](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson).

<br/>

## Common mistakes

+ **A capability with no `--scope`**: any token can call it. Give every capability that changes data a scope.
+ **The client is out of date**: a capability added or changed after `client` ran is missing from
  `capabilities.js`, or called with old inputs. Run `client` again.
+ **A custom validator works on pages but not with `call`** (G141): `call`, the scheduler and the queue never load
  `config.php`. Register it where the command line loads it too, or use a built-in rule.
+ **`call ... | jq` hides a refusal** (G89): the pipe reports `jq`'s exit code. Use `set -o pipefail`, or read the
  `error` key.

<br/>

## Related pages

+ [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability)
+ [Capabilities in depth](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
+ [API reference: services and capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
