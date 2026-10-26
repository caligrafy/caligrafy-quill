<?php
/**
 * Bootstrap of the unit tests: loads the core classes that are tested without starting the framework
 * (no web server, no database, no .env). Run with vendor/bin/phpunit
 */

define('APP_KEY', base64_encode(str_repeat('k', 32)));
putenv('APP_KEY=' . APP_KEY);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/framework/helpers/corehelpers.php';
foreach (['Errors', 'Route', 'Request', 'Model', 'Validator', 'Controller', 'Upgrader', 'Doctor'] as $class) {
    require_once $root . "/framework/core/$class.php";
}
require __DIR__ . '/fixtures/controllers.php';
