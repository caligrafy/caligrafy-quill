# Caligrafy Quill gotchas and wiki errata (verified against the source)

Each item names the file that proves it. "Source" = caligrafy-quill master. Items marked *(MySQL rule)* follow
from the SQL the framework generates plus documented MySQL behavior. Items marked **Fixed** describe behavior
corrected by the security release (framework + `framework/settings/application` templates, covered by `qa/run.sh`):
a project initialized before it keeps the old `application/` copies until it is upgraded (G39).

## Requests, API and security

**G1. JSON/API calls need `Accept: application/json` + `Authorization: Bearer <API_KEY>`; form posts need `_token`.**
`Request::wantsJson()` is true only when `Accept` contains `application/json` and NOT `text/html`.
- When `wantsJson()` is true, `Route::execute()` requires `Auth::activateAPI()` in web.php and a valid Bearer API key for controller routes, else `[ERROR 1004] Access Denied`. CSRF is skipped.
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

**G4. Unknown URLs do not 404: they run the FIRST route declared.** `Route::evaluate()` falls back to route index 0 when nothing matches (also for a wrong HTTP method). Keep `Route::get('/', ...)` as the first route and do not rely on 404s. Proof: `Route.php` (`evaluate`).

**G5. `redirect()` does not stop the script.** It only sends a `Location` header. Write `redirect('/x'); exit;` (or `return`) when code follows. `logout()` and `guard()` do exit. Proof: `corehelpers.php` (`redirect`), `Auth.php`.

**G27. Client app pages expose `API_KEY` to the browser.** `views/Client/app.pug` injects `apiKey => API_KEY` into the JS `env`, so anyone who can load a client-app page can call the JSON API. Every API key is `encryptDecrypt('encrypt', APP_KEY, APP_KEY)`: all keys generated from one APP_KEY are equivalent and can only be revoked together by changing `APP_KEY`. Protect sensitive data routes with `authorized()`/`guard()` too. Proof: `views/Client/app.pug`, `Request::authorizeApiRequest`, `Caligrafer::generateKeys`.

**G28. Closure routes skip the API-key check.** Only `'Controller@action'` routes enforce `Auth::activateAPI()` + Bearer key; closures run for any JSON request. Proof: `Route.php` (`execute`).

**G29. PUT with multipart/form-data does not populate `$_POST`.** For file uploads send a POST with `_method=PUT`; otherwise send JSON with the JSON Accept header.

**G34. Server files and uploads.** The root `.htaccess` denies (403) existing dotfiles (`.env`, `.git`, `.claude`), the folders `application|framework|vendor|tests|qa|docker|claude-plugin|archives|node_modules`, `caligrafer.php`, `composer.*`, `docker-compose.yaml` and `.sql|.log|.bak|.ini|.sh|.env` files; non-existing paths still reach the router. `public/uploads/.htaccess` denies scripts. `uploadFile($file, $allowedExtensions = null)` only accepts `DEFAULT_UPLOAD_EXTENSIONS` (or the given list) and always refuses php-like, html, js and svg files; `isAllowedUpload($name, $list)` checks a name. nginx ignores `.htaccess`: copy the rules from the wiki (1. Getting Started > Protecting your server files). Verify with `curl -I <host>/.env` (403/404). **Fixed** (before: `.env` was downloadable and an uploaded `.php` ran). Proof: `.htaccess`, `public/uploads/.htaccess`, `corehelpers.php` (`uploadFile`, `isAllowedUpload`).

**G35. Client app template.** `views/Client/app.pug` only includes `public/<appName>/<app>.php` when both names match `[A-Za-z0-9_-]+` and the real path stays in the app folder, and escapes `$env` for the JS template literal (JSON_HEX_TAG + `\`, backtick, `${`). **Fixed** (before: `?app=../../x` included any PHP file and `?x=${...}` executed script). Projects initialized earlier must copy the new `app.pug` and `ClientController.php` (G39). Proof: `views/Client/app.pug`, `controllers/core/AI/ClientController.php`.

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

**G7. Saved values.** **Fixed:** `create()`/`update()`/`save()` send every non-null value (`0`, `''`, `false` -> `0` are saved; `null` is skipped so DB defaults/current values apply); `update()` with nothing left to save no longer emits invalid SQL. Older framework versions dropped all falsy values. Proof: `Model.php` (`prepareInputs`).

**G8. `all($args)` clauses are raw SQL.** `all(['condition' => 'WHERE active = ?', 'order' => 'ORDER BY created_at DESC', 'limit' => 'LIMIT 10', 'bindings' => [1]])` - each clause is appended verbatim (include the keywords); never interpolate user input, use `?` + `'bindings'` (bindings are new in the security release). Proof: `Model.php` (`all`).

**G9. `belongsTo()` is not an inverse relationship.** It calls `hasMany()` with the same defaults (`SELECT * FROM joined WHERE <thisclass>_id = this.id`) and returns an array. For "this row has a FK to a parent" use `hasOne('Parent', 'parents')` (FK `parent_id` on this table). Proof: `Model.php`.

**G10. FK names and types** *(MySQL rule)*. FK columns must match the referenced column's type/signedness and constraint names must be unique per database: name them `fk_<table>_<column>`. **Fixed for auto-created pivots:** `belongsToMany()` copies the referenced id types (`SHOW COLUMNS`) and names its constraints `fk_<pivot>_<column>` (before: signed `INT` + `fk1`/`fk2`, which failed with `INT UNSIGNED` ids and on the second pivot). Pivot name: `<table1>_<table2>` unless `<table2>_<table1>` exists. Call the relationship before `attach('<joined table>', $id)`/`detach(...)`; both return the refreshed list. Proof: `Model.php` (`belongsToMany`, `columnType`).

**G23. `Controller::save($inputs)` updates when the route has `{id}`.** `save()`, `find()` and `delete()` default the id to `$this->request->fetch('id')`. On a route like `POST /books/{id}/reviews`, `$this->save($review)` UPDATES review `{id}` instead of creating one. Name the route variable differently (`{book_id}`) or pass the id explicitly / use `$this->model->create($inputs)`. Proof: `Controller.php`.

**G24. `Database::execute()` returns arrays of associative arrays** (it re-encodes rows), not objects. Use `arrayToModel()` to get models. Models use `public` properties for every column; ids are strings when they come from PDO.

**G19. Global class namespace, everything is required.** The autoloader `require_once`s every `.php` file in `application/` and `tests/` (depth <= 5) the first time any unknown class is used. Classes are in the global namespace: two files declaring the same class name is a fatal "Cannot declare class" error. Use distinct names (`BookController`, `Book`, `BookService`) and keep scratch PHP out of `application/` and `tests/` (the QA suite lives in `qa/`, which is not autoloaded). Proof: `Framework.php` (`load`, `_require_all`).

## Environment and Docker

**G11. `.env` overrides docker-compose environment.** `index.php` and `caligrafer.php` use `Dotenv::createUnsafeMutable()`, which overwrites existing variables. Under Docker set in `.env`: `DB_HOST=mysql`, `APP_ROOT=` (empty), `DB_ACTIVATE=true`. Wrong `APP_ROOT` makes `session('home')`, `redirect()` and asset URLs point to `http://localhost:8080/<folder>/...`.

**G12. Docker ports 8080 / 8077 / 3306 clash** with other Quill projects or a local MySQL/MAMP. Check with `lsof -nP -iTCP:<port> -sTCP:LISTEN` or `docker ps` and remap the host side (e.g. `8081:80`, `8078:80`, `3307:3306`). The MySQL container has no named volume: after `docker-compose down` the recreated container starts with an empty database (`stop` keeps it). The QA stack uses 8090/3316.

**G20. Every `.env` key becomes a PHP constant, as a string.** A key that classes read (`OPEN_AI_KEY`, `GROQ_API_KEY`, `WATSON_API_KEY`, `MAIL_HOST`, `API_KEY`, `APP_KEY`, `APPROVED_AGENTS`, ...) must exist in `.env` or PHP throws "Undefined constant". Values are strings: test booleans with `filter_var(X, FILTER_VALIDATE_BOOLEAN)` (**Fixed** in `Mail`: `APP_DEBUG=false` used to enable SMTP debug output). Use `defined('X') && X !== ''` for optional settings. Proof: `Framework::init()` (`loadConstantFromArray($_ENV)`).

**G17. The Groq key is `GROQ_API_KEY`** (`Agent::__construct()`, `.env.example`). The wiki said `GROQ_AI_KEY` (corrected).

**G25. `caligrafer initialize` side effects.** It keeps an existing `APP_KEY`/`API_KEY` (**Fixed**: it used to prepend new keys on every run, invalidating API keys), adds `APP_ROOT` only if missing, copies `framework/settings/application` to `application/` if absent, `chmod -R 777 public/uploads`, and **deletes `.git`**. `.bin/caligrafer server start` does nothing when a local `httpd` (MAMP/LAMP) is running.

**G39. Upgrading a project.** New projects initialized with this release already have the current templates. For older projects, updating the framework does not update `application/`: the auth module, `views/Client/app.pug`, `ClientController`, `AIController` and the auth views are project copies. Compare them with `framework/settings/application`, copy the root and `public/uploads` `.htaccess` files, run `ALTER TABLE users MODIFY passcode varchar(255) NOT NULL;` and promote admins (`UPDATE users SET permissions = 1 WHERE ...`). Wiki page 9.5 Security and Upgrading lists every change.

## Views and Vue client apps

**G15. Page routes for client apps (Vue-as-library, bot, facedetect, ml).** `ClientController@index` renders `views/Client/app.pug`, which includes `public/<appName>/<app>.php`:
- the route path must END with the app folder name (`Route::get('/apps/{appName}', 'ClientController')` or `Route::get('/faceauth', ...)` for a fixed folder);
- view vars `appName` (folder in `public/`) and `app` (markup file without `.php`, default `index`, overridable with `?app=`) select the file; both must match `[A-Za-z0-9_-]+` (G35);
- every other view var, plus `app_root`, `service_root`, `app_resources`, `request`, `apiKey`, `token`, `home`, `keywords`, `metadata`, `copyright`, is JSON-encoded into `$env`, which the markup passes to `loadEnvironment(\`<?php echo $env; ?>\`)`; JS then reads `env.home`, `env.token`, `env.appName`, global `apiKey`;
- `loadVue({scripts: ['main']})` loads `public/<appName>/scripts/main.js`.
A custom controller can do the same: `return view('Client/app', ['appName' => 'faceauth', 'app' => 'index', 'user' => $data]);`.

**G16. Wrap FaceDetector in `Vue.markRaw`.** `const detector = Vue.markRaw(new FaceDetector('detection'));` - otherwise Vue 3 proxies the detector, its canvases/streams and face-api objects. **Fixed** in the `facedetect` scaffold.

**G21. `view()` and `api()` end the request.** For JSON requests (and always for `api()` / `view(null, ...)`), the View echoes JSON and calls `exit`. Set status codes first: `http_response_code(422); return api([...]);` (axios then rejects: payload in `error.response.data`).

**G22. Vue SPA template (`caligrafer create`).** **Fixed:** `src/common/app.js` uses `import axios from 'axios'` and `import.meta.env.VITE_API_KEY` (it had Vue-CLI `require()`/`process.env.VUE_APP_*`). In Vite always use `import.meta.env.VITE_*`.

**G26. Session setters and falsy values.** **Fixed:** `session('x', 0|false|'')`, `scripts()`, `keywords('')`, `copyright('')`, `metadata(['k' => ''])` store the value (only `null` means "read"). Use `clearFromSession('x')` to remove. Older versions ignored falsy values.

**G30. String helpers.** `startWith()`/`endWith()` return booleans (the wiki used to say they strip the needle); **Fixed:** `endWith()` compares the whole needle (it compared only the last character). `singularize()` just drops the last character.

**G36. The Watson bot client is Vue 2.** BotUI is a Vue 2 plugin: the `bot` scaffold pins `vue@2.7.16` and uses `new Vue({...})`. All other scaffolds use Vue 3 (`Vue.createApp`, `app.component`, `emits`).

## Auth module

**G13. `Auth/create_db.sql`.** **Fixed:** `CREATE TABLE IF NOT EXISTS users` (no `DROP`), `passcode varchar(255)`, unique `username`. Scripts from older projects start with `DROP TABLE IF EXISTS users;` - never re-run those on a database with data.

**G14. Passwords.** **Fixed:** `AuthController` stores `User::hashPassword()` (`password_hash`), checks `verifyPassword()` and upgrades legacy `encryptDecrypt` passcodes on the next login (`needsRehash()`); passwords are 8-72 characters; usernames are looked up exactly. `AdminController::ADMIN_PERMISSION = 1`: new users (permissions 0) cannot manage users; promote admins in SQL. Keep `APP_KEY` unchanged until legacy users have logged in once. `Auth::authorize($user)` requires an instance of the model given to `Auth::authentication()`; after changing a user's row in the DB the session no longer matches and the user must log in again.

## AI modules

**G31. OpenAI defaults.** `new OpenAI()` uses `GPT3` (gpt-3.5-turbo) unless you pass `['model' => '...']` as `$parameters`. **Fixed:** `GPT4OMINI` / `ASSISTANT_GPT4OMINI` = `'gpt-4o-mini'`, `GPT4_32` alias; `COMPLETE` sends the text after `[insert]`. `CUSTOM` is an empty system prompt: put yours first in `$context`. Resolve user input with `OpenAI::command($name)` / `OpenAI::type($name)` (allow-lists returning null), never `constant($input)` (**Fixed** in the sample `AIController`, which let a form select `APP_KEY`/`DB_PASSWORD`). `httpRequest()` sends the JSON body for every method except GET (**Fixed**: it was POST only).

**G32. Assistants.** `new Assistant([...])` without `assistant_id` creates a new OpenAI assistant every call (cost + clutter): persist the returned `assistant_id`. **Fixed:** `run()` only sends the overrides given (it always sent `model: ""` and `tools: []`, disabling `file_search`) and `retrieveResponse($timeout = 120, $interval = 2)` stops polling after the timeout. The file method is `deleteAssistantFile($file_id)`.

**G33. Agent (Groq) default models may be retired** (`llama-3.2-11b-vision-preview`). Pass a current model id. `delegate()` arguments are positional/named: `delegate(model: 'llama-3.3-70b-versatile', messages: [['role' => 'user', 'content' => 'Hi']])`; `transcribe()`/`translate()` return a JSON string.

## Payments

**G37. Stripe.** **Fixed:** `createCheckout(..., $customerEmail, $locale)` crashed (undefined `$input`); it now merges both. Amounts are in cents; methods return `['action_success' => bool, 'data' | 'error' => ...]`. `createTransaction()` sends raw card data (Stripe restricts it): prefer `createPaymentIntent()` or `createCheckout()`.

## Testing the framework

**G38. QA suite.** `./qa/run.sh` (Docker, PHP + curl, Node, `vendor/`) builds an isolated stack (compose project `quillqa`, ports 8090/3316) and runs framework (`qa/tests/cli_tests.php`), HTTP (`qa/tests/http_tests.php`) and tooling (`qa/tests/tooling_tests.sh`) tests, then removes everything (`--keep` to inspect). It refuses to run when a non-QA `application/` or `.env` exists. `qa/fixtures/docs/components.php` is a browser page of the wiki Vue samples (`/apps/qaapp?app=components` with `--keep`).

## Diagnosing errors quickly
- `[ERROR 1001]` controller/action not found: check spelling, that the file is under `application/`, no duplicate class names.
- `[ERROR 1004]` JSON call refused: `Auth::activateAPI()` present? Bearer key generated from the current `APP_KEY`?
- `[ERROR 3000]` CSRF: missing `_token`, or an API call without `Accept: application/json`.
- `[ERROR 2001]` invalid column name passed to `search()` (or a Model write with a non-identifier key).
- `[ERROR 2003]` DB: `DB_ACTIVATE=true`? `DB_HOST` (`mysql` in Docker)? table exists? SQL valid?
- Blank page / home page instead of your route: route not matched (G4) - check segment count and HTTP method.
- `403 Forbidden` on a file: it is in a denied folder or has a denied extension (G34) - move public assets to `public/`.
