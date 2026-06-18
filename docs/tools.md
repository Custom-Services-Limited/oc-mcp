# MCP Tools

Tools are filtered per client by capability pack, scope, and optional allowed tool list.

Client creation exposes each capability pack with its risk tier, scopes, description, and included tools. Write/R4 tools require advanced mode and explicit acknowledgement before the client can be created.

## Catalog Read

- `catalog.store.get`
- `catalog.category.tree`
- `catalog.product.search`
- `catalog.product.get`
- `catalog.product.availability`
- `catalog.product.price`
- `catalog.product.related`
- `catalog.product.compare`
- `catalog.manufacturer.list`
- `catalog.manufacturer.get`
- `catalog.review.list`
- `catalog.information.list`
- `catalog.information.get`
- `catalog.filter.options`
- `catalog.search.suggest`

Catalog reads only return storefront-safe data and avoid disabled/future/unassigned products where visibility matters.

## Catalog Session

- `cart.create`
- `cart.get`
- `cart.add_item`
- `cart.update_item`
- `cart.remove_item`
- `cart.clear`
- `cart.apply_coupon`
- `cart.remove_coupon`
- `cart.apply_voucher`
- `cart.apply_reward`
- `cart.quote_shipping`
- `cart.select_shipping`
- `cart.quote_payment`
- `cart.select_payment`
- `cart.estimate_totals`
- `checkout.validate`

Cart tools use dedicated MCP cart sessions. They do not create orders, capture payment, or mutate a browser customer session. Shipping and payment quote tools call repository checkout adapters when available; otherwise they return an explicit adapter-required response without side effects.

## Customer Self-Service

- `customer.self.get_profile`
- `customer.self.update_profile`
- `customer.self.list_addresses`
- `customer.self.add_address`
- `customer.self.update_address`
- `customer.self.delete_address`
- `customer.self.orders`
- `customer.self.order_get`
- `customer.self.downloads`
- `customer.self.returns`
- `customer.self.create_return`
- `customer.self.reward_points`

Customer self-service tools require a signed `customer_context_token`; customer ID alone is rejected.

## Admin Read

- `admin.product.search`
- `admin.product.get`
- `admin.product.diagnose_visibility`
- `admin.product.list_missing_data`
- `admin.inventory.get`
- `admin.inventory.search_low_stock`
- `admin.order.search`
- `admin.order.get`
- `admin.customer.search`
- `admin.customer.get`
- `admin.customer.orders`
- `admin.coupon.search`
- `admin.coupon.get`
- `admin.report.sales_summary`
- `admin.report.orders_by_status`
- `admin.report.low_stock_value`
- `admin.setting.get`
- `admin.setting.get_public`
- `admin.setting.get_effective`
- `diagnostic.status`
- `admin.diagnostic.status`
- `admin.diagnostic.permissions`
- `admin.diagnostic.route_check`
- `admin.diagnostic.cache_status`
- `admin.diagnostic.recent_errors`

Customer and order reads mask email and phone values by default. Setting reads block secret-like keys.

Additional bounded PRD read tools are registered for categories, attributes, options, manufacturers, downloads, reviews, information pages, inventory movements, order timelines/products/totals/exceptions, customer addresses, vouchers, marketing tracking, media metadata, and aggregate reports.

## Controlled Writes

- `admin.inventory.adjust`
- `admin.product.update_status`
- `admin.product.update_price`
- `admin.product.update_seo`
- `admin.product.update_description`
- `admin.order.add_history`
- `admin.customer.update_status`
- `admin.coupon.create`
- `admin.coupon.disable`
- `admin.inventory.set_quantity`
- `admin.inventory.bulk_adjust`
- `admin.product.create`
- `admin.product.update`
- `admin.product.update_specials`
- `admin.product.update_discounts`
- `admin.product.assign_categories`
- `admin.product.update_images`
- `admin.product.attach_image`
- `admin.category.create`
- `admin.category.update`
- `admin.category.update_status`
- `admin.manufacturer.create`
- `admin.manufacturer.update`
- `admin.information.create`
- `admin.information.update`
- `admin.review.approve`
- `admin.review.update`
- `admin.order.update_status`
- `admin.order.add_note`
- `admin.order.notify_customer`
- `admin.order.create_return`
- `admin.order.update_tracking`
- `admin.order.resend_invoice`
- `admin.customer.update`
- `admin.customer.update_group`
- `admin.customer.add_address`
- `admin.customer.update_address`
- `admin.customer.delete_address`
- `admin.coupon.update`
- `admin.setting.update`
- `admin.setting.update_basic`
- `admin.setting.update_seo`
- `admin.setting.update_localisation`
- `admin.media.upload`

R5 advanced-only tools are also registered for destructive or broad operations such as selected deletes and customer export. Payment capture, refund, void, order edit, and sensitive payment/shipping/tax/security setting writes remain excluded.

All controlled admin writes require:

- Required capability pack and scope.
- OpenCart MCP modify permission still present for the creator user group.
- Dry-run request first.
- Confirmation token from dry-run.
- Idempotency key.
- Human reason.
- Audit logging.

Admin customer profile, customer group, and address-book tools execute scoped updates against the selected customer. Password changes, login impersonation, and marketing-consent mutation are not supported.

Inventory exact quantity, bulk adjustment, and movement-history tools execute through the same stock policy and MCP movement log used by single stock adjustments.

Order status, note, customer notification, return, tracking, and resend-request tools execute through scoped order status/history or return records. Payment capture, refund, void, and order item edits remain excluded.

Coupon update and delete tools execute scoped coupon mutations with explicit reason, dry-run support, and date/type validation.

Non-sensitive setting update tools execute allowlisted store/basic/SEO/localisation changes only. Payment, shipping, tax, mail, API, security, token, password, and secret-like keys are rejected server-side.
Product create/update/special/discount/category/image/delete tools execute scoped product-table mutations for allowed catalog fields only.
Category, manufacturer, information, and review moderation tools execute scoped catalog mutations with dry-run diffs and no generic SQL exposure.
Customer export and delete are R5 advanced-only tools; export returns selected customer-owned records, delete removes customer-linked records without touching orders.
Media search/get/upload/delete are constrained to image-library paths under `catalog/`; uploads require base64 image content, allowed MIME/extensions, and a 5MB size limit.

## Audit Operations

The OpenCart admin module includes:

- Audit filters by client, tool, risk, status, date range, entity, IP, and request ID.
- CSV export for the filtered audit set.
- Reviewed markers and admin notes.
- Alert audit rows for high-risk successful execution and security failures.

Dry-run request:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/call",
  "params": {
    "name": "admin.inventory.adjust",
    "arguments": {
      "product_id": 42,
      "delta": 5,
      "reason": "Warehouse count correction",
      "dry_run": true
    }
  }
}
```

Execution request:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "admin.inventory.adjust",
    "arguments": {
      "product_id": 42,
      "delta": 5,
      "reason": "Warehouse count correction",
      "idempotency_key": "unique-operation-key",
      "confirmation_token": "token-from-dry-run"
    }
  }
}
```
