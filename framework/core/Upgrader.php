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
 * Client apps in public/ created from framework/plugins are reported only.
 */
class Upgrader
{
    const TEMPLATE = 'framework/settings/application';
    const HISTORY = 'framework/settings/template-history.json';
    const PLUGINS = 'framework/plugins';
    const MANIFEST = '.caligrafy-manifest.json';
    const ROUTES = 'config/routing/web.php';
    const NEW_SUFFIX = '.caligrafy-new';
    const MANIFEST_FORMAT = 1;

    // client app type => marker found in its index.php or scripts/main.js (checked in this order)
    const PLUGIN_MARKERS = ['facedetect' => 'new FaceDetector', 'ml' => 'new MlCore', 'bot' => '__bots__', 'app' => 'loadVue('];

    private $root;
    private $apply;
    private $force;
    private $backupDir = null;

    public function __construct($root = '.', $apply = false, $force = null)
    {
        $this->root = rtrim($root, '/');
        $this->apply = $apply;
        $this->force = $force === null? null : preg_replace('#^(\./)?(application/)?#', '', $force);
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
    public static function writeManifest($root = '.', $files = null)
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
            elseif (($manifest['files'][$path] ?? null) === $current || in_array($current, $history[$path] ?? [], true)) $status = 'outdated';
            else $status = 'customized';
            $plan[$path] = ['status' => $status, 'template' => $hash, 'current' => $current];
        }
        return $plan;
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
        $counts = array_fill_keys(['missing', 'identical', 'outdated', 'customized', 'routes'], 0);
        foreach ($plan as $path => $item) {
            $counts[$item['status']]++;
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
                case 'customized':
                case 'routes':
                    if (!$forced) {
                        // listed below; the new version of a customized file is written next to it
                        if ($this->apply && $item['status'] === 'customized') $this->copy($path, $new);
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
            if (in_array($item['status'], ['customized', 'routes']) && $this->force !== $path) {
                printf("  %-11s application/%s: %s\n", $item['status'], $path, $item['status'] === 'routes'? "your routes: never replaced"
                    : ($this->apply? "kept, new version written to application/$path" . self::NEW_SUFFIX
                        : "kept, new version will be written to application/$path" . self::NEW_SUFFIX . " (replace it with --force application/$path)"));
            }
        }
        print("  identical  " . $counts['identical'] . " file(s)\n");

        $this->routesPatch($plan);
        $this->clientApps();

        if ($changing) {
            self::writeManifest($this->root, $files);
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
