<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */

require_once dirname(__DIR__) . '/support/fakes.php';

mcp_test_case('tool executor returns non-sensitive diagnostic status for diagnostic aliases', function () {
    $executor = new \OpenCartMcp\ToolExecutor(new McpTestProtocolRepository());
    $status = $executor->execute('admin.diagnostic.status', array(), array('client_id' => 7), 'req-1');
    $permissions = $executor->execute('admin.diagnostic.permissions', array(), array('client_id' => 7), 'req-2');

    mcp_assert_same('OpenCart MCP Server', $status['extension'], 'diagnostic status should identify extension');
    mcp_assert_same(false, $status['streaming'], 'diagnostic status should not claim streaming support');
    mcp_assert_same('admin.diagnostic.permissions', $permissions['tool'], 'diagnostic alias should echo requested tool');
    mcp_assert_array_has_key('status', $permissions, 'diagnostic alias should include bounded status payload');
});

mcp_test_case('tool executor rejects sensitive setting writes before persistence', function () {
    $executor = new \OpenCartMcp\ToolExecutor(new McpTestProtocolRepository());

    try {
        $executor->execute('admin.setting.update', array(
            'settings' => array('payment_secret' => 'x'),
            'reason' => 'unsafe',
        ), array('client_id' => 7), 'req-3');
        mcp_test_fail('sensitive setting writes should throw');
    } catch (\OpenCartMcp\McpException $e) {
        mcp_assert_same('INVALID_INPUT', $e->getMcpCode(), 'sensitive setting writes should be rejected');
    }
});

mcp_test_case('tool executor rejects media paths outside catalog image directory', function () {
    $executor = new \OpenCartMcp\ToolExecutor(new McpTestProtocolRepository());

    try {
        $executor->execute('admin.media.upload', array(
            'target_path' => '../config.php',
            'content_base64' => 'abcd',
            'reason' => 'unsafe',
        ), array('client_id' => 7), 'req-4');
        mcp_test_fail('unsafe media path should throw');
    } catch (\OpenCartMcp\McpException $e) {
        mcp_assert_same('INVALID_INPUT', $e->getMcpCode(), 'unsafe media paths should be rejected');
    }
});

mcp_test_case('tool executor rejects unknown tools with MCP-safe error code', function () {
    $executor = new \OpenCartMcp\ToolExecutor(new McpTestProtocolRepository());

    try {
        $executor->execute('database.execute_sql', array(), array('client_id' => 7), 'req-5');
        mcp_test_fail('unknown tool should throw');
    } catch (\OpenCartMcp\McpException $e) {
        mcp_assert_same('TOOL_NOT_FOUND', $e->getMcpCode(), 'unknown tools should not fall through to generic execution');
    }
});
