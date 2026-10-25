# Caligrafy Quill gotchas and wiki errata (verified against the source)

Each item names the file that proves it. "Source" = caligrafy-quill master. Items marked *(MySQL rule)* follow
from the SQL the framework generates plus documented MySQL behavior and were not executed against a database.

## Requests, API and security

**G1. JSON/API calls need `Accept: application/json` + `Authorization: Bearer <API_KEY>`; form posts need `_token`.**
`Request::wantsJson()` is true only when `Accept` contains `application/json` and NOT `text/html`.
- When `wantsJson()` is true, `Route::execute()` requires `Auth::activateAPI()` in web.php and a valid Bearer API key for controller routes, else `[ERROR 1004] Access Denied`. CSRF is skipped.
- When it is false, every POST/PUT/DELETE must carry `_token` (= `session('token')`) in `$_POST`, else `[ERROR 3000]`. Use `=csrf()` in pug forms, `formData.append('_token', env.token)` in JS.
- `axios` sends `Accept: application/json, text/plain, */*` by default (counts as JSON); `fetch()` sends `*/*` (does NOT). Always set `Accept: application/json` explicitly for API calls.
- The token is read from `$_POST` only. A JSON body (`Content-Type: application/json`) without the JSON Accept header always fails CSRF.
- Proof: `framework/core/Request.php` (`wantsJson`, `checkCSRF`, `authorizeApiRequest`), `framework/core/Route.php` (`execute`).

**G2. `Model::search()` / `Controller::search()` inline the keywords into SQL.**
`search()` builds `` `col` LIKE '<keywords>' `` by string concatenation: a quote in user input breaks the query and allows SQL injection (the default `AuthController::login/register` pass the raw username to it). Write a model method with a bound query instead:
```php
public function findByUsername($username) {
    $rows = Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE username = ? LIMIT 1', [$username]));
    return !empty($rows[0]) ? $this->arrayToModel($rows[0]) : null;
}
// LIKE search: Database::execute("SELECT * FROM {$this->table} WHERE title LIKE ?", ['%' . $term . '%'])
```
Proof: `framework/core/Model.php` (`search`).

**G3. `DB_PORT` is ignored.** `Database::connect()` builds `mysql:host=DB_HOST;dbname=DB_DATABASE` without a port. To reach MySQL on a non-default port from the host, use `DB_HOST="127.0.0.1;port=3307"` (quoted; the value is spliced into the DSN). Inside Docker use `DB_HOST=mysql` (container port 3306). Proof: `framework/database/Database.php`.

**G4. Unknown URLs do not 404: they run the FIRST route declared.** `Route::evaluate()` falls back to route index 0 when nothing matches (also for a wrong HTTP method). The root URL `/` is always served by index 0. Keep `Route::get('/', ...)` as the first route and do not rely on 404s. Proof: `Route.php` (`evaluate`).

**G5. `redirect()` does not stop the script.** It only sends a `Location` header. Write `redirect('/x'); exit;` (or `return`) when code follows. `logout()` and `guard()` do exit. Proof: `corehelpers.php` (`redirect`), `Auth.php`.

**G27. Client app pages expose `API_KEY` to the browser.** `views/Client/app.pug` injects `apiKey => API_KEY` into the JS `env`, so anyone who can load a client-app page can call the JSON API. Protect sensitive data routes with `authorized()`/`guard()` too, not only with the API key. Proof: `framework/settings/application/views/Client/app.pug`.

**G28. Closure routes skip the API-key check.** Only `'Controller@action'` routes enforce `Auth::activateAPI()` + Bearer key; closures run for any JSON request. Proof: `Route.php` (`execute`).

**G29. PUT with multipart/form-data does not populate `$_POST`.** For file uploads send a POST with `_method=PUT`; otherwise send JSON with the JSON Accept header. (The wiki's Postman notes say the same.)

## Validation

**G6. `Controller::check()` returns an array on success too.** With rules AND filters it returns GUMP `run()` output: the filtered data array on success, the error array on failure, so `if ($result !== true)` treats success as an error. Prefer:
```php
$data = $this->filter($input, ['email' => 'trim|sanitize_email|lower_case']);
$valid = $this->validate($data, ['email' => 'required|valid_email']);
if ($valid !== true) { http_response_code(422); return api(['errors' => $valid]); }
```
`check()` with rules only returns `true` or `['field' => 'message']`. Proof: `framework/core/Validator.php`, GUMP `run()`.

**G18. The wiki's `$this->validator->is_value(...)` does not exist.** The GUMP method is static `GUMP::is_valid(array $data, array $validators, array $fields_error_messages = [])` (callable as `$this->validator->is_valid(...)`), and it returns `true` or *readable HTML* error strings. Wiki 9.7.5 also uses `$this->validator->check(...)` - that works (it is `Validator::check`) - but its example has the typo `'image_url' = '...'`.

## Models and database

**G7. `create()`/`update()`/`save()` silently drop falsy values.** Inputs are filtered with `$value != null`, so `null`, `0`, `false` and `''` never reach the database: you cannot reset a column to 0/empty through the Model. Use `Database::execute('UPDATE t SET flag = ? WHERE id = ?', [0, $id])`. Proof: `Model.php` (`create`, `update`).

**G8. `all($args)` is raw SQL.** `all(['condition' => 'WHERE active = 1', 'order' => 'ORDER BY created_at DESC', 'limit' => 'LIMIT 10'])` - each value is appended verbatim (include the keywords) and never bound: never interpolate user input. Proof: `Model.php` (`all`); `AdminController` uses `['order' => 'order by modified_at DESC']`.

**G9. `belongsTo()` is not an inverse relationship.** It calls `hasMany()` with the same defaults (`SELECT * FROM joined WHERE <thisclass>_id = this.id`) and returns an array. For "this row has a FK to a parent" use `hasOne('Parent', 'parents')` (FK `parent_id` on this table), as the wiki examples actually do. Proof: `Model.php`.

**G10. `belongsToMany()` pivot creation can fail.** *(MySQL rule)* The auto-created pivot uses `INT(25) NOT NULL` (signed) FK columns and constraint names `fk1`/`fk2`. MySQL requires FK columns to match the parent column's signedness (so `INT UNSIGNED` primary keys make the CREATE fail) and FK constraint names are unique per database (a second pivot, or the wiki's own `CONSTRAINT fk1` on `books`, collides). Either use signed `INT` ids, or create the pivot yourself first (`CREATE TABLE users_accounts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, account_id INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT now(), modified_at DATETIME NOT NULL DEFAULT now() ON UPDATE now(), CONSTRAINT fk_users_accounts_user ...)`) - `CREATE TABLE IF NOT EXISTS` then leaves it alone. Always give FKs unique names such as `fk_<table>_<column>`. Proof: `Model.php` (`belongsToMany`).

**G23. `Controller::save($inputs)` updates when the route has `{id}`.** `save()`, `find()` and `delete()` default the id to `$this->request->fetch('id')`. On a route like `POST /books/{id}/reviews`, `$this->save($review)` UPDATES review `{id}` instead of creating one. Name the route variable differently (`{book_id}`) or pass the id explicitly / use `$this->model->create($inputs)`. Proof: `Controller.php`.

**G24. `Database::execute()` returns arrays of associative arrays** (it re-encodes rows), not objects. Use `arrayToModel()` to get models. Models use `public` properties for every column; ids are strings when they come from PDO.

**G19. Global class namespace, everything is required.** The autoloader `require_once`s every `.php` file in `application/` and `tests/` (depth <= 5) the first time any unknown class is used. Classes are in the global namespace: two files declaring the same class name (e.g. a model `FaceAuth` and a service `FaceAuth`) is a fatal "Cannot declare class" error. Use distinct names (`BookController`, `Book`, `BookService`) and keep scratch PHP out of `application/` and `tests/`. Folders are free (`application/models/Shop/Book.php`). Proof: `Framework.php` (`load`, `_require_all`).

## Environment and Docker

**G11. `.env` overrides docker-compose environment.** `index.php` and `caligrafer.php` use `Dotenv::createUnsafeMutable()`, which overwrites existing variables. docker-compose's `DB_HOST: mysql` / `APP_ROOT: ""` are replaced by `.env` values, so under Docker set in `.env`: `DB_HOST=mysql`, `APP_ROOT=` (empty), `DB_ACTIVATE=true`. Wrong `APP_ROOT` makes `session('home')`, `redirect()` and asset URLs point to `http://localhost:8080/<folder>/...`.

**G12. Docker ports 8080 / 8077 / 3306 clash** with other Quill projects or a local MySQL/MAMP. `docker-compose.yaml` hard-codes `8080:80` (dev-box and prod-box), `8077:80` (phpmyadmin) and `3306:3306` (mysql). Check with `lsof -nP -iTCP:<port> -sTCP:LISTEN` or `docker ps` and remap the host side (e.g. `8081:80`, `8078:80`, `3307:3306`). The app inside Docker still uses `DB_HOST=mysql` (container port 3306). The MySQL container has no named volume: after `docker-compose down` the recreated container starts with an empty database (`stop` keeps it).

**G20. Every `.env` key becomes a PHP constant, as a string.** A key that classes read (`OPEN_AI_KEY`, `GROQ_API_KEY`, `WATSON_API_KEY`, `MAIL_HOST`, `API_KEY`, `APP_KEY`, ...) must exist in `.env` or PHP throws "Undefined constant". Values are strings: `APP_DEBUG=false` is truthy in `APP_DEBUG ? 1 : 0`. Use `defined('X') && X !== ''` for optional settings. Proof: `Framework::init()` (`loadConstantFromArray($_ENV)`).

**G17. The Groq key is `GROQ_API_KEY`.** The wiki (9.9.6) says `GROQ_AI_KEY`, but `Agent::__construct()` reads `GROQ_API_KEY` (and `.env.example` defines `GROQ_API_KEY`).

**G25. `caligrafer initialize` side effects.** It prepends a NEW `APP_KEY`/`API_KEY`/`APP_ROOT` block to `.env` every time it runs (re-running invalidates API keys you handed out) and deletes `.git`. It does not touch `.claude/`, `.claude-plugin/` or `claude-plugin/`. `.bin/caligrafer server start` does nothing when a local `httpd` (MAMP/LAMP) is running, because it only passes the Docker flag when no `httpd` process exists.

## Views and Vue client apps

**G15. Page routes for client apps (Vue-as-library, bot, facedetect, ml).** `ClientController@index` renders `views/Client/app.pug`, which includes `public/<appName>/<app>.php`:
- the route path must END with the app folder name (`Route::get('/apps/{appName}', 'ClientController')` or `Route::get('/faceauth', ...)` for a fixed folder);
- view vars `appName` (folder in `public/`) and `app` (markup file without `.php`, default `index`, overridable with `?app=`) select the file;
- every other view var, plus `app_root`, `service_root`, `app_resources`, `request`, `apiKey`, `token`, `home`, `keywords`, `metadata`, `copyright`, is JSON-encoded into `$env`, which the markup passes to `loadEnvironment(\`<?php echo $env; ?>\`)`; JS then reads `env.home`, `env.token`, `env.appName`, global `apiKey`;
- `loadVue({scripts: ['main']})` loads `public/<appName>/scripts/main.js`.
A custom controller can do the same: `return view('Client/app', ['appName' => 'faceauth', 'app' => 'index', 'user' => $data]);`.
Proof: `ClientController.php`, `views/Client/app.pug`, `public/js/services/app.js`.

**G16. Wrap FaceDetector in `Vue.markRaw`.** Putting `new FaceDetector('detection')` in Vue 3 `data()` makes Vue proxy it (and its canvases/streams/face-api objects), which breaks or slows detection. Use `const detector = Vue.markRaw(new FaceDetector('detection'));`. The scaffolded `framework/plugins/facedetect/scripts/main.js` does not do this yet.

**G21. `view()` and `api()` end the request.** For JSON requests (and always for `api()` / `view(null, ...)`), the View echoes JSON and calls `exit`. Set status codes first: `http_response_code(422); return api([...]);`.

**G22. Vue SPA template (`caligrafer create`) still has Vue-CLI code.** `framework/librairies/app/src/common/app.js` uses `require('axios')` and `process.env.VUE_APP_API_KEY`, but the project is Vite and `initialize` writes `VITE_APP_KEY`/`VITE_API_KEY`. In Vite use `import axios from 'axios'` and `import.meta.env.VITE_API_KEY`.

**G26. Session setters ignore falsy values.** `session('x', 0)`, `session('x', false)`, `metadata(['k' => ''])` do nothing. Use `$_SESSION['x'] = 0` or `clearFromSession('x')`.

**G30. `startWith()`/`endWith()` return booleans and `endWith()` only compares the last character** (wiki says they return the string without the needle). `singularize()` just drops the last character.

## Auth module

**G13. `application/models/Auth/create_db.sql` starts with `DROP TABLE IF EXISTS users;`.** Running it again wipes all users. Never run it on a database that already holds data.

**G14. Default auth stores passwords with reversible encryption** (`encryptDecrypt('encrypt', $passcode)`, AES with `APP_KEY`), validates them as `alpha_numeric|max_len,20`, and looks users up with `search()` (G2). For new code prefer `password_hash()`/`password_verify()` and a bound lookup. Changing `APP_KEY` makes every stored password undecryptable. `Auth::authorize($user)` requires `$user` to be an instance of the model given to `Auth::authentication()`.

## AI modules

**G31. OpenAI defaults are dated.** `new OpenAI()` uses `GPT3` (gpt-3.5-turbo) unless you pass `['model' => '...']` as `$parameters`; `GPT4OMINI` is defined as `'gpt-4o mini'` (invalid id; use `'gpt-4o-mini'`); `GPT4-32` is not a valid PHP constant name. `httpRequest()` only sends a body for POST.

**G32. `new Assistant([...])` without `assistant_id` creates a new OpenAI assistant every call** (cost + clutter). Persist the returned `assistant_id` and pass it next time. `retrieveResponse()` polls every 2 s with no timeout. The wiki's `deleteAssistantFiles` is `deleteAssistantFile($file_id)` in source.

**G33. Agent (Groq) default models may be retired** (`llama-3.2-11b-vision-preview`). Pass a current model id. `delegate()` arguments are positional/named: `delegate(model: 'llama-3.3-70b-versatile', messages: [['role' => 'user', 'content' => 'Hi']])`.

## Diagnosing errors quickly
- `[ERROR 1001]` controller/action not found: check spelling, that the file is under `application/`, no duplicate class names.
- `[ERROR 1004]` JSON call refused: `Auth::activateAPI()` present? Bearer key generated from the current `APP_KEY`?
- `[ERROR 3000]` CSRF: missing `_token`, or an API call without `Accept: application/json`.
- `[ERROR 2003]` DB: `DB_ACTIVATE=true`? `DB_HOST` (`mysql` in Docker)? table exists? SQL valid?
- Blank page / home page instead of your route: route not matched (G4) - check segment count and HTTP method.
