<?php
/**
 * ClassMap.php
 * Loads a class only when it is used, from a map of the project's classes kept between requests
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class ClassMap (2.18.0)
 * Before 2.18.0 the first unknown class of a request loaded every PHP file of framework/core, framework/database,
 * framework/interfaces, application/ and tests/. Now a map of class => file (namespaced or not, interfaces, traits and
 * enums too) is built once from those folders (application/config, application/views and tests/ left out) and kept in a
 * private folder of the temporary directory; a class is loaded when it is first used.
 *
 * Kept as before: a file that does more than declare classes (it declares functions, or runs code at the top level, or
 * declares a class inside an if) is loaded at start, as every file used to be. A class missing from the map, and the
 * list of capabilities, make the map check its files (once per request; with APP_ENV=production at most once a
 * minute): a file added, removed or changed since the map was built rebuilds it, so a new class works at once while
 * you develop. A new container starts with no map. CLASS_MAP=false in .env goes back to loading every file.
 */
class ClassMap
{
    const VERSION = 2;
    const PRODUCTION_CHECK = 60;       // seconds between checks of the files with APP_ENV=production

    private static $map = null;        // ['classes' => [lower class => file], 'eager' => [files], 'capabilities' => [files], 'stamps' => [path => mtime]]
    private static $checked = false;
    private static $dirs = array();
    private static $skip = array();
    private static $file = null;

    /**
     * Whether the class map is on (CLASS_MAP=false turns it off)
     */
    public static function enabled()
    {
        return strtolower(trim((string) getenv('CLASS_MAP'))) !== 'false';
    }

    /**
     * Registers the autoloader for these folders and loads the files that must run at start
     * @param array $dirs absolute folders to map
     * @param array $skip absolute folders inside them to leave out
     */
    public static function register(array $dirs, array $skip = array())
    {
        self::$dirs = array_values(array_filter(array_map(function ($d) { return rtrim($d, '/\\'); }, $dirs), 'is_dir'));
        self::$skip = array_map(function ($d) { return rtrim($d, '/\\') . DIRECTORY_SEPARATOR; }, $skip);
        self::$map = null;
        self::$checked = false;
        self::$file = null;
        $folder = self::tempFolder('classes');
        if ($folder !== null) {
            self::$file = $folder . DIRECTORY_SEPARATOR . 'map-' . substr(md5(implode('|', self::$dirs) . '#' . implode('|', self::$skip)), 0, 12) . '.php';
        }
        spl_autoload_register(array(__CLASS__, 'load'));
        foreach (self::map()['eager'] as $path) {
            require_once $path;
        }
    }

    /**
     * The autoloader
     */
    public static function load($class)
    {
        $key = strtolower(ltrim($class, '\\'));
        $map = self::map();
        if (!isset($map['classes'][$key])) {
            $map = self::refresh();
        }
        if (isset($map['classes'][$key]) && is_file($map['classes'][$key])) {
            require_once $map['classes'][$key];
        }
    }

    /**
     * The files that may declare capabilities (they mention #[Capability]): Capabilities loads them to find every one
     */
    public static function capabilityFiles($fresh = false)
    {
        if (!self::$dirs) {
            return array();
        }
        if ($fresh) {
            self::refresh();
        }
        return self::map()['capabilities'];
    }

    /**
     * Rebuilds the map when a file was added, removed or edited since it was built (checked once per request)
     */
    public static function refresh()
    {
        if (self::$checked) {
            return self::map();
        }
        self::$checked = true;
        $map = self::map();
        // in production the code changes only with a deploy: its files are checked at most once a minute
        $production = strtolower(trim((string) getenv('APP_ENV'))) === 'production';
        if ($production && self::$file !== null && is_file(self::$file) && time() - (int) @filemtime(self::$file) < self::PRODUCTION_CHECK) {
            return $map;
        }
        // the map's time moves first, so requests arriving meanwhile skip the check: only this one scans the files
        // (stale() compares the files with the times stored in the map, not with the map's own time)
        if ($production && self::$file !== null) {
            @touch(self::$file);
        }
        if (self::stale($map)) {
            return self::rebuild();
        }
        return $map;
    }

    /**
     * Whether the autoloader is registered
     */
    public static function registered()
    {
        return (bool) self::$dirs;
    }

    /**
     * The map, from memory, the cached file or a new scan
     */
    public static function map()
    {
        if (self::$map !== null) {
            return self::$map;
        }
        if (self::$file !== null && is_file(self::$file)) {
            $cached = @include self::$file;
            if (is_array($cached) && ($cached['version'] ?? 0) === self::VERSION) {
                return self::$map = $cached;
            }
        }
        return self::rebuild();
    }

    /**
     * Scans the folders again and saves the map
     */
    public static function rebuild()
    {
        $map = array('version' => self::VERSION, 'classes' => array(), 'eager' => array(), 'capabilities' => array(), 'stamps' => array());
        foreach (self::$dirs as $dir) {
            self::scan($dir, $map);
        }
        sort($map['eager']);
        sort($map['capabilities']);
        if (self::$file !== null) {
            $tmp = self::$file . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, '<?php return ' . var_export($map, true) . ";\n") !== false) {
                @chmod($tmp, 0600);
                @rename($tmp, self::$file);
                if (function_exists('opcache_invalidate')) @opcache_invalidate(self::$file, true);
            }
        }
        return self::$map = $map;
    }

    /**
     * Whether a folder or a file of the map changed since it was built (a file added, removed or edited)
     */
    private static function stale(array $map)
    {
        // the PHP files now (a folder's time does not always change when a file is added, on Docker for Mac)
        $files = array();
        foreach (self::$dirs as $dir) {
            self::files($dir, $files);
        }
        if (count($files) !== count($map['stamps']) || array_diff_key($files, $map['stamps'])) {
            return true;
        }
        foreach ($map['stamps'] as $path => $mtime) {
            clearstatcache(true, $path);
            if (@filemtime($path) !== $mtime) {
                return true;
            }
        }
        return false;
    }

    /**
     * The PHP files of a folder and its subfolders (not the skipped ones), as path => true
     */
    private static function files($dir, array &$files, $depth = 0)
    {
        if ($depth > 8) {
            return;
        }
        foreach (@scandir($dir) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'node_modules' || $entry === 'vendor') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                if (!in_array($path . DIRECTORY_SEPARATOR, self::$skip, true)) {
                    self::files($path, $files, $depth + 1);
                }
            } elseif (substr($entry, -4) === '.php' && !self::moved($path)) {
                $files[$path] = true;
            }
        }
    }

    private static function scan($dir, array &$map)
    {
        $files = array();
        self::files($dir, $files);
        foreach (array_keys($files) as $path) {
            $map['stamps'][$path] = @filemtime($path);
            $source = (string) @file_get_contents($path);
            $info = self::declarations($source);
            foreach ($info['classes'] as $class) {
                $key = strtolower($class);
                if (!isset($map['classes'][$key])) {
                    $map['classes'][$key] = $path;
                }
            }
            if ($info['eager']) {
                $map['eager'][] = $path;
            }
            if (strpos($source, '#[') !== false && stripos($source, 'Capability') !== false) {
                $map['capabilities'][] = $path;
            }
        }
    }

    /**
     * What a PHP file declares at its top level: its classes (with their namespace), and whether it does anything else
     * (functions, code, a class declared inside a block), which makes it a file to load at start
     * @return array ['classes' => [...], 'eager' => bool]
     */
    public static function declarations($source)
    {
        $tokens = @token_get_all((string) $source);
        $classes = array();
        $eager = false;
        $namespace = '';
        $depth = 0;              // braces open at this point
        $nsBlocks = array();     // the depth inside each braced namespace (namespace A { ... }): its top level
        $declared = array(T_CLASS, T_INTERFACE, T_TRAIT, defined('T_ENUM') ? T_ENUM : -1);
        $modifiers = array(T_ABSTRACT, T_FINAL, defined('T_READONLY') ? T_READONLY : -1);
        $ignore = array(T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            $id = is_array($t) ? $t[0] : $t;
            if ($id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
                continue;
            }
            if ($id === '}') {
                if ($nsBlocks && end($nsBlocks) === $depth) {
                    array_pop($nsBlocks);
                    $namespace = '';
                }
                $depth--;
                continue;
            }
            if ($depth !== ($nsBlocks ? end($nsBlocks) : 0)) {
                continue;            // inside a class, a function or a block
            }
            if (in_array($id, $ignore, true) || $id === T_INLINE_HTML && trim($t[1]) === '') {
                continue;
            }
            if ($id === T_NAMESPACE) {
                $name = '';
                for ($i++; $i < $count; $i++) {
                    $n = $tokens[$i];
                    $nid = is_array($n) ? $n[0] : $n;
                    if ($nid === ';' || $nid === '{') break;
                    if (is_array($n) && in_array($nid, array(T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR), true)) $name .= $n[1];
                }
                $namespace = trim($name, '\\');
                if (($tokens[$i] ?? null) === '{') {
                    $depth++;
                    $nsBlocks[] = $depth;
                }
                continue;
            }
            if ($id === T_USE || $id === T_DECLARE) {
                for ($i++; $i < $count && $tokens[$i] !== ';'; $i++) {
                    if ($tokens[$i] === '{' || $tokens[$i] === '(') {
                        // use A\{B, C}; declare(strict_types=1);
                        $close = $tokens[$i] === '{' ? '}' : ')';
                        for ($i++; $i < $count && $tokens[$i] !== $close; $i++);
                    }
                }
                continue;
            }
            if ($id === T_ATTRIBUTE) {
                for ($level = 1, $i++; $i < $count && $level > 0; $i++) {
                    if ($tokens[$i] === '[') $level++;
                    if ($tokens[$i] === ']') $level--;
                }
                $i--;
                continue;
            }
            if (in_array($id, $modifiers, true)) {
                continue;
            }
            if (in_array($id, $declared, true)) {
                for ($j = $i + 1; $j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], $ignore, true); $j++);
                if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $classes[] = ($namespace !== '' ? $namespace . '\\' : '') . $tokens[$j][1];
                    $i = $j;
                    // skip extends/implements up to the body's {
                    for ($i++; $i < $count && $tokens[$i] !== '{'; $i++);
                    $i--;
                    continue;
                }
            }
            $eager = true;            // a function, code, or a conditional declaration at the top level
        }
        return array('classes' => $classes, 'eager' => $eager);
    }

    /**
     * A private folder of the system's temporary directory for this project, this system user and one kind of cache
     * (views, classes, data): created 0700, null when it cannot be created or written, is a link, or someone else owns it
     */
    public static function tempFolder($kind)
    {
        $user = function_exists('posix_geteuid') ? posix_geteuid() : null;
        $root = defined('ROOT') ? ROOT : getcwd() . DIRECTORY_SEPARATOR;
        $folder = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'caligrafy-' . $kind . '-'
            . substr(md5(realpath($root) ?: $root), 0, 12) . ($user === null ? '' : '-' . $user);
        if (!is_dir($folder) && !@mkdir($folder, 0700, true) && !is_dir($folder)) return null;
        if (is_link($folder) || !is_writable($folder)) return null;
        if ($user !== null && @fileowner($folder) !== $user) return null;
        return $folder;
    }

    private static function moved($path)
    {
        if (!defined('FRAMEWORK_MOVED_FILES') || !defined('FRAMEWORK_PATH')) {
            return false;
        }
        foreach (FRAMEWORK_MOVED_FILES as $moved) {
            if (realpath($path) === realpath(FRAMEWORK_PATH . $moved)) {
                return true;
            }
        }
        return false;
    }
}
