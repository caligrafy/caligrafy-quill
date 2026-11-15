<?php
/**
 * Capabilities.php
 * The registry of capabilities: finds them, describes them and calls them the same way for every channel
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Capabilities
 * A capability is a class of application/services/ that extends Service and carries #[Capability(...)].
 * Every channel calls it through call(), which always does the same, in order:
 * 1. finds it (not_found)
 * 2. checks the context: public, or signed in / a token (unauthenticated), with the scope and level (forbidden); a token
 *    needs the declared scope, or for a write capability that declares none its own name (scopeOf(), 2.26.0)
 * 3. keeps, filters and validates the declared inputs (invalid)
 * 4. runs handle()
 * 5. writes the audit log: every refusal, and every success of a capability that is not read-only
 * and returns plain data (models become arrays of their columns).
 *
 * The registry is built from the classes declared once the capability files are loaded. With the class map (2.26.1)
 * a call by name (get(), so call(), REST, MCP, the JS client) reads a cache of it instead, kept next to the class map
 * and out of date as soon as the class map is (a file added, removed or edited) or a file of framework/services
 * changes: it saves finding and reflecting every service on every request. A name the cache does not have (a class
 * declared elsewhere, registered by hand) builds the registry as before.
 */
class Capabilities
{
    /** @var array|null name => ['class' => ..., 'capability' => Capability] */
    private static $registry = null;

    const CACHE_VERSION = 1;

    /** @var array|false|null the registry's cache once read in this request (false: none, or out of date) */
    private static $cache = null;

    /** @var array name => entry: what get() took from the cache in this request */
    private static $found = array();

    /** @var array extra classes registered by hand (tests, packages) */
    private static $extra = array();

    /**
     * @var bool|null whether a write capability declared without a scope needs the scope named after it from a token
     * (2.26.0): null until the first use reads TOKEN_DEFAULT_SCOPES (false, deprecated: any token, as before)
     */
    public static $defaultScopes = null;

    /**
     * The scope a token needs to call a capability (2.26.0): the one it declares; for a write capability (not readOnly,
     * not public) declared without one, its own name (create_book), which a token grants by that name or by *.
     * Read-only and public capabilities without a scope need none. Signed-in users and the CLI never need a scope.
     */
    public static function scopeOf(Capability $capability)
    {
        if ($capability->scope !== '' || $capability->readOnly || $capability->public) {
            return $capability->scope;
        }
        if (self::$defaultScopes === null) {
            $setting = getenv('TOKEN_DEFAULT_SCOPES');
            $setting = $setting === false ? ($_ENV['TOKEN_DEFAULT_SCOPES'] ?? (defined('TOKEN_DEFAULT_SCOPES') ? constant('TOKEN_DEFAULT_SCOPES') : '')) : $setting;
            $setting = is_bool($setting) ? ($setting ? 'true' : 'false') : strtolower(trim((string) $setting));
            self::$defaultScopes = !in_array($setting, array('false', '0', 'off', 'no'), true);
        }
        return self::$defaultScopes ? $capability->name : '';
    }

    /**
     * The write capabilities declared without a scope, each with the scope a token now needs to call them (doctor)
     * @return array name => scope
     */
    public static function unscopedWrites()
    {
        $list = array();
        foreach (self::all() as $name => $entry) {
            $capability = $entry['capability'];
            if ($capability->scope === '' && !$capability->readOnly && !$capability->public && !$capability->webOnly) {
                $list[$name] = $name;
            }
        }
        return $list;
    }

    /**
     * Registers a service class that is not in application/services/
     */
    public static function register($class)
    {
        self::$extra[] = ltrim($class, '\\');
        self::$registry = null;
        self::$found = array();
    }

    /**
     * Every capability, by name
     * @return array name => ['class' => string, 'capability' => Capability]
     */
    public static function all()
    {
        if (self::$registry !== null) {
            return self::$registry;
        }
        $files = self::loadServices();
        $registry = array();
        $sources = array();   // name => the file of its class
        // the services among every declared class, then sorted (only them: sorting every class cost more than the rest)
        $classes = array();
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Service::class)) {
                $classes[] = $class;
            }
        }
        foreach (self::$extra as $class) {
            if (class_exists($class) && is_subclass_of($class, Service::class)) {
                $classes[] = $class;
            }
        }
        $classes = array_unique($classes);
        sort($classes);
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }
            $attributes = $reflection->getAttributes(Capability::class);
            if (!$attributes) {
                continue;
            }
            $capability = $attributes[0]->newInstance();
            if (isset($registry[$capability->name]) && $registry[$capability->name]['class'] !== $class) {
                throw new \LogicException("Two services declare the capability '{$capability->name}': {$registry[$capability->name]['class']} and $class");
            }
            $registry[$capability->name] = array('class' => $class, 'capability' => $capability);
            $sources[$capability->name] = $reflection->getFileName();
        }
        ksort($registry);
        if ($files !== null && self::cache() === false) {
            self::saveCache($registry, $sources, $files);
        }
        return self::$registry = $registry;
    }

    /**
     * One capability (null when there is none of that name)
     */
    public static function get($name)
    {
        if (self::$registry === null && !self::$extra && is_string($name) && ($entry = self::cached($name)) !== null) {
            return $entry;
        }
        return self::all()[$name] ?? null;
    }

    /**
     * A capability from the registry's cache (2.26.1): its file loaded as the registry would, its attribute as declared;
     * null when the cache does not have it or cannot be used
     */
    private static function cached($name)
    {
        if (isset(self::$found[$name])) {
            return self::$found[$name];
        }
        $cache = self::cache();
        if ($cache === false || !isset($cache['entries'][$name])) {
            return null;
        }
        $cached = $cache['entries'][$name];
        if (!is_file($cached['file'])) {
            return null;
        }
        // the framework's own capability files first, as loadServices() does: an app's service may extend one of theirs
        foreach (array_keys($cache['services']) as $path) {
            if (is_file($path)) {
                require_once $path;
            }
        }
        require_once $cached['file'];
        if (!class_exists($cached['class'], false)) {
            return null;
        }
        try {
            $capability = new Capability(...$cached['capability']);
        } catch (\Throwable $e) {
            return null;
        }
        return self::$found[$name] = array('class' => $cached['class'], 'capability' => $capability);
    }

    /**
     * The registry's cache when it is current, else false: it belongs to the class map as it is now (the class map
     * checks its files first, as for a missing class) and to the same files of framework/services
     */
    private static function cache()
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $file = self::cacheFile();
        if ($file === null || !is_file($file)) {
            return self::$cache = false;
        }
        $cache = @include $file;
        if (!is_array($cache) || ($cache['version'] ?? null) !== self::CACHE_VERSION || !isset($cache['map'], $cache['services'], $cache['entries'])) {
            return self::$cache = false;
        }
        ClassMap::refresh();
        if ($cache['map'] !== (ClassMap::map()['built'] ?? null)) {
            return self::$cache = false;
        }
        // the framework's own capabilities are not in the class map: their files are compared whenever it compares its own
        if (ClassMap::scanned() && $cache['services'] !== self::stamps(self::frameworkServiceFiles())) {
            return self::$cache = false;
        }
        return self::$cache = $cache;
    }

    // where the cache is kept: next to the class map, only while the class map is on and has a folder
    private static function cacheFile()
    {
        if (!class_exists('Caligrafy\\ClassMap', false) || !ClassMap::registered() || empty(ClassMap::map()['built'])) {
            return null;
        }
        $folder = ClassMap::tempFolder('classes');
        if ($folder === null) {
            return null;
        }
        return $folder . DIRECTORY_SEPARATOR . 'capabilities-' . substr(md5((defined('APP_PATH') ? APP_PATH : '') . '|' . dirname(__DIR__)), 0, 12) . '.php';
    }

    // keeps the capabilities whose class is in a file the registry loads (not one declared elsewhere or registered by hand)
    private static function saveCache(array $registry, array $sources, array $files)
    {
        $file = self::cacheFile();
        if ($file === null) {
            return;
        }
        $known = array();   // the real path (what reflection gives) => the path loaded
        foreach ($files as $path) {
            $known[realpath($path) ?: $path] = $path;
        }
        $entries = array();
        foreach ($registry as $name => $entry) {
            $source = is_string($sources[$name] ?? null) ? (realpath($sources[$name]) ?: $sources[$name]) : null;
            if ($source !== null && isset($known[$source])) {
                $entries[$name] = array('class' => $entry['class'], 'file' => $known[$source], 'capability' => get_object_vars($entry['capability']));
            }
        }
        $cache = array('version' => self::CACHE_VERSION, 'map' => ClassMap::map()['built'],
            'services' => self::stamps(self::frameworkServiceFiles()), 'entries' => $entries);
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, '<?php return ' . var_export($cache, true) . ";\n") !== false) {
            @chmod($tmp, 0600);
            @rename($tmp, $file);
            if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
        }
        self::$cache = $cache;
    }

    // path => mtime
    private static function stamps(array $files)
    {
        $stamps = array();
        foreach ($files as $path) {
            clearstatcache(true, $path);
            $stamps[$path] = @filemtime($path);
        }
        return $stamps;
    }

    // the framework's own capabilities (framework/services: notifications, 2.15.0), in every project
    private static function frameworkServiceFiles()
    {
        return glob(dirname(__DIR__) . '/services/*/*.php') ?: array();
    }

    /**
     * The capabilities a context may call (MCP lists only these)
     */
    public static function available(Context $context)
    {
        return array_filter(self::all(), function ($entry) use ($context) {
            $capability = $entry['capability'];
            return !$capability->webOnly && ($capability->public || $context->allows(self::scopeOf($capability), $capability->level));
        });
    }

    /**
     * Calls a capability
     * @param string $name the capability name
     * @param array $input the inputs (extra keys are ignored)
     * @param Context $context who calls, through which channel
     * @param Uploads|null $uploads the files of the request (web and REST): saved once the caller is allowed, given to
     *                     the capability as their URLs, deleted again when the call fails
     * @return mixed plain data
     * @throws ServiceError
     */
    public static function call($name, array $input, Context $context, ?Uploads $uploads = null)
    {
        $entry = self::get($name);
        if ($entry === null) {
            throw ServiceError::notFound("There is no capability '$name'");
        }
        $capability = $entry['capability'];
        if ($capability->webOnly && empty($context->viaAction)) {
            throw ServiceError::notFound("There is no capability '$name'");   // Route::action only (2.21.0)
        }
        try {
            if (!$capability->public) {
                if (!$context->authenticated()) {
                    throw ServiceError::unauthenticated();
                }
                $scope = self::scopeOf($capability);
                if (!$context->allows($scope, $capability->level)) {
                    $shown = $context->token !== null ? $scope : $capability->scope;   // a scope only matters to a token
                    throw ServiceError::forbidden($shown !== '' ? "This needs the scope $shown" . ($capability->level > 0 ? " and permission level {$capability->level}" : '')
                        . ($shown !== $capability->scope ? ' (a write capability that declares no scope needs a token with the scope named after it, or *)' : '') : 'You are not allowed to do this');
                }
            }
            $service = Container::make($entry['class']);   // its constructor gets what it needs (2.18.0)
            if ($uploads !== null) {
                $input = $uploads->apply($input, (array) $service->rules);
            }
            $result = $service->handle($service->validate($input), $context);
            $result = $result instanceof Web ? $result : self::plain($result);
        } catch (ServiceError $e) {
            if ($uploads !== null) {
                $uploads->discard();
            }
            Audit::record($context, $name, $e->kind, $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            if ($uploads !== null) {
                $uploads->discard();
            }
            Audit::record($context, $name, 'error', get_class($e));
            throw $e;
        }
        if (!$capability->readOnly) {
            Audit::record($context, $name, 'ok');
        }
        return $result;
    }

    /**
     * The JSON Schema of a capability's inputs, from its GUMP rules
     */
    public static function schema($name)
    {
        $entry = self::get($name);
        if ($entry === null) {
            return null;
        }
        $defaults = get_class_vars($entry['class']);
        return self::schemaFromRules($defaults['rules'] ?? array(), $defaults['descriptions'] ?? array());
    }

    /**
     * JSON Schema (draft 2020-12 subset) of GUMP rules
     * @param array $rules input => 'required|max_len,100|integer'
     * @param array $descriptions input => description
     */
    public static function schemaFromRules(array $rules, array $descriptions = array())
    {
        $properties = array();
        $required = array();
        foreach ($rules as $field => $rule) {
            $property = array();
            $type = 'string';
            foreach (array_filter(explode('|', (string) $rule)) as $part) {
                $pieces = explode(',', $part, 2);
                $ruleName = trim($pieces[0]);
                $argument = isset($pieces[1]) ? trim($pieces[1]) : null;
                switch ($ruleName) {
                    case 'required': $required[] = $field; break;
                    case 'integer': $type = 'integer'; break;
                    case 'numeric': case 'float': $type = 'number'; break;
                    case 'boolean': $type = 'boolean'; break;
                    case 'valid_email': $property['format'] = 'email'; break;
                    case 'valid_url': $property['format'] = 'uri'; break;
                    case 'uuid': $property['format'] = 'uuid'; break;
                    case 'date': $property['format'] = 'date'; break;
                    // a missing or wrong argument (max_numeric, with nothing after it) is left out of the schema
                    case 'max_len': if (ctype_digit((string) $argument)) { $property['maxLength'] = (int) $argument; } break;
                    case 'min_len': if (ctype_digit((string) $argument)) { $property['minLength'] = (int) $argument; } break;
                    case 'exact_len': if (ctype_digit((string) $argument)) { $property['minLength'] = $property['maxLength'] = (int) $argument; } break;
                    case 'min_numeric': if (($bound = self::bound($argument)) !== null) { $property['minimum'] = $bound; } break;
                    case 'max_numeric': if (($bound = self::bound($argument)) !== null) { $property['maximum'] = $bound; } break;
                    case 'contains_list': $property['enum'] = array_values(array_filter(array_map('trim', explode(';', (string) $argument)), 'strlen')); break;
                    // a file input (Uploads): agents send the URL or the path of the file
                    case 'file': $property['description'] = 'The URL or the path of a file'; break;
                    case 'required_file': $required[] = $field; $property['description'] = 'The URL or the path of a file'; break;
                }
            }
            if ($type !== 'string') {
                unset($property['format'], $property['maxLength'], $property['minLength']);
            } else {
                unset($property['minimum'], $property['maximum']);
            }
            $property = array('type' => $type) + $property;
            if (!empty($descriptions[$field])) {
                $property['description'] = (string) $descriptions[$field];
            }
            $properties[$field] = $property;
        }
        $schema = array('type' => 'object', 'properties' => $properties ?: new \stdClass(), 'additionalProperties' => false);
        if ($required) {
            $schema['required'] = array_values(array_unique($required));
        }
        return $schema;
    }

    // the number of min_numeric/max_numeric: current_year is the year of today (Validator), anything else not numeric none
    private static function bound($argument)
    {
        if ($argument === 'current_year') {
            return (int) date('Y');
        }
        return is_numeric($argument) ? $argument + 0 : null;
    }

    /**
     * The description of every capability, as a list (php caligrafer.php capabilities, the REST listing)
     * @param Context|null $context only the capabilities this context may call
     */
    public static function describe(?Context $context = null)
    {
        $list = array();
        foreach ($context ? self::available($context) : self::all() as $name => $entry) {
            $capability = $entry['capability'];
            if ($capability->webOnly) {
                continue;   // Route::action only (2.21.0)
            }
            $list[] = array('name' => $name, 'description' => $capability->description, 'scope' => self::scopeOf($capability),
                'level' => $capability->level, 'readOnly' => $capability->readOnly, 'destructive' => $capability->destructive,
                'public' => $capability->public, 'input' => self::schema($name));
        }
        return $list;
    }

    /**
     * Plain data: models become arrays of their public columns, recursively
     */
    public static function plain($value)
    {
        if ($value instanceof \JsonSerializable) {
            return self::plain($value->jsonSerialize());
        }
        if (is_object($value)) {
            $isModel = $value instanceof Model;
            $value = get_object_vars($value);
            if ($isModel) {
                unset($value['table']);
            }
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::plain($item);
            }
        }
        return $value;
    }

    /**
     * Requires the PHP files that declare capabilities before the registry is built. With the class map (2.18.0) only
     * the files that mention #[Capability]: the classes they use load when used. Without it (CLASS_MAP=false) every PHP
     * file of application/ but config/ (web.php would dispatch a route) and views/.
     */
    private static function loadServices()
    {
        // the framework's own capabilities (framework/services: notifications, 2.15.0), in every project
        $files = self::frameworkServiceFiles();
        foreach ($files as $path) {
            require_once $path;
        }
        $mapped = class_exists('Caligrafy\\ClassMap', false) && ClassMap::registered();
        if (!defined('APP_PATH') || !is_dir(APP_PATH . 'services')) {
            return $mapped ? $files : null;
        }
        // with the class map (2.18.0): only the files that mention #[Capability]; the classes they use load when used
        if ($mapped) {
            foreach (ClassMap::capabilityFiles(true) as $path) {
                require_once $path;
                $files[] = $path;
            }
            return $files;
        }
        $skip = array(APP_PATH . 'config' . DIRECTORY_SEPARATOR, APP_PATH . 'views' . DIRECTORY_SEPARATOR);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APP_PATH, \FilesystemIterator::SKIP_DOTS));
        $paths = array();
        foreach ($files as $file) {
            $path = (string) $file;
            if (substr($path, -4) === '.php' && strpos($path, $skip[0]) !== 0 && strpos($path, $skip[1]) !== 0) {
                $paths[] = $path;
            }
        }
        sort($paths);
        foreach ($paths as $path) {
            require_once $path;
        }
        return null;   // no class map: no cache, the registry is built on every request as before
    }

    /**
     * Forgets the registry (tests)
     */
    public static function reset()
    {
        self::$registry = null;
        self::$extra = array();
        self::$cache = null;
        self::$found = array();
    }
}
