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
$version = '0.1.0';
foreach ($argv as $arg) {
    if (strpos($arg, '--version=') === 0) {
        $version = substr($arg, 10);
    }
}

if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
    fwrite(STDERR, "Version must be semver, e.g. 1.2.3\n");
    exit(1);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP ZipArchive extension is required.\n");
    exit(1);
}

$dist = $root . '/dist';
$work = $dist . '/work';
remove_dir($dist);
mkdir($work, 0777, true);

build_oc3($root, $work, $dist, $version);
build_oc4($root, $work, $dist, $version);
remove_dir($work);

echo "Built release artifacts in dist/" . PHP_EOL;

function build_oc3($root, $work, $dist, $version) {
    $package = $work . '/opencart3';
    mkdir($package, 0777, true);
    copy_dir($root . '/src/opencart3', $package);
    copy_dir($root . '/src/shared/system/library/mcp', $package . '/upload/system/library/mcp');
    replace_in_file($package . '/install.xml', '<version>0.1.0</version>', '<version>' . $version . '</version>');
    zip_dir($package, $dist . '/oc_mcp-opencart3-v' . $version . '.ocmod.zip');
}

function build_oc4($root, $work, $dist, $version) {
    $package = $work . '/opencart4';
    mkdir($package, 0777, true);
    copy_dir($root . '/src/opencart4', $package);
    copy_dir($root . '/src/shared/system/library/mcp', $package . '/system/library/mcp');
    $json = json_decode(file_get_contents($package . '/install.json'), true);
    $json['version'] = $version;
    file_put_contents($package . '/install.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    zip_dir($package, $dist . '/mcp.ocmod.zip');
}

function copy_dir($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0777, true);
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $target = $dest . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0777, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

function zip_dir($source, $zipFile) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Cannot create " . $zipFile . "\n");
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $path = $file->getPathname();
            $local = str_replace('\\', '/', substr($path, strlen($source) + 1));
            $zip->addFile($path, $local);
        }
    }
    $zip->close();
}

function replace_in_file($file, $search, $replace) {
    $contents = file_get_contents($file);
    file_put_contents($file, str_replace($search, $replace, $contents));
}

function remove_dir($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}
