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

class ToolRegistry {
    public function all() {
        return array_merge(array(
            $this->tool('catalog.store.get', 'Get storefront name, URL, language, currency, and safe public settings.', 'R1', 'catalog_read', array('catalog:read'), array()),
            $this->tool('catalog.category.tree', 'List enabled storefront categories for the selected store and language.', 'R1', 'catalog_read', array('catalog:read'), array(
                'parent_id' => $this->intSchema(0),
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
                'limit' => $this->intSchema(1, 100),
            )),
            $this->tool('catalog.product.search', 'Search storefront-visible products only. Disabled, future-dated, and unassigned products are excluded.', 'R1', 'catalog_read', array('catalog:read'), array(
                'query' => $this->stringSchema(128),
                'category_id' => $this->intSchema(1),
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            )),
            $this->tool('catalog.product.get', 'Get one storefront-visible product with localized fields and safe price/stock metadata.', 'R1', 'catalog_read', array('catalog:read'), array(
                'product_id' => $this->intSchema(1),
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
            ), array('product_id')),
            $this->tool('catalog.product.availability', 'Get storefront-safe purchasability and stock status for one product.', 'R1', 'catalog_read', array('catalog:read'), array(
                'product_id' => $this->intSchema(1),
                'store_id' => $this->intSchema(0),
            ), array('product_id')),
            $this->tool('catalog.product.price', 'Get storefront-safe base and special price data for one product.', 'R1', 'catalog_read', array('catalog:read'), array(
                'product_id' => $this->intSchema(1),
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
                'customer_group_id' => $this->intSchema(1),
            ), array('product_id')),
            $this->tool('catalog.product.related', 'List visible related products for one product.', 'R1', 'catalog_read', array('catalog:read'), array(
                'product_id' => $this->intSchema(1),
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
                'limit' => $this->intSchema(1, 100),
            ), array('product_id')),
            $this->tool('catalog.product.compare', 'Compare visible product attributes for up to 10 products.', 'R1', 'catalog_read', array('catalog:read'), array(
                'product_ids' => $this->arrayOf($this->intSchema(1)),
                'language_id' => $this->intSchema(1),
            ), array('product_ids')),
            $this->tool('catalog.manufacturer.list', 'List manufacturers attached to visible catalog products.', 'R1', 'catalog_read', array('catalog:read'), array(
                'limit' => $this->intSchema(1, 100),
            )),
            $this->tool('catalog.manufacturer.get', 'Get one manufacturer and visible product IDs.', 'R1', 'catalog_read', array('catalog:read'), array(
                'manufacturer_id' => $this->intSchema(1),
                'store_id' => $this->intSchema(0),
                'limit' => $this->intSchema(1, 100),
            ), array('manufacturer_id')),
            $this->tool('catalog.review.list', 'List approved reviews for one visible product.', 'R1', 'catalog_read', array('catalog:read'), array(
                'product_id' => $this->intSchema(1),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            ), array('product_id')),
            $this->tool('catalog.information.list', 'List enabled information pages for a store and language.', 'R1', 'catalog_read', array('catalog:read'), array(
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
                'limit' => $this->intSchema(1, 100),
            )),
            $this->tool('catalog.information.get', 'Get one enabled information page for a store and language.', 'R1', 'catalog_read', array('catalog:read'), array(
                'information_id' => $this->intSchema(1),
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
            ), array('information_id')),
            $this->tool('catalog.filter.options', 'List enabled filters for a visible category.', 'R1', 'catalog_read', array('catalog:read'), array(
                'category_id' => $this->intSchema(1),
                'language_id' => $this->intSchema(1),
                'limit' => $this->intSchema(1, 100),
            ), array('category_id')),
            $this->tool('catalog.search.suggest', 'Return storefront-safe product search suggestions.', 'R1', 'catalog_read', array('catalog:read'), array(
                'query' => $this->stringSchema(128),
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
                'limit' => $this->intSchema(1, 20),
            ), array('query')),

            $this->tool('cart.create', 'Create a dedicated MCP cart context. Does not create an order or touch browser sessions.', 'R2', 'catalog_session', array('catalog:cart'), array(
                'store_id' => $this->intSchema(0),
                'currency_code' => $this->stringSchema(3),
            )),
            $this->tool('cart.get', 'Read an MCP cart context.', 'R2', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
            ), array('cart_id')),
            $this->tool('cart.add_item', 'Add a product to an MCP cart context after product visibility and stock checks.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
                'product_id' => $this->intSchema(1),
                'quantity' => $this->intSchema(1, 1000),
            ), array('cart_id', 'product_id', 'quantity')),
            $this->tool('cart.update_item', 'Update quantity for one MCP cart item.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
                'product_id' => $this->intSchema(1),
                'quantity' => $this->intSchema(0, 1000),
            ), array('cart_id', 'product_id', 'quantity')),
            $this->tool('cart.remove_item', 'Remove one product from an MCP cart context.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
                'product_id' => $this->intSchema(1),
            ), array('cart_id', 'product_id')),
            $this->tool('cart.clear', 'Clear an MCP cart context.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
            ), array('cart_id')),
            $this->tool('cart.apply_coupon', 'Apply an active coupon code to an MCP cart context.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
                'coupon_code' => $this->stringSchema(64),
            ), array('cart_id', 'coupon_code')),
            $this->tool('cart.remove_coupon', 'Remove the coupon from an MCP cart context.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
            ), array('cart_id')),
            $this->tool('cart.apply_voucher', 'Apply a voucher code to an MCP cart context without charging payment.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
                'voucher_code' => $this->stringSchema(64),
            ), array('cart_id', 'voucher_code')),
            $this->tool('cart.apply_reward', 'Apply reward points to an MCP cart context for a signed customer.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
                'customer_context_token' => $this->stringSchema(255),
                'points' => $this->intSchema(1),
            ), array('cart_id', 'customer_context_token', 'points')),
            $this->tool('cart.quote_shipping', 'Return safe shipping quote availability for an MCP cart context.', 'R2', 'catalog_session', array('catalog:checkout_quote'), array(
                'cart_id' => $this->stringSchema(64),
            ), array('cart_id')),
            $this->tool('cart.select_shipping', 'Select a quoted shipping method on an MCP cart context.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
                'shipping_method' => $this->stringSchema(128),
            ), array('cart_id', 'shipping_method')),
            $this->tool('cart.quote_payment', 'Return safe payment method availability for an MCP cart context.', 'R2', 'catalog_session', array('catalog:checkout_quote'), array(
                'cart_id' => $this->stringSchema(64),
            ), array('cart_id')),
            $this->tool('cart.select_payment', 'Select a quoted payment method on an MCP cart context without capturing funds.', 'R3', 'catalog_session', array('catalog:cart'), array(
                'cart_id' => $this->stringSchema(64),
                'payment_method' => $this->stringSchema(128),
            ), array('cart_id', 'payment_method')),
            $this->tool('cart.estimate_totals', 'Estimate MCP cart totals without creating an order or selecting payment.', 'R2', 'catalog_session', array('catalog:checkout_quote'), array(
                'cart_id' => $this->stringSchema(64),
            ), array('cart_id')),
            $this->tool('checkout.validate', 'Validate MCP cart readiness without creating an order.', 'R2', 'catalog_session', array('catalog:checkout_quote'), array(
                'cart_id' => $this->stringSchema(64),
            ), array('cart_id')),

            $this->tool('customer.self.get_profile', 'Get profile for a signed customer context.', 'R2', 'customer_self_service', array('customer:self_read'), array(
                'customer_context_token' => $this->stringSchema(255),
            ), array('customer_context_token')),
            $this->tool('customer.self.update_profile', 'Update allowed profile fields for a signed customer context.', 'R3', 'customer_self_service', array('customer:self_write'), array(
                'customer_context_token' => $this->stringSchema(255),
                'firstname' => $this->stringSchema(64),
                'lastname' => $this->stringSchema(64),
                'email' => $this->stringSchema(96),
                'telephone' => $this->stringSchema(32),
            ), array('customer_context_token')),
            $this->tool('customer.self.list_addresses', 'List addresses for a signed customer context.', 'R2', 'customer_self_service', array('customer:self_read'), array(
                'customer_context_token' => $this->stringSchema(255),
            ), array('customer_context_token')),
            $this->tool('customer.self.add_address', 'Add address for a signed customer context.', 'R3', 'customer_self_service', array('customer:self_write'), array(
                'customer_context_token' => $this->stringSchema(255),
                'firstname' => $this->stringSchema(64),
                'lastname' => $this->stringSchema(64),
                'company' => $this->stringSchema(64),
                'address_1' => $this->stringSchema(128),
                'address_2' => $this->stringSchema(128),
                'city' => $this->stringSchema(128),
                'postcode' => $this->stringSchema(16),
                'country_id' => $this->intSchema(1),
                'zone_id' => $this->intSchema(0),
            ), array('customer_context_token', 'firstname', 'lastname', 'address_1', 'city', 'country_id', 'zone_id')),
            $this->tool('customer.self.update_address', 'Update address for a signed customer context.', 'R3', 'customer_self_service', array('customer:self_write'), array(
                'customer_context_token' => $this->stringSchema(255),
                'address_id' => $this->intSchema(1),
                'firstname' => $this->stringSchema(64),
                'lastname' => $this->stringSchema(64),
                'company' => $this->stringSchema(64),
                'address_1' => $this->stringSchema(128),
                'address_2' => $this->stringSchema(128),
                'city' => $this->stringSchema(128),
                'postcode' => $this->stringSchema(16),
                'country_id' => $this->intSchema(1),
                'zone_id' => $this->intSchema(0),
            ), array('customer_context_token', 'address_id')),
            $this->tool('customer.self.delete_address', 'Delete address for a signed customer context.', 'R3', 'customer_self_service', array('customer:self_write'), array(
                'customer_context_token' => $this->stringSchema(255),
                'address_id' => $this->intSchema(1),
            ), array('customer_context_token', 'address_id')),
            $this->tool('customer.self.orders', 'List orders for a signed customer context. Customer ID alone is not accepted.', 'R2', 'customer_self_service', array('customer:self_read'), array(
                'customer_context_token' => $this->stringSchema(255),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            ), array('customer_context_token')),
            $this->tool('customer.self.order_get', 'Get one order belonging to a signed customer context.', 'R2', 'customer_self_service', array('customer:self_read'), array(
                'customer_context_token' => $this->stringSchema(255),
                'order_id' => $this->intSchema(1),
            ), array('customer_context_token', 'order_id')),
            $this->tool('customer.self.downloads', 'List downloadable products for a signed customer context.', 'R2', 'customer_self_service', array('customer:self_read'), array(
                'customer_context_token' => $this->stringSchema(255),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            ), array('customer_context_token')),
            $this->tool('customer.self.returns', 'List returns for a signed customer context.', 'R2', 'customer_self_service', array('customer:self_read'), array(
                'customer_context_token' => $this->stringSchema(255),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            ), array('customer_context_token')),
            $this->tool('customer.self.create_return', 'Create a return request for a signed customer context.', 'R3', 'customer_self_service', array('customer:self_write'), array(
                'customer_context_token' => $this->stringSchema(255),
                'order_id' => $this->intSchema(1),
                'product_id' => $this->intSchema(1),
                'quantity' => $this->intSchema(1, 1000),
                'return_reason_id' => $this->intSchema(1),
                'opened' => $this->intSchema(0, 1),
                'comment' => $this->stringSchema(1000),
            ), array('customer_context_token', 'order_id', 'product_id', 'quantity', 'return_reason_id')),
            $this->tool('customer.self.reward_points', 'Get reward points for a signed customer context.', 'R2', 'customer_self_service', array('customer:self_read'), array(
                'customer_context_token' => $this->stringSchema(255),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            ), array('customer_context_token')),

            $this->tool('admin.product.search', 'Search products for merchant operations, including disabled or out-of-stock products.', 'R2', 'admin_catalog_read', array('admin:catalog_read'), array(
                'query' => $this->stringSchema(128),
                'status' => array('type' => 'integer', 'enum' => array(0, 1)),
                'sku' => $this->stringSchema(64),
                'model' => $this->stringSchema(64),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            )),
            $this->tool('admin.product.get', 'Get a merchant product record with descriptions, store assignment, and inventory metadata.', 'R2', 'admin_catalog_read', array('admin:catalog_read'), array(
                'product_id' => $this->intSchema(1),
            ), array('product_id')),
            $this->tool('admin.product.diagnose_visibility', 'Explain common reasons a product is not visible in storefront.', 'R2', 'admin_catalog_read', array('admin:catalog_read'), array(
                'product_id' => $this->intSchema(1),
                'store_id' => $this->intSchema(0),
                'language_id' => $this->intSchema(1),
            ), array('product_id')),
            $this->tool('admin.product.list_missing_data', 'Find products missing images, SEO titles, descriptions, categories, price, or stock.', 'R2', 'admin_catalog_read', array('admin:catalog_read'), array(
                'field' => array('type' => 'string', 'enum' => array('image', 'seo', 'description', 'category', 'price', 'stock')),
                'limit' => $this->intSchema(1, 100),
            ), array('field')),
            $this->tool('admin.product.update_status', 'WRITE: Enable or disable a product. Requires dry-run, confirmation token, idempotency key, and reason.', 'R4', 'admin_catalog_write', array('admin:catalog_write'), array(
                'product_id' => $this->intSchema(1),
                'status' => array('type' => 'integer', 'enum' => array(0, 1)),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('product_id', 'status', 'reason'), true, true, true),
            $this->tool('admin.product.update_price', 'WRITE: Update base product price. Requires dry-run, confirmation token, idempotency key, and reason.', 'R4', 'admin_catalog_write', array('admin:catalog_write'), array(
                'product_id' => $this->intSchema(1),
                'price' => $this->numberSchema(0, 100000000),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('product_id', 'price', 'reason'), true, true, true),
            $this->tool('admin.product.update_seo', 'WRITE: Update product SEO metadata for one language. Requires dry-run, confirmation token, idempotency key, and reason.', 'R3', 'admin_catalog_write', array('admin:catalog_write'), array(
                'product_id' => $this->intSchema(1),
                'language_id' => $this->intSchema(1),
                'meta_title' => $this->stringSchema(255),
                'meta_description' => $this->stringSchema(512),
                'meta_keyword' => $this->stringSchema(255),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('product_id', 'language_id', 'reason'), true, true, true),
            $this->tool('admin.product.update_description', 'WRITE: Update product name and description for one language. Requires dry-run, confirmation token, idempotency key, and reason.', 'R3', 'admin_catalog_write', array('admin:catalog_write'), array(
                'product_id' => $this->intSchema(1),
                'language_id' => $this->intSchema(1),
                'name' => $this->stringSchema(255),
                'description' => $this->stringSchema(65535),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('product_id', 'language_id', 'reason'), true, true, true),

            $this->tool('admin.inventory.get', 'Get stock data by product ID, SKU, or model.', 'R2', 'admin_inventory_read', array('admin:inventory_read'), array(
                'product_id' => $this->intSchema(1),
                'sku' => $this->stringSchema(64),
                'model' => $this->stringSchema(64),
            )),
            $this->tool('admin.inventory.search_low_stock', 'Find products at or below a stock threshold.', 'R2', 'admin_inventory_read', array('admin:inventory_read'), array(
                'threshold' => $this->intSchema(0),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            )),
            $this->tool('admin.inventory.adjust', 'WRITE: Adjust stock quantity by delta. Requires dry-run, confirmation token, idempotency key, and reason.', 'R4', 'admin_inventory_write', array('admin:inventory_write'), array(
                'product_id' => $this->intSchema(1),
                'delta' => array('type' => 'integer', 'minimum' => -100000, 'maximum' => 100000),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('product_id', 'delta', 'reason'), true, true, true),

            $this->tool('admin.order.search', 'Search orders with bounded filters. Customer PII is masked by default.', 'R2', 'admin_order_read', array('admin:order_read'), array(
                'order_id' => $this->intSchema(1),
                'customer_email' => array('type' => 'string', 'format' => 'email', 'maxLength' => 96),
                'order_status_id' => $this->intSchema(0),
                'date_from' => $this->stringSchema(32),
                'date_to' => $this->stringSchema(32),
                'store_id' => $this->intSchema(0),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            )),
            $this->tool('admin.order.get', 'Get one order and its products/totals. Customer PII is masked by default.', 'R2', 'admin_order_read', array('admin:order_read'), array(
                'order_id' => $this->intSchema(1),
            ), array('order_id')),
            $this->tool('admin.order.add_history', 'WRITE: Add an internal order history comment without payment actions. Requires dry-run, confirmation token, idempotency key, and reason.', 'R4', 'admin_order_write', array('admin:order_write'), array(
                'order_id' => $this->intSchema(1),
                'order_status_id' => $this->intSchema(1),
                'comment' => $this->stringSchema(1000),
                'notify_customer' => array('type' => 'boolean'),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('order_id', 'order_status_id', 'comment', 'reason'), true, true, true),

            $this->tool('admin.customer.search', 'Search customers with masked PII in broad result sets.', 'R2', 'admin_customer_read', array('admin:customer_read'), array(
                'email' => array('type' => 'string', 'format' => 'email', 'maxLength' => 96),
                'name' => $this->stringSchema(128),
                'status' => array('type' => 'integer', 'enum' => array(0, 1)),
                'limit' => $this->intSchema(1, 100),
                'page' => $this->intSchema(1),
            )),
            $this->tool('admin.customer.get', 'Get one customer profile with PII masked by default.', 'R2', 'admin_customer_read', array('admin:customer_read'), array(
                'customer_id' => $this->intSchema(1),
            ), array('customer_id')),
            $this->tool('admin.customer.orders', 'Get orders for one customer with masked PII.', 'R2', 'admin_customer_read', array('admin:customer_read'), array(
                'customer_id' => $this->intSchema(1),
                'limit' => $this->intSchema(1, 100),
            ), array('customer_id')),
            $this->tool('admin.customer.update_status', 'WRITE: Enable or disable a customer. Requires dry-run, confirmation token, idempotency key, and reason.', 'R4', 'admin_customer_write', array('admin:customer_write'), array(
                'customer_id' => $this->intSchema(1),
                'status' => array('type' => 'integer', 'enum' => array(0, 1)),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('customer_id', 'status', 'reason'), true, true, true),

            $this->tool('admin.coupon.search', 'Search coupons with bounded results.', 'R2', 'marketing', array('admin:marketing_read'), array(
                'code' => $this->stringSchema(64),
                'status' => array('type' => 'integer', 'enum' => array(0, 1)),
                'limit' => $this->intSchema(1, 100),
            )),
            $this->tool('admin.coupon.get', 'Get one coupon detail.', 'R2', 'marketing', array('admin:marketing_read'), array(
                'coupon_id' => $this->intSchema(1),
            ), array('coupon_id')),
            $this->tool('admin.coupon.create', 'WRITE: Create a coupon with explicit limits and dates. Requires dry-run, confirmation token, idempotency key, and reason.', 'R4', 'marketing_write', array('admin:marketing_write'), array(
                'name' => $this->stringSchema(128),
                'code' => $this->stringSchema(64),
                'type' => array('type' => 'string', 'enum' => array('P', 'F')),
                'discount' => $this->numberSchema(0, 1000000),
                'total' => $this->numberSchema(0, 1000000),
                'date_start' => $this->stringSchema(32),
                'date_end' => $this->stringSchema(32),
                'uses_total' => $this->intSchema(0),
                'uses_customer' => $this->intSchema(0),
                'status' => array('type' => 'integer', 'enum' => array(0, 1)),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('name', 'code', 'type', 'discount', 'date_start', 'date_end', 'reason'), true, true, true),
            $this->tool('admin.coupon.disable', 'WRITE: Disable a coupon. Requires dry-run, confirmation token, idempotency key, and reason.', 'R4', 'marketing_write', array('admin:marketing_write'), array(
                'coupon_id' => $this->intSchema(1),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('coupon_id', 'reason'), true, true, true),

            $this->tool('admin.report.sales_summary', 'Return aggregate sales summary for a bounded date range.', 'R2', 'reports', array('admin:report_read'), array(
                'date_from' => $this->stringSchema(32),
                'date_to' => $this->stringSchema(32),
                'store_id' => $this->intSchema(0),
            ), array('date_from', 'date_to')),
            $this->tool('admin.report.orders_by_status', 'Return aggregate order counts and totals by status for a bounded date range.', 'R2', 'reports', array('admin:report_read'), array(
                'date_from' => $this->stringSchema(32),
                'date_to' => $this->stringSchema(32),
                'store_id' => $this->intSchema(0),
            ), array('date_from', 'date_to')),
            $this->tool('admin.report.low_stock_value', 'Return aggregate value for low-stock products.', 'R2', 'reports', array('admin:report_read'), array(
                'threshold' => $this->intSchema(0),
            )),

            $this->tool('admin.setting.get', 'Read allowlisted store settings. Secrets and credentials are never returned.', 'R2', 'settings_read', array('admin:setting_read'), array(
                'keys' => $this->arrayOf($this->stringSchema(96)),
                'store_id' => $this->intSchema(0),
            ), array('keys')),
            $this->tool('diagnostic.status', 'Return non-sensitive extension diagnostics and enabled capability state.', 'R1', 'diagnostics', array('admin:diagnostic_read'), array())
        ), $this->prdTools());
    }

    private function prdTools() {
        $read = array(
            'query' => $this->stringSchema(128),
            'limit' => $this->intSchema(1, 100),
            'page' => $this->intSchema(1),
            'store_id' => $this->intSchema(0),
            'language_id' => $this->intSchema(1),
            'status' => $this->intSchema(0, 1),
            'date_from' => $this->stringSchema(32),
            'date_to' => $this->stringSchema(32),
            'product_id' => $this->intSchema(1),
            'category_id' => $this->intSchema(1),
            'manufacturer_id' => $this->intSchema(1),
            'order_id' => $this->intSchema(1),
            'customer_id' => $this->intSchema(1),
            'coupon_id' => $this->intSchema(1),
            'address_id' => $this->intSchema(1),
            'filename' => $this->stringSchema(255),
            'keys' => $this->arrayOf($this->stringSchema(96)),
        );
        $write = array_merge($read, array(
            'name' => $this->stringSchema(255),
            'code' => $this->stringSchema(64),
            'title' => $this->stringSchema(255),
            'description' => $this->stringSchema(2000),
            'meta_title' => $this->stringSchema(255),
            'price' => $this->numberSchema(0),
            'quantity' => $this->intSchema(0, 1000000),
            'customer_group_id' => $this->intSchema(1),
            'firstname' => $this->stringSchema(64),
            'lastname' => $this->stringSchema(64),
            'email' => $this->stringSchema(96),
            'telephone' => $this->stringSchema(32),
            'address_1' => $this->stringSchema(128),
            'address_2' => $this->stringSchema(128),
            'city' => $this->stringSchema(128),
            'postcode' => $this->stringSchema(16),
            'country_id' => $this->intSchema(1),
            'zone_id' => $this->intSchema(0),
            'order_status_id' => $this->intSchema(1),
            'comment' => $this->stringSchema(1000),
            'notify' => array('type' => 'boolean'),
            'tracking' => $this->stringSchema(128),
            'image' => $this->stringSchema(255),
            'model' => $this->stringSchema(64),
            'sku' => $this->stringSchema(64),
            'meta_description' => $this->stringSchema(255),
            'meta_keyword' => $this->stringSchema(255),
            'tag' => $this->stringSchema(255),
            'stock_status_id' => $this->intSchema(0),
            'manufacturer_id' => $this->intSchema(1),
            'subtract' => $this->intSchema(0, 1),
            'sort_order' => $this->intSchema(0),
            'parent_id' => $this->intSchema(0),
            'bottom' => $this->intSchema(0, 1),
            'rating' => $this->intSchema(1, 5),
            'category_ids' => $this->arrayOf($this->intSchema(1)),
            'images' => $this->arrayOf(array('type' => 'object', 'additionalProperties' => false, 'properties' => array(
                'image' => $this->stringSchema(255),
                'sort_order' => $this->intSchema(0),
            ), 'required' => array('image'))),
            'specials' => $this->arrayOf(array('type' => 'object', 'additionalProperties' => false, 'properties' => array(
                'customer_group_id' => $this->intSchema(1),
                'priority' => $this->intSchema(0),
                'price' => $this->numberSchema(0),
                'date_start' => $this->stringSchema(32),
                'date_end' => $this->stringSchema(32),
            ), 'required' => array('price'))),
            'discounts' => $this->arrayOf(array('type' => 'object', 'additionalProperties' => false, 'properties' => array(
                'customer_group_id' => $this->intSchema(1),
                'quantity' => $this->intSchema(1, 1000000),
                'priority' => $this->intSchema(0),
                'price' => $this->numberSchema(0),
                'date_start' => $this->stringSchema(32),
                'date_end' => $this->stringSchema(32),
            ), 'required' => array('price'))),
            'primary_image' => $this->stringSchema(255),
            'primary' => array('type' => 'boolean'),
            'path' => $this->stringSchema(255),
            'content_base64' => $this->stringSchema(7000000),
            'source_path' => $this->stringSchema(255),
            'target_path' => $this->stringSchema(255),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ));

        $tools = array();
        $add = function ($name, $description, $risk, $capability, $scopes, $properties, $required = array(), $writeTool = false) use (&$tools) {
            $tools[] = $this->tool($name, $description, $risk, $capability, $scopes, $properties, $required, $writeTool, $writeTool, $writeTool);
        };

        foreach (array(
            'catalog.category.get' => 'Get one storefront-visible category.',
            'catalog.product.options' => 'List storefront-safe product options.',
        ) as $name => $description) {
            $add($name, $description, 'R1', 'catalog_read', array('catalog:read'), $read);
        }

        foreach (array(
            'admin.category.search' => 'Search categories including disabled categories.',
            'admin.category.get' => 'Get category record.',
            'admin.attribute.list' => 'List attribute groups and attributes.',
            'admin.option.list' => 'List product options.',
            'admin.manufacturer.list' => 'List manufacturers.',
            'admin.download.list' => 'List download metadata.',
            'admin.review.search' => 'Search reviews including unapproved reviews.',
            'admin.information.search' => 'Search information pages.',
        ) as $name => $description) {
            $add($name, $description, 'R2', 'admin_catalog_read', array('admin:catalog_read'), $read);
        }

        foreach (array(
            'admin.product.create' => 'WRITE: Create product with allowed fields.',
            'admin.product.update' => 'WRITE: Update allowed product fields.',
            'admin.product.update_specials' => 'WRITE: Update product specials.',
            'admin.product.update_discounts' => 'WRITE: Update product discounts.',
            'admin.product.assign_categories' => 'WRITE: Assign product categories.',
            'admin.product.update_images' => 'WRITE: Update product image ordering.',
            'admin.product.attach_image' => 'WRITE: Attach existing image to product.',
            'admin.category.create' => 'WRITE: Create category.',
            'admin.category.update' => 'WRITE: Update category.',
            'admin.category.update_status' => 'WRITE: Enable or disable category.',
            'admin.manufacturer.create' => 'WRITE: Create manufacturer.',
            'admin.manufacturer.update' => 'WRITE: Update manufacturer.',
            'admin.information.create' => 'WRITE: Create information page.',
            'admin.information.update' => 'WRITE: Update information page.',
            'admin.review.approve' => 'WRITE: Approve review.',
            'admin.review.update' => 'WRITE: Update review fields.',
        ) as $name => $description) {
            $add($name, $description, 'R4', 'admin_catalog_write', array('admin:catalog_write'), $write, array('reason'), true);
        }

        foreach (array(
            'admin.product.delete' => 'WRITE R5: Delete product.',
            'admin.category.delete' => 'WRITE R5: Delete category.',
            'admin.information.delete' => 'WRITE R5: Delete information page.',
            'admin.review.delete' => 'WRITE R5: Delete review.',
        ) as $name => $description) {
            $add($name, $description, 'R5', 'admin_catalog_write', array('admin:catalog_write'), $write, array('reason'), true);
        }

        $add('admin.inventory.set_quantity', 'WRITE: Set exact product stock quantity.', 'R4', 'admin_inventory_write', array('admin:inventory_write'), array(
            'product_id' => $this->intSchema(1),
            'quantity' => $this->intSchema(0, 1000000),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('product_id', 'quantity', 'reason'), true);
        $add('admin.inventory.bulk_adjust', 'WRITE: Batch stock adjustments.', 'R4', 'admin_inventory_write', array('admin:inventory_write'), array(
            'items' => $this->arrayOf(array('type' => 'object', 'additionalProperties' => false, 'properties' => array(
                'product_id' => $this->intSchema(1),
                'delta' => $this->intSchema(-1000000, 1000000),
            ), 'required' => array('product_id', 'delta'))),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('items', 'reason'), true);
        $add('admin.inventory.get_movements', 'Get MCP-created stock movement history.', 'R2', 'admin_inventory_read', array('admin:inventory_read'), array(
            'product_id' => $this->intSchema(1),
            'limit' => $this->intSchema(1, 100),
        ));

        foreach (array(
            'admin.order.timeline' => 'Get order status timeline.',
            'admin.order.products' => 'Get order products.',
            'admin.order.totals' => 'Get order totals.',
            'admin.order.customer_summary' => 'Summarise customer order history.',
            'admin.order.find_payment_failures' => 'Find failed or pending payment orders.',
            'admin.order.find_fulfilment_exceptions' => 'Find fulfilment exceptions.',
        ) as $name => $description) {
            $add($name, $description, 'R2', 'admin_order_read', array('admin:order_read'), $read);
        }

        $add('admin.order.update_status', 'WRITE: Change order status.', 'R4', 'admin_order_write', array('admin:order_write'), array(
            'order_id' => $this->intSchema(1),
            'order_status_id' => $this->intSchema(1),
            'comment' => $this->stringSchema(1000),
            'notify' => array('type' => 'boolean'),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('order_id', 'order_status_id', 'reason'), true);
        $add('admin.order.add_note', 'WRITE: Add internal order note where supported.', 'R3', 'admin_order_write', array('admin:order_write'), array(
            'order_id' => $this->intSchema(1),
            'comment' => $this->stringSchema(1000),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('order_id', 'comment', 'reason'), true);
        $add('admin.order.notify_customer', 'WRITE: Notify customer through order history.', 'R4', 'admin_order_write', array('admin:order_write'), array(
            'order_id' => $this->intSchema(1),
            'order_status_id' => $this->intSchema(1),
            'comment' => $this->stringSchema(1000),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('order_id', 'comment', 'reason'), true);
        $add('admin.order.create_return', 'WRITE: Create return request.', 'R4', 'admin_order_write', array('admin:order_write'), array(
            'order_id' => $this->intSchema(1),
            'product_id' => $this->intSchema(1),
            'quantity' => $this->intSchema(1, 1000),
            'return_reason_id' => $this->intSchema(1),
            'opened' => $this->intSchema(0, 1),
            'comment' => $this->stringSchema(1000),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('order_id', 'product_id', 'quantity', 'return_reason_id', 'reason'), true);
        $add('admin.order.update_tracking', 'WRITE: Add shipment tracking metadata.', 'R4', 'admin_order_write', array('admin:order_write'), array(
            'order_id' => $this->intSchema(1),
            'tracking' => $this->stringSchema(128),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('order_id', 'tracking', 'reason'), true);
        $add('admin.order.resend_invoice', 'WRITE: Resend invoice or confirmation where supported.', 'R4', 'admin_order_write', array('admin:order_write'), array(
            'order_id' => $this->intSchema(1),
            'comment' => $this->stringSchema(1000),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('order_id', 'reason'), true);

        foreach (array(
            'admin.customer.addresses' => 'Read customer addresses.',
        ) as $name => $description) {
            $add($name, $description, 'R2', 'admin_customer_read', array('admin:customer_read'), $read);
        }
        foreach (array(
            'admin.customer.update' => 'WRITE: Update allowed customer fields.',
            'admin.customer.update_group' => 'WRITE: Change customer group.',
            'admin.customer.add_address' => 'WRITE: Add customer address.',
            'admin.customer.update_address' => 'WRITE: Update customer address.',
            'admin.customer.delete_address' => 'WRITE: Delete customer address.',
        ) as $name => $description) {
            $add($name, $description, 'R4', 'admin_customer_write', array('admin:customer_write'), $write, array('customer_id', 'reason'), true);
        }
        foreach (array(
            'admin.customer.delete' => 'WRITE R5: Delete customer.',
            'admin.customer.export' => 'WRITE R5: Export customer data.',
        ) as $name => $description) {
            $add($name, $description, 'R5', 'admin_customer_write', array('admin:customer_write'), $write, array('reason'), true);
        }

        foreach (array(
            'admin.voucher.search' => 'Search vouchers.',
            'admin.marketing.campaign_search' => 'Search marketing campaigns.',
            'admin.marketing.tracking_get' => 'Get tracking link metadata.',
        ) as $name => $description) {
            $add($name, $description, 'R2', 'marketing', array('admin:marketing_read'), $read);
        }
        $add('admin.coupon.update', 'WRITE: Update coupon.', 'R4', 'marketing_write', array('admin:marketing_write'), array(
            'coupon_id' => $this->intSchema(1),
            'name' => $this->stringSchema(128),
            'code' => $this->stringSchema(64),
            'type' => array('type' => 'string', 'enum' => array('P', 'F')),
            'discount' => $this->numberSchema(0),
            'total' => $this->numberSchema(0),
            'logged' => $this->intSchema(0, 1),
            'shipping' => $this->intSchema(0, 1),
            'date_start' => $this->stringSchema(32),
            'date_end' => $this->stringSchema(32),
            'uses_total' => $this->intSchema(0),
            'uses_customer' => $this->intSchema(0),
            'status' => $this->intSchema(0, 1),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('coupon_id', 'reason'), true);
        $add('admin.coupon.delete', 'WRITE R5: Delete coupon.', 'R5', 'marketing_write', array('admin:marketing_write'), array(
            'coupon_id' => $this->intSchema(1),
            'reason' => $this->stringSchema(512),
            'dry_run' => array('type' => 'boolean'),
            'idempotency_key' => $this->stringSchema(128),
            'confirmation_token' => $this->stringSchema(128),
        ), array('coupon_id', 'reason'), true);

        foreach (array(
            'admin.report.best_sellers' => 'Best-selling products report.',
            'admin.report.customers' => 'Customer summary report.',
            'admin.report.coupons' => 'Coupon usage report.',
            'admin.report.returns' => 'Returns report.',
            'admin.report.tax' => 'Tax report.',
            'admin.report.low_stock' => 'Low-stock report.',
        ) as $name => $description) {
            $add($name, $description, 'R2', 'reports', array('admin:report_read'), $read);
        }

        foreach (array(
            'admin.setting.get_public' => 'Read public non-sensitive settings.',
            'admin.setting.get_effective' => 'Read effective non-sensitive settings.',
        ) as $name => $description) {
            $add($name, $description, 'R2', 'settings_read', array('admin:setting_read'), $read);
        }
        foreach (array(
            'admin.setting.update' => 'WRITE: Update selected non-sensitive settings.',
            'admin.setting.update_basic' => 'WRITE: Update basic store settings.',
            'admin.setting.update_seo' => 'WRITE: Update SEO settings.',
            'admin.setting.update_localisation' => 'WRITE: Update localisation settings.',
        ) as $name => $description) {
            $add($name, $description, 'R5', 'settings_write', array('admin:setting_write'), array(
                'settings' => array('type' => 'object'),
                'store_id' => $this->intSchema(0),
                'reason' => $this->stringSchema(512),
                'dry_run' => array('type' => 'boolean'),
                'idempotency_key' => $this->stringSchema(128),
                'confirmation_token' => $this->stringSchema(128),
            ), array('settings', 'reason'), true);
        }

        foreach (array(
            'admin.media.search' => 'Search media metadata.',
            'admin.media.get' => 'Get media metadata.',
        ) as $name => $description) {
            $add($name, $description, 'R2', 'admin_catalog_read', array('admin:catalog_read'), $read);
        }
        foreach (array(
            'admin.media.upload' => 'WRITE: Upload image to allowed directory.',
            'admin.media.delete' => 'WRITE R5: Delete media file.',
        ) as $name => $description) {
            $add($name, $description, $name === 'admin.media.delete' ? 'R5' : 'R4', 'admin_catalog_write', array('admin:catalog_write'), $write, array('reason'), true);
        }

        foreach (array(
            'admin.diagnostic.status' => 'Get extension diagnostics.',
            'admin.diagnostic.permissions' => 'Check permission mapping.',
            'admin.diagnostic.route_check' => 'Check MCP route setup.',
            'admin.diagnostic.cache_status' => 'Check OpenCart cache status.',
            'admin.diagnostic.recent_errors' => 'Summarise recent extension errors.',
        ) as $name => $description) {
            $add($name, $description, 'R2', 'diagnostics', array('admin:diagnostic_read'), $read);
        }

        return $tools;
    }

    public function capabilityPacks() {
        return array(
            'protocol_core' => array('label' => 'MCP Protocol Core', 'scopes' => array('mcp:protocol')),
            'catalog_read' => array('label' => 'Catalog Read', 'risk' => 'R1', 'description' => 'Storefront-safe catalog reads.', 'scopes' => array('catalog:read')),
            'catalog_session' => array('label' => 'Catalog Session Actions', 'risk' => 'R3', 'description' => 'Dedicated MCP cart contexts without order creation.', 'scopes' => array('catalog:cart', 'catalog:checkout_quote')),
            'customer_self_service' => array('label' => 'Customer Self-Service', 'risk' => 'R3', 'description' => 'Signed customer-context reads and self-service writes.', 'scopes' => array('customer:self_read', 'customer:self_write')),
            'admin_catalog_read' => array('label' => 'Admin Catalog Read', 'risk' => 'R2', 'description' => 'Merchant catalog reads, including hidden products.', 'scopes' => array('admin:catalog_read')),
            'admin_catalog_write' => array('label' => 'Admin Catalog Write', 'risk' => 'R4', 'description' => 'Controlled product writes requiring dry-run and confirmation.', 'scopes' => array('admin:catalog_write')),
            'admin_inventory_read' => array('label' => 'Admin Inventory Read', 'risk' => 'R2', 'description' => 'Stock and low-stock reads.', 'scopes' => array('admin:inventory_read')),
            'admin_inventory_write' => array('label' => 'Admin Inventory Write', 'risk' => 'R4', 'description' => 'Controlled stock adjustments requiring dry-run and confirmation.', 'scopes' => array('admin:inventory_write')),
            'admin_order_read' => array('label' => 'Admin Orders Read', 'risk' => 'R2', 'description' => 'Order reads with PII masking.', 'scopes' => array('admin:order_read')),
            'admin_order_write' => array('label' => 'Admin Orders Write', 'risk' => 'R4', 'description' => 'Controlled order history writes only; no payment actions.', 'scopes' => array('admin:order_write')),
            'admin_customer_read' => array('label' => 'Admin Customers Read', 'risk' => 'R2', 'description' => 'Customer reads with PII masking.', 'scopes' => array('admin:customer_read')),
            'admin_customer_write' => array('label' => 'Admin Customers Write', 'risk' => 'R4', 'description' => 'Controlled customer status writes.', 'scopes' => array('admin:customer_write')),
            'marketing' => array('label' => 'Promotions and Marketing Read', 'risk' => 'R2', 'description' => 'Coupon and marketing reads.', 'scopes' => array('admin:marketing_read')),
            'marketing_write' => array('label' => 'Promotions and Marketing Write', 'risk' => 'R4', 'description' => 'Controlled coupon writes requiring dry-run and confirmation.', 'scopes' => array('admin:marketing_write')),
            'reports' => array('label' => 'Reports and Analytics', 'risk' => 'R2', 'description' => 'Bounded aggregate reports.', 'scopes' => array('admin:report_read')),
            'settings_read' => array('label' => 'Store Configuration Read', 'risk' => 'R2', 'description' => 'Allowlisted non-secret setting reads.', 'scopes' => array('admin:setting_read')),
            'settings_write' => array('label' => 'Store Configuration Write', 'risk' => 'R5', 'description' => 'Controlled non-sensitive setting writes.', 'scopes' => array('admin:setting_write')),
            'diagnostics' => array('label' => 'Diagnostics', 'risk' => 'R1', 'description' => 'Non-sensitive extension diagnostics.', 'scopes' => array('admin:diagnostic_read')),
        );
    }

    public function toolsByCapability() {
        $groups = array();
        foreach ($this->all() as $tool) {
            if ($tool['capability'] === 'protocol_core') {
                continue;
            }

            if (!isset($groups[$tool['capability']])) {
                $groups[$tool['capability']] = array();
            }

            $groups[$tool['capability']][] = array(
                'name' => $tool['name'],
                'description' => $tool['description'],
                'risk_tier' => $tool['risk_tier'],
                'write' => !empty($tool['write']),
                'dry_run' => !empty($tool['dry_run']),
                'confirmation' => !empty($tool['confirmation']),
                'scopes' => $tool['scopes'],
            );
        }

        return $groups;
    }

    public function selectionHasHighRisk($packs, $allowedTools = array()) {
        $allowedTools = (array)$allowedTools;
        foreach ($this->all() as $tool) {
            if (!in_array($tool['capability'], (array)$packs, true)) {
                continue;
            }

            if ($allowedTools && !in_array($tool['name'], $allowedTools, true)) {
                continue;
            }

            if (!empty($tool['write']) || in_array($tool['risk_tier'], array('R4', 'R5'), true)) {
                return true;
            }
        }

        return false;
    }

    public function scopesForPacks($packs) {
        $all = $this->capabilityPacks();
        $scopes = array('mcp:protocol');
        foreach ((array)$packs as $pack) {
            if (isset($all[$pack])) {
                $scopes = array_merge($scopes, $all[$pack]['scopes']);
            }
        }

        return array_values(array_unique($scopes));
    }

    public function find($name) {
        foreach ($this->all() as $tool) {
            if ($tool['name'] === $name) {
                return $tool;
            }
        }

        return null;
    }

    public function availableForClient($client) {
        $packs = isset($client['capability_packs']) ? (array)$client['capability_packs'] : array();
        $scopes = isset($client['scopes']) ? (array)$client['scopes'] : array();
        $allowedTools = isset($client['allowed_tools']) ? (array)$client['allowed_tools'] : array();
        $tools = array();

        foreach ($this->all() as $tool) {
            if ($tool['capability'] !== 'protocol_core' && !in_array($tool['capability'], $packs, true)) {
                continue;
            }

            if ($allowedTools && !in_array($tool['name'], $allowedTools, true)) {
                continue;
            }

            if (array_diff($tool['scopes'], $scopes)) {
                continue;
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    public function listResponse($client) {
        $items = array();
        foreach ($this->availableForClient($client) as $tool) {
            $items[] = array(
                'name' => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            );
        }

        return $items;
    }

    private function tool($name, $description, $risk, $capability, $scopes, $properties, $required = array(), $write = false, $dryRun = false, $confirmation = false) {
        return array(
            'name' => $name,
            'description' => $description,
            'risk_tier' => $risk,
            'capability' => $capability,
            'scopes' => $scopes,
            'write' => $write,
            'dry_run' => $dryRun,
            'confirmation' => $confirmation,
            'idempotency' => $write,
            'inputSchema' => array(
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => $properties,
                'required' => $required,
            ),
        );
    }

    private function intSchema($minimum = null, $maximum = null) {
        $schema = array('type' => 'integer');
        if ($minimum !== null) {
            $schema['minimum'] = $minimum;
        }
        if ($maximum !== null) {
            $schema['maximum'] = $maximum;
        }
        return $schema;
    }

    private function numberSchema($minimum = null, $maximum = null) {
        $schema = array('type' => 'number');
        if ($minimum !== null) {
            $schema['minimum'] = $minimum;
        }
        if ($maximum !== null) {
            $schema['maximum'] = $maximum;
        }
        return $schema;
    }

    private function stringSchema($maxLength) {
        return array('type' => 'string', 'maxLength' => $maxLength);
    }

    private function arrayOf($schema) {
        return array('type' => 'array', 'items' => $schema);
    }
}
