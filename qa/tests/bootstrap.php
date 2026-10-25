<?php
/**
 * Boots the Caligrafy framework for CLI tests without dispatching a route:
 * same steps as Framework::init() + autoload, run from the project root.
 */

chdir(dirname(__DIR__, 2));
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createUnsafeMutable(getcwd());
$dotenv->load();

if (!function_exists('getallheaders')) {
    function getallheaders() { return []; }
}

require_once 'framework/helpers/initialize.php';
require_once 'framework/helpers/corehelpers.php';
loadConstantFromArray($_ENV);
require_once 'framework/settings/coresettings.php';

spl_autoload_register(function () {
    foreach ([CORE_PATH, DB_PATH, APP_PATH] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            // never load the routes (web.php) or config: they would dispatch a request
            if (substr($file, -4) === '.php' && strpos($file, DS . 'config' . DS) === false) {
                require_once $file;
            }
        }
    }
});

require_once __DIR__ . '/lib.php';
