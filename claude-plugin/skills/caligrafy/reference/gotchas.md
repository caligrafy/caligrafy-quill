# Caligrafy Quill gotchas and wiki errata (verified against the source)

Each item names the file that proves it. "Source" = caligrafy-quill master. Items marked *(MySQL rule)* follow
from the SQL the framework generates plus documented MySQL behavior. Items marked **Fixed** describe behavior
corrected by the security release (framework + `framework/settings/application` templates, covered by `qa/run.sh`):
a project initialized before it keeps the old `application/` copies until it is upgraded (G39).

## Requests, API and security

**G1. JSON calls from a page need `Accept: application/json` + `X-CSRF-Token` (2.7.0+); programs a Bearer token; form posts `_token`.**
`Request::wantsJson()` is true only when `Accept` contains `application/json` and NOT `text/html`.
- When `wantsJson()` is true, `Route::execute()` accepts a controller route call from a page of the application (the
  session cookie and `X-CSRF-Token: <session token>`, 2.7.0+: `apiHeaders()`, G87), or, with `Auth::activateAPI()` in
  web.php, `Authorization: Bearer` with a personal access token granting `*` or the legacy API key; else `[ERROR 1004]
  Access Denied: <why>`. Before 2.7.0 only the Bearer was accepted. The form CSRF check is skipped.
- When it is false, every POST/PUT/DELETE must carry `_token` (= `session('token')`) in `$_POST`, else `[ERROR 3000]`. Use `=csrf()` in pug forms, `formData.append('_token', env.token)` in JS.
- `axios` sends `Accept: application/json, text/plain, */*` by default (counts as JSON); `fetch()` sends `*/*` (does NOT). Always set `Accept: application/json` explicitly for API calls.
- The token is read from `$_POST` only. A JSON body (`Content-Type: application/json`) without the JSON Accept header always fails CSRF.
- `APPROVED_AGENTS` (comma separated User-Agents) skip CSRF; empty entries are ignored (**Fixed**: an empty setting used to approve requests without a User-Agent).
- Proof: `framework/core/Request.php` (`wantsJson`, `checkCSRF`, `authorizeApiRequest`), `framework/core/Route.php` (`execute`).

**G2. SQL built from input.** **Fixed:** `Model::search()` binds the keywords and validates column names (letters, digits, `_`); `update()`, `hasOne()`, `hasMany()`, `belongsToMany()` bind the ids; the auth module uses the bound `User::findByUsername()`. Still: `Database::execute()` with concatenated values and `all()` clauses (G8) are injectable - always use `?` placeholders:
```php
public function findByUsername($username) {
    $rows = Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE username = ? LIMIT 1', [$username]));
    return !empty($rows[0]) ? $this->arrayToModel($rows[0]) : null;
}
// LIKE search: $this->search('title', '%' . $term . '%') or Database::execute("SELECT * FROM {$this->table} WHERE title LIKE ?", ['%' . $term . '%'])
```
Proof: `framework/core/Model.php`, `framework/settings/application/models/Auth/User.php`.

**G3. `DB_PORT`.** **Fixed:** `Database::dsn()` adds `;port=DB_PORT` unless `DB_HOST` already contains `port=` (the old workaround `DB_HOST="127.0.0.1;port=3307"` keeps working). Inside Docker use `DB_HOST=mysql`, `DB_PORT=3306`. With `DB_HOST=localhost`, PDO MySQL uses the Unix socket and ignores the port: use `127.0.0.1` to force TCP. Proof: `framework/database/Database.php`.

**G4. Unknown URLs (fixed in 2.16.0).** Before 2.16.0 `Route::evaluate()` fell back to the first route declared when nothing matched (an unknown URL or a wrong HTTP method answered 200 with that route, usually the home page). Since 2.16.0: no match answers `Errors::raise(1005)` (404, a page or `{"error"}` JSON); an address declared with other methods answers `Errors::raise(1000)` (405) with an `Allow` header; `/` is matched by its own route (its routes have no components), and `HEAD` is evaluated as `GET`. A POST, PUT or DELETE form without `_token` still fails the CSRF check (`[ERROR 3000]`) before routing. On older versions keep `Route::get('/', ...)` first. Proof: `Route::evaluate()`, `Route::pathMatches()`, `qa/tests/http_tests.php`.

**G5. `redirect()` does not stop the script.** It only sends a `Location` header. Write `redirect('/x'); exit;` (or `return`) when code follows. `logout()` and `guard()` do exit. Proof: `corehelpers.php` (`redirect`), `Auth.php`.

**G27. Client app pages expose `API_KEY` to the browser** (while the legacy key is on: since 2.7.0 `env.apiKey` is `null` with `LEGACY_API_KEY=false`, and pages call with `apiHeaders()`, G87). `views/Client/app.pug` injects `apiKey => API_KEY` into the JS `env`, so anyone who can load a client-app page can call the JSON API. Every API key is `encryptDecrypt('encrypt', APP_KEY, APP_KEY)`: all keys generated from one APP_KEY are equivalent and can only be revoked together by changing `APP_KEY`. Protect sensitive data routes with `authorized()`/`guard()` too. Proof: `views/Client/app.pug`, `Request::authorizeApiRequest`, `Caligrafer::generateKeys`.

**G28. Closure routes skip the API-key check.** Only `'Controller@action'` routes enforce `Auth::activateAPI()` + Bearer key; closures run for any JSON request. Proof: `Route.php` (`execute`).

**G29. PUT with multipart/form-data does not populate `$_POST`.** For file uploads send a POST with `_method=PUT`; otherwise send JSON with the JSON Accept header.

**G34. Server files and uploads.** The root `.htaccess` denies (403) existing dotfiles (`.env`, `.git`, `.claude`), the folders `application|framework|vendor|tests|qa|docker|claude-plugin|archives|backups|node_modules` (`backups/` since 2.19.1: before, the copies of the files `update`/`upgrade` replace, `.env` included (`Updater::env()` backs it up before adding new settings), could be downloaded from a host that serves the project folder; projects get the line with `update`, or add `backups` to the rule by hand), `caligrafer.php`, `composer.*`, `docker-compose.yaml` and `.sql|.log|.bak|.ini|.sh|.env` files; non-existing paths still reach the router. `public/uploads/.htaccess` denies scripts. Both files only use mod_rewrite with `[NC]` (case-insensitive, for macOS/Windows file systems): hosts need `AllowOverride FileInfo` (no `Require`/`php_flag`, which need AuthConfig/Options and caused 500s). `uploadFile($file, $allowedExtensions = null)` only accepts `DEFAULT_UPLOAD_EXTENSIONS` (or the given list) and always refuses php-like, html, js and svg files; `isAllowedUpload($name, $list)` checks a name. nginx ignores `.htaccess`: copy the rules from the wiki (1. Getting Started > Protecting your server files). Verify with `curl -I <host>/.env` (403/404). **Fixed** (before: `.env` was downloadable and an uploaded `.php` ran). Proof: `.htaccess`, `public/uploads/.htaccess`, `corehelpers.php` (`uploadFile`, `isAllowedUpload`).

**G35. Client app template.** `views/Client/app.pug` only includes `public/<appName>/<app>.php` when both names match `[A-Za-z0-9_-]+` and the real path stays in the app folder (no sub-folders, no symlinks leaving the app), and escapes `$env` for the JS template literal (JSON_HEX_TAG + `\`, backtick, `${`). **Fixed** (before: `?app=../../x` included any PHP file and `?x=${...}` executed script). Projects initialized earlier must copy the new `app.pug` and `ClientController.php` (G39). Proof: `views/Client/app.pug`, `controllers/core/AI/ClientController.php`.

## Validation

**G6. `Controller::check()` returns an array on success too.** With rules AND filters it returns GUMP `run()` output: the filtered data array on success, the error array on failure, so `if ($result !== true)` treats success as an error. Prefer:
```php
$data = $this->filter($input, ['email' => 'trim|sanitize_email|lower_case']);
$valid = $this->validate($data, ['email' => 'required|valid_email']);
if ($valid !== true) { http_response_code(422); return api(['errors' => $valid]); }
```
`check()` with rules only returns `true` or `['field' => 'message']`. Rules other than `required` are skipped when the value is empty. Proof: `framework/core/Validator.php`, GUMP `run()`, `qa/tests/cli_tests.php`.

**G18. `GUMP::is_valid(array $data, array $validators, array $fields_error_messages = [])`** (callable as `$this->validator->is_valid(...)`) returns `true` or readable HTML messages. The wiki used to say `is_value` (corrected).

## Models and database

**G7. Saved values.** **Fixed:** `create()`/`update()`/`save()` send every non-null value of a real table column (`0`, `''`, `false` -> `0` are saved; `null` is skipped so DB defaults/current values apply; non-column helper properties such as `public $errors = []` are ignored, columns read once per table with `SHOW COLUMNS`); `update()` with nothing left to save no longer emits invalid SQL. Older framework versions dropped all falsy values. Proof: `Model.php` (`prepareInputs`).

**G8. `all($args)` clauses are raw SQL.** `all(['condition' => 'WHERE active = ?', 'order' => 'ORDER BY created_at DESC', 'limit' => 'LIMIT 10', 'bindings' => [1]])` - each clause is appended verbatim (include the keywords); never interpolate user input, use `?` + `'bindings'` (bindings are new in the security release). Proof: `Model.php` (`all`).

**G9. `belongsTo()` is not an inverse relationship.** It calls `hasMany()` with the same defaults (`SELECT * FROM joined WHERE <thisclass>_id = this.id`) and returns an array. For "this row has a FK to a parent" use `hasOne('Parent', 'parents')` (FK `parent_id` on this table). Proof: `Model.php`.

**G10. FK names and types** *(MySQL rule)*. FK columns must match the referenced column's type/signedness and constraint names must be unique per database: name them `fk_<table>_<column>`. **Fixed for auto-created pivots:** `belongsToMany()` copies the referenced id types (`SHOW COLUMNS`) and names its constraints `fk_<pivot>_<column>` (hashed when longer than MySQL's 64 characters; before: signed `INT` + `fk1`/`fk2`, which failed with `INT UNSIGNED` ids and on the second pivot). Pivot name: `<table1>_<table2>` unless `<table2>_<table1>` exists. Call the relationship before `attach('<joined table>', $id)`/`detach(...)`; both return the refreshed list. Proof: `Model.php` (`belongsToMany`, `columnType`).

**G23. `Controller::save($inputs)` updates when the route has `{id}`.** `save()`, `find()` and `delete()` default the id to `$this->request->fetch('id')`. On a route like `POST /books/{id}/reviews`, `$this->save($review)` UPDATES review `{id}` instead of creating one. Name the route variable differently (`{book_id}`) or pass the id explicitly / use `$this->model->create($inputs)`. Proof: `Controller.php`.

**G24. `Database::execute()` returns arrays of associative arrays** (it re-encodes rows), not objects. Use `arrayToModel()` to get models. Models use `public` properties for every column; ids are strings when they come from PDO.

**G19. Global class namespace.** Since 2.18.0 the class map loads each class of `application/` when it is first used (G118; before, every `.php` file of `application/` and `tests/` was required on the first unknown class), and `tests/` is never loaded. Classes are global unless you declare a namespace: two files declaring the same fully qualified class name is a fatal "Cannot declare class" error. Use distinct names (`BookController`, `Book`, `BookService`) or a namespace (`Shop\OrderController`, routed as `'Shop\OrderController@show'`, G47); the example controllers are in `Caligrafy\Controllers` and keep scratch PHP out of `application/` (the QA suite lives in `qa/`, the app's tests in `tests/`, neither loaded on a page). Proof: `ClassMap.php`, `Framework.php`.

## Environment and Docker

**G11. `.env` overrides docker-compose environment.** `index.php` and `caligrafer.php` use `Dotenv::createUnsafeMutable()`, which overwrites existing variables. Under Docker set in `.env`: `DB_HOST=mysql`, `APP_ROOT=` (empty), `DB_ACTIVATE=true`. Wrong `APP_ROOT` makes `session('home')`, `redirect()` and asset URLs point to `http://localhost:8080/<folder>/...`.

**G12. Docker ports 8080 / 8077 / 3306 clash** with other Quill projects or a local MySQL/MAMP. Check with `lsof -nP -iTCP:<port> -sTCP:LISTEN` or `docker ps` and remap the host side (e.g. `8081:80`, `8078:80`, `3307:3306`). The MySQL container has no named volume: after `docker-compose down` the recreated container starts with an empty database (`stop` keeps it). The QA stack uses 8090/3316.

**G20. Every `.env` key becomes a PHP constant, as a string.** A key that classes read (`OPEN_AI_KEY`, `GROQ_API_KEY`, `WATSON_API_KEY`, `MAIL_HOST`, `API_KEY`, `APP_KEY`, `APPROVED_AGENTS`, ...) must exist in `.env` or PHP throws "Undefined constant". Values are strings: test booleans with `filter_var(X, FILTER_VALIDATE_BOOLEAN)` (**Fixed** in `Mail`: `APP_DEBUG=false` used to enable SMTP debug output). Use `defined('X') && X !== ''` for optional settings. Proof: `Framework::init()` (`loadConstantFromArray($_ENV)`).

**G17. The Groq key is `GROQ_API_KEY`** (`Agent::settings()`, `.env.example`). The wiki said `GROQ_AI_KEY` (corrected). The other Agent providers read `TOGETHER_API_KEY`, `FIREWORKS_API_KEY`, `OPENROUTER_API_KEY`, then `AGENT_API_KEY`; the key of another provider is never used, so switching `AGENT_PROVIDER` without its key throws in `new Agent()`.

**G25. `caligrafer initialize` side effects.** It keeps an existing `APP_KEY`/`API_KEY` (**Fixed**: it used to prepend new keys on every run, invalidating API keys), always rewrites `APP_ROOT` (the folder name, or empty when an argument follows: `.bin/caligrafer` passes `off` when no local `httpd` runs), copies `framework/settings/application` to `application/` if absent, `chmod -R 777 public/uploads`, and **deletes `.git`**. `.bin/caligrafer server start` does nothing when a local `httpd` (MAMP/LAMP) is running.

**G39. Upgrading a project.** New projects initialized with this release already have the current templates. For older projects, updating the framework does not update `application/`: the auth module, `views/Client/app.pug`, `ClientController`, `AIController` and the auth views are project copies. Run `php caligrafer.php upgrade` (dry run; on a framework that has it) and `--apply`: missing and untouched files are updated with a backup in `backups/`, customized ones get `<file>.caligrafy-new` to merge by hand, the old `echo $e->getMessage();` routes `catch` becomes `Errors::handle($e);`; `.caligrafy-new` files and the reported client apps still need a manual merge. Without the command, compare them with `framework/settings/application`. Then copy the root and `public/uploads` `.htaccess` files, run `ALTER TABLE users MODIFY passcode varchar(255) NOT NULL;` and promote admins (`UPDATE users SET permissions = 1 WHERE ...`). Wiki page 9.5 Security and Upgrading lists every change.

## Views and Vue client apps

**G15. Page routes for client apps (Vue-as-library, bot, facedetect, ml).** `ClientController@index` renders `views/Client/app.pug`, which includes `public/<appName>/<app>.php`:
- the route path must END with the app folder name (`Route::get('/apps/{appName}', 'ClientController')` or `Route::get('/faceauth', ...)` for a fixed folder);
- view vars `appName` (folder in `public/`) and `app` (markup file without `.php`, default `index`, overridable with `?app=`) select the file; both must match `[A-Za-z0-9_-]+` (G35);
- every other view var, plus `app_root`, `service_root`, `app_resources`, `request`, `apiKey`, `token`, `home`, `keywords`, `metadata`, `copyright`, is JSON-encoded into `$env`, which the markup passes to `loadEnvironment(\`<?php echo $env; ?>\`)`; JS then reads `env.home`, `env.token`, `env.appName`, global `apiKey`;
- `loadVue({scripts: ['main']})` loads `public/<appName>/scripts/main.js`.
A custom controller can do the same: `return view('Client/app', ['appName' => 'faceauth', 'app' => 'index', 'user' => $data]);`.

**G16. FaceDetect (@vladmandic/face-api 1.7.15).** Wrap the detector in `Vue.markRaw`. `const detector = Vue.markRaw(new FaceDetector('detection'));` - otherwise Vue 3 proxies the detector, its canvases/streams and face-api objects. **Fixed** in the `facedetect` scaffold.

**G21. `view()` and `api()` end the request.** For JSON requests (and always for `api()` / `view(null, ...)`), the View echoes JSON and calls `exit`. Set status codes first: `http_response_code(422); return api([...]);` (axios then rejects: payload in `error.response.data`).

**G22. Vue SPA template (`caligrafer create`).** Vite 8, Vue 3.5, Vue Router 5, Pinia (store `src/common/store.js` with `defineStore`, no Vuex), axios 1.20, ESLint 10 flat config (`eslint.config.js`); needs Node 20.19+/22.12+. In Vite always use `import.meta.env.VITE_*` (**Fixed**: the template had Vue-CLI `require()`/`process.env.VUE_APP_*`). Since 2.19.1 the template calls the application with the session, never a key: `src/common/app.js` exports `config` (`apiRoute` = `VITE_API_ROUTE` or `''` for the same origin, `capabilities` = `VITE_CAPABILITIES_ROUTE` or `/capabilities`), `csrfToken()` (read once from `GET {apiRoute}/capabilities`, so web.php needs `Route::capabilities('/capabilities')`), `apiHeaders(extra)` (async: `Accept: application/json` + `X-CSRF-Token`) and `apiConfig` (`withCredentials: true`, no `Authorization`); `main.js` puts them on `globalProperties` (`this.config`, `this.apiConfig`, `this.apiHeaders`). Writes: `axios.post(config.apiRoute + 'books', book, { headers: await apiHeaders(), withCredentials: true })`; better, the generated JS client (`php caligrafer.php client public/<name>/src/capabilities.js`). `src/router/index.js` uses `createWebHashHistory()` and `vite.config.js` `base: './'`, so `dist/` works from `public/<name>/dist/` with no server fallback; `App.vue` has no `#app` wrapper (the page's `#app` is the mount point). `dist/` is no longer git-ignored: commit it, the production image runs no npm. Apps created before 2.19.1 keep `VITE_API_KEY` in `src/common/app.js` and `main.js` (`Authorization: Bearer`): replace those with the session calls above (G50), set `base: './'` and the hash history, and remove `dist` from the app's `.gitignore`. Proof: `framework/librairies/app/`.

**G26. Session setters and falsy values.** **Fixed:** `session('x', 0|false|'')`, `scripts()`, `keywords('')`, `copyright('')`, `metadata(['k' => ''])` store the value (only `null` means "read"). Use `clearFromSession('x')` to remove. Older versions ignored falsy values.

**G30. String helpers.** `startWith()`/`endWith()` return booleans (the wiki used to say they strip the needle); **Fixed:** `endWith()` compares the whole needle (it compared only the last character). `singularize()` just drops the last character.

**G36. The Watson bot client is a Vue 3 chat** (`framework/plugins/bot`, `public/css/bot.css`): BotUI and Vue 2 were removed. Messages render as text (no `v-html`); `pause` responses with a context action call `window.executeBotAction(context)` (return `{text, variables}`). The Watson class needs `WATSON_URL` (instance URL), optionally `WATSON_ENVIRONMENT_ID` (sessions under `/environments/{id}/sessions`) and `WATSON_API_VERSION` (default 2024-08-25); auth stays basic `apikey:<key>`.

## Auth module

**G13. `Auth/create_db.sql`.** **Fixed:** `CREATE TABLE IF NOT EXISTS users` (no `DROP`), `passcode varchar(255)`, unique `username`. Scripts from older projects start with `DROP TABLE IF EXISTS users;` - never re-run those on a database with data.

**G14. Passwords.** **Fixed:** `AuthController` stores `User::hashPassword()` (`password_hash`), checks `verifyPassword()` and upgrades legacy `encryptDecrypt` passcodes on the next login (`needsRehash()`); passwords are 8-72 characters; usernames are looked up exactly. `AdminController::ADMIN_PERMISSION = 1`: new users (permissions 0) cannot manage users; promote admins in SQL. Keep `APP_KEY` unchanged until legacy users have logged in once. `Auth::authorize($user)` requires an instance of the model given to `Auth::authentication()`; after changing a user's row in the DB the session no longer matches and the user must log in again.

## AI modules

**G31. OpenAI models.** Defaults (Oct 2026): `OPENAI_DEFAULT_MODEL` = `GPT6_LUNA` (`gpt-6-luna`, override with `OPEN_AI_MODEL`), also `GPT6_SOL`, `GPT6_ASTRA` (flagship; `GPT4_LATEST` points to it). Only `model` is sent by default (reasoning models reject temperature/top_p/max_tokens; use `max_completion_tokens`/`reasoning_effort` when supported). `GPT3`, `GPT4`, `GPT4_32` are shut down; `GPT4O`/`GPT4OMINI` retire Jan 14, 2027. `IMAGE` uses `gpt-image-2` (`OPEN_AI_IMAGE_MODEL`) and returns **base64** (`response[0]['b64_json']`, type `'image'`), never URLs; DALL.E is shut down. `COMPLETE` sends the text after `[insert]`; `CUSTOM` is an empty system prompt (put yours first in `$context`). Resolve user input with `OpenAI::command()`/`OpenAI::type()`, never `constant()`. `httpRequest()` sends JSON bodies for every method except GET.

**G32. Assistant 2.0 (Responses + Conversations APIs).** OpenAI shut down the Assistants API on Aug 26, 2026. `Assistant` keeps `start()` (POST /v1/conversations, `thread_id` = conv id), `createMessage()` (queued input), `run()` (POST /v1/responses with model, instructions, tools, conversation; the answer is ready when it returns), `retrieveResponse($timeout, $interval)` (only for queued/in_progress), `end()`. Read the answer with `text()` / `response_text`; `messages` entries are `['role', 'content' => [['type' => 'input_text'|'output_text', 'text']]]`. Nothing is created on OpenAI for the assistant itself (`assistant_id` unused): files go to a vector store (`vector_store_id`, reuse it) used by `file_search`; `code_interpreter` gets `container: {type: auto, file_ids}`. `delete()` deletes the vector store; `deleteAssistantFile($id)`.

**G33. Agent providers and defaults.** `AGENT_PROVIDER` empty = Groq through groq-php, exactly as before: `GROQ_DEFAULT_MODEL` = `openai/gpt-oss-120b` (`GROQ_MODEL`), `GROQ_SMALL_MODEL` = `openai/gpt-oss-20b`, `GROQ_DEFAULT_VISION_MODEL` = `qwen/qwen3.8-27b` (preview; `GROQ_VISION_MODEL`), `GROQ_TRANSCRIPTION_MODEL` = `whisper-large-v3`, `GROQ_API_BASE`. Groq retired **all** its Llama chat models (Llama 3.x in Aug 2026, Llama 4 Scout/Maverick earlier); Together and Fireworks serve Llama only on dedicated/on-demand deployments, so serverless Llama means `AGENT_PROVIDER=openrouter` (`meta-llama/llama-3.3-70b-instruct`, vision `meta-llama/llama-4-maverick`) or `ollama` (`llama3.2`, `llama3.2-vision`, after `ollama pull`). From Docker, Ollama is `AGENT_API_BASE=http://host.docker.internal:11434/v1` (`localhost` is the container). `AGENT_MODEL`/`AGENT_VISION_MODEL` win over `GROQ_MODEL`/`GROQ_VISION_MODEL`. Use `$agent->model()` rather than `GROQ_DEFAULT_MODEL` in code that may run on another provider. `delegate(model:, messages:, ...)` uses the default model when `model` is empty; errors come back as `['api_success' => false, 'response' => message]`; `transcribe()`/`translate()` return a JSON string and are Groq only (Exception elsewhere). `configure()` called the missing groq-php `setConfig()` before (fatal error); it now stores parameters merged into each request. `AGENT_API_BASE`/`GROQ_API_BASE` override the endpoint (used by the QA mock).

## Payments

**G37. Stripe (stripe-php 22).** `createCheckout()` returns `'url'`: redirect to it (Stripe.js removed `redirectToCheckout`; the example view `default/pages/checkout` takes `checkoutUrl`). `$paymentType` defaults to null: current API versions reject `payment_method_types` on checkout sessions (payment methods come from the dashboard). `createPaymentIntent()` enables automatic payment methods; the payment page uses the **Payment Element** (`stripe_client.js`, `stripe.confirmPayment`, `#payment-element`; since 2.11.0 it reads the page's tokens for its colors and Inter). Amounts are in cents; methods return `['action_success' => bool, 'data' | 'error' => ...]`. `createTransaction()` (Charges + tokens) is legacy and needs raw-card access for card arrays. The QA suite runs `Payment` against stripe-mock.

## Testing the framework

**G38. QA suite.** `./qa/run.sh` (Docker, PHP + curl, Node, `vendor/`) builds an isolated stack (compose project `quillqa`, the first free ports from 8090 and 3316, or `QA_WEB_PORT`/`QA_DB_PORT`) and runs framework (`qa/tests/cli_tests.php`), HTTP (`qa/tests/http_tests.php`) and tooling (`qa/tests/tooling_tests.sh`) tests, then removes everything (`--keep` to inspect). It refuses to run when a non-QA `application/` or `.env` exists. `qa/fixtures/docs/components.php` is a browser page of the wiki Vue samples (`/apps/qaapp?app=components` with `--keep`). It also runs the PHPUnit unit tests of the core (`qa/unit`, `phpunit.xml.dist`); run them alone with `vendor/bin/phpunit` (no Docker, database or `.env`), and add one there for new pure logic in `framework/core` (never under `tests/`: the framework requires every PHP file of `tests/` on each request). `QA_NO_BUILD=1` reuses an existing `quillqa-web` image instead of `up --build` (for networks where the image build fails during apt-get). Only one QA stack can run at a time (shared compose project and ports).

## Current libraries (October 2026 upgrade)

**G40. Bootstrap 5, no jQuery.** The default layout loads `scripts('bootstrap_css')`/`scripts('bootstrap_script')` = Bootstrap 5.3.8 and no jQuery. Use Bootstrap 5 classes (`mb-3`, `w-100`, `d-none d-md-block`, `badge bg-success`, `.row` around `col-*`, `visually-hidden`) and plain JavaScript (`script.js`, `picture_uploader.js` are vanilla). `theme.css` keeps the black and white look (14px body, Caligrafy `.card` as block, black `btn-primary` incl. disabled, white `btn-secondary` with black text, black focus rings, readable `a.btn-*` links: Bootstrap 5 alone makes `btn-secondary` white on white). `styles.css` caps buttons in forms at 150px (`form .btn`): keep labels short (`w-100` has no effect there). `theme.css` also restores the Bootstrap 3 layout: `body .container { max-width: none }` (Bootstrap 5 capped and left-aligned every page), heading margins in the Pug layout only (`body:where(#gesturize, .caligrafy-layout) h1`: the layout body is `body(id='gesturize' class='caligrafy-layout')`, the id is a compat alias for older layouts; they center the header title; SPAs load styles.css after Bootstrap and keep margin 0), `header.caligrafy-header` for the Caligrafy header in client apps outside the Pug layout (title margins without custom CSS; or `class="caligrafy-layout"` on their body). Rules that override a same-selector Bootstrap rule are prefixed with `html` (work even if Bootstrap loads last; classes still win), `p`/`label` spacing, `.row` inside `.wrapper`/`.bounded*` without negative margins (a `.row` class on the wrapper itself un-centers it: put the row inside), card border `#d9d9d9` and `.card.colorN` colors, text `#333`, button line-height/4px corners. The signed-in header (`nav.horizontal.row.g-0`) keeps Bootstrap 3 columns: 15px column padding and the title next to the menu icon on phones (no auto margins). Bootstrap 5 breakpoints are one step lower: BS3 `col-sm-*` = BS5 `col-md-*`, `col-md-*` = `col-lg-*`, `col-lg-*` = `col-xl-*`, `hidden-xs` = `d-none d-md-block`. `.card` is capped at 600px (styles.css): use `.card-unbounded` for wider content. The SPA template's `index.html` loads Bootstrap 5.3.8 (older SPAs load Bootstrap 3 + jQuery there). For new client apps reuse the Caligrafy chrome (header `Cali`/`grafy`, `content-section` > `wrapper` > `.card dropshadow color0`, footer) and these classes instead of Bootstrap colors. jQuery 4 (`scripts('jquery')`) is only for Summernote-lite 0.9.1 (`wysiwyg_css/js`, `load_wysiwyg.js`). Removed: add-to-homescreen.js (the `add_to_home`/`add_to_home_css` scripts keys do not exist any more; the layout links `public/manifest.webmanifest` instead and `'install' => true` now includes the new plain-JavaScript `modules/install.pug`: Install button on `beforeinstallprompt`, iOS Share hint, wiki 7 #install), Hammer.js gestures, lightbox key, IE fallbacks. Pinned shared libs: `scripts('vue')` (3.5.44), `scripts('axios')` (1.20.0). Old projects keep Bootstrap 3 views: override the keys in `application/config/config.php` (wiki 9.5).

**G41. ML Core 2.0 (ml5 1.4.0, p5 2.3.4).** `poseNet`/`uNet` became `bodyPose` (MoveNet) / `bodySegmentation`; callbacks are `(results, error)`; models load asynchronously (`myMl.ready`); `ml5.setBackend('webgl')` is called for the neural network (WebGPU is not ready synchronously). Poses have the ml5 shape (`keypoints[i] = {x, y, confidence, name}` snake_case, `pose.nose`) plus the previous shape (`pose.pose.keypoints[i].position/score`, camelCase `pose.pose.leftEye`, `pose.skeleton`), scaled to the canvas (logical size, not the 2x backing store of p5). Feature extractor: options `{version, alpha, task}` (no `numLabels`), `train(options, whileTraining, done)` needs a function as 2nd callback, `addImage(media, label)`, `classify(media, cb)`. p5 2.x: no `p5.dom` file, no `preload()`.

**G42. face-api.** `public/js/services/face-api.min.js` is @vladmandic/face-api 1.7.15 (archived but the maintained fork) with its models in `public/resources/models` (`*-weights_manifest.json` + `*.bin`). MTCNN and TinyYolov2 no longer exist (`faceapi.nets.mtcnn` must not be loaded). `FaceDetector.ready` resolves when the models are loaded; `loadApp()` awaits it (recognition samples used to load too early). Camera: `new FaceDetector(id, { autoStart: false })` + `await detector.startStream(constraints)` (rejects with `NotAllowedError`/`NotFoundError`, also in `detector.streamError`), `stopStream()`; with the default autoStart the error is only logged. Face prints: `await detector.faceprint(media)` returns `{descriptor: [128], detection}` of the only face (rejects `No face found` / `More than one face found`); same person < 0.5 euclidean distance, different > 0.6; compare on the server. A single print is not a login: a photo passes and a captured print can be replayed. Face login = `FaceLogin::challenge()` (server nonce, 60 s, single use, 2 of `turn-left`/`turn-right`/`smile` in a random order; `blink` was removed as unreliable and is now refused as an unknown challenge) -> `await detector.liveness({ challenges })` (resolves `{passed, reason, challenges, prints}`, never rejects on a failed check; passes only with >= 3 prints, the minimum of verify(); turns are the person's own left/right) -> `FaceLogin::verify($response, $username)` (nonce consumed even on failure; liveness in the requested order; 3-12 prints of one face, not identical) -> `FaceLogin::match()`, plus a table of used print hashes. It raises the bar, it is not proof (a deepfake virtual camera or a modified page can pass): pair it with a passkey (`Caligrafy\Passkey` + `public/js/services/passkey.js`, lbuchs/webauthn) for sensitive accounts. Passkey pitfalls: one lbuchs `WebAuthn` instance reuses its first challenge (Passkey creates one per ceremony); set `PASSKEY_RP_ID`/`PASSKEY_ORIGIN` in production (default: the request host); https except on localhost; `userVerification`/`residentKey` `required`. Recipe: recipes-face-login.md.

**G43. Crypto payments: Coinbase Business.** Coinbase Commerce shut down (Mar 31, 2026). `CryptoPayment` 2.0 calls `https://business.coinbase.com/api/v1/checkouts` with a per-request JWT (`iss: cdp`, `aud: [cdp_service]`, `uri: "METHOD host/path"`, 120 s) signed with `COINBASE_API_KEY_SECRET` (Ed25519 base64 -> EdDSA via sodium seed keypair; PEM EC -> ES256) and `kid`/`sub` = `COINBASE_API_KEY_NAME`. `createTransaction()` returns `url` (also `data['hosted_url']`); `getChargeStatus()` returns ACTIVE/PROCESSING/COMPLETED/EXPIRED/FAILED/DEACTIVATED/REFUNDED/PARTIALLY_REFUNDED; `cancelTransaction()` deactivates; `refundTransaction()`. US/Singapore merchants only. `CRYPTO_PAY_KEY` is no longer read.

**G44. PHP 8.3+ (Docker PHP 8.5).** Composer requires PHP ^8.3 with stable releases (stripe-php 22, Phug 2, Pug-php 3.6, PHPMailer 7, phpdotenv 5.8, GUMP 2.3, SimpleImage 4.4, groq-php 1.4). `index.php` and `caligrafer.php` hide `E_DEPRECATED` (Phug 2 calls `SplObjectStorage::attach`, deprecated in 8.5, which used to break pages and sessions). Do not call `curl_close()` or `ReflectionProperty::setAccessible()` (deprecated in 8.5, no effect since 8.0/8.1). Docker: php:8.5-apache, mysql:8.4, phpmyadmin:5.2.3, composer:2.

**G45. Vue state is Pinia.** Vuex is gone from the SPA template: `app.use(createPinia())` in `main.js`, stores with `defineStore('id', { state: () => ({}), getters, actions })`, use `const store = useAppStore()` (Options API: in `data()`), no mutations (`store.count++`, actions or `$patch`).

**G46. .env keys of the integrations.** `OPEN_AI_KEY`, `OPEN_AI_MODEL`, `OPEN_AI_IMAGE_MODEL`, `GROQ_API_KEY`, `GROQ_MODEL`, `GROQ_VISION_MODEL`, `WATSON_API_KEY`, `WATSON_URL`, `WATSON_ENVIRONMENT_ID`, `WATSON_API_VERSION`, `COINBASE_API_KEY_NAME`, `COINBASE_API_KEY_SECRET`, `PAY_*` (Stripe). Optional ones may be empty; the code checks `defined(X) && X !== ''`.

**G47. Controller names that collide with the example controllers (fixed).** Before Quill with plugin 1.6.0, the initialized `application/controllers/core/` declared `AIController`, `AdminController`, `AuthController`, `ClientController` and `WatsonController` as global classes, so a controller of yours with one of these names was a fatal "Cannot redeclare class" on every page (HTTP 500). Now they live in `Caligrafy\Controllers` (`Route::EXAMPLE_CONTROLLERS_NAMESPACE`) and `Route::resolveController()` resolves `'Name@action'` in this order: a declared class `Name` (global or fully qualified, leading `\` allowed) wins, so your own `AIController` is used; else a short name falls back to `Caligrafy\Controllers\Name` (old routes such as `'ClientController'`, `'AuthController@login'` keep working); else 404 (1001). New template routes use the full names (`'Caligrafy\Controllers\ClientController'`). Namespaced app controllers work: `namespace Shop;` + `Route::get('/orders/{id}', 'Shop\OrderController@show')`. Older projects: `php caligrafer.php upgrade --apply` replaces the untouched example controllers (customized ones get a `.caligrafy-new`: add `namespace Caligrafy\Controllers;` and `use User;` by hand); their `web.php` needs no change. Code that referenced an example class directly (`new AuthController()`, `extends AIController`) must use `Caligrafy\Controllers\...`. Other classes (models, your controllers) are still global unless you namespace them (G19).

**G48. Errors are served with HTTP 200 (fixed).** Older Quill versions answered every framework error (`[ERROR 1004] Access Denied`, `[ERROR 3000] Security Error`, PHP fatals) with status 200. Now: `401` (1004, missing/invalid API key), `403` (3000, CSRF), `404` (1001, controller or action), `405` (1000), `500` for other errors, PHP fatals and uncaught exceptions (message only with `APP_DEBUG=true`, `[ERROR 500] Internal Server Error` otherwise, never a stack trace). API calls (`Accept: application/json`) get `{"error": {"code": 1004, "message": "Access Denied"}}`; HTML requests keep the `[ERROR ...]` text. axios rejects these responses: read `e.response.status` and `e.response.data.error.message`. Projects initialized before the fix keep a text body until `application/config/routing/web.php` calls `Errors::handle($e);` in its `catch` (add `use Caligrafy\Errors;`); the status codes are right either way. Your own controller errors: set `http_response_code()` before `api()` (`api()` keeps the code you set).

**G49. `.env` comments on their own line.** `FLAG=false # comment` and parentheses in unquoted values make PHP's `parse_ini_file` fail (used by the QA suite and some tools; the project's own `.env` loading is more tolerant). Put comments on their own `# ...` line. The QA tooling phase checks the three templates (`framework/settings/.env.example`, `.env.example`, `framework/settings/.env.docker`).

## Diagnosing errors quickly
- Start with `php caligrafer.php doctor` (`--only db,openai,groq`, `--json`): read-only checks of PHP, `.env`, keys (spaces, malformed prefixes), the database, OpenAI/Groq models available to the key, Stripe, Coinbase, Watson, SMTP, and TLS/proxy errors, with secrets masked.
- `[ERROR 1001]` controller/action not found: check spelling, that the file is under `application/`, no duplicate class names.
- `[ERROR 1004]` JSON call refused: `Auth::activateAPI()` present? Bearer key generated from the current `APP_KEY`?
- `[ERROR 3000]` CSRF: missing `_token`, or an API call without `Accept: application/json`.
- `[ERROR 2001]` invalid column name passed to `search()` (or a Model write with a non-identifier key).
- `[ERROR 2003]` DB: `DB_ACTIVATE=true`? `DB_HOST` (`mysql` in Docker)? table exists? SQL valid?
- Blank page / home page instead of your route: route not matched (G4) - check segment count and HTTP method.
- `403 Forbidden` on a file: it is in a denied folder or has a denied extension (G34) - move public assets to `public/`.

## Build once: services, capabilities, tokens and MCP (Quill 2.1)

**G50. The legacy API key is public.** `views/Client/app.pug` passes `API_KEY` to every client page while it is on and Vite SPAs created before 2.19.1 build `VITE_API_KEY` into their bundle (`src/main.js`, `src/common/app.js`; the 2.19.1 template uses the session and `X-CSRF-Token` instead, G22), while JSON requests skip the CSRF check (`Request::checkCSRF()`): anyone who opens an app page can read the key and call every JSON controller route, writes included, from anywhere. Every key from `generatekeys`/`generateapikey` encrypts the same `APP_KEY` (`Request::isLegacyApiKey()`), so none can be revoked alone. For browser clients use the session (the generated JS client, G54; `apiHeaders()` for controller routes since 2.7.0, G87); for programs and agents use personal access tokens (`php caligrafer.php token create`, `Caligrafy\Tokens`); then set `LEGACY_API_KEY=false` (doctor reports the setting).

**G51. Closure routes are not protected for JSON.** `Route::execute()` checks the API key only for `'Controller@action'` routes, and `Request` skips CSRF for JSON: `Route::post('/x', function () {...})` accepts a JSON post with no key and no token. Put anything sensitive behind a controller route or a capability (`Route::capability()` checks the context itself).

**G52. Services return data; only declared inputs reach them.** A `Caligrafy\Service` must not call `view()`, `api()`, `redirect()`, `echo` or read `$_SESSION`/the request: the channel renders (`Controller::call()`, `Rest`, `Mcp\Server`) and `$context` says who calls. `validate()` keeps only the keys of `$rules` (`array_intersect_key`): an input without a rule must still be listed, with `''`, or it is dropped silently. `handle()` receives the filtered values (GUMP `run()`), so read `$input`, never the raw request.

**G53. Scopes and levels.** `Context::allows($scope, $level)`: the CLI and the legacy key always pass; a signed-in user needs `permissions >= level` (no scopes); a token needs the scope (`books:write`, `books:*`, `*`), and when it has an owner (`token create --user <id>`) the owner needs the level too. An application token (no `--user`) is limited by its scopes only: give destructive scopes to application tokens with care. A capability with `scope: ''` is callable by any token; `public: true` by anyone.

**G54. Session calls that write need `X-CSRF-Token`.** `Rest::respond()` refuses (403, "Missing or invalid CSRF token") a call that is authenticated by the session cookie, is not read-only and lacks the CSRF token in `X-CSRF-Token` (or `_token`). `GET {prefix}` of `Route::capabilities()` returns it in `csrf`. Calls with `Authorization: Bearer` (token or legacy key) need none: a cross-site form cannot set that header, and `Request::checkCSRF()` skips the form check when a bearer is present.

**G55. `Auth::authorized()` compares the whole user row.** The session stores an encrypted copy of the user; `authorized()` reloads the row and compares every column (`$user == $sessionAuthUser`). Any change to the row signs the user out, including `modified_at` (`ON UPDATE now()` in the Auth `create_db.sql`) after an admin changes `permissions`. Sign in again after updating your own row, or update rows of other users only.

**G56. MCP.** `Route::mcp('/mcp')` (`Caligrafy\Mcp\Http`): POST only (GET/DELETE 405), one JSON-RPC message per request (no batches), JSON answers (no SSE), personal access tokens only (the legacy key gets 401), a foreign browser `Origin` gets 403 unless listed in `MCP_ALLOWED_ORIGINS`. Without a valid token: 401 with `WWW-Authenticate: Bearer error="invalid_token"` (what MCP clients read to ask for a token). Since 2.19.1 `Request::checkCSRF()` skips POSTs to a `Route::mcp` address (`Route::isMcp()`): before, a client that did not send `Accept: application/json` got the `[ERROR 3000]` page instead of the 401. Tools = `Capabilities::available($context)`; a tool the token cannot see answers like an unknown one (-32602). Refusals are tool results with `isError: true` and the `ServiceError` JSON in the text. `php caligrafer.php mcp` (stdio) without `CALIGRAFY_TOKEN`/`--token` has full access (`Context::local()`). The capability `description` is the tool description: write it for an agent; mark deletes `destructive: true`.

**G57. `Database::execute()` connects on first use.** Before 2.1 only `Controller`'s constructor connected, so a closure route that used a model threw `[ERROR 2003]`. Now `execute()` calls `Database::connect()` when there is no connection yet (and `DB_ACTIVATE=true`).

**G58. The channel classes moved to `framework/interfaces/` (2.1.2).** `Rest.php`, `Client.php` and `Mcp/{Server,Http}.php` left `framework/core/`; class names and namespaces are unchanged (`Caligrafy\Rest`, `Caligrafy\Mcp\Server`). A framework copied over 2.1.0/2.1.1 keeps the old copies: `Framework`/`Caligrafer` skip them (`FRAMEWORK_MOVED_FILES` in `initialize.php`), so no class is declared twice, and `doctor` warns `Framework files: old copies ...` with the `rm` command. `git pull` removes them by itself.

**G59. The Caligrafy header of `layoutblocks.pug` is fixed: content starts under it.** (Pages on `site.pug`, 2.11.0+, have a sticky top bar instead: nothing hides under it.) `header` is `position: fixed` (`public/css/styles.css`, about 80px tall) and `block content` of `layoutblocks.pug` starts at the top of the page, so a page that begins with a heading or `.spacer-2` hides its title under the header. Wrap pages in `main.cq-page` (top padding that clears the header, `tokens.css`), or `.content-section` + `.spacer-4` as the sign-in view does. See [ui-guidelines.md](ui-guidelines.md).

**G60. The theme overrides some Bootstrap utilities on cards and buttons.** `styles.css` caps `.card` at 600px, adds 40px padding and centers it vertically (`align-self: center`), and the theme sizes `.btn`, so `w-100` on a button and wide `.card`s do not do what Bootstrap says. Use the page components instead: `.cq-card` (600px like the sign-in card since 2.4.0, 640px before; `.cq-page--wide` 960px; no padding of its own) and `btn btn-primary cq-submit` (at least 150px, on the left since 2.4.0; full width before).


**G61. Two `.cq-card` in one page sat side by side (2.2.0 only).** In 2.2.0 `.cq-page` was `display: flex` in a row, so a second `.cq-card` landed beside the first on wide screens. Fixed in 2.3.0 (`flex-direction: column`): cards stack. In a 2.2.0 project copy `public/css/tokens.css` from 2.3.0, or keep one card per page with `.cq-section` parts. Proof: `public/css/tokens.css` (`.cq-page`).

**G62. `caligrafer create` in Docker does not install the SPA packages.** The `dev-box` image has no Node.js. Since 2.3.0 `create` says so ("its packages are NOT installed: npm is not available here"); before, it printed `sh: 1: npm: not found` then a false "successfully created and all packages have been installed". Either way run `cd public/<name> && npm install` on the computer (Node 20.19+ or 22.12+).

**G63. A Vite SPA page keeps showing the previous item when only the route parameter changes.** Vue Router reuses the component from `#/books/1` to `#/books/3`, so `mounted()` does not run again. Use `<router-view :key="$route.fullPath">` in `App.vue` (or watch the parameter). Prefer `createWebHashHistory()`: the built app in `public/<name>/dist/` is not at the site root, and hash addresses survive a reload there.

**G64. `$this->request->fetch('field')` reads route variables, not posted fields.** In a controller, a form's fields come from `$this->request->parameters()` (GET: the query string; POST/PUT/DELETE: the form or the JSON body). `fetch()` returns `''` for a posted field, so validation reports it as missing. `$this->call('<capability>')` merges both for you. Proof: `Request.php` (`fetch()`, `parameters()`).

**G65. The audit time and the `created_at` of rows could differ by hours (before 2.3.0).** The audit is written with PHP's clock (`SERVER_TIMEZONE`, America/New_York by default) while `DEFAULT CURRENT_TIMESTAMP` columns use MySQL's (UTC in the Docker `mysql` container). Since 2.3.0 `Database::connect()` sets the MySQL session time zone to PHP's, so both agree; `DB_TIMEZONE=server` keeps MySQL's zone. Rows written before the upgrade keep their old times.

**G66. `npm install` in a Vite app reports 10 moderate vulnerabilities.** All come from `minimatch` below 11.0.1 (GHSA-g3hv-75wg-m249), required by ESLint 10 and its plugins: a denial of service through crafted glob patterns, in the lint tooling only. Vue, Vue Router, Pinia, axios and the built app are not affected (`npm audit --omit=dev`: 0). `npm audit fix --force` would install ESLint 4.0.0: never run it. Fix: `"overrides": { "minimatch": "^11.0.1" }` in the app's `package.json`, then `rm -rf node_modules package-lock.json && npm install` (verified: ESLint and `npm run build` still work, `npm audit` 0). The template of `framework/librairies/app` has the override after 2.2.0. Remove it once ESLint requires minimatch 11.

## Putting it online (2.4.0: production image, `deploy`, Caligrafy Cloud)

**G67. Production reads a `.env` file, never plain environment variables.** `index.php` and `caligrafer.php` load `.env` with Dotenv (`createUnsafeMutable`): its values replace environment variables of the same name, and `caligrafer.php` copies `.env.example` to `.env` when the file is missing. So the production image needs its settings as a file at `/var/www/html/.env` (Coolify file storage, `docker run -v .env.production:/var/www/html/.env:ro`, Caligrafy Cloud writes it). Hosts that only offer environment variables (Railway, Render, DigitalOcean App Platform) are not supported yet: do not tell a user they work as they are. In that file `APP_ROOT=` must be empty: the folder name that `initialize` writes (`APP_ROOT=my-project`) makes every link and redirect point to `/my-project/...` on a domain root.

**G68. `php caligrafer.php deploy` runs a changed `create_db.sql` again, except one that drops or deletes.** It records each `application/models/*/create_db.sql` with its SHA-256 in `caligrafy_scripts`. A new or changed script runs on the next start, so it must be safe to run twice: `CREATE TABLE IF NOT EXISTS`, and changes as added statements (`ALTER TABLE ... ADD COLUMN`, `ADD INDEX`, `ADD CONSTRAINT ... FOREIGN KEY`), never by dropping the table. Since 2.13.5 a statement that adds what is already there (MySQL/MariaDB errors 1060 duplicate column, 1061 duplicate key name, 1826 duplicate foreign key) is skipped, so the script keeps its `ALTER TABLE ... ADD` lines and runs again on every change; before 2.13.5 the second run stopped at such a line, the script was never recorded, and every later deploy failed on it (add the column by hand, then remove the line). A changed script whose text (comments removed) contains `DROP TABLE|DATABASE|SCHEMA|VIEW|COLUMN`, `DELETE FROM` or `TRUNCATE` is skipped with a WARN line: run it by hand (`php caligrafer.php sql <file>`). `seed.sql` is never run. Since 2.18.0 a change to a table that has data belongs in a migration (`application/migrations`), which the deploy step runs once, after the scripts (G120). Proof: `framework/core/Deployer.php` (`Deployer::alreadyApplied()`, `qa/unit/DeployerTest.php`).

**G69. A production build needs `composer.lock` committed.** The `Dockerfile` runs `composer install --no-dev` from `composer.json` and `composer.lock`; without the lock file (or with one older than `composer.json`) the build fails or installs other versions. `vendor/`, `.env`, `.git`, `qa`, `tests` and `backups` never reach the image (`Dockerfile.dockerignore`).

**G70. Sessions and uploads live in the container unless a volume keeps them.** PHP sessions are in `/var/lib/php/sessions` (since 2.4.0) and uploads in `/var/www/html/public/uploads`: without a volume at each, a new deploy signs everyone out (a sign-in form opened before the deploy then fails with `[ERROR 3000] Cross Site Forgery`) and deletes the uploads. Caligrafy Cloud adds both volumes.

**G71. MySQL 8.4 with its defaults needs more than 256 MB.** Given a 256 MB limit it is killed while it initializes (`OOMKilled`, exit 137, restarting), and the half-initialized volume never gets its application user: the app then fails with `SQLSTATE[HY000] [1130] Host '...' is not allowed to connect`. For a small app database: `performance_schema = OFF`, `innodb_buffer_pool_size = 64M` and about 384 MB; if it was already killed and holds no data, recreate its volume.

**G72. Before 2.7.0, `php caligrafer.php cloud login` needs a terminal to paste into** (2.7.0+: it is approved in the browser, G88; `--paste` keeps the prompt). It reads the token from a hidden prompt; run without a keyboard (an IDE "Run" button, a pipe) it reads nothing and stops with "This is not a Caligrafy token". Ask the user to run it in their own terminal, or `read -rs T && php caligrafer.php cloud login --token "$T"; unset T`. Never ask for the token in chat. It is kept in `.caligrafy/cloud.json` (0600, git-ignored since 2.4.0).

**G73. Stripe accounts with Managed Payments refuse a Checkout product without a tax code.** New Stripe accounts have Managed Payments on by default (Stripe collects the sales tax): a Checkout line item without a tax code fails with "the product tax code is missing". Before 2.11.0 `Payment::createCheckout()` then returned `action_success => false`, so the example `/checkout` page and every app's checkout failed on a new account. Since 2.11.0 a call without `tax_code` creates a classic Checkout (`managed_payments[enabled]=false`); to keep Managed Payments, add `'tax_code' => 'txcd_...'` to its `$productData` (the 4th argument: it goes into `price_data.product_data`), for example `txcd_10701100` (Website Hosting), `txcd_10103001` (SaaS, business use), `txcd_10000000` (electronically supplied services). `'managed_payments'` in `$payment_intent_data` (the 10th argument, merged into the session) decides either way. `GET https://api.stripe.com/v1/tax_codes/<code>` checks a code. Proof: `framework/core/Payment.php` (`createCheckout`).

## Building an application from an idea (the `idea` skill, plugin 1.16)

**G74. A view only knows who is signed in when its route says so.** `view()` passes nothing on its own; the layout's
header and any `if $authorized` in a page read the variable the route gives. The framework's controllers pass it
(`AuthController`, `AdminController`, `AIController`), closure routes do not: `Route::get('/books', function () { return view('Books/index'); });`
renders every visitor as signed out (no *Sign out*, the signed-in parts hidden). Pass it from every route of such a
page: `view('Books/index', array('authorized' => authorized()))`, and the same for `/` and `/home`. Since 2.5.0 use
`Route::page('/books', 'Books/index')` instead: the page controller always passes `authorized`, `user` and the route
variables. Proof: `framework/helpers/corehelpers.php` (`view()`), `framework/core/PageController.php`, the controllers
in `framework/settings/application/controllers/core/`.

**G75. The sign-up form accepts usernames of letters and digits only.** `AuthController::register()` validates
`username` with `required|alpha_numeric|max_len,100`: an email address, a dot or a dash is refused ("The username
must only contain letters and digits"). Say so next to the *Sign up* link of an application, or change the rule in the
project's copy of the controller.

**G76. `php caligrafer.php service` without `--scope` writes `scope: ''`.** The template's `{{scope}}` becomes an empty
string, so the capability needs no scope from a token: any token can call it. Always pass `--scope <resource:action>`
(one `service` command per capability), or set the scope in the attribute right away. Proof: `caligrafer.php`
(`case 'service'`), `framework/settings/service.php.template`.

**G77. `token revoke` takes the number, not `#2`.** `token list` prints tokens as `#2 name ...`; `token revoke #2`
reads `(int) '#2'` = 0 and answers "No active token #0", revoking nothing. Use `php caligrafer.php token revoke 2`.

**G78. `quickstart` prints `localhost:8080` even when the ports are remapped.** In Docker its closing `claude mcp add`
line always uses `http://localhost:8080`; with a `docker-compose.override.yml` (port 8080 taken by another project),
use the remapped port in that line and in every URL you give the user. Proof: `framework/core/Quickstart.php`.

**G79. Tokens for an application online come from a page, not the command line.** A deployed application (Caligrafy
Cloud, Coolify) has no shell for `php caligrafer.php token create`, so AI agents could not connect to it. Give the
application a capability for signed-in people that calls `Tokens::create($name, $scopes, $context->user->id, $expiresAt)`
and refuses token contexts (`$context->token !== null`: a token must not create tokens), with a small page card that
shows the token once and the `claude mcp add` line (the `idea` skill's `create_my_token`). It still appears in MCP
`tools/list` for a token with its scope or `*`: its refusal there is expected.

## Pages and the user capabilities (2.5.0: `Route::page`, only capabilities touch models)

**G80. `upgrade` holds the user capabilities when the project already manages users its own way.** 2.5.0 adds
`application/services/Users/` (capabilities `list_users`, `get_user`, `update_user`, `delete_user`, `register_user`;
classes `CaligrafyListUsers`, `CaligrafyGetUser`, `CaligrafyUpdateUser`, `CaligrafyDeleteUser`, `CaligrafyRegisterUser`,
`CaligrafyUsers`). They show as `held` (not added, with the reason) when another PHP file of `application/` declares one
of those capability names or classes (two declarations stop the registry, or are a PHP fatal), or when the project's
`AdminController` is customized; `AdminController` and `AuthController` are then held too, since they call them. Fix
the reason (rename the project's own capability or class), then add the files with
`php caligrafer.php upgrade --force application/services/Users/<file>`. Proof: `framework/core/Upgrader.php`
(`holdUserCapabilities()`, `declared()`).

**G81. `register_user` is refused over REST and MCP.** It is `public: true` (visitors sign up) but throws `forbidden`
unless `$context->channel` is `web` or `cli`, so nobody can create accounts in bulk through `/capabilities/register_user`,
a `Route::capability` route or an agent. Sign-up goes through the sign-up page (`AuthController@register` calls it) or
`php caligrafer.php call register_user username=... passcode=...`. Proof: `application/services/Users/CaligrafyRegisterUser.php`.

**G82. The administrator level lives in `CaligrafyUsers::ADMIN_LEVEL`.** The user capabilities declare
`level: CaligrafyUsers::ADMIN_LEVEL` and the 2.5.0 `AdminController` sets `ADMIN_PERMISSION` from it. A project whose
`AdminController` is customized (it may have raised `ADMIN_PERMISSION`) gets the user capabilities `held` by `upgrade`
(G80): set `ADMIN_LEVEL` in the new `application/services/Users/CaligrafyUsers.php` to the project's administrator
level, then add the files with `--force`. Otherwise the pages would guard with one level and REST and MCP with another.

**G83. `Route::page` data: read-only capabilities, route variables win, the query string never reaches the view.**
`'data' => ['book' => 'get_book']` calls `get_book` as the person who opens the page with the query string plus the
route pattern's variables, the route variables winning (`/books/3?id=9` gives `id = 3`); undeclared inputs are dropped.
Fixed inputs: `['book' => ['get_book', ['id' => 3]]]`. Only existing `readOnly` capabilities: another one, or a
`data`/`with` name `authorized` or `user`, throws `LogicException` (a 500 on every visit). A refusal ends the page: a
visitor (`unauthenticated`) goes to `/login`; `forbidden` answers `[ERROR 1006]` (403); `not_found`, `invalid` and
`conflict` answer `[ERROR 1005]` (404), or render the `notFound` view (option) with status 404, the route variables,
the `with` values, `$message`, `$authorized` and `$user`. The view gets the route variables, `with`, the data, `authorized` and `user`:
never the query string (read it through a capability input). Capability results are plain arrays (`$book['title']`).
Proof: `framework/core/PageController.php`.

**G84. `Route::page` with `signedIn` or `level` needs the authentication module.** Without
`Auth::authentication('User', 'users');` in `web.php`, such a page throws `LogicException("Route::page('...'):
'signedIn' and 'level' need Auth::authentication(...)")` (a 500). Pages without those options work without it
(`$authorized` is then false). `level` compares `Context::$levelAttribute` (`permissions` by default). Proof:
`framework/core/PageController.php` (`render()`).

**G85. The legacy API key and tokens without an owner never manage users.** The user capabilities call
`CaligrafyUsers::requireAdmin()`: only the command line or a real person at `ADMIN_LEVEL` (signed in, or a token
created with `--user <id>`). The shared `API_KEY` (full access everywhere else) and `token create ... --scopes "*"`
without `--user` get `forbidden`; give an agent that manages users a token of an administrator. No one manages someone
above their own level (`forbidden`) or grants more than it (`invalid` on `permissions`); `delete_user` revokes the
deleted person's tokens, and since 2.5.0 `Context::allows()` refuses a token whose owner no longer exists (with
`Auth::authentication` on): recreate tokens of deleted users. Proof: `application/services/Users/CaligrafyUsers.php`,
`framework/core/Context.php` (`allows()`).

**G86. A local server's web folder and ports are what its settings say, not the defaults.** Many MAMP installs serve
another folder than `/Applications/MAMP/htdocs` (Apache's `DocumentRoot`, for example `~/www`) and use ports 80 and
3306 instead of 8888 and 8889 (verified on a real install). Since 2.6.0 `quickstart` reads them from MAMP's
`conf/apache/httpd.conf` and `bin/startMysql.sh`; for XAMPP and LAMP it assumes their defaults. A project outside the
served folder gets a 404 from Apache, not a Caligrafy error: create it in the folder `DocumentRoot` names (or give the
address with `--url`). A Docker MySQL published on 3306 (another project's `docker compose`) takes the port a local
MySQL wants: MAMP's MySQL then cannot start, and quickstart's defaults would reach the Docker database instead; check
`lsof -nP -iTCP:3306 -sTCP:LISTEN` before trusting the defaults. That is why, without a terminal to confirm them (Claude
running it, CI), quickstart skips the database instead of using the defaults: never pass `--db-defaults` before checking
which MySQL answers on that port. Proof: `framework/core/Quickstart.php` (`stacks()`,
`localSite()`).

**G87. Pages call their routes with the session, never the API key: online the key is off.** Caligrafy Cloud writes
`LEGACY_API_KEY=false` for every app (`Cloud::envFile()`), so a page that sends `Authorization: Bearer ' + apiKey`
works locally and gets `[ERROR 1004] Access Denied` online (verified on a FacePass deploy: the header reached PHP, the
key was refused). Since 2.7.0 `Route::execute()` accepts a JSON call to a controller route that carries the session's
CSRF token in `X-CSRF-Token` (`Request::authorizeSessionRequest()`): the controller sees the signed-in user or a
visitor, exactly as for a form post. In a client app, `apiHeaders()` of `public/js/services/app.js` gives
`Accept` and `X-CSRF-Token` (`env.csrf`), never `Content-Type` (axios 1.x would send a `FormData` as JSON); `env.apiKey` is `null` when the key is off. Capabilities
already worked this way through the JS client (G54). The refusal now says why: no CSRF token and no Bearer, a token
that is unknown or lacks `*`, `LEGACY_API_KEY=false`, the API not activated, or a key of another `APP_KEY`.
`doctor` warns about `public/*/scripts/*.js` that send the key. Before 2.7.0, call capabilities with the JS client
instead of controller routes. Proof: `framework/core/Route.php` (`execute`), `framework/core/Request.php`
(`authorizeSessionRequest`, `apiRefusal`), `framework/settings/application/views/Client/app.pug`.

**G88. `cloud login` is approved in the browser (2.7.0+).** Without `--token` it calls `start_cli_login` (no token),
prints a code and `https://deploy.caligrafy.com/cli?code=XXXX-XXXX`, opens it (not from inside Docker: `/.dockerenv`,
or with `--no-browser` / `CALIGRAFY_NO_BROWSER`), and polls `poll_cli_login` every 3 seconds for 10 minutes. The owner
signs in (or signs up, then back to the page), checks the code and approves; the command saves the token in
`.caligrafy/cloud.json`. A visitor who is not signed in is sent to `/login` and comes back to the approval after it.
The account must have confirmed its email. Tokens are listed and revoked on the dashboard. `--token cq_...` and
`--paste` keep the old ways. Run from Claude, start it in the background and open the URL for the user when it runs
in the Docker server. Proof: `framework/core/CloudClient.php` (`browserLogin`), Caligrafy Cloud `StartCliLogin`,
`PollCliLogin`, `ApproveCliLogin`.

**G89. A pipe hides the exit code of a command.** `php caligrafer.php cloud deploy <name> | tail -15` exits 0 even when
Caligrafy Cloud refused the deploy ("has no time left"): the shell reports `tail`'s status. `cloud` exits 1 on every
refusal and failed build; run it whole (in the background when long) or with `set -o pipefail`. The same holds for
`doctor`, `call` and `qa/run.sh`.

**G90. Every new app on Caligrafy Cloud is free for its first 30 minutes, from its first deploy.** Since November 2026
the free minutes belong to the app, not the account (before, an account had 30 minutes once). An app whose minutes
are used answers `deploy_app` with "has no time left (its free 30 minutes are used): buy a week (2 credits)...";
`cloud extend <name> week|month|year` then deploy again. `cloud status <name>` shows the time left; `my_account` gives
`free_minutes_per_app`. A private repository needs its read-only deploy key on GitHub first: since 2.7.0 `cloud create`
saves it in `.caligrafy/<name>-deploy-key.pub` (`cloud key <name>`), for `gh repo deploy-key add`.

**G91. Give a token's expiry as a Unix time (2.7.1+).** Token dates are stored in local time (`SERVER_TIMEZONE`). When
the clocks fall back, one local hour happens twice and `strtotime()` reads its first occurrence: before 2.7.1 a token
created in that hour to expire an hour later was refused at once. `Tokens::create($name, $scopes, $userId, time() +
86400 * 90)` stores the expiry with `Tokens::expiresAt()`, which moves an instant of the repeated hour to the first
local time after it (02:00): a token never stops early, at worst an hour late. A `date('Y-m-d H:i:s', ...)` string is
still accepted and stored as given (without that protection). `Tokens::verify()` and `token list` share
`Tokens::expired()`. Proof: `framework/core/Tokens.php`.

**G92. A design style changes nothing until its stylesheet exists (2.8+).** `DESIGN_STYLE=<name>` in `.env` only
selects a style: the layout (`design_stylesheet()` in `layoutblocks.pug` and `framework/plugins/app/index.php`) links
`public/css/designs/<name>.css` when that file exists, after `styles.css`, Bootstrap, `theme.css` and `tokens.css`.
An empty setting, an unknown name, a name that is not `[a-z0-9._-]` (no paths) or a style whose stylesheet is not
written yet all keep the Caligrafy look; `doctor` says which (`WARN Design style: ...`). The `design` skill writes the
stylesheet from the style's `DESIGN.md`: the first of `application/design/<name>/` (the project's own),
`framework/design/local/<name>/` (`interstellar`...) and `framework/design/vendor/<vendor>/<name>/` (awesome-design-md). Never restyle by
editing `tokens.css`, `tokens.json` (the QA suite checks they match), `theme.css`, `styles.css` or the views: an emptied
`DESIGN_STYLE` must bring the Caligrafy look back. Projects copied before 2.8 have no `design_stylesheet()` line in
their layout: `php caligrafer.php upgrade` updates an untouched `layoutblocks.pug`, otherwise add the four lines by hand.
Proof: `framework/core/Designs.php`.

**G93. A design stylesheet wins by specificity, not `!important`.** `styles.css` sets `font-family` and
`font-weight: lighter` on `body *`, `theme.css` colors buttons on `html .btn-primary` / `html .btn-secondary` through
Bootstrap's `--bs-btn-*` variables and gives `.form-control` `height: 3em; font-size: 1.5em`: prefix every rule of
`public/css/designs/<name>.css` with `html body`, redefine the whole `--bs-btn-*` set and set `background-color` /
`border-color` / `color` too. The sign-in button is `btn btn-primary btn-secondary w-100` (it renders as the white
secondary button in the Caligrafy look): style `.btn-primary.btn-secondary` as the main action. A Vite SPA imports its
CSS at build time (`src/main.js`): it needs `import '../../css/designs/<name>.css'` and a rebuild. Online: commit the
stylesheet and set `DESIGN_STYLE` on the server (`cloud secret set <app> DESIGN_STYLE=<name>`); the production image
leaves `framework/design/` out.

**G94. `update` needs itself first, and never touches your code.** `php caligrafer.php update` exists since 2.9.0: an
older project gets `framework/core/Updater.php` once (`curl -fsSL https://raw.githubusercontent.com/caligrafy/caligrafy-quill/master/framework/core/Updater.php -o framework/core/Updater.php`)
and runs `php -r "require 'framework/core/Updater.php'; exit((new Caligrafy\Updater('.'))->run());"`. It decides
what you changed by comparing each file with your project's own release (`Framework::VERSION`, downloaded from GitHub
with the new one): a project whose version is unknown (before 2.0) gets a `.caligrafy-new` for every file that
differs. `framework/` and `claude-plugin/` are always replaced (changes made there by hand are listed and backed up,
not kept): customize in `application/` and `public/<your app>/` instead. It never replaces `application/` (that is
`upgrade --apply`'s job, which it runs), `.env` values, `public/uploads` or `vendor/`, and stops on uncommitted git
changes unless `--yes`. Run it whole, not piped (G89). Proof: `framework/core/Updater.php`.

**G95. Before 2.9.2, `.bin/caligrafer` initialized any folder without `application/`, and `initialize` deleted `.git`
without asking.** Any launcher command (`.bin/caligrafer design list`, `cloud ...`) run in a clone of the framework,
or in the framework's own repository, ran `initialize` first, which removed the `.git` folder: the history was gone
unless it was pushed (verified: it happened in the framework repository; it was restored from GitHub with
`git clone --no-checkout` and moving its `.git` in, then `git reset`). The launcher also passed only one word after
the command, lowercased, plus `off`: `.bin/caligrafer design use linear.app` selected a style named `off`. Since
2.9.2 the launcher passes arguments as typed, never initializes on the way to another command (it says to run
`quickstart`), and `initialize` deletes `.git` only with `--yes` or a yes typed in a terminal. With an older
framework, never run `.bin/caligrafer` in a folder without `application/` and with a `.git` you want to keep.
Proof: `.bin/caligrafer` (`run_caligrafer`), `caligrafer.php` (`initialize`).

**G96. `upgrade` looks for a newer release first (2.10.0+).** Before 2.10.0, `php caligrafer.php upgrade` only
compared `application/` with the templates of the release the project already ran: it never said a newer release
was out, and "upgrading" changed nothing when the framework itself was old. Since 2.10.0 it asks GitHub
(`Updater::newerRelease()`); when a newer release exists, `upgrade` is `update --check` and `upgrade --apply` is
`update` (the framework, the release's untouched files, `composer.json`, `.gitignore`, `.env`, then the templates).
`--local`, `--force` and `--keep` skip the check; offline it says so and compares the templates. A project before
2.10.0 runs `php caligrafer.php update` (2.9.0+) or gets the command first (G94). Proof: `caligrafer.php` (`upgrade`).

**G97. One plugin source name for every project gave them all the first one's plugin.** A project enables its own
`claude-plugin/` through `.claude/settings.json` (a directory source at `./`, `caligrafy-quill@<source>`), and Claude
Code keeps one source per name for the whole computer (`~/.claude/plugins/known_marketplaces.json`). Before 2.10.1
every project called it `caligrafy`: the first project opened was registered, every other project silently loaded that
project's plugin (an older one: `/caligrafy-quill:design` "unknown command" in a 2.8.0 project, verified), and no
project got any once that folder was deleted. An already trusted folder is never asked again, so starting `claude`
there does not fix it. Since 2.10.1 `initialize` names the source `caligrafy-<folder>-<4 hex>` and `update` renames it;
`php caligrafer.php plugin` renames, registers (`claude plugin marketplace add <folder>`, replacing another folder's)
and installs it (`claude plugin install caligrafy-quill@<name> --scope project`); since 2.10.2 it also removes a
`caligrafy` registration made from the project's folder (claude opened before quickstart), so the next clone
registers its own; `doctor` warns. The framework's own
repository keeps `caligrafy`: it is the source added from GitHub. Proof: `framework/core/PluginSource.php`.

## Caligrafy's look (2.11.0: `site.pug`, `layout.css`, `look.css`, `camera.css`)

**G98. In a Caligrafy Pug view, `+` does not join strings.** Caligrafy renders Pug with PHP expressions: `"a" + $b`
is an addition, and with two strings PHP 8 throws "Unsupported operand types: string + string", so the page becomes a
template error. It only shows when that line runs: in 2.11.0's first build the message block of the sign-in pages
(`modules/notice.pug`) did it, and a wrong password gave an error page instead of "Invalid credentials" (fixed before
release; the QA test now checks the rendered alert). Join with `.` (`'Hello ' . $name`), or let Pug merge classes:
`div.cq-alert(class=($status == 'danger' ? 'cq-alert--error' : null))`. Proof:
`framework/settings/application/views/default/modules/notice.pug`.

**G99. New pages extend `default/master/site.pug`, not `layoutblocks.pug`.** Since 2.11.0 Caligrafy's own pages use
`site.pug` and the classes of `public/css/layout.css` (no cards, see [ui-guidelines.md](ui-guidelines.md));
`layoutblocks.pug` and the card components of `tokens.css` still work for older pages and get the new look from
`look.css`. A project before 2.11.0 has neither `site.pug` nor `layout.css`/`look.css`/`camera.css`: `php caligrafer.php
update` adds the stylesheets and `upgrade --apply` the view templates (a template changed by hand is kept, the new
version written next to it). The SPA template imports `layout.css` and `look.css` in `src/main.js`: an SPA made
before 2.11.0 adds the two imports and rebuilds.

**G100. The top bar has a button: find yours by id.** `site.pug` puts `button.cq-nav-toggle` (Menu, for phones)
before the page. A script that takes `document.querySelector('button')` gets the Menu button: caligrafy.com's card
payment read its Stripe key from it, so Stripe started with an empty key and no card field appeared. Use ids
(`#submit`) or a class of your own. Proof: `framework/settings/application/views/default/master/site.pug`.

**G101. Stylesheets and scripts carry their modification time.** `site.pug` links `tokens.css`, `layout.css` and
`look.css` with `?v=<filemtime>`, so a deploy reaches browsers at once. Link your own assets the same way
(`src=$home . 'public/js/app.js?v=' . @filemtime(PUBLIC_PATH . 'js/app.js')`): an unversioned script stays cached and
a fixed page keeps running the old code. When checking a page in a browser after a change, reload it: navigating to
the same URL with only a new `#hash` does not reload.

**G102. Pug (PHP mode): a computed attribute value can fail to parse.** An attribute whose value is an expression with parentheses and concatenation, such as
`aria-label=($i + 1) . ' of ' . count($items)`, or a last attribute ending in a comparison such as `hidden=$i !== 0)`,
fails with *ParseError: syntax error, unexpected token ")"* on the line of the tag. Compute the value first on a code
line and pass the variable, or use a simpler comparison:
```pug
each $shot, $i in $shots
  - $position = sprintf('%d of %d', $i + 1, count($shots))
  figure(aria-label=$position, hidden=$i > 0)
```
(found building the marketplace pages of caligrafy.com, 2.13.0).

**G103. A new skill is missing after an update: Claude Code runs its own copy of the plugin.** Installing the plugin
copies `claude-plugin/` into `~/.claude/plugins/cache/<source>/caligrafy-quill/<version>/`
(`~/.claude/plugins/installed_plugins.json` records it per project), and Claude Code loads that copy. Before 2.13.1
`php caligrafer.php update` replaced the folder but not the copy: a project updated to 2.13.0 kept plugin 1.24.4,
without `/caligrafy-quill:marketplace` (verified). Since 2.13.1 `update` and `php caligrafer.php plugin` run
`claude plugin marketplace update <source>` and `claude plugin update caligrafy-quill@<source> --scope project` when
the installed version differs from `claude-plugin/.claude-plugin/plugin.json`, and `doctor` warns. In Docker, where
`claude` is missing, run `php caligrafer.php plugin` on the computer. Then restart `claude` in the folder. Proof:
`PluginSource::installedVersion()`, `connect()`.

**G104. A form with no method leaks its fields into the address when its script does not run.** A `form` without
`method` is a GET form: a page whose script sends it (the JS client, `event.preventDefault()`) works while the script
runs, but when it is not loaded, fails or is still loading, the browser submits the form itself, as
`GET /page?email=...&password=...`: in the address bar, the history and the server's logs (verified on Caligrafy
Cloud's sign-up page, whose view did not load its script). Give every form `method='POST'` (the script still sends it;
without the script the post is refused by the CSRF check, never shown in an address), make sure the page loads the
script, and send each form once in a browser: the address must not get a `?`. Since 2.13.3 `doctor` warns about a
Pug form in `application/views` with an email or a password field and no method. Proof: `Doctor::formsWithoutMethod()`,
`qa/unit/DoctorFormsTest.php`.

**G105. In a Pug view, a dollar sign before digits vanishes.** Caligrafy renders Pug with PHP: `$40` in text, in an
attribute or in a string is read as a variable and shows as nothing (`p From $40 a month` renders "From  a month";
`'$' . $price` is fine). Write `&#36;40` in text (`p From &#36;40 a month`), or build the amount in an expression
(`p= '$' . number_format($price)`). Since 2.13.5 `doctor` names each line (`Pug` WARN, `file:line $digits`); comments
(`//-`) are ignored. Proof: `Doctor::pugPitfalls()`, `qa/unit/DoctorPugTest.php`.

**G106. In a Pug view, a one-line `- if (...) ...` or `- foreach (...) { ... }` does not compile.** Code after the
condition on the same line (`- if ($sold) echo 'Sold'`, `- foreach ($rows as $r) { $total += $r['price']; }`) breaks the
whole template with a Phug error. Use Pug's own `if $sold` / `each $r in $rows` with the lines under it, put the code
of `- if (...)` on the indented lines below, or write an expression (`- $total = array_sum(array_column($rows, 'price'))`).
Since 2.13.5 `doctor` names each line (`file:line one-line - if`). Proof: `Doctor::pugPitfalls()`,
`qa/unit/DoctorPugTest.php`.

**G107. `redirect()` to another site (fixed).** Before 2.13.5 `redirect('https://deploy.caligrafy.com/...')` put the
site's own address in front (`https://caligrafy.com/https://deploy...`). Since 2.13.5 an `http://` or `https://`
address is used as it is, and a path stays a path of this site. On older versions, write
`header('Location: ' . $url)`. Proof: `redirect()` in `framework/helpers/corehelpers.php`.

**G108. A scheduled job has nobody signed in.** A job runs its capability with `Context::system('schedule', <job>)`:
full access, the audit log records `schedule` and the job's name, and `$context->userId()` is `null`. A capability
written for a signed-in user (`where user_id = $context->userId()`) does nothing, or everything, as a job: give the
job its own capability that finds the users it works for (all those with a due watch, say), and keep per-user rules
in it. Its inputs are the fixed `input` of `application/config/schedule.php`, validated by the capability's rules.
Proof: `Context::system()`, `Scheduler::execute()`, `qa/unit/SchedulerTest.php`.

**G109. Web push needs a click, HTTPS, and on iPhone the Home Screen.** `Notify.enable()` asks the browser's
permission, which browsers only allow from a user's click (never on page load). Push works on HTTPS (or `localhost`);
on iPhone and iPad only in the app added to the Home Screen (`Notify.status()` says `install-needed`). It also needs
`Route::capabilities('/capabilities')` (what `notify.js` calls), the VAPID keys (`php caligrafer.php notify keys`, made
by Caligrafy Cloud) and, after `platform install`, the tables. Never make new keys for a live app: every subscribed
browser stops getting messages. Proof: `public/js/notify.js`, `Notify::subscribe()`.

**G110. Emails sent by a job need `APP_URL`.** A job or the command line has no web request, so `Notify` cannot know
the app's address for the email's links: without `APP_URL` the email channel says `not-configured` (push still
works). Set `APP_URL=https://<the app's address>` in `.env`; Caligrafy Cloud sets it for its apps. Email also needs
`MAIL_*`; the sender is `MAIL_FROM`, else `MAIL_USERNAME` when it is an address. Proof: `Notify::base()`,
`Notify::emailConfigured()`.

**G111. Emails take their look from the design style's first `:root` rule.** `Notify::theme()` reads the custom
properties of the first `:root` rule of `tokens.css`, `look.css` and `public/css/designs/<DESIGN_STYLE>.css` (later
files win, `var()` resolved): `--cq-color-paper`, `--cq-color-page`, `--cq-color-ink`, `--cq-color-text`,
`--cq-color-text-muted`, `--cq-color-border`, `--cq-color-primary`, `--design-on-primary`, `--design-font-body` or
`--design-font`, `--cq-radius-control`, `--design-radius-card`. A style that sets its colors only inside other rules, or
in a second `:root`, leaves the emails in Caligrafy's look; web fonts do not load in most mail apps, so the stack's next
fonts show. Proof: `Notify::theme()`, `qa/unit/NotifyTest.php`.

**G112. The social card comes from `metadata()`, and needs a title.** Every page built on `site.pug` prints its card
(`modules/metadata.pug`) from `metadata()` in `application/config/config.php`, or the `'metadata'` its view gets;
nothing is printed before a `title`. Give `site_name`, `title`, a `description` under 125 characters (longer ones are
cut), and an `image` of 1200x630 with `image_alt`; `og:url` is `site_url` (else `APP_URL`) plus the page's path. X and
LinkedIn show only the image, the title and the domain: put the message in the title. Before 2.16.2 the card had
`og:type` `og:product` (refused by Facebook), a 600x317 image and placeholder values, and only pages that passed
`'metadata' => metadata()` showed it. Platforms keep a card for days: after a change, "Scrape again" in Facebook's
Sharing Debugger. Proof: `framework/settings/application/views/default/modules/metadata.pug`.

**G113. In `site.pug`, the children of `body` are flex items.** Since 2.16.2 `body.cq-site` is a flex column (the
footer starts one screen down): an inline element placed directly in `body` (a bare `a` or `span`) takes the full
width, and vertical margins between sections no longer collapse. Put content in a block (`section.cq-band`,
`.cq-inner`...); a page that writes its own footer keeps `footer.cq-footer` as a direct child of `body`. Proof:
`body.cq-site` in `public/css/layout.css`, the footer script of `site.pug`.

**G114. A password-protected app on Caligrafy Cloud answers a redirect, not its page.** Until it is offered to the
marketplace, an app's owner may lock it with a password on its Cloud page (Password section, off by default; the
`set_app_password` capability of Cloud). Every visitor, its owner included, then types it once per browser (30 days):
a page request answers `302` to `https://deploy.caligrafy.com/unlock/<name>`, and a JSON or API call answers `401
{"error":"This app is private..."}`. That is a healthy app, not a failed deploy: do not "fix" it. `cloud apps`, `cloud
status` and the end of `cloud deploy` print `[password protected]` (2.16.3); check it with
`curl -s -o /dev/null -w '%{http_code} %{redirect_url}' https://<name>.caligrafy.com/`. Never ask for the password and
never remove it without the user's OK. Offering the app to the marketplace removes it for good. Proof: Cloud's
`Passwords` model, `CloudClient::locked()`.

**G115. A Stripe test-mode webhook left on the production address fails once the app goes live.** Stripe keeps one
list of webhook endpoints per mode, each with its own signing secret (`whsec_...`). After `APP_ENV=production` and the
live keys, the server checks every event with the live secret, so the events of a test-mode endpoint still pointing at
it get `400` (invalid signature): Stripe retries them, emails "trouble sending requests in test mode",
and disables the endpoint after about nine days. Real payments are not affected. Fix: create the endpoint in live mode
with the live secret, and disable the test-mode one (or point it at a test server). The framework's own checkout
methods use no webhook (`retrieveCheckout()` on the return page): only an app that added one is concerned. Proof: wiki
9.2 "Going live", Caligrafy Cloud's `/stripe/webhook` in November 2026.

**G116. An empty bash array is an unbound variable on macOS under `set -u`.** macOS ships bash 3.2, where
`"${list[@]}"` on an empty array stops the script with `list[@]: unbound variable`; bash 4.4 and later accept it, so
Linux and Docker never show it. `github.sh` before 2.16.5 died this way on every Mac using SSH: `push` printed nothing
(its error went to `/dev/null`) and pushed nothing, and `publish` took an existing repository for a missing one. Write
`${list[@]+"${list[@]}"}`, and try a script with `/bin/bash` on a Mac. Proof: `qa/tests/tooling_tests.sh`.

**G117. A view copied with its old modification time can be served from the cache (2.17.0+).** Compiled Pug views are
kept in `<tmp>/caligrafy-views-<project>-<user>` and recompiled only when a view or a file it includes or extends is
newer than the cached copy. `rsync -a`, `cp -p`, `tar`/`unzip` and some deploy tools keep a file's old modification
time, so a changed view can look older than its cache and the old page keeps showing. Docker images and Caligrafy
Cloud are not concerned (each container starts with an empty `/tmp`), and editing a view locally always shows. Fix:
`rm -rf /tmp/caligrafy-views-*` after such a copy, or `VIEW_CACHE=false`. Do not "fix" a stale page by changing the
view again. Proof: `View::cache()`, Phug's `FileAdapter::hasExpiredImport()`.

**G118. A file in `application/` that only declares classes no longer runs on every request (2.18.0+).** The class
map loads a class when it is first used. A file that does more (it declares functions, calls
`Capabilities::register()`, sets something at the top level, declares a class inside `if`) is still loaded at start,
in path order, as every file used to be. Do not rely on one class file having been loaded before another is used:
reference the class and the map loads it. Capabilities are found by the files that mention `#[Capability`. A class
added while the app runs works at once in development; with `APP_ENV=production` the map checks its files at most once
a minute (a new container starts with none). `CLASS_MAP=false` restores the old loader. Proof: `ClassMap::declarations()`,
`qa/unit/ClassMapTest.php`.

**G119. The web server and the command line do not share the file cache (2.18.0+).** Apache runs as `www-data`, the
CLI, the queue and scheduled jobs as another user (root in Docker): `CACHE_STORE=file` gives each its own private
folder, so a job's `Cache::forget()` leaves the page's copy in place. That is why the store is `database` by default
when `DB_ACTIVATE=true` (the `caligrafy_cache` table; without it the file store is used and `error_log` says so). Keep
`file` only for an app without a database, and do not set `CACHE_STORE=file` on a project whose jobs change cached data.
Proof: the 2.18.0 local test (a book added from the CLI stayed hidden from the page with the file store).

**G120. A migration runs once: editing it after it ran does nothing (2.18.0+).** `migrate` records each file's name
and sha256 in `caligrafy_migrations`; an edited file shows as `changed` in `migrate status`, `doctor` warns, and the
deploy step never runs it again. Write the change in a new file (`php caligrafer.php migrate make <name>`), and restore
the old one. A file stops at its first failing statement and is not recorded, but MySQL has already committed the
`CREATE`/`ALTER` statements before it: keep one change per file, or make it safe to run twice (`IF NOT EXISTS`).
Proof: `Migrations::run()`.

**G121. A background job runs with full access, and a test's transaction ends at the first CREATE or ALTER (2.18.0+).**
`Queue::execute()` calls the capability as `Context::system('queue', ...)`, like a scheduled job: no user, every scope
(G108). Check who may ask for the work in the capability that pushes it, and pass ids. In
`Caligrafy\Testing\TestCase` each test runs in a transaction that is rolled back; MySQL commits it at any DDL
statement, so a test that creates or alters a table leaves its rows behind: create tables with migrations, run once on
the test database (`DB_TEST_DATABASE`). Proof: `framework/core/Queue.php`, `framework/testing/TestCase.php`.

## Queries (the query builder: `Database::table()`, `Book::query()`)

**G122. `Book::find()` and `$book->find()` are two methods.** `Model` no longer declares `find()`: on an instance
`__call()` runs the old `SELECT * FROM <table> WHERE id = ?`, statically `__callStatic()` runs
`Book::query()->find($id)` (`... WHERE \`id\` = ? LIMIT 1`, a `Book`). A model that declares its own `find()` keeps it
for instances (and `parent::find()` still works), but `Book::find(1)` then fails with "Non-static method cannot be
called statically": use `Book::query()->find(1)`. The same holds for `all()`, `search()`, `create()`, `update()`,
`delete()` (instance methods: never static). `method_exists($model, 'find')` is now false; `is_callable()` is true.
Proof: `framework/core/Model.php` (`__call`, `__callStatic`).

**G123. The table of `Book::query()` is guessed from the class name.** `Book` -> `books`, `BookReview` -> `bookreviews`
(the short class name lower-cased + plural, not snake_case), unless the class declares `protected static $tableName =
'book_reviews';` or a default `public $table = 'book_reviews';`. A private static `$tableName` is not seen. An
instance (`(new Book('books'))->where(...)`) uses its own `$table`. Proof: `Model::queryTable()`.

**G124. Relations of `with()` follow names, not the database.** A singular name is belongsTo (`author` -> `author_id`
on this table, `authors.id`), a plural one hasMany (`books` -> `books.<singular this table>_id`). Many-to-many needs
`['tags' => ['pivot' => true]]`, or `['pivot' => 'name']` for a pivot of another name. The default is the pivot
`Model::belongsToMany()` creates (G10): `<this table>_<related table>` (`books_tags`), and when the query on it fails
the reversed `tags_books` (the name Model uses when that one exists), remembered for the process; when both fail the
first error is thrown. Columns: `<singular this table>_id` and `<singular related>_id` (Model uses the lower-cased
class names: `Book` -> `book_id`; pass `foreign_key` / `related_key` when they differ). On PostgreSQL a failed
query aborts the transaction it runs in: inside `Database::transaction()` name the pivot. Irregular names (`person`/`people`, `status`) need `table` / `type`. The key columns must be in the rows:
`select('title')->with('author')` has no `author_id` and attaches `null`. A `query` constraint on a belongsToMany
relation runs on the join: qualify columns (`tags.label`). A `limit()` in a constraint limits the whole relation
query, not each parent. Related rows stay arrays when the main rows are models. Proof: `Query::relation()`,
`qa/unit/QueryEagerTest.php`.

**G125. `update()`, `delete()`, `increment()` and `decrement()` of a query need a `where`.** Without one they throw
`LogicException` and run nothing; `whereNotIn('id', [])` and `search($cols, '')` add no condition, so they do not count.
For every row of a table write the SQL with `Database::execute()`. Proof: `Query::needsWhere()`.

**G126. `search()` of a query escapes `%` and `_`; `Model::search()` does not.** `Book::query()->search(['title'],
'50%')` finds the text `50%` (bound `%50\%%`, MySQL's default `\` escape; `NO_BACKSLASH_ESCAPES` mode breaks it).
`(new Book('books'))->search('title', '%50%')` still takes a LIKE pattern as is (G2). Proof: `Query::search()`.

**G127. The N+1 warning is development only, once per shape and process.** With `APP_ENV` other than `production`
the 10th identical SELECT shape logs "Probable N+1 query" with the first file:line outside `framework/`; it counts
queries run through `Database::execute()` (models included). `APP_ENV` is read once per process, so a long-running
worker counts across jobs (the counts reset at 2000 shapes); call `Query::watch(null)` between jobs. Proof:
`Query::observe()`.

**G128. `Database::transaction()` does not nest.** A call inside another one (or inside a transaction already open on
the connection, such as a test's) just runs: an exception still reaches the outer call, which rolls back everything;
an inner exception that you catch inside the outer callback does not undo the inner writes. MySQL commits at any DDL
(`CREATE`, `ALTER`, and `Model::belongsToMany()`'s `CREATE TABLE IF NOT EXISTS`), ending the transaction early (G121).
Proof: `Database::transaction()`, `qa/unit/DatabaseQueryTest.php`.

**G129. Flash data lasts one request: the redirect target must be the page that shows it (2.19.0+).** `Route::run()`
ages the flash (`Flash::age()`) at the start of every request, so `errors()`, `old()` and `flash()` are read by the
first page after a `Route::form` post: a `success` path that redirects again, a page loaded in between (a prefetch,
another tab) or a JSON call on the same session consumes them. Point `back` and `success` at the page with the form or
the message. Passwords and other secrets are never kept for `old()` (`/pass|secret|token|card|cvc|cvv|iban/i`).
Proof: `framework/core/Flash.php`, `Form::oldInput()`.

**G130. A JSON request skips the form's CSRF check: the declarative routes ask for X-CSRF-Token themselves (2.19.0+).**
`Request::checkCSRF()` does not check `_token` when `wantsJson()` is true, and `Accept` is a header any cross-site
`fetch()` may set. `Route::form` (like `Rest`), `Route::auth` and the passkey endpoints therefore refuse a JSON write
that comes with the session cookie and no `X-CSRF-Token` (403). `'Controller@action'` routes are already protected:
`Route::execute()` refuses a JSON call without the session's token or an API token (1004). Closure routes are not
(G28): never write data in a closure route; use a capability route or `Route::form`. Proof: `Form::outcome()`,
`AuthRoutes::login()`, `Route.php` (`execute`).

**G131. A file input is a URL, and only web pages and REST can send the file (2.19.0+).** With
`'cover' => 'required|file|extension,png;jpg|max_size,2mb'` the web and REST channels save `$_FILES['cover']` (after
the access check, under a random name, executable types always refused) and give `handle()` its URL; a string posted in
that field is dropped. MCP and the CLI send a URL or a path whose extension is checked: never `fopen()` or `include` it
without checking where it points. `required_file` (or `required|file`) refuses a call without a file, URL or path
("The Cover field needs a file"); before 2.19.1 `required_file` was ignored on every channel and missing from the
schema's `required`: use `required|file` on older versions. The file is deleted when the call fails, not when a later step of your own fails.
Proof: `framework/core/Uploads.php`.

**G132. A webhook has no session and no CSRF token: its signature is the only proof (2.19.0+).** `Route::webhook`
refuses to register without `verify`; with `'stripe'` set `STRIPE_WEBHOOK_SECRET` (the endpoint's `whsec_...`, not the
API key) or every event answers 500. The capability runs as `Context::system('webhook', $path)` with full access: treat
`event` as data from outside (look the object up again with the Stripe API before trusting amounts), and make it
idempotent, Stripe retries until it gets a 2xx. Proof: `framework/core/Webhook.php`.

**G133. `Route::auth(['identifier' => 'email'])` changes the field the sign-in form posts (2.19.0+).** The POST
field is named after the identifier, so the shipped `login.pug` (a `username` field) must be changed to an `email`
field; the view gets `$identifier`. The lookup is the model's `findByUsername()` only for `username`; for another
column it is `findBy($column, $value)` when the model has one (the shipped `User` since 2.19.0), else a bound query on
the model's table. An older `findByUsername()` rewritten to search by email keeps working with the default identifier.
Sign-up now sends the register capability every input its `$rules` declare (not only `username`/`passcode`): a form
field the rules do not list never reaches it. Proof: `AuthRoutes::finder()`, `AuthRoutes::registerInput()`.

**G134. `exists` and `unique` are checks, not constraints (2.19.0+).** They run one bound query at validation time:
two requests at the same instant can both pass `unique`, so keep the column's `UNIQUE` index (and catch the duplicate
as `register_user` does). An empty optional input is not checked: add `required`. GUMP reads one `,` per rule, so the
framework joins `unique`'s extra arguments with `;` (`unique,users.email,id,id` = `unique,users.email;id;id`). A rule
named `exists` or `unique` that the app added with `GUMP::add_validator()` before the first `new Validator()` is kept
and replaces the framework's; one added after throws (GUMP refuses a second definition). The message uses the table in
the singular (`authors` -> "There is no such author"): set your own with `GUMP::set_error_message()`. Proof:
`framework/core/Validator.php`, `qa/unit/ValidatorRulesTest.php`.

**G135. `current_year` is read when the input is checked; the schema ignores a rule without its argument (2.19.0+).**
`max_numeric,current_year` (and `min_numeric`) compare with `date('Y')` of the server at validation time; no need to
append `date('Y')` to the rules in `validate()` (the rules are class defaults, read by the schema). In the JSON Schema
agents see, `current_year` is this year's number and a missing or non-numeric argument (`max_numeric,`, `max_len,x`)
is left out instead of crashing `Capabilities::schemaFromRules()` with a TypeError; GUMP still fails such a rule at
validation. Proof: `Capabilities::bound()`, `Validator::normalize()`.

## 2.19.1 fixes and pitfalls

**G136. Client app pages printed the session cookie into their JavaScript (fixed in 2.19.1).** `views/Client/app.pug`
gave `env.request` the whole `request()`, so every client app page (`ClientController`, `/apps/{appName}`, bots, ML)
wrote the `Cookie` header (the session id) and the `Authorization` header into the page, where any script on it could
read them. Since 2.19.1 it gives `request()->forClient()`: method, host, uri, route, route variables and the query
string, never a header that carries a credential nor the POST body. The view is a template of `application/`: a project
created earlier keeps the old one until `php caligrafer.php upgrade --apply` (or `update`) replaces
`application/views/Client/app.pug` (a copy you changed is kept and the new one written next to it as
`app.pug.caligrafy-new`: merge its `'request' => request()->forClient()` line, or `upgrade --force
application/views/Client/app.pug`). Code that read `env.request.cookie` or `env.request.authorization` must stop:
the browser has the cookie, and a token never belongs in a page. Proof: `Request::forClient()`,
`framework/settings/application/views/Client/app.pug`, `qa/unit/RequestTest.php`.

**G137. A webhook's capability is also served to REST, the JS client and MCP: guard it with `actorType()`.**
`Route::webhook('/stripe/events', 'record_payment', ['verify' => 'stripe'])` checks the signature, but
`record_payment` is a capability like any other: `Route::capabilities()` (`POST /capabilities/record_payment`),
`Route::mcp()` and `php caligrafer.php call` serve it to any signed-in user whose level passes, or any token with its
scope, with an `event` they made up. The webhook calls it as `Context::system('webhook', $path)`, whose `actorType()`
is `'webhook'` (and `actorId()` the path), so start `handle()` with:
```php
if ($context->actorType() !== 'webhook') {
    throw ServiceError::forbidden('Only the Stripe webhook records payments');
}
```
and add `mcp: false` to the attribute so agents do not even see it. The same holds for work only the system starts:
the scheduler calls with `Context::system('schedule', <job>)` (`actorType()` `schedule`), `Queue` jobs with
`Context::system('queue', '<capability> #<id>')` (`queue`); refuse other actors the same way (allow `cli` too if you
want `php caligrafer.php call` to run it by hand: the CLI context is `cli`). A scope alone is not a guard: a token
with `*` has every scope. Proof: `framework/core/{Webhook,Scheduler,Queue,Context}.php`.

**G138. `Book::query()->select('id', 'title')->get()` returns whole `Book` models.** Each row is hydrated with
`arrayToModel()`: the selected columns are set and every other public property of the class stays `null`, and
REST, MCP, `Route::download` (CSV columns) and `json_encode` show them all (`{"id": 1, "title": "Dune", "author_id":
null, "price": null}`): an API that looks like it lost data, or a CSV full of empty columns. For only the selected
columns use `Book::query()->select('id', 'title')->arrays()->get()` or `Database::table('books')->select('id',
'title')->get()` (arrays). Proof: `Query::get()`, `Model::arrayToModel()`, `Capabilities::plain()`.

**G139. GUMP's `word_count,min,10,max,500` never fails.** GUMP's `parse_rule()` splits a rule at the first `,` only
(`list($rule, $param) = explode(',', $rule)`), so the parameters are `['min']` and `validate_word_count()` checks
nothing. Its parameters are separated with `;`: `'body' => 'required|word_count,min;10;max;500'`. The same goes for
every GUMP rule with several parameters (`between_len,3;10`, `contains,a;b`); the framework only rewrites the extra
`,` of `unique` and `exists` (G134). Verified: `GUMP::validate(['t' => 'one two'], ['t' => 'word_count,min,10,max,500'])`
is `true`, with `min;10;max;500` it fails. Proof: `vendor/wixel/gump/gump.class.php` (`parse_rule`,
`validate_word_count`).

**G140. A wrong `Bearer cq_...` token is a visitor, and an application token passes any `level`.**
`Context::fromRequest()` turns a bearer that looks like a token but does not verify (unknown, revoked, expired, a typo)
into a guest, not a refusal: `public: true` capabilities still answer 200, the others 401 (MCP: 401 for any missing or
bad token). A client that sends a revoked token to a public capability gets no hint that the token is dead: check
`GET /capabilities` (the listing shows what the caller may use). A token created without `--user` has no owner, and
`Context::allows()` lets it through any `level` once it has the scope (`$this->user === null` and an empty `user_id`):
the level is a check on people. For capabilities that only administrators may use, check the person in `handle()`
(`$context->user` and `$context->level()`, as `CaligrafyUsers::requireAdmin()` does, G85) or keep their scope off
application tokens. Proof: `framework/core/Context.php` (`fromRequest`, `allows`).

**G141. `php caligrafer.php call` (and the scheduler, the queue worker, `mcp`) never loads
`application/config/config.php`.** The CLI loader (`Caligrafer::run()`) loads the constants, the helpers and the class
map, not `config.php` (only `Framework::run()`, the web, includes it). A custom rule added there with
`GUMP::add_validator()` exists on web pages and REST but not on the command line, the scheduler or a queue job:
`call` answers an error for the unknown rule. Put each custom rule in its own file of `application/`, outside
`config/` and `views/`, for example `application/rules/isbn.php`:
```php
<?php
// loaded at start by the web and by the CLI: a file with top-level code is "eager" in the class map
GUMP::add_validator('isbn', function ($field, $input, $param = null, $value = null) {
    return (bool) preg_match('/^(97[89])?\d{9}[\dX]$/', str_replace('-', '', (string) $value));
}, 'The {field} field is not an ISBN');
```
The class map (2.18.0) requires every file of `application/` that has top-level code when it registers, in the web and
in the CLI (`ClassMap::register()`, `ClassMap::declarations()` marks it `eager`); with `CLASS_MAP=false`,
`Capabilities::all()` requires every PHP file of `application/` except `config/` and `views/` before any capability
runs. Register a rule once: GUMP throws when the same name is added twice, so never also add it in `config.php`.
Proof: `framework/core/Caligrafer.php`, `framework/core/ClassMap.php`, `framework/core/Framework.php`.

**G142. Face drawings narrower than the face and sliding off it (before 2.19.2).** `FaceDetector` sized its canvas
from the `<video>`'s `width`/`height` attributes (640 x 480, 4:3) and resized the detections to that, while most
cameras send 16:9 (1280 x 720). The points were squeezed into 4:3, then `camera.css` cropped the 16:9 video and the 4:3
canvas differently (`object-fit: cover`): boxes and landmarks about 25% too narrow, further off away from the center.
2.19.2: the canvas takes the picture's size (`detector.mediaSize()`: `videoWidth` x `videoHeight`), follows a change of
resolution and copies the media's `object-fit`; `fetchImage` draws at the canvas size; the scaffold's page loads
`detect.js?v=<file time>`. An app drawing its own overlay: size its canvas with `video.videoWidth`/`videoHeight`, never
the element's attributes or `offsetWidth`, and give it the video's `object-fit`. Old client apps keep their own
`public/<app>/index.php`: add the `?v=` to its `detect.js` script so browsers fetch the new file.

**G143 (2.21.0). A `webOnly` capability answers "There is no capability" everywhere but `Route::action`.** REST, MCP, the JS client, `php caligrafer.php call`, `Route::page`, `Route::form` and `$this->call()` all get `not_found`: that is what lets it read the session, sign people in and change data on a GET. Keep plain capabilities for anything several channels share, and call them from it with `$this->capability($name, $input, $context)`. In tests use `$this->action()`, not `$this->call()`.
