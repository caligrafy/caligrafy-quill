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
 */
class PluginSource
{
    const SHARED = 'caligrafy';
    const PLUGIN = 'caligrafy-quill';
    const SETTINGS = '.claude/settings.json';
    const MARKETPLACE = '.claude-plugin/marketplace.json';

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
            $say("Claude Code (claude) is not on this computer's PATH: start claude in $folder and accept the plugin source and the plugin");
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
