<?php
/**
 * MobileApp.php
 * php caligrafer.php mobile <name>: a phone and tablet app of the project, for iOS and Android (2.27.0)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class MobileApp
 * Creates public/<name> from framework/librairies/mobile: the Vite Vue app of `create`, wrapped by Capacitor 8 into an
 * iOS and an Android project, that signs its user in through the site (Route::mobile) and calls the capabilities with
 * that user's token, turns push notifications on (Notify), and builds for TestFlight and Google Play with fastlane, on
 * a Mac or on GitHub Actions (.github/workflows/mobile-<name>.yml, written next to the project's own workflows).
 * Options: --id (the app id: com.example.books, the bundle id of iOS, the package of Android and the URL scheme of the
 * sign-in), --display (the name under the icon), --url (the site it calls once online). Node.js 22+ installs it and adds
 * ios/ and android/ (npx cap add); without it, the folder is created and the README says what to run.
 */
class MobileApp
{
    const TEMPLATE = 'framework/librairies/mobile';
    const RESERVED = array('app', 'bot', '__bots__', 'css', 'facedetect', 'fonts', 'images', 'js', 'ml', 'resources', 'uploads', 'snap', 'mobile');
    const PLACEHOLDERS = array('package.json', 'package-lock.json', 'capacitor.config.json', 'index.html', '.env.production', 'README.md');

    /** @var callable|null replaces the shell in tests: fn(string $command, string $cwd) => int exit status */
    public static $shell = null;

    private $root;
    private $say;

    public function __construct($root = '.', ?callable $say = null)
    {
        $this->root = rtrim((string) $root, '/');
        $this->say = $say ?? function ($line) { print(" $line\n"); };
    }

    /**
     * Whether a name can be the app's folder: public/<name>
     */
    public static function validName($name)
    {
        return is_string($name) && preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $name) === 1 && !in_array($name, self::RESERVED, true);
    }

    /**
     * Whether an app id works for Apple, Google and as a URL scheme: two or more segments of lowercase letters and
     * digits, each starting with a letter, separated by dots, 100 characters at most (Apple refuses _, Android -, and a
     * URL scheme takes neither _)
     */
    public static function validId($id)
    {
        return is_string($id) && strlen($id) <= 100 && preg_match('/^[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)+$/', $id) === 1
            && Mobile::validScheme($id);
    }

    /**
     * The app id when none is given: com.<project folder>.<name>, kept to what Apple and Google take
     */
    public static function defaultId($project, $name)
    {
        $part = function ($value, $fallback) {
            $value = preg_replace('/[^a-z0-9]/', '', strtolower((string) $value));
            $value = ltrim($value, '0123456789');
            return $value !== '' ? substr($value, 0, 40) : $fallback;
        };
        return 'com.' . $part($project, 'caligrafy') . '.' . $part($name, 'app');
    }

    /**
     * The site the store builds call: APP_URL when it is https, else https://<project folder>.caligrafy.com
     */
    public static function defaultUrl($project, $appUrl)
    {
        $appUrl = rtrim(trim((string) $appUrl), '/');
        if (preg_match('#^https://[^\s/]+#', $appUrl)) {
            return $appUrl;
        }
        $host = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $project)), '-');
        return 'https://' . ($host !== '' ? $host : 'myapp') . '.caligrafy.com';
    }

    /**
     * Creates the app: 0 when it was created, 1 otherwise (the reason is said)
     */
    public function create($name, array $options = array())
    {
        $name = strtolower(trim((string) $name));
        if (!self::validName($name)) {
            return $this->fail("'$name' cannot be the app's folder: lowercase letters, digits, - and _, starting with a letter (not one of " . implode(', ', self::RESERVED) . ')');
        }
        $folder = "public/$name";
        if (file_exists("$this->root/$folder")) {
            return $this->fail("$folder already exists: nothing was created. Choose another name, or remove the folder first.");
        }
        if (!is_dir("$this->root/" . self::TEMPLATE)) {
            return $this->fail(self::TEMPLATE . ' is missing: php caligrafer.php update brings it (Caligrafy Quill 2.27.0+)');
        }
        $project = basename(realpath($this->root) ?: $this->root);
        $id = strtolower(trim((string) ($options['id'] ?? ''))) ?: self::defaultId($project, $name);
        if (!self::validId($id)) {
            return $this->fail("'$id' is not an app id: two or more parts separated by dots, lowercase letters and digits, each starting with a letter (com.example.books)");
        }
        $display = trim((string) ($options['display'] ?? '')) ?: ucwords(str_replace(array('-', '_'), ' ', $name));
        if (mb_strlen($display) > 30 || preg_match('/[<>"&\\\\]/', $display)) {
            return $this->fail("The app's name is shown under its icon: 30 characters at most, without < > \" & \\");
        }
        $url = rtrim(trim((string) ($options['url'] ?? '')), '/') ?: self::defaultUrl($project, getenv('APP_URL'));
        if (!preg_match('#^https://[a-z0-9.-]+(:\d+)?(/[^\s]*)?$#i', $url)) {
            return $this->fail("'$url' is not the site's address online: the store builds call https:// only (Apple and Google refuse plain http)");
        }

        $this->copy("$this->root/" . self::TEMPLATE, "$this->root/$folder");
        $values = array('__NAME__' => $name, '__APP_ID__' => $id, '__APP_NAME__' => $display, '__API_URL__' => $url);
        foreach (self::PLACEHOLDERS as $file) {
            $path = "$this->root/$folder/$file";
            if (is_file($path)) {
                $text = file_get_contents($path);
                // JSON files get JSON strings, the others the plain value
                $replace = substr($file, -5) === '.json' ? array_map(function ($v) { return substr(json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1, -1); }, $values) : $values;
                file_put_contents($path, strtr($text, $replace));
            }
        }
        $workflow = $this->workflow($name);
        $this->say("$folder created: $display ($id), calling $url once online");
        if ($workflow) {
            $this->say("$workflow written: the store builds on GitHub Actions (TestFlight and Google Play)");
        }

        $native = $this->native("$this->root/$folder");
        $this->say('');
        $this->say('Next:');
        $this->say("1. In application/config/routing/web.php, after Route::auth() and Route::capabilities():");
        $this->say("     Route::mobile(['scheme' => '$id']);");
        $this->say("2. cd $folder && npm run dev: the app in a browser, signing in on http://localhost:8080 (php caligrafer.php server start)");
        if ($native) {
            $this->say("3. npm run ios (Xcode) or npm run android (Android Studio): the app on a simulator or a phone");
        }
        $this->say("Publishing (App Store, Google Play): the README of $folder, or /caligrafy-quill:mobile");
        return 0;
    }

    /**
     * npm install, then the iOS and Android projects and their set-up, where Node.js 22+ is: whether they exist
     */
    private function native($path)
    {
        $node = trim((string) $this->capture('node -v 2>/dev/null', $path));
        if (!preg_match('/^v(\d+)\./', $node, $m) || (int) $m[1] < 22) {
            $this->say('Node.js 22+ is not available here' . ($node !== '' ? " ($node)" : '') . ': the packages and the native projects are NOT installed.');
            $this->say("On your computer: cd " . substr($path, strlen($this->root) + 1) . " && npm install && npx cap add ios && npx cap add android && npm run native && npm run icons");
            return false;
        }
        $this->say('Installing the packages (npm install)...');
        if ($this->run('npm install --no-audit --no-fund', $path) !== 0) {
            $this->say('npm install failed: the native projects are NOT added. Run npm install there, then npx cap add ios && npx cap add android && npm run native');
            return false;
        }
        // the web files first: cap add copies dist/ into the native projects
        $this->run('npx vite build', $path);
        $ok = true;
        foreach (array('ios', 'android') as $platform) {
            if ($this->run("npx cap add $platform", $path) !== 0) {
                $this->say("npx cap add $platform failed: run it again in that folder once the cause is fixed");
                $ok = false;
            }
        }
        $this->run('node scripts/native.mjs', $path);
        if ($ok) {
            $this->run('npm run icons', $path);
        }
        return $ok;
    }

    /**
     * .github/workflows/mobile-<name>.yml from the template (never over an existing one): its path, or null
     */
    private function workflow($name)
    {
        $target = ".github/workflows/mobile-$name.yml";
        $source = "$this->root/public/$name/scripts/workflow.yml";
        if (!is_file($source) || file_exists("$this->root/$target")) {
            return null;
        }
        @mkdir("$this->root/.github/workflows", 0755, true);
        file_put_contents("$this->root/$target", str_replace('__NAME__', $name, file_get_contents($source)));
        return $target;
    }

    private function copy($from, $to)
    {
        @mkdir($to, 0755, true);
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $relative = substr($item->getPathname(), strlen($from) + 1);
            if (preg_match('#^(node_modules|dist|ios|android)(/|$)#', $relative)) {
                continue;
            }
            $item->isDir() ? @mkdir("$to/$relative", 0755, true) : copy($item->getPathname(), "$to/$relative");
        }
    }

    private function run($command, $cwd)
    {
        if (self::$shell) {
            return (int) call_user_func(self::$shell, $command, $cwd);
        }
        $previous = getcwd();
        chdir($cwd);
        passthru($command, $status);
        chdir($previous);
        return (int) $status;
    }

    private function capture($command, $cwd)
    {
        if (self::$shell) {
            return call_user_func(self::$shell, $command, $cwd) === 0 ? 'v22.12.0' : '';
        }
        $previous = getcwd();
        chdir($cwd);
        $out = shell_exec($command);
        chdir($previous);
        return (string) $out;
    }

    private function say($line)
    {
        call_user_func($this->say, $line);
    }

    private function fail($message)
    {
        $this->say($message);
        return 1;
    }
}
