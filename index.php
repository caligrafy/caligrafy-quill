<?php
/**
 * index.php
 * Run time where all magic happens
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

use Caligrafy\Framework;

// Deprecation notices (often raised inside vendor libraries by new PHP versions) are never printed into pages
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once "framework/core/Framework.php";

// load external vendors
require_once 'vendor/autoload.php';
       
// load environment variables: from .env.php, compiled from .env by the deploy step or php caligrafer.php env cache,
// while .env is still what it was compiled from (2.19.0: no parsing on every request), else from .env
$envCache = is_file(__DIR__ . '/.env.php') ? @include __DIR__ . '/.env.php' : null;
$envValues = is_array($envCache) && isset($envCache['sha1'], $envCache['values']) && is_array($envCache['values'])
    && $envCache['sha1'] === @sha1_file(__DIR__ . '/.env') ? $envCache['values'] : null;
if (is_array($envValues)) {
    foreach ($envValues as $envName => $envValue) {
        putenv("$envName=$envValue");
        $_ENV[$envName] = $_SERVER[$envName] = $envValue;
    }
} else {
    $dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__);
    $dotenv->load();
}
unset($envCache, $envValues, $envName, $envValue);

Framework::run();




