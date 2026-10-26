<?php
/**
 * Rebuilds framework/settings/template-history.json: for every file of framework/settings/application,
 * the sha256 of each version it had in the git history (plus the working tree).
 * `php caligrafer.php upgrade` uses it to recognize untouched copies in projects that have no manifest.
 *
 * Run from the project root after changing a template:  php qa/build-template-history.php
 * (qa/tests/tooling_tests.sh fails when a current template version is missing from the history.)
 */

chdir(dirname(__DIR__));
$base = 'framework/settings/application/';
$history = [];
$add = function ($path, $hash) use (&$history) {
    if (basename($path) === '.DS_Store') return;
    $history[$path] = $history[$path] ?? [];
    if (!in_array($hash, $history[$path])) $history[$path][] = $hash;
};

$blobs = [];
foreach (array_filter(explode("\n", (string)shell_exec('git log --format=%H -- ' . escapeshellarg($base)))) as $commit) {
    foreach (array_filter(explode("\n", (string)shell_exec('git ls-tree -r ' . $commit . ' -- ' . escapeshellarg($base)))) as $line) {
        if (!preg_match('/^\d+ blob ([0-9a-f]+)\t(.+)$/', $line, $m)) continue;
        $blobs[$m[1]] = $blobs[$m[1]] ?? hash('sha256', (string)shell_exec('git cat-file blob ' . $m[1]));
        $add(substr($m[2], strlen($base)), $blobs[$m[1]]);
    }
}
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $add(substr(str_replace('\\', '/', $file->getPathname()), strlen($base)), hash_file('sha256', $file->getPathname()));
}
ksort($history);
foreach ($history as &$hashes) sort($hashes);
file_put_contents('framework/settings/template-history.json', json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo count($history) . " template files, " . array_sum(array_map('count', $history)) . " versions\n";
