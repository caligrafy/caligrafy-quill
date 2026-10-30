<?php
/**
 * End-to-end HTTP tests against the QA web container (qa/run.sh starts it).
 * Run on the host:  php qa/tests/http_tests.php
 */

require __DIR__ . '/lib.php';

$BASE = getenv('QA_BASE') ?: 'http://localhost:8090';
$ROOT = dirname(__DIR__, 2);
$env = parse_ini_file("$ROOT/.env", false, INI_SCANNER_RAW);
$API_KEY = $env['API_KEY'];
$APP_KEY = $env['APP_KEY'];

class QaBrowser
{
    private $jar;
    public function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'qajar'); }

    public function request($method, $path, $options = [])
    {
        global $BASE;
        $ch = curl_init($BASE . $path);
        $headers = $options['headers'] ?? ['Accept: text/html,application/xhtml+xml'];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $options['agent'] ?? 'QA-Browser/1.0',
            CURLOPT_TIMEOUT => 30,
        ]);
        if (isset($options['form'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['form']);
        }
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $headerSize);
        preg_match('/^Location:\s*(.+)$/mi', $head, $m);
        return ['status' => $status, 'location' => trim($m[1] ?? ''), 'body' => substr($raw, $headerSize), 'headers' => $head];
    }

    public function get($path, $o = []) { return $this->request('GET', $path, $o); }
    public function post($path, $form, $o = []) { return $this->request('POST', $path, $o + ['form' => $form]); }

    public function token($page = '/login')
    {
        $r = $this->get($page);
        preg_match("/name='_token' value='([^']+)'/", $r['body'], $m);
        qa_assert(!empty($m[1]), "no CSRF token on $page (status {$r['status']})");
        return $m[1];
    }

    public function login($user, $pass)
    {
        return $this->post('/login', ['_token' => $this->token('/login'), 'username' => $user, 'passcode' => $pass]);
    }
}

function qa_sql($query)
{
    $cmd = 'docker compose -f ' . escapeshellarg(dirname(__DIR__) . '/docker-compose.yml') .
        ' exec -T mysql mysql -uroot -proot quillqa -N -B -e ' . escapeshellarg($query) . ' 2>/dev/null';
    return trim((string)shell_exec($cmd));
}

function qa_in_web($php)
{
    $cmd = 'docker compose -f ' . escapeshellarg(dirname(__DIR__) . '/docker-compose.yml') .
        ' exec -T web php -r ' . escapeshellarg($php) . ' 2>&1';
    return trim((string)shell_exec($cmd));
}

function qa_node($js)
{
    $file = tempnam(sys_get_temp_dir(), 'qajs') . '.js';
    file_put_contents($file, $js);
    $out = shell_exec('node ' . escapeshellarg($file) . ' 2>&1');
    unlink($file);
    return trim((string)$out);
}

$guest = new QaBrowser();

// ---------------------------------------------------------------------------
qa_section('Front controller and assets');

qa_test('GET / renders the home page', function () use ($guest) {
    $r = $guest->get('/');
    qa_equals(200, $r['status']);
    qa_contains('Welcome to Caligrafy', $r['body']);
});

qa_test('GET /helloworld', function () use ($guest) {
    qa_contains('Hello World', $guest->get('/helloworld')['body']);
});

qa_test('public assets are still served', function () use ($guest) {
    $r = $guest->get('/public/css/styles.css');
    qa_equals(200, $r['status']);
    $r = $guest->get('/public/js/services/app.js');
    qa_equals(200, $r['status']);
});

// ---------------------------------------------------------------------------
qa_section('Install to home screen (web app manifest, service worker)');

qa_test('the manifest is served as application/manifest+json and its icons resolve from its URL', function () use ($guest, $BASE) {
    $r = $guest->get('/public/manifest.webmanifest');
    qa_equals(200, $r['status']);
    qa_assert(preg_match('#^Content-Type:\s*application/manifest\+json#mi', $r['headers']) === 1, 'Content-Type: ' . $r['headers']);
    $manifest = json_decode($r['body'], true);
    qa_assert(is_array($manifest) && !empty($manifest['icons']), 'not a manifest: ' . substr($r['body'], 0, 200));
    foreach ($manifest['icons'] as $icon) {
        // icon, start_url and scope URLs are relative to the manifest URL: ../ is the application root
        $icon = $guest->get('/public/' . $icon['src']);
        qa_equals(200, $icon['status'], $icon['status'] . ' for ' . $icon['src']);
        qa_assert(preg_match('#^Content-Type:\s*image/png#mi', $icon['headers']) === 1, 'icon is not a PNG');
    }
    qa_equals('../', $manifest['start_url']);
});

qa_test('the layout links the manifest; the service worker and the install module are off by default', function () use ($guest, $BASE) {
    $body = $guest->get('/')['body'];
    qa_contains('<link rel="manifest" href="' . $BASE . '/public/manifest.webmanifest">', $body);
    qa_not_contains('serviceWorker.register', $body);
    qa_not_contains('id="caligrafy-install"', $body);
});

qa_test("'install' => true adds the install module (beforeinstallprompt button and iOS hint)", function () use ($guest) {
    $r = $guest->get('/qa/install');
    qa_equals(200, $r['status']);
    qa_contains('id="caligrafy-install"', $r['body']);
    qa_contains('beforeinstallprompt', $r['body']);
    qa_contains('Add to Home Screen', $r['body']);
});

qa_test('public/sw.js is served with Service-Worker-Allowed and the offline page is self-contained', function () use ($guest) {
    $r = $guest->get('/public/sw.js');
    qa_equals(200, $r['status']);
    qa_assert(preg_match('#^Content-Type:\s*(text|application)/javascript#mi', $r['headers']) === 1, 'Content-Type of sw.js');
    qa_assert(preg_match('#^Service-Worker-Allowed:\s*/\s*$#mi', $r['headers']) === 1, 'no Service-Worker-Allowed header');
    qa_assert(preg_match('#^Service-Worker-Allowed:#mi', $guest->get('/public/js/script.js')['headers']) === 0, 'Service-Worker-Allowed on other files');
    $offline = $guest->get('/public/offline.html');
    qa_equals(200, $offline['status']);
    qa_not_contains('<link', $offline['body']);
    qa_not_contains('<script src', $offline['body']);
});

qa_test('PWA_SERVICE_WORKER=true registers the service worker with the application root as scope', function () use ($guest, $BASE, $ROOT) {
    $file = "$ROOT/.env";
    $orig = file_get_contents($file);
    qa_assert(preg_match('/^PWA_SERVICE_WORKER=/m', $orig) === 1, 'PWA_SERVICE_WORKER is not in the QA .env (framework/settings/.env.example)');
    file_put_contents($file, preg_replace('/^PWA_SERVICE_WORKER=.*$/m', 'PWA_SERVICE_WORKER=true', $orig));
    try { $body = $guest->get('/')['body']; } finally { file_put_contents($file, $orig); }
    qa_contains("navigator.serviceWorker.register('$BASE/public/sw.js', { scope: '$BASE/' })", $body);
    qa_contains("serviceWorker.getRegistrations", $guest->get('/')['body']); // turned off again: unregisters it
});

// ---------------------------------------------------------------------------
qa_section('Sensitive files are not served');

foreach (['/.env', '/.git/HEAD', '/composer.json', '/composer.lock', '/caligrafer.php', '/docker-compose.yaml',
          '/application/config/routing/web.php', '/framework/core/Model.php', '/framework/settings/.env.example',
          '/vendor/autoload.php', '/qa/fixtures/outside/marker.php', '/claude-plugin/README.md',
          '/application/models/Auth/create_db.sql', '/.claude/settings.json',
          // case variants: macOS/Windows filesystems resolve them to the real files
          '/Composer.json', '/Caligrafer.php', '/Framework/core/Model.php', '/VENDOR/autoload.php',
          '/application/models/Auth/create_db.SQL', '/.ENV'] as $path) {
    qa_test("GET $path is denied", function () use ($guest, $path, $APP_KEY) {
        $r = $guest->get($path);
        qa_assert(in_array($r['status'], [403, 404]), "status {$r['status']}");
        qa_not_contains($APP_KEY, $r['body'], 'APP_KEY leaked');
        qa_not_contains('QA-TRAVERSAL-MARKER', $r['body']);
    });
}

qa_test('PHP files dropped in public/uploads are not executed', function () use ($guest, $ROOT) {
    file_put_contents("$ROOT/public/uploads/qa_probe.php", '<?php echo "EXEC" . "UTED";');
    $r = $guest->get('/public/uploads/qa_probe.php');
    unlink("$ROOT/public/uploads/qa_probe.php");
    qa_not_contains('EXECUTED', $r['body'], "status {$r['status']}");
});

// ---------------------------------------------------------------------------
qa_section('Client apps (views/Client/app.pug)');

qa_test('GET /apps/qaapp renders the client app', function () use ($guest) {
    $r = $guest->get('/apps/qaapp');
    qa_equals(200, $r['status']);
    qa_contains('loadEnvironment(', $r['body']);
});

qa_test('?app= selects another markup file of the same app', function () use ($guest) {
    qa_contains('QA-SECOND-PAGE', $guest->get('/apps/qaapp?app=second')['body']);
});

foreach (['../../qa/fixtures/outside/marker', '..%2F..%2Fqa%2Ffixtures%2Foutside%2Fmarker', '../qaapp/../../qa/fixtures/outside/marker'] as $p) {
    qa_test("?app=$p cannot include files outside the app", function () use ($guest, $p) {
        $r = $guest->get('/apps/qaapp?app=' . $p);
        qa_not_contains('QA-TRAVERSAL-MARKER', $r['body']);
    });
}

qa_test('query parameters cannot inject script into the env template literal', function () use ($guest) {
    $payload = '${globalThis.QA_PWNED=1}`;globalThis.QA_PWNED=2;`</script><script>globalThis.QA_PWNED=3</script> "quotes" \\backslash\\ \'single\' line' . "\n" . 'break é ✓';
    $r = $guest->get('/apps/qaapp?x=' . rawurlencode($payload));
    qa_not_contains('<script>globalThis.QA_PWNED=3', $r['body'], 'raw </script> breakout');
    preg_match('/<script>(loadEnvironment\(.*?)<\/script>/s', $r['body'], $m);
    qa_assert(!empty($m[1]), 'loadEnvironment script not found');
    $js = 'const vm = require("vm"); const ctx = { env: null, globalThis: {} };' .
        'ctx.loadEnvironment = function (e) { ctx.env = JSON.parse(e); };' .
        'try { vm.runInNewContext(' . json_encode($m[1]) . ', ctx); } catch (e) { console.log("ERROR " + e.message); process.exit(0); }' .
        'console.log(JSON.stringify({ pwned: ctx.QA_PWNED || ctx.globalThis.QA_PWNED || null, x: ctx.env && ctx.env.x }));';
    $out = qa_node($js);
    qa_not_contains('ERROR', $out, 'env script does not parse');
    $res = json_decode($out, true);
    qa_equals(null, $res['pwned'], 'code was executed');
    qa_equals($payload, $res['x'], 'the value must round-trip unchanged');
});

qa_test('the Watson bot page uses the pinned Vue 3 build and no BotUI', function () use ($guest) {
    $r = $guest->get('/bot/qabot?botSkillId=qa');
    qa_equals(200, $r['status']);
    qa_contains('vue@3.5.44/dist/vue.global.prod.js', $r['body']);
    qa_not_contains('botui', strtolower($r['body']));
});

qa_test('the bot data routes reach Watson (mock) through WatsonController', function () use ($API_KEY) {
    $b = new QaBrowser();
    $headers = ['headers' => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $API_KEY]];
    $connect = $b->request('POST', '/__bots__/qabot/assistant-qa', $headers + ['form' => '{}']);
    qa_equals('true', trim($connect['body']), $connect['body']);
    $reply = json_decode($b->request('POST', '/__bots__/qabot/communicate', $headers + ['form' => json_encode(['text' => 'show me the menu'])])['body'], true);
    qa_equals(true, $reply['action_success'] ?? null, json_encode($reply));
    qa_equals('option', $reply['response'][0]['response_type']);
    qa_equals(['Dog', 'Cat'], array_column($reply['response'][0]['options'], 'label'));
});

// ---------------------------------------------------------------------------
qa_section('CSRF and API access');

qa_test('a form POST without _token is rejected', function () use ($guest) {
    $r = $guest->post('/login', ['username' => 'a', 'passcode' => 'b']);
    qa_equals(403, $r['status']);
    qa_contains('[ERROR 3000] Security Error - Cross Site Forgery', $r['body']);
});

qa_test('a form POST without _token and without a User-Agent is rejected', function () use ($guest) {
    $r = $guest->post('/login', ['username' => 'a', 'passcode' => 'b'], ['agent' => '']);
    qa_equals(403, $r['status']);
    qa_contains('ERROR 3000', $r['body']);
});

qa_test('a JSON call without the API key is denied', function () use ($guest) {
    $r = $guest->get('/qa/books/5', ['headers' => ['Accept: application/json']]);
    qa_equals(401, $r['status']);
    qa_contains('application/json', $r['headers']);
    qa_equals(['error' => ['code' => 1004, 'message' => 'Access Denied']], json_decode($r['body'], true));
});

qa_test('a JSON call with a wrong API key is denied with 401', function () use ($guest) {
    $r = $guest->get('/qa/books/5', ['headers' => ['Accept: application/json', 'Authorization: Bearer wrong']]);
    qa_equals(401, $r['status']);
    qa_equals(1004, json_decode($r['body'], true)['error']['code'] ?? null);
});

qa_test('a route to a missing controller action returns 404 with the same error text', function () use ($guest) {
    $r = $guest->get('/qa/missing');
    qa_equals(404, $r['status']);
    qa_contains('[ERROR 1001] Invalid Controller or Action', $r['body']);
});

qa_test('a route to a missing action returns a JSON 404 to API clients', function () use ($guest, $API_KEY) {
    $r = $guest->get('/qa/missing', ['headers' => ['Accept: application/json', 'Authorization: Bearer ' . $API_KEY]]);
    qa_equals(404, $r['status']);
    qa_equals(['error' => ['code' => 1001, 'message' => 'Invalid Controller or Action']], json_decode($r['body'], true));
});

// Controller names (plugin gotcha G47): example controllers live in Caligrafy\Controllers
qa_test('a project controller named like an example controller (global AIController) does not collide and is routed', function () use ($guest) {
    $r = $guest->get('/qa/own-ai');
    qa_equals(200, $r['status']);
    qa_contains('QA-OWN-AICONTROLLER', $r['body']);
    qa_equals(200, $guest->get('/')['status']);
});

qa_test('an old-style short route to an example controller (ClientController) still resolves', function () use ($guest) {
    $r = $guest->get('/qa/old-apps/qaapp');
    qa_equals(200, $r['status']);
    qa_contains('loadEnvironment(', $r['body']);
});

qa_test('a namespaced application controller route (Shop\OrderController@show, with or without a leading backslash)', function () use ($guest, $API_KEY) {
    foreach (['/qa/shop/7', '/qa/shop-root/7'] as $path) {
        $r = $guest->get($path, ['headers' => ['Accept: application/json', 'Authorization: Bearer ' . $API_KEY]]);
        qa_equals(200, $r['status'], $path);
        qa_equals(['controller' => 'Shop\OrderController', 'id' => '7'], json_decode($r['body'], true), $path);
    }
});

qa_test('a route to a missing namespaced controller returns 404', function () use ($guest) {
    $r = $guest->get('/qa/shop-missing');
    qa_equals(404, $r['status']);
    qa_contains('[ERROR 1001] Invalid Controller or Action', $r['body']);
});

// PHP fatal errors and uncaught Errors: 500, message only with APP_DEBUG=true, never a stack trace
$qaWithDebug = function ($value, $fn) use ($ROOT) {
    $file = "$ROOT/.env";
    $orig = file_get_contents($file);
    file_put_contents($file, preg_replace('/^APP_DEBUG=.*$/m', "APP_DEBUG=$value", $orig));
    try { $fn(); } finally { file_put_contents($file, $orig); }
};

qa_test('a PHP fatal error returns 500 without details when APP_DEBUG=false', function () use ($guest, $qaWithDebug, $API_KEY) {
    $qaWithDebug('false', function () use ($guest, $API_KEY) {
        $r = $guest->get('/qa/fatal');
        qa_equals(500, $r['status']);
        qa_equals('[ERROR 500] Internal Server Error', trim($r['body']));
        $r = $guest->get('/qa/boom', ['headers' => ['Accept: application/json', 'Authorization: Bearer ' . $API_KEY]]);
        qa_equals(500, $r['status']);
        qa_equals(['error' => ['code' => 500, 'message' => 'Internal Server Error']], json_decode($r['body'], true));
    });
});

qa_test('with APP_DEBUG=true the 500 shows the message but no stack trace', function () use ($guest, $qaWithDebug) {
    $qaWithDebug('true', function () use ($guest) {
        $r = $guest->get('/qa/fatal');
        qa_equals(500, $r['status']);
        qa_contains('QaFatalTwice', $r['body']);
        qa_not_contains('PARTIAL-PAGE', $r['body']);
        $r = $guest->get('/qa/boom');
        qa_equals(500, $r['status']);
        qa_contains('qa_undefined_function_with_secret_name', $r['body']);
        qa_not_contains('Stack trace', $r['body']);
        qa_not_contains('#0 ', $r['body']);
    });
});

qa_test('a JSON call with the API key succeeds', function () use ($guest, $API_KEY) {
    $r = $guest->get('/qa/books/5', ['headers' => ['Accept: application/json', 'Authorization: Bearer ' . $API_KEY]]);
    qa_equals(['ok' => true, 'id' => '5'], json_decode($r['body'], true));
});

// ---------------------------------------------------------------------------
qa_section('REST API example from the wiki (BookController)');

function qa_api($method, $path, $json = null, $form = null)
{
    global $API_KEY;
    $headers = ['Accept: application/json', 'Authorization: Bearer ' . $API_KEY];
    $o = ['headers' => $headers];
    if ($json !== null) { $o['headers'][] = 'Content-Type: application/json'; $o['form'] = json_encode($json); }
    if ($form !== null) { $o['form'] = $form; }
    $r = (new QaBrowser())->request($method, $path, $o);
    return json_decode($r['body'], true) ?? $r['body'];
}

qa_test('POST /books (raw JSON) creates a book', function () {
    $b = qa_api('POST', '/books', ['title' => 'Dune', 'author' => 'Herbert', 'publisher_id' => 1]);
    qa_equals('Dune', $b['title'] ?? null, json_encode($b));
    $GLOBALS['bookId'] = $b['id'];
});

qa_test('GET /books lists it', function () {
    $list = qa_api('GET', '/books');
    qa_equals(['Dune'], array_column($list, 'title'));
});

qa_test('PUT /books/{id} (raw JSON) updates only the given fields', function () {
    $b = qa_api('PUT', '/books/' . $GLOBALS['bookId'], ['title' => 'Dune Messiah']);
    qa_equals('Dune Messiah', $b['title']);
    qa_equals('Herbert', $b['author']);
});

qa_test('POST with _method=PUT (form-data) updates too', function () {
    $b = qa_api('POST', '/books/' . $GLOBALS['bookId'], null, ['_method' => 'PUT', 'author' => 'Frank Herbert']);
    qa_equals('Frank Herbert', $b['author'] ?? null, json_encode($b));
});

qa_test('hasOne() through the API returns the publisher', function () {
    $p = qa_api('GET', '/books/' . $GLOBALS['bookId'] . '/publisher');
    qa_equals('Penguin', $p['publishername'] ?? null);
});

qa_test('DELETE /books/{id} deletes it', function () {
    qa_api('DELETE', '/books/' . $GLOBALS['bookId']);
    qa_equals([], qa_api('GET', '/books'));
});

qa_test('PUT /books/{id} with an injected id cannot touch other rows', function () {
    $a = qa_api('POST', '/books', ['title' => 'A', 'author' => 'x', 'publisher_id' => 1]);
    $b = qa_api('POST', '/books', ['title' => 'B', 'author' => 'y', 'publisher_id' => 1]);
    qa_api('PUT', '/books/' . rawurlencode($a['id'] . ' OR 1=1'), ['title' => 'HACKED']);
    $titles = array_column(qa_api('GET', '/books'), 'title');
    qa_assert(!in_array('HACKED', $titles, true) || count(array_keys($titles, 'HACKED', true)) <= 1, implode(',', $titles));
    qa_assert(in_array('B', $titles, true), 'row B was overwritten: ' . implode(',', $titles));
});

// ---------------------------------------------------------------------------
qa_section('Uploads through a real HTTP request');

qa_test('uploading shell.php is refused', function () use ($guest) {
    $tmp = tempnam(sys_get_temp_dir(), 'qa');
    file_put_contents($tmp, '<?php echo "EXEC"."UTED";');
    $r = $guest->post('/qa/upload', ['_token' => $guest->token('/login'), 'f' => new CURLFile($tmp, 'application/x-php', 'shell.php')]);
    qa_equals('', json_decode($r['body'], true)['url'] ?? 'missing', $r['body']);
});

qa_test('uploading a .jpg works and the file is served', function () use ($guest, $BASE) {
    $tmp = tempnam(sys_get_temp_dir(), 'qa');
    file_put_contents($tmp, 'QA-JPEG-BYTES');
    $r = $guest->post('/qa/upload', ['_token' => $guest->token('/login'), 'f' => new CURLFile($tmp, 'image/jpeg', 'photo.jpg')]);
    $url = json_decode($r['body'], true)['url'] ?? '';
    qa_assert(substr($url, -4) === '.jpg', "unexpected url '$url' / {$r['body']}");
    $path = parse_url($url, PHP_URL_PATH);
    $file = (new QaBrowser())->get($path);
    qa_equals('QA-JPEG-BYTES', $file['body']);
    @unlink(dirname(__DIR__, 2) . '/public/uploads/' . basename($path));
});

// ---------------------------------------------------------------------------
qa_section('Authentication module');

$alice = new QaBrowser();

qa_test('register stores a password_hash() hash', function () use ($alice) {
    $r = $alice->post('/register', ['_token' => $alice->token('/register'), 'username' => 'alice', 'passcode' => 'Passw0rdQa', 'confirmpasscode' => 'Passw0rdQa']);
    qa_contains('/login', $r['location'], "status {$r['status']}: " . substr(strip_tags($r['body']), 0, 300));
    $hash = qa_sql("SELECT passcode FROM users WHERE username = 'alice'");
    qa_assert(password_get_info($hash)['algo'] !== null && strpos($hash, '$2y$') === 0, "not a bcrypt hash: $hash");
});

qa_test('login with the right password', function () use ($alice) {
    $r = $alice->login('alice', 'Passw0rdQa');
    qa_contains('/home', $r['location'], substr(strip_tags($r['body']), 0, 300));
    qa_contains('You are currently logged in', $alice->get('/home')['body']);
});

qa_test('login with a wrong password fails', function () {
    $b = new QaBrowser();
    qa_contains('Invalid credentials', $b->login('alice', 'nope12345')['body']);
});

qa_test('a LIKE wildcard username cannot log in as somebody else', function () {
    $b = new QaBrowser();
    $r = $b->login('%', 'Passw0rdQa');
    qa_not_contains('/home', $r['location']);
    $r = $b->login('al_ce', 'Passw0rdQa');
    qa_not_contains('/home', $r['location']);
});

qa_test("a quote in the username does not break the query", function () {
    $b = new QaBrowser();
    $r = $b->login("x' OR '1'='1", 'Passw0rdQa');
    qa_not_contains('/home', $r['location']);
    qa_not_contains('ERROR 2003', $r['body']);
    qa_not_contains('Fatal error', $r['body']);
});

qa_test('a legacy (encryptDecrypt) password still logs in and is upgraded to a hash', function () use ($APP_KEY) {
    $legacy = qa_in_web('require "qa/tests/bootstrap.php"; echo encryptDecrypt("encrypt", "Legacy123");');
    qa_sql("INSERT INTO users (username, passcode) VALUES ('legacy', '" . addslashes($legacy) . "')");
    $b = new QaBrowser();
    qa_contains('/home', $b->login('legacy', 'Legacy123')['location']);
    qa_equals(0, strpos(qa_sql("SELECT passcode FROM users WHERE username = 'legacy'"), '$2y$'), 'password was not re-hashed');
    $b2 = new QaBrowser();
    qa_contains('/home', $b2->login('legacy', 'Legacy123')['location'], 'login after the upgrade');
});

qa_test('a logged-in user visiting /register is redirected without rendering the form', function () use ($alice) {
    $r = $alice->get('/register');
    qa_contains('/home', $r['location']);
    qa_not_contains('confirmpasscode', $r['body']);
});

qa_test('logout ends the session', function () {
    $b = new QaBrowser();
    $b->login('alice', 'Passw0rdQa');
    $b->get('/logout');
    qa_contains('/login', $b->get('/home')['location']);
});

qa_test('a user with permissions 0 cannot open /admin', function () use ($alice) {
    $r = $alice->get('/admin');
    qa_contains('notAuthorized', $r['location'], "status {$r['status']}");
});

qa_test('an admin (permissions >= 1) can open /admin and demote a user to 0', function () {
    $bob = new QaBrowser();
    $bob->post('/register', ['_token' => $bob->token('/register'), 'username' => 'bob', 'passcode' => 'Passw0rdQa', 'confirmpasscode' => 'Passw0rdQa']);
    qa_sql("UPDATE users SET permissions = 1 WHERE username IN ('alice', 'bob')");
    $admin = new QaBrowser();
    qa_contains('/home', $admin->login('alice', 'Passw0rdQa')['location']);
    $r = $admin->get('/admin');
    qa_equals(200, $r['status']);
    qa_contains('bob', $r['body']);
    $bobId = qa_sql("SELECT id FROM users WHERE username = 'bob'");
    $token = $admin->token("/users/$bobId/edit");
    $admin->post("/users/$bobId/edit", ['_token' => $token, '_method' => 'PUT', 'username' => 'bob', 'passcode' => '', 'confirmpasscode' => '', 'permissions' => '0']);
    qa_equals('0', qa_sql("SELECT permissions FROM users WHERE username = 'bob'"), 'permissions must drop to 0');
    qa_equals(0, strpos(qa_sql("SELECT passcode FROM users WHERE username = 'bob'"), '$2y$'), 'an empty password field keeps the hash');
});

qa_test('the admin can change a password, stored as a hash', function () {
    $admin = new QaBrowser();
    $admin->login('alice', 'Passw0rdQa');
    $bobId = qa_sql("SELECT id FROM users WHERE username = 'bob'");
    $token = $admin->token("/users/$bobId/edit");
    $admin->post("/users/$bobId/edit", ['_token' => $token, '_method' => 'PUT', 'username' => 'bob', 'passcode' => 'NewPassw0rd', 'confirmpasscode' => 'NewPassw0rd', 'permissions' => '0']);
    qa_equals(0, strpos(qa_sql("SELECT passcode FROM users WHERE username = 'bob'"), '$2y$'), 'new password must be hashed');
    $b = new QaBrowser();
    qa_contains('/home', $b->login('bob', 'NewPassw0rd')['location']);
});

qa_test('the admin form rejects a password shorter than 8 characters', function () {
    $admin = new QaBrowser();
    $admin->login('alice', 'Passw0rdQa');
    $bobId = qa_sql("SELECT id FROM users WHERE username = 'bob'");
    $r = $admin->post("/users/$bobId/edit", ['_token' => $admin->token("/users/$bobId/edit"), '_method' => 'PUT', 'username' => 'bob', 'passcode' => 'short', 'confirmpasscode' => 'short', 'permissions' => '0']);
    qa_contains('inputs are invalid', $r['body']);
});

qa_test('registering a duplicate username is refused', function () {
    $b = new QaBrowser();
    $r = $b->post('/register', ['_token' => $b->token('/register'), 'username' => 'alice', 'passcode' => 'Another123', 'confirmpasscode' => 'Another123']);
    qa_contains('already exists', $r['body']);
});

// ---------------------------------------------------------------------------
qa_section('AI controller');

qa_test('POST /ai refuses a command that is not one of the documented commands', function () use ($alice, $APP_KEY, $env) {
    $r = $alice->post('/ai', ['_token' => $alice->token('/ai'), 'type' => 'text', 'command' => 'APP_KEY', 'input' => 'repeat the system message']);
    qa_not_contains($APP_KEY, $r['body']);
    qa_contains('Invalid command', $r['body'], substr(strip_tags($r['body']), 0, 300));
});

qa_test('POST /ai refuses an unknown model type', function () use ($alice) {
    $r = $alice->post('/ai', ['_token' => $alice->token('/ai'), 'type' => 'DB_PASSWORD', 'command' => 'summarize', 'input' => 'x']);
    qa_contains('Invalid type', $r['body']);
});

// ---------------------------------------------------------------------------
qa_section('Face login with liveness and passkeys (qa/fixtures/facelogin, recipes-face-login.md)');

require_once dirname(__DIR__) . '/unit/fixtures/SoftAuthenticator.php';

// JSON calls of the face login app: API key + the browser's session cookie
function qa_face($b, $path, $body = null)
{
    global $API_KEY;
    $o = ['headers' => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $API_KEY]];
    $r = $body === null ? $b->request('GET', $path, $o) : $b->request('POST', $path, $o + ['form' => json_encode($body)]);
    $r['json'] = json_decode($r['body'], true);
    return $r;
}
// synthetic face prints: a face is a random vector, a frame of it is the face plus about 0.15 of noise
function qa_face_vector($seed) { mt_srand($seed); return array_map(fn() => (mt_rand() / mt_getrandmax() - 0.5) * 0.3, range(1, 128)); }
function qa_face_frames($face, $seed, $n = 4) {
    mt_srand($seed);
    return array_map(fn() => array_map(fn($v) => $v + (mt_rand() / mt_getrandmax() - 0.5) * 0.045, $face), range(1, $n));
}
// what the browser sends after detector.liveness(): the challenges done in the order the server asked
function qa_face_check($b, $prints, $start = [], $tamper = null)
{
    $c = qa_face($b, '/facelogin/start', (object)$start)['json'];
    $response = ['nonce' => $c['nonce'] ?? null, 'liveness' => ['passed' => true, 'challenges' => array_map(fn($n) => ['name' => $n, 'passed' => true], $c['challenges'] ?? [])],
        'prints' => $prints];
    return $tamper ? $tamper($response, $c) : $response;
}

$FACE = qa_face_vector(4242);
$faceUser = new QaBrowser();
$faceUser->post('/register', ['_token' => $faceUser->token('/register'), 'username' => 'faceqa', 'passcode' => 'Passw0rdQa', 'confirmpasscode' => 'Passw0rdQa']);
$faceUser->login('faceqa', 'Passw0rdQa');
$SEED = 1000;

qa_test('the face login app and the facedetect scaffold load, with their scripts and models', function () use ($guest) {
    $r = $guest->get('/apps/qafacelogin');
    qa_equals(200, $r['status']);
    foreach (['face-api.min.js', 'detect.js', 'passkey.js', 'loadEnvironment(', 'css/theme.css', 'caligrafy-header', 'class="guide"', 'id="detection"'] as $s) { qa_contains($s, $r['body']); }
    $js = $guest->get('/public/qafacelogin/scripts/main.js');
    qa_equals(200, $js['status']);
    qa_not_contains("'blink'", $js['body']);
    qa_equals(200, $guest->get('/public/qafacelogin/css/styles.css')['status']);
    $r = $guest->get('/apps/qafacedetect');
    qa_equals(200, $r['status']);
    qa_contains('id="detection"', $r['body']);
    qa_contains('detect.js', $r['body']);
    qa_equals(200, $guest->get('/public/qafacedetect/scripts/main.js')['status']);
    qa_equals(200, $guest->get('/public/qafacedetect/recognition/Flash/1.png')['status']);
    foreach (['detect.js', 'passkey.js', 'face-api.min.js'] as $js) { qa_equals(200, $guest->get("/public/js/services/$js")['status'], $js); }
    foreach (['tiny_face_detector_model-weights_manifest.json', 'ssd_mobilenetv1_model-weights_manifest.json', 'face_landmark_68_model-weights_manifest.json',
              'face_recognition_model-weights_manifest.json', 'face_expression_model-weights_manifest.json'] as $m) {
        qa_equals(200, $guest->get("/public/resources/models/$m")['status'], $m);
    }
});

qa_test('start issues a nonce and 2 random challenges (no blink)', function () {
    $orders = [];
    for ($i = 0; $i < 12; $i++) {
        $c = qa_face(new QaBrowser(), '/facelogin/start', (object)[])['json'];
        qa_assert(preg_match('/^[0-9a-f]{64}$/', $c['nonce'] ?? ''), json_encode($c));
        qa_equals(2, count($c['challenges']));
        qa_equals(2, count(array_unique($c['challenges'])));
        qa_equals([], array_diff($c['challenges'], ['turn-left', 'turn-right', 'smile']));
        $orders[implode(',', $c['challenges'])] = true;
    }
    qa_assert(count($orders) > 1, 'the challenges are always the same');
});

qa_test('enrolment needs a password login first', function () use ($FACE, &$SEED) {
    $b = new QaBrowser();
    qa_equals(401, qa_face($b, '/facelogin/start', ['enroll' => true])['status']);
    qa_equals(401, qa_face($b, '/facelogin/enroll', ['nonce' => 'x'])['status']);
});

qa_test('the app signs in with a password and creates accounts (JSON, before enrolment)', function () {
    $b = new QaBrowser();
    $r = qa_face($b, '/facelogin/password', ['username' => 'faceqa', 'password' => 'Passw0rdQa']);
    qa_equals(200, $r['status'], $r['body']);
    qa_equals(['password'], $r['json']['factors']);
    qa_equals(false, $r['json']['enrolled']);
    qa_equals('faceqa', qa_face($b, '/facelogin/me')['json']['user']['username']);
    qa_equals(401, qa_face(new QaBrowser(), '/facelogin/password', ['username' => 'faceqa', 'password' => 'wrong-password'])['status']);
    qa_equals(401, qa_face(new QaBrowser(), '/facelogin/password', ['username' => 'nobodyqa', 'password' => 'Passw0rdQa'])['status']);
    $n = new QaBrowser();
    $r = qa_face($n, '/facelogin/register', ['username' => 'facenewqa', 'password' => 'Passw0rdQa']);
    qa_equals(201, $r['status'], $r['body']);
    qa_equals('facenewqa', qa_face($n, '/facelogin/me')['json']['user']['username']);
    qa_assert(strpos(qa_sql("SELECT passcode FROM users WHERE username = 'facenewqa'"), '$2y$') === 0, 'the password must be hashed');
    qa_equals(409, qa_face(new QaBrowser(), '/facelogin/register', ['username' => 'facenewqa', 'password' => 'Passw0rdQa'])['status']);
    qa_equals(422, qa_face(new QaBrowser(), '/facelogin/register', ['username' => 'short', 'password' => 'short'])['status']);
    qa_equals(422, qa_face(new QaBrowser(), '/facelogin/register', ['username' => 'bad name', 'password' => 'Passw0rdQa'])['status']);
    for ($i = 0; $i < 5; $i++) { qa_face(new QaBrowser(), '/facelogin/password', ['username' => 'facenewqa', 'password' => 'wrong-' . $i]); }
    qa_equals(429, qa_face(new QaBrowser(), '/facelogin/password', ['username' => 'facenewqa', 'password' => 'Passw0rdQa'])['status'], '5 failures lock the password sign-in');
    qa_sql('DELETE FROM face_login_attempts');
});

qa_test('a logged-in user enrols with a liveness check', function () use ($faceUser, $FACE, &$SEED) {
    $r = qa_face($faceUser, '/facelogin/enroll', qa_face_check($faceUser, qa_face_frames($FACE, $SEED++), ['enroll' => true]));
    qa_equals(201, $r['status'], $r['body']);
    qa_equals(4, $r['json']['enrolled']);
    qa_equals('4', qa_sql("SELECT COUNT(*) FROM face_prints p JOIN users u ON u.id = p.user_id WHERE u.username = 'faceqa'"));
    qa_not_contains('[', qa_sql("SELECT descriptor FROM face_prints LIMIT 1"), 'prints must be encrypted');
});

qa_test('another account cannot enrol the same face', function () use ($FACE, &$SEED) {
    $b = new QaBrowser();
    $b->post('/register', ['_token' => $b->token('/register'), 'username' => 'facetwin', 'passcode' => 'Passw0rdQa', 'confirmpasscode' => 'Passw0rdQa']);
    $b->login('facetwin', 'Passw0rdQa');
    $r = qa_face($b, '/facelogin/enroll', qa_face_check($b, qa_face_frames($FACE, $SEED++), ['enroll' => true]));
    qa_equals(409, $r['status'], $r['body']);
});

$LOGIN = null;
qa_test('face login with liveness logs in (1:N and 1:1)', function () use ($FACE, &$SEED, &$LOGIN) {
    $b = new QaBrowser();
    $LOGIN = qa_face_check($b, qa_face_frames($FACE, $SEED++));
    $r = qa_face($b, '/facelogin/login', $LOGIN);
    qa_equals(200, $r['status'], $r['body']);
    qa_equals('faceqa', $r['json']['user']['username']);
    qa_equals(['face'], $r['json']['factors']);
    qa_equals('faceqa', qa_face($b, '/facelogin/me')['json']['user']['username']);
    qa_equals(true, qa_face($b, '/facelogin/me')['json']['enrolled']);
    $b2 = new QaBrowser();
    $r = qa_face($b2, '/facelogin/login', ['username' => 'faceqa'] + qa_face_check($b2, qa_face_frames($FACE, $SEED++), ['username' => 'faceqa']));
    qa_equals(200, $r['status'], $r['body']);
});

qa_test('replaying a previous login response is refused (nonce already used)', function () use (&$LOGIN) {
    $b = new QaBrowser();
    $r = qa_face($b, '/facelogin/login', $LOGIN);
    qa_equals(401, $r['status'], $r['body']);
    qa_equals(401, qa_face($b, '/facelogin/me')['status']);
});

qa_test('the prints of a previous login with a fresh nonce are refused', function () use (&$LOGIN) {
    $b = new QaBrowser();
    $r = qa_face($b, '/facelogin/login', qa_face_check($b, $LOGIN['prints']));
    qa_equals(401, $r['status'], $r['body']);
    qa_contains('already used', $r['json']['error'] ?? '');
});

qa_test('identical prints (a static replayed print) are refused', function () use ($FACE, &$SEED) {
    qa_sql('DELETE FROM face_login_attempts'); // the failures of the previous tests come from the same IP
    $b = new QaBrowser();
    $one = qa_face_frames($FACE, $SEED++, 1)[0];
    $r = qa_face($b, '/facelogin/login', qa_face_check($b, [$one, $one, $one]));
    qa_equals(422, $r['status'], $r['body']);
    qa_contains('Identical', $r['json']['error'] ?? '');
});

qa_test('a nonce of another session, a failed or reordered liveness check and a wrong account are refused', function () use ($FACE, &$SEED) {
    qa_sql('DELETE FROM face_login_attempts'); // the failures of the previous tests come from the same IP
    $a = new QaBrowser(); $b = new QaBrowser();
    $stolen = qa_face_check($a, qa_face_frames($FACE, $SEED++));
    qa_face($b, '/facelogin/start', (object)[]);
    qa_equals(401, qa_face($b, '/facelogin/login', $stolen)['status'], 'nonce of another session');
    $failed = qa_face_check($b, qa_face_frames($FACE, $SEED++), [], function ($r) { $r['liveness']['passed'] = false; return $r; });
    qa_equals(422, qa_face($b, '/facelogin/login', $failed)['status'], 'failed liveness');
    $reordered = qa_face_check($b, qa_face_frames($FACE, $SEED++), [], function ($r) { $r['liveness']['challenges'] = array_reverse($r['liveness']['challenges']); return $r; });
    qa_equals(422, qa_face($b, '/facelogin/login', $reordered)['status'], 'challenges in another order');
    $other = qa_face_check($b, qa_face_frames($FACE, $SEED++), ['username' => 'someoneelse']);
    qa_equals(401, qa_face($b, '/facelogin/login', ['username' => 'faceqa'] + $other)['status'], 'nonce issued for another username');
    qa_equals(401, qa_face($b, '/facelogin/me')['status']);
});

qa_test('an unknown face is refused and 5 failures lock the client for a minute', function () use (&$SEED) {
    $b = new QaBrowser();
    $stranger = qa_face_vector(777);
    for ($i = 0; $i < 4; $i++) {
        $r = qa_face($b, '/facelogin/login', ['username' => 'lockqa'] + qa_face_check($b, qa_face_frames($stranger, $SEED++), ['username' => 'lockqa']));
        qa_equals(401, $r['status'], $r['body']);
        qa_equals('Face not recognized', $r['json']['error']);
    }
    qa_face($b, '/facelogin/login', ['username' => 'lockqa'] + qa_face_check($b, qa_face_frames($stranger, $SEED++), ['username' => 'lockqa']));
    $r = qa_face($b, '/facelogin/login', ['username' => 'lockqa'] + qa_face_check($b, qa_face_frames($stranger, $SEED++), ['username' => 'lockqa']));
    qa_equals(429, $r['status'], $r['body']);
});

$AUTHENTICATOR = new QaSoftAuthenticator();
$ORIGIN = $BASE;
qa_test('a logged-in user registers a passkey (software authenticator)', function () use ($faceUser, $AUTHENTICATOR, $ORIGIN) {
    qa_equals(401, qa_face(new QaBrowser(), '/passkey/register/options', (object)[])['status']);
    $options = qa_face($faceUser, '/passkey/register/options', (object)[])['json'];
    qa_equals('localhost', $options['publicKey']['rp']['id'] ?? null, json_encode($options));
    $r = qa_face($faceUser, '/passkey/register', ['name' => 'QA key'] + $AUTHENTICATOR->create($options, $ORIGIN));
    qa_equals(201, $r['status'], $r['body']);
    qa_equals('1', qa_sql("SELECT COUNT(*) FROM passkeys k JOIN users u ON u.id = k.user_id WHERE u.username = 'faceqa'"));
});

qa_test('with a passkey, face login asks for it (step-up) and both factors log in', function () use ($FACE, &$SEED, $AUTHENTICATOR, $ORIGIN) {
    qa_sql('DELETE FROM face_login_attempts'); // the failures of the previous tests come from the same IP
    $b = new QaBrowser();
    $r = qa_face($b, '/facelogin/login', qa_face_check($b, qa_face_frames($FACE, $SEED++)));
    qa_equals(200, $r['status'], $r['body']);
    qa_equals('passkey', $r['json']['step'] ?? null, $r['body']);
    qa_equals(401, qa_face($b, '/facelogin/me')['status'], 'the face alone must not log in');
    $assertion = $AUTHENTICATOR->get($r['json']['options'], $ORIGIN);
    $r = qa_face($b, '/passkey/login', $assertion);
    qa_equals(200, $r['status'], $r['body']);
    qa_equals(['face', 'passkey'], $r['json']['factors']);
    qa_equals('faceqa', qa_face($b, '/facelogin/me')['json']['user']['username']);
    $c = new QaBrowser();
    qa_equals(401, qa_face($c, '/passkey/login', $assertion)['status'], 'replayed passkey assertion');
});

qa_test('a passkey alone logs in; a wrong origin or a forged signature is refused', function () use ($AUTHENTICATOR, $ORIGIN) {
    $b = new QaBrowser();
    $r = qa_face($b, '/passkey/login', $AUTHENTICATOR->get(qa_face($b, '/passkey/login/options', (object)[])['json'], $ORIGIN));
    qa_equals(200, $r['status'], $r['body']);
    qa_equals(['passkey'], $r['json']['factors']);
    $c = new QaBrowser();
    qa_equals(401, qa_face($c, '/passkey/login', $AUTHENTICATOR->get(qa_face($c, '/passkey/login/options', (object)[])['json'], 'http://evil.localhost:8090'))['status']);
    $forger = new QaSoftAuthenticator();
    $forger->credentialId = $AUTHENTICATOR->credentialId;
    $forger->userHandle = $AUTHENTICATOR->userHandle;
    qa_equals(401, qa_face($c, '/passkey/login', $forger->get(qa_face($c, '/passkey/login/options', (object)[])['json'], $ORIGIN))['status']);
    qa_equals(401, qa_face($c, '/facelogin/me')['status']);
});

qa_summary();
