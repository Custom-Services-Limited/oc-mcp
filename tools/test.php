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

$root = dirname(__DIR__);
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
    public $movements = array();
    public $orders = array();
    public $orderHistories = array();
    public $returns = array();
    public $coupons = array();
    public $settings = array();
    public $queries = array();
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
    }

    public function db() { return null; }
    public function prefix() { return 'oc_'; }
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

        if (strpos($sql, 'UPDATE `oc_product`') !== false) {
            preg_match("/SET quantity = '(-?[0-9]+)'/", $sql, $match);
            if (isset($match[1])) {
                $this->products[9]['quantity'] = (int)$match[1];
            }
            return new FakeQueryResult();
        }

        if (strpos($sql, 'FROM `oc_product`') !== false) {
            return new FakeQueryResult($this->products[9], array($this->products[9]));
        }

        if (strpos($sql, 'FROM `oc_product` p') !== false) {
            return new FakeQueryResult($this->products[9]);
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
assert_true($shippingQuote['available'] === false && count($shippingQuote['messages']) > 0, 'shipping quote should fail safely when checkout adapters are unavailable');
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
