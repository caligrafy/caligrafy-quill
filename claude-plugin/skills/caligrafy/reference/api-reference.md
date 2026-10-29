# Caligrafy Quill API reference (verified against the framework source)

> Every signature below was copied from `framework/core/*.php`, `framework/helpers/corehelpers.php` and
> `framework/database/Database.php` of caligrafy-quill (master). When the wiki and the source disagree, the
> source wins and the difference is listed in [gotchas.md](gotchas.md).
> Everything lives in the `Caligrafy` namespace except the global helper functions and your own
> application classes (which are in the global namespace).

## Bootstrap and request lifecycle

1. `index.php` sets `error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED)`, loads `vendor/autoload.php`, then `Dotenv\Dotenv::createUnsafeMutable(__DIR__)->load()` (the `.env` file **overrides** variables already in the environment, e.g. docker-compose `environment:`).
2. `Framework::run()`:
   - `framework/helpers/initialize.php`: sets timezone (`SERVER_TIMEZONE`), `session_start()`, creates `$_SESSION['token']` (CSRF token), defines path constants and the session URLs (`base`, `home`, `public`, `uploadsUrl`, `jsUrl`, `cssUrl`, `imagesUrl`, `appClient`, `appService`, `appResources`) plus constants `APP_CLIENT`, `APP_SERVICE_ROOT`, `APP_RESOURCES`.
   - loads `corehelpers.php`, then `loadConstantFromArray($_ENV)`: **every `.env` key becomes a PHP constant** (string values).
   - `framework/settings/coresettings.php`: `ROUTE_ARG_PATTERN` (`#\{+(.*?)\}+#`), default `scripts()`, `metadata()`, `keywords()`, `copyright()`.
   - registers an autoloader that, on the first unknown class, `require_once`s **every `.php` file** under `framework/core`, `framework/database`, `application/` and `tests/` (max depth 5).
   - `include application/config/config.php`, then `require_once application/config/routing/web.php`.
3. `web.php` declares routes then calls `Route::run()`, wrapped in `try { ... } catch (Exception $e) { Errors::handle($e); }` (older projects: `echo $e->getMessage();`, see Errors below).

Path constants: `ROOT` (cwd), `APP_PATH` (application/), `TEST_PATH`, `FRAMEWORK_PATH`, `PUBLIC_PATH`, `CONFIG_PATH`, `ROUTING_PATH`, `CONTROLLER_PATH`, `MODEL_PATH`, `VIEW_PATH` (application/views/), `CORE_PATH`, `DB_PATH`, `LIB_PATH` (framework/librairies/), `HELPER_PATH`, `UPLOAD_PATH` (public/uploads/), `FONTS_PATH`, `DS`.

URL building: `session('home')` = `APP_PROTOCOL://SERVER_NAME[:port]/` + `APP_ROOT/` (when `APP_ROOT` is non-empty). `session('public')` = home + `public/`.

## Route (`Caligrafy\Route`)

```php
Route::get(string $route, string|callable $method = null)
Route::post(string $route, string|callable $method = null)
Route::put(string $route, string|callable $method = null)
Route::delete(string $route, string|callable $method = null)
Route::run()                       // MUST be the last call in web.php
Route::$currentRoute               // matched route pattern, e.g. "books/{id}"
Route::$currentBind                // ['id' => '12', ...] (+ query string params for GET routes)
```
- `$method` is `'Controller'` (runs `index`), `'Controller@action'`, or a closure. The controller may be fully qualified (`'Shop\OrderController@show'`, leading `\` allowed). `Route::resolveController($name)`: a declared class wins; else a short name falls back to `Route::EXAMPLE_CONTROLLERS_NAMESPACE` (`Caligrafy\Controllers`, the example controllers of `controllers/core/`); else `null` -> 1001 (G47).
- Leading/trailing slashes are stripped; a route matches only if it has the **same number of segments** and the **same HTTP method** as the request; `{name}` segments capture values.
- No match (or wrong method) falls back to the **first route declared** (keep `Route::get('/', ...)` first).
- For controller routes: missing class/method throws `[ERROR 1001]` (404); a JSON request (`wantsJson()`) without `Auth::activateAPI()` or without a valid Bearer API key throws `[ERROR 1004] Access Denied` (401). Closure routes skip the API-key check.
- Method spoofing: a `_method` field (`PUT`/`DELETE`) in `$_REQUEST` overrides the HTTP method.

## Auth (`Caligrafy\Auth`)

```php
Auth::activateAPI()                                   // allow JSON/API access (Bearer API_KEY) to controller routes
Auth::authentication(string $modelname, string $tablename)   // e.g. Auth::authentication('User', 'users')
Auth::authorize($user): bool                          // $user must be an instance of the auth model; stores encrypted user in session '__authorization'
Auth::unauthorize()
Auth::authorized(): bool                              // re-reads the user by id from the DB and compares with the session copy
Auth::guard($permissionAttribute, $acceptedPermission, $loginUrl = "/login", $notAuthorized = '/notAuthorized')
Auth::logout($redirectUrl = '/')                      // unauthorize + session_destroy + redirect + exit
Auth::user(): ?Model                                  // user model rebuilt from the session (not re-read from DB)
Auth::api(): bool
```
- `guard()` redirects (and exits) to `$loginUrl` when not logged in, to `$notAuthorized` when `$user->$permissionAttribute < $acceptedPermission`.
- `Auth::authentication()` must run in `web.php` before any `authorize/authorized/user/guard` call.
- API key check (`Request::authorizeApiRequest`): header `Authorization: Bearer <API_KEY>`; valid when `encryptDecrypt('decrypt', <API_KEY>) == APP_KEY`. So any key produced by `caligrafer generateapikey` for the current `APP_KEY` works.

## Request (`Caligrafy\Request`)

Created fresh by `request()` and in every controller (`$this->request`).

Public properties:
```php
$method          // HTTP method, after _method spoofing (upper case)
$uri             // path without leading/trailing slash ('/' for root); from ?uri= set by .htaccess
$fullUri         // uri + '?' + query string of the parameters
$uriComponents   // explode('/', $uri)
$uriParameters   // request body/query parameters (see parameters())
$parameters      // = $uriParameters, or the route bind when there are none
$fetch           // (object) route bind: route {vars} + (GET routes) query string params -> $this->request->fetch->id
$currentRoute, $currentBind
$host, $authorization, $cache, $userAgent, $accept, $acceptEncoding, $acceptLanguage, $cookie, $parsedURI
```
Methods:
```php
fetch($argument)            // by name: $this->request->fetch->$argument ?? ''; by int: nth bound value (1-based)
parameters(): array         // GET: $_GET; POST/PUT/DELETE: $_POST if non-empty else json_decode(php://input) (also runs the CSRF check)
files($fileAttribute): array  // $_FILES[$fileAttribute] ?? []
wantsJson(): bool           // true when Accept contains application/json AND not text/html
authorizeApiRequest(): bool
```
CSRF (`checkCSRF`, run for POST/PUT/DELETE whenever a Request is built): skipped when `wantsJson()` is true or the User-Agent is listed in `APPROVED_AGENTS` (comma separated; entries are trimmed and empty entries ignored). Otherwise `$_POST['_token']` must equal `$_SESSION['token']`, else `[ERROR 3000] Security Error - Cross Site Forgery`. The token is read from `$_POST` only (form-encoded or multipart), never from a JSON body.

## Controller (`Caligrafy\Controller`)

```php
class BookController extends Controller { public function index() { ... } }   // global or namespaced (namespace Shop; -> 'Shop\BookController@index'), use Caligrafy\Controller;

protected $loader;      // Caligrafy\Loader: ->library($lib) includes LIB_PATH/$lib.php, ->helper($h) includes HELPER_PATH/$h.php
protected $request;     // Caligrafy\Request
protected $model;       // Caligrafy\Model (set by associate)
protected $connection;  // PDO or null (connect())
protected $validator;   // Caligrafy\Validator (extends GUMP)
protected $payment;     // Caligrafy\Payment after activatePayment(), else null

associate($modelname, $table): Controller   // $modelname: class name string or Model instance; swaps $this->model (keeps request/validator, reconnects DB)
activatePayment(): Controller              // $this->payment = new Payment()
index()                                    // default action
all($args = array())                        // -> $this->model->all($args)
find($id = null)                            // id defaults to $this->request->fetch('id') (route {id})
save($inputs, $id = null)                   // id defaults to route {id}: with an id -> model->update($inputs, $id), else model->create($inputs); returns the saved Model (re-read)
delete($id = null)                          // id defaults to route {id}
search($scope = null, $keywords = null)     // defaults from request fetch->scope / fetch->keywords -> model->search()
table(): string
check($data, $rules = array(), $filters = array())   // see Validator::check
validate($data, $rules)                     // true, or ['field' => 'message', ...]
filter($data, $filters)                     // filtered array
view($viewName, $viewData = array())        // -> view()
api($viewData = array())                    // -> api()
```
Do not override these method names in your controllers.

## Model (`Caligrafy\Model`)

```php
use Caligrafy\Model;
class Book extends Model {
    public $id; public $title; public $author; public $publisher_id; public $created_at;   // one public property per column
}

public $table;
protected $__restricted;            // ['table', '__restricted', 'pivot'] + relationship method names; never written to the DB

__construct($table = null)
associate($table): Model
create($inputs): ?Model             // array or object; skips null values and restricted keys only (0, '', false -> 0 are saved); column names validated; INSERT with bound values; returns find(lastInsertId)
update($inputs, $id): ?Model        // same preparation; UPDATE ... WHERE id = ? (bound), skipped when the record does not exist or nothing is left to save; returns find($id)
delete($id)                         // DELETE ... WHERE id = ? (bound)
find($id): ?Model                   // SELECT * ... WHERE id = ? (bound)
all($args = array()): Model[]       // 'SELECT * FROM table' . ' '.$args['condition'].' ' . ' '.$args['order'].' ' . ' '.$args['limit'].' '  (raw clauses) executed with $args['bindings'] (array) for ? placeholders
search($scope, $keywords): Model[]  // $scope = 'col1,col2' (each must match [A-Za-z0-9_], else Exception 2001); WHERE `col1` LIKE ? OR `col2` LIKE ?  (keywords bound; % and _ are wildcards)
save()                              // $this->id ? update($this, $this->id) : create($this)
hasOne($modelName, $joinedTableName, $foreignKey = null, $localKey = null, $functionName = null): ?Model
hasMany($modelName, $joinedTableName, $foreignKey = null, $localKey = null, $functionName = null): Model[]   // ids bound
belongsTo($modelName, $joinedTableName, $foreignKey = null, $localKey = null): Model[]   // calls hasMany()
belongsToMany($modelName, $joinedTableName, $foreignKeyLocal = null, $foreignKeyJoin = null, $functionName = null): Model[]
attach($joinedTableName, $id)       // many-to-many: add pivot row (call the relationship method first), returns the relationship method's result
detach($joinedTableName, $id)
arrayToModel($array): Model         // new static($this->table) hydrated with the array
toArray(): array
tableExists($tablename): bool
// protected helpers: prepareInputs($inputs) (non-null values of real columns), tableColumns(), quoteIdentifier($name), columnType($table, $column), constraintName($pivot, $column)
```
Relationship key defaults (from source):
- `hasOne('Account','accounts')` on `User` (table `users`): `SELECT accounts.* FROM users LEFT JOIN accounts ON accounts.id = users.account_id WHERE users.id = <this id>` (FK `<lowercase model>_id` lives on **this** table; `$foreignKey` overrides that column, `$localKey` overrides `accounts.id`).
- `hasMany('Account','accounts')` on `User`: `SELECT * FROM accounts WHERE user_id = <this id>` (FK `<lowercase this class>_id` on the **joined** table; `$foreignKey` overrides it; `$localKey` names a property of this model to use instead of `id`).
- `belongsToMany('Account','accounts')` on `User`: pivot table `users_accounts` (or the existing `accounts_users`), columns `user_id`, `account_id` typed like the referenced ids (`columnType()` reads `SHOW COLUMNS`), constraints `fk_<pivot>_<column>`, auto-created with `CREATE TABLE IF NOT EXISTS`; also sets `$this->pivot->accounts` (`->all` holds the joined rows).
- Relationship methods must be called on a loaded model (`$user = $this->find(1); $user->accounts();`). The calling method name is pushed into `__restricted` so it is not saved as a column.

## Database (`Caligrafy\Database`)

```php
Database::connect(): ?PDO                 // only when DB_ACTIVATE == 'true'; DSN from Database::dsn()
Database::dsn($adapter, $host, $dbName, $port = null): string   // "mysql:host=H;port=P;dbname=D"; port omitted when empty or when $host already contains "port="
Database::execute($query, $args = array()): array   // prepare + execute with bound $args; returns array of associative arrays
Database::toArray($result): array
Database::lastInsertId()
Database::is_Json($string): bool
```
- PDO uses native prepares (`ATTR_EMULATE_PREPARES` false). Without an active connection `execute()` throws `[ERROR 2003]`.
- Safe custom query in a model method:
```php
use Caligrafy\Database;
public function findByEmail($email) {
    $rows = Database::toArray(Database::execute('SELECT * FROM ' . $this->table . ' WHERE email = ? LIMIT 1', [$email]));
    return !empty($rows[0]) ? $this->arrayToModel($rows[0]) : null;
}
```

## Validator (`Caligrafy\Validator extends \GUMP`)

```php
check($data, $rules = array(), $filters = array())
//  rules + filters: GUMP::run() -> the FILTERED DATA ARRAY on success, the errors array on failure (both arrays!)
//  rules only:      true, or ['field' => 'message']
//  filters only:    filtered data array
//  neither:         true
// GUMP methods available on $this->validator:
validate(array $input, array $ruleset)         // true or raw error list (Controller::validate() converts to field => message)
filter(array $input, array $filterset)
run(array $data, $check_fields = false)
get_errors_array(); get_readable_errors(bool $convert_to_string = false, ...);
GUMP::is_valid(array $data, array $validators, array $fields_error_messages = [])   // static; true or readable (HTML) error strings
GUMP::filter_input(array $data, array $filters)
GUMP::set_field_name($field, $readable); GUMP::set_error_message($rule, $message);
```
Rule syntax: `'field' => 'required|max_len,100|valid_email'`, `'image' => 'required_file|extension,png;jpg'`. Filters: `'field' => 'trim|sanitize_string|lower_case'`.

## View (`Caligrafy\View`) and response helpers

```php
view($viewName, $viewData = array())   // renders application/views/<viewName>.pug with Phug::displayFile(); variables = keys of $viewData ($name in pug)
api($viewData = array())               // = new View(null, $viewData)
```
If `$viewName` is null **or** the request `wantsJson()`, the View echoes `json_encode($viewData, JSON_UNESCAPED_SLASHES)` and calls `exit`. A route therefore serves HTML to browsers and JSON to API clients with the same `view()` call. Set the HTTP status with `http_response_code($code)` before `api()`.

## Server protection (`.htaccess`)
Root `.htaccess` (mod_rewrite only, `AllowOverride FileInfo`): front controller (`index.php?uri=$1` for non-existing files) and 403 (case-insensitive) for existing dotfiles, `application|framework|vendor|tests|qa|docker|claude-plugin|archives|node_modules`, `caligrafer.php`, `composer.(json|lock)`, `docker-compose.y(a)ml`, `package(-lock).json`, `*.sql|log|bak|ini|sh|env`; `Service-Worker-Allowed: /` on `public/sw.js`; `AddType application/manifest+json .webmanifest` (mod_mime). `public/uploads/.htaccess`: mod_rewrite `[NC,F]` for script extensions.

## Auth module templates (`framework/settings/application`, copied to `application/` by initialize)
- `models/Auth/User.php`: `findByUsername($username)`, `User::hashPassword($password)`, `verifyPassword($password)` (hash, or legacy `encryptDecrypt` value), `needsRehash()`.
- The example controllers below are in `namespace Caligrafy\Controllers` (template routes use the full names; short names still resolve, G47).
- `controllers/core/Auth/AuthController.php`: `index, registerForm, home, register, login, logout, notAuthorized`; login upgrades legacy passcodes. Passwords 8-72 chars, alphanumeric unique usernames.
- `controllers/core/Auth/AdminController.php`: `ADMIN_PERMISSION = 1`; `index, editUserForm, updateUser, deleteUser` all `guard('permissions', 1)`.
- `models/Auth/create_db.sql`: `CREATE TABLE IF NOT EXISTS users` (passcode varchar(255), unique username).
- `controllers/core/AI/ClientController.php` + `views/Client/app.pug`: client apps (G15, G35). `controllers/core/AI/AIController.php`: OpenAI example using `OpenAI::type()`/`command()`.

## Front-end libraries (`scripts($key)`, pinned in `framework/settings/coresettings.php`)
`bootstrap_css` / `bootstrap_script` (Bootstrap 5.3.8 CSS / bundle), `vue` (Vue 3.5.44 global prod build), `axios` (1.20.0), `jquery` and alias `bootstrap_jquery` (jQuery 4.0.0, only for Summernote), `wysiwyg_css` / `wysiwyg_js` (Summernote-lite 0.9.1), `css`, `animate`, `script`, `social_buttons_css`, `favicon` (no `add_to_home`/`add_to_home_css`: removed with add-to-homescreen.js). Override in `application/config/config.php`: `scripts('bootstrap_css', '<url>')`. Client scaffolds: app/facedetect (Vue 3 via `scripts('vue')`), ml (p5 2.3.4 + ml5 1.4.0), bot (Vue 3 chat, `public/css/bot.css`). Services: `face-api.min.js` (@vladmandic/face-api 1.7.15) + `detect.js` (`FaceDetector(id, {autoStart})`, `ready`, `startStream(constraints)` promise / `streamError`, `stopStream()`, `faceprint(media)` -> `{descriptor[128], detection}`, `resolveMedia()`: the scaffolds create the detector before Vue mounts, which replaces the page elements, so the media is looked up again on the page before streaming), `mlcore.js` (`MlCore` 2.0), `stripe_client.js` (Payment Element), `app.js` (`loadEnvironment`, `loadVue`).

## Install to home screen (web app manifest, `modules/install.pug`, `public/sw.js`)
- `public/manifest.webmanifest` (static JSON, served as `application/manifest+json` by the root `.htaccess` `AddType`): `name`/`short_name` `Caligrafy`, `display: standalone`, `theme_color`/`background_color` `#ffffff`, `id`/`start_url`/`scope` `../` (relative to the manifest = the application root for any `APP_ROOT`), icons relative to `public/`: `images/resources/icon-192x192.png`, `icon-512x512.png` (also `purpose: maskable`), `apple-touch-icon-180x180.png`. `layoutblocks.pug` headers: `meta(name='theme-color', content='#ffffff')`, `link(rel='manifest', href="{$home}public/manifest.webmanifest")`, plus `modules/touchicons` (57 to 180 px Apple touch icons).
- `default/modules/install.pug`, included by `layoutblocks` when the view data has `'install' => true` (`if $install`, after `modules/standalone` and `modules/serviceworker`): `div#caligrafy-install.caligrafy-install[hidden]` (fixed bottom card, styles scoped in the module), `#caligrafy-install-button.btn.btn-primary` shown on `beforeinstallprompt` (calls `prompt()`, `userChoice` `dismissed` is remembered), `#caligrafy-install-dismiss.btn.btn-secondary` (`Not now`/`Got it`), iOS/iPadOS Safari (`iPhone|iPad|iPod` or `MacIntel` + `maxTouchPoints > 1`, not CriOS/FxiOS/EdgiOS) shows `#caligrafy-install-ios` (Share, then Add to Home Screen). Hidden when standalone (`navigator.standalone`, `display-mode: standalone`) or `localStorage['caligrafy-install-dismissed'] === '1'`; `appinstalled` hides it.
- `default/modules/serviceworker.pug` (always included): when `PWA_SERVICE_WORKER` is true (`defined()` + `FILTER_VALIDATE_BOOLEAN`; `.env.example` has `false`) registers `{$home}public/sw.js` with scope `{$home}` on `load`; otherwise, if a worker controls the page, unregisters the registrations whose script is `{$home}public/sw.js`. The root `.htaccess` sets `Service-Worker-Allowed: /` for `public/sw.js` only (`RewriteRule ^public/sw\.js$ - [E=CALIGRAFY_SW:1]` + `Header set ... env=CALIGRAFY_SW`, FileInfo-compatible).
- `public/sw.js`: cache `caligrafy-offline-v1` holds only `public/offline.html` (self-contained page, no external files); `fetch` handles only `GET` + `mode === 'navigate'`: network, and the offline page on a network error. Everything else (API routes, POST/PUT/DELETE, assets) is not intercepted; nothing is ever cached at runtime. Bump `CACHE` after editing `offline.html`; `activate` deletes other `caligrafy-*` caches.
- Client apps outside the Pug layout link `session('public').'manifest.webmanifest'` (installs the whole app) or their own copy in `public/<app>/` with `start_url`/`scope`/`id` `./` and icons `../images/resources/...` (wiki 7 #install).

## Global helpers (`framework/helpers/corehelpers.php`)

```php
// keys & crypto
generateKey($size = 32)                       // base64 random bytes (APP_KEY format)
generateToken($size = 12)                     // hex string of 2*$size chars
encryptDecrypt($action, $data, $key = APP_KEY) // 'encrypt' | 'decrypt', AES-256-CBC
// session & globals (setters store any non-null value, including 0, '' and false; null = read)
isSessionExpired($time = SESSION_EXPIRATION_TIME)   // constant not defined by default; pass $time
saveInSession($input_data, $session_variable); clearFromSession($session_variable)
session($key, $value = null); scripts($key, $value = null); copyright($value = null); keywords($value = null)
metadata($pairs = array())                    // merges non-null pairs into $_SESSION['metadata'], returns it
terminateSession($url); getGlobalInformation($superGlobal = 'session')   // 'post' | 'get' | 'files' | default session
// views / requests / routing
view($viewName, $viewData = array()); api($viewData = array()); request(); connect(); currentRoute(); currentBind()
redirect($url)                                // header('Location: '.session('home').sanitizePath($url)); does NOT exit
methodField($method); csrf()                  // echo hidden inputs _method / _token (use as `=methodField('PUT')`, `=csrf()` in pug)
import($filename)                             // require_once LIB_PATH or HELPER_PATH file
// files
uploadFile($file, $allowedExtensions = null): string   // moves an uploaded $_FILES entry to public/uploads/<random>.<ext> when isAllowedUpload(); returns its public URL ('' when refused or on failure)
isAllowedUpload($filename, $allowedExtensions = null): bool  // extension in $allowedExtensions ?? DEFAULT_UPLOAD_EXTENSIONS and never php*/phtml/phar/cgi/pl/py/sh/asp/jsp/htaccess/ini/svg/html/js/exe/bat
DEFAULT_UPLOAD_EXTENSIONS                     // jpg jpeg png gif webp bmp ico heic pdf txt csv md rtf doc docx xls xlsx ppt pptx odt ods odp mp3 wav ogg m4a mp4 mov webm avi zip json
deleteFile($url): bool                        // unlink public/uploads/<basename>; refuses hidden files (.htaccess, .gitignore)
imageCanva()                                  // new \claviska\SimpleImage()
// strings & arrays
findKey($array, $keySearch): bool; recursive_filter(&$array, $allowed); loadConstantFromArray($array)
isJson($string); sanitize($string) /* htmlentities */; parsePath($path); sanitizePath($path)
startWith($haystack, $needle): bool; endWith($haystack, $needle): bool
singularize($word) /* drops the last char */; rearrange($arr) /* re-index multi-file $_FILES */
arraySort($array, $attribute, $sort = SORT_DESC); bindRouteToVar($route, $uri); parseRoute($path)
// time
getRelativeTime($datetime, $depth = 1); now() /* 'Y-m-d H:i:s' */
// auth wrappers
authorize($user); authorized(); unauthorize(); user()
guard($permissionAttribute, $acceptedPermission, $loginUrl = '/login', $notAuthorized = '/notAuthorized'); logout($redirectUrl = '/')
// http / mail
httpRequest($url, $method, $data, $headers, $username = null, $password = null)   // cURL; body (json_encode) sent for every method except GET when $data is not empty; returns json_decode(..., true)
httpFile($url, $filepath, $headers, $username = null, $password = null, $purpose = 'assistants')
sendMail($recipients, $emailContent, $from)   // ['email'=>..,'name'=>..], ['subject'=>..,'body'=>html], ['email'=>..,'name'=>..]; SMTP from MAIL_* env
associate($controller, $modelname, $table)
```

## Payment (`Caligrafy\Payment`, Stripe) - `$this->activatePayment()` then `$this->payment->...`

Keys: `APP_ENV=production` uses `PAY_PUBLIC_KEY_PRODUCTION/PAY_PRIVATE_KEY_PRODUCTION`, anything else the `_TEST` pair.
```php
createTransaction($amount, $currency, $card, $receipt_email = null, $metadata = null, $description = '', $transfer = array(), $save = false)
createPaymentIntent($amount, $currency, $metadata = array(), $receipt_email = null, $description = '', $transfer = array())
retrieveIntent($intentId)
createCheckout($amount = 1000, $currency = 'usd', $quantity = 1, $productData = array(), $successUrl = '', $cancelUrl = '', $customerEmail = null, $locale = null, $paymentType = null, $payment_intent_data = array(), $vendor = array())
   // returns ['action_success', 'data' => session, 'url' => hosted page]; payment_method_types only sent when $paymentType is given
retrieveCheckout($sessionId)
createAccount($email = null, $country = null, $type = 'express', $capabilities = array(), $accountInformation = array())   // Stripe Connect
createAccountLink($accountId, $refreshUrl, $returnUrl, $type = 'account_onboarding'); createLoginLink($accountId)
updateAccount($accountId, $updates); retrieve($accountId); getPublicKey()
```
Results are arrays with `action_success` and `data` (Stripe object) or `error`. Amounts are in cents. stripe-php 22 (API version pinned by the SDK). `createPaymentIntent()` sets `automatic_payment_methods.enabled`. Client: `public/js/services/stripe_client.js` (Payment Element, `confirmPayment`, `redirect: 'if_required'`, return to the same page); `default/pages/checkout.pug` links to `$checkoutUrl`.

## CryptoPayment (`Caligrafy\CryptoPayment`, Coinbase Business Checkouts API, keys `COINBASE_API_KEY_NAME` / `COINBASE_API_KEY_SECRET`)
```php
$crypto = new CryptoPayment($keyName = null, $keySecret = null);   // defaults to the .env keys
createTransaction($amount, $currency, $charge = array(), $metadata = array(), $redirectUrl = '', $cancelUrl = '', $expiresAt = null)
   // POST https://business.coinbase.com/api/v1/checkouts {amount "10.00", currency, description, metadata, successRedirectUrl, failRedirectUrl, expiresAt}
   // returns ['action_success' => true, 'url' => hosted page, 'data' => checkout (+ 'hosted_url')]
getCharge($chargeId); getChargeStatus($chargeId); getCharges($filters = array())   // GET /api/v1/checkouts[/{id}]
cancelTransaction($id)                                                          // POST /api/v1/checkouts/{id}/deactivate
refundTransaction($id, $amount = null, $currency = 'USDC', $reason = '')        // POST /api/v1/checkouts/{id}/refund
coinbaseRequest($requestPath, $requestMethod, $body = null, $additionalHeaders = array())
jwt($method, $path)   // per-request JWT: EdDSA (Ed25519 base64 secret) or ES256 (PEM EC key); claims sub, iss=cdp, aud=[cdp_service], nbf, exp=+120, uri
```
Errors (missing keys, API errors) come back as `['action_success' => false, 'error' => message]`. Create/refund send an `X-Idempotency-Key` UUID.

## Mail (`Caligrafy\Mail`) - PHPMailer over SMTP with `MAIL_HOST`, `MAIL_PORT` (465 => ssl, else tls), `MAIL_USERNAME`, `MAIL_PASSWORD`; `sendMail()` returns bool. SMTP debug output only when `APP_DEBUG` parses as true (`filter_var(..., FILTER_VALIDATE_BOOLEAN)`).

## OpenAI (`Caligrafy\OpenAI`, key `OPEN_AI_KEY`)
```php
new OpenAI($mode = TEXT)          // TEXT (chat completions) or IMAGE (images/generations, type 'image') - array constants
delegate($input = '', $outcome = '', $command = ANALYZE, $context = array(), $parameters = array())
converse($parameters = array(), $attachments = array(), $method = 'POST', $additionalHeaders = array())
// returns ['api_success' => bool, 'response' => choices | data (images: [['b64_json' => ...]]) | raw, 'type' => 'text'|'image']
OpenAI::command($name): ?string   // allow-list: 'summarize' -> SUMMARIZE ... (OpenAI::COMMANDS), anything else -> null
OpenAI::type($name): ?array       // 'text' -> TEXT, 'image' -> IMAGE (OpenAI::TYPES), anything else -> null
```
Commands (system prompts): `CONVERSE, SUMMARIZE, ANALYZE, CORRECT, CLASSIFY, COMPLETE` (with an `[insert]` marker the whole text is sent and the prompt asks for the replacement), `PREDICT, RECOMMEND, CUSTOM` (empty system prompt: put yours first in `$context`). Messages sent: `$context` + system command + user input/outcome.
Models: `GPT6_LUNA` (`gpt-6-luna`, default via `OPENAI_DEFAULT_MODEL` / `OPEN_AI_MODEL`), `GPT6_SOL` (`gpt-6.1-sol`), `GPT6_ASTRA` (`gpt-6-astra`; `GPT4_LATEST` and `GPT4_LATEST_VISION` point to it), images `GPT_IMAGE` (`gpt-image-2`, default via `OPENAI_DEFAULT_IMAGE_MODEL` / `OPEN_AI_IMAGE_MODEL`), `GPT_IMAGE_BEST`, `GPT_IMAGE_FAST`. Legacy: `GPT3`, `GPT4`, `GPT4_32` (shut down), `GPT4O`, `GPT4OMINI` (retire Jan 2027).
Defaults: TEXT `['model' => OPENAI_DEFAULT_MODEL]` only; IMAGE `['model', 'n' => 1, 'size' => '1024x1024', 'quality' => 'medium', 'output_format' => 'png']`.

## Assistant (`Caligrafy\Assistant` 2.0, OpenAI Responses + Conversations APIs, key `OPEN_AI_KEY`)
```php
new Assistant($parameters = array())   // name, description, instructions, model, tools, metadata, file_paths, file_ids, vector_store_id
                                       // no request unless files are given (upload + vector store)
retrieve()                             // no-op (compatibility)
start()                                // POST /v1/conversations -> thread_id = conv_...
createMessage($content, $file_ids = [], $metadata = null, $role = "user")   // queued until run()
run($additional_instructions = '', $instructions = null, $tools = [], $model = '', $metadata = null)
                                       // POST /v1/responses {model, input, instructions, conversation, tools, metadata}; run_id = resp_..., status
retrieveResponse($timeout = 120, $interval = 2)   // polls GET /v1/responses/{id} while queued/in_progress
end()                                  // DELETE /v1/conversations/{id}
text()                                 // text of the last answer (also ->response_text)
listMessages()                         // GET /v1/conversations/{id}/items?order=asc
uploadAssistantFiles($paths = [])      // POST /v1/files (purpose assistants) + vector store; returns ids
deleteAssistantFile($file_id)          // DELETE vector store file + DELETE /v1/files/{id}
delete()                               // DELETE /v1/vector_stores/{id}
// props: name, description, instructions, tools, file_ids, file_paths, metadata, vector_store_id, thread_id, run_id, status,
//        messages (['role', 'content' => [['type', 'text']]]), response_text, last_response, assistant_id (unused)
```
Chainable: `retrieve, start, end, createMessage, run, retrieveResponse`. Tools: `file_search` gets `vector_store_ids` (skipped without a vector store), `code_interpreter` gets `container: {type: auto, file_ids}`, `retrieval` is converted to `file_search`. Model constants: `ASSISTANT_GPT6_LUNA` (default, `ASSISTANT_DEFAULT_MODEL` / `OPEN_AI_MODEL`), `ASSISTANT_GPT6_SOL`, `ASSISTANT_GPT6_ASTRA`.

## Agent (`Caligrafy\Agent`, Groq, key `GROQ_API_KEY`)
```php
new Agent($apiKey = "")
configure($parameters = array())
delegate($model = "", $messages = array(), $response_format = array(), $stream = false, $tool_choice = "", $tools = array())
   // $model empty -> GROQ_DEFAULT_MODEL; returns ['api_success' => bool, 'response' => content string | error message]
visualize($file = "", $prompt = "Describe this image", $model = GROQ_DEFAULT_VISION_MODEL)
transcribe($file = "", $prompt = "", $model = GROQ_TRANSCRIPTION_MODEL, $language = "en", $response_format = "json")   // JSON string
translate($file = "", $prompt = "", $model = GROQ_TRANSCRIPTION_MODEL, $response_format = "json")                     // JSON string
```
Constants: `GROQ_DEFAULT_MODEL` (`openai/gpt-oss-120b` or `GROQ_MODEL`), `GROQ_SMALL_MODEL` (`openai/gpt-oss-20b`), `GROQ_DEFAULT_VISION_MODEL` (`qwen/qwen3.8-27b` or `GROQ_VISION_MODEL`), `GROQ_TRANSCRIPTION_MODEL` (`whisper-large-v3`). `GROQ_API_BASE` overrides the API URL.

## Watson (`Caligrafy\Watson`, watsonx Assistant v2, keys `WATSON_API_KEY`, `WATSON_URL`, `WATSON_ENVIRONMENT_ID`, `WATSON_API_VERSION`)
```php
new Watson($watson_assistant_id, $environment_id = null)   // throws without WATSON_URL; opens a session or throws
communicate($messageText, $skillVariables = null); getSessionId(); getContext(); getSkillVariables()
getContextVariable($variable); getConversation(); getLastExchange(); getResponse(); getExchangeAt($turnCount); closeSession()
```
URLs: `{WATSON_URL}/v2/assistants/{assistant_id}[/environments/{environment_id}]/sessions[/{session}/message]?version={WATSON_API_VERSION|2024-08-25}`, basic auth `apikey:{WATSON_API_KEY}`.

## Errors (`Caligrafy\Errors::get($code)`, `Errors::set($message, $code)`)
| Code | Meaning | Usual cause |
| - | - | - |
| 1000 | Invalid Http Request Method | |
| 1001 | Invalid Controller or Action | class/method name typo, file outside `application/`, wrong namespace in the route |
| 1002 | Invalid library | `import()` of a missing file |
| 1004 | Access Denied | JSON request to a controller route without `Auth::activateAPI()` or without a valid `Authorization: Bearer <API_KEY>` |
| 2001 | Database Security Error | invalid column name given to `search()` or a Model write |
| 2003 | Database Query Error | `DB_ACTIVATE` not `true`, bad credentials/host, or SQL that fails to prepare |
| 3000 | Cross Site Forgery | form POST/PUT/DELETE without `_token`, or a non-JSON fetch/axios call |
| 4000/4001 | Mail errors | |
| 5000 | Key generator error | |

Errors are caught in `web.php` by `Errors::handle($e)`: HTTP status from `Errors::status($code)` (1001 → 404, 1004 → 401, 3000 → 403, 1000 → 405, anything else → 500), the `[ERROR ...]` text for HTML requests and `{"error": {"code": ..., "message": "..."}}` (prefix removed) when the request wants JSON. `Errors::raise($code)` (used by the router and the CSRF check) sets the status and returns the exception, so the status is right even with an old `web.php` that only echoes. `Framework::run()` buffers output (`ob_start` 1 MB chunks), and registers `Errors::shutdown` (PHP fatals → 500, partial page discarded) and an exception handler (uncaught `Throwable` → 500); their message is only shown when `APP_DEBUG=true`, otherwise `[ERROR 500] Internal Server Error`. Before this fix every error was HTTP 200.

## Caligrafer CLI (`php caligrafer.php <cmd>` or `.bin/caligrafer <cmd>`)
| Command | Effect (from caligrafer.php) |
| - | - |
| (none) | help text |
| `initialize` | keeps existing `APP_KEY`/`API_KEY` or prepends new ones, adds `APP_ROOT` if missing (creates `.env` from `framework/settings/.env.example` first if missing), writes `framework/librairies/app/.env` (`VITE_APP_KEY`, `VITE_API_KEY`), copies `framework/settings/application` to `./application` if absent (and writes `application/.caligrafy-manifest.json`), `chmod -R 777 public/uploads`, **deletes `.git`**. `APP_ROOT` = folder name, or empty when a 2nd argument is passed (`.bin/caligrafer` passes `off` when no local httpd runs, i.e. Docker) |
| `upgrade [--apply] [--force <application/path>]` | compares every file of `framework/settings/application` with `application/` (`Caligrafy\Upgrader`): `missing`, `identical`, `outdated` (hash matches `application/.caligrafy-manifest.json` or a previous release in `framework/settings/template-history.json` = untouched), `customized`, `routes` (`config/routing/web.php`, never replaced). Dry run by default. `--apply`: copies missing, replaces outdated, writes `<file>.caligrafy-new` next to customized files, patches a `catch` that does `echo $e->getMessage();` to `Errors::handle($e);` (+ `use Caligrafy\Errors;`), updates the manifest. `--force <path>` (alone) replaces one template file. Replaced files are backed up to `backups/caligrafy-upgrade-<Ymd-His>/application/...`. Never touches `.env`, uploads, vendor or non-template files. Client apps in `public/` (markers `new FaceDetector`, `new MlCore`, `__bots__`, `loadVue(`) are reported against `framework/plugins/<type>`, never modified. Exit 1 without `application/` or with an unknown `--force` path |
| `doctor [--only <checks>] [--json] [--env <file>]` | read-only health check (`Caligrafy\Doctor`). Checks, in order: `project` (PHP >= 8.3; curl, json, mbstring, openssl, pdo required, gd/sodium/intl/zip optional; `vendor/autoload.php`; the env file; `APP_KEY`/`API_KEY`; `public/uploads` writable; `public/manifest.webmanifest` valid JSON with `name`, `start_url` and existing relative icons incl. 192x192 and 512x512, else WARN; with `PWA_SERVICE_WORKER` true: `public/sw.js` + `public/offline.html` exist and `APP_PROTOCOL=https` when `APP_ENV=production`, else WARN; `Upgrader::plan()` outdated+missing count => WARN "run upgrade"), `database` (when `DB_ACTIVATE` is true: PDO with `Database::dsn()`, 5 s timeout, server version), `openai` (`GET {OPENAI_API_BASE or https://api.openai.com/v1}/models`: 401 => FAIL; `OPENAI_DEFAULT_MODEL`/`OPENAI_DEFAULT_IMAGE_MODEL` missing from the key's list => WARN with available models; billing is not checkable read-only), `groq` (`GET {GROQ_API_BASE or https://api.groq.com/openai/v1}/models`, `GROQ_DEFAULT_MODEL`/`GROQ_DEFAULT_VISION_MODEL`), `stripe` (key of `APP_ENV`: `PAY_PRIVATE_KEY_PRODUCTION` when production else `_TEST`; `balance->retrieve()` with stripe-php, `api_base` from `STRIPE_API_BASE`; test/live mode and pk/sk mode mismatch WARN), `coinbase` (`CryptoPayment::jwt()` then `GET {COINBASE_API_BASE or https://business.coinbase.com}/api/v1/checkouts?pageSize=1`), `watson` (`WATSON_URL` format, `GET {WATSON_URL}/v2/assistants?version=` with `apikey:` basic auth; when listing is refused and `WATSON_ASSISTANT_ID` is set, opens and deletes one session), `mail` (TCP or SSL on 465 to `MAIL_HOST:MAIL_PORT`, banner, `EHLO`, `STARTTLS` when offered, `QUIT`; never sends). `.env.example` placeholders (`your-smtp-host`, `sk_test_1234`...) count as not set (SKIP). Prints `OK/WARN/FAIL/SKIP <name>: <message>` + `-> <fix>`; secrets only as `Doctor::mask()` (known prefix + `…` + last 4 + length, nothing under 16 chars); spaces around a key or a known prefix without its separator (`sk-proj`, `gsk`, `sk_test`...) => WARN. cURL errors get hints: TLS (`self-signed certificate` => corporate TLS inspection, server skill), DNS, timeout, refused. Timeouts 5 s connect / 8 s total. `--only` takes a comma list (unknown name => exit 1), `--json` prints `{results: [{check, name, status, message, fix}], summary}`, `--env <file>` loads another env file. Never creates `.env`. Exit 1 when any FAIL |
| `generatekeys` | prints a new `APP_KEY`/`API_KEY` pair |
| `generateapikey` | prints an extra API key for the current `APP_KEY` |
| `create <name>` | copies `framework/librairies/app` (Vue 3 + Vite + vue-router + vuex SPA) to `public/<name>` and runs `npm install` |
| `app <name>` / `bot <name>` / `facedetect <name>` / `ml <name>` | copies `framework/plugins/<type>` to `public/<name>` |
| `server start|stop` | only acts when a 3rd arg is present (`.bin/caligrafer` adds `off` if no `httpd` process): `docker-compose up --build -d dev-box` / `docker-compose stop` |
Reserved app names: `app, bot, __bots__, css, facedetect, fonts, images, js, ml, resources, uploads`.

`.bin/caligrafer`: if PHP and Composer exist, runs `composer install` and `initialize` when needed, then `./caligrafer.php <mode> <command> [off]` (`upgrade` and `doctor` get their arguments unchanged; `doctor` never runs `composer install` or `initialize`); without PHP it runs `.bin/server start|stop` (Docker `prod-box`, which copies the code into the image).

## Docker (`docker-compose.yaml`)
| Service | Port | Notes |
| - | - | - |
| `dev-box` | 8080:80 | php:8.5-apache (rewrite + headers, gd, intl, pdo_mysql, zip, bcmath), mounts `./` at `/var/www/html`, env `APP_ROOT=""`, `DB_HOST=mysql` (overridden by `.env`) |
| `prod-box` | 8080:80 | copies code into `/var/www/html/caligrafy-quill`, composer (from `composer:2`) + initialize |
| `phpmyadmin` | 8077:80 | `phpmyadmin:5.2.3`, PMA_HOST=mysql |
| `mysql` | 3306:3306 | `mysql:8.4` (LTS), root/root, no named volume (`down` + `up` gives an empty database; use `stop`) |
