<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */
namespace OpenCartMcp;

class ToolExecutor {
    private $repository;
    private $db;
    private $prefix;

    public function __construct($repository) {
        $this->repository = $repository;
        $this->db = $repository->db();
        $this->prefix = $repository->prefix();
    }

    public function execute($toolName, $arguments, $client, $requestId) {
        switch ($toolName) {
            case 'catalog.store.get': return $this->storeGet();
            case 'catalog.category.tree': return $this->categoryTree($arguments);
            case 'catalog.product.search': return $this->catalogProductSearch($arguments);
            case 'catalog.product.get': return $this->catalogProductGet($arguments);
            case 'catalog.product.availability': return $this->productAvailability($arguments);
            case 'catalog.product.price': return $this->productPrice($arguments);
            case 'catalog.product.related': return $this->productRelated($arguments);
            case 'catalog.product.compare': return $this->productCompare($arguments);
            case 'catalog.manufacturer.list': return $this->manufacturerList($arguments);
            case 'catalog.manufacturer.get': return $this->manufacturerGet($arguments);
            case 'catalog.review.list': return $this->reviewList($arguments);
            case 'catalog.information.list': return $this->informationList($arguments);
            case 'catalog.information.get': return $this->informationGet($arguments);
            case 'catalog.filter.options': return $this->filterOptions($arguments);
            case 'catalog.search.suggest': return $this->searchSuggest($arguments);
            case 'cart.create': return $this->cartCreate($arguments, $client);
            case 'cart.get': return $this->cartGet($arguments, $client);
            case 'cart.add_item': return $this->cartAddItem($arguments, $client);
            case 'cart.update_item': return $this->cartUpdateItem($arguments, $client);
            case 'cart.remove_item': return $this->cartRemoveItem($arguments, $client);
            case 'cart.clear': return $this->cartClear($arguments, $client);
            case 'cart.apply_coupon': return $this->cartApplyCoupon($arguments, $client);
            case 'cart.remove_coupon': return $this->cartRemoveCoupon($arguments, $client);
            case 'cart.apply_voucher': return $this->cartApplyVoucher($arguments, $client);
            case 'cart.apply_reward': return $this->cartApplyReward($arguments, $client);
            case 'cart.quote_shipping': return $this->cartQuoteShipping($arguments, $client);
            case 'cart.select_shipping': return $this->cartSelectShipping($arguments, $client);
            case 'cart.quote_payment': return $this->cartQuotePayment($arguments, $client);
            case 'cart.select_payment': return $this->cartSelectPayment($arguments, $client);
            case 'cart.estimate_totals': return $this->cartEstimateTotals($arguments, $client);
            case 'checkout.validate': return $this->checkoutValidate($arguments, $client);
            case 'customer.self.get_profile': return $this->customerSelfProfile($arguments);
            case 'customer.self.update_profile': return $this->customerSelfUpdateProfile($arguments);
            case 'customer.self.list_addresses': return $this->customerSelfListAddresses($arguments);
            case 'customer.self.add_address': return $this->customerSelfAddAddress($arguments);
            case 'customer.self.update_address': return $this->customerSelfUpdateAddress($arguments);
            case 'customer.self.delete_address': return $this->customerSelfDeleteAddress($arguments);
            case 'customer.self.orders': return $this->customerSelfOrders($arguments);
            case 'customer.self.order_get': return $this->customerSelfOrderGet($arguments);
            case 'customer.self.downloads': return $this->customerSelfDownloads($arguments);
            case 'customer.self.returns': return $this->customerSelfReturns($arguments);
            case 'customer.self.create_return': return $this->customerSelfCreateReturn($arguments);
            case 'customer.self.reward_points': return $this->customerSelfRewardPoints($arguments);
            case 'admin.product.search': return $this->adminProductSearch($arguments);
            case 'admin.product.get': return $this->adminProductGet($arguments);
            case 'admin.product.diagnose_visibility': return $this->adminProductDiagnoseVisibility($arguments);
            case 'admin.product.list_missing_data': return $this->adminProductMissingData($arguments);
            case 'admin.product.update_status': return $this->adminProductUpdateStatus($arguments);
            case 'admin.product.update_price': return $this->adminProductUpdatePrice($arguments);
            case 'admin.product.update_seo': return $this->adminProductUpdateSeo($arguments);
            case 'admin.product.update_description': return $this->adminProductUpdateDescription($arguments);
            case 'admin.inventory.get': return $this->inventoryGet($arguments);
            case 'admin.inventory.search_low_stock': return $this->inventoryLowStock($arguments);
            case 'admin.inventory.adjust': return $this->inventoryAdjust($arguments, $client, $requestId);
            case 'admin.inventory.set_quantity': return $this->inventorySetQuantity($arguments, $client, $requestId);
            case 'admin.inventory.bulk_adjust': return $this->inventoryBulkAdjust($arguments, $client, $requestId);
            case 'admin.inventory.get_movements': return $this->inventoryMovements($arguments);
            case 'admin.order.search': return $this->orderSearch($arguments);
            case 'admin.order.get': return $this->orderGet($arguments);
            case 'admin.order.add_history': return $this->orderAddHistory($arguments);
            case 'admin.order.update_status': return $this->orderUpdateStatus($arguments);
            case 'admin.order.add_note': return $this->orderAddNote($arguments);
            case 'admin.order.notify_customer': return $this->orderNotifyCustomer($arguments);
            case 'admin.order.create_return': return $this->orderCreateReturn($arguments);
            case 'admin.order.update_tracking': return $this->orderUpdateTracking($arguments);
            case 'admin.order.resend_invoice': return $this->orderResendInvoice($arguments);
            case 'admin.customer.search': return $this->customerSearch($arguments);
            case 'admin.customer.get': return $this->customerGet($arguments);
            case 'admin.customer.orders': return $this->customerOrders($arguments);
            case 'admin.customer.update_status': return $this->customerUpdateStatus($arguments);
            case 'admin.customer.update': return $this->customerUpdate($arguments);
            case 'admin.customer.update_group': return $this->customerUpdateGroup($arguments);
            case 'admin.customer.addresses': return $this->customerAddresses($arguments);
            case 'admin.customer.add_address': return $this->customerAddAddress($arguments);
            case 'admin.customer.update_address': return $this->customerUpdateAddress($arguments);
            case 'admin.customer.delete_address': return $this->customerDeleteAddress($arguments);
            case 'admin.coupon.search': return $this->couponSearch($arguments);
            case 'admin.coupon.get': return $this->couponGet($arguments);
            case 'admin.coupon.create': return $this->couponCreate($arguments);
            case 'admin.coupon.disable': return $this->couponDisable($arguments);
            case 'admin.coupon.update': return $this->couponUpdate($arguments);
            case 'admin.coupon.delete': return $this->couponDelete($arguments);
            case 'admin.report.sales_summary': return $this->reportSalesSummary($arguments);
            case 'admin.report.orders_by_status': return $this->reportOrdersByStatus($arguments);
            case 'admin.report.low_stock_value': return $this->reportLowStockValue($arguments);
            case 'admin.setting.get': return $this->settingGet($arguments);
            case 'diagnostic.status': return $this->diagnosticStatus();
            default:
                return $this->supplementalPrdTool($toolName, $arguments, $client, $requestId);
        }
    }

    private function supplementalPrdTool($toolName, $args, $client, $requestId) {
        $registry = new ToolRegistry();
        $tool = $registry->find($toolName);
        if (!$tool) {
            throw new McpException('TOOL_NOT_FOUND', 'Tool not found.');
        }

        if ($toolName === 'admin.setting.get_public' || $toolName === 'admin.setting.get_effective') {
            return $this->settingGet($args);
        }

        if ($toolName === 'admin.diagnostic.status') {
            return $this->diagnosticStatus();
        }

        if (strpos($toolName, 'admin.diagnostic.') === 0) {
            return array(
                'tool' => $toolName,
                'extension' => 'OpenCart MCP Server',
                'available' => true,
                'messages' => array('Diagnostic data is limited to non-sensitive extension state.'),
                'status' => $this->diagnosticStatus(),
            );
        }

        if (!empty($tool['write'])) {
            return $this->supplementalDryRunWrite($toolName, $args, $tool);
        }

        return $this->supplementalBoundedRead($toolName, $args);
    }

    private function supplementalDryRunWrite($toolName, $args, $tool) {
        if (empty($args['reason'])) {
            throw new McpException('INVALID_INPUT', 'A reason is required for controlled writes.');
        }

        return array(
            'tool' => $toolName,
            'dry_run' => !empty($args['dry_run']),
            'executed' => false,
            'requires_dedicated_integration' => true,
            'reason' => $args['reason'],
            'risk_tier' => $tool['risk_tier'],
            'message' => 'This PRD tool is registered and permissioned, but live mutation requires a dedicated OpenCart model integration. No changes were made.',
        );
    }

    private function supplementalBoundedRead($toolName, $args) {
        $limit = $this->limit($args['limit'] ?? 25);
        $table = $this->supplementalReadTable($toolName);

        if ($table === null) {
            return array(
                'tool' => $toolName,
                'items' => array(),
                'messages' => array('This PRD read is available but has no portable OpenCart table fallback.'),
                'limit' => $limit,
            );
        }

        $where = array();
        foreach (array('product_id', 'category_id', 'manufacturer_id', 'order_id', 'customer_id', 'coupon_id', 'address_id', 'status') as $field) {
            if (array_key_exists($field, $args)) {
                $where[] = "`" . $this->escapeIdentifier($field) . "` = '" . (int)$args[$field] . "'";
            }
        }
        $sql = "SELECT * FROM `" . $this->prefix . $table . "`";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " LIMIT " . $limit;

        try {
            $rows = $this->repository->query($sql)->rows;
        } catch (\Exception $e) {
            $rows = array();
        }

        return array('tool' => $toolName, 'items' => $rows, 'limit' => $limit);
    }

    private function supplementalReadTable($toolName) {
        $tables = array(
            'catalog.category.get' => 'category',
            'catalog.product.options' => 'product_option',
            'admin.category.search' => 'category',
            'admin.category.get' => 'category',
            'admin.attribute.list' => 'attribute',
            'admin.option.list' => 'option',
            'admin.manufacturer.list' => 'manufacturer',
            'admin.download.list' => 'download',
            'admin.review.search' => 'review',
            'admin.information.search' => 'information',
            'admin.inventory.get_movements' => 'mcp_stock_movement',
            'admin.order.timeline' => 'order_history',
            'admin.order.products' => 'order_product',
            'admin.order.totals' => 'order_total',
            'admin.order.customer_summary' => 'order',
            'admin.order.find_payment_failures' => 'order',
            'admin.order.find_fulfilment_exceptions' => 'order',
            'admin.customer.addresses' => 'address',
            'admin.voucher.search' => 'voucher',
            'admin.marketing.campaign_search' => 'marketing',
            'admin.marketing.tracking_get' => 'marketing',
            'admin.report.best_sellers' => 'order_product',
            'admin.report.customers' => 'customer',
            'admin.report.coupons' => 'coupon_history',
            'admin.report.returns' => 'return',
            'admin.report.tax' => 'order_total',
            'admin.report.low_stock' => 'product',
        );
        return array_key_exists($toolName, $tables) ? $tables[$toolName] : null;
    }

    private function storeGet() {
        return array(
            'name' => $this->repository->config('config_name', ''),
            'url' => $this->repository->config('config_url', defined('HTTP_SERVER') ? HTTP_SERVER : ''),
            'language' => $this->repository->config('config_language', ''),
            'language_id' => (int)$this->repository->config('config_language_id', 1),
            'currency' => $this->repository->config('config_currency', ''),
            'tax_display' => $this->repository->config('config_tax', ''),
            'stock_checkout' => $this->repository->config('config_stock_checkout', ''),
            'server_version' => defined('VERSION') ? VERSION : '',
        );
    }

    private function categoryTree($args) {
        $storeId = (int)($args['store_id'] ?? 0);
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $parentId = (int)($args['parent_id'] ?? 0);
        $limit = $this->limit($args['limit'] ?? 100);

        $sql = "SELECT c.category_id, c.parent_id, cd.name
            FROM `" . $this->prefix . "category` c
            JOIN `" . $this->prefix . "category_description` cd ON c.category_id = cd.category_id
            JOIN `" . $this->prefix . "category_to_store` c2s ON c.category_id = c2s.category_id
            WHERE c.status = 1
            AND c.parent_id = '" . $parentId . "'
            AND cd.language_id = '" . $languageId . "'
            AND c2s.store_id = '" . $storeId . "'
            ORDER BY c.sort_order, cd.name
            LIMIT " . $limit;

        return array('categories' => $this->repository->query($sql)->rows);
    }

    private function catalogProductSearch($args) {
        $storeId = (int)($args['store_id'] ?? 0);
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $where = $this->visibleProductWhere($storeId, $languageId);

        if (!empty($args['query'])) {
            $query = $this->escapeLike($args['query']);
            $where[] = "(pd.name LIKE '%" . $query . "%' OR p.model LIKE '%" . $query . "%' OR p.sku LIKE '%" . $query . "%')";
        }

        if (!empty($args['category_id'])) {
            $where[] = "p.product_id IN (SELECT product_id FROM `" . $this->prefix . "product_to_category` WHERE category_id = '" . (int)$args['category_id'] . "')";
        }

        $sql = "SELECT p.product_id, pd.name, p.model, p.sku, p.quantity, p.price, p.image, p.status
            FROM `" . $this->prefix . "product` p
            JOIN `" . $this->prefix . "product_description` pd ON p.product_id = pd.product_id
            JOIN `" . $this->prefix . "product_to_store` p2s ON p.product_id = p2s.product_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY pd.name
            LIMIT " . (int)$start . ", " . $limit;

        return array('products' => $this->repository->query($sql)->rows, 'page' => $page, 'limit' => $limit);
    }

    private function catalogProductGet($args) {
        $storeId = (int)($args['store_id'] ?? 0);
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $product = $this->visibleProduct((int)$args['product_id'], $storeId, $languageId);
        if (!$product) {
            throw new McpException('ENTITY_NOT_FOUND', 'Product not found or not visible.');
        }

        return array('product' => $product);
    }

    private function productAvailability($args) {
        $storeId = (int)($args['store_id'] ?? 0);
        $row = $this->repository->query("SELECT product_id, quantity, stock_status_id, subtract, minimum, status, date_available
            FROM `" . $this->prefix . "product` p
            JOIN `" . $this->prefix . "product_to_store` p2s ON p.product_id = p2s.product_id
            WHERE p.product_id = '" . (int)$args['product_id'] . "'
            AND p2s.store_id = '" . $storeId . "'
            LIMIT 1")->row;
        if (!$row || (int)$row['status'] !== 1 || strtotime($row['date_available']) > time()) {
            throw new McpException('ENTITY_NOT_FOUND', 'Product not found or not visible.');
        }

        return array('availability' => array(
            'product_id' => (int)$row['product_id'],
            'quantity' => (int)$row['quantity'],
            'stock_status_id' => (int)$row['stock_status_id'],
            'subtract' => (int)$row['subtract'],
            'minimum' => (int)$row['minimum'],
            'purchasable' => (int)$row['quantity'] >= (int)$row['minimum'] || (string)$this->repository->config('config_stock_checkout', '0') === '1',
        ));
    }

    private function productPrice($args) {
        $productId = (int)$args['product_id'];
        $storeId = (int)($args['store_id'] ?? 0);
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $groupId = (int)($args['customer_group_id'] ?? $this->repository->config('config_customer_group_id', 1));
        $product = $this->visibleProduct($productId, $storeId, $languageId);
        if (!$product) {
            throw new McpException('ENTITY_NOT_FOUND', 'Product not found or not visible.');
        }

        $special = $this->repository->query("SELECT price FROM `" . $this->prefix . "product_special`
            WHERE product_id = '" . $productId . "'
            AND customer_group_id = '" . $groupId . "'
            AND (date_start = '0000-00-00' OR date_start <= CURDATE())
            AND (date_end = '0000-00-00' OR date_end >= CURDATE())
            ORDER BY priority ASC, price ASC LIMIT 1")->row;

        return array('price' => array(
            'product_id' => $productId,
            'base' => (float)$product['price'],
            'special' => $special ? (float)$special['price'] : null,
            'currency' => $this->repository->config('config_currency', ''),
        ));
    }

    private function productRelated($args) {
        $storeId = (int)($args['store_id'] ?? 0);
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $limit = $this->limit($args['limit'] ?? 10);
        $where = $this->visibleProductWhere($storeId, $languageId);
        $where[] = "p.product_id IN (SELECT related_id FROM `" . $this->prefix . "product_related` WHERE product_id = '" . (int)$args['product_id'] . "')";
        $rows = $this->repository->query("SELECT p.product_id, pd.name, p.model, p.price
            FROM `" . $this->prefix . "product` p
            JOIN `" . $this->prefix . "product_description` pd ON p.product_id = pd.product_id
            JOIN `" . $this->prefix . "product_to_store` p2s ON p.product_id = p2s.product_id
            WHERE " . implode(' AND ', $where) . "
            LIMIT " . $limit)->rows;
        return array('products' => $rows);
    }

    private function productCompare($args) {
        $ids = array_slice(array_map('intval', (array)$args['product_ids']), 0, 10);
        if (!$ids) {
            throw new McpException('INVALID_INPUT', 'At least one product_id is required.');
        }
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $idSql = implode(',', $ids);
        $products = $this->repository->query("SELECT p.product_id, pd.name, p.model, p.sku, p.price, p.quantity
            FROM `" . $this->prefix . "product` p
            JOIN `" . $this->prefix . "product_description` pd ON p.product_id = pd.product_id
            WHERE p.product_id IN (" . $idSql . ")
            AND p.status = 1
            AND pd.language_id = '" . $languageId . "'")->rows;
        $attributes = $this->repository->query("SELECT pa.product_id, ad.name, pa.text
            FROM `" . $this->prefix . "product_attribute` pa
            JOIN `" . $this->prefix . "attribute_description` ad ON pa.attribute_id = ad.attribute_id
            WHERE pa.product_id IN (" . $idSql . ")
            AND pa.language_id = '" . $languageId . "'
            AND ad.language_id = '" . $languageId . "'
            ORDER BY ad.name")->rows;

        return array('products' => $products, 'attributes' => $attributes);
    }

    private function manufacturerList($args) {
        $limit = $this->limit($args['limit'] ?? 100);
        $rows = $this->repository->query("SELECT manufacturer_id, name, image FROM `" . $this->prefix . "manufacturer` ORDER BY name LIMIT " . $limit)->rows;
        return array('manufacturers' => $rows);
    }

    private function manufacturerGet($args) {
        $manufacturerId = (int)$args['manufacturer_id'];
        $storeId = (int)($args['store_id'] ?? 0);
        $limit = $this->limit($args['limit'] ?? 50);
        $manufacturer = $this->repository->query("SELECT manufacturer_id, name, image FROM `" . $this->prefix . "manufacturer` WHERE manufacturer_id = '" . $manufacturerId . "' LIMIT 1")->row;
        if (!$manufacturer) {
            throw new McpException('ENTITY_NOT_FOUND', 'Manufacturer not found.');
        }
        $products = $this->repository->query("SELECT p.product_id
            FROM `" . $this->prefix . "product` p
            JOIN `" . $this->prefix . "product_to_store` p2s ON p.product_id = p2s.product_id
            WHERE p.manufacturer_id = '" . $manufacturerId . "'
            AND p.status = 1
            AND p2s.store_id = '" . $storeId . "'
            LIMIT " . $limit)->rows;
        return array('manufacturer' => $manufacturer, 'products' => $products);
    }

    private function reviewList($args) {
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $rows = $this->repository->query("SELECT review_id, product_id, author, text, rating, date_added
            FROM `" . $this->prefix . "review`
            WHERE product_id = '" . (int)$args['product_id'] . "'
            AND status = 1
            ORDER BY date_added DESC
            LIMIT " . (int)$start . ", " . $limit)->rows;
        return array('reviews' => $rows, 'page' => $page, 'limit' => $limit);
    }

    private function informationList($args) {
        $storeId = (int)($args['store_id'] ?? 0);
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $limit = $this->limit($args['limit'] ?? 100);
        $rows = $this->repository->query("SELECT i.information_id, id.title
            FROM `" . $this->prefix . "information` i
            JOIN `" . $this->prefix . "information_description` id ON i.information_id = id.information_id
            JOIN `" . $this->prefix . "information_to_store` i2s ON i.information_id = i2s.information_id
            WHERE i.status = 1
            AND id.language_id = '" . $languageId . "'
            AND i2s.store_id = '" . $storeId . "'
            ORDER BY i.sort_order, id.title
            LIMIT " . $limit)->rows;
        return array('information' => $rows);
    }

    private function informationGet($args) {
        $storeId = (int)($args['store_id'] ?? 0);
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $row = $this->repository->query("SELECT i.information_id, id.title, id.description, id.meta_title, id.meta_description
            FROM `" . $this->prefix . "information` i
            JOIN `" . $this->prefix . "information_description` id ON i.information_id = id.information_id
            JOIN `" . $this->prefix . "information_to_store` i2s ON i.information_id = i2s.information_id
            WHERE i.information_id = '" . (int)$args['information_id'] . "'
            AND i.status = 1
            AND id.language_id = '" . $languageId . "'
            AND i2s.store_id = '" . $storeId . "'
            LIMIT 1")->row;
        if (!$row) {
            throw new McpException('ENTITY_NOT_FOUND', 'Information page not found or not visible.');
        }
        return array('information' => $row);
    }

    private function filterOptions($args) {
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $limit = $this->limit($args['limit'] ?? 100);
        $rows = $this->repository->query("SELECT f.filter_id, fd.name, fgd.name AS group_name
            FROM `" . $this->prefix . "category_filter` cf
            JOIN `" . $this->prefix . "filter` f ON cf.filter_id = f.filter_id
            JOIN `" . $this->prefix . "filter_description` fd ON f.filter_id = fd.filter_id
            JOIN `" . $this->prefix . "filter_group_description` fgd ON f.filter_group_id = fgd.filter_group_id
            WHERE cf.category_id = '" . (int)$args['category_id'] . "'
            AND fd.language_id = '" . $languageId . "'
            AND fgd.language_id = '" . $languageId . "'
            ORDER BY f.sort_order, fd.name
            LIMIT " . $limit)->rows;
        return array('filters' => $rows);
    }

    private function searchSuggest($args) {
        $args['limit'] = min(20, (int)($args['limit'] ?? 10));
        $result = $this->catalogProductSearch($args);
        $suggestions = array();
        foreach ($result['products'] as $product) {
            $suggestions[] = array('product_id' => (int)$product['product_id'], 'name' => $product['name']);
        }
        return array('suggestions' => $suggestions);
    }

    private function cartCreate($args, $client) {
        $data = array('items' => array(), 'coupon' => null);
        $cartId = $this->repository->createCart($client['client_id'], (int)($args['store_id'] ?? 0), (string)($args['currency_code'] ?? $this->repository->config('config_currency', '')), $data);
        return array('cart_id' => $cartId, 'items' => array());
    }

    private function cartGet($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        return array('cart_id' => $cart['cart_id'], 'store_id' => (int)$cart['store_id'], 'currency_code' => $cart['currency_code'], 'data' => $cart['data']);
    }

    private function cartAddItem($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $product = $this->cartProduct((int)$args['product_id'], (int)$cart['store_id']);
        $quantity = (int)$args['quantity'];
        $data = $cart['data'];
        $existingQuantity = (int)($data['items'][(string)$product['product_id']]['quantity'] ?? 0);
        $newQuantity = $quantity + $existingQuantity;
        if ((int)$product['quantity'] < $newQuantity && (string)$this->repository->config('config_stock_checkout', '0') !== '1') {
            throw new McpException('ENTITY_CONFLICT', 'Requested quantity exceeds available stock.');
        }
        $data['items'][(string)$product['product_id']] = array(
            'product_id' => (int)$product['product_id'],
            'name' => $product['name'],
            'quantity' => $newQuantity,
            'unit_price' => (float)$product['price'],
        );
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'item' => $data['items'][(string)$product['product_id']]);
    }

    private function cartUpdateItem($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $data = $cart['data'];
        $key = (string)(int)$args['product_id'];
        if (!isset($data['items'][$key])) {
            throw new McpException('ENTITY_NOT_FOUND', 'Cart item not found.');
        }
        if ((int)$args['quantity'] === 0) {
            unset($data['items'][$key]);
        } else {
            $data['items'][$key]['quantity'] = (int)$args['quantity'];
        }
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'data' => $data);
    }

    private function cartRemoveItem($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $data = $cart['data'];
        $key = (string)(int)$args['product_id'];
        if (!isset($data['items'][$key])) {
            throw new McpException('ENTITY_NOT_FOUND', 'Cart item not found.');
        }
        unset($data['items'][$key]);
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'data' => $data);
    }

    private function cartClear($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $data = array('items' => array(), 'coupon' => null);
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'data' => $data);
    }

    private function cartApplyCoupon($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $coupon = $this->couponByCode($args['coupon_code']);
        $data = $cart['data'];
        $data['coupon'] = array('coupon_id' => (int)$coupon['coupon_id'], 'code' => $coupon['code']);
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'coupon' => $data['coupon']);
    }

    private function cartRemoveCoupon($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $data = $cart['data'];
        $data['coupon'] = null;
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'data' => $data);
    }

    private function cartApplyVoucher($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $data = $cart['data'];
        $data['voucher'] = array('code' => trim((string)$args['voucher_code']));
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'voucher' => $data['voucher'], 'payment_captured' => false);
    }

    private function cartApplyReward($args, $client) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $cart = $this->loadCart($args['cart_id'], $client);
        $points = (int)$args['points'];
        $available = $this->customerRewardTotal($customerId);
        if ($points > $available) {
            throw new McpException('ENTITY_CONFLICT', 'Requested reward points exceed available balance.');
        }
        $data = $cart['data'];
        $data['reward'] = array('customer_id' => $customerId, 'points' => $points);
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'reward' => $data['reward']);
    }

    private function cartQuoteShipping($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        return array(
            'cart_id' => $cart['cart_id'],
            'available' => false,
            'quotes' => array(),
            'messages' => array('Live OpenCart shipping quote adapters are not available through this MCP endpoint yet.'),
            'order_created' => false,
            'payment_captured' => false,
        );
    }

    private function cartSelectShipping($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $data = $cart['data'];
        $data['shipping_method'] = trim((string)$args['shipping_method']);
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'shipping_method' => $data['shipping_method'], 'order_created' => false);
    }

    private function cartQuotePayment($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        return array(
            'cart_id' => $cart['cart_id'],
            'available' => false,
            'methods' => array(),
            'messages' => array('Live OpenCart payment method adapters are not available through this MCP endpoint yet.'),
            'order_created' => false,
            'payment_captured' => false,
        );
    }

    private function cartSelectPayment($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $data = $cart['data'];
        $data['payment_method'] = trim((string)$args['payment_method']);
        $this->repository->saveCart($cart['cart_id'], $client['client_id'], $data);
        return array('cart_id' => $cart['cart_id'], 'payment_method' => $data['payment_method'], 'payment_captured' => false);
    }

    private function cartEstimateTotals($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $subtotal = 0.0;
        foreach ($cart['data']['items'] as $item) {
            $subtotal += (float)$item['unit_price'] * (int)$item['quantity'];
        }
        return array(
            'cart_id' => $cart['cart_id'],
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'currency_code' => $cart['currency_code'],
            'order_created' => false,
            'payment_captured' => false,
        );
    }

    private function checkoutValidate($args, $client) {
        $cart = $this->loadCart($args['cart_id'], $client);
        $errors = array();
        if (empty($cart['data']['items'])) {
            $errors[] = 'Cart has no items.';
        }
        if (empty($cart['data']['shipping_method'])) {
            $errors[] = 'Shipping method has not been selected.';
        }
        if (empty($cart['data']['payment_method'])) {
            $errors[] = 'Payment method has not been selected.';
        }
        return array(
            'cart_id' => $cart['cart_id'],
            'ready' => count($errors) === 0,
            'errors' => $errors,
            'order_created' => false,
            'payment_captured' => false,
        );
    }

    private function customerSelfProfile($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $row = $this->repository->query("SELECT customer_id, customer_group_id, firstname, lastname, email, telephone, status, date_added
            FROM `" . $this->prefix . "customer` WHERE customer_id = '" . $customerId . "' AND status = 1 LIMIT 1")->row;
        if (!$row) {
            throw new McpException('ENTITY_NOT_FOUND', 'Customer not found.');
        }
        return array('customer' => $row);
    }

    private function customerSelfUpdateProfile($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $fields = array();
        foreach (array('firstname', 'lastname', 'email', 'telephone') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[] = "`" . $field . "` = '" . $this->escape($args[$field]) . "'";
            }
        }
        if ($fields) {
            $this->repository->query("UPDATE `" . $this->prefix . "customer` SET " . implode(', ', $fields) . " WHERE customer_id = '" . $customerId . "' LIMIT 1");
        }
        return $this->customerSelfProfile(array('customer_context_token' => $args['customer_context_token']));
    }

    private function customerSelfListAddresses($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $rows = $this->repository->query("SELECT address_id, firstname, lastname, company, address_1, address_2, city, postcode, country_id, zone_id, custom_field
            FROM `" . $this->prefix . "address`
            WHERE customer_id = '" . $customerId . "'
            ORDER BY address_id ASC")->rows;
        return array('addresses' => $rows);
    }

    private function customerSelfAddAddress($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $this->repository->query("INSERT INTO `" . $this->prefix . "address` SET
            customer_id = '" . $customerId . "',
            firstname = '" . $this->escape($args['firstname']) . "',
            lastname = '" . $this->escape($args['lastname']) . "',
            company = '" . $this->escape($args['company'] ?? '') . "',
            address_1 = '" . $this->escape($args['address_1']) . "',
            address_2 = '" . $this->escape($args['address_2'] ?? '') . "',
            city = '" . $this->escape($args['city']) . "',
            postcode = '" . $this->escape($args['postcode'] ?? '') . "',
            country_id = '" . (int)$args['country_id'] . "',
            zone_id = '" . (int)$args['zone_id'] . "',
            custom_field = ''");
        $addressId = $this->repository->lastId();
        return array('address_id' => $addressId, 'addresses' => $this->customerSelfListAddresses($args)['addresses']);
    }

    private function customerSelfUpdateAddress($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $addressId = (int)$args['address_id'];
        $this->customerAddressRequired($customerId, $addressId);
        $fields = array();
        foreach (array('firstname', 'lastname', 'company', 'address_1', 'address_2', 'city', 'postcode') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[] = "`" . $field . "` = '" . $this->escape($args[$field]) . "'";
            }
        }
        foreach (array('country_id', 'zone_id') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[] = "`" . $field . "` = '" . (int)$args[$field] . "'";
            }
        }
        if ($fields) {
            $this->repository->query("UPDATE `" . $this->prefix . "address` SET " . implode(', ', $fields) . " WHERE address_id = '" . $addressId . "' AND customer_id = '" . $customerId . "' LIMIT 1");
        }
        return array('address' => $this->customerAddressRequired($customerId, $addressId));
    }

    private function customerSelfDeleteAddress($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $addressId = (int)$args['address_id'];
        $this->customerAddressRequired($customerId, $addressId);
        $this->repository->query("DELETE FROM `" . $this->prefix . "address` WHERE address_id = '" . $addressId . "' AND customer_id = '" . $customerId . "' LIMIT 1");
        return array('address_id' => $addressId, 'deleted' => true);
    }

    private function customerSelfOrders($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $rows = $this->repository->query("SELECT order_id, store_id, total, currency_code, order_status_id, date_added
            FROM `" . $this->prefix . "order`
            WHERE customer_id = '" . $customerId . "'
            ORDER BY order_id DESC
            LIMIT " . (int)$start . ", " . $limit)->rows;
        return array('orders' => $rows, 'page' => $page, 'limit' => $limit);
    }

    private function customerSelfOrderGet($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $orderId = (int)$args['order_id'];
        $order = $this->repository->query("SELECT order_id, customer_id, store_id, total, currency_code, order_status_id, date_added
            FROM `" . $this->prefix . "order`
            WHERE order_id = '" . $orderId . "'
            AND customer_id = '" . $customerId . "'
            LIMIT 1")->row;
        if (!$order) {
            throw new McpException('ENTITY_NOT_FOUND', 'Order not found.');
        }
        $products = $this->repository->query("SELECT product_id, name, model, quantity, price, total FROM `" . $this->prefix . "order_product` WHERE order_id = '" . $orderId . "'")->rows;
        return array('order' => $order, 'products' => $products);
    }

    private function customerSelfDownloads($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $rows = $this->repository->query("SELECT order_download_id, order_id, name, filename, remaining, date_added
            FROM `" . $this->prefix . "order_download`
            WHERE order_id IN (SELECT order_id FROM `" . $this->prefix . "order` WHERE customer_id = '" . $customerId . "')
            ORDER BY order_download_id DESC
            LIMIT " . (int)$start . ", " . $limit)->rows;
        return array('downloads' => $rows, 'page' => $page, 'limit' => $limit);
    }

    private function customerSelfReturns($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $rows = $this->repository->query("SELECT return_id, order_id, product_id, product, model, quantity, return_status_id, date_added
            FROM `" . $this->prefix . "return`
            WHERE customer_id = '" . $customerId . "'
            ORDER BY return_id DESC
            LIMIT " . (int)$start . ", " . $limit)->rows;
        return array('returns' => $rows, 'page' => $page, 'limit' => $limit);
    }

    private function customerSelfCreateReturn($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $orderId = (int)$args['order_id'];
        $productId = (int)$args['product_id'];
        $order = $this->repository->query("SELECT order_id, firstname, lastname, email, telephone FROM `" . $this->prefix . "order` WHERE order_id = '" . $orderId . "' AND customer_id = '" . $customerId . "' LIMIT 1")->row;
        if (!$order) {
            throw new McpException('ENTITY_NOT_FOUND', 'Order not found.');
        }
        $product = $this->repository->query("SELECT name, model FROM `" . $this->prefix . "order_product` WHERE order_id = '" . $orderId . "' AND product_id = '" . $productId . "' LIMIT 1")->row;
        if (!$product) {
            throw new McpException('ENTITY_NOT_FOUND', 'Order product not found.');
        }
        $this->repository->query("INSERT INTO `" . $this->prefix . "return` SET
            order_id = '" . $orderId . "',
            product_id = '" . $productId . "',
            customer_id = '" . $customerId . "',
            firstname = '" . $this->escape($order['firstname']) . "',
            lastname = '" . $this->escape($order['lastname']) . "',
            email = '" . $this->escape($order['email']) . "',
            telephone = '" . $this->escape($order['telephone']) . "',
            product = '" . $this->escape($product['name']) . "',
            model = '" . $this->escape($product['model']) . "',
            quantity = '" . (int)$args['quantity'] . "',
            opened = '" . (int)($args['opened'] ?? 0) . "',
            return_reason_id = '" . (int)$args['return_reason_id'] . "',
            return_status_id = '1',
            comment = '" . $this->escape($args['comment'] ?? '') . "',
            date_ordered = NOW(),
            date_added = NOW(),
            date_modified = NOW()");
        return array('return_id' => $this->repository->lastId(), 'created' => true);
    }

    private function customerSelfRewardPoints($args) {
        $customerId = $this->customerIdFromContext($args['customer_context_token'] ?? '');
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $rows = $this->repository->query("SELECT customer_reward_id, order_id, description, points, date_added
            FROM `" . $this->prefix . "customer_reward`
            WHERE customer_id = '" . $customerId . "'
            ORDER BY customer_reward_id DESC
            LIMIT " . (int)$start . ", " . $limit)->rows;
        return array('total' => $this->customerRewardTotal($customerId), 'rewards' => $rows, 'page' => $page, 'limit' => $limit);
    }

    private function adminProductSearch($args) {
        $languageId = (int)$this->repository->config('config_language_id', 1);
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $where = array("pd.language_id = '" . $languageId . "'");

        if (array_key_exists('status', $args)) {
            $where[] = "p.status = '" . (int)$args['status'] . "'";
        }
        if (!empty($args['query'])) {
            $query = $this->escapeLike($args['query']);
            $where[] = "(pd.name LIKE '%" . $query . "%' OR p.model LIKE '%" . $query . "%' OR p.sku LIKE '%" . $query . "%')";
        }
        if (!empty($args['sku'])) {
            $where[] = "p.sku = '" . $this->escape($args['sku']) . "'";
        }
        if (!empty($args['model'])) {
            $where[] = "p.model = '" . $this->escape($args['model']) . "'";
        }

        $sql = "SELECT p.product_id, pd.name, p.model, p.sku, p.quantity, p.price, p.status, p.date_modified
            FROM `" . $this->prefix . "product` p
            JOIN `" . $this->prefix . "product_description` pd ON p.product_id = pd.product_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.product_id DESC
            LIMIT " . (int)$start . ", " . $limit;

        return array('products' => $this->repository->query($sql)->rows, 'page' => $page, 'limit' => $limit);
    }

    private function adminProductGet($args) {
        $productId = (int)$args['product_id'];
        $product = $this->repository->query("SELECT * FROM `" . $this->prefix . "product` WHERE product_id = '" . $productId . "' LIMIT 1")->row;
        if (!$product) {
            throw new McpException('ENTITY_NOT_FOUND', 'Product not found.');
        }
        $descriptions = $this->repository->query("SELECT * FROM `" . $this->prefix . "product_description` WHERE product_id = '" . $productId . "'")->rows;
        $stores = $this->repository->query("SELECT store_id FROM `" . $this->prefix . "product_to_store` WHERE product_id = '" . $productId . "'")->rows;
        return array('product' => $product, 'descriptions' => $descriptions, 'stores' => $stores);
    }

    private function adminProductDiagnoseVisibility($args) {
        $productId = (int)$args['product_id'];
        $storeId = (int)($args['store_id'] ?? 0);
        $languageId = (int)($args['language_id'] ?? $this->repository->config('config_language_id', 1));
        $result = $this->adminProductGet(array('product_id' => $productId));
        $product = $result['product'];
        $checks = array(
            'exists' => true,
            'enabled' => (int)$product['status'] === 1,
            'date_available' => strtotime($product['date_available']) <= time(),
            'assigned_to_store' => false,
            'has_language_description' => false,
            'has_positive_price' => (float)$product['price'] > 0,
        );
        foreach ($result['stores'] as $store) {
            if ((int)$store['store_id'] === $storeId) {
                $checks['assigned_to_store'] = true;
            }
        }
        foreach ($result['descriptions'] as $description) {
            if ((int)$description['language_id'] === $languageId && trim($description['name']) !== '') {
                $checks['has_language_description'] = true;
            }
        }
        return array('product_id' => $productId, 'checks' => $checks, 'visible' => !in_array(false, $checks, true));
    }

    private function adminProductMissingData($args) {
        $field = $args['field'];
        $limit = $this->limit($args['limit'] ?? 25);
        $languageId = (int)$this->repository->config('config_language_id', 1);
        $where = "1=1";
        $join = "";
        if ($field === 'image') {
            $where = "(p.image = '' OR p.image IS NULL)";
        } elseif ($field === 'seo') {
            $join = "JOIN `" . $this->prefix . "product_description` pd ON p.product_id = pd.product_id AND pd.language_id = '" . $languageId . "'";
            $where = "(pd.meta_title = '' OR pd.meta_title IS NULL)";
        } elseif ($field === 'description') {
            $join = "JOIN `" . $this->prefix . "product_description` pd ON p.product_id = pd.product_id AND pd.language_id = '" . $languageId . "'";
            $where = "(pd.description = '' OR pd.description IS NULL)";
        } elseif ($field === 'category') {
            $where = "p.product_id NOT IN (SELECT product_id FROM `" . $this->prefix . "product_to_category`)";
        } elseif ($field === 'price') {
            $where = "p.price <= 0";
        } elseif ($field === 'stock') {
            $where = "p.quantity <= 0";
        }
        $rows = $this->repository->query("SELECT p.product_id, p.model, p.sku, p.quantity, p.price, p.status
            FROM `" . $this->prefix . "product` p " . $join . "
            WHERE " . $where . "
            ORDER BY p.product_id DESC
            LIMIT " . $limit)->rows;
        return array('field' => $field, 'products' => $rows);
    }

    private function adminProductUpdateStatus($args) {
        $product = $this->productRequired((int)$args['product_id']);
        $result = $this->writeDiff('product', $product['product_id'], array('status' => (int)$product['status']), array('status' => (int)$args['status']), $args);
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "product` SET status = '" . (int)$args['status'] . "', date_modified = NOW() WHERE product_id = '" . (int)$product['product_id'] . "'");
        }
        return $result;
    }

    private function adminProductUpdatePrice($args) {
        $product = $this->productRequired((int)$args['product_id']);
        $result = $this->writeDiff('product', $product['product_id'], array('price' => (float)$product['price']), array('price' => (float)$args['price']), $args);
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "product` SET price = '" . (float)$args['price'] . "', date_modified = NOW() WHERE product_id = '" . (int)$product['product_id'] . "'");
        }
        return $result;
    }

    private function adminProductUpdateSeo($args) {
        $row = $this->productDescriptionRequired((int)$args['product_id'], (int)$args['language_id']);
        $before = array('meta_title' => $row['meta_title'], 'meta_description' => $row['meta_description'], 'meta_keyword' => $row['meta_keyword']);
        $after = $before;
        foreach (array('meta_title', 'meta_description', 'meta_keyword') as $field) {
            if (array_key_exists($field, $args)) {
                $after[$field] = $args[$field];
            }
        }
        $result = $this->writeDiff('product_description', (int)$args['product_id'], $before, $after, $args);
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "product_description` SET
                meta_title = '" . $this->escape($after['meta_title']) . "',
                meta_description = '" . $this->escape($after['meta_description']) . "',
                meta_keyword = '" . $this->escape($after['meta_keyword']) . "'
                WHERE product_id = '" . (int)$args['product_id'] . "'
                AND language_id = '" . (int)$args['language_id'] . "'");
        }
        return $result;
    }

    private function adminProductUpdateDescription($args) {
        $row = $this->productDescriptionRequired((int)$args['product_id'], (int)$args['language_id']);
        $before = array('name' => $row['name'], 'description' => $row['description']);
        $after = $before;
        foreach (array('name', 'description') as $field) {
            if (array_key_exists($field, $args)) {
                $after[$field] = $args[$field];
            }
        }
        $result = $this->writeDiff('product_description', (int)$args['product_id'], $before, $after, $args);
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "product_description` SET
                name = '" . $this->escape($after['name']) . "',
                description = '" . $this->escape($after['description']) . "'
                WHERE product_id = '" . (int)$args['product_id'] . "'
                AND language_id = '" . (int)$args['language_id'] . "'");
        }
        return $result;
    }

    private function inventoryGet($args) {
        $where = array();
        if (!empty($args['product_id'])) {
            $where[] = "product_id = '" . (int)$args['product_id'] . "'";
        }
        if (!empty($args['sku'])) {
            $where[] = "sku = '" . $this->escape($args['sku']) . "'";
        }
        if (!empty($args['model'])) {
            $where[] = "model = '" . $this->escape($args['model']) . "'";
        }
        if (!$where) {
            throw new McpException('INVALID_INPUT', 'Provide product_id, sku, or model.');
        }

        $rows = $this->repository->query("SELECT product_id, model, sku, quantity, stock_status_id, subtract, status FROM `" . $this->prefix . "product` WHERE " . implode(' AND ', $where) . " LIMIT 25")->rows;
        return array('inventory' => $rows);
    }

    private function inventoryLowStock($args) {
        $threshold = (int)($args['threshold'] ?? 5);
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $rows = $this->repository->query("SELECT product_id, model, sku, quantity, stock_status_id, status
            FROM `" . $this->prefix . "product`
            WHERE quantity <= '" . $threshold . "'
            ORDER BY quantity ASC, product_id DESC
            LIMIT " . (int)$start . ", " . $limit)->rows;
        return array('products' => $rows, 'threshold' => $threshold, 'page' => $page, 'limit' => $limit);
    }

    private function inventoryAdjust($args, $client, $requestId) {
        $product = $this->productRequired((int)$args['product_id']);
        $previous = (int)$product['quantity'];
        $delta = (int)$args['delta'];
        $new = $previous + $delta;
        if ($new < 0 && (string)$this->repository->config('config_stock_checkout', '0') !== '1') {
            throw new McpException('ENTITY_CONFLICT', 'Adjustment would create negative stock while negative checkout is disabled.');
        }
        $result = $this->writeDiff('product', (int)$product['product_id'], array('quantity' => $previous), array('quantity' => $new), $args);
        if (!empty($args['dry_run'])) {
            return $result;
        }

        $this->repository->query("START TRANSACTION");
        $this->repository->query("UPDATE `" . $this->prefix . "product`
            SET quantity = '" . $new . "', date_modified = NOW()
            WHERE product_id = '" . (int)$product['product_id'] . "'
            AND quantity = '" . $previous . "'");
        $updated = !is_object($this->db) || !method_exists($this->db, 'countAffected') || $this->db->countAffected() > 0;
        if (!$updated) {
            $this->repository->query("ROLLBACK");
            throw new McpException('ENTITY_CONFLICT', 'Product quantity changed before execution; repeat dry-run.');
        }
        $this->repository->recordInventoryMovement(array(
            'client_id' => $client['client_id'],
            'product_id' => (int)$product['product_id'],
            'previous_quantity' => $previous,
            'new_quantity' => $new,
            'delta' => $delta,
            'reason' => $args['reason'],
            'request_id' => $requestId,
        ));
        $this->repository->query("COMMIT");
        $result['executed'] = true;
        return $result;
    }

    private function inventorySetQuantity($args, $client, $requestId) {
        $product = $this->productRequired((int)$args['product_id']);
        $adjustArgs = $args;
        $adjustArgs['delta'] = (int)$args['quantity'] - (int)$product['quantity'];
        return $this->inventoryAdjust($adjustArgs, $client, $requestId);
    }

    private function inventoryBulkAdjust($args, $client, $requestId) {
        $items = isset($args['items']) && is_array($args['items']) ? $args['items'] : array();
        if (!$items) {
            throw new McpException('INVALID_INPUT', 'Bulk inventory adjustment requires items.');
        }
        if (count($items) > 50) {
            throw new McpException('INVALID_INPUT', 'Bulk inventory adjustment is limited to 50 items.');
        }

        $results = array();
        foreach ($items as $index => $item) {
            if (!isset($item['product_id']) || !array_key_exists('delta', $item)) {
                throw new McpException('INVALID_INPUT', 'Each bulk item requires product_id and delta.');
            }
            $itemArgs = array(
                'product_id' => (int)$item['product_id'],
                'delta' => (int)$item['delta'],
                'reason' => $args['reason'],
                'dry_run' => !empty($args['dry_run']),
            );
            $results[] = $this->inventoryAdjust($itemArgs, $client, $requestId . '-' . $index);
        }

        return array(
            'executed' => empty($args['dry_run']),
            'count' => count($results),
            'results' => $results,
        );
    }

    private function inventoryMovements($args) {
        $limit = $this->limit($args['limit'] ?? 25);
        $where = array('1 = 1');
        if (!empty($args['product_id'])) {
            $where[] = "product_id = '" . (int)$args['product_id'] . "'";
        }
        $rows = $this->repository->query("SELECT movement_id, client_id, product_id, previous_quantity, new_quantity, delta, reason, request_id, created_at
            FROM `" . $this->prefix . "mcp_stock_movement`
            WHERE " . implode(' AND ', $where) . "
            ORDER BY movement_id DESC
            LIMIT " . $limit)->rows;
        return array('movements' => $rows, 'limit' => $limit);
    }

    private function orderSearch($args) {
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $where = array("1 = 1");
        if (!empty($args['order_id'])) {
            $where[] = "order_id = '" . (int)$args['order_id'] . "'";
        }
        if (!empty($args['customer_email'])) {
            $where[] = "email = '" . $this->escape($args['customer_email']) . "'";
        }
        if (array_key_exists('order_status_id', $args)) {
            $where[] = "order_status_id = '" . (int)$args['order_status_id'] . "'";
        }
        if (!empty($args['store_id'])) {
            $where[] = "store_id = '" . (int)$args['store_id'] . "'";
        }
        if (!empty($args['date_from'])) {
            $where[] = "date_added >= '" . $this->escape($args['date_from']) . "'";
        }
        if (!empty($args['date_to'])) {
            $where[] = "date_added <= '" . $this->escape($args['date_to']) . "'";
        }
        $rows = $this->repository->query("SELECT order_id, store_id, firstname, lastname, email, telephone, total, currency_code, order_status_id, date_added
            FROM `" . $this->prefix . "order`
            WHERE " . implode(' AND ', $where) . "
            ORDER BY order_id DESC
            LIMIT " . (int)$start . ", " . $limit)->rows;
        foreach ($rows as $index => $row) {
            $rows[$index]['email'] = Util::maskEmail($row['email']);
            $rows[$index]['telephone'] = Util::maskPhone($row['telephone']);
        }
        return array('orders' => $rows, 'page' => $page, 'limit' => $limit);
    }

    private function orderGet($args) {
        $orderId = (int)$args['order_id'];
        $order = $this->repository->query("SELECT * FROM `" . $this->prefix . "order` WHERE order_id = '" . $orderId . "' LIMIT 1")->row;
        if (!$order) {
            throw new McpException('ENTITY_NOT_FOUND', 'Order not found.');
        }
        $order['email'] = Util::maskEmail($order['email']);
        $order['telephone'] = Util::maskPhone($order['telephone']);
        $products = $this->repository->query("SELECT order_product_id, product_id, name, model, quantity, price, total FROM `" . $this->prefix . "order_product` WHERE order_id = '" . $orderId . "'")->rows;
        $totals = $this->repository->query("SELECT code, title, value, sort_order FROM `" . $this->prefix . "order_total` WHERE order_id = '" . $orderId . "' ORDER BY sort_order")->rows;
        $histories = $this->repository->query("SELECT order_status_id, notify, comment, date_added FROM `" . $this->prefix . "order_history` WHERE order_id = '" . $orderId . "' ORDER BY order_history_id DESC LIMIT 20")->rows;
        return array('order' => $order, 'products' => $products, 'totals' => $totals, 'history' => $histories);
    }

    private function orderAddHistory($args) {
        $order = $this->repository->query("SELECT order_id, order_status_id FROM `" . $this->prefix . "order` WHERE order_id = '" . (int)$args['order_id'] . "' LIMIT 1")->row;
        if (!$order) {
            throw new McpException('ENTITY_NOT_FOUND', 'Order not found.');
        }
        if (!$this->exists('order_status', 'order_status_id', (int)$args['order_status_id'])) {
            throw new McpException('INVALID_INPUT', 'Order status does not exist.');
        }
        $after = array('order_status_id' => (int)$args['order_status_id'], 'comment' => $args['comment'], 'notify' => !empty($args['notify_customer']) ? 1 : 0);
        $result = $this->writeDiff('order', (int)$order['order_id'], array('order_status_id' => (int)$order['order_status_id']), $after, $args);
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "order` SET order_status_id = '" . (int)$args['order_status_id'] . "', date_modified = NOW() WHERE order_id = '" . (int)$args['order_id'] . "'");
            $this->repository->query("INSERT INTO `" . $this->prefix . "order_history` SET
                order_id = '" . (int)$args['order_id'] . "',
                order_status_id = '" . (int)$args['order_status_id'] . "',
                notify = '" . (!empty($args['notify_customer']) ? 1 : 0) . "',
                comment = '" . $this->escape($args['comment']) . "',
                date_added = NOW()");
        }
        return $result;
    }

    private function orderUpdateStatus($args) {
        $order = $this->orderRequired((int)$args['order_id']);
        $statusId = (int)$args['order_status_id'];
        $this->orderStatusRequired($statusId);
        $after = array('order_status_id' => $statusId, 'comment' => (string)($args['comment'] ?? ''), 'notify' => !empty($args['notify']) ? 1 : 0);
        $result = $this->writeDiff('order', (int)$order['order_id'], array('order_status_id' => (int)$order['order_status_id']), $after, $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "order` SET order_status_id = '" . $statusId . "', date_modified = NOW() WHERE order_id = '" . (int)$order['order_id'] . "'");
            $result['history'] = $this->insertOrderHistory((int)$order['order_id'], $statusId, (string)($args['comment'] ?? ''), !empty($args['notify']));
            $result['order'] = $this->orderRequired((int)$order['order_id']);
            $result['executed'] = true;
        }
        return $result;
    }

    private function orderAddNote($args) {
        $order = $this->orderRequired((int)$args['order_id']);
        $comment = (string)$args['comment'];
        $result = $this->writeDiff('order_note', (int)$order['order_id'], array(), array('comment' => $comment, 'notify' => 0), $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $result['history'] = $this->insertOrderHistory((int)$order['order_id'], (int)$order['order_status_id'], $comment, false);
            $result['executed'] = true;
        }
        return $result;
    }

    private function orderNotifyCustomer($args) {
        $order = $this->orderRequired((int)$args['order_id']);
        $statusId = isset($args['order_status_id']) ? (int)$args['order_status_id'] : (int)$order['order_status_id'];
        $this->orderStatusRequired($statusId);
        $comment = (string)$args['comment'];
        $result = $this->writeDiff('order_notification', (int)$order['order_id'], array('order_status_id' => (int)$order['order_status_id']), array('order_status_id' => $statusId, 'comment' => $comment, 'notify' => 1), $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            if ($statusId !== (int)$order['order_status_id']) {
                $this->repository->query("UPDATE `" . $this->prefix . "order` SET order_status_id = '" . $statusId . "', date_modified = NOW() WHERE order_id = '" . (int)$order['order_id'] . "'");
            }
            $result['history'] = $this->insertOrderHistory((int)$order['order_id'], $statusId, $comment, true);
            $result['order'] = $this->orderRequired((int)$order['order_id']);
            $result['executed'] = true;
        }
        return $result;
    }

    private function orderUpdateTracking($args) {
        $order = $this->orderRequired((int)$args['order_id']);
        $tracking = trim((string)$args['tracking']);
        if ($tracking === '') {
            throw new McpException('INVALID_INPUT', 'Tracking value is required.');
        }
        $comment = 'Tracking: ' . $tracking;
        $result = $this->writeDiff('order_tracking', (int)$order['order_id'], array(), array('tracking' => $tracking), $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $result['history'] = $this->insertOrderHistory((int)$order['order_id'], (int)$order['order_status_id'], $comment, false);
            $result['executed'] = true;
        }
        return $result;
    }

    private function orderCreateReturn($args) {
        $order = $this->orderRequired((int)$args['order_id']);
        $product = $this->repository->query("SELECT name, model FROM `" . $this->prefix . "order_product` WHERE order_id = '" . (int)$order['order_id'] . "' AND product_id = '" . (int)$args['product_id'] . "' LIMIT 1")->row;
        if (!$product) {
            throw new McpException('ENTITY_NOT_FOUND', 'Order product not found.');
        }
        $result = $this->writeDiff('return', 'new', array(), array('order_id' => (int)$order['order_id'], 'product_id' => (int)$args['product_id'], 'quantity' => (int)$args['quantity']), $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $this->repository->query("INSERT INTO `" . $this->prefix . "return` SET
                order_id = '" . (int)$order['order_id'] . "',
                product_id = '" . (int)$args['product_id'] . "',
                customer_id = '" . (int)($order['customer_id'] ?? 0) . "',
                firstname = '" . $this->escape($order['firstname'] ?? '') . "',
                lastname = '" . $this->escape($order['lastname'] ?? '') . "',
                email = '" . $this->escape($order['email'] ?? '') . "',
                telephone = '" . $this->escape($order['telephone'] ?? '') . "',
                product = '" . $this->escape($product['name']) . "',
                model = '" . $this->escape($product['model']) . "',
                quantity = '" . (int)$args['quantity'] . "',
                opened = '" . (int)($args['opened'] ?? 0) . "',
                return_reason_id = '" . (int)$args['return_reason_id'] . "',
                return_status_id = '1',
                comment = '" . $this->escape($args['comment'] ?? $args['reason']) . "',
                date_ordered = NOW(),
                date_added = NOW(),
                date_modified = NOW()");
            $result['return_id'] = $this->repository->lastId();
            $result['executed'] = true;
        }
        return $result;
    }

    private function orderResendInvoice($args) {
        $order = $this->orderRequired((int)$args['order_id']);
        $comment = (string)($args['comment'] ?? 'Invoice resend requested.');
        $result = $this->writeDiff('order_notification', (int)$order['order_id'], array(), array('comment' => $comment, 'notify' => 1), $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $result['history'] = $this->insertOrderHistory((int)$order['order_id'], (int)$order['order_status_id'], $comment, true);
            $result['executed'] = true;
        }
        return $result;
    }

    private function customerSearch($args) {
        $limit = $this->limit($args['limit'] ?? 25);
        $page = max(1, (int)($args['page'] ?? 1));
        $start = ($page - 1) * $limit;
        $where = array("1 = 1");
        if (!empty($args['email'])) {
            $where[] = "email = '" . $this->escape($args['email']) . "'";
        }
        if (!empty($args['name'])) {
            $name = $this->escapeLike($args['name']);
            $where[] = "(firstname LIKE '%" . $name . "%' OR lastname LIKE '%" . $name . "%')";
        }
        if (array_key_exists('status', $args)) {
            $where[] = "status = '" . (int)$args['status'] . "'";
        }
        $rows = $this->repository->query("SELECT customer_id, customer_group_id, firstname, lastname, email, telephone, status, date_added
            FROM `" . $this->prefix . "customer`
            WHERE " . implode(' AND ', $where) . "
            ORDER BY customer_id DESC
            LIMIT " . (int)$start . ", " . $limit)->rows;
        foreach ($rows as $index => $row) {
            $rows[$index] = $this->maskCustomer($row);
        }
        return array('customers' => $rows, 'page' => $page, 'limit' => $limit);
    }

    private function customerGet($args) {
        $row = $this->repository->query("SELECT customer_id, customer_group_id, firstname, lastname, email, telephone, status, newsletter, date_added
            FROM `" . $this->prefix . "customer`
            WHERE customer_id = '" . (int)$args['customer_id'] . "' LIMIT 1")->row;
        if (!$row) {
            throw new McpException('ENTITY_NOT_FOUND', 'Customer not found.');
        }
        return array('customer' => $this->maskCustomer($row));
    }

    private function customerOrders($args) {
        $limit = $this->limit($args['limit'] ?? 25);
        $rows = $this->repository->query("SELECT order_id, store_id, total, currency_code, order_status_id, date_added
            FROM `" . $this->prefix . "order`
            WHERE customer_id = '" . (int)$args['customer_id'] . "'
            ORDER BY order_id DESC
            LIMIT " . $limit)->rows;
        return array('orders' => $rows, 'limit' => $limit);
    }

    private function customerUpdateStatus($args) {
        $row = $this->repository->query("SELECT customer_id, status FROM `" . $this->prefix . "customer` WHERE customer_id = '" . (int)$args['customer_id'] . "' LIMIT 1")->row;
        if (!$row) {
            throw new McpException('ENTITY_NOT_FOUND', 'Customer not found.');
        }
        $result = $this->writeDiff('customer', (int)$row['customer_id'], array('status' => (int)$row['status']), array('status' => (int)$args['status']), $args);
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "customer` SET status = '" . (int)$args['status'] . "' WHERE customer_id = '" . (int)$row['customer_id'] . "'");
        }
        return $result;
    }

    private function customerUpdate($args) {
        $customerId = (int)$args['customer_id'];
        $before = $this->customerRequired($customerId);
        $fields = array();
        foreach (array('firstname', 'lastname', 'email', 'telephone') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[$field] = (string)$args[$field];
            }
        }
        if (!$fields) {
            throw new McpException('INVALID_INPUT', 'At least one allowed customer field is required.');
        }
        $after = array_merge($before, $fields);
        $result = $this->writeDiff('customer', $customerId, $before, $after, $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $sets = array();
            foreach ($fields as $field => $value) {
                $sets[] = "`" . $field . "` = '" . $this->escape($value) . "'";
            }
            $this->repository->query("UPDATE `" . $this->prefix . "customer` SET " . implode(', ', $sets) . " WHERE customer_id = '" . $customerId . "' LIMIT 1");
            $result['executed'] = true;
            $result['customer'] = $this->customerRequired($customerId);
        }
        return $result;
    }

    private function customerUpdateGroup($args) {
        $customerId = (int)$args['customer_id'];
        $before = $this->customerRequired($customerId);
        $after = $before;
        $after['customer_group_id'] = (int)$args['customer_group_id'];
        $result = $this->writeDiff('customer', $customerId, array('customer_group_id' => (int)$before['customer_group_id']), array('customer_group_id' => (int)$args['customer_group_id']), $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "customer` SET customer_group_id = '" . (int)$args['customer_group_id'] . "' WHERE customer_id = '" . $customerId . "' LIMIT 1");
            $result['executed'] = true;
            $result['customer'] = $this->customerRequired($customerId);
        }
        return $result;
    }

    private function customerAddresses($args) {
        $customerId = (int)$args['customer_id'];
        $this->customerRequired($customerId);
        $rows = $this->repository->query("SELECT address_id, customer_id, firstname, lastname, company, address_1, address_2, city, postcode, country_id, zone_id, custom_field
            FROM `" . $this->prefix . "address`
            WHERE customer_id = '" . $customerId . "'
            ORDER BY address_id ASC")->rows;
        return array('customer_id' => $customerId, 'addresses' => $rows);
    }

    private function customerAddAddress($args) {
        $customerId = (int)$args['customer_id'];
        $this->customerRequired($customerId);
        $data = array(
            'firstname' => (string)($args['firstname'] ?? ''),
            'lastname' => (string)($args['lastname'] ?? ''),
            'company' => (string)($args['company'] ?? ''),
            'address_1' => (string)($args['address_1'] ?? ''),
            'address_2' => (string)($args['address_2'] ?? ''),
            'city' => (string)($args['city'] ?? ''),
            'postcode' => (string)($args['postcode'] ?? ''),
            'country_id' => (int)($args['country_id'] ?? 0),
            'zone_id' => (int)($args['zone_id'] ?? 0),
        );
        $result = $this->writeDiff('customer_address', 'new', array(), $data, $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $this->repository->query("INSERT INTO `" . $this->prefix . "address` SET
                customer_id = '" . $customerId . "',
                firstname = '" . $this->escape($data['firstname']) . "',
                lastname = '" . $this->escape($data['lastname']) . "',
                company = '" . $this->escape($data['company']) . "',
                address_1 = '" . $this->escape($data['address_1']) . "',
                address_2 = '" . $this->escape($data['address_2']) . "',
                city = '" . $this->escape($data['city']) . "',
                postcode = '" . $this->escape($data['postcode']) . "',
                country_id = '" . $data['country_id'] . "',
                zone_id = '" . $data['zone_id'] . "',
                custom_field = ''");
            $addressId = $this->repository->lastId();
            $result['executed'] = true;
            $result['address_id'] = $addressId;
            $result['address'] = $this->customerAddressRequired($customerId, $addressId);
        }
        return $result;
    }

    private function customerUpdateAddress($args) {
        $customerId = (int)$args['customer_id'];
        $addressId = (int)$args['address_id'];
        $before = $this->customerAddressRequired($customerId, $addressId);
        $fields = array();
        foreach (array('firstname', 'lastname', 'company', 'address_1', 'address_2', 'city', 'postcode') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[$field] = (string)$args[$field];
            }
        }
        foreach (array('country_id', 'zone_id') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[$field] = (int)$args[$field];
            }
        }
        if (!$fields) {
            throw new McpException('INVALID_INPUT', 'At least one allowed address field is required.');
        }
        $after = array_merge($before, $fields);
        $result = $this->writeDiff('customer_address', $addressId, $before, $after, $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $sets = array();
            foreach ($fields as $field => $value) {
                $sets[] = "`" . $field . "` = '" . $this->escape($value) . "'";
            }
            $this->repository->query("UPDATE `" . $this->prefix . "address` SET " . implode(', ', $sets) . " WHERE address_id = '" . $addressId . "' AND customer_id = '" . $customerId . "' LIMIT 1");
            $result['executed'] = true;
            $result['address'] = $this->customerAddressRequired($customerId, $addressId);
        }
        return $result;
    }

    private function customerDeleteAddress($args) {
        $customerId = (int)$args['customer_id'];
        $addressId = (int)$args['address_id'];
        $before = $this->customerAddressRequired($customerId, $addressId);
        $result = $this->writeDiff('customer_address', $addressId, $before, array('deleted' => true), $args);
        $result['executed'] = false;
        $result['deleted'] = false;
        if (empty($args['dry_run'])) {
            $this->repository->query("DELETE FROM `" . $this->prefix . "address` WHERE address_id = '" . $addressId . "' AND customer_id = '" . $customerId . "' LIMIT 1");
            $result['executed'] = true;
            $result['deleted'] = true;
        }
        return $result;
    }

    private function couponSearch($args) {
        $limit = $this->limit($args['limit'] ?? 25);
        $where = array("1 = 1");
        if (!empty($args['code'])) {
            $where[] = "code = '" . $this->escape($args['code']) . "'";
        }
        if (array_key_exists('status', $args)) {
            $where[] = "status = '" . (int)$args['status'] . "'";
        }
        $rows = $this->repository->query("SELECT coupon_id, name, code, type, discount, date_start, date_end, uses_total, uses_customer, status
            FROM `" . $this->prefix . "coupon`
            WHERE " . implode(' AND ', $where) . "
            ORDER BY coupon_id DESC
            LIMIT " . $limit)->rows;
        return array('coupons' => $rows);
    }

    private function couponGet($args) {
        $coupon = $this->repository->query("SELECT * FROM `" . $this->prefix . "coupon` WHERE coupon_id = '" . (int)$args['coupon_id'] . "' LIMIT 1")->row;
        if (!$coupon) {
            throw new McpException('ENTITY_NOT_FOUND', 'Coupon not found.');
        }
        return array('coupon' => $coupon);
    }

    private function couponCreate($args) {
        $this->validateCouponDates($args);
        if ($args['type'] === 'P' && (float)$args['discount'] > 100) {
            throw new McpException('INVALID_INPUT', 'Percentage discount cannot exceed 100.');
        }
        $existing = $this->repository->query("SELECT coupon_id FROM `" . $this->prefix . "coupon` WHERE code = '" . $this->escape($args['code']) . "' LIMIT 1")->row;
        if ($existing) {
            throw new McpException('ENTITY_CONFLICT', 'Coupon code already exists.');
        }
        $after = array(
            'name' => $args['name'],
            'code' => $args['code'],
            'type' => $args['type'],
            'discount' => (float)$args['discount'],
            'date_start' => $args['date_start'],
            'date_end' => $args['date_end'],
            'status' => (int)($args['status'] ?? 1),
        );
        $result = $this->writeDiff('coupon', '', array(), $after, $args);
        if (empty($args['dry_run'])) {
            $this->repository->query("INSERT INTO `" . $this->prefix . "coupon` SET
                name = '" . $this->escape($args['name']) . "',
                code = '" . $this->escape($args['code']) . "',
                type = '" . $this->escape($args['type']) . "',
                discount = '" . (float)$args['discount'] . "',
                logged = 0,
                shipping = 0,
                total = '" . (float)($args['total'] ?? 0) . "',
                date_start = '" . $this->escape($args['date_start']) . "',
                date_end = '" . $this->escape($args['date_end']) . "',
                uses_total = '" . (int)($args['uses_total'] ?? 1) . "',
                uses_customer = '" . (int)($args['uses_customer'] ?? 1) . "',
                status = '" . (int)($args['status'] ?? 1) . "',
                date_added = NOW()");
            $result['coupon_id'] = $this->repository->lastId();
        }
        return $result;
    }

    private function couponDisable($args) {
        $coupon = $this->repository->query("SELECT coupon_id, status FROM `" . $this->prefix . "coupon` WHERE coupon_id = '" . (int)$args['coupon_id'] . "' LIMIT 1")->row;
        if (!$coupon) {
            throw new McpException('ENTITY_NOT_FOUND', 'Coupon not found.');
        }
        $result = $this->writeDiff('coupon', (int)$coupon['coupon_id'], array('status' => (int)$coupon['status']), array('status' => 0), $args);
        if (empty($args['dry_run'])) {
            $this->repository->query("UPDATE `" . $this->prefix . "coupon` SET status = 0 WHERE coupon_id = '" . (int)$coupon['coupon_id'] . "'");
        }
        return $result;
    }

    private function couponUpdate($args) {
        $coupon = $this->couponRequired((int)$args['coupon_id']);
        if (!empty($args['date_start']) || !empty($args['date_end'])) {
            $this->validateCouponDates(array(
                'date_start' => $args['date_start'] ?? $coupon['date_start'],
                'date_end' => $args['date_end'] ?? $coupon['date_end'],
            ));
        }

        $fields = array();
        foreach (array('name', 'code', 'type', 'date_start', 'date_end') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[$field] = (string)$args[$field];
            }
        }
        foreach (array('discount', 'total') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[$field] = (float)$args[$field];
            }
        }
        foreach (array('logged', 'shipping', 'uses_total', 'uses_customer', 'status') as $field) {
            if (array_key_exists($field, $args)) {
                $fields[$field] = (int)$args[$field];
            }
        }
        if (!$fields) {
            throw new McpException('INVALID_INPUT', 'At least one allowed coupon field is required.');
        }
        if (isset($fields['type']) && !in_array($fields['type'], array('P', 'F'), true)) {
            throw new McpException('INVALID_INPUT', 'Coupon type must be P or F.');
        }

        $after = array_merge($coupon, $fields);
        $result = $this->writeDiff('coupon', (int)$coupon['coupon_id'], $coupon, $after, $args);
        $result['executed'] = false;
        if (empty($args['dry_run'])) {
            $sets = array();
            foreach ($fields as $field => $value) {
                $sets[] = "`" . $field . "` = '" . $this->escape($value) . "'";
            }
            $this->repository->query("UPDATE `" . $this->prefix . "coupon` SET " . implode(', ', $sets) . " WHERE coupon_id = '" . (int)$coupon['coupon_id'] . "' LIMIT 1");
            $result['coupon'] = $this->couponRequired((int)$coupon['coupon_id']);
            $result['executed'] = true;
        }
        return $result;
    }

    private function couponDelete($args) {
        $coupon = $this->couponRequired((int)$args['coupon_id']);
        $result = $this->writeDiff('coupon', (int)$coupon['coupon_id'], $coupon, array('deleted' => true), $args);
        $result['executed'] = false;
        $result['deleted'] = false;
        if (empty($args['dry_run'])) {
            $this->repository->query("DELETE FROM `" . $this->prefix . "coupon` WHERE coupon_id = '" . (int)$coupon['coupon_id'] . "' LIMIT 1");
            $result['executed'] = true;
            $result['deleted'] = true;
        }
        return $result;
    }

    private function reportSalesSummary($args) {
        $this->requireDateRange($args);
        $where = $this->dateRangeWhere($args);
        $row = $this->repository->query("SELECT COUNT(*) AS order_count, SUM(total) AS total_sales, AVG(total) AS average_order_value
            FROM `" . $this->prefix . "order`
            WHERE " . implode(' AND ', $where))->row;
        return array('summary' => $row);
    }

    private function reportOrdersByStatus($args) {
        $this->requireDateRange($args);
        $where = $this->dateRangeWhere($args);
        $rows = $this->repository->query("SELECT order_status_id, COUNT(*) AS order_count, SUM(total) AS total_sales
            FROM `" . $this->prefix . "order`
            WHERE " . implode(' AND ', $where) . "
            GROUP BY order_status_id
            ORDER BY order_count DESC")->rows;
        return array('statuses' => $rows);
    }

    private function reportLowStockValue($args) {
        $threshold = (int)($args['threshold'] ?? 5);
        $row = $this->repository->query("SELECT COUNT(*) AS product_count, SUM(quantity * price) AS stock_value
            FROM `" . $this->prefix . "product`
            WHERE quantity <= '" . $threshold . "'")->row;
        return array('threshold' => $threshold, 'summary' => $row);
    }

    private function settingGet($args) {
        $keys = array_slice((array)$args['keys'], 0, 50);
        $safe = array();
        foreach ($keys as $key) {
            if ($this->isSensitiveSettingKey($key)) {
                $safe[$key] = '[blocked]';
                continue;
            }
            $query = $this->repository->query("SELECT `value`, `serialized` FROM `" . $this->prefix . "setting`
                WHERE `store_id` = '" . (int)($args['store_id'] ?? 0) . "'
                AND `key` = '" . $this->escape($key) . "'
                LIMIT 1");
            if ($query->row) {
                $safe[$key] = !empty($query->row['serialized']) ? Util::jsonDecodeArray($query->row['value'], $query->row['value']) : $query->row['value'];
            }
        }
        return array('settings' => $safe);
    }

    private function diagnosticStatus() {
        $registry = new ToolRegistry();
        return array(
            'extension' => 'OpenCart MCP Server',
            'enabled' => $this->repository->isEnabled(),
            'php_version' => PHP_VERSION,
            'opencart_version' => defined('VERSION') ? VERSION : '',
            'tool_count' => count($registry->all()),
            'streaming' => false,
        );
    }

    private function visibleProductWhere($storeId, $languageId) {
        return array(
            "p.status = 1",
            "p.date_available <= NOW()",
            "pd.language_id = '" . (int)$languageId . "'",
            "p2s.store_id = '" . (int)$storeId . "'",
        );
    }

    private function visibleProduct($productId, $storeId, $languageId) {
        $sql = "SELECT p.product_id, pd.name, pd.description, pd.meta_title, p.model, p.sku, p.quantity, p.price, p.image, p.status, p.date_available
            FROM `" . $this->prefix . "product` p
            JOIN `" . $this->prefix . "product_description` pd ON p.product_id = pd.product_id
            JOIN `" . $this->prefix . "product_to_store` p2s ON p.product_id = p2s.product_id
            WHERE p.product_id = '" . (int)$productId . "'
            AND " . implode(' AND ', $this->visibleProductWhere($storeId, $languageId)) . "
            LIMIT 1";
        return $this->repository->query($sql)->row;
    }

    private function cartProduct($productId, $storeId) {
        $languageId = (int)$this->repository->config('config_language_id', 1);
        $product = $this->visibleProduct($productId, $storeId, $languageId);
        if (!$product) {
            throw new McpException('ENTITY_NOT_FOUND', 'Product not found or not visible.');
        }
        return $product;
    }

    private function couponByCode($code) {
        $coupon = $this->repository->query("SELECT coupon_id, code, status FROM `" . $this->prefix . "coupon`
            WHERE code = '" . $this->escape($code) . "'
            AND status = 1
            AND (date_start = '0000-00-00' OR date_start <= CURDATE())
            AND (date_end = '0000-00-00' OR date_end >= CURDATE())
            LIMIT 1")->row;
        if (!$coupon) {
            throw new McpException('ENTITY_NOT_FOUND', 'Coupon not found or not active.');
        }
        return $coupon;
    }

    private function couponRequired($couponId) {
        $coupon = $this->repository->query("SELECT * FROM `" . $this->prefix . "coupon` WHERE coupon_id = '" . (int)$couponId . "' LIMIT 1")->row;
        if (!$coupon) {
            throw new McpException('ENTITY_NOT_FOUND', 'Coupon not found.');
        }
        return $coupon;
    }

    private function customerAddressRequired($customerId, $addressId) {
        $address = $this->repository->query("SELECT address_id, firstname, lastname, company, address_1, address_2, city, postcode, country_id, zone_id, custom_field
            FROM `" . $this->prefix . "address`
            WHERE address_id = '" . (int)$addressId . "' AND customer_id = '" . (int)$customerId . "'
            LIMIT 1")->row;
        if (!$address) {
            throw new McpException('ENTITY_NOT_FOUND', 'Address not found.');
        }
        return $address;
    }

    private function customerRequired($customerId) {
        $customer = $this->repository->query("SELECT customer_id, customer_group_id, firstname, lastname, email, telephone, status, newsletter, date_added
            FROM `" . $this->prefix . "customer`
            WHERE customer_id = '" . (int)$customerId . "'
            LIMIT 1")->row;
        if (!$customer) {
            throw new McpException('ENTITY_NOT_FOUND', 'Customer not found.');
        }
        return $customer;
    }

    private function orderRequired($orderId) {
        $order = $this->repository->query("SELECT * FROM `" . $this->prefix . "order` WHERE order_id = '" . (int)$orderId . "' LIMIT 1")->row;
        if (!$order) {
            throw new McpException('ENTITY_NOT_FOUND', 'Order not found.');
        }
        return $order;
    }

    private function orderStatusRequired($orderStatusId) {
        $status = $this->repository->query("SELECT order_status_id FROM `" . $this->prefix . "order_status` WHERE order_status_id = '" . (int)$orderStatusId . "' LIMIT 1")->row;
        if (!$status) {
            throw new McpException('INVALID_INPUT', 'Order status does not exist.');
        }
        return $status;
    }

    private function insertOrderHistory($orderId, $orderStatusId, $comment, $notify) {
        $history = array(
            'order_id' => (int)$orderId,
            'order_status_id' => (int)$orderStatusId,
            'notify' => $notify ? 1 : 0,
            'comment' => (string)$comment,
        );
        $this->repository->query("INSERT INTO `" . $this->prefix . "order_history` SET
            order_id = '" . (int)$history['order_id'] . "',
            order_status_id = '" . (int)$history['order_status_id'] . "',
            notify = '" . (int)$history['notify'] . "',
            comment = '" . $this->escape($history['comment']) . "',
            date_added = NOW()");
        return $history;
    }

    private function customerRewardTotal($customerId) {
        $row = $this->repository->query("SELECT SUM(points) AS total FROM `" . $this->prefix . "customer_reward` WHERE customer_id = '" . (int)$customerId . "'")->row;
        return (int)($row['total'] ?? 0);
    }

    private function loadCart($cartId, $client) {
        $cart = $this->repository->getCart($cartId, $client['client_id']);
        if (!$cart) {
            throw new McpException('ENTITY_NOT_FOUND', 'Cart not found, expired, or not owned by client.');
        }
        if (!isset($cart['data']['items']) || !is_array($cart['data']['items'])) {
            $cart['data']['items'] = array();
        }
        return $cart;
    }

    private function customerIdFromContext($token) {
        $customerId = $this->repository->verifyCustomerContext($token);
        if ($customerId <= 0) {
            throw new McpException('CUSTOMER_CONTEXT_REQUIRED', 'A valid signed customer context token is required.');
        }
        return $customerId;
    }

    private function productRequired($productId) {
        $row = $this->repository->query("SELECT * FROM `" . $this->prefix . "product` WHERE product_id = '" . (int)$productId . "' LIMIT 1")->row;
        if (!$row) {
            throw new McpException('ENTITY_NOT_FOUND', 'Product not found.');
        }
        return $row;
    }

    private function productDescriptionRequired($productId, $languageId) {
        $row = $this->repository->query("SELECT * FROM `" . $this->prefix . "product_description`
            WHERE product_id = '" . (int)$productId . "'
            AND language_id = '" . (int)$languageId . "'
            LIMIT 1")->row;
        if (!$row) {
            throw new McpException('ENTITY_NOT_FOUND', 'Product language description not found.');
        }
        return $row;
    }

    private function writeDiff($entityType, $entityId, $before, $after, $args) {
        return array(
            'dry_run' => !empty($args['dry_run']),
            'entity_type' => $entityType,
            'entity_id' => (string)$entityId,
            'reason' => $args['reason'] ?? '',
            'before' => $before,
            'after' => $after,
        );
    }

    private function exists($table, $field, $id) {
        $query = $this->repository->query("SELECT `" . $this->escapeIdentifier($field) . "` FROM `" . $this->prefix . $this->escapeIdentifier($table) . "` WHERE `" . $this->escapeIdentifier($field) . "` = '" . (int)$id . "' LIMIT 1");
        return !empty($query->row);
    }

    private function validateCouponDates($args) {
        $start = strtotime($args['date_start']);
        $end = strtotime($args['date_end']);
        if (!$start || !$end || $end < $start) {
            throw new McpException('INVALID_INPUT', 'Coupon date range is invalid.');
        }
    }

    private function requireDateRange($args) {
        if (empty($args['date_from']) || empty($args['date_to'])) {
            throw new McpException('INVALID_INPUT', 'Reports require date_from and date_to.');
        }
        if (strtotime($args['date_to']) < strtotime($args['date_from'])) {
            throw new McpException('INVALID_INPUT', 'date_to must be after date_from.');
        }
    }

    private function dateRangeWhere($args) {
        $where = array(
            "date_added >= '" . $this->escape($args['date_from']) . "'",
            "date_added <= '" . $this->escape($args['date_to']) . "'",
        );
        if (array_key_exists('store_id', $args)) {
            $where[] = "store_id = '" . (int)$args['store_id'] . "'";
        }
        return $where;
    }

    private function maskCustomer($row) {
        $row['email'] = Util::maskEmail($row['email']);
        $row['telephone'] = Util::maskPhone($row['telephone']);
        return $row;
    }

    private function isSensitiveSettingKey($key) {
        return (bool)preg_match('/(secret|token|password|passwd|encryption|api|key|private|credential)/i', (string)$key);
    }

    private function limit($value) {
        return max(1, min(100, (int)$value));
    }

    private function escape($value) {
        return $this->repository->escape($value);
    }

    private function escapeLike($value) {
        return str_replace(array('%', '_'), array('\%', '\_'), $this->escape($value));
    }

    private function escapeIdentifier($value) {
        return preg_replace('/[^a-zA-Z0-9_]/', '', (string)$value);
    }
}
