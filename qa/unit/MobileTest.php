<?php

use Caligrafy\Mobile;
use Caligrafy\MobileApp;
use Caligrafy\Notify;
use PHPUnit\Framework\TestCase;

/**
 * The phone apps (2.27.0) without a web server: Route::mobile's options, its CORS, the sign-in request it accepts, PKCE,
 * the push signatures for Apple and Firebase, and php caligrafer.php mobile
 */
final class MobileTest extends TestCase
{
    protected function setUp(): void
    {
        Mobile::$options = Mobile::options(['scheme' => 'com.example.books', 'origins' => ['https://admin.example.com']]);
    }

    protected function tearDown(): void
    {
        Mobile::$options = null;
        Mobile::$respond = null;
        MobileApp::$shell = null;
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_HOST']);
        foreach (['APNS_KEY', 'APNS_KEY_ID', 'APNS_TEAM_ID', 'APNS_TOPIC', 'FCM_SERVICE_ACCOUNT'] as $name) putenv($name);
    }

    public function testTheOptionsAreChecked(): void
    {
        $o = Mobile::options(['scheme' => 'Com.Example.Books']);
        $this->assertSame(['com.example.books'], $o['scheme']);
        $this->assertSame(['*'], $o['scopes']);
        $this->assertSame(90, $o['days']);
        $this->assertSame('/mobile', $o['prefix']);
        foreach ([[], ['scheme' => 'https'], ['scheme' => 'ab'], ['scheme' => 'javascript'], ['scheme' => 'x.y', 'days' => 0],
                  ['scheme' => 'x.y', 'scopes' => ['not a scope']], ['scheme' => 'x.y', 'origins' => ['https://a.example/path']], ['scheme' => 'x.y', 'prefix' => '/']] as $bad) {
            try {
                Mobile::options($bad);
                $this->fail('refused: ' . json_encode($bad));
            } catch (InvalidArgumentException $e) {
                $this->assertStringStartsWith('Route::mobile', $e->getMessage());
            }
        }
    }

    public function testOnlyTheAppsAndTheNamedOriginsGetCors(): void
    {
        foreach (['capacitor://localhost', 'https://localhost', 'http://localhost', 'http://localhost:5173', 'http://127.0.0.1:8080', 'https://admin.example.com'] as $origin) {
            $this->assertTrue(Mobile::allowedOrigin($origin), $origin);
        }
        foreach (['https://evil.example', 'http://localhost.evil.example', 'https://localhost:8443', 'null', ''] as $origin) {
            $this->assertFalse(Mobile::allowedOrigin($origin), $origin);
        }
        Mobile::$options = null;
        $this->assertFalse(Mobile::allowedOrigin('capacitor://localhost'), 'no Route::mobile, no CORS');
    }

    public function testAPreflightIsAnsweredWithoutCredentials(): void
    {
        $sent = [];
        Mobile::$respond = function ($status, $headers) use (&$sent) { $sent[] = [$status, $headers]; };
        $_SERVER['HTTP_ORIGIN'] = 'capacitor://localhost';
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $this->assertTrue(Mobile::cors());
        $this->assertSame(204, $sent[0][0]);
        $this->assertSame('capacitor://localhost', $sent[0][1]['Access-Control-Allow-Origin']);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $sent[0][1]);
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $this->assertTrue(Mobile::cors());
        $this->assertSame(403, $sent[1][0]);
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $sent[1][1]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ORIGIN'] = 'https://localhost';
        $this->assertFalse(Mobile::cors(), 'a request goes on to its route');
        $this->assertSame([null, ['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://localhost']], $sent[2]);
    }

    public function testTheSignInRequestOfAnApp(): void
    {
        $challenge = Mobile::challenge('a-verifier-of-at-least-forty-three-characters-0123');
        $input = Mobile::authorizeInput(['challenge' => $challenge, 'state' => 'state-0123456789abcdef', 'device' => "Maya's iPhone <script>"]);
        $this->assertSame('com.example.books', $input['scheme'], 'the only scheme of Route::mobile');
        $this->assertSame("Maya's iPhone script", $input['device']);
        $this->assertSame('com.example.books://auth?state=state-0123456789abcdef', $input['back']);
        $this->assertIsString(Mobile::authorizeInput(['scheme' => 'com.other.app', 'challenge' => $challenge, 'state' => 'state-0123456789abcdef']));
        $this->assertIsString(Mobile::authorizeInput(['challenge' => 'plain', 'state' => 'state-0123456789abcdef']), 'S256 only');
        $this->assertIsString(Mobile::authorizeInput(['challenge' => $challenge, 'state' => 'state-0123456789abcdef', 'method' => 'plain']));
        $this->assertIsString(Mobile::authorizeInput(['challenge' => $challenge, 'state' => 'short']));
    }

    public function testADevelopmentRedirectOnlyFromASiteOnThisComputer(): void
    {
        $challenge = Mobile::challenge(str_repeat('v', 43));
        $request = ['redirect' => 'http://localhost:5173', 'challenge' => $challenge, 'state' => 'state-0123456789abcdef'];
        $_SERVER['HTTP_HOST'] = 'books.caligrafy.com';
        $this->assertIsString(Mobile::authorizeInput($request), 'never online');
        $_SERVER['HTTP_HOST'] = 'localhost:8080';
        $this->assertSame('http://localhost:5173/?mobile_auth=1&state=state-0123456789abcdef', Mobile::authorizeInput($request)['back']);
        $this->assertIsString(Mobile::authorizeInput(['redirect' => 'https://evil.example'] + $request));
    }

    public function testThePkceChallengeIsRfc7636(): void
    {
        // RFC 7636, appendix B
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Mobile::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
    }

    public function testAppleTokensAreEs256(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);
        putenv('APNS_KEY=' . base64_encode($pem));
        putenv('APNS_KEY_ID=KEY1234567');
        putenv('APNS_TEAM_ID=TEAM123456');
        putenv('APNS_TOPIC=com.example.books');
        $this->assertTrue(Notify::deviceConfigured('apns'));
        \Caligrafy\Cache::forget('caligrafy.notify.apns-jwt');
        list($header, $claims, $signature) = explode('.', Notify::apnsJwt());
        $this->assertSame(['alg' => 'ES256', 'kid' => 'KEY1234567'], json_decode(base64_decode(strtr($header, '-_', '+/')), true));
        $this->assertSame('TEAM123456', json_decode(base64_decode(strtr($claims, '-_', '+/')), true)['iss']);
        // R || S back to DER: the public key verifies it
        $raw = base64_decode(strtr($signature, '-_', '+/') . str_repeat('=', (4 - strlen($signature) % 4) % 4));
        $this->assertSame(64, strlen($raw));
        $int = function ($bytes) { $bytes = ltrim($bytes, "\x00"); if (ord($bytes[0]) & 0x80) $bytes = "\x00" . $bytes; return "\x02" . chr(strlen($bytes)) . $bytes; };
        $der = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
        $der = "\x30" . chr(strlen($der)) . $der;
        $public = openssl_pkey_get_details($key)['key'];
        $this->assertSame(1, openssl_verify("$header.$claims", $der, $public, OPENSSL_ALGO_SHA256));
        \Caligrafy\Cache::forget('caligrafy.notify.apns-jwt');
    }

    public function testWhatAppleAndFirebaseReceiveAndWhenADeviceIsGone(): void
    {
        $message = Notify::message(['title' => 'Bingo!', 'body' => 'Row 3 is complete.', 'url' => 'https://bingo.example/cards/3', 'tag' => 'card-3', 'urgency' => 'high']);
        $apple = Notify::devicePayload('apns', $message);
        $this->assertSame(['title' => 'Bingo!', 'body' => 'Row 3 is complete.'], $apple['aps']['alert']);
        $this->assertSame('card-3', $apple['aps']['thread-id']);
        $this->assertSame('https://bingo.example/cards/3', $apple['url']);
        $google = Notify::devicePayload('fcm', $message);
        $this->assertSame(['title' => 'Bingo!', 'body' => 'Row 3 is complete.'], $google['notification']);
        $this->assertSame(['priority' => 'HIGH', 'ttl' => '86400s', 'collapse_key' => 'card-3'], $google['android']);
        $this->assertTrue(Notify::deviceGone('apns', 410, 'Unregistered'));
        $this->assertTrue(Notify::deviceGone('apns', 400, 'BadDeviceToken'));
        $this->assertFalse(Notify::deviceGone('apns', 429, 'TooManyRequests'));
        $this->assertTrue(Notify::deviceGone('fcm', 404, 'UNREGISTERED'));
        $this->assertFalse(Notify::deviceGone('fcm', 503, 'UNAVAILABLE'));
    }

    public function testTheFirebaseServiceAccountIsReadAsJsonOrBase64(): void
    {
        $account = ['project_id' => 'books', 'client_email' => 'push@books.iam.gserviceaccount.com', 'private_key' => '-----BEGIN PRIVATE KEY-----'];
        putenv('FCM_SERVICE_ACCOUNT=' . json_encode($account));
        $this->assertSame('books', Notify::serviceAccount()['project_id']);
        putenv('FCM_SERVICE_ACCOUNT=' . base64_encode(json_encode($account)));
        $this->assertTrue(Notify::deviceConfigured('fcm'));
        putenv('FCM_SERVICE_ACCOUNT={"project_id":"books"}');
        $this->assertNull(Notify::serviceAccount(), 'incomplete');
    }

    public function testAppIdsAndNames(): void
    {
        foreach (['com.example.books', 'app.caligrafy.holidaybingo2'] as $id) $this->assertTrue(MobileApp::validId($id), $id);
        foreach (['books', 'com.Example.books', 'com.1books.app', 'com.example-books.app', 'com.holiday_bingo.app', 'com.', '.com.x'] as $id) $this->assertFalse(MobileApp::validId($id), $id);
        $this->assertSame('com.holidaybingo.bingo', MobileApp::defaultId('holiday-bingo', 'bingo'));
        $this->assertSame('com.caligrafy.app', MobileApp::defaultId('123', '-'));
        $this->assertTrue(MobileApp::validName('bingo'));
        $this->assertFalse(MobileApp::validName('css'), 'a folder of public/');
        $this->assertSame('https://books.example.com', MobileApp::defaultUrl('books', 'https://books.example.com/'));
        $this->assertSame('https://holiday-bingo.caligrafy.com', MobileApp::defaultUrl('Holiday Bingo', 'http://localhost:8080'));
        // the Docker server: /var/www/html is no name; its database is, unless a placeholder
        putenv('DB_DATABASE=bookshelf');
        $this->assertSame('bookshelf', MobileApp::projectName('/var/www/html'));
        putenv('DB_DATABASE=caligrafy');
        $this->assertNull(MobileApp::projectName('/var/www/html'));
        putenv('DB_DATABASE');
        $this->assertSame('books', MobileApp::projectName('/Users/maya/code/books'));
    }

    public function testMobileCreatesTheAppAndItsWorkflow(): void
    {
        $root = sys_get_temp_dir() . '/caligrafy-mobile-' . bin2hex(random_bytes(4));
        mkdir("$root/framework/librairies", 0755, true);
        mkdir("$root/public", 0755, true);
        self::copy(dirname(__DIR__, 2) . '/framework/librairies/mobile', "$root/framework/librairies/mobile");
        $lines = [];
        MobileApp::$shell = function () { return 1; };   // no Node.js here
        $status = (new MobileApp($root, function ($line) use (&$lines) { $lines[] = $line; }))->create('bingo', ['id' => 'com.holiday.bingo', 'display' => 'Holiday Bingo', 'url' => 'https://bingo.example.com']);
        try {
            $this->assertSame(0, $status, implode("\n", $lines));
            $config = json_decode(file_get_contents("$root/public/bingo/capacitor.config.json"), true);
            $this->assertSame(['com.holiday.bingo', 'Holiday Bingo', 'dist'], [$config['appId'], $config['appName'], $config['webDir']]);
            $this->assertSame('bingo', json_decode(file_get_contents("$root/public/bingo/package.json"), true)['name']);
            $this->assertStringContainsString('VITE_API_ROUTE=https://bingo.example.com', file_get_contents("$root/public/bingo/.env.production"));
            $this->assertStringContainsString('<title>Holiday Bingo</title>', file_get_contents("$root/public/bingo/index.html"));
            $workflow = file_get_contents("$root/.github/workflows/mobile-bingo.yml");
            $this->assertStringContainsString('working-directory: public/bingo', $workflow);
            $this->assertStringNotContainsString('__NAME__', $workflow);
            $this->assertContains("     Route::mobile(['scheme' => 'com.holiday.bingo']);", $lines);
            $this->assertNotEmpty(array_filter($lines, function ($l) { return strpos($l, 'Node.js 22+ is not available here') === 0; }), 'without Node.js it says what to run');
            $this->assertSame(1, (new MobileApp($root, function () {}))->create('bingo'), 'never over an existing folder');
            $this->assertSame(1, (new MobileApp($root, function () {}))->create('quiz', ['url' => 'http://insecure.example']), 'https only');
        } finally {
            self::remove($root);
        }
    }

    private static function copy($from, $to)
    {
        mkdir($to, 0755, true);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $target = $to . substr($item->getPathname(), strlen($from));
            $item->isDir() ? @mkdir($target, 0755, true) : copy($item->getPathname(), $target);
        }
    }

    private static function remove($path)
    {
        if (strpos($path, sys_get_temp_dir() . '/caligrafy-mobile-') !== 0) return;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
