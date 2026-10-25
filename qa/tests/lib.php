<?php
/**
 * Minimal assertion helpers for the Caligrafy Quill QA suite.
 * No dependencies so it runs both on the host and inside the web container.
 */

$GLOBALS['__qa'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

function qa_test($name, callable $fn)
{
    try {
        $fn();
        $GLOBALS['__qa']['pass']++;
        echo "  \033[32mPASS\033[0m $name\n";
    } catch (Throwable $e) {
        $GLOBALS['__qa']['fail']++;
        $GLOBALS['__qa']['failures'][] = "$name: " . $e->getMessage();
        echo "  \033[31mFAIL\033[0m $name\n       " . str_replace("\n", "\n       ", $e->getMessage()) . "\n";
    }
}

function qa_section($title)
{
    echo "\n\033[1m$title\033[0m\n";
}

function qa_assert($condition, $message = 'assertion failed')
{
    if (!$condition) {
        throw new Exception($message);
    }
}

function qa_equals($expected, $actual, $message = '')
{
    if ($expected !== $actual) {
        throw new Exception(($message ? "$message\n" : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function qa_contains($needle, $haystack, $message = '')
{
    if (strpos((string)$haystack, $needle) === false) {
        throw new Exception(($message ? "$message\n" : '') . "expected to find '$needle' in: " . substr((string)$haystack, 0, 400));
    }
}

function qa_not_contains($needle, $haystack, $message = '')
{
    if (strpos((string)$haystack, $needle) !== false) {
        throw new Exception(($message ? "$message\n" : '') . "did not expect to find '$needle' in: " . substr((string)$haystack, 0, 400));
    }
}

function qa_summary()
{
    $r = $GLOBALS['__qa'];
    echo "\n" . ($r['fail'] ? "\033[31m" : "\033[32m") . "{$r['pass']} passed, {$r['fail']} failed\033[0m\n";
    foreach ($r['failures'] as $f) {
        echo "  - " . strtok($f, "\n") . "\n";
    }
    exit($r['fail'] ? 1 : 0);
}
