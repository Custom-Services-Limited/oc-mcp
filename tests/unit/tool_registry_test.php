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

mcp_test_case('tool registry exposes explicit PRD tools with write confirmation metadata', function () {
    $registry = new \OpenCartMcp\ToolRegistry();

    mcp_assert_true(count($registry->all()) >= 50, 'registry should expose expanded PRD tool surface');
    mcp_assert_true($registry->find('admin.inventory.adjust')['confirmation'] === true, 'inventory adjustment should require confirmation');
    mcp_assert_true($registry->find('admin.product.update_price')['confirmation'] === true, 'price updates should require confirmation');
    mcp_assert_true($registry->find('admin.coupon.create')['confirmation'] === true, 'coupon creation should require confirmation');
});

mcp_test_case('tool registry filters available tools by client scopes and allowed tool list', function () {
    $registry = new \OpenCartMcp\ToolRegistry();
    $client = array(
        'scopes' => array('mcp:protocol', 'catalog:read'),
        'capability_packs' => array('catalog_read'),
        'allowed_tools' => array('catalog.product.search'),
    );
    $visible = array_map(function ($tool) {
        return $tool['name'];
    }, $registry->availableForClient($client));

    mcp_assert_same(array('catalog.product.search'), $visible, 'allowed tool list should narrow visible tools');
});

mcp_test_case('tool registry derives scopes and high-risk selection from capability packs', function () {
    $registry = new \OpenCartMcp\ToolRegistry();
    $scopes = $registry->scopesForPacks(array('catalog_read', 'admin_catalog_write'));

    mcp_assert_true(in_array('mcp:protocol', $scopes, true), 'derived scopes should always include protocol scope');
    mcp_assert_true(in_array('catalog:read', $scopes, true), 'catalog read scope should be derived');
    mcp_assert_true(in_array('admin:catalog_write', $scopes, true), 'admin write scope should be derived');
    mcp_assert_true($registry->selectionHasHighRisk(array('admin_catalog_write')), 'write packs should be high risk');
    mcp_assert_true(!$registry->selectionHasHighRisk(array('catalog_read')), 'read-only catalog pack should not be high risk');
});
