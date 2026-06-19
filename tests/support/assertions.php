<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */

function mcp_test_root() {
    return dirname(__DIR__, 2);
}

function mcp_test_bootstrap() {
    require_once mcp_test_root() . '/src/shared/system/library/mcp/bootstrap.php';
}

function mcp_test_fail($message) {
    fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
    exit(1);
}

function mcp_assert_true($condition, $message) {
    if (!$condition) {
        mcp_test_fail($message);
    }
}

function mcp_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        mcp_test_fail($message . ' expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function mcp_assert_array_has_key($key, $array, $message) {
    if (!is_array($array) || !array_key_exists($key, $array)) {
        mcp_test_fail($message . ' missing key ' . $key);
    }
}

function mcp_test_case($name, $callback) {
    try {
        call_user_func($callback);
    } catch (Exception $e) {
        mcp_test_fail($name . ': ' . $e->getMessage());
    }
}
