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

$root = dirname(__DIR__, 2);
$php = PHP_BINARY;

function fail($message) {
    fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
    exit(1);
}

function assert_true($condition, $message) {
    if (!$condition) {
        fail($message);
    }
}

function php_files($dir) {
    $files = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

foreach (array_merge(php_files($root . '/src'), php_files($root . '/tools')) as $file) {
    $cmd = escapeshellarg($php) . ' -l ' . escapeshellarg($file);
    exec($cmd, $output, $code);
    if ($code !== 0) {
        fail("PHP lint failed for " . $file . "\n" . implode("\n", $output));
    }

    $source = file_get_contents($file);
    assert_true(strpos($source, 'Copyright (c) Custom Services Limited') !== false, 'PHP file missing copyright header: ' . $file);
    assert_true(strpos($source, 'https://support.opencartgreece.gr/') !== false, 'PHP file missing support link header: ' . $file);
}

require_once $root . '/src/shared/system/library/mcp/bootstrap.php';

function next_version_case($php, $root, $latest) {
    $output = array();
    $code = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($root . '/tools/next-version.php') . ' --latest=' . escapeshellarg($latest), $output, $code);
    assert_true($code === 0, 'next-version should accept latest=' . $latest);
    return trim(implode("\n", $output));
}

assert_true(next_version_case($php, $root, 'none') === '1.0.0', 'next release without tags should be 1.0.0');
assert_true(next_version_case($php, $root, '1.0.0') === '1.1.0', 'next release after 1.0.0 should be 1.1.0');
assert_true(next_version_case($php, $root, '1.9.0') === '2.0.0', 'next release after 1.9.0 should be 2.0.0');

$invalidOutput = array();
$invalidCode = 0;
exec(escapeshellarg($php) . ' ' . escapeshellarg($root . '/tools/next-version.php') . ' --latest=' . escapeshellarg('1.x.0') . ' 2>/dev/null', $invalidOutput, $invalidCode);
assert_true($invalidCode !== 0, 'next-version should reject invalid semver');

$tokenService = new \OpenCartMcp\TokenService('test-secret');
$token = $tokenService->generateToken();
$hash = $tokenService->hashToken($token);
assert_true($tokenService->verify($token, $hash), 'token verification should pass');
assert_true(!$tokenService->verify($token . 'x', $hash), 'changed token should fail verification');
assert_true(strpos($hash, $token) === false, 'token hash must not contain plaintext token');

$registry = new \OpenCartMcp\ToolRegistry();
$tools = $registry->all();
assert_true(count($tools) >= 50, 'registry should expose expanded PRD tool surface');
assert_true($registry->find('admin.inventory.adjust')['confirmation'] === true, 'inventory adjust must require confirmation');
assert_true($registry->find('admin.product.update_price')['confirmation'] === true, 'product price update must require confirmation');
assert_true($registry->find('admin.coupon.create')['confirmation'] === true, 'coupon create must require confirmation');
assert_true(strpos($registry->find('admin.inventory.adjust')['description'], 'WRITE') !== false, 'write tool description should warn about side effects');
$toolSource = file_get_contents($root . '/src/shared/system/library/mcp/tools.php');
preg_match_all("/case '([^']+)'/", $toolSource, $toolCaseMatches);
$executorCases = array_flip($toolCaseMatches[1]);
$missingWriteCases = array();
foreach ($tools as $tool) {
    if (!empty($tool['write']) && !isset($executorCases[$tool['name']])) {
        $missingWriteCases[] = $tool['name'];
    }
}
assert_true(!$missingWriteCases, 'write tools require explicit executor cases: ' . implode(', ', $missingWriteCases));

$requiredPrdTools = array(
    'cart.remove_item',
    'cart.apply_coupon',
    'cart.remove_coupon',
    'cart.apply_voucher',
    'cart.apply_reward',
    'cart.quote_shipping',
    'cart.select_shipping',
    'cart.quote_payment',
    'cart.select_payment',
    'checkout.validate',
    'customer.self.update_profile',
    'customer.self.list_addresses',
    'customer.self.add_address',
    'customer.self.update_address',
    'customer.self.delete_address',
    'customer.self.downloads',
    'customer.self.returns',
    'customer.self.create_return',
    'customer.self.reward_points',
);
foreach ($requiredPrdTools as $toolName) {
    assert_true($registry->find($toolName) !== null, 'registry should expose PRD tool: ' . $toolName);
}

$nonToolPrdBackticks = array(
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
);
$nonToolPrdBackticks = array_fill_keys($nonToolPrdBackticks, true);
$prd = file_get_contents($root . '/docs/internal_prd.md');
preg_match_all('/`([a-z]+(?:\.[a-z0-9_]+)+)`/', $prd, $prdToolMatches);
foreach (array_unique($prdToolMatches[1]) as $toolName) {
    if (isset($nonToolPrdBackticks[$toolName]) || strpos($toolName, 'opencart://') === 0 || strpos($toolName, 'mcp.') === 0) {
        continue;
    }
    assert_true($registry->find($toolName) !== null, 'registry should expose non-excluded PRD tool table entry: ' . $toolName);
}

$client = array(
    'scopes' => array('mcp:protocol', 'catalog:read'),
    'capability_packs' => array('catalog_read'),
    'allowed_tools' => array(),
);
$visible = array_map(function ($tool) { return $tool['name']; }, $registry->availableForClient($client));
assert_true(in_array('catalog.product.search', $visible, true), 'catalog read client should see catalog product search');
assert_true(in_array('catalog.review.list', $visible, true), 'catalog read client should see catalog review list');
assert_true(!in_array('admin.order.search', $visible, true), 'catalog read client must not see admin order search');
assert_true(!in_array('customer.self.orders', $visible, true), 'catalog read client must not see customer self-service tools');

$adminClient = array(
    'scopes' => $registry->scopesForPacks(array('admin_customer_read', 'marketing', 'reports', 'settings_read')),
    'capability_packs' => array('admin_customer_read', 'marketing', 'reports', 'settings_read'),
    'allowed_tools' => array(),
);
$adminVisible = array_map(function ($tool) { return $tool['name']; }, $registry->availableForClient($adminClient));
assert_true(in_array('admin.customer.search', $adminVisible, true), 'admin customer read pack should expose customer search');
assert_true(in_array('admin.coupon.search', $adminVisible, true), 'marketing read pack should expose coupon search');
assert_true(in_array('admin.report.sales_summary', $adminVisible, true), 'reports pack should expose sales summary');
assert_true(!in_array('admin.coupon.create', $adminVisible, true), 'marketing read pack must not expose coupon writes');

$validator = new \OpenCartMcp\SchemaValidator();
$schema = $registry->find('admin.inventory.adjust')['inputSchema'];
assert_true($validator->validate($schema, array('product_id' => 1, 'delta' => 2, 'reason' => 'stock count', 'dry_run' => true)) === array(), 'valid inventory adjust dry-run input should pass');
assert_true(count($validator->validate($schema, array('product_id' => 1, 'delta' => 2, 'extra' => 'x'))) >= 2, 'invalid inventory adjust input should report errors');
$schema = $registry->find('admin.product.update_images')['inputSchema'];
assert_true($validator->validate($schema, array('product_id' => 1, 'images' => array(array('image' => 'catalog/products/a.png')), 'reason' => 'image update')) === array(), 'product image update schema should accept structured image inputs');
$schema = $registry->find('admin.media.upload')['inputSchema'];
assert_true($validator->validate($schema, array('target_path' => 'catalog/mcp/test.png', 'content_base64' => 'abcd', 'reason' => 'upload')) === array(), 'media upload schema should accept base64 content inputs');
$arraySchema = array('type' => 'object', 'properties' => array('items' => array('type' => 'array', 'items' => array('type' => 'string'))), 'required' => array('items'), 'additionalProperties' => false);
assert_true($validator->validate($arraySchema, array('items' => array())) === array(), 'schema validator should accept empty arrays');
assert_true(\OpenCartMcp\Util::clientIp(array('REMOTE_ADDR' => '203.0.113.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20')) === '203.0.113.10', 'clientIp should prefer REMOTE_ADDR over spoofable forwarded headers');

class FakeConfig {
    public function get($key) {
        $values = array(
            'module_mcp_status' => '1',
            'module_mcp_display_name' => 'Test MCP',
            'module_mcp_max_body_bytes' => 1048576,
        );
        return $values[$key] ?? null;
    }
}

class FakeRepository {
    public $audits = array();

    public function db() { return null; }
    public function prefix() { return 'oc_'; }
    public function isEnabled() { return true; }
    public function config($key, $default = null) { return (new FakeConfig())->get($key) ?? $default; }
    public function findClientByToken($token) {
        if ($token !== 'valid') {
            return null;
        }
        return array(
            'client_id' => 7,
            'name' => 'Test Client',
            'scopes' => array('mcp:protocol', 'catalog:read'),
            'capability_packs' => array('catalog_read'),
            'allowed_tools' => array(),
            'allowed_store_ids' => array(),
            'ip_allowlist' => '',
            'origin_allowlist' => '',
            'rate_limit_per_minute' => 60,
            'user_group_id' => 1,
        );
    }
    public function checkRateLimit($client) { return true; }
    public function clientHasOpenCartModifyPermission($client) { return true; }
    public function audit($data) { $this->audits[] = $data; return count($this->audits); }
}

class FakeQueryResult {
    public $row;
    public $rows;

    public function __construct($row = array(), $rows = array()) {
        $this->row = $row;
        $this->rows = $rows;
    }
}

class FakeToolRepository {
    public $carts = array();
    public $customers = array();
    public $addresses = array();
    public $products = array();
    public $productDescriptions = array();
    public $productSpecials = array();
    public $productDiscounts = array();
    public $productCategories = array();
    public $productImages = array();
    public $categories = array();
    public $categoryDescriptions = array();
    public $manufacturers = array();
    public $informations = array();
    public $informationDescriptions = array();
    public $movements = array();
    public $orders = array();
    public $orderHistories = array();
    public $returns = array();
    public $coupons = array();
    public $settings = array();
    public $reviews = array();
    public $queries = array();
    private $imageRoot;
    public $lastId = 100;

    public function __construct() {
        $this->customers[42] = array(
            'customer_id' => 42,
            'customer_group_id' => 1,
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.com',
            'telephone' => '123',
            'status' => 1,
            'date_added' => '2026-01-01',
        );
        $this->addresses[5] = array(
            'address_id' => 5,
            'customer_id' => 42,
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'company' => '',
            'address_1' => '1 Loop Street',
            'address_2' => '',
            'city' => 'London',
            'postcode' => 'N1',
            'country_id' => 222,
            'zone_id' => 3513,
            'custom_field' => '',
        );
        $this->products[9] = array(
            'product_id' => 9,
            'name' => 'Test Product',
            'description' => '',
            'meta_title' => 'Test Product',
            'model' => 'TP-9',
            'sku' => 'SKU9',
            'quantity' => 10,
            'price' => '12.50',
            'image' => '',
            'status' => 1,
            'date_available' => '2026-01-01',
            'stock_status_id' => 7,
            'subtract' => 1,
        );
        $this->productDescriptions[9] = array(
            'product_id' => 9,
            'language_id' => 1,
            'name' => 'Test Product',
            'description' => '',
            'tag' => '',
            'meta_title' => 'Test Product',
            'meta_description' => '',
            'meta_keyword' => '',
        );
        $this->categories[20] = array(
            'category_id' => 20,
            'parent_id' => 0,
            'sort_order' => 0,
            'status' => 1,
            'image' => '',
        );
        $this->categoryDescriptions[20] = array(
            'category_id' => 20,
            'language_id' => 1,
            'name' => 'Test Category',
            'description' => '',
            'meta_title' => 'Test Category',
            'meta_description' => '',
            'meta_keyword' => '',
        );
        $this->manufacturers[30] = array(
            'manufacturer_id' => 30,
            'name' => 'Test Manufacturer',
            'image' => '',
            'sort_order' => 0,
        );
        $this->informations[40] = array(
            'information_id' => 40,
            'bottom' => 0,
            'sort_order' => 0,
            'status' => 1,
        );
        $this->informationDescriptions[40] = array(
            'information_id' => 40,
            'language_id' => 1,
            'title' => 'Test Page',
            'description' => '',
            'meta_title' => 'Test Page',
            'meta_description' => '',
            'meta_keyword' => '',
        );
        $this->orders[1001] = array(
            'order_id' => 1001,
            'customer_id' => 42,
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.com',
            'telephone' => '123',
            'store_id' => 0,
            'total' => '25.00',
            'currency_code' => 'GBP',
            'order_status_id' => 1,
            'date_added' => '2026-01-01',
        );
        $this->coupons[3] = array(
            'coupon_id' => 3,
            'name' => 'Save 10',
            'code' => 'SAVE10',
            'type' => 'P',
            'discount' => '10.0000',
            'total' => '0.0000',
            'logged' => 0,
            'shipping' => 0,
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'uses_total' => 100,
            'uses_customer' => 1,
            'status' => 1,
        );
        $this->settings['config_name'] = 'Old Store';
        $this->settings['config_meta_title'] = 'Old Title';
        $this->reviews[7] = array(
            'review_id' => 7,
            'product_id' => 9,
            'customer_id' => 42,
            'author' => 'Ada',
            'text' => 'Pending review',
            'rating' => 4,
            'status' => 0,
            'date_added' => '2026-01-01',
        );
        $this->imageRoot = sys_get_temp_dir() . '/oc-mcp-test-images-' . getmypid() . '/';
    }

    public function db() { return null; }
    public function prefix() { return 'oc_'; }
    public function imageRoot() { return $this->imageRoot; }
    public function isEnabled() { return true; }
    public function config($key, $default = null) {
        $values = array('config_currency' => 'GBP', 'config_language_id' => 1, 'config_stock_checkout' => '0');
        return array_key_exists($key, $values) ? $values[$key] : $default;
    }
    public function createCart($clientId, $storeId, $currencyCode, $data) {
        $cartId = 'cart-1';
        $this->carts[$cartId] = array(
            'cart_id' => $cartId,
            'client_id' => $clientId,
            'store_id' => $storeId,
            'currency_code' => $currencyCode,
            'data' => $data,
        );
        return $cartId;
    }
    public function getCart($cartId, $clientId) {
        if (!isset($this->carts[$cartId]) || (int)$this->carts[$cartId]['client_id'] !== (int)$clientId) {
            return null;
        }
        return $this->carts[$cartId];
    }
    public function saveCart($cartId, $clientId, $data) {
        $this->carts[$cartId]['data'] = $data;
    }
    public function quoteShipping($cart, $args) {
        return array(
            'available' => true,
            'quotes' => array(array('code' => 'flat.flat', 'title' => 'Flat Rate', 'cost' => 5.00)),
            'messages' => array(),
        );
    }
    public function quotePayment($cart, $args) {
        return array(
            'available' => true,
            'methods' => array(array('code' => 'cod', 'title' => 'Cash On Delivery')),
            'messages' => array(),
        );
    }
    public function verifyCustomerContext($token) {
        return $token === 'customer-token' ? 42 : 0;
    }
    public function escape($value) {
        return addslashes((string)$value);
    }
    public function lastId() {
        return $this->lastId;
    }
    public function recordInventoryMovement($data) {
        $data['movement_id'] = count($this->movements) + 1;
        $data['created_at'] = '2026-01-01';
        $this->movements[] = $data;
    }
    public function query($sql) {
        $this->queries[] = $sql;

        if (strpos($sql, 'FROM `oc_mcp_stock_movement`') !== false) {
            return new FakeQueryResult(array(), array_reverse($this->movements));
        }

        if (strpos($sql, 'FROM `oc_setting`') !== false) {
            preg_match("/`key` = '([^']+)'/", $sql, $match);
            $key = isset($match[1]) ? $match[1] : 'config_name';
            return new FakeQueryResult(isset($this->settings[$key]) ? array('value' => $this->settings[$key], 'serialized' => 0) : array());
        }

        if (strpos($sql, 'UPDATE `oc_review`') !== false) {
            if (strpos($sql, 'status') !== false && strpos($sql, "'1'") !== false) {
                $this->reviews[7]['status'] = 1;
            }
            if (strpos($sql, 'Updated review') !== false) {
                $this->reviews[7]['text'] = 'Updated review';
            }
            if (strpos($sql, 'rating') !== false && strpos($sql, "'5'") !== false) {
                $this->reviews[7]['rating'] = 5;
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'DELETE FROM `oc_review`') !== false) {
            unset($this->reviews[7]);
            return new FakeQueryResult();
        }

        if (strpos($sql, 'FROM `oc_review`') !== false) {
            return new FakeQueryResult(isset($this->reviews[7]) ? $this->reviews[7] : array(), array_values($this->reviews));
        }

        if (strpos($sql, 'DELETE FROM `oc_setting`') !== false) {
            preg_match("/`key` = '([^']+)'/", $sql, $match);
            if (isset($match[1])) {
                unset($this->settings[$match[1]]);
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'INSERT INTO `oc_setting`') !== false) {
            preg_match("/`key` = '([^']+)'/", $sql, $keyMatch);
            preg_match("/`value` = '([^']*)'/", $sql, $valueMatch);
            if (isset($keyMatch[1])) {
                $this->settings[$keyMatch[1]] = isset($valueMatch[1]) ? stripslashes($valueMatch[1]) : '';
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'FROM `oc_order_status`') !== false) {
            return new FakeQueryResult(array('order_status_id' => 2));
        }

        if (strpos($sql, 'UPDATE `oc_order`') !== false) {
            preg_match("/order_status_id = '([0-9]+)'/", $sql, $match);
            if (isset($match[1])) {
                $this->orders[1001]['order_status_id'] = (int)$match[1];
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'INSERT INTO `oc_order_history`') !== false) {
            preg_match("/order_status_id = '([0-9]+)'/", $sql, $statusMatch);
            preg_match("/notify = '([0-9]+)'/", $sql, $notifyMatch);
            $this->orderHistories[] = array(
                'order_history_id' => count($this->orderHistories) + 1,
                'order_id' => 1001,
                'order_status_id' => isset($statusMatch[1]) ? (int)$statusMatch[1] : $this->orders[1001]['order_status_id'],
                'notify' => isset($notifyMatch[1]) ? (int)$notifyMatch[1] : 0,
                'comment' => $sql,
                'date_added' => '2026-01-01',
            );
            return new FakeQueryResult();
        }

        if (strpos($sql, 'FROM `oc_order_product`') !== false) {
            return new FakeQueryResult(array('order_product_id' => 11, 'product_id' => 9, 'name' => 'Test Product', 'model' => 'TP-9', 'quantity' => 2, 'price' => '12.50', 'total' => '25.00'), array(array('order_product_id' => 11, 'product_id' => 9, 'name' => 'Test Product', 'model' => 'TP-9', 'quantity' => 2, 'price' => '12.50', 'total' => '25.00')));
        }

        if (strpos($sql, 'FROM `oc_order_total`') !== false) {
            return new FakeQueryResult(array(), array(array('code' => 'total', 'title' => 'Total', 'value' => '25.00', 'sort_order' => 9)));
        }

        if (strpos($sql, 'FROM `oc_order_history`') !== false) {
            return new FakeQueryResult(array(), array_reverse($this->orderHistories));
        }

        if (strpos($sql, 'FROM `oc_order`') !== false) {
            return new FakeQueryResult($this->orders[1001], array($this->orders[1001]));
        }

        if (strpos($sql, 'INSERT INTO `oc_return`') !== false) {
            $this->lastId = 201;
            $this->returns[] = array('return_id' => 201, 'order_id' => 1001, 'product_id' => 9);
            return new FakeQueryResult();
        }

        if (strpos($sql, 'INSERT INTO `oc_product`') !== false) {
            $this->lastId = 301;
            $this->products[301] = array(
                'product_id' => 301,
                'name' => strpos($sql, 'New Product') !== false ? 'New Product' : '',
                'description' => '',
                'meta_title' => 'New Product',
                'model' => 'NP-1',
                'sku' => '',
                'quantity' => 4,
                'price' => '19.95',
                'image' => '',
                'status' => 0,
                'date_available' => '2026-01-01',
                'stock_status_id' => 0,
                'subtract' => 1,
            );
            return new FakeQueryResult();
        }

        if (strpos($sql, 'INSERT INTO `oc_product_description`') !== false) {
            preg_match("/product_id = '([0-9]+)'/", $sql, $match);
            $productId = isset($match[1]) ? (int)$match[1] : 301;
            $this->productDescriptions[$productId] = array(
                'product_id' => $productId,
                'language_id' => 1,
                'name' => strpos($sql, 'New Product') !== false ? 'New Product' : 'Test Product',
                'description' => '',
                'tag' => '',
                'meta_title' => strpos($sql, 'New Product') !== false ? 'New Product' : 'Test Product',
                'meta_description' => '',
                'meta_keyword' => '',
            );
            return new FakeQueryResult();
        }

        if (strpos($sql, 'UPDATE `oc_product_description`') !== false) {
            preg_match("/product_id = '([0-9]+)'/", $sql, $match);
            $productId = isset($match[1]) ? (int)$match[1] : 9;
            if (isset($this->productDescriptions[$productId]) && strpos($sql, 'Updated Product') !== false) {
                $this->productDescriptions[$productId]['name'] = 'Updated Product';
                $this->products[$productId]['name'] = 'Updated Product';
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'FROM `oc_product_description`') !== false) {
            preg_match("/product_id = '([0-9]+)'/", $sql, $match);
            $productId = isset($match[1]) ? (int)$match[1] : 9;
            return new FakeQueryResult(isset($this->productDescriptions[$productId]) ? $this->productDescriptions[$productId] : array());
        }

        if (strpos($sql, 'DELETE FROM `oc_product_special`') !== false) {
            $this->productSpecials = array();
            return new FakeQueryResult();
        }
        if (strpos($sql, 'INSERT INTO `oc_product_special`') !== false) {
            $this->productSpecials[] = $sql;
            return new FakeQueryResult();
        }
        if (strpos($sql, 'DELETE FROM `oc_product_discount`') !== false) {
            $this->productDiscounts = array();
            return new FakeQueryResult();
        }
        if (strpos($sql, 'INSERT INTO `oc_product_discount`') !== false) {
            $this->productDiscounts[] = $sql;
            return new FakeQueryResult();
        }
        if (strpos($sql, 'DELETE FROM `oc_product_to_category`') !== false) {
            $this->productCategories = array();
            return new FakeQueryResult();
        }
        if (strpos($sql, 'INSERT INTO `oc_product_to_category`') !== false) {
            preg_match("/category_id = '([0-9]+)'/", $sql, $match);
            if (isset($match[1])) $this->productCategories[] = (int)$match[1];
            return new FakeQueryResult();
        }
        if (strpos($sql, 'DELETE FROM `oc_product_image`') !== false) {
            $this->productImages = array();
            return new FakeQueryResult();
        }
        if (strpos($sql, 'INSERT INTO `oc_product_image`') !== false) {
            preg_match("/image = '([^']+)'/", $sql, $match);
            $this->productImages[] = isset($match[1]) ? $match[1] : '';
            return new FakeQueryResult();
        }

        if (strpos($sql, 'UPDATE `oc_product`') !== false) {
            preg_match("/SET quantity = '(-?[0-9]+)'/", $sql, $match);
            if (isset($match[1])) {
                $this->products[9]['quantity'] = (int)$match[1];
            }
            if (strpos($sql, "price` = '14.5'") !== false || strpos($sql, "price = '14.5'") !== false) {
                $this->products[9]['price'] = '14.5';
            }
            if (strpos($sql, 'catalog/products/c.jpg') !== false) {
                $this->products[9]['image'] = 'catalog/products/c.jpg';
            } elseif (strpos($sql, 'catalog/products/a.png') !== false) {
                $this->products[9]['image'] = 'catalog/products/a.png';
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'DELETE FROM `oc_product`') !== false) {
            unset($this->products[9]);
            return new FakeQueryResult();
        }

        if (strpos($sql, 'FROM `oc_product`') !== false) {
            return new FakeQueryResult($this->products[9], array($this->products[9]));
        }

        if (strpos($sql, 'FROM `oc_product` p') !== false) {
            return new FakeQueryResult($this->products[9]);
        }

        if (strpos($sql, 'INSERT INTO `oc_category`') !== false) {
            $this->lastId = 320;
            $this->categories[320] = array('category_id' => 320, 'parent_id' => 0, 'sort_order' => 0, 'status' => 0, 'image' => '');
            return new FakeQueryResult();
        }
        if (strpos($sql, 'INSERT INTO `oc_category_description`') !== false) {
            preg_match("/category_id = '([0-9]+)'/", $sql, $match);
            $categoryId = isset($match[1]) ? (int)$match[1] : 320;
            $this->categoryDescriptions[$categoryId] = array('category_id' => $categoryId, 'language_id' => 1, 'name' => strpos($sql, 'New Category') !== false ? 'New Category' : 'Test Category', 'description' => '', 'meta_title' => 'New Category', 'meta_description' => '', 'meta_keyword' => '');
            return new FakeQueryResult();
        }
        if (strpos($sql, 'UPDATE `oc_category_description`') !== false) {
            preg_match("/category_id = '([0-9]+)'/", $sql, $match);
            $categoryId = isset($match[1]) ? (int)$match[1] : 20;
            if (isset($this->categoryDescriptions[$categoryId]) && strpos($sql, 'Updated Category') !== false) {
                $this->categoryDescriptions[$categoryId]['name'] = 'Updated Category';
            }
            return new FakeQueryResult();
        }
        if (strpos($sql, 'FROM `oc_category_description`') !== false) {
            preg_match("/category_id = '([0-9]+)'/", $sql, $match);
            $categoryId = isset($match[1]) ? (int)$match[1] : 20;
            return new FakeQueryResult(isset($this->categoryDescriptions[$categoryId]) ? $this->categoryDescriptions[$categoryId] : array());
        }
        if (strpos($sql, 'UPDATE `oc_category`') !== false) {
            preg_match("/category_id = '([0-9]+)'/", $sql, $match);
            $categoryId = isset($match[1]) ? (int)$match[1] : 20;
            if (isset($this->categories[$categoryId]) && strpos($sql, 'status') !== false) $this->categories[$categoryId]['status'] = strpos($sql, "'0'") !== false ? 0 : 1;
            return new FakeQueryResult();
        }
        if (strpos($sql, 'DELETE FROM `oc_category`') !== false) {
            unset($this->categories[20]);
            return new FakeQueryResult();
        }
        if (strpos($sql, 'FROM `oc_category`') !== false) {
            preg_match("/category_id = '([0-9]+)'/", $sql, $match);
            $categoryId = isset($match[1]) ? (int)$match[1] : 20;
            return new FakeQueryResult(isset($this->categories[$categoryId]) ? $this->categories[$categoryId] : array(), array_values($this->categories));
        }

        if (strpos($sql, 'INSERT INTO `oc_manufacturer`') !== false) {
            $this->lastId = 330;
            $this->manufacturers[330] = array('manufacturer_id' => 330, 'name' => 'New Manufacturer', 'image' => '', 'sort_order' => 0);
            return new FakeQueryResult();
        }
        if (strpos($sql, 'UPDATE `oc_manufacturer`') !== false) {
            if (strpos($sql, 'Updated Manufacturer') !== false) $this->manufacturers[30]['name'] = 'Updated Manufacturer';
            return new FakeQueryResult();
        }
        if (strpos($sql, 'FROM `oc_manufacturer`') !== false) {
            preg_match("/manufacturer_id = '([0-9]+)'/", $sql, $match);
            $manufacturerId = isset($match[1]) ? (int)$match[1] : 30;
            return new FakeQueryResult(isset($this->manufacturers[$manufacturerId]) ? $this->manufacturers[$manufacturerId] : array(), array_values($this->manufacturers));
        }

        if (strpos($sql, 'INSERT INTO `oc_information`') !== false) {
            $this->lastId = 340;
            $this->informations[340] = array('information_id' => 340, 'bottom' => 0, 'sort_order' => 0, 'status' => 0);
            return new FakeQueryResult();
        }
        if (strpos($sql, 'INSERT INTO `oc_information_description`') !== false) {
            preg_match("/information_id = '([0-9]+)'/", $sql, $match);
            $informationId = isset($match[1]) ? (int)$match[1] : 340;
            $this->informationDescriptions[$informationId] = array('information_id' => $informationId, 'language_id' => 1, 'title' => strpos($sql, 'New Page') !== false ? 'New Page' : 'Test Page', 'description' => '', 'meta_title' => 'New Page', 'meta_description' => '', 'meta_keyword' => '');
            return new FakeQueryResult();
        }
        if (strpos($sql, 'UPDATE `oc_information_description`') !== false) {
            preg_match("/information_id = '([0-9]+)'/", $sql, $match);
            $informationId = isset($match[1]) ? (int)$match[1] : 40;
            if (isset($this->informationDescriptions[$informationId]) && strpos($sql, 'Updated Page') !== false) $this->informationDescriptions[$informationId]['title'] = 'Updated Page';
            return new FakeQueryResult();
        }
        if (strpos($sql, 'FROM `oc_information_description`') !== false) {
            preg_match("/information_id = '([0-9]+)'/", $sql, $match);
            $informationId = isset($match[1]) ? (int)$match[1] : 40;
            return new FakeQueryResult(isset($this->informationDescriptions[$informationId]) ? $this->informationDescriptions[$informationId] : array());
        }
        if (strpos($sql, 'UPDATE `oc_information`') !== false) {
            return new FakeQueryResult();
        }
        if (strpos($sql, 'DELETE FROM `oc_information`') !== false) {
            unset($this->informations[40]);
            return new FakeQueryResult();
        }
        if (strpos($sql, 'FROM `oc_information`') !== false) {
            preg_match("/information_id = '([0-9]+)'/", $sql, $match);
            $informationId = isset($match[1]) ? (int)$match[1] : 40;
            return new FakeQueryResult(isset($this->informations[$informationId]) ? $this->informations[$informationId] : array(), array_values($this->informations));
        }

        if (strpos($sql, 'UPDATE `oc_coupon`') !== false) {
            if (strpos($sql, 'SAVE20') !== false) {
                $this->coupons[3]['code'] = 'SAVE20';
            }
            if (strpos($sql, 'Updated Coupon') !== false) {
                $this->coupons[3]['name'] = 'Updated Coupon';
            }
            if (strpos($sql, 'status = 0') !== false) {
                $this->coupons[3]['status'] = 0;
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'DELETE FROM `oc_coupon`') !== false) {
            unset($this->coupons[3]);
            return new FakeQueryResult();
        }

        if (strpos($sql, 'FROM `oc_coupon`') !== false) {
            return new FakeQueryResult(isset($this->coupons[3]) ? $this->coupons[3] : array(), array_values($this->coupons));
        }

        if (strpos($sql, 'DELETE FROM `oc_customer`') !== false) {
            unset($this->customers[42]);
            return new FakeQueryResult();
        }

        if (strpos($sql, 'FROM `oc_customer`') !== false) {
            return new FakeQueryResult($this->customers[42]);
        }

        if (strpos($sql, 'FROM `oc_address`') !== false && strpos($sql, 'address_id') !== false && strpos($sql, 'LIMIT 1') !== false) {
            preg_match("/address_id = '([0-9]+)'/", $sql, $match);
            $addressId = isset($match[1]) ? (int)$match[1] : 5;
            return new FakeQueryResult(isset($this->addresses[$addressId]) ? $this->addresses[$addressId] : array());
        }

        if (strpos($sql, 'FROM `oc_address`') !== false) {
            return new FakeQueryResult(array(), array_values($this->addresses));
        }

        if (strpos($sql, 'INSERT INTO `oc_address`') !== false) {
            $this->lastId = 101;
            $this->addresses[101] = array(
                'address_id' => 101,
                'customer_id' => 42,
                'firstname' => strpos($sql, 'Katherine') !== false ? 'Katherine' : 'Ada',
                'lastname' => strpos($sql, 'Johnson') !== false ? 'Johnson' : 'Lovelace',
                'company' => '',
                'address_1' => strpos($sql, '2 Orbit Road') !== false ? '2 Orbit Road' : '1 Loop Street',
                'address_2' => '',
                'city' => 'London',
                'postcode' => '',
                'country_id' => 222,
                'zone_id' => 3513,
                'custom_field' => '',
            );
            return new FakeQueryResult();
        }

        if (strpos($sql, 'UPDATE `oc_customer`') !== false) {
            if (strpos($sql, 'Katherine') !== false) {
                $this->customers[42]['firstname'] = 'Katherine';
            } elseif (strpos($sql, 'Grace') !== false) {
                $this->customers[42]['firstname'] = 'Grace';
            }
            if (strpos($sql, 'kat@example.com') !== false) {
                $this->customers[42]['email'] = 'kat@example.com';
            }
            if (strpos($sql, 'customer_group_id') !== false) {
                $this->customers[42]['customer_group_id'] = 3;
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'UPDATE `oc_address`') !== false) {
            preg_match("/address_id = '([0-9]+)'/", $sql, $match);
            $addressId = isset($match[1]) ? (int)$match[1] : 5;
            if (isset($this->addresses[$addressId])) {
                $this->addresses[$addressId]['city'] = strpos($sql, 'Cambridge') !== false ? 'Cambridge' : 'Oxford';
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'DELETE FROM `oc_address`') !== false) {
            preg_match("/address_id = '([0-9]+)'/", $sql, $match);
            $addressId = isset($match[1]) ? (int)$match[1] : 5;
            unset($this->addresses[$addressId]);
            return new FakeQueryResult();
        }

        return new FakeQueryResult(array(), array());
    }
}

$toolRepository = new FakeToolRepository();
$executor = new \OpenCartMcp\ToolExecutor($toolRepository);
$toolClient = array('client_id' => 77);
$createdCart = $executor->execute('cart.create', array('store_id' => 0, 'currency_code' => 'GBP'), $toolClient, 'req-1');
$cartId = $createdCart['cart_id'];
$executor->execute('cart.add_item', array('cart_id' => $cartId, 'product_id' => 9, 'quantity' => 2), $toolClient, 'req-2');
$executor->execute('cart.apply_coupon', array('cart_id' => $cartId, 'coupon_code' => 'SAVE10'), $toolClient, 'req-3');
$cartAfterCoupon = $executor->execute('cart.get', array('cart_id' => $cartId), $toolClient, 'req-4');
assert_true($cartAfterCoupon['data']['coupon']['code'] === 'SAVE10', 'cart coupon should be stored on MCP cart session');
$executor->execute('cart.remove_item', array('cart_id' => $cartId, 'product_id' => 9), $toolClient, 'req-5');
$cartAfterRemove = $executor->execute('cart.get', array('cart_id' => $cartId), $toolClient, 'req-6');
assert_true(!isset($cartAfterRemove['data']['items']['9']), 'cart.remove_item should remove the requested product');
$shippingQuote = $executor->execute('cart.quote_shipping', array('cart_id' => $cartId), $toolClient, 'req-7');
assert_true($shippingQuote['available'] === true && $shippingQuote['order_created'] === false && $shippingQuote['payment_captured'] === false, 'cart.quote_shipping should return adapter quotes without checkout side effects');
$paymentQuote = $executor->execute('cart.quote_payment', array('cart_id' => $cartId), $toolClient, 'req-7b');
assert_true($paymentQuote['available'] === true && $paymentQuote['payment_captured'] === false, 'cart.quote_payment should return adapter methods without capturing payment');
$checkout = $executor->execute('checkout.validate', array('cart_id' => $cartId), $toolClient, 'req-8');
assert_true($checkout['ready'] === false, 'checkout.validate should not create orders and should report missing readiness');

try {
    $executor->execute('customer.self.update_profile', array('firstname' => 'Grace'), $toolClient, 'req-9');
    fail('customer self-service writes must require signed customer context token');
} catch (\OpenCartMcp\McpException $e) {
    assert_true($e->getMcpCode() === 'CUSTOMER_CONTEXT_REQUIRED', 'missing customer context should be rejected');
}

$updatedProfile = $executor->execute('customer.self.update_profile', array('customer_context_token' => 'customer-token', 'firstname' => 'Grace'), $toolClient, 'req-10');
assert_true($updatedProfile['customer']['firstname'] === 'Grace', 'customer.self.update_profile should update allowed profile fields only');
$addresses = $executor->execute('customer.self.list_addresses', array('customer_context_token' => 'customer-token'), $toolClient, 'req-11');
assert_true(count($addresses['addresses']) === 1, 'customer.self.list_addresses should return the signed customer address book');
$reward = $executor->execute('customer.self.reward_points', array('customer_context_token' => 'customer-token'), $toolClient, 'req-12');
assert_true(array_key_exists('total', $reward), 'customer.self.reward_points should return total reward points');
$adminUpdated = $executor->execute('admin.customer.update', array('customer_id' => 42, 'firstname' => 'Katherine', 'email' => 'kat@example.com', 'reason' => 'support correction', 'dry_run' => false), $toolClient, 'req-12a');
assert_true($adminUpdated['executed'] === true && $adminUpdated['customer']['firstname'] === 'Katherine', 'admin.customer.update should execute allowed customer field changes');
$adminGroup = $executor->execute('admin.customer.update_group', array('customer_id' => 42, 'customer_group_id' => 3, 'reason' => 'pricing tier correction'), $toolClient, 'req-12b');
assert_true($adminGroup['executed'] === true && (int)$adminGroup['customer']['customer_group_id'] === 3, 'admin.customer.update_group should execute customer group changes');
$adminAddresses = $executor->execute('admin.customer.addresses', array('customer_id' => 42), $toolClient, 'req-12c');
assert_true(count($adminAddresses['addresses']) === 1, 'admin.customer.addresses should return addresses for requested customer');
$adminAddress = $executor->execute('admin.customer.add_address', array('customer_id' => 42, 'firstname' => 'Katherine', 'lastname' => 'Johnson', 'address_1' => '2 Orbit Road', 'city' => 'London', 'country_id' => 222, 'zone_id' => 3513, 'reason' => 'customer request'), $toolClient, 'req-12d');
assert_true($adminAddress['executed'] === true && $adminAddress['address_id'] > 0, 'admin.customer.add_address should create a scoped customer address');
$adminAddressUpdate = $executor->execute('admin.customer.update_address', array('customer_id' => 42, 'address_id' => $adminAddress['address_id'], 'city' => 'Cambridge', 'reason' => 'customer request'), $toolClient, 'req-12e');
assert_true($adminAddressUpdate['executed'] === true && $adminAddressUpdate['address']['city'] === 'Cambridge', 'admin.customer.update_address should update a scoped customer address');
$adminAddressDelete = $executor->execute('admin.customer.delete_address', array('customer_id' => 42, 'address_id' => $adminAddress['address_id'], 'reason' => 'customer request'), $toolClient, 'req-12f');
assert_true($adminAddressDelete['executed'] === true && $adminAddressDelete['deleted'] === true, 'admin.customer.delete_address should delete a scoped customer address');
$setInventory = $executor->execute('admin.inventory.set_quantity', array('product_id' => 9, 'quantity' => 15, 'reason' => 'cycle count'), $toolClient, 'req-12g');
assert_true($setInventory['executed'] === true && $setInventory['after']['quantity'] === 15, 'admin.inventory.set_quantity should execute exact stock changes');
$bulkInventory = $executor->execute('admin.inventory.bulk_adjust', array('items' => array(array('product_id' => 9, 'delta' => -2)), 'reason' => 'batch cycle count'), $toolClient, 'req-12h');
assert_true($bulkInventory['executed'] === true && $bulkInventory['results'][0]['after']['quantity'] === 13, 'admin.inventory.bulk_adjust should execute bounded stock adjustments');
$movements = $executor->execute('admin.inventory.get_movements', array('product_id' => 9), $toolClient, 'req-12i');
assert_true(count($movements['movements']) >= 2, 'admin.inventory.get_movements should return recorded MCP stock movements');
$orderStatus = $executor->execute('admin.order.update_status', array('order_id' => 1001, 'order_status_id' => 2, 'comment' => 'Packed', 'notify' => false, 'reason' => 'fulfilment update'), $toolClient, 'req-12j');
assert_true($orderStatus['executed'] === true && $orderStatus['order']['order_status_id'] === 2, 'admin.order.update_status should execute status changes');
$orderNote = $executor->execute('admin.order.add_note', array('order_id' => 1001, 'comment' => 'Internal pick note', 'reason' => 'warehouse note'), $toolClient, 'req-12k');
assert_true($orderNote['executed'] === true && $orderNote['history']['notify'] === 0, 'admin.order.add_note should create non-notifying history');
$orderNotify = $executor->execute('admin.order.notify_customer', array('order_id' => 1001, 'order_status_id' => 2, 'comment' => 'Your order shipped', 'reason' => 'customer update'), $toolClient, 'req-12l');
assert_true($orderNotify['executed'] === true && $orderNotify['history']['notify'] === 1, 'admin.order.notify_customer should create notifying history');
$orderTracking = $executor->execute('admin.order.update_tracking', array('order_id' => 1001, 'tracking' => 'TRACK123', 'reason' => 'shipment label'), $toolClient, 'req-12m');
assert_true($orderTracking['executed'] === true && strpos($orderTracking['history']['comment'], 'TRACK123') !== false, 'admin.order.update_tracking should record tracking metadata');
$orderReturn = $executor->execute('admin.order.create_return', array('order_id' => 1001, 'product_id' => 9, 'quantity' => 1, 'return_reason_id' => 1, 'reason' => 'damaged item'), $toolClient, 'req-12n');
assert_true($orderReturn['executed'] === true && $orderReturn['return_id'] > 0, 'admin.order.create_return should create return request');
$orderResend = $executor->execute('admin.order.resend_invoice', array('order_id' => 1001, 'reason' => 'customer asked'), $toolClient, 'req-12o');
assert_true($orderResend['executed'] === true && $orderResend['history']['notify'] === 1, 'admin.order.resend_invoice should record a notifying resend request');
$couponUpdate = $executor->execute('admin.coupon.update', array('coupon_id' => 3, 'name' => 'Updated Coupon', 'code' => 'SAVE20', 'type' => 'P', 'discount' => 20, 'date_start' => '2026-01-01', 'date_end' => '2026-12-31', 'reason' => 'campaign refresh'), $toolClient, 'req-12p');
assert_true($couponUpdate['executed'] === true && $couponUpdate['coupon']['code'] === 'SAVE20', 'admin.coupon.update should execute allowed coupon field changes');
$couponDelete = $executor->execute('admin.coupon.delete', array('coupon_id' => 3, 'reason' => 'campaign ended'), $toolClient, 'req-12q');
assert_true($couponDelete['executed'] === true && $couponDelete['deleted'] === true, 'admin.coupon.delete should delete the selected coupon');
$reviewApprove = $executor->execute('admin.review.approve', array('review_id' => 7, 'reason' => 'moderation approved'), $toolClient, 'req-12r');
assert_true($reviewApprove['executed'] === true && $reviewApprove['review']['status'] === 1, 'admin.review.approve should approve the selected review');
$reviewUpdate = $executor->execute('admin.review.update', array('review_id' => 7, 'text' => 'Updated review', 'rating' => 5, 'reason' => 'moderation correction'), $toolClient, 'req-12s');
assert_true($reviewUpdate['executed'] === true && $reviewUpdate['review']['rating'] === 5, 'admin.review.update should execute allowed review changes');
$reviewDelete = $executor->execute('admin.review.delete', array('review_id' => 7, 'reason' => 'spam'), $toolClient, 'req-12t');
assert_true($reviewDelete['executed'] === true && $reviewDelete['deleted'] === true, 'admin.review.delete should delete the selected review');
$productCreate = $executor->execute('admin.product.create', array('name' => 'New Product', 'model' => 'NP-1', 'price' => 19.95, 'quantity' => 4, 'reason' => 'catalog addition'), $toolClient, 'req-12u');
assert_true($productCreate['executed'] === true && $productCreate['product_id'] > 0, 'admin.product.create should create product rows');
$productUpdate = $executor->execute('admin.product.update', array('product_id' => 9, 'name' => 'Updated Product', 'price' => 14.5, 'language_id' => 1, 'reason' => 'catalog correction'), $toolClient, 'req-12v');
assert_true($productUpdate['executed'] === true && (float)$productUpdate['product']['price'] === 14.5, 'admin.product.update should update allowed product fields');
$productSpecials = $executor->execute('admin.product.update_specials', array('product_id' => 9, 'specials' => array(array('price' => 9.99)), 'reason' => 'sale'), $toolClient, 'req-12w');
assert_true($productSpecials['executed'] === true && count($productSpecials['specials']) === 1, 'admin.product.update_specials should replace specials');
$productDiscounts = $executor->execute('admin.product.update_discounts', array('product_id' => 9, 'discounts' => array(array('quantity' => 5, 'price' => 11.5)), 'reason' => 'bulk pricing'), $toolClient, 'req-12x');
assert_true($productDiscounts['executed'] === true && $productDiscounts['discounts'][0]['quantity'] === 5, 'admin.product.update_discounts should replace discounts');
$productCategories = $executor->execute('admin.product.assign_categories', array('product_id' => 9, 'category_ids' => array(20, 21), 'reason' => 'taxonomy'), $toolClient, 'req-12y');
assert_true($productCategories['executed'] === true && count($productCategories['category_ids']) === 2, 'admin.product.assign_categories should replace category links');
$productImages = $executor->execute('admin.product.update_images', array('product_id' => 9, 'images' => array('catalog/products/a.png', 'catalog/products/b.webp'), 'reason' => 'image ordering'), $toolClient, 'req-12z');
assert_true($productImages['executed'] === true && $productImages['primary_image'] === 'catalog/products/a.png', 'admin.product.update_images should set product image ordering');
$productAttach = $executor->execute('admin.product.attach_image', array('product_id' => 9, 'image' => 'catalog/products/c.jpg', 'primary' => true, 'reason' => 'hero image'), $toolClient, 'req-12aa');
assert_true($productAttach['executed'] === true && $toolRepository->products[9]['image'] === 'catalog/products/c.jpg', 'admin.product.attach_image should attach existing images');
$categoryCreate = $executor->execute('admin.category.create', array('name' => 'New Category', 'reason' => 'taxonomy'), $toolClient, 'req-12ab');
assert_true($categoryCreate['executed'] === true && $categoryCreate['category_id'] > 0, 'admin.category.create should create category rows');
$categoryUpdate = $executor->execute('admin.category.update', array('category_id' => 20, 'name' => 'Updated Category', 'language_id' => 1, 'reason' => 'taxonomy'), $toolClient, 'req-12ac');
assert_true($categoryUpdate['executed'] === true && $categoryUpdate['category']['category_id'] === 20, 'admin.category.update should update category rows');
$categoryStatus = $executor->execute('admin.category.update_status', array('category_id' => 20, 'status' => 0, 'reason' => 'hide category'), $toolClient, 'req-12ad');
assert_true($categoryStatus['executed'] === true && (int)$categoryStatus['category']['status'] === 0, 'admin.category.update_status should change category status');
$manufacturerCreate = $executor->execute('admin.manufacturer.create', array('name' => 'New Manufacturer', 'reason' => 'brand'), $toolClient, 'req-12ae');
assert_true($manufacturerCreate['executed'] === true && $manufacturerCreate['manufacturer_id'] > 0, 'admin.manufacturer.create should create manufacturer rows');
$manufacturerUpdate = $executor->execute('admin.manufacturer.update', array('manufacturer_id' => 30, 'name' => 'Updated Manufacturer', 'reason' => 'brand'), $toolClient, 'req-12af');
assert_true($manufacturerUpdate['executed'] === true && $manufacturerUpdate['manufacturer']['name'] === 'Updated Manufacturer', 'admin.manufacturer.update should update manufacturer rows');
$informationCreate = $executor->execute('admin.information.create', array('title' => 'New Page', 'description' => 'Body', 'reason' => 'content'), $toolClient, 'req-12ag');
assert_true($informationCreate['executed'] === true && $informationCreate['information_id'] > 0, 'admin.information.create should create information rows');
$informationUpdate = $executor->execute('admin.information.update', array('information_id' => 40, 'title' => 'Updated Page', 'language_id' => 1, 'reason' => 'content'), $toolClient, 'req-12ah');
assert_true($informationUpdate['executed'] === true && $informationUpdate['information']['information_id'] === 40, 'admin.information.update should update information rows');
$customerExport = $executor->execute('admin.customer.export', array('customer_id' => 42, 'reason' => 'subject access'), $toolClient, 'req-12ai');
assert_true($customerExport['exported'] === true && $customerExport['customer']['email'] === 'kat@example.com', 'admin.customer.export should return customer-owned records');
$mediaUpload = $executor->execute('admin.media.upload', array('target_path' => 'catalog/mcp/test.png', 'content_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', 'reason' => 'asset upload'), $toolClient, 'req-12aj');
assert_true($mediaUpload['executed'] === true && $mediaUpload['media']['exists'] === true, 'admin.media.upload should write constrained image files');
$mediaSearch = $executor->execute('admin.media.search', array('query' => 'test.png'), $toolClient, 'req-12ak');
assert_true(count($mediaSearch['items']) === 1, 'admin.media.search should find constrained image files');
$mediaGet = $executor->execute('admin.media.get', array('path' => 'catalog/mcp/test.png'), $toolClient, 'req-12al');
assert_true($mediaGet['media']['exists'] === true, 'admin.media.get should return image metadata');
$mediaDelete = $executor->execute('admin.media.delete', array('path' => 'catalog/mcp/test.png', 'reason' => 'cleanup'), $toolClient, 'req-12am');
assert_true($mediaDelete['executed'] === true && $mediaDelete['deleted'] === true, 'admin.media.delete should delete constrained image files');
$productDelete = $executor->execute('admin.product.delete', array('product_id' => 9, 'reason' => 'retired'), $toolClient, 'req-12an');
assert_true($productDelete['executed'] === true && $productDelete['deleted'] === true, 'admin.product.delete should delete selected product');
$categoryDelete = $executor->execute('admin.category.delete', array('category_id' => 20, 'reason' => 'retired'), $toolClient, 'req-12ao');
assert_true($categoryDelete['executed'] === true && $categoryDelete['deleted'] === true, 'admin.category.delete should delete selected category');
$informationDelete = $executor->execute('admin.information.delete', array('information_id' => 40, 'reason' => 'retired'), $toolClient, 'req-12ap');
assert_true($informationDelete['executed'] === true && $informationDelete['deleted'] === true, 'admin.information.delete should delete selected information page');
$customerDelete = $executor->execute('admin.customer.delete', array('customer_id' => 42, 'reason' => 'privacy request'), $toolClient, 'req-12aq');
assert_true($customerDelete['executed'] === true && $customerDelete['deleted'] === true, 'admin.customer.delete should delete selected customer records');
$categorySearch = $executor->execute('admin.category.search', array('limit' => 5), $toolClient, 'req-13');
assert_true(isset($categorySearch['items']) && $categorySearch['tool'] === 'admin.category.search', 'supplemental admin read tools should execute through safe fallback');
$writeDryRun = $executor->execute('admin.category.create', array('name' => 'New Category', 'reason' => 'test', 'dry_run' => true), $toolClient, 'req-14');
assert_true($writeDryRun['dry_run'] === true && $writeDryRun['executed'] === false, 'supplemental admin write tools should support dry-run without mutating');
$settingPublic = $executor->execute('admin.setting.get_public', array('keys' => array('config_name')), $toolClient, 'req-15');
assert_true(isset($settingPublic['settings']), 'settings aliases should execute safely');
$settingUpdate = $executor->execute('admin.setting.update_basic', array('settings' => array('config_name' => 'New Store'), 'reason' => 'merchant rename'), $toolClient, 'req-15a');
assert_true($settingUpdate['executed'] === true && $settingUpdate['settings']['config_name'] === 'New Store', 'admin.setting.update_basic should execute non-sensitive setting writes');
try {
    $executor->execute('admin.setting.update', array('settings' => array('payment_secret' => 'x'), 'reason' => 'unsafe'), $toolClient, 'req-15b');
    fail('admin.setting.update should reject sensitive setting keys');
} catch (\OpenCartMcp\McpException $e) {
    assert_true($e->getMcpCode() === 'INVALID_INPUT', 'sensitive setting writes should be rejected');
}
$diagnosticAdmin = $executor->execute('admin.diagnostic.status', array(), $toolClient, 'req-16');
assert_true(isset($diagnosticAdmin['extension']) && $diagnosticAdmin['streaming'] === false, 'admin diagnostic alias should return non-sensitive diagnostic status');

$protocol = new \OpenCartMcp\ProtocolServer(new FakeRepository());
$missingAuth = $protocol->handle('{"jsonrpc":"2.0","id":1,"method":"initialize"}', array('REMOTE_ADDR' => '127.0.0.1'), array(), array());
assert_true(($missingAuth['error']['data']['code'] ?? '') === 'AUTHENTICATION_FAILED', 'initialize should require bearer auth');

$queryToken = $protocol->handle('{"jsonrpc":"2.0","id":1,"method":"initialize"}', array('REMOTE_ADDR' => '127.0.0.1'), array('Authorization' => 'Bearer valid'), array('token' => 'x'));
assert_true(($queryToken['error']['data']['code'] ?? '') === 'TOKEN_IN_QUERY_REJECTED', 'query string token must be rejected');

$listed = $protocol->handle('{"jsonrpc":"2.0","id":2,"method":"tools/list"}', array('REMOTE_ADDR' => '127.0.0.1'), array('Authorization' => 'Bearer valid'), array());
$listedNames = array_map(function ($tool) { return $tool['name']; }, $listed['result']['tools']);
assert_true(in_array('catalog.product.search', $listedNames, true), 'tools/list should include scoped catalog tools');
assert_true(!in_array('admin.product.search', $listedNames, true), 'tools/list should filter admin tools');

$resources = $protocol->handle('{"jsonrpc":"2.0","id":3,"method":"resources/list"}', array('REMOTE_ADDR' => '127.0.0.1'), array('Authorization' => 'Bearer valid'), array());
$resourceUris = array_map(function ($resource) { return $resource['uri']; }, $resources['result']['resources']);
assert_true(in_array('opencart://store/config', $resourceUris, true), 'resources/list should expose scoped store config resource');
assert_true(!in_array('opencart://admin/schema/customer', $resourceUris, true), 'resources/list should filter unscoped admin resources');

$repositorySource = file_get_contents($root . '/src/shared/system/library/mcp/repository.php');
assert_true(strpos($repositorySource, 'function auditCsvRows') !== false, 'repository should expose audit CSV rows');
assert_true(strpos($repositorySource, 'function markAuditReviewed') !== false, 'repository should support reviewed audit notes');
assert_true(strpos($repositorySource, 'alert.high_risk_tool') !== false, 'repository should create high-risk alert audit rows');
assert_true(strpos($repositorySource, 'function sendAuditAlert') !== false, 'repository should send configured audit alert emails');
assert_true(strpos($repositorySource, 'config_email') !== false, 'audit alert email should fall back to configured store email');

$oc3Controller = file_get_contents($root . '/src/opencart3/upload/admin/controller/extension/module/mcp.php');
$oc4Controller = file_get_contents($root . '/src/opencart4/admin/controller/module/mcp.php');
foreach (array($oc3Controller, $oc4Controller) as $controllerSource) {
    assert_true(strpos($controllerSource, 'function exportAudit') !== false, 'admin controller should expose audit CSV export');
    assert_true(strpos($controllerSource, 'function reviewAudit') !== false, 'admin controller should expose audit review action');
    assert_true(strpos($controllerSource, 'auditFilters') !== false, 'admin controller should support audit filters');
    assert_true(strpos($controllerSource, 'module_mcp_alert_email') !== false, 'admin controller should persist audit alert email');
}

$oc3View = file_get_contents($root . '/src/opencart3/upload/admin/view/template/extension/module/mcp.twig');
$oc4View = file_get_contents($root . '/src/opencart4/admin/view/template/module/mcp.twig');
foreach (array($oc3View, $oc4View) as $viewSource) {
    assert_true(strpos($viewSource, 'Export CSV') !== false, 'admin view should include audit CSV export');
    assert_true(strpos($viewSource, 'Mark reviewed') !== false, 'admin view should include audit review control');
    assert_true(strpos($viewSource, 'audit_detail') !== false, 'admin view should link audit details');
    assert_true(strpos($viewSource, 'module_mcp_alerts_enabled') !== false, 'admin view should expose alert toggle');
    assert_true(strpos($viewSource, 'module_mcp_alert_email') !== false, 'admin view should expose alert email');
    assert_true(strpos($viewSource, 'allowed_tools[]') !== false, 'admin view should expose per-tool permissions');
    assert_true(strpos($viewSource, 'high_risk_ack') !== false, 'admin view should expose high-risk acknowledgement');
}

$readme = file_get_contents($root . '/README.md');
assert_true(strpos($readme, 'Install On OpenCart 3.x') !== false, 'README should include OpenCart 3 installation steps');
assert_true(strpos($readme, 'Install On OpenCart 4.x') !== false, 'README should include OpenCart 4 installation steps');
assert_true(strpos($readme, '## Development') === false, 'README should not expose internal development section');
assert_true(strpos($readme, '## GitHub Actions') === false, 'README should not expose internal GitHub Actions section');
assert_true(strpos($readme, '## Packages') === false, 'README should not expose internal packages section');

echo "OK: lint and unit checks passed" . PHP_EOL;
