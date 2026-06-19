<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */

require_once dirname(__DIR__) . '/support/assertions.php';
mcp_test_bootstrap();

mcp_test_case('token service hashes and verifies bearer tokens without storing plaintext', function () {
    $service = new \OpenCartMcp\TokenService('test-secret');
    $otherSecret = new \OpenCartMcp\TokenService('other-secret');
    $token = $service->generateToken();
    $hash = $service->hashToken($token);

    mcp_assert_same('ocmcp_', substr($token, 0, 6), 'generated token should use the OpenCart MCP prefix');
    mcp_assert_true(strlen($token) >= 46, 'generated token should include enough entropy text');
    mcp_assert_true(strpos($hash, $token) === false, 'token hash must not contain plaintext token');
    mcp_assert_true($service->verify($token, $hash), 'matching token should verify');
    mcp_assert_true(!$service->verify($token . 'x', $hash), 'modified token should not verify');
    mcp_assert_true(!$otherSecret->verify($token, $hash), 'hash should be bound to the configured secret');
});

mcp_test_case('token hints reveal only fixed prefix and suffix fragments', function () {
    $service = new \OpenCartMcp\TokenService('test-secret');
    $hint = $service->hint('ocmcp_abcdefghijklmnopqrstuvwxyz0123456789');

    mcp_assert_same('ocmcp_abcd...456789', $hint, 'token hint should expose only the first 10 and last 6 chars');
});
