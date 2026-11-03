---
name: caligrafy
description: Expert knowledge of the Caligrafy Quill PHP MVC framework (Caligrafy\Controller, Caligrafy\Model, Route, Request, Auth, Pug/Phug views, GUMP validation, caligrafer CLI, Docker dev server, Vue client apps, Stripe/Coinbase payments, OpenAI/Groq/Watson AI, open models (Llama) through Together, Fireworks, OpenRouter or Ollama, FaceDetect, ML, and the build-once layer: services and capabilities served to web pages, REST, the JS client and AI agents over MCP, with personal access tokens and an audit log, and the page components every new UI is built with). Use whenever code touches a Caligrafy Quill project - application/config/routing/web.php, application/controllers, application/models, application/views/*.pug, public/<app>/, .env, caligrafer.php or .bin/caligrafer.
when_to_use: The working directory contains caligrafer.php, framework/core/Controller.php or application/config/routing/web.php; the user mentions Caligrafy, Quill, caligrafer, Phug/pug views, Caligrafy Vue apps, FaceDetect or MlCore; or asks to add routes, controllers, models, REST APIs, capabilities, MCP tools for agents, tokens, auth, payments, AI features, or to debug [ERROR 1001/1004/2003/3000] messages.
---

# Caligrafy Quill

Caligrafy Quill is "Describe it. Approve it. Ship it.": an idea turned into a complete app, built once and experienced everywhere. It is a PHP MVC framework (PHP >= 8.3, MySQL via PDO) with Pug templates (Phug), GUMP validation,
a REST API built into every route, and Vue 3 client apps. It covers the whole way from zero to one in one project:
start (`quickstart`), data, capabilities written once, every experience (pages, Vue and phone apps, REST, MCP agents),
people and trust, ship (tests, `doctor`, review), online (`Dockerfile`, `caligrafer cloud deploy`). When a user
describes an idea, take it along these steps (the `idea` skill does all of them); when a feature works locally, the next step is the `deploy` skill. This skill is an index: read the reference file
for the topic before writing code, and check [gotchas.md](reference/gotchas.md) whenever behavior surprises you.
Signatures in [api-reference.md](reference/api-reference.md) are copied from the source and win over the wiki.

## Reference map

| Topic | Read |
| - | - |
| **Start here**: set up a project in one command (`.bin/caligrafer quickstart`), the ideas, a complete first feature | [15-quickstart.md](reference/15-quickstart.md), [16-how-caligrafy-works.md](reference/16-how-caligrafy-works.md), [17-your-first-capability.md](reference/17-your-first-capability.md) |
| Learning Caligrafy from zero (a user asks for a tutorial or course): the Caligrafy course builds Bookshelf capabilities first in 15 lessons, up to putting it online, each with a manual track and a With Claude track (point the user to it; its lessons match these docs) | https://github.com/caligrafy/caligrafy-quill-course/wiki |
| Every Caligrafer command for a user (what it does, options, examples): quickstart, initialize, server, service, client, apps, sql, token, mcp, audit, doctor, upgrade, deploy, cloud | [18-caligrafer.md](reference/18-caligrafer.md) (exact behavior: the CLI table of api-reference.md) |
| Exact class/helper signatures, lifecycle, CLI, Docker | [api-reference.md](reference/api-reference.md) |
| Verified pitfalls and wiki errata (G1-G107) | [gotchas.md](reference/gotchas.md) |
| Overview / README | [00-home-overview.md](reference/00-home-overview.md) |
| Install, MVC concepts, naming conventions, file structure, caligrafer | [01-getting-started.md](reference/01-getting-started.md) |
| Routes, params, controller routes, method spoofing | [02-routing.md](reference/02-routing.md) |
| Request object | [03-request.md](reference/03-request.md) |
| Database activation, SQL schemas, models, CRUD | [04-models.md](reference/04-models.md) |
| hasOne / hasMany / belongsToMany, pivots | [05-relationships.md](reference/05-relationships.md) |
| GUMP validators and filters, check/validate/filter | [06-validation.md](reference/06-validation.md) |
| Pug views | [07-views.md](reference/07-views.md) |
| Controllers, associate(), built-in CRUD | [08-controllers.md](reference/08-controllers.md) |
| Helpers, HTML forms, CSRF, REST API + Postman | [09-helpers-forms-rest-api.md](reference/09-helpers-forms-rest-api.md) |
| Authentication module | [09.1-authentication.md](reference/09.1-authentication.md) |
| Production checklist, server-file protection, upgrading old projects, behavior changes | [09.5-security-and-upgrading.md](reference/09.5-security-and-upgrading.md) |
| Build once: services, `#[Capability]`, REST (`Route::capability`), the JS client, personal access tokens, MCP for agents, audit log, design tokens | [13-build-once-services-capabilities-mcp.md](reference/13-build-once-services-capabilities-mcp.md), [api-reference.md](reference/api-reference.md) (Build once section), G50-G58 |
| This Claude Code plugin (commands, agent, install) | [10-claude-code-plugin.md](reference/10-claude-code-plugin.md) |
| Upgrading a project to 2.1 (build once): checklist, what is new, behavior changes, the legacy API key | [14-release-notes-2.1.0.md](reference/14-release-notes-2.1.0.md) |
| **A user describes an application to build** (an idea, "build me an app that..."): describe it, approve it, ship it with the `idea` skill (the Quickstart with Claude) | [22-quickstart-with-claude.md](reference/22-quickstart-with-claude.md), the `idea` skill |
| **Putting a project online**: the production `Dockerfile`, `caligrafer deploy` (database at every start), Caligrafy Cloud (`caligrafer cloud`, https://<name>.caligrafy.com, trial and credits), own server (Coolify, Docker); use the `deploy` skill | [20-putting-it-online.md](reference/20-putting-it-online.md), G67-G73 |
| What 2.4.0 changed: production image, `deploy`, `cloud`, sessions volume, the sign-in look of the page components, upgrading from 2.3.0 | [21-release-notes-2.4.0.md](reference/21-release-notes-2.4.0.md) |
| What 2.3.0 changed: `caligrafer call`, the upgrade reports and `--keep`, doctor warnings, fixed templates, one time zone, upgrading from 2.2.0 | [19-release-notes-2.3.0.md](reference/19-release-notes-2.3.0.md) |
| Every change of October 2026: upgrade checklist for existing projects, what can break, `.env` settings | [11-release-notes-october-2026.md](reference/11-release-notes-october-2026.md) |
| How the framework itself is built: the request lifecycle, `Capabilities::call`, the `Context` (actors, scopes, levels), each experience's adapter, the design decisions | `ARCHITECTURE.md` at the root of the framework (and of every project) |
| Changing the framework itself: QA suite phases, PHPUnit tests in `qa/unit`, mocks, adding a test, release process | [12-testing-caligrafy.md](reference/12-testing-caligrafy.md) |
| Stripe and Coinbase payments | [09.2-stripe-crypto-payment.md](reference/09.2-stripe-crypto-payment.md) |
| Metadata, rich cards, SEO, analytics | [09.3-metadata-rich-cards.md](reference/09.3-metadata-rich-cards.md), [09.4-search-referencing-analytics.md](reference/09.4-search-referencing-analytics.md) |
| Vue as a library (`caligrafer app`): setup, flow, structure, routes, requests, forms, validation, components | [09.7.0](reference/09.7.0-vue-as-library-setup.md), [09.7](reference/09.7-vue-flow-of-information.md), [09.7.1](reference/09.7.1-vue-structure.md), [09.7.2](reference/09.7.2-vue-routes.md), [09.7.3](reference/09.7.3-vue-requests.md), [09.7.4](reference/09.7.4-vue-forms.md), [09.7.5](reference/09.7.5-vue-validations.md), [09.7.6](reference/09.7.6-vue-components.md) |
| Vue SPA (`caligrafer create`): Vite 8 setup, Vue Router 5, SFC, Pinia | [09.8.1](reference/09.8.1-vue-spa-setup.md), [09.8.2](reference/09.8.2-vue-spa-routing.md), [09.8.3](reference/09.8.3-vue-single-file-components.md), [09.8.4](reference/09.8.4-vue-state-management.md) |
| Watson bots | [09.9.1-ai-watson-bots.md](reference/09.9.1-ai-watson-bots.md) |
| FaceDetect (@vladmandic/face-api) | [09.9.2-ai-face-detection.md](reference/09.9.2-ai-face-detection.md) |
| ML Core (ml5 + p5) | [09.9.3-ai-machine-learning.md](reference/09.9.3-ai-machine-learning.md) |
| OpenAI chat/images, Assistants, open source agents (Groq, Together, Fireworks, OpenRouter, Ollama) | [09.9.4](reference/09.9.4-ai-openai-chatgpt-dalle.md), [09.9.5](reference/09.9.5-ai-openai-assistants.md), [09.9.6](reference/09.9.6-ai-open-source-agents-groq.md) |
| Recipe: OpenAI/open model chat app (history, live model list, images, vision, Llama on OpenRouter/Ollama, Vue chat UI) | [recipes-ai-apps.md](reference/recipes-ai-apps.md) |
| Recipe: face login (liveness challenges, single-use nonce, anti-replay, encrypted prints, lockout, passkeys / WebAuthn step-up) | [recipes-face-login.md](reference/recipes-face-login.md) |
| What 2.6.0 changed: the quickstart from Composer on MAMP, XAMPP, LAMP or Docker (`composer create-project`, `created`, `.bin/caligrafer quickstart --local/--docker`, local addresses and MySQL settings) | [24-release-notes-2.6.0.md](reference/24-release-notes-2.6.0.md), G86 |
| What 2.7.0 changed: pages call their routes with the session (`apiHeaders()`, `X-CSRF-Token`), `cloud login` approved in the browser, `cloud key`, 30 free minutes per new app, the `ship` skill | [25-release-notes-2.7.0.md](reference/25-release-notes-2.7.0.md), G87-G90 |
| What 2.8.0 changed: design styles (`framework/design/`, `DESIGN_STYLE`, `php caligrafer.php design`, the `design` skill) | [27-release-notes-2.8.0.md](reference/27-release-notes-2.8.0.md), G92-G93 |
| What 2.9.0 changed: `php caligrafer.php update` (one command to the latest release, keeping what the project changed), the `update` skill | [28-release-notes-2.9.0.md](reference/28-release-notes-2.9.0.md), G94 |
| What 2.10.0 changed: `php caligrafer.php upgrade` checks for a newer release first and applies it like `update` (`--local`: templates only) | [29-release-notes-2.10.0.md](reference/29-release-notes-2.10.0.md), G96 |
| What 2.11.0 changed: Caligrafy's new look on every out-of-the-box page (`site.pug`, `layout.css`, `look.css`, `camera.css`, the Caligrafy mark), full-screen camera apps, the SPA template, Stripe Checkout on new accounts, the Payment Element in the page's colors | [30-release-notes-2.11.0.md](reference/30-release-notes-2.11.0.md), G73, G98-G101 |
| What 2.12.0 changed: a list and its detail side by side (`.cq-split > section.cq-split-main + aside.cq-split-detail`), used by the course's finder and SPA | [31-release-notes-2.12.0.md](reference/31-release-notes-2.12.0.md), [ui-guidelines.md](reference/ui-guidelines.md#a-list-and-its-detail-side-by-side-2120) |
| What 2.13.0 changed: selling an app on the marketplace of caligrafy.com: the `marketplace` skill (reads the code, walks the app for its screenshots, writes `.caligrafy/marketplace.json`), `php caligrafer.php cloud submit <name>` | [32-release-notes-2.13.0.md](reference/32-release-notes-2.13.0.md), [20-putting-it-online.md](reference/20-putting-it-online.md#marketplace), G102 |
| What 2.13.1 fixed: `update` and `plugin` refresh Claude Code's copy of the plugin (a new skill was missing after an update), `doctor` warns while it is older | [33-release-notes-2.13.1.md](reference/33-release-notes-2.13.1.md), G103 |
| What 2.13.2 changed: Describe it. Approve it. Ship it. in the plugin, `CLAUDE.md` and `AGENTS.md`; the `idea` skill stops for a review before shipping when asked, field messages under their fields | [34-release-notes-2.13.2.md](reference/34-release-notes-2.13.2.md) |
| What 2.13.3 fixed: a form with no method leaks its fields into the address when its script does not run; `doctor` warns | [35-release-notes-2.13.3.md](reference/35-release-notes-2.13.3.md), G104 |
| What 2.13.4 changed: Caligrafy accounts sign in with a confirmed email, *Forgot your password?* sends a 6-digit code; the `ship` skill guides the user | [36-release-notes-2.13.4.md](reference/36-release-notes-2.13.4.md), the `ship` skill |
| **Pages and the rule "only capabilities touch models"** (2.5.0): `Route::page` (options, what the view gets, refusals), when to still write a controller, the user capabilities | [02-routing.md](reference/02-routing.md#pageroutes), [08-controllers.md](reference/08-controllers.md), [09.1-authentication.md](reference/09.1-authentication.md), [23-release-notes-2.5.0.md](reference/23-release-notes-2.5.0.md), G80-G85 |
| **Another look for every page**: a design style (`DESIGN_STYLE`, `php caligrafer.php design`, a `DESIGN.md` of `framework/design/` (vendored VoltAgent/awesome-design-md, local styles) or `application/design/`), one override stylesheet `public/css/designs/<name>.css`; use the `design` skill | [26-design-styles.md](reference/26-design-styles.md), [ui-guidelines.md](reference/ui-guidelines.md#design-styles), G92-G93 |
| **Any new page or client app UI**: Caligrafy's look (2.11.0+): `extends ../default/master/site`, the classes of `layout.css` (`.cq-hero`, `.cq-band`, `.cq-auth`, `.cq-form-grid`, `.cq-rows`, `.cq-split`, `.cq-tiles`, `.cq-account`...), `look.css`, full-screen camera pages (`camera.css`), a worked example, the checklist | [ui-guidelines.md](reference/ui-guidelines.md), G98-G101 |
| Wiki table of contents | [wiki-sidebar.md](reference/wiki-sidebar.md) |

Online docs: https://github.com/caligrafy/caligrafy-quill/wiki

## Project layout (after `caligrafer initialize`)

```
application/                 # YOUR server code - every .php here is auto-required (global namespace, unique class names)
  config/config.php          # app-wide config (keywords(), copyright(), ...)
  config/routing/web.php     # ALL routes; ends with Route::run()
  controllers/               # *Controller.php, any sub-folders (core/Auth, core/AI ship by default)
  models/                    # Model classes + create_db.sql scripts (models/Auth/User.php, models/Auth/create_db.sql)
  services/                  # capabilities: Service classes with #[Capability] (php caligrafer.php service <Folder/Name>); Users/ ships the user capabilities (2.5.0)
  views/                     # .pug templates (default/, Auth/, AI/, Client/app.pug)
public/                      # browser-facing: css, js, images, uploads, client apps public/<appName>/
framework/                   # rarely edited: core/ (framework + Service, Capability, Context...), interfaces/ (Rest, Mcp/, Client), database/; framework/settings/application is the template for application/
.env                         # every key becomes a PHP constant
caligrafer.php, .bin/        # CLI
docker-compose.yaml, docker/ # dev server (8080), phpmyadmin (8077), mysql (3306)
```
`application/` and `.env` do not exist in a fresh clone until `php caligrafer.php initialize` (or `.bin/caligrafer`) runs.

## Conventions that matter

- Every table has primary key `id`. Foreign keys are `<lowercase model name>_id` (model name, not table name).
- One model class per entity, `use Caligrafy\Model;`, one `public $column;` per column, table bound explicitly via `associate()` - there is no implicit table naming.
- **Only capabilities touch models** (2.5.0): pages get their data with `Route::page(..., ['data' => [...]])`, controllers call `$this->call('<capability>')`. Controllers (`use Caligrafy\Controller;`, must have `index()`) are for form posts, several capabilities with conditions, and the session (sign-in); `associate()`/`save()`/`all()` in a controller is legacy MVC.
- Schema is written by hand in SQL (`application/models/<Entity>/create_db.sql`); the framework never migrates (except auto-pivots).
- Many-to-many pivots are created by `belongsToMany()` (typed like the referenced ids, unique constraint names - G10). Name your own FK constraints `fk_<table>_<column>`.
- Model writes save `0`, `''` and `false`; only `null` is skipped (G7). `all()` clauses are raw SQL: use `'bindings'` for values (G8).
- Pages: `Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book'], 'signedIn' => true])` gives the view `authorized`, `user`, the route variables and the data (G74, G83, G84); prefer it to closures returning `view()`.
- One route file: `application/config/routing/web.php`. Keep the `/` route first (unmatched URLs fall back to route #1, G4) and `Route::run()` last.
- The same route serves HTML and JSON: `view('tpl', $data)` returns JSON automatically for API calls; `api($data)` always returns JSON.

## Canonical recipes

### CRUD resource (model + controller + routes)
```php
// application/models/Book/Book.php
use Caligrafy\Model;
use Caligrafy\Database;
class Book extends Model {
    public $id; public $title; public $author; public $publisher_id; public $created_at;
    public function publisher() { return $this->hasOne('Publisher', 'publishers'); }   // books.publisher_id -> publishers.id
    public function findByTitle($title) {                                               // exact match with a bound query (search() is LIKE)
        $rows = Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE title = ?', [$title]));
        return array_map(fn($r) => $this->arrayToModel($r), $rows);
    }
}
```
```php
// application/services/Books/CreateBook.php  (php caligrafer.php service Books/CreateBook --scope books:write)
use Caligrafy\Capability; use Caligrafy\Context; use Caligrafy\Service; use Caligrafy\ServiceError;
#[Capability(name: 'create_book', description: 'Adds a book to the catalog and returns it with its id.', scope: 'books:write')]
class CreateBook extends Service {
    public $rules = ['title' => 'required|max_len,255', 'author' => 'required|max_len,255'];
    public $filters = ['title' => 'trim', 'author' => 'trim'];
    public function handle(array $input, Context $context) { return (new Book('books'))->create($input); }
}
// list_books, get_book (ServiceError::notFound), update_book, delete_book (destructive: true) the same way
```
```php
// application/config/routing/web.php (inside the try, before Route::run())
Route::capability('GET', '/api/books', 'list_books');          // REST
Route::capability('GET', '/api/books/{id}', 'get_book');
Route::capability('POST', '/api/books', 'create_book');
Route::capability('PUT', '/api/books/{id}', 'update_book');
Route::capability('DELETE', '/api/books/{id}', 'delete_book');
Route::page('/books', 'Books/index', ['data' => ['books' => 'list_books']]);      // pages: data from capabilities
Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);
Route::capabilities('/capabilities'); Route::mcp('/mcp');                          // JS client and AI agents
```
**Only capabilities touch models**: no controller reads or writes `Book`. A thin controller only for a classic form
post, calling the capability (`$this->call('create_book')`, then redirect or re-render with `$e->fields`).
Use the `/caligrafy-quill:new-resource` skill to scaffold this including the SQL script and the capabilities.

### Calling the API
```bash
TOKEN=$(php caligrafer.php token create "my script" --scopes books:read,books:write | grep -o 'cq_[^ ]*')
curl -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" http://localhost:8080/api/books
curl -X POST -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"title":"Dune","author":"Herbert"}' http://localhost:8080/api/books
```
Browser forms instead need `=csrf()` (and `=methodField('PUT')` for PUT/DELETE) - see G1.

### Pug view with data
```pug
//- application/views/Book/index.pug  -> view('Book/index', ['books' => $books])
extends ../default/master/layoutblocks

block content
    each $book in $books
        p #{$book->title} by #{$book->author}
    form(method='POST', action="{$home}books")
        =csrf()
        input(type='text', name='title')
        input(type='text', name='author')
        input(type='submit', value='Add')
```
Pug variables are PHP variables (`$books`, `$book->title`); `$home` is set by `master/layout.pug`. `layoutblocks` defines the blocks
`headers`, `navigation_default`, `navigation_authorized`, `content`, `footer`, `scripts` (`additional_preload_scripts`, `additional_scripts`).
Pass `'authorized' => authorized()` to show the logged-in navigation, `'install' => true` to invite visitors to install the app
(Install button / iOS Share hint; the layout always links `public/manifest.webmanifest`; offline page with `PWA_SERVICE_WORKER=true`).

### Vue client app with a page route and data routes
```bash
php caligrafer.php app shop          # or bot | facedetect | ml ; 'create' for a Vite SPA
```
```php
Route::get('/shop', function() { return view('Client/app', ['appName' => 'shop', 'app' => 'index']); }); // path ends with the folder name
Route::get('/shop/products', 'ProductController@all');   // data route used by axios
```
In `public/shop/scripts/main.js` send `{ headers: apiHeaders() }` (the session and its `X-CSRF-Token`, 2.7.0+; never the API key, which is off online, G87); read server data from `env` (`env.home`, `env.csrf`, your own view vars). See G15, G16, G27.

### Authentication
Uncomment `Auth::authentication('User', 'users');` and the auth routes in web.php, create the `users` table from `application/models/Auth/create_db.sql` (safe to re-run in current templates; old copies start with `DROP TABLE` - G13), then use `authorized()`, `user()`, `guard('permissions', 1)`, `authorize($user)`, `logout('/')`. Passwords are `password_hash()`ed by `User::hashPassword()`; admin pages need `permissions >= 1` (promote in SQL). Read [09.1-authentication.md](reference/09.1-authentication.md) and G14.

### File uploads
```php
$valid = $this->check($_FILES, ['image' => 'required_file|extension,png;jpg']);
if ($valid === true) { $url = uploadFile($this->request->files('image'), ['png', 'jpg']); }   // '' when refused
```
`uploadFile()` refuses scripts/html/svg whatever the list; `public/uploads/.htaccess` blocks script execution (G34).

## Workflow rules for Claude

1. Before editing, confirm it is a Quill project and whether `application/` and `.env` exist (a project that was never set up: offer `.bin/caligrafer quickstart`, which asks before deleting `.git`) (if not, `php caligrafer.php initialize` or `.bin/caligrafer` is needed - it deletes `.git`, so ask first when the folder is a git repo the user cares about).
2. Read the relevant reference file; prefer the source in `framework/core/` when in doubt.
3. New server code goes in `application/` (never `framework/` unless the user asks to change the framework). Client code goes in `public/`.
4. Use bound `Database::execute()` queries for anything built from user input (G2, G8). Filter then validate (G6).
5. After editing PHP, `php -l <file>`; after changing routes, hit them with curl (JSON + Bearer) or the browser. When you change `framework/` or `framework/settings/application`, run `./qa/run.sh` (G38) and add a test for the change.
6. In a project initialized with an older framework, run `php caligrafer.php upgrade` (dry run, safe) to see which `application/` copies are outdated or customized before assuming the current behavior; read its settings and Bootstrap 3 reports, propose `--keep` for any default page that is really the site's own (an old home page), and apply with `--apply` only when the user agrees (G39). To try a capability you wrote, use `php caligrafer.php call <name> key=value` (2.3.0+).
7. A feature that more than one channel needs (pages and a Vue app, an API, an AI agent) is a capability: a `Service` in `application/services/` with `#[Capability]`, called by `$this->call()`, `Route::capability()`, the JS client and MCP (`/caligrafy-quill:new-capability`). Never put an API key or a token in a page or a bundle (G50): browser clients use the session through the generated client.
8. Every page or client app UI you create follows [ui-guidelines.md](reference/ui-guidelines.md): `extends ../default/master/site` with the classes of `layout.css` (title band, bands, `.cq-field`, `.cq-rows`, `.cq-tiles`), labeled fields, black primary button, no cards, no Bootstrap colors, no page `style` block. Open it in a browser at desktop and phone width before calling it done.
9. To know which framework version a project runs, use `php caligrafer.php version` (or `Caligrafy\Framework::VERSION`; releases are git tags `v<version>`, changes in `CHANGELOG.md`). Releases start at v2.0.0; a project with no `version` command predates it.
10. When something fails at runtime (AI calls returning errors or null, database, payments, mail), run `php caligrafer.php doctor` (read-only; in the container with Docker) before reading code: it checks `.env`, keys (masked), models allowed for the key, retired Groq models, the Agent provider (Ollama reachable, models pulled), the database and the services, with a fix per line.
11. Run SQL scripts with the `/caligrafy-quill:db` skill, start/stop Docker with `/caligrafy-quill:server`, scaffold client apps with `/caligrafy-quill:new-app`.
12. Never print or commit the values of `APP_KEY`, `API_KEY` or other secrets from `.env`; refer to them by name.
13. For a review of Caligrafy code, delegate to the `caligrafy-reviewer` agent.
