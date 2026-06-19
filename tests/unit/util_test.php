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

mcp_test_case('util canonical hash is stable for associative key order', function () {
    $first = \OpenCartMcp\Util::hashInput(array('b' => 2, 'a' => array('z' => 1, 'y' => 2)));
    $second = \OpenCartMcp\Util::hashInput(array('a' => array('y' => 2, 'z' => 1), 'b' => 2));

    mcp_assert_same($first, $second, 'canonical hash should ignore associative array key order');
});

mcp_test_case('util redacts sensitive keys recursively', function () {
    $redacted = \OpenCartMcp\Util::redact(array(
        'token' => 'secret-token',
        'nested' => array('password' => 'secret-password', 'visible' => 'ok'),
    ));

    mcp_assert_same('[redacted]', $redacted['token'], 'top-level token should be redacted');
    mcp_assert_same('[redacted]', $redacted['nested']['password'], 'nested password should be redacted');
    mcp_assert_same('ok', $redacted['nested']['visible'], 'non-sensitive fields should remain visible');
});

mcp_test_case('util reads authorization headers case-insensitively and prefers REMOTE_ADDR for client IP', function () {
    $headers = array('authorization' => 'Bearer valid-token');

    mcp_assert_same('valid-token', \OpenCartMcp\Util::bearerToken($headers), 'bearer token should be parsed case-insensitively');
    mcp_assert_same(
        '203.0.113.10',
        \OpenCartMcp\Util::clientIp(array('REMOTE_ADDR' => '203.0.113.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20')),
        'REMOTE_ADDR should be preferred over spoofable forwarded headers'
    );
});

mcp_test_case('util applies IP and origin allowlists exactly', function () {
    mcp_assert_true(\OpenCartMcp\Util::ipAllowed('203.0.113.10', '203.0.113.10, 198.51.100.20'), 'matching IP should be allowed');
    mcp_assert_true(!\OpenCartMcp\Util::ipAllowed('203.0.113.10', '198.51.100.20'), 'non-matching IP should be denied');
    mcp_assert_true(\OpenCartMcp\Util::originAllowed('https://shop.example.com', 'shop.example.com'), 'matching origin host should be allowed');
    mcp_assert_true(!\OpenCartMcp\Util::originAllowed('https://admin.example.com', 'shop.example.com'), 'non-matching origin host should be denied');
});
