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
// Database reads these at its first use; no database in the unit tests (Database::useConnection() gives one SQLite)
foreach (['DB_ACTIVATE' => 'false', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => 'localhost', 'DB_DATABASE' => 'none', 'DB_USERNAME' => '', 'DB_PASSWORD' => ''] as $name => $value) {
    defined($name) || define($name, $value);
}
defined('ROUTE_ARG_PATTERN') || define('ROUTE_ARG_PATTERN', '#\{+(.*?)\}+#');
foreach (['Framework', 'Errors', 'Route', 'Request', 'Model', 'Validator', 'Controller', 'Upgrader', 'Agent', 'Doctor', 'FaceLogin', 'Passkey', 'Auth', 'Tokens', 'Context', 'Audit', 'ServiceError', 'Capability', 'Service', 'Capabilities', 'Quickstart', 'Deployer', 'CloudClient', 'Designs', 'Updater', 'PluginSource', 'Scheduler', 'Notify', 'Mail', 'ClassMap', 'View', 'Migrations', 'Container', 'Cache', 'Query', 'PageController', 'Flash', 'Uploads', 'Form', 'Webhook', 'Download', 'Throttle', 'AuthRoutes'] as $class) {
    require_once $root . "/framework/core/$class.php";
}
foreach (['Rest', 'Mcp/Server', 'Mcp/Http', 'Client'] as $class) {
    require_once $root . "/framework/interfaces/$class.php";
}
require_once $root . '/framework/database/Database.php';
require __DIR__ . '/fixtures/controllers.php';
require __DIR__ . '/fixtures/SoftAuthenticator.php';
require __DIR__ . '/fixtures/services.php';
require __DIR__ . '/fixtures/query.php';
