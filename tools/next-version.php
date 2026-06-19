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

$latest = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--latest=') === 0) {
        $latest = substr($arg, 9);
    }
}

if ($latest === null || $latest === '' || strtolower($latest) === 'none') {
    echo "1.0.0\n";
    exit(0);
}

$latest = ltrim($latest, 'v');

if (!preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/', $latest, $matches)) {
    fwrite(STDERR, "Latest version must be semver, vMAJOR.MINOR.PATCH, or none.\n");
    exit(1);
}

$major = (int)$matches[1];
$minor = (int)$matches[2];

if ($minor < 9) {
    $minor++;
} else {
    $major++;
    $minor = 0;
}

echo $major . '.' . $minor . ".0\n";
