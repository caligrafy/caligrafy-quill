<?php
/**
 * Updater.php
 * Moves a project to another release of Caligrafy Quill in one command (php caligrafer.php update)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Updater
 * php caligrafer.php update [<version>] downloads the project's release (Framework::VERSION) and the target release
 * (the latest by default), then:
 * 1. framework/ and claude-plugin/ are the framework's: replaced whole (files the release dropped go too), except
 *    the files initialize writes there (KEEP). Framework files changed by hand are named in the report
 * 2. every other file of the release (caligrafer.php, .bin/, Dockerfile, index.php, public/css, public/js...) is
 *    compared with the project's release: an untouched copy is replaced, a missing one added, a changed one kept with
 *    the new version written next to it (<file>.caligrafy-new). Documents (DOCS) changed by hand are left alone
 * 3. composer.json gets the new packages and versions the project did not change (then composer update for them),
 *    .gitignore the new lines, .env the new settings with their defaults (existing values are never changed)
 * 4. php caligrafer.php upgrade --apply, from the new release, brings application/ up to date
 * Everything replaced is copied first to backups/caligrafy-update-<from>-to-<to>-<time>/. --check reports only.
 * application/, .env values, public/uploads and vendor/ are never replaced.
 */
class Updater
{
    const REPOSITORY = 'caligrafy/caligrafy-quill';
    const WHOLE = array('framework', 'claude-plugin');
    // written by initialize inside framework/: kept across updates
    const KEEP = array('framework/librairies/app/.env');
    // in the release but not the project's: tests, CI, the lock file of the framework's own development
    // and the plugin source files: each project names its source (PluginSource), ensureOwn() keeps them right
    const SKIP = array('/^qa\//', '/^tests\//', '/^\.github\//', '/^composer\.lock$/', '/^public\/uploads\//', '/^application\//',
        '/^\.claude\/settings\.json$/', '/^\.claude-plugin\/marketplace\.json$/',
        '/^\.claude\/skills\/caligrafy-contributor\//', '/^\.gitattributes$/');   // contributors to the framework only
    // merged instead of compared
    const MERGED = array('composer.json', '.gitignore');
    // replaced when untouched, else left alone without a .caligrafy-new, never added (the project's own documents:
    // a release's LICENSE must not license a project)
    const DOCS = array('README.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'CODE_OF_CONDUCT.md', 'ARCHITECTURE.md', 'LICENSE');
    const NEW_SUFFIX = '.caligrafy-new';
    const ENV_EXAMPLE = 'framework/settings/.env.example';

    private $root;
    private $fetch;
    private $run;
    private $runner;
    private $lines = array();
    private $tmp = array();

    /**
     * @param string $root the project
     * @param callable|null $fetch fn(string $version): string folder of that release, null when it does not exist;
     *                            fn('latest') gives the latest version number (tests)
     * @param callable|null $run fn(string $command): int, runs a shell command in the project (tests)
     */
    public function __construct($root = '.', $fetch = null, $run = null)
    {
        $this->root = rtrim($root, '/');
        $this->fetch = $fetch ?: array($this, 'download');
        $this->run = $run ?: function ($command) { passthru($command, $code); return $code; };
        $this->runner = $run ? function ($command) use ($run) { return array(call_user_func($run, $command), ''); } : null;
    }

    /**
     * @param string|null $target a version (2.8.0, v2.8.0) or null for the latest
     * @param bool $check report only
     * @param bool $yes go on although git has uncommitted changes
     * @param bool $composer run composer update for changed packages
     * @return int exit code
     */
    public function run($target = null, $check = false, $yes = false, $composer = true)
    {
        try {
            return $this->update($target, $check, $yes, $composer);
        } catch (\RuntimeException $e) {
            $this->out(' ' . $e->getMessage());
            return 1;
        } finally {
            foreach ($this->tmp as $dir) self::remove($dir);
        }
    }

    private function update($target, $check, $yes, $composer)
    {
        $from = $this->projectVersion();
        $to = ltrim((string) ($target ?: call_user_func($this->fetch, 'latest')), 'vV');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $to)) {
            throw new \RuntimeException("Not a release: $to (php caligrafer.php update 2.8.0, or no version for the latest)");
        }
        if ($from !== null && version_compare($to, $from, '<')) {
            throw new \RuntimeException("This project runs $from: going back to $to is not supported (restore a backup instead)");
        }
        if ($from === $to) {
            $this->out(" Caligrafy Quill $from is the latest: nothing to update. php caligrafer.php upgrade checks application/.");
            return 0;
        }
        if (!$check && !$yes && $this->gitDirty()) {
            throw new \RuntimeException("git has uncommitted changes: commit them first (the update is easier to review and undo), or add --yes");
        }

        $this->out(" Updating Caligrafy Quill " . ($from ?? '(unknown version)') . " to $to" . ($check ? ' (check: nothing is changed)' : '') . "\n");
        $new = call_user_func($this->fetch, $to);
        if (!$new || !is_file("$new/framework/core/Framework.php")) {
            throw new \RuntimeException("Caligrafy Quill $to could not be downloaded from github.com/" . self::REPOSITORY);
        }
        $old = $from !== null ? call_user_func($this->fetch, $from) : null;
        $backup = $this->root . '/backups/caligrafy-update-' . ($from ?? 'unknown') . "-to-$to-" . date('Ymd-His');

        $plan = array('replace' => array(), 'add' => array(), 'new' => array(), 'kept' => array(), 'framework' => array());
        foreach (self::WHOLE as $folder) {
            $this->wholeFolder($folder, $old, $new, $check, $backup, $plan);
        }
        foreach (self::files($new) as $file) {
            if ($this->skipped($file)) continue;
            $this->releaseFile($file, $old, $new, $check, $backup, $plan);
        }
        $packages = $this->composerJson($old, $new, $check, $backup);
        $this->gitignore($new, $check);
        $settings = $this->env($old, $new, $check, $backup);

        $this->report($plan, $packages, $settings, $check);
        if ($check) {
            $this->out("\n Nothing was changed. php caligrafer.php update" . ($target ? " $to" : '') . " applies it.");
            return 0;
        }
        $this->out("\n Backups: " . substr($backup, strlen($this->root) + 1) . '/');
        foreach (array('.claude/settings.json', '.claude-plugin/marketplace.json') as $file) {
            if (!is_file("$this->root/$file") && is_file("$new/$file")) self::put("$this->root/$file", "$new/$file");
        }
        if (class_exists('Caligrafy\\PluginSource') || is_file("$this->root/framework/core/PluginSource.php")) {
            if (!class_exists('Caligrafy\\PluginSource')) require_once "$this->root/framework/core/PluginSource.php";
            if ($name = PluginSource::ensureOwn($this->root)) {
                $this->out(" Claude Code plugin source: $name (its own name: the shared \"caligrafy\" gave every project the first one's plugin)");
                PluginSource::connect($this->root, function ($line) { $this->out(" $line"); }, $this->runner);
            }
        }

        $code = 0;
        if ($packages && $composer) {
            $this->out("\n composer update " . implode(' ', $packages) . ":");
            if (call_user_func($this->run, 'composer update --no-interaction ' . implode(' ', array_map('escapeshellarg', $packages))) !== 0) {
                $this->out(" composer update did not finish: run it again (composer update " . implode(' ', $packages) . ')');
                $code = 1;
            }
        } elseif ($packages) {
            $this->out("\n Then: composer update " . implode(' ', $packages));
        }
        $this->out("\n application/: php caligrafer.php upgrade --apply (from $to)");
        // --local: the templates of this release only (upgrade would otherwise look for a newer release again)
        $upgrade = call_user_func($this->run, escapeshellarg(PHP_BINARY) . ' caligrafer.php upgrade --apply --local');
        $this->out("\n Caligrafy Quill $to is in place." . ($plan['new'] ? ' Merge the .caligrafy-new files listed above into yours, then delete them.' : '')
            . " Check the project with php caligrafer.php doctor; the release notes: https://github.com/" . self::REPOSITORY . "/wiki/Release-notes:-$to");
        return $code ?: (int) $upgrade;
    }

    // framework/ and claude-plugin/: the new release's copy, the files initialize wrote there kept
    private function wholeFolder($folder, $old, $new, $check, $backup, array &$plan)
    {
        $mine = $this->root . "/$folder";
        if (!is_dir("$new/$folder")) return;
        if ($old && is_dir($mine)) {
            foreach (self::files($mine, $folder) as $file) {
                if (in_array($file, self::KEEP, true) || basename($file) === '.DS_Store') continue;
                if (is_file("$old/$file") && !self::same("$old/$file", "$this->root/$file")) {
                    $plan['framework'][] = $file;   // changed by hand: replaced (a copy is in the backup)
                }
            }
        }
        $plan['replace'][] = "$folder/";
        if ($check) return;
        if (is_dir($mine)) {
            self::copyTree($mine, "$backup/$folder");
        }
        $kept = array();
        foreach (self::KEEP as $file) {
            if (strpos($file, "$folder/") === 0 && is_file("$this->root/$file")) $kept[$file] = file_get_contents("$this->root/$file");
        }
        self::remove($mine);
        self::copyTree("$new/$folder", $mine);
        foreach ($kept as $file => $content) {
            @mkdir(dirname("$this->root/$file"), 0755, true);
            file_put_contents("$this->root/$file", $content);
        }
    }

    // a file of the release outside framework/: replaced when untouched, added when missing, else .caligrafy-new
    private function releaseFile($file, $old, $new, $check, $backup, array &$plan)
    {
        $mine = "$this->root/$file";
        if (!is_file($mine) && in_array($file, self::DOCS, true)) return;   // the project's own documents: never added
        if (!is_file($mine)) {
            $plan['add'][] = $file;
            if (!$check) self::put($mine, "$new/$file");
            return;
        }
        if (self::same($mine, "$new/$file")) return;
        if ($old && self::same("$old/$file", "$new/$file")) return;   // the release did not change it: yours stays as is
        $untouched = $old && is_file("$old/$file") && self::same($mine, "$old/$file");
        if ($untouched) {
            $plan['replace'][] = $file;
            if (!$check) {
                self::put("$backup/$file", $mine);
                self::put($mine, "$new/$file");
                if (is_executable("$new/$file")) @chmod($mine, 0755);
            }
        } elseif (in_array($file, self::DOCS, true)) {
            $plan['kept'][] = $file;
        } else {
            $plan['new'][] = $file;
            if (!$check) self::put($mine . self::NEW_SUFFIX, "$new/$file");
        }
    }

    // composer.json: what the release changed and the project did not; returns the packages to update
    private function composerJson($old, $new, $check, $backup)
    {
        $file = "$this->root/composer.json";
        $theirs = self::json("$new/composer.json");
        $mine = self::json($file);
        if ($theirs === null || $mine === null) return array();
        $base = $old ? (self::json("$old/composer.json") ?? array()) : array();
        $packages = array();
        $changed = false;
        foreach (array('require', 'require-dev') as $section) {
            foreach ((array) ($theirs[$section] ?? array()) as $package => $version) {
                $current = $mine[$section][$package] ?? null;
                $before = $base[$section][$package] ?? null;
                if ($current === $version) continue;
                if ($current === null || $current === $before) {
                    $mine[$section][$package] = $version;
                    if ($package !== 'php' && strpos($package, 'ext-') !== 0) $packages[] = $package;
                    $changed = true;
                } else {
                    $this->lines[] = "  kept        composer.json $package: $current (the release asks for $version)";
                }
            }
        }
        foreach ((array) ($theirs['scripts'] ?? array()) as $name => $script) {
            if (!isset($mine['scripts'][$name])) {
                $mine['scripts'][$name] = $script;
                $changed = true;
            }
        }
        if ($changed) {
            $this->lines[] = '  merged      composer.json' . ($packages ? ': ' . implode(', ', $packages) : ': scripts');
            if (!$check) {
                self::put("$backup/composer.json", $file);
                file_put_contents($file, json_encode($mine, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            }
        }
        return $packages;
    }

    // .gitignore: the release's new lines are added at the end
    private function gitignore($new, $check)
    {
        $file = "$this->root/.gitignore";
        if (!is_file("$new/.gitignore")) return;
        $mine = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : array();
        $missing = array();
        foreach (file("$new/.gitignore", FILE_IGNORE_NEW_LINES) as $line) {
            if (trim($line) !== '' && $line[0] !== '#' && !in_array($line, $mine, true)) $missing[] = $line;
        }
        if (!$missing) return;
        $this->lines[] = '  merged      .gitignore: ' . implode(' ', $missing);
        if (!$check) file_put_contents($file, rtrim(implode("\n", $mine)) . "\n" . implode("\n", $missing) . "\n");
    }

    // .env: the settings the release added, with their comments and defaults; existing values never change
    private function env($old, $new, $check, $backup)
    {
        $file = "$this->root/.env";
        if (!is_file($file) || !is_file("$new/" . self::ENV_EXAMPLE)) return array();
        $have = self::keys((string) file_get_contents($file));
        $before = $old && is_file("$old/" . self::ENV_EXAMPLE) ? self::keys((string) file_get_contents("$old/" . self::ENV_EXAMPLE)) : null;
        $blocks = array();
        $comments = array();
        foreach (file("$new/" . self::ENV_EXAMPLE, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*#/', $line)) { $comments[] = $line; continue; }
            if (preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $m)) {
                $added = $before === null ? true : !in_array($m[1], $before, true);
                if ($added && !in_array($m[1], $have, true)) $blocks[$m[1]] = array_merge($comments, array($line));
            }
            $comments = array();
        }
        if (!$blocks) return array();
        if (!$check) {
            self::put("$backup/.env", $file);
            $text = rtrim((string) file_get_contents($file)) . "\n\n# Added by php caligrafer.php update (Caligrafy Quill settings, defaults)\n";
            foreach ($blocks as $lines) $text .= implode("\n", $lines) . "\n";
            file_put_contents($file, $text);
        }
        return array_keys($blocks);
    }

    private function report(array $plan, array $packages, array $settings, $check)
    {
        foreach ($plan['replace'] as $file) $this->out("  replaced    $file" . ($check ? ' (will be replaced)' : ''));
        foreach ($plan['add'] as $file) $this->out("  added       $file" . ($check ? ' (will be added)' : ''));
        foreach ($plan['new'] as $file) $this->out("  customized  $file: kept, the new version " . ($check ? 'will be written' : 'is') . " in $file" . self::NEW_SUFFIX);
        foreach ($plan['kept'] as $file) $this->out("  kept        $file (your own)");
        foreach ($this->lines as $line) $this->out($line);
        foreach ($settings as $key) $this->out("  setting     .env: $key " . ($check ? 'will be added' : 'added') . " with its default");
        if ($plan['framework']) {
            $this->out("\n Framework files you changed by hand, " . ($check ? 'to be replaced' : 'replaced') . " (your copies are in the backup):\n   " . implode("\n   ", $plan['framework']));
        }
    }

    /**
     * The latest release when it is newer than the project's, for upgrade: [version or null, why it could not tell or null]
     */
    public function newerRelease()
    {
        try {
            $latest = ltrim((string) call_user_func($this->fetch, 'latest'), 'vV');
        } catch (\RuntimeException $e) {
            return array(null, $e->getMessage());
        }
        $mine = $this->projectVersion();
        if (!preg_match('/^\d+\.\d+\.\d+$/', $latest)) {
            return array(null, 'the latest release could not be read');
        }
        return array($mine === null || version_compare($latest, $mine, '>') ? $latest : null, null);
    }

    /**
     * The release folder of a version, downloaded from GitHub and unpacked in a temporary folder; 'latest' gives the
     * latest version number
     */
    public function download($version)
    {
        if ($version === 'latest') {
            $json = json_decode((string) self::get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest'), true);
            if (empty($json['tag_name'])) throw new \RuntimeException('The latest release could not be read from github.com/' . self::REPOSITORY);
            return $json['tag_name'];
        }
        $archive = self::get('https://codeload.github.com/' . self::REPOSITORY . "/tar.gz/refs/tags/v$version");
        if ($archive === null) return null;
        $dir = sys_get_temp_dir() . '/caligrafy-' . $version . '-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        $this->tmp[] = $dir;
        file_put_contents("$dir/release.tar.gz", $archive);
        exec('tar -xzf ' . escapeshellarg("$dir/release.tar.gz") . ' -C ' . escapeshellarg($dir) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            (new \PharData("$dir/release.tar.gz"))->extractTo($dir, null, true);
        }
        foreach (glob("$dir/*", GLOB_ONLYDIR) ?: array() as $folder) {
            if (is_file("$folder/framework/core/Framework.php")) return $folder;
        }
        return null;
    }

    private static function get($url)
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => array('User-Agent: caligrafer-update', 'Accept: application/vnd.github+json')));
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return $body !== false && $status === 200 ? $body : null;
    }

    private function projectVersion()
    {
        $source = (string) @file_get_contents($this->root . '/framework/core/Framework.php');
        return preg_match("/const VERSION = '([0-9.]+)'/", $source, $m) ? $m[1] : null;
    }

    private function gitDirty()
    {
        if (!is_dir($this->root . '/.git')) return false;
        exec('git -C ' . escapeshellarg($this->root) . ' status --porcelain 2>/dev/null', $output, $code);
        return $code === 0 && (bool) array_filter($output, function ($line) { return strpos($line, 'backups/') === false; });
    }

    private function skipped($file)
    {
        foreach (self::WHOLE as $folder) if (strpos($file, "$folder/") === 0) return true;
        foreach (self::SKIP as $pattern) if (preg_match($pattern, $file)) return true;
        return in_array($file, self::MERGED, true) || basename($file) === '.DS_Store';
    }

    // relative paths of every file under a folder
    private static function files($dir, $prefix = '')
    {
        $files = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $files[] = ltrim(($prefix !== '' ? "$prefix/" : '') . substr($item->getPathname(), strlen(rtrim($dir, '/')) + 1), '/');
            }
        }
        sort($files);
        return $files;
    }

    private static function keys($text)
    {
        preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $text, $m);
        return $m[1];
    }

    private static function json($file)
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($data) ? $data : null;
    }

    private static function same($a, $b)
    {
        return is_file($a) && is_file($b) && filesize($a) === filesize($b) && hash_file('sha256', $a) === hash_file('sha256', $b);
    }

    private static function put($to, $from)
    {
        @mkdir(dirname($to), 0755, true);
        copy($from, $to);
    }

    private static function copyTree($from, $to)
    {
        foreach (self::files($from) as $file) {
            self::put("$to/$file", "$from/$file");
            if (is_executable("$from/$file")) @chmod("$to/$file", 0755);
        }
    }

    private static function remove($dir)
    {
        if (!is_dir($dir)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    private function out($text)
    {
        print($text . "\n");
    }
}
