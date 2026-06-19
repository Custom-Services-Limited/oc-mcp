<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */

error_reporting(E_ALL);

$root = dirname(__DIR__);
$php = PHP_BINARY;

function fail($message) {
    fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
    exit(1);
}

function php_files($dir) {
    if (!is_dir($dir)) {
        return array();
    }

    $files = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function test_files($dir) {
    $files = array();
    foreach (php_files($dir) as $file) {
        if (substr($file, -9) === '_test.php') {
            $files[] = $file;
        }
    }
    return $files;
}

$lintFiles = array_merge(php_files($root . '/src'), php_files($root . '/tools'), php_files($root . '/tests'));
foreach ($lintFiles as $file) {
    $output = array();
    $code = 0;
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file), $output, $code);
    if ($code !== 0) {
        fail("PHP lint failed for " . $file . "\n" . implode("\n", $output));
    }

    $source = file_get_contents($file);
    if (strpos($source, 'Copyright (c) Custom Services Limited') === false) {
        fail('PHP file missing copyright header: ' . $file);
    }
    if (strpos($source, 'https://support.opencartgreece.gr/') === false) {
        fail('PHP file missing support link header: ' . $file);
    }
}

$tests = test_files($root . '/tests');
if (!$tests) {
    fail('No unit tests found under tests/');
}

foreach ($tests as $test) {
    $output = array();
    $code = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($test), $output, $code);
    if ($code !== 0) {
        fail("Test failed: " . $test . "\n" . implode("\n", $output));
    }
}

echo "OK: lint and unit checks passed (" . count($tests) . " suites)" . PHP_EOL;
