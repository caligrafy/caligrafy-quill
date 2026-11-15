<?php
/**
 * Features.php
 * The optional parts of Caligrafy Quill, installed when an application uses them (php caligrafer.php add <feature>)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * A feature whose SDK (or files) this project does not install: the message names the command that adds it
 */
class FeatureMissing extends \LogicException {}

/**
 * Class Features (2.26.0)
 * The SDKs that only some applications use are no longer in Caligrafy's composer.json (they are suggested): a project
 * adds the one it needs with php caligrafer.php add <feature>, which runs composer require with the pinned constraint.
 * The framework classes that use an SDK call Features::need() first: without it they throw FeatureMissing, whose
 * message names the command, never a fatal "class not found".
 *
 * FaceDetect's models (@vladmandic/face-api 1.7.15) and the pictures of its recognition example are not in the release
 * either: php caligrafer.php add facedetect (and php caligrafer.php facedetect <app>) download them from pinned,
 * versioned sources and check the SHA-256 of each file before anything is written.
 *
 * used() tells which features a project uses (its .env settings and its application/ code): doctor reports the ones
 * whose SDK is missing, update keeps (or adds back) their packages in composer.json.
 */
class Features
{
    const SINCE = '2.26.0';

    // feature => what it is, its packages (name => constraint), the .env settings that mean it is configured and the
    // application/ code that uses it
    const FEATURES = array(
        'payments' => array(
            'title' => 'Stripe payments (Caligrafy\\Payment, Stripe webhooks)',
            'packages' => array('stripe/stripe-php' => '^23.0'),
            'settings' => array('PAY_PRIVATE_KEY_TEST', 'PAY_PRIVATE_KEY_PRODUCTION', 'STRIPE_WEBHOOK_SECRET'),
            'code' => '/new\s+\\\\?(Caligrafy\\\\)?Payment\s*\(|\\\\?Stripe\\\\[A-Z]|[\'"]verify[\'"]\s*=>\s*[\'"]stripe[\'"]/',
        ),
        'ai' => array(
            'title' => 'Caligrafy\\Agent with Groq (groq-php)',
            'packages' => array('lucianotonet/groq-php' => '^1.4'),
            'settings' => array('GROQ_API_KEY'),
            'code' => '/LucianoTonet\\\\GroqPHP/',
            // new Agent() talks to Groq through groq-php when AGENT_PROVIDER is groq (the default)
            'agent' => true,
        ),
        'push' => array(
            'title' => 'web push notifications (Caligrafy\\Notify)',
            'packages' => array('minishlink/web-push' => '^11.0', 'guzzlehttp/guzzle' => '^7.9'),
            'settings' => array('VAPID_PRIVATE_KEY'),
            'code' => '/Minishlink\\\\WebPush/',
        ),
        'passkeys' => array(
            'title' => 'passkeys (Caligrafy\\Passkey, Route::auth with \'passkeys\' => true)',
            'packages' => array('lbuchs/webauthn' => '^2.2'),
            'settings' => array('PASSKEY_RP_ID', 'PASSKEY_ORIGIN'),
            'code' => '/[\'"]passkeys[\'"]\s*=>\s*true|new\s+\\\\?(Caligrafy\\\\)?Passkey\s*\(|lbuchs\\\\WebAuthn/',
        ),
        'snap' => array(
            'title' => 'the Snap assistant with Claude (anthropic-ai/sdk)',
            'packages' => array('anthropic-ai/sdk' => '^1.8', 'guzzlehttp/guzzle' => '^7.9'),
            'settings' => array('ANTHROPIC_API_KEY'),
            'code' => '/\\\\?Anthropic\\\\(Client|Messages|Core)\b/',
        ),
        'async' => array(
            'title' => 'the async server (php caligrafer.php async start, ASYNC=on in the production image)',
            'packages' => array('workerman/workerman' => '^5.1'),
            'settings' => array(),
            'code' => '/(?!)/',
            // used when .env says ASYNC=on (Features::used reads the settings with a value)
            'onSetting' => array('ASYNC' => array('on', 'true')),
        ),
    );

    // a class of each package: present when the package is installed
    const CLASSES = array(
        'stripe/stripe-php' => 'Stripe\\StripeClient',
        'lucianotonet/groq-php' => 'LucianoTonet\\GroqPHP\\Groq',
        'minishlink/web-push' => 'Minishlink\\WebPush\\WebPush',
        'guzzlehttp/guzzle' => 'GuzzleHttp\\Client',
        'workerman/workerman' => 'Workerman\\Worker',
        'lbuchs/webauthn' => 'lbuchs\\WebAuthn\\WebAuthn',
        'anthropic-ai/sdk' => 'Anthropic\\Client',
    );

    // values of framework/settings/.env.example that only mean "not configured yet"
    const PLACEHOLDERS = array('pk_test_1234', 'sk_test_1234', 'pk_live_1234', 'sk_live_1234');

    // FaceDetect: @vladmandic/face-api 1.7.15's models (public/resources/models) and the pictures of the recognition
    // example (the app's recognition/Flash/), each with its SHA-256 and size. Models: the npm package, from jsDelivr
    // then unpkg. Pictures: Caligrafy Quill's v2.25.0 tag (the last release that shipped them), from GitHub then jsDelivr
    const FACE_API = '1.7.15';
    const MODELS_DIR = 'public/resources/models';
    const MODELS = array(
        'age_gender_model-weights_manifest.json' => array('74b60bf3ebf981bf4e661e9cca3a185614e10b25d797118b62e2a68d3ff2569f', 8392),
        'age_gender_model.bin' => array('49f92bbc0afef2342c4385e4a2b0acb6d7a727c6f0aa539499719a81acf9abaa', 429708),
        'face_expression_model-weights_manifest.json' => array('5b349c17c7836da506e03422cd6461c74085d8bb2713ad4114647dc5d28174c8', 6980),
        'face_expression_model.bin' => array('9a9840f2cf1f4c7eab95f197512569345c00d2426754d4608b92af30e0300f3d', 329468),
        'face_landmark_68_model-weights_manifest.json' => array('ca4886639f86e99b39fed0c155f81b63317225773bd9616716e887b0153389c9', 8485),
        'face_landmark_68_model.bin' => array('4611ef65c87d836d03d684b30eec4d195d8b219fa1dd58fc58945831c6b9299b', 356840),
        'face_landmark_68_tiny_model-weights_manifest.json' => array('9a1a5dd19fd814fd5095c3cb58f8e0d3193b8c5b9a6715b30a69d559aea31ad4', 4806),
        'face_landmark_68_tiny_model.bin' => array('b98e9f2f7da76f8a6dda9741a36ed485b224b889d552de2b2c1bb16217f67bfc', 77224),
        'face_recognition_model-weights_manifest.json' => array('cbaffa501b0b9275a12b63357a6843e7e30c054e1c9151e1a5f879b26e32986b', 19615),
        'face_recognition_model.bin' => array('b413e420d6840b2775fba32008db6f3cddb07d485967fb42cfcf379c16a8c589', 6444032),
        'ssd_mobilenetv1_model-weights_manifest.json' => array('888f744dffbc84f5f060d1f8427d9742341943a2dfaaf9205d10029b7e7a2955', 28233),
        'ssd_mobilenetv1_model.bin' => array('2835640602d53718aa6ae9a42896327eaa21beefed056c218d32ca7d79e1e2f8', 5616957),
        'tiny_face_detector_model-weights_manifest.json' => array('5d1af4849ac48d5b985f4a9b16010c512353ddd6fcc63d50fd0bc9e9e64296e5', 3219),
        'tiny_face_detector_model.bin' => array('b7503ce7df31039b1c43316a9b865cab6a70dd748cc602d3fa28b551503c3871', 193321),
    );
    const MODEL_SOURCES = array(
        'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model/',
        'https://unpkg.com/@vladmandic/face-api@1.7.15/model/',
    );
    const PICTURES_DIR = 'recognition/Flash';
    const PICTURES = array(
        '1.png' => array('81b454f0a16d6431b2a11a0a59fcac7c7eb1c075a1cd8f7f6c4c88715d717cab', 1204888),
        '2.png' => array('5b1abd2522670f7cbf2e14ad507b70962c0389b9f78320f83fc11b182838a726', 385485),
        '3.png' => array('b5f8446a751fc2ccf0f0b862782efa1f99ce79041dbcc01272f14f0f37fe3817', 875027),
        '4.png' => array('68de69085fba29de27cad6b0e0fd6f93d21a5fad2e035032d37cff8b5f5dc2bf', 807157),
        '5.png' => array('98ae6fb65e600cb20838d2deb73211aeee3293e49b37a742c7ae023b558b0324', 1102381),
        '6.png' => array('5b1abd2522670f7cbf2e14ad507b70962c0389b9f78320f83fc11b182838a726', 385485),
    );
    const PICTURE_SOURCES = array(
        'https://raw.githubusercontent.com/caligrafy/caligrafy-quill/v2.25.0/framework/plugins/facedetect/recognition/Flash/',
        'https://cdn.jsdelivr.net/gh/caligrafy/caligrafy-quill@v2.25.0/framework/plugins/facedetect/recognition/Flash/',
    );

    /** @var array tests: packages treated as not installed */
    public static $absent = array();

    /** @var callable|null tests: fn (string $url): ?string, the body of a download (null when it failed) */
    public static $fetcher = null;

    /**
     * The feature names: payments, ai, push, passkeys, snap (SDKs) and facedetect (files)
     */
    public static function names()
    {
        return array_merge(array_keys(self::FEATURES), array('facedetect'));
    }

    /**
     * Whether a package is installed (its class loads)
     */
    public static function installedPackage($package)
    {
        if (in_array($package, self::$absent, true)) return false;
        $class = self::CLASSES[$package] ?? null;
        return $class === null || class_exists($class);
    }

    /**
     * The packages of a feature that are not installed: [name => constraint]
     */
    public static function missing($feature)
    {
        $packages = self::FEATURES[$feature]['packages'] ?? array();
        return array_filter($packages, function ($package) { return !self::installedPackage($package); }, ARRAY_FILTER_USE_KEY);
    }

    public static function installed($feature)
    {
        return !self::missing($feature);
    }

    /**
     * Throws FeatureMissing when the feature's SDK is not installed
     * @param string $what what needs it (Caligrafy\Payment, new Agent() with Groq...)
     */
    public static function need($feature, $what)
    {
        if (self::installed($feature)) return;
        throw new FeatureMissing(self::message($feature, $what));
    }

    /**
     * The message of a missing SDK: what needs it, the packages, the command that installs them
     */
    public static function message($feature, $what = null)
    {
        $missing = self::missing($feature) ?: (self::FEATURES[$feature]['packages'] ?? array());
        return ($what ?: ucfirst(self::FEATURES[$feature]['title'])) . ' needs ' . implode(' and ', array_keys($missing))
            . ', which this project does not install (optional since Caligrafy Quill ' . self::SINCE . '): run php caligrafer.php add ' . $feature;
    }

    /**
     * The composer require command of features: composer require --no-interaction 'a/b:^1' ...
     */
    public static function requireCommand(array $features)
    {
        $packages = array();
        foreach ($features as $feature) {
            foreach (self::FEATURES[$feature]['packages'] ?? array() as $package => $constraint) $packages[$package] = $constraint;
        }
        return 'composer require --no-interaction ' . implode(' ', array_map(function ($p) use ($packages) {
            return escapeshellarg("$p:{$packages[$p]}");
        }, array_keys($packages)));
    }

    /**
     * The features a project uses: [feature => [reasons]], from its .env settings (set, not a placeholder) and its
     * application/ code. Values of .env are never returned, only the names of the settings
     * @param callable|null $env fn(string $name): string, a setting (default: the project's .env file)
     */
    public static function used($root = '.', $env = null)
    {
        $root = rtrim($root, '/');
        if ($env === null) {
            $values = self::envFile("$root/.env");
            $env = function ($name) use ($values) { return $values[$name] ?? ''; };
        }
        $code = self::code("$root/application");
        $used = array();
        foreach (self::FEATURES as $feature => $f) {
            foreach ($f['settings'] as $setting) {
                $value = trim((string) $env($setting));
                if ($value !== '' && !in_array($value, self::PLACEHOLDERS, true)) $used[$feature][] = "$setting is set in .env";
            }
            foreach ($code as $file => $source) {
                if (preg_match($f['code'], $source)) { $used[$feature][] = "$file uses it"; break; }
            }
            foreach ((array) ($f['onSetting'] ?? array()) as $setting => $values) {
                if (in_array(strtolower(trim((string) $env($setting))), $values, true)) $used[$feature][] = "$setting=" . trim((string) $env($setting)) . ' in .env';
            }
            if (!empty($f['agent']) && in_array(strtolower(trim((string) $env('AGENT_PROVIDER'))), array('', 'groq'), true)) {
                foreach ($code as $file => $source) {
                    if (preg_match('/new\s+\\\\?(Caligrafy\\\\)?Agent\s*\(/', $source)) { $used[$feature][] = "$file uses new Agent() with Groq (AGENT_PROVIDER)"; break; }
                }
            }
        }
        return $used;
    }

    /**
     * The require entries a project's composer.json lacks for the features it uses: [package => [constraint, feature,
     * reason]]. Packages it already requires (any constraint) are left alone
     */
    public static function toRestore($root, array $composer, $env = null)
    {
        $restore = array();
        foreach (self::used($root, $env) as $feature => $reasons) {
            foreach (self::FEATURES[$feature]['packages'] as $package => $constraint) {
                if (isset($composer['require'][$package]) || isset($restore[$package])) continue;
                $restore[$package] = array($constraint, $feature, $reasons[0]);
            }
        }
        return $restore;
    }

    /**
     * php caligrafer.php add <feature>...: composer require for the SDKs, the downloads for facedetect
     * @param callable|null $run fn(string $command): int (tests fake Composer)
     * @param callable|null $out fn(string $line)
     * @return int exit code
     */
    public static function add(array $features, $root = '.', $run = null, $out = null)
    {
        $out = $out ?: function ($line) { print($line . "\n"); };
        $run = $run ?: function ($command) { passthru($command, $code); return $code; };
        $features = array_values(array_unique(array_map('strtolower', $features)));
        if (!$features) {
            $out(' Usage: php caligrafer.php add <' . implode(' | ', self::names()) . '> ...');
            foreach (self::FEATURES as $feature => $f) {
                $out(sprintf('  %-10s %-9s %s: %s', $feature, self::installed($feature) ? 'installed' : '-', $f['title'], implode(', ', array_map(function ($p, $c) { return "$p $c"; }, array_keys($f['packages']), $f['packages']))));
            }
            $out(sprintf('  %-10s %-9s %s', 'facedetect', self::facedetectReady($root) ? 'installed' : '-', 'FaceDetect models (@vladmandic/face-api ' . self::FACE_API . ') in ' . self::MODELS_DIR));
            return 0;
        }
        $unknown = array_diff($features, self::names());
        if ($unknown) {
            $out(' Unknown feature: ' . implode(', ', $unknown) . '. Available: ' . implode(', ', self::names()));
            return 1;
        }
        $code = 0;
        if (in_array('facedetect', $features, true)) {
            try {
                foreach (self::download(self::modelFiles($root)) as $line) $out(" $line");
            } catch (\RuntimeException $e) {
                $out(' ' . $e->getMessage());
                $code = 1;
            }
        }
        $sdks = array_values(array_filter($features, function ($f) { return isset(self::FEATURES[$f]); }));
        $needed = array_values(array_filter($sdks, function ($f) { return !self::installed($f); }));
        foreach (array_diff($sdks, $needed) as $feature) $out(" $feature: " . implode(', ', array_keys(self::FEATURES[$feature]['packages'])) . ' already installed');
        if ($needed) {
            $command = self::requireCommand($needed);
            $out(" $command");
            if (call_user_func($run, $command) !== 0) {
                $out(' composer require did not finish: run it again (' . $command . ')');
                return 1;
            }
            $out(' Added ' . implode(', ', $needed) . '.');
        }
        return $code;
    }

    // ---- FaceDetect files ----

    /**
     * Whether the FaceDetect models are in place (each file there with its size)
     */
    public static function facedetectReady($root = '.')
    {
        foreach (self::modelFiles($root) as $file) {
            if (!is_file($file['path']) || filesize($file['path']) !== $file['size']) return false;
        }
        return true;
    }

    /**
     * The model files: [path, sha256, size, urls]
     */
    public static function modelFiles($root = '.')
    {
        return self::files(rtrim($root, '/') . '/' . self::MODELS_DIR, self::MODELS, self::MODEL_SOURCES);
    }

    /**
     * The pictures of the recognition example of a FaceDetect app (its folder)
     */
    public static function pictureFiles($appDir)
    {
        return self::files(rtrim($appDir, '/') . '/' . self::PICTURES_DIR, self::PICTURES, self::PICTURE_SOURCES);
    }

    /**
     * Downloads the files that are missing, checks the SHA-256 of each, then writes them all; nothing is written when
     * one fails. A file already there with another content is left alone (yours)
     * @return array lines of the report
     * @throws \RuntimeException offline, or a download that does not match its SHA-256
     */
    public static function download(array $files)
    {
        $lines = array();
        $todo = array();
        foreach ($files as $file) {
            if (is_file($file['path'])) {
                if (hash_file('sha256', $file['path']) !== $file['sha256']) $lines[] = 'kept     ' . self::shown($file['path']) . ' (not the pinned file: yours)';
                continue;
            }
            $todo[] = $file;
        }
        if (!$todo) {
            $lines[] = 'in place ' . count($files) . ' file(s) in ' . self::shown(dirname($files[0]['path']));
            return $lines;
        }
        $bodies = array();
        foreach ($todo as $file) {
            $body = null;
            $tried = array();
            foreach ($file['urls'] as $url) {
                $body = self::get($url);
                $tried[] = parse_url($url, PHP_URL_HOST);
                if ($body === null) continue;
                if (strlen($body) !== $file['size'] || hash('sha256', $body) !== $file['sha256']) {
                    throw new \RuntimeException('The download of ' . basename($file['path']) . " from $url does not match its pinned SHA-256: nothing was written. Try again later, or report it.");
                }
                break;
            }
            if ($body === null) {
                throw new \RuntimeException(basename($file['path']) . ' could not be downloaded from ' . implode(' or ', $tried)
                    . ' (offline?): nothing was written. Connect to the internet and run the command again (php caligrafer.php add facedetect).');
            }
            $bodies[] = array($file, $body);
        }
        foreach ($bodies as list($file, $body)) {
            if (!is_dir(dirname($file['path']))) @mkdir(dirname($file['path']), 0755, true);
            $tmp = $file['path'] . '.download';
            file_put_contents($tmp, $body);
            rename($tmp, $file['path']);
        }
        $lines[] = 'added    ' . count($bodies) . ' file(s) in ' . self::shown(dirname($todo[0]['path'])) . ' (SHA-256 checked)';
        return $lines;
    }

    // ---- helpers ----

    private static function files($dir, array $list, array $sources)
    {
        $files = array();
        foreach ($list as $name => $pin) {
            $files[] = array('path' => "$dir/$name", 'sha256' => $pin[0], 'size' => $pin[1],
                'urls' => array_map(function ($base) use ($name) { return $base . $name; }, $sources));
        }
        return $files;
    }

    private static function get($url)
    {
        if (self::$fetcher !== null) return call_user_func(self::$fetcher, $url);
        if (!function_exists('curl_init')) return null;
        $curl = curl_init($url);
        curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => array('User-Agent: caligrafer')));
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return $body !== false && $status === 200 ? $body : null;
    }

    private static function shown($path)
    {
        $cwd = rtrim((string) getcwd(), '/') . '/';
        return strpos($path, $cwd) === 0 ? substr($path, strlen($cwd)) : preg_replace('#^\./#', '', $path);
    }

    // the settings of a .env file (names and values, never printed)
    private static function envFile($file)
    {
        $values = array();
        if (!is_file($file)) return $values;
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                $value = preg_replace('/\s+#.*$/', '', trim($m[2]));
                $values[$m[1]] = trim($value, "\"'");
            }
        }
        return $values;
    }

    // the PHP files of application/: [relative path => source]
    private static function code($dir)
    {
        $code = array();
        if (!is_dir($dir)) return $code;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile() && substr($item->getFilename(), -4) === '.php' && $item->getSize() < 1048576) {
                $code['application/' . ltrim(substr($item->getPathname(), strlen($dir)), '/')] = (string) file_get_contents($item->getPathname());
            }
        }
        ksort($code);
        return $code;
    }
}
