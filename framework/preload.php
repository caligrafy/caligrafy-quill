<?php
/**
 * preload.php (2.26.0): OPcache preloading of the production image (docker/production/php.ini: opcache.preload).
 * Run once when Apache starts: the framework's classes and the libraries every request uses are compiled into shared
 * memory, so no request compiles or loads them again. Measured on the benchmark (2 CPUs): 5 to 25% more requests per
 * second than OPcache alone, and a faster first request. Only files that just declare classes are compiled: a file
 * that declares functions or runs code (corehelpers.php, a library's bootstrap) is left to its normal include, where
 * a second declaration would be an error. A file that fails to compile is skipped: preloading never stops Apache.
 */

$root = dirname(__DIR__);
require_once $root . '/framework/core/ClassMap.php';

$files = array();
foreach (array('framework/core', 'framework/core/Snap', 'framework/core/Async', 'framework/database', 'framework/interfaces', 'framework/interfaces/Mcp') as $dir) {
    foreach (glob("$root/$dir/*.php") ?: array() as $file) {
        $files[] = $file;
    }
}
// the libraries of every request: Composer's loader, the .env reader, the Pug views, the validator
$files[] = "$root/vendor/composer/ClassLoader.php";
foreach (array('vendor/vlucas/phpdotenv/src', 'vendor/graham-campbell/result-type/src', 'vendor/phpoption/phpoption/src', 'vendor/phug', 'vendor/wixel/gump') as $dir) {
    if (!is_dir("$root/$dir")) {
        continue;
    }
    $found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($found as $file) {
        $path = (string) $file;
        if (substr($path, -4) === '.php' && !preg_match('#/(tests?|Tests?)/|/Util/TestCase|/CompatibilityUtil/#', $path)) {
            $files[] = $path;
        }
    }
}
foreach ($files as $file) {
    if (!is_file($file) || Caligrafy\ClassMap::declarations((string) file_get_contents($file))['eager']) {
        continue;
    }
    try {
        @opcache_compile_file($file);
    } catch (\Throwable $e) {
        // a class whose parent is in a library not installed: loaded normally when used
    }
}
