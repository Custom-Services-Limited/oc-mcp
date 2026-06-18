# Architecture

## Layout

- `src/shared/system/library/mcp/` contains the OpenCart-version-neutral MCP runtime.
- `src/opencart3/` contains OpenCart 3 package-specific controllers, model, language, view, and OCMOD metadata.
- `src/opencart4/` contains OpenCart 4 package-specific namespaced controllers, model, language, view, and `install.json`.
- `tools/build.php` assembles both installable package formats.
- `tools/test.php` runs dependency-free lint and runtime checks across PHP 7.x and 8.x.

## Runtime Flow

1. The catalog endpoint receives a Streamable HTTP JSON request.
2. `ProtocolServer` rejects query-string tokens, over-sized bodies, disabled-server state, invalid JSON, missing auth, invalid auth, failed IP/origin policy, and rate-limit violations.
3. `tools/list` filters the registry by client scopes, capability packs, and optional allowed tool list.
4. `tools/call` validates input JSON schema before invoking a tool handler.
5. Every request writes a redacted audit log row with request ID, client, method, tool, risk, status, input hash, and result/error summary.

## Data Model

Installer-created tables:

- `mcp_client`
- `mcp_audit_log`
- `mcp_rate_limit`
- `mcp_idempotency`
- `mcp_confirmation`
- `mcp_inventory_movement`
- `mcp_cart_session`

OpenCart's standard `setting` table stores module-level settings.

## Safety Boundaries

The shared runtime exposes explicit tools only. It does not include generic SQL, shell, PHP execution, arbitrary file operations, payment capture/refund/void, admin user management, extension management, order creation, or broad setting writes.

Admin writes use the same protocol gate: the client must have the right capability pack and scope, OpenCart MCP modify permission must still be present for the creator user group, the first call must be a dry-run, execution must include the dry-run confirmation token and idempotency key, and every request is audited.

High-risk client creation has an additional admin gate. Capability packs expose risk, scopes, descriptions, and exact included tools in the admin UI. Write/R4/R5 selections require advanced tools to be visible, audit alerts enabled, explicit acknowledgement, and per-tool permission selection.

## Audit UX

The admin module exposes audit filters for request ID, client, tool, risk tier, status, entity, IP address, and date range. Filtered logs can be exported as CSV. Individual audit events can be marked reviewed with an admin note.

Audit alert rows are created for high-risk successful tool calls and security failures such as missing scopes, OpenCart permission failures, invalid origin/IP, query-string token use, oversized requests, rate limits, and authentication failures.

## Version Adapters

OpenCart 3 uses classic controller class names such as `ControllerExtensionMcpServer` and module route `extension/module/mcp`.

OpenCart 4 uses namespaced classes under `Opencart\Admin\Controller\Extension\Mcp\Module` and `Opencart\Catalog\Controller\Extension\Mcp`, with admin route `extension/mcp/module/mcp` and catalog route `extension/mcp/server`.
