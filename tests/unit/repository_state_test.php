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

function mcp_test_repository($db) {
    return new \OpenCartMcp\Repository(new McpTestRegistry(array(
        'db' => $db,
        'config' => new McpTestConfig(array('config_encryption' => 'repository-secret')),
    )));
}

function mcp_last_query($db) {
    return $db->queries ? $db->queries[count($db->queries) - 1] : '';
}

mcp_test_case('repository idempotency helpers read and write bounded result state', function () {
    $db = new McpTestDb();
    $db->rows['mcp_idempotency'] = new McpTestQueryResult(array(
        'client_id' => 7,
        'tool_name' => 'admin.inventory.adjust',
        'idempotency_key' => 'key-hash',
        'input_hash' => 'input-hash',
        'status' => 'complete',
    ));
    $repository = mcp_test_repository($db);

    $row = $repository->getIdempotency(7, 'admin.inventory.adjust', 'key-hash');
    mcp_assert_same('complete', $row['status'], 'existing idempotency row should be returned');

    $repository->saveIdempotency(7, 'admin.inventory.adjust', 'key-hash', 'input-hash', 'complete', array('ok' => true));
    $query = mcp_last_query($db);

    mcp_assert_true(strpos($query, 'mcp_idempotency') !== false, 'idempotency save should target the idempotency table');
    mcp_assert_true(strpos($query, 'admin.inventory.adjust') !== false, 'idempotency save should include tool name');
    mcp_assert_true(strpos($query, 'result_hash') !== false, 'idempotency save should store a result hash');
});

mcp_test_case('repository confirmation helper creates one-time token and consumes matching active rows', function () {
    $db = new McpTestDb();
    $repository = mcp_test_repository($db);
    $token = $repository->createConfirmation(7, 'admin.inventory.adjust', 'product', 42, 'input-hash');

    mcp_assert_true(strlen($token) >= 32, 'confirmation token should contain random entropy');
    mcp_assert_true(strpos(mcp_last_query($db), 'mcp_confirmation') !== false, 'confirmation creation should write confirmation row');

    $db->rows['mcp_confirmation'] = new McpTestQueryResult(array('confirmation_id' => 99));
    mcp_assert_true($repository->consumeConfirmation(7, 'admin.inventory.adjust', $token, 'input-hash'), 'matching active confirmation should be consumed');
    mcp_assert_true(strpos(mcp_last_query($db), "status` = 'used'") !== false, 'confirmation consumption should mark row used');
});

mcp_test_case('repository rate limit helper allows counts at limit and rejects counts above limit', function () {
    $allowedDb = new McpTestDb();
    $allowedDb->rows['mcp_rate_limit'] = new McpTestQueryResult(array('request_count' => 2));
    $allowedRepository = mcp_test_repository($allowedDb);
    mcp_assert_true($allowedRepository->checkRateLimit(array('client_id' => 7, 'rate_limit_per_minute' => 2)), 'request count at limit should be allowed');

    $deniedDb = new McpTestDb();
    $deniedDb->rows['mcp_rate_limit'] = new McpTestQueryResult(array('request_count' => 3));
    $deniedRepository = mcp_test_repository($deniedDb);
    mcp_assert_true(!$deniedRepository->checkRateLimit(array('client_id' => 7, 'rate_limit_per_minute' => 2)), 'request count above limit should be denied');
});
