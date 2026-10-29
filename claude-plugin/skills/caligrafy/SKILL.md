---
name: caligrafy
description: Expert knowledge of the Caligrafy Quill PHP MVC framework (Caligrafy\Controller, Caligrafy\Model, Route, Request, Auth, Pug/Phug views, GUMP validation, caligrafer CLI, Docker dev server, Vue client apps, Stripe/Coinbase payments, OpenAI/Groq/Watson AI, open models (Llama) through Together, Fireworks, OpenRouter or Ollama, FaceDetect, ML). Use whenever code touches a Caligrafy Quill project - application/config/routing/web.php, application/controllers, application/models, application/views/*.pug, public/<app>/, .env, caligrafer.php or .bin/caligrafer.
when_to_use: The working directory contains caligrafer.php, framework/core/Controller.php or application/config/routing/web.php; the user mentions Caligrafy, Quill, caligrafer, Phug/pug views, Caligrafy Vue apps, FaceDetect or MlCore; or asks to add routes, controllers, models, REST APIs, auth, payments, AI features, or to debug [ERROR 1001/1004/2003/3000] messages.
---

# Caligrafy Quill

Caligrafy Quill is a PHP MVC framework (PHP >= 8.3, MySQL via PDO) with Pug templates (Phug), GUMP validation,
a REST API built into every route, and Vue 3 client apps. This skill is an index: read the reference file
for the topic before writing code, and check [gotchas.md](reference/gotchas.md) whenever behavior surprises you.
Signatures in [api-reference.md](reference/api-reference.md) are copied from the source and win over the wiki.

## Reference map

| Topic | Read |
| - | - |
| Exact class/helper signatures, lifecycle, CLI, Docker | [api-reference.md](reference/api-reference.md) |
| Verified pitfalls and wiki errata (G1-G49) | [gotchas.md](reference/gotchas.md) |
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
| This Claude Code plugin (commands, agent, install) | [10-claude-code-plugin.md](reference/10-claude-code-plugin.md) |
| Every change of October 2026: upgrade checklist for existing projects, what can break, `.env` settings | [11-release-notes-october-2026.md](reference/11-release-notes-october-2026.md) |
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
| Wiki table of contents | [wiki-sidebar.md](reference/wiki-sidebar.md) |

Online docs: https://github.com/caligrafy/caligrafy-quill/wiki

## Project layout (after `caligrafer initialize`)

```
application/                 # YOUR server code - every .php here is auto-required (global namespace, unique class names)
  config/config.php          # app-wide config (keywords(), copyright(), ...)
  config/routing/web.php     # ALL routes; ends with Route::run()
  controllers/               # *Controller.php, any sub-folders (core/Auth, core/AI ship by default)
  models/                    # Model classes + create_db.sql scripts (models/Auth/User.php, models/Auth/create_db.sql)
  views/                     # .pug templates (default/, Auth/, AI/, Client/app.pug)
public/                      # browser-facing: css, js, images, uploads, client apps public/<appName>/
framework/                   # core (rarely edited); framework/settings/application is the template for application/
.env                         # every key becomes a PHP constant
caligrafer.php, .bin/        # CLI
docker-compose.yaml, docker/ # dev server (8080), phpmyadmin (8077), mysql (3306)
```
`application/` and `.env` do not exist in a fresh clone until `php caligrafer.php initialize` (or `.bin/caligrafer`) runs.

## Conventions that matter

- Every table has primary key `id`. Foreign keys are `<lowercase model name>_id` (model name, not table name).
- One model class per entity, `use Caligrafy\Model;`, one `public $column;` per column, table bound explicitly via `associate()` - there is no implicit table naming.
- Controllers `use Caligrafy\Controller;`, must have `index()`; pick the model per action with `$this->associate('Book', 'books');`.
- Schema is written by hand in SQL (`application/models/<Entity>/create_db.sql`); the framework never migrates (except auto-pivots).
- Many-to-many pivots are created by `belongsToMany()` (typed like the referenced ids, unique constraint names - G10). Name your own FK constraints `fk_<table>_<column>`.
- Model writes save `0`, `''` and `false`; only `null` is skipped (G7). `all()` clauses are raw SQL: use `'bindings'` for values (G8).
- One route file: `application/config/routing/web.php`. Keep `Route::get('/', ...)` first (unmatched URLs fall back to route #1, G4) and `Route::run()` last.
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
// application/controllers/Book/BookController.php
use Caligrafy\Controller;
class BookController extends Controller {
    private function context() { $this->associate('Book', 'books'); }
    public function index()  { $this->context(); return view('Book/index', ['books' => $this->all(['order' => 'ORDER BY created_at DESC'])]); }
    public function read()   { $this->context(); $book = $this->find(); if (!$book) { http_response_code(404); return api(['error' => 'Not found']); } return api($book); }
    public function create() {
        $this->context();
        $data = $this->filter($this->request->parameters(), ['title' => 'trim|sanitize_string', 'author' => 'trim|sanitize_string']);
        $valid = $this->validate($data, ['title' => 'required|max_len,255', 'author' => 'required|max_len,255']);
        if ($valid !== true) { http_response_code(422); return api(['errors' => $valid]); }
        $book = new Book(); $book->title = $data['title']; $book->author = $data['author'];
        http_response_code(201); return api($this->save($book));
    }
    public function update() { $this->context(); $book = $this->find(); /* filter + validate as above, assign */ return api($this->save($book)); }
    public function remove() { $this->context(); $this->delete(); return api(['deleted' => true]); }
}
```
```php
// application/config/routing/web.php (inside the try, before Route::run())
Route::get('/books', 'BookController');
Route::get('/books/{id}', 'BookController@read');
Route::post('/books', 'BookController@create');
Route::put('/books/{id}', 'BookController@update');
Route::delete('/books/{id}', 'BookController@remove');
```
Use the `/caligrafy-quill:new-resource` skill to scaffold this including the SQL script.

### Calling the API
```bash
curl -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" http://localhost:8080/books
curl -X POST -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" -H "Content-Type: application/json" \
     -d '{"title":"Dune","author":"Herbert"}' http://localhost:8080/books
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
In `public/shop/scripts/main.js` send `{ headers: { Authorization: 'Bearer ' + apiKey, Accept: 'application/json' } }`; read server data from `env` (`env.home`, `env.token`, your own view vars). See G15, G16, G27.

### Authentication
Uncomment `Auth::authentication('User', 'users');` and the auth routes in web.php, create the `users` table from `application/models/Auth/create_db.sql` (safe to re-run in current templates; old copies start with `DROP TABLE` - G13), then use `authorized()`, `user()`, `guard('permissions', 1)`, `authorize($user)`, `logout('/')`. Passwords are `password_hash()`ed by `User::hashPassword()`; admin pages need `permissions >= 1` (promote in SQL). Read [09.1-authentication.md](reference/09.1-authentication.md) and G14.

### File uploads
```php
$valid = $this->check($_FILES, ['image' => 'required_file|extension,png;jpg']);
if ($valid === true) { $url = uploadFile($this->request->files('image'), ['png', 'jpg']); }   // '' when refused
```
`uploadFile()` refuses scripts/html/svg whatever the list; `public/uploads/.htaccess` blocks script execution (G34).

## Workflow rules for Claude

1. Before editing, confirm it is a Quill project and whether `application/` and `.env` exist (if not, `php caligrafer.php initialize` or `.bin/caligrafer` is needed - it deletes `.git`, so ask first when the folder is a git repo the user cares about).
2. Read the relevant reference file; prefer the source in `framework/core/` when in doubt.
3. New server code goes in `application/` (never `framework/` unless the user asks to change the framework). Client code goes in `public/`.
4. Use bound `Database::execute()` queries for anything built from user input (G2, G8). Filter then validate (G6).
5. After editing PHP, `php -l <file>`; after changing routes, hit them with curl (JSON + Bearer) or the browser. When you change `framework/` or `framework/settings/application`, run `./qa/run.sh` (G38) and add a test for the change.
6. In a project initialized with an older framework, run `php caligrafer.php upgrade` (dry run, safe) to see which `application/` copies are outdated or customized before assuming the current behavior; apply with `--apply` only when the user agrees (G39).
7. When something fails at runtime (AI calls returning errors or null, database, payments, mail), run `php caligrafer.php doctor` (read-only; in the container with Docker) before reading code: it checks `.env`, keys (masked), models allowed for the key, retired Groq models, the Agent provider (Ollama reachable, models pulled), the database and the services, with a fix per line.
8. Run SQL scripts with the `/caligrafy-quill:db` skill, start/stop Docker with `/caligrafy-quill:server`, scaffold client apps with `/caligrafy-quill:new-app`.
9. Never print or commit the values of `APP_KEY`, `API_KEY` or other secrets from `.env`; refer to them by name.
10. For a review of Caligrafy code, delegate to the `caligrafy-reviewer` agent.
