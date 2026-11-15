<?php

use Caligrafy\Agent;
use Caligrafy\Doctor;
use Caligrafy\FeatureMissing;
use Caligrafy\Features;
use Caligrafy\Notify;
use Caligrafy\Passkey;
use Caligrafy\Snap\Planner;
use Caligrafy\Updater;
use Caligrafy\Upgrader;
use Caligrafy\Webhook;
use PHPUnit\Framework\TestCase;

/**
 * The optional features (2.26.0): the SDKs only some applications use (php caligrafer.php add <feature>, Composer
 * faked), the message of each framework class without its SDK, doctor and update, the FaceDetect downloads (faked:
 * no network in the tests)
 */
final class FeaturesTest extends TestCase
{
    private string $dir;
    private array $commands = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/quill-features-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0755, true);
        Features::$absent = [];
        Features::$fetcher = null;
    }

    protected function tearDown(): void
    {
        Features::$absent = [];
        Features::$fetcher = null;
        Upgrader::$composer = null;
        Webhook::$logger = null;
        foreach (['ANTHROPIC_API_KEY', 'GROQ_API_KEY', 'QA_FEATURE_SECRET'] as $name) putenv($name);
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function write(string $path, string $content): void
    {
        @mkdir(dirname("$this->dir/$path"), 0755, true);
        file_put_contents("$this->dir/$path", $content);
    }

    private function absent(string ...$features): void
    {
        foreach ($features as $feature) {
            Features::$absent = array_merge(Features::$absent, array_keys(Features::FEATURES[$feature]['packages']));
        }
    }

    // ---- the message of each class without its SDK ----

    public function testEachFeatureNamesItsCommand(): void
    {
        foreach (array_keys(Features::FEATURES) as $feature) {
            $this->absent($feature);
            try {
                Features::need($feature, 'Something');
                $this->fail("$feature: no exception");
            } catch (FeatureMissing $e) {
                $this->assertStringStartsWith('Something needs ', $e->getMessage());
                $this->assertStringContainsString("php caligrafer.php add $feature", $e->getMessage());
                $this->assertStringContainsString('optional since Caligrafy Quill ' . Features::SINCE, $e->getMessage());
            }
            Features::$absent = [];
        }
        $this->absent('push');
        $this->assertSame('X needs minishlink/web-push and guzzlehttp/guzzle, which this project does not install (optional since Caligrafy Quill 2.26.0): run php caligrafer.php add push',
            Features::message('push', 'X'));
    }

    public function testAnInstalledFeaturePasses(): void
    {
        Features::$absent = ['stripe/stripe-php'];
        $this->assertFalse(Features::installed('payments'));
        $this->assertSame(['stripe/stripe-php' => '^23.0'], Features::missing('payments'));
        // a package whose class loads is installed (phpdotenv is a dependency of the framework)
        $this->assertTrue(Features::installedPackage('vlucas/phpdotenv'));
    }

    public function testPaymentWithoutStripe(): void
    {
        require_once dirname(__DIR__, 2) . '/framework/core/Payment.php';
        defined('APP_ENV') || define('APP_ENV', 'local');
        defined('PAY_PUBLIC_KEY_TEST') || define('PAY_PUBLIC_KEY_TEST', 'pk_test_1234');
        defined('PAY_PRIVATE_KEY_TEST') || define('PAY_PRIVATE_KEY_TEST', 'sk_test_1234');
        $this->absent('payments');
        $this->expectException(FeatureMissing::class);
        $this->expectExceptionMessage('Caligrafy\\Payment needs stripe/stripe-php, which this project does not install (optional since Caligrafy Quill 2.26.0): run php caligrafer.php add payments');
        new Caligrafy\Payment();
    }

    public function testStripeWebhookWithoutStripeIsNotSetUp(): void
    {
        $this->absent('payments');
        $log = '';
        Webhook::$logger = function ($message) use (&$log) { $log .= $message; };
        putenv('QA_FEATURE_SECRET=whsec_x');
        $answer = Webhook::process('/hooks/stripe', 'qa_record_event', ['verify' => 'stripe', 'secret_env' => 'QA_FEATURE_SECRET'], '{}', ['Stripe-Signature' => 't=1,v1=x']);
        $this->assertSame(500, $answer[0]);
        $this->assertStringContainsString("Route::webhook with 'verify' => 'stripe' needs stripe/stripe-php", $log);
        $this->assertStringContainsString('php caligrafer.php add payments', $log);
    }

    public function testAgentWithGroqWithoutGroqPhp(): void
    {
        $this->absent('ai');
        try {
            new Agent('gsk_test', 'groq');
            $this->fail('no exception');
        } catch (FeatureMissing $e) {
            $this->assertStringContainsString('new Agent() with Groq (AGENT_PROVIDER=groq, the default) needs lucianotonet/groq-php', $e->getMessage());
            $this->assertStringContainsString('php caligrafer.php add ai', $e->getMessage());
        }
        // the other providers need no SDK
        $this->assertSame('ollama', (new Agent('', 'ollama'))->provider());
    }

    public function testNotifyKeysWithoutWebPush(): void
    {
        $this->absent('push');
        $this->expectException(FeatureMissing::class);
        $this->expectExceptionMessage('Web push (Caligrafy\\Notify) needs minishlink/web-push and guzzlehttp/guzzle');
        Notify::keys();
    }

    public function testPasskeyWithoutWebAuthn(): void
    {
        $_SESSION = [];
        $this->absent('passkeys');
        $this->expectException(FeatureMissing::class);
        $this->expectExceptionMessage('Caligrafy\\Passkey needs lbuchs/webauthn, which this project does not install (optional since Caligrafy Quill 2.26.0): run php caligrafer.php add passkeys');
        (new Passkey(null, 'quill.test', 'https://quill.test'))->registrationOptions(1, 'ada');
    }

    public function testSnapWithAKeyButNoSdkAnswersWithoutTheModel(): void
    {
        $this->absent('snap');
        putenv('ANTHROPIC_API_KEY=sk-ant-test');
        $this->assertFalse(Planner::on());
        Features::$absent = [];
        if (Features::installed('snap')) $this->assertTrue(Planner::on());
    }

    // ---- php caligrafer.php add ----

    private function add(array $features, int $status = 0): array
    {
        $lines = [];
        $code = Features::add($features, $this->dir, function ($command) use ($status) { $this->commands[] = $command; return $status; },
            function ($line) use (&$lines) { $lines[] = $line; });
        return [$code, implode("\n", $lines)];
    }

    public function testAddRunsComposerRequireWithThePinnedConstraints(): void
    {
        $this->absent('payments', 'snap');
        [$code, $out] = $this->add(['payments', 'snap']);
        $this->assertSame(0, $code, $out);
        $this->assertSame(["composer require --no-interaction 'stripe/stripe-php:^23.0' 'anthropic-ai/sdk:^1.8' 'guzzlehttp/guzzle:^7.9'"], $this->commands);
        $this->assertStringContainsString('Added payments, snap.', $out);
    }

    public function testAddSkipsWhatIsInstalledAndReportsComposerFailures(): void
    {
        $this->absent('passkeys');
        [$code, $out] = $this->add(['passkeys'], 2);
        $this->assertSame(1, $code);
        $this->assertStringContainsString("composer require did not finish: run it again (composer require --no-interaction 'lbuchs/webauthn:^2.2')", $out);
        // installed: no composer at all
        $this->commands = [];
        Features::$absent = [];
        if (Features::installed('passkeys')) {
            [$code, $out] = $this->add(['passkeys']);
            $this->assertSame(0, $code);
            $this->assertSame([], $this->commands);
            $this->assertStringContainsString('passkeys: lbuchs/webauthn already installed', $out);
        }
    }

    public function testAddListsTheFeaturesAndRefusesUnknownOnes(): void
    {
        [$code, $out] = $this->add([]);
        $this->assertSame(0, $code);
        foreach (Features::names() as $feature) $this->assertStringContainsString("  $feature", $out);
        [$code, $out] = $this->add(['payments', 'blockchain']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Unknown feature: blockchain', $out);
        $this->assertSame([], $this->commands);
    }

    // ---- what a project uses: doctor, update, upgrade ----

    public function testUsedReadsTheSettingsAndTheCode(): void
    {
        $this->write('.env', "PAY_PRIVATE_KEY_TEST=sk_test_1234\nGROQ_API_KEY=\nANTHROPIC_API_KEY=sk-ant-real # the key\nVAPID_PRIVATE_KEY=\n");
        $this->write('application/config/routing/web.php', "<?php Route::auth(['passkeys' => true]);");
        $this->write('application/services/Shop.php', "<?php \$p = new Payment(); ");
        $used = Features::used($this->dir);
        $this->assertSame(['payments', 'passkeys', 'snap'], array_keys($used));   // the placeholder key alone would not count
        $this->assertSame(['application/services/Shop.php uses it'], $used['payments']);
        $this->assertSame(['ANTHROPIC_API_KEY is set in .env'], $used['snap']);
        $this->assertStringNotContainsString('sk-ant-real', json_encode($used));   // names only, never values
        // new Agent() counts for Groq only when Groq is the provider
        $this->write('application/services/Ask.php', '<?php $a = new Agent();');
        $this->assertArrayHasKey('ai', Features::used($this->dir));
        $this->assertArrayNotHasKey('ai', Features::used($this->dir, fn ($name) => $name === 'AGENT_PROVIDER' ? 'ollama' : ''));
    }

    public function testDoctorReportsAConfiguredFeatureWithoutItsSdk(): void
    {
        $this->absent('ai', 'snap');
        putenv('GROQ_API_KEY=gsk_feature_test');
        putenv('ANTHROPIC_API_KEY=sk-ant-feature-test');
        $doctor = new Doctor($this->dir);
        (new ReflectionMethod(Doctor::class, 'optionalFeatures'))->invoke($doctor);
        $results = $doctor->results();
        $this->assertCount(2, $results);
        $this->assertSame(['FAIL', 'Optional SDK', 'php caligrafer.php add ai'], [$results[0]['status'], $results[0]['name'], $results[0]['fix']]);
        $this->assertStringContainsString('lucianotonet/groq-php not installed (GROQ_API_KEY is set in .env)', $results[0]['message']);
        $this->assertSame(['WARN', 'php caligrafer.php add snap'], [$results[1]['status'], $results[1]['fix']]);
        $this->assertStringNotContainsString('gsk_feature_test', json_encode($results));
    }

    public function testDoctorReportsAFaceDetectAppWithoutTheModels(): void
    {
        $this->write('public/faces/scripts/main.js', 'const detector = Vue.markRaw(new FaceDetector("detection"));');
        $doctor = new Doctor($this->dir);
        (new ReflectionMethod(Doctor::class, 'optionalFeatures'))->invoke($doctor);
        $this->assertSame([['check' => 'project', 'name' => 'FaceDetect models', 'status' => 'FAIL',
            'message' => 'public/faces use face-api, but public/resources/models lacks its models (not in the release since 2.26.0)', 'fix' => 'php caligrafer.php add facedetect']], $doctor->results());
    }

    public function testUpdateAddsBackThePackageOfAFeatureTheProjectUses(): void
    {
        $release = function ($version, array $require) {
            $root = "$this->dir/release-$version";
            @mkdir("$root/framework/core", 0755, true);
            file_put_contents("$root/framework/core/Framework.php", "<?php const VERSION = '$version';");
            file_put_contents("$root/composer.json", json_encode(['require' => $require]));
            return $root;
        };
        $old = $release('1.0.0', ['php' => '^8.3', 'stripe/stripe-php' => '^23.0', 'anthropic-ai/sdk' => '^1.8']);
        $new = $release('1.1.0', ['php' => '^8.3']);
        // the project: 1.0.0, uses payments (code) and snap (.env), and its composer.json lost the Stripe entry
        $this->write('project/framework/core/Framework.php', "<?php const VERSION = '1.0.0';");
        $this->write('project/composer.json', json_encode(['require' => ['php' => '^8.3', 'anthropic-ai/sdk' => '^1.8']]));
        $this->write('project/.env', "ANTHROPIC_API_KEY=sk-ant-x\n");
        $this->write('project/application/services/Shop.php', '<?php new \\Caligrafy\\Payment();');
        $fetch = fn ($v) => $v === 'latest' ? '1.1.0' : ($v === '1.0.0' ? $old : ($v === '1.1.0' ? $new : null));
        ob_start();
        $code = (new Updater("$this->dir/project", $fetch, function ($command) { $this->commands[] = $command; return 0; }))->run(null, false, true, true);
        $out = (string) ob_get_clean();
        $this->assertSame(0, $code, $out);
        $require = json_decode(file_get_contents("$this->dir/project/composer.json"), true)['require'];
        $this->assertSame('^23.0', $require['stripe/stripe-php']);     // added back: the project uses payments
        $this->assertSame('^1.8', $require['anthropic-ai/sdk']);       // never removed although the release dropped it
        $this->assertArrayNotHasKey('lucianotonet/groq-php', $require); // not used: not added
        $this->assertStringContainsString('kept        composer.json stripe/stripe-php ^23.0: the project uses payments (application/services/Shop.php uses it)', $out);
        // snap (ANTHROPIC_API_KEY) also needs Guzzle, which the project's composer.json does not list
        $this->assertSame('^7.9', $require['guzzlehttp/guzzle']);
        $this->assertSame("composer update --no-interaction 'stripe/stripe-php' 'guzzlehttp/guzzle'", $this->commands[0]);
    }

    public function testUpgradeReportsAndAddsTheSdkOfAFeatureTheProjectUses(): void
    {
        $this->write('framework/settings/application/views/x.pug', 'p x');
        $this->write('application/views/x.pug', 'p x');
        $this->write('composer.json', json_encode(['require' => ['php' => '^8.3']]));
        $this->write('application/config/routing/web.php', "<?php Route::auth(['passkeys' => true]);");
        $this->absent('passkeys');
        Upgrader::$composer = function ($command) { $this->commands[] = $command; return 0; };
        ob_start();
        (new Upgrader($this->dir, false))->run();
        $dry = (string) ob_get_clean();
        $this->assertStringContainsString('missing    lbuchs/webauthn: the project uses passkeys (application/config/routing/web.php uses it): php caligrafer.php add passkeys', $dry);
        $this->assertSame([], $this->commands);
        ob_start();
        (new Upgrader($this->dir, true))->run();
        $applied = (string) ob_get_clean();
        $this->assertStringContainsString('added      lbuchs/webauthn', $applied);
        $this->assertSame(["composer require --no-interaction 'lbuchs/webauthn:^2.2'"], $this->commands);
    }

    // ---- FaceDetect downloads ----

    private function files(array $bodies): array
    {
        $files = [];
        foreach ($bodies as $name => $body) {
            $files[] = ['path' => "$this->dir/models/$name", 'sha256' => hash('sha256', $body), 'size' => strlen($body),
                'urls' => ["https://first.test/$name", "https://second.test/$name"]];
        }
        return $files;
    }

    public function testDownloadChecksEachFileAndUsesTheMirror(): void
    {
        $bodies = ['a.bin' => 'model a', 'b.json' => '{"b":1}'];
        $asked = [];
        Features::$fetcher = function ($url) use ($bodies, &$asked) {
            $asked[] = $url;
            return strpos($url, 'first.test/b.json') !== false ? null : $bodies[basename($url)];   // the first host misses b
        };
        $lines = Features::download($this->files($bodies));
        $this->assertSame('model a', file_get_contents("$this->dir/models/a.bin"));
        $this->assertSame('{"b":1}', file_get_contents("$this->dir/models/b.json"));
        $this->assertSame(['https://first.test/a.bin', 'https://first.test/b.json', 'https://second.test/b.json'], $asked);
        $this->assertStringContainsString('added    2 file(s)', $lines[0]);
        $this->assertStringContainsString('(SHA-256 checked)', $lines[0]);
        // again: everything is in place, nothing is downloaded
        $asked = [];
        $this->assertStringStartsWith('in place 2 file(s)', Features::download($this->files($bodies))[0]);
        $this->assertSame([], $asked);
    }

    public function testDownloadWritesNothingOfflineOrOnABadHash(): void
    {
        $bodies = ['a.bin' => 'model a', 'b.bin' => 'model b'];
        Features::$fetcher = fn ($url) => basename($url) === 'a.bin' ? 'model a' : null;
        try {
            Features::download($this->files($bodies));
            $this->fail('no exception offline');
        } catch (RuntimeException $e) {
            $this->assertSame('b.bin could not be downloaded from first.test or second.test (offline?): nothing was written. Connect to the internet and run the command again (php caligrafer.php add facedetect).', $e->getMessage());
        }
        $this->assertFileDoesNotExist("$this->dir/models/a.bin");
        Features::$fetcher = fn ($url) => 'tampered';
        try {
            Features::download($this->files($bodies));
            $this->fail('no exception on a bad hash');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('does not match its pinned SHA-256: nothing was written', $e->getMessage());
        }
        $this->assertDirectoryDoesNotExist("$this->dir/models");
    }

    public function testDownloadKeepsAFileThatIsAlreadyThere(): void
    {
        $this->write('models/a.bin', 'my own model');
        Features::$fetcher = fn ($url) => $this->fail('nothing to download');
        $lines = Features::download($this->files(['a.bin' => 'model a']));
        $this->assertSame('my own model', file_get_contents("$this->dir/models/a.bin"));
        $this->assertStringContainsString('(not the pinned file: yours)', $lines[0]);
    }

    public function testTheFaceDetectPinsMatchTheFilesOfTheRepository(): void
    {
        $root = dirname(__DIR__, 2);
        if (!is_dir("$root/" . Features::MODELS_DIR)) $this->markTestSkipped('no models in this checkout');
        foreach (array_merge(Features::modelFiles($root), Features::pictureFiles("$root/framework/plugins/facedetect")) as $file) {
            $this->assertSame($file['sha256'], hash_file('sha256', $file['path']), $file['path']);
            $this->assertSame($file['size'], filesize($file['path']), $file['path']);
            // pinned, versioned sources only
            $this->assertMatchesRegularExpression(strpos($file['path'], '/Flash/') ? '#(/v2\.25\.0/|@v2\.25\.0/)#' : '#@vladmandic/face-api@1\.7\.15/model/#', $file['urls'][0]);
            $this->assertCount(2, $file['urls']);
        }
        $this->assertTrue(Features::facedetectReady($root));
        $this->assertFalse(Features::facedetectReady($this->dir));
    }
}
