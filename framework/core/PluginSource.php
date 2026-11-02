<?php
/**
 * PluginSource.php
 * Gives a project its own name for the Claude Code plugin source that ships in it
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class PluginSource
 * A project enables the Claude Code plugin of its own claude-plugin/ folder through .claude/settings.json: a plugin
 * source (a "marketplace", declared in .claude-plugin/marketplace.json) at "./", and the plugin caligrafy-quill@<name>.
 * Claude Code keeps one source per name for the whole computer: when every project calls it "caligrafy", the first
 * project opened wins, every other project gets that project's plugin (an older one), and none gets any once that
 * folder is deleted. initialize and update give the project its own name: caligrafy-<folder>-<4 hex>.
 * The framework's own repository keeps "caligrafy": it is the source people add from GitHub.
 * Claude Code runs a copy of the plugin it makes at install time (~/.claude/plugins/cache), not the project's folder:
 * after an update brings a newer claude-plugin/, connect() refreshes that copy, and doctor warns while it is older.
 */
class PluginSource
{
    const SHARED = 'caligrafy';
    const PLUGIN = 'caligrafy-quill';
    const SETTINGS = '.claude/settings.json';
    const MARKETPLACE = '.claude-plugin/marketplace.json';
    const MANIFEST = 'claude-plugin/.claude-plugin/plugin.json';

    /**
     * The name of the project's plugin source, or null when the project has no plugin source files
     */
    public static function name($root = '.')
    {
        $marketplace = self::read(rtrim($root, '/') . '/' . self::MARKETPLACE);
        return is_string($marketplace['name'] ?? null) ? $marketplace['name'] : null;
    }

    /**
     * Renames a shared "caligrafy" source to the project's own name, in both files
     * @return string|null the new name, or null when there was nothing to rename
     */
    public static function ensureOwn($root = '.')
    {
        $root = rtrim($root, '/');
        $marketplace = self::read("$root/" . self::MARKETPLACE);
        $settings = self::read("$root/" . self::SETTINGS);
        if (($marketplace['name'] ?? null) !== self::SHARED || $settings === null) {
            return null;
        }
        $name = self::newName($root);
        $marketplace['name'] = $name;

        $sources = (array) ($settings['extraKnownMarketplaces'] ?? array());
        $source = $sources[self::SHARED] ?? array('source' => array('source' => 'directory', 'path' => './'));
        unset($sources[self::SHARED]);
        $sources[$name] = $source;
        $settings['extraKnownMarketplaces'] = $sources;

        $plugins = (array) ($settings['enabledPlugins'] ?? array());
        $enabled = $plugins[self::PLUGIN . '@' . self::SHARED] ?? true;
        unset($plugins[self::PLUGIN . '@' . self::SHARED]);
        $plugins[self::PLUGIN . '@' . $name] = $enabled;
        $settings['enabledPlugins'] = $plugins;

        self::write("$root/" . self::MARKETPLACE, $marketplace);
        self::write("$root/" . self::SETTINGS, $settings);
        return $name;
    }

    /**
     * Where Claude Code on this computer reads the source of that name (~/.claude/plugins/known_marketplaces.json),
     * or null when it does not know it yet
     */
    public static function registeredPath($name, $home = null)
    {
        $home = $home ?? (getenv('HOME') ?: '');
        $known = self::read(rtrim($home, '/') . '/.claude/plugins/known_marketplaces.json');
        $entry = $known[$name] ?? null;
        if (!is_array($entry)) {
            return null;
        }
        return $entry['installLocation'] ?? ($entry['source']['path'] ?? null);
    }

    /**
     * The version of the plugin in the project's claude-plugin/ folder, or null
     */
    public static function projectVersion($root = '.')
    {
        $manifest = self::read(rtrim($root, '/') . '/' . self::MANIFEST);
        return is_string($manifest['version'] ?? null) ? $manifest['version'] : null;
    }

    /**
     * The version of the copy Claude Code runs for this project (~/.claude/plugins/installed_plugins.json: installed
     * for the project, else for the user), or null when the plugin is not installed
     */
    public static function installedVersion($name, $root = '.', $home = null)
    {
        $home = $home ?? (getenv('HOME') ?: '');
        $installed = self::read(rtrim($home, '/') . '/.claude/plugins/installed_plugins.json');
        $entries = $installed['plugins'][self::PLUGIN . '@' . $name] ?? null;
        if (!is_array($entries)) {
            return null;
        }
        $entries = isset($entries['version']) ? array($entries) : $entries;
        $folder = realpath($root) ?: $root;
        $user = null;
        foreach ($entries as $entry) {
            if (!is_array($entry)) continue;
            $path = $entry['projectPath'] ?? null;
            if ($path !== null && (realpath($path) ?: $path) === $folder) return $entry['version'] ?? null;
            if (($entry['scope'] ?? '') === 'user') $user = $entry['version'] ?? null;
        }
        return $user;
    }

    /**
     * Registers the project's plugin source with Claude Code on this computer and installs the plugin for the project
     * (claude plugin marketplace add <folder>, claude plugin install caligrafy-quill@<name> --scope project). An
     * already trusted folder is never asked again, so starting claude there does not register a renamed source
     * @param callable $say fn(string $line)
     * @param callable|null $run fn(string $command): [int $code, string $output] (tests)
     * @return int exit code
     */
    public static function connect($root, $say, $run = null)
    {
        $run = $run ?: function ($command) { exec($command . ' 2>&1', $out, $code); return array($code, implode("\n", $out)); };
        $name = self::name($root);
        $folder = realpath($root) ?: $root;
        if ($name === null) {
            $say('No .claude-plugin/marketplace.json in this project');
            return 1;
        }
        list($found) = call_user_func($run, 'command -v claude');
        if ($found !== 0) {
            $say("Claude Code (claude) is not on this PATH (in Docker?): on your computer, run php caligrafer.php plugin in this folder, then restart claude there (it registers the plugin and refreshes Claude Code's copy of it)");
            return 0;
        }
        // a shared "caligrafy" registered from this folder (claude was opened before quickstart renamed it) would give
        // the next clone this folder's plugin, or none once it is deleted: it goes
        $shared = $name !== self::SHARED ? self::registeredPath(self::SHARED) : null;
        if ($shared !== null && realpath($shared) === $folder) {
            call_user_func($run, 'cd ' . escapeshellarg(sys_get_temp_dir()) . ' && claude plugin marketplace remove ' . escapeshellarg(self::SHARED));
        }
        $registered = self::registeredPath($name);
        if ($registered !== null && realpath($registered) !== $folder) {
            // from a neutral folder: run inside a project, remove also deletes the source from that project's settings
            call_user_func($run, 'cd ' . escapeshellarg(sys_get_temp_dir()) . ' && claude plugin marketplace remove ' . escapeshellarg($name));
            $registered = null;
        }
        if ($registered === null) {
            list($code, $out) = call_user_func($run, 'claude plugin marketplace add ' . escapeshellarg($folder));
            if ($code !== 0) {
                $say("claude plugin marketplace add did not work: $out");
                return 1;
            }
        }
        list($code, $out) = call_user_func($run, 'cd ' . escapeshellarg($folder) . ' && claude plugin list');
        if (strpos($out, self::PLUGIN . '@' . $name) === false) {
            list($code, $out) = call_user_func($run, 'cd ' . escapeshellarg($folder) . ' && claude plugin install ' . escapeshellarg(self::PLUGIN . '@' . $name) . ' --scope project');
            if ($code !== 0) {
                $say("claude plugin install did not work: $out");
                return 1;
            }
        } elseif (($installed = self::installedVersion($name, $root)) !== null && ($version = self::projectVersion($root)) !== null && $installed !== $version) {
            // Claude Code runs its own copy of the plugin: an update of claude-plugin/ reaches it only through these
            call_user_func($run, 'cd ' . escapeshellarg($folder) . ' && claude plugin marketplace update ' . escapeshellarg($name));
            list($code, $out) = call_user_func($run, 'cd ' . escapeshellarg($folder) . ' && claude plugin update ' . escapeshellarg(self::PLUGIN . '@' . $name) . ' --scope project');
            if ($code !== 0) {
                $say("claude plugin update did not work: $out");
                return 1;
            }
            $say("Claude Code's copy of the plugin: $installed, now $version");
        }
        $say("Claude Code reads the plugin from $folder/claude-plugin: restart claude in this folder to load it");
        return 0;
    }

    // caligrafy-<folder>-<4 hex>: readable, and unique even for projects with the same folder name (or "html" in Docker)
    private static function newName($root)
    {
        $folder = strtolower(basename((string) (realpath($root) ?: $root)));
        $folder = trim(preg_replace('/[^a-z0-9]+/', '-', $folder), '-');
        $folder = in_array($folder, array('', 'html', 'app', 'www'), true) ? '' : substr($folder, 0, 24) . '-';
        return self::SHARED . '-' . $folder . bin2hex(random_bytes(2));
    }

    private static function read($file)
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($data) ? $data : null;
    }

    private static function write($file, array $data)
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }
}
