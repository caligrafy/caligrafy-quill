<?php
/**
 * The bootstrap of an application's tests (php caligrafer.php test, 2.18.0): the framework as the CLI loads it, the
 * settings of .env, then those of .env.testing when the project has one (a test database, for example), and
 * Caligrafy\Testing\TestCase. DB_TEST_DATABASE, when set, replaces DB_DATABASE for the tests.
 */

$root = dirname(__DIR__, 2);
chdir($root);
require_once $root . '/vendor/autoload.php';

foreach (array('.env', '.env.testing') as $file) {
    if (is_file("$root/$file")) {
        Dotenv\Dotenv::createUnsafeMutable($root, $file)->load();
    }
}
if (($test = getenv('DB_TEST_DATABASE')) !== false && trim($test) !== '') {
    putenv("DB_DATABASE=$test");
    $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $test;
}
putenv('CALIGRAFY_TESTING=true');

require_once $root . '/framework/core/Caligrafer.php';
Caligrafy\Caligrafer::run();
require_once __DIR__ . '/TestCase.php';
