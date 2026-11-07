<?php
/**
 * Container.php
 * A small dependency-injection container: what a class needs is given to its constructor
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Container (2.18.0)
 * Capabilities are made by the container, so a service asks for what it uses in its constructor:
 *
 *     public function __construct(private Mailer $mailer) {}
 *
 * A class the container has no binding for is built with its constructor's parameters resolved the same way (a class
 * or interface type -> Container::get, else the parameter's default). Bindings go in application/config/container.php:
 *
 *     Container::singleton(PaymentGateway::class, fn () => new StripeGateway(getenv('STRIPE_SECRET')));
 *     Container::bind(Clock::class, SystemClock::class);
 *
 * A test swaps one with Container::instance(Mailer::class, $fake), then Container::reset() forgets it.
 */
class Container
{
    const FILE = 'config/container.php';

    private static $bindings = array();    // id => ['factory' => callable|string, 'shared' => bool]
    private static $instances = array();   // id => object
    private static $resolving = array();
    private static $booted = false;

    /**
     * A new object each time: $factory is a callable returning it, or a class name (an interface bound to a class)
     */
    public static function bind($id, $factory = null)
    {
        self::register($id, $factory, false);
    }

    /**
     * One object for the whole request, made the first time it is asked for
     */
    public static function singleton($id, $factory = null)
    {
        self::register($id, $factory, true);
    }

    /**
     * This object for $id (a test's fake, for example)
     */
    public static function instance($id, $object)
    {
        $id = self::id($id);
        self::$instances[$id] = $object;
        return $object;
    }

    /**
     * Whether $id has a binding or an instance
     */
    public static function has($id)
    {
        $id = self::id($id);
        return isset(self::$instances[$id]) || isset(self::$bindings[$id]);
    }

    /**
     * The object for $id: its instance, its binding, or the class built with its dependencies
     */
    public static function get($id)
    {
        self::boot();
        $id = self::id($id);
        if (isset(self::$instances[$id])) {
            return self::$instances[$id];
        }
        if (!isset(self::$bindings[$id])) {
            return self::make($id);
        }
        $binding = self::$bindings[$id];
        $factory = $binding['factory'];
        $object = is_string($factory) ? self::make($factory) : $factory();
        if ($binding['shared']) {
            self::$instances[$id] = $object;
        }
        return $object;
    }

    /**
     * Builds $class (never a shared instance): the constructor parameters named in $parameters, then by type, then
     * their defaults
     */
    public static function make($class, array $parameters = array())
    {
        self::boot();
        $class = self::id($class);
        if (!class_exists($class)) {
            throw new \LogicException(interface_exists($class)
                ? "The container cannot build $class: it is an interface. Bind it to a class in application/" . self::FILE
                : "The container cannot build $class: there is no such class");
        }
        if (isset(self::$resolving[$class])) {
            throw new \LogicException('The container cannot build ' . $class . ': it needs itself (' . implode(' -> ', array_keys(self::$resolving)) . " -> $class)");
        }
        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new \LogicException("The container cannot build $class: it is abstract or its constructor is not public");
        }
        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }
        self::$resolving[$class] = true;
        try {
            $args = array();
            foreach ($constructor->getParameters() as $parameter) {
                $args[] = self::argument($class, $parameter, $parameters);
            }
        } finally {
            unset(self::$resolving[$class]);
        }
        return $reflection->newInstanceArgs($args);
    }

    /**
     * Loads application/config/container.php once (the web front controller does it after config.php; the CLI and
     * tests the first time the container is used)
     */
    public static function boot()
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        if (defined('APP_PATH') && is_file(APP_PATH . self::FILE)) {
            require APP_PATH . self::FILE;
        }
    }

    /**
     * Forgets every binding and instance (tests); the bindings file is read again on next use
     */
    public static function reset()
    {
        self::$bindings = array();
        self::$instances = array();
        self::$resolving = array();
        self::$booted = false;
    }

    private static function register($id, $factory, $shared)
    {
        $id = self::id($id);
        if ($factory !== null && !is_callable($factory) && !is_string($factory)) {
            throw new \InvalidArgumentException("The binding of $id must be a callable or a class name");
        }
        self::$bindings[$id] = array('factory' => $factory ?? $id, 'shared' => $shared);
        unset(self::$instances[$id]);
    }

    private static function argument($class, \ReflectionParameter $parameter, array $given)
    {
        $name = $parameter->getName();
        if (array_key_exists($name, $given)) {
            return $given[$name];
        }
        $type = $parameter->getType();
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $wanted = $type->getName();
            if (self::has($wanted) || class_exists($wanted)) {
                return self::get($wanted);
            }
            if (!$parameter->isDefaultValueAvailable() && !$type->allowsNull()) {
                return self::get($wanted);       // says that the interface needs a binding
            }
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        if ($type !== null && $type->allowsNull()) {
            return null;
        }
        throw new \LogicException("The container cannot build $class: nothing gives its parameter \$$name"
            . ($type ? " ($type)" : '') . '. Give it a default, a class type, or bind it in application/' . self::FILE);
    }

    private static function id($id)
    {
        return ltrim((string) $id, '\\');
    }
}
