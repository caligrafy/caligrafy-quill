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
 * 2. checks the context: public, or signed in / a token (unauthenticated), with the scope and level (forbidden)
 * 3. keeps, filters and validates the declared inputs (invalid)
 * 4. runs handle()
 * 5. writes the audit log: every refusal, and every success of a capability that is not read-only
 * and returns plain data (models become arrays of their columns).
 */
class Capabilities
{
    /** @var array|null name => ['class' => ..., 'capability' => Capability] */
    private static $registry = null;

    /** @var array extra classes registered by hand (tests, packages) */
    private static $extra = array();

    /**
     * Registers a service class that is not in application/services/
     */
    public static function register($class)
    {
        self::$extra[] = ltrim($class, '\\');
        self::$registry = null;
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
        self::loadServices();
        $registry = array();
        $classes = array_unique(array_merge(get_declared_classes(), self::$extra));
        sort($classes);
        foreach ($classes as $class) {
            if (!class_exists($class) || !is_subclass_of($class, Service::class)) {
                continue;
            }
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
        }
        ksort($registry);
        return self::$registry = $registry;
    }

    /**
     * One capability (null when there is none of that name)
     */
    public static function get($name)
    {
        return self::all()[$name] ?? null;
    }

    /**
     * The capabilities a context may call (MCP lists only these)
     */
    public static function available(Context $context)
    {
        return array_filter(self::all(), function ($entry) use ($context) {
            $capability = $entry['capability'];
            return $capability->public || $context->allows($capability->scope, $capability->level);
        });
    }

    /**
     * Calls a capability
     * @param string $name the capability name
     * @param array $input the inputs (extra keys are ignored)
     * @param Context $context who calls, through which channel
     * @return mixed plain data
     * @throws ServiceError
     */
    public static function call($name, array $input, Context $context)
    {
        $entry = self::get($name);
        if ($entry === null) {
            throw ServiceError::notFound("There is no capability '$name'");
        }
        $capability = $entry['capability'];
        try {
            if (!$capability->public) {
                if (!$context->authenticated()) {
                    throw ServiceError::unauthenticated();
                }
                if (!$context->allows($capability->scope, $capability->level)) {
                    throw ServiceError::forbidden($capability->scope !== '' ? "This needs the scope {$capability->scope}" . ($capability->level > 0 ? " and permission level {$capability->level}" : '') : 'You are not allowed to do this');
                }
            }
            $service = new $entry['class']();
            $result = self::plain($service->handle($service->validate($input), $context));
        } catch (ServiceError $e) {
            Audit::record($context, $name, $e->kind, $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
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
                    case 'max_len': $property['maxLength'] = (int) $argument; break;
                    case 'min_len': $property['minLength'] = (int) $argument; break;
                    case 'exact_len': $property['minLength'] = $property['maxLength'] = (int) $argument; break;
                    case 'min_numeric': $property['minimum'] = $argument + 0; break;
                    case 'max_numeric': $property['maximum'] = $argument + 0; break;
                    case 'contains_list': $property['enum'] = array_values(array_filter(array_map('trim', explode(';', (string) $argument)), 'strlen')); break;
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

    /**
     * The description of every capability, as a list (php caligrafer.php capabilities, the REST listing)
     * @param Context|null $context only the capabilities this context may call
     */
    public static function describe(?Context $context = null)
    {
        $list = array();
        foreach ($context ? self::available($context) : self::all() as $name => $entry) {
            $capability = $entry['capability'];
            $list[] = array('name' => $name, 'description' => $capability->description, 'scope' => $capability->scope,
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
     * Requires the PHP files of application/ before the registry is built: the services, and the classes they use
     * (models, helpers next to the controllers). The web autoloader does the same on the first unknown class; the CLI
     * (php caligrafer.php capabilities, mcp, client) does not. config/ (web.php would dispatch a route) and views/ are skipped.
     */
    private static function loadServices()
    {
        if (!defined('APP_PATH') || !is_dir(APP_PATH . 'services')) {
            return;
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
    }

    /**
     * Forgets the registry (tests)
     */
    public static function reset()
    {
        self::$registry = null;
        self::$extra = array();
    }
}
