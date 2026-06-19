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

function mcp_protocol_error_code($response) {
    return isset($response['error']['data']['code']) ? $response['error']['data']['code'] : '';
}

mcp_test_case('protocol rejects invalid JSON before dispatch', function () {
    $repository = new McpTestProtocolRepository();
    $server = new \OpenCartMcp\ProtocolServer($repository);
    $response = $server->handle('{bad json', array('REMOTE_ADDR' => '127.0.0.1'), array(), array());

    mcp_assert_same('INVALID_JSON', mcp_protocol_error_code($response), 'invalid JSON should be rejected with MCP-safe error code');
    mcp_assert_same('error', $repository->audits[0]['status'], 'invalid JSON should still be audited');
});

mcp_test_case('protocol rejects missing and invalid bearer tokens', function () {
    $repository = new McpTestProtocolRepository();
    $server = new \OpenCartMcp\ProtocolServer($repository);
    $missing = $server->handle('{"jsonrpc":"2.0","id":1,"method":"initialize"}', array('REMOTE_ADDR' => '127.0.0.1'), array(), array());
    $invalid = $server->handle('{"jsonrpc":"2.0","id":2,"method":"initialize"}', array('REMOTE_ADDR' => '127.0.0.1'), array('Authorization' => 'Bearer wrong'), array());

    mcp_assert_same('AUTHENTICATION_FAILED', mcp_protocol_error_code($missing), 'missing bearer token should be rejected');
    mcp_assert_same('AUTHENTICATION_FAILED', mcp_protocol_error_code($invalid), 'invalid bearer token should be rejected');
});

mcp_test_case('protocol rejects query-string tokens, disabled server, oversized body, and rate limits', function () {
    $repository = new McpTestProtocolRepository();
    $server = new \OpenCartMcp\ProtocolServer($repository);
    $queryToken = $server->handle('{"jsonrpc":"2.0","id":1,"method":"initialize"}', array(), array(), array('token' => 'valid'));
    mcp_assert_same('TOKEN_IN_QUERY_REJECTED', mcp_protocol_error_code($queryToken), 'query string token should be rejected');

    $repository = new McpTestProtocolRepository(null, array('module_mcp_max_body_bytes' => 5));
    $server = new \OpenCartMcp\ProtocolServer($repository);
    $large = $server->handle('{"jsonrpc":"2.0","id":1,"method":"initialize"}', array(), array(), array());
    mcp_assert_same('REQUEST_TOO_LARGE', mcp_protocol_error_code($large), 'oversized request body should be rejected');

    $repository = new McpTestProtocolRepository();
    $repository->enabled = false;
    $server = new \OpenCartMcp\ProtocolServer($repository);
    $disabled = $server->handle('{"jsonrpc":"2.0","id":1,"method":"initialize"}', array(), array('Authorization' => 'Bearer valid'), array());
    mcp_assert_same('SERVER_DISABLED', mcp_protocol_error_code($disabled), 'disabled server should reject authenticated requests');

    $repository = new McpTestProtocolRepository();
    $repository->rateLimitAllowed = false;
    $server = new \OpenCartMcp\ProtocolServer($repository);
    $limited = $server->handle('{"jsonrpc":"2.0","id":1,"method":"initialize"}', array('REMOTE_ADDR' => '127.0.0.1'), array('Authorization' => 'Bearer valid'), array());
    mcp_assert_same('RATE_LIMITED', mcp_protocol_error_code($limited), 'rate-limited client should be rejected');
});

mcp_test_case('protocol enforces IP and origin allowlists', function () {
    $client = array(
        'client_id' => 7,
        'name' => 'Restricted Client',
        'scopes' => array('mcp:protocol', 'catalog:read'),
        'capability_packs' => array('catalog_read'),
        'allowed_tools' => array(),
        'allowed_store_ids' => array(),
        'ip_allowlist' => '203.0.113.10',
        'origin_allowlist' => 'shop.example.com',
        'rate_limit_per_minute' => 60,
        'user_group_id' => 1,
    );
    $server = new \OpenCartMcp\ProtocolServer(new McpTestProtocolRepository($client));

    $badIp = $server->handle('{"jsonrpc":"2.0","id":1,"method":"initialize"}', array('REMOTE_ADDR' => '198.51.100.20'), array('Authorization' => 'Bearer valid', 'Origin' => 'https://shop.example.com'), array());
    $badOrigin = $server->handle('{"jsonrpc":"2.0","id":2,"method":"initialize"}', array('REMOTE_ADDR' => '203.0.113.10'), array('Authorization' => 'Bearer valid', 'Origin' => 'https://admin.example.com'), array());
    $allowed = $server->handle('{"jsonrpc":"2.0","id":3,"method":"initialize"}', array('REMOTE_ADDR' => '203.0.113.10'), array('Authorization' => 'Bearer valid', 'Origin' => 'https://shop.example.com'), array());

    mcp_assert_same('IP_NOT_ALLOWED', mcp_protocol_error_code($badIp), 'non-allowlisted IP should be rejected');
    mcp_assert_same('ORIGIN_NOT_ALLOWED', mcp_protocol_error_code($badOrigin), 'non-allowlisted origin should be rejected');
    mcp_assert_array_has_key('result', $allowed, 'allowlisted IP and origin should pass');
});

mcp_test_case('protocol filters tools and resources by client scope', function () {
    $repository = new McpTestProtocolRepository();
    $server = new \OpenCartMcp\ProtocolServer($repository);

    $tools = $server->handle('{"jsonrpc":"2.0","id":1,"method":"tools/list"}', array('REMOTE_ADDR' => '127.0.0.1'), array('Authorization' => 'Bearer valid'), array());
    $toolNames = array_map(function ($tool) {
        return $tool['name'];
    }, $tools['result']['tools']);

    $resources = $server->handle('{"jsonrpc":"2.0","id":2,"method":"resources/list"}', array('REMOTE_ADDR' => '127.0.0.1'), array('Authorization' => 'Bearer valid'), array());
    $resourceUris = array_map(function ($resource) {
        return $resource['uri'];
    }, $resources['result']['resources']);

    mcp_assert_true(in_array('catalog.product.search', $toolNames, true), 'catalog tool should be visible to catalog client');
    mcp_assert_true(!in_array('admin.product.search', $toolNames, true), 'admin tool should be hidden from catalog client');
    mcp_assert_true(in_array('opencart://store/config', $resourceUris, true), 'store config resource should be visible');
    mcp_assert_true(!in_array('opencart://admin/schema/customer', $resourceUris, true), 'admin schema resource should be hidden');
});
