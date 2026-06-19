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

mcp_test_case('next-version tool preserves release increment policy', function () {
    $root = mcp_test_root();
    $php = PHP_BINARY;

    $cases = array(
        'none' => '1.0.0',
        '1.0.0' => '1.1.0',
        '1.9.0' => '2.0.0',
    );

    foreach ($cases as $latest => $expected) {
        $output = array();
        $code = 0;
        exec(escapeshellarg($php) . ' ' . escapeshellarg($root . '/tools/next-version.php') . ' --latest=' . escapeshellarg($latest), $output, $code);
        mcp_assert_same(0, $code, 'next-version should accept latest=' . $latest);
        mcp_assert_same($expected, trim(implode("\n", $output)), 'next-version should calculate release after ' . $latest);
    }

    $output = array();
    $code = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($root . '/tools/next-version.php') . ' --latest=' . escapeshellarg('1.x.0'), $output, $code);
    mcp_assert_true($code !== 0, 'next-version should reject invalid semver');
});

mcp_test_case('registry PRD entries and executor cases stay aligned', function () {
    $root = mcp_test_root();
    $registry = new \OpenCartMcp\ToolRegistry();
    $tools = $registry->all();
    $toolSource = file_get_contents($root . '/src/shared/system/library/mcp/tools.php');
    preg_match_all("/case '([^']+)'/", $toolSource, $toolCaseMatches);
    $executorCases = array_flip($toolCaseMatches[1]);
    $missingWriteCases = array();

    foreach ($tools as $tool) {
        if (!empty($tool['write']) && !isset($executorCases[$tool['name']])) {
            $missingWriteCases[] = $tool['name'];
        }
    }

    mcp_assert_true(!$missingWriteCases, 'write tools require explicit executor cases: ' . implode(', ', $missingWriteCases));
});

mcp_test_case('internal PRD tool names are registered unless explicitly excluded', function () {
    $root = mcp_test_root();
    $registry = new \OpenCartMcp\ToolRegistry();
    $nonToolPrdBackticks = array_fill_keys(array(
        'admin.order.capture_payment',
        'admin.order.refund',
        'admin.order.void_payment',
        'admin.order.edit',
        'admin.setting.update_payment',
        'admin.setting.update_shipping',
        'admin.setting.update_tax',
        'admin.setting.update_security',
        'config.php',
        'database.execute_sql',
        'opencart.catalog_seo_review',
        'opencart.coupon_planning',
        'opencart.customer_issue_summary',
        'opencart.low_stock_triage',
        'opencart.order_support_summary',
        'opencart.price_change_dry_run',
        'opencart.product_visibility_diagnosis',
        'order.add_history',
        'product.search',
        'product.update_price',
    ), true);

    $prd = file_get_contents($root . '/docs/internal_prd.md');
    preg_match_all('/`([a-z]+(?:\.[a-z0-9_]+)+)`/', $prd, $matches);
    foreach (array_unique($matches[1]) as $toolName) {
        if (isset($nonToolPrdBackticks[$toolName]) || strpos($toolName, 'opencart://') === 0 || strpos($toolName, 'mcp.') === 0) {
            continue;
        }
        mcp_assert_true($registry->find($toolName) !== null, 'registry should expose non-excluded PRD tool table entry: ' . $toolName);
    }
});

mcp_test_case('OpenCart 3 and 4 admin integrations expose audit review and alert controls', function () {
    $root = mcp_test_root();
    $controllers = array(
        file_get_contents($root . '/src/opencart3/upload/admin/controller/extension/module/mcp.php'),
        file_get_contents($root . '/src/opencart4/admin/controller/module/mcp.php'),
    );
    foreach ($controllers as $source) {
        mcp_assert_true(strpos($source, 'function exportAudit') !== false, 'admin controller should expose audit CSV export');
        mcp_assert_true(strpos($source, 'function reviewAudit') !== false, 'admin controller should expose audit review action');
        mcp_assert_true(strpos($source, 'auditFilters') !== false, 'admin controller should support audit filters');
        mcp_assert_true(strpos($source, 'module_mcp_alert_email') !== false, 'admin controller should persist audit alert email');
    }
});

mcp_test_case('public README excludes internal release sections', function () {
    $readme = file_get_contents(mcp_test_root() . '/README.md');

    mcp_assert_true(strpos($readme, '## GitHub Actions') === false, 'README should not expose internal GitHub Actions section');
    mcp_assert_true(strpos($readme, '## Packages') === false, 'README should not expose internal packages section');
});
