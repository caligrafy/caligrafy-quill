<?php
/**
 * Upgrader.php
 * Brings an existing project up to date with the framework templates (php caligrafer.php upgrade)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Upgrader
 * Compares application/ with framework/settings/application and reports or applies the template updates.
 * Only template files are touched; files you created, .env, public/uploads and vendor are never read or written.
 * Client apps in public/ created from framework/plugins are reported only, and so are .env (new and obsolete settings)
 * and the views or apps that still use Bootstrap 3 classes (the framework uses Bootstrap 5).
 */
class Upgrader
{
    const TEMPLATE = 'framework/settings/application';
    const HISTORY = 'framework/settings/template-history.json';
    const USER_SERVICES = 'services/Users/';
    const ADMIN_CONTROLLER = 'controllers/core/Auth/AdminController.php';
    const AUTH_CONTROLLER = 'controllers/core/Auth/AuthController.php';
    const PLUGINS = 'framework/plugins';
    const MANIFEST = '.caligrafy-manifest.json';
    const ROUTES = 'config/routing/web.php';
    const NEW_SUFFIX = '.caligrafy-new';
    const MANIFEST_FORMAT = 1;

    // client app type => marker found in its index.php or scripts/main.js (checked in this order)
    const PLUGIN_MARKERS = ['facedetect' => 'new FaceDetector', 'ml' => 'new MlCore', 'bot' => '__bots__', 'app' => 'loadVue('];

    // Bootstrap 3 classes that Bootstrap 5 dropped: a page that uses them loses its layout
    const BOOTSTRAP3 = '/\\b(col-xs-\\d+|pull-(left|right)|img-responsive|btn-default|panel-(heading|body|default)|hidden-(xs|sm|md|lg)|visible-(xs|sm|md|lg)|center-block|input-group-addon|label-default)\\b/';

    // settings that no longer do anything => what replaces them
    const OBSOLETE_SETTINGS = ['CRYPTO_PAY_KEY' => 'Coinbase Commerce closed on March 31, 2026: use COINBASE_API_KEY_NAME and COINBASE_API_KEY_SECRET (Coinbase Business)'];

    private $root;
    private $apply;
    private $force;
    private $backupDir = null;
    private $keep = [];

    /**
     * @param array $keep application/ paths to keep even though they are untouched copies of an older template
     *                    (a default page that became a page of the site, like the home page): the new version is
     *                    written next to them, as for customized files
     */
    public function __construct($root = '.', $apply = false, $force = null, $keep = array())
    {
        $this->root = rtrim($root, '/');
        $this->apply = $apply;
        $this->force = $force === null? null : preg_replace('#^(\./)?(application/)?#', '', $force);
        foreach ((array) $keep as $path) {
            $this->keep[] = preg_replace('#^(\./)?(application/)?#', '', $path);
        }
    }

    /**
     * sha256 of every file of a folder, keyed by relative path (.DS_Store and node_modules skipped)
     */
    public static function hashes($dir)
    {
        $files = [];
        if (!is_dir($dir)) return $files;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = substr(str_replace('\\', '/', $file->getPathname()), strlen($dir) + 1);
            if (basename($path) === '.DS_Store' || strpos('/' . $path, '/node_modules/') !== false) continue;
            $files[$path] = hash_file('sha256', $file->getPathname());
        }
        ksort($files);
        return $files;
    }

    /**
     * Writes application/.caligrafy-manifest.json (called by initialize after copying the templates)
     */
    public static function writeManifest($root = '.', $files = null, $kept = array())
    {
        $root = rtrim($root, '/');
        $template = self::hashes("$root/" . self::TEMPLATE);
        $files = $files ?? $template;
        ksort($files);
        $manifest = [
            'format' => self::MANIFEST_FORMAT,
            'templates' => substr(hash('sha256', json_encode($template)), 0, 12),
            'updated' => date('c'),
            'files' => $files
        ];
        if ($kept) $manifest['kept'] = array_values(array_unique($kept));   // pages kept with --keep: never replaced by --apply
        file_put_contents("$root/application/" . self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Compares every template file with application/
     * @return array path => ['status' => missing|identical|outdated|customized|routes, 'template' => hash, 'current' => hash|null]
     */
    public function plan()
    {
        $template = self::hashes($this->root . '/' . self::TEMPLATE);
        $manifest = $this->manifest();
        $history = json_decode((string)@file_get_contents($this->root . '/' . self::HISTORY), true) ?: [];
        $plan = [];
        foreach ($template as $path => $hash) {
            $file = $this->app($path);
            $current = is_file($file)? hash_file('sha256', $file) : null;
            if ($current === null) $status = 'missing';
            elseif ($current === $hash) $status = 'identical';
            elseif ($path === self::ROUTES) $status = 'routes';
            elseif (($manifest['files'][$path] ?? null) === $current || in_array($current, $history[$path] ?? [], true)) {
                $status = in_array($path, $manifest['kept'] ?? [], true)? 'kept' : 'outdated';
            }
            else $status = 'customized';
            $plan[$path] = ['status' => $status, 'template' => $hash, 'current' => $current];
        }
        return $this->holdSignInController($this->holdUserCapabilities($plan));
    }

    /**
     * A project that signs people in with Route::auth (2.19) and removed the AuthController template does not get it back:
     * its routes no longer call it (2.19.3). --force adds it anyway.
     */
    private function holdSignInController(array $plan)
    {
        if (($plan[self::AUTH_CONTROLLER]['status'] ?? '') !== 'missing') return $plan;
        $routes = @file_get_contents($this->root . '/application/config/routing/web.php');
        // Route::auth( in code, not in a comment: a line that does not start with // or *
        if ($routes !== false && preg_match('#^(?!\s*(//|\*|/\*)).*Route::auth\s*\(#m', $routes)) {
            $plan[self::AUTH_CONTROLLER]['status'] = 'held';
            $plan[self::AUTH_CONTROLLER]['reason'] = 'your routes sign people in with Route::auth: AuthController is not needed (add it with --force)';
        }
        return $plan;
    }

    /**
     * The user capabilities (application/services/Users, 2.5.0) are not added on their own when the project manages
     * users its own way: a customized AdminController (it may use another administrator level), or a class or a
     * capability of the same name already in application/ (two declarations stop the application). The controllers
     * that call them (AdminController, AuthController) are held with them. --force adds a file anyway.
     */
    private function holdUserCapabilities(array $plan)
    {
        $users = array_filter(array_keys($plan), function ($path) use ($plan) {
            return strpos($path, self::USER_SERVICES) === 0 && $plan[$path]['status'] === 'missing';
        });
        if (!$users) return $plan;
        $reason = null;
        if (in_array($plan[self::ADMIN_CONTROLLER]['status'] ?? '', ['customized', 'kept'], true)) {
            $reason = 'your AdminController is customized: set CaligrafyUsers::ADMIN_LEVEL to its administrator level, then add them with --force';
        } else {
            $taken = $this->declared(array('list_users', 'get_user', 'update_user', 'delete_user', 'register_user'),
                array('CaligrafyUsers', 'CaligrafyListUsers', 'CaligrafyGetUser', 'CaligrafyUpdateUser', 'CaligrafyDeleteUser', 'CaligrafyRegisterUser'));
            if ($taken) $reason = 'application/ already declares ' . implode(', ', $taken) . ': rename yours, then add them with --force';
        }
        if ($reason === null) return $plan;
        foreach (array_merge($users, array(self::ADMIN_CONTROLLER, self::AUTH_CONTROLLER)) as $path) {
            if (isset($plan[$path]) && in_array($plan[$path]['status'], ['missing', 'outdated'], true)) {
                $plan[$path]['status'] = 'held';
                $plan[$path]['reason'] = $reason;
            }
        }
        return $plan;
    }

    /**
     * Capability names and class names declared by the PHP files of application/ (outside the user capabilities)
     */
    private function declared(array $capabilities, array $classes)
    {
        $found = [];
        $dir = $this->root . '/application';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($dir) + 1);
            if (substr($rel, -4) !== '.php' || strpos($rel, self::USER_SERVICES) === 0) continue;
            $code = (string) @file_get_contents($file->getPathname());
            foreach ($capabilities as $name) {
                if (preg_match("/name\\s*:\\s*['\"]" . $name . "['\"]/", $code)) $found[] = "the capability $name ($rel)";
            }
            foreach ($classes as $class) {
                if (preg_match('/^\\s*(final\\s+|abstract\\s+)?class\\s+' . $class . '\\b/mi', $code)) $found[] = "the class $class ($rel)";
            }
        }
        return array_values(array_unique($found));
    }

    /**
     * Prints the plan and, with --apply or --force, applies it
     */
    public function run()
    {
        if (!is_dir($this->root . '/application') || !is_dir($this->root . '/' . self::TEMPLATE)) {
            print("\n No application folder: run 'php caligrafer.php initialize' first.\n\n");
            return 1;
        }
        $plan = $this->plan();
        $manifest = $this->manifest();
        if ($this->force !== null && !isset($plan[$this->force])) {
            print("\n --force: '" . $this->force . "' is not a framework template file (application/<path>).\n\n");
            return 1;
        }
        $changing = $this->apply || $this->force !== null;
        print("\n Caligrafy upgrade" . ($this->apply? "" : ($this->force !== null? " (--force " . $this->force . " only)" : " (dry run: nothing is changed, run 'php caligrafer.php upgrade --apply' to update)")) . "\n");
        print(" Templates: " . self::TEMPLATE . " (" . count($plan) . " files) compared with application/ - "
            . ($manifest? "manifest " . self::MANIFEST . " found" : "no manifest (older project): untouched files recognized from the release history") . "\n\n");

        $files = $manifest['files'] ?? [];
        $counts = array_fill_keys(['missing', 'identical', 'outdated', 'customized', 'routes', 'held'], 0);
        foreach ($plan as $path => $item) {
            if ($item['status'] === 'outdated' && in_array($path, $this->keep, true)) {
                $item['status'] = $plan[$path]['status'] = 'kept';
            }
            $counts[$item['status']] = ($counts[$item['status']] ?? 0) + 1;
            $forced = $this->force === $path;
            $new = $this->app($path) . self::NEW_SUFFIX;
            switch ($item['status']) {
                case 'identical':
                    $files[$path] = $item['template'];
                    if ($this->apply && is_file($new)) unlink($new);
                    continue 2;
                case 'missing':
                    $action = $this->apply? "added" : "will be added";
                    if ($this->apply) $this->copy($path);
                    break;
                case 'outdated':
                    $action = $this->apply || $forced? "replaced (backup in " . $this->backupName() . ")" : "untouched copy, will be replaced (with a backup)";
                    if ($this->apply || $forced) $this->copy($path);
                    break;
                case 'held':
                    if (!$forced) {
                        printf("  %-11s application/%s: not added: %s\n", 'held', $path, $item['reason']);
                        continue 2;
                    }
                    $this->copy($path);
                    $action = "forced: added";
                    break;
                case 'customized':
                case 'kept':
                case 'routes':
                    if (!$forced) {
                        // listed below; the new version of a customized file is written next to it
                        if ($this->apply && $item['status'] !== 'routes') $this->copy($path, $new);
                        continue 2;
                    }
                    $this->copy($path);
                    $action = "forced: replaced (backup in " . $this->backupName() . ")";
                    break;
            }
            if ($this->apply || $forced) {
                $files[$path] = $item['template'];
                if (is_file($new)) unlink($new);
            }
            printf("  %-11s application/%s: %s\n", $item['status'], $path, $action);
        }
        // customized files and routes are listed after the files that change
        foreach ($plan as $path => $item) {
            if (in_array($item['status'], ['customized', 'kept', 'routes']) && $this->force !== $path) {
                printf("  %-11s application/%s: %s\n", $item['status'], $path, $item['status'] === 'routes'? "your routes: never replaced"
                    : ($this->apply? "kept, new version written to application/$path" . self::NEW_SUFFIX
                        : "kept, new version will be written to application/$path" . self::NEW_SUFFIX . " (replace it with --force application/$path)"));
            }
        }
        print("  identical  " . $counts['identical'] . " file(s)\n");
        if ($counts['outdated'] > 0 && !$this->apply) {
            print("\n Untouched copies are replaced by the new template. If one of them is a page of your site (for example the\n"
                . " home page views/default/index.pug), keep it: --keep application/<path> (the new version is written next to it).\n");
        }

        $this->routesPatch($plan);
        $this->clientApps();
        $this->bootstrap3($plan);
        $this->envReport();

        if ($changing) {
            $kept = array_diff(array_merge($manifest['kept'] ?? [], $this->keep), (array) $this->force);
            self::writeManifest($this->root, $files, $kept);
            print("\n Manifest application/" . self::MANIFEST . " updated.");
            if ($this->backupDir) print(" Backups: " . $this->backupName() . "/");
            print("\n\n");
        } else {
            print("\n Nothing was changed.\n\n");
        }
        return 0;
    }

    /**
     * Old projects print the error in the routes catch block: offer the one-line patch to Errors::handle($e)
     */
    private function routesPatch($plan)
    {
        $file = $this->app(self::ROUTES);
        if (!is_file($file) || $this->force === self::ROUTES) return;
        $code = file_get_contents($file);
        $pattern = '/(catch\s*\(\s*\\\\?Exception\s+\$e\s*\)\s*\{[^{}]*?)echo\s+\$e->getMessage\(\)\s*;/s';
        if (!preg_match($pattern, $code)) return;
        if (!$this->apply) {
            print("\n Routes: application/" . self::ROUTES . " prints errors with `echo \$e->getMessage();` (HTTP 200)."
                . "\n  --apply patches the catch block to `Errors::handle(\$e);` (real HTTP status codes), after a backup.\n");
            return;
        }
        $patched = preg_replace($pattern, '$1Errors::handle($e);', $code, 1);
        if (!preg_match('/^\s*use\s+\\\\?Caligrafy\\\\Errors\s*;/m', $patched)) {
            $patched = preg_match('/^\s*use\s+\\\\?Caligrafy\\\\Route\s*;.*$/m', $patched)
                ? preg_replace('/^(\s*use\s+\\\\?Caligrafy\\\\Route\s*;.*)$/m', "$1\nuse Caligrafy\\Errors;", $patched, 1)
                : preg_replace('/^<\?php.*$/m', "$0\nuse Caligrafy\\Errors;", $patched, 1);
        }
        $this->backup(self::ROUTES);
        file_put_contents($file, $patched);
        print("\n Routes: application/" . self::ROUTES . " catch block patched to `Errors::handle(\$e);` (backup in " . $this->backupName() . ").\n");
    }

    /**
     * Views and client apps that still use Bootstrap 3 classes (report only): the framework uses Bootstrap 5
     */
    private function bootstrap3($plan)
    {
        $files = [];
        $views = $this->root . '/application/views';
        if (is_dir($views)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $rel = substr($file->getPathname(), strlen($this->root . '/application/'));
                // an untouched copy that this upgrade replaces gets the Bootstrap 5 template
                if (substr($rel, -4) === '.pug' && !(($plan[$rel]['status'] ?? '') === 'outdated')) $files[] = $file->getPathname();
            }
        }
        foreach (glob($this->root . '/public/*', GLOB_ONLYDIR) as $dir) {
            if (in_array(basename($dir), ['css', 'js', 'fonts', 'images', 'resources', 'uploads'], true)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                function ($f) { return !in_array($f->getFilename(), ['node_modules', 'dist'], true); }));
            foreach ($it as $file) {
                if (preg_match('/\.(php|html|vue)$/', $file->getFilename())) $files[] = $file->getPathname();
            }
        }
        $lines = [];
        foreach ($files as $file) {
            if (preg_match_all(self::BOOTSTRAP3, (string) @file_get_contents($file), $m)) {
                $lines[] = "  " . substr($file, strlen($this->root) + 1) . ": " . implode(', ', array_slice(array_unique($m[1]), 0, 5));
            }
        }
        if ($lines) {
            print("\n Bootstrap 3 classes (report only): the framework uses Bootstrap 5, where these classes do nothing and the layout breaks.\n"
                . " Use .row > .col-md-6 (no col-xs-), float-start/float-end, img-fluid, btn-secondary, card, d-none... or the page components (wiki 7. Views):\n"
                . implode("\n", $lines) . "\n");
        }
    }

    /**
     * .env compared with framework/settings/.env.example: new settings it lacks and obsolete ones (report only)
     */
    private function envReport()
    {
        $env = $this->root . '/.env';
        $example = $this->root . '/framework/settings/.env.example';
        if (!is_file($env) || !is_file($example)) return;
        $keys = function ($file) {
            preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', (string) file_get_contents($file), $m);
            return array_unique($m[1]);
        };
        $mine = $keys($env);
        $missing = array_values(array_diff($keys($example), $mine));
        $obsolete = array_values(array_intersect(array_keys(self::OBSOLETE_SETTINGS), $mine));
        if (!$missing && !$obsolete) return;
        print("\n Settings (.env, report only, never modified):\n");
        if ($missing) {
            print("  new        " . implode(', ', $missing) . "\n"
                . "             not in your .env (empty means the default): copy the lines you need from framework/settings/.env.example\n");
        }
        foreach ($obsolete as $key) {
            print("  obsolete   $key: " . self::OBSOLETE_SETTINGS[$key] . "\n");
        }
    }

    /**
     * Lists the client apps of public/ created from framework/plugins and the files that differ (report only)
     */
    private function clientApps()
    {
        $lines = [];
        foreach (glob($this->root . '/public/*', GLOB_ONLYDIR) as $dir) {
            $type = null;
            $code = @file_get_contents("$dir/index.php") . @file_get_contents("$dir/scripts/main.js");
            foreach (self::PLUGIN_MARKERS as $plugin => $marker) {
                if (strpos($code, $marker) !== false) { $type = $plugin; break; }
            }
            if (!$type || !is_dir($this->root . '/' . self::PLUGINS . "/$type")) continue;
            $plugin = self::hashes($this->root . '/' . self::PLUGINS . "/$type");
            $app = self::hashes($dir);
            $differ = [];
            foreach ($plugin as $path => $hash) {
                if (!isset($app[$path])) $differ[] = "$path (missing)";
                elseif ($app[$path] !== $hash) $differ[] = $path;
            }
            $lines[] = "  public/" . basename($dir) . " ($type): " . ($differ? count($differ) . " file(s) differ from " . self::PLUGINS . "/$type: " . implode(', ', $differ) : "same as " . self::PLUGINS . "/$type");
        }
        if ($lines) {
            print("\n Client apps (report only, never modified; compare them with framework/plugins/<type> by hand):\n" . implode("\n", $lines) . "\n");
        }
    }

    private function manifest()
    {
        $manifest = json_decode((string)@file_get_contents($this->app(self::MANIFEST)), true);
        return is_array($manifest) && isset($manifest['files'])? $manifest : null;
    }

    private function app($path)
    {
        return $this->root . '/application/' . $path;
    }

    private function backupName()
    {
        if (!$this->backupDir) $this->backupDir = 'backups/caligrafy-upgrade-' . date('Ymd-His');
        return $this->backupDir;
    }

    private function backup($path)
    {
        $source = $this->app($path);
        if (!is_file($source)) return;
        $target = $this->root . '/' . $this->backupName() . '/application/' . $path;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
        copy($source, $target);
    }

    private function copy($path, $target = null)
    {
        $target = $target ?? $this->app($path);
        if ($target === $this->app($path)) $this->backup($path);
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
        copy($this->root . '/' . self::TEMPLATE . '/' . $path, $target);
    }
}
