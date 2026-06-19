# OpenCart MCP Server

OpenCart MCP Server is an installable OpenCart extension that exposes a controlled Model Context Protocol endpoint for merchant-approved AI clients and automation tools.

Project repository:

https://github.com/Custom-Services-Limited/oc-mcp/

Support:

https://support.opencartgreece.gr/

## What It Provides

- Streamable HTTP JSON MCP endpoint.
- Merchant-controlled enablement, client creation, scoped tokens, capability packs, diagnostics, and audit logs.
- Bearer token authentication with hashed token storage and one-time token display.
- Client-level scopes, capability packs, IP allowlists, origin allowlists, rate limiting, revocation, and tool-level permissions.
- Audit logging for MCP requests and tool calls, with admin filters, CSV export, reviewed markers, admin notes, and alert rows for high-risk/security events.
- Catalog, cart, customer self-service, admin catalog, inventory, order, customer, coupon, report, setting-read, and diagnostic tools.
- Controlled admin writes requiring dry-run, confirmation token, idempotency key, reason, before/after diff, and audit trail.

The extension does not expose raw SQL, shell execution, arbitrary PHP execution, arbitrary file read/write, admin user management, extension installation, payment capture/refund/void, or database backup download.

## Requirements

- OpenCart 3.x or OpenCart 4.x.
- PHP version compatible with your OpenCart version.
- HTTPS for production use.
- Admin access with permission to install and configure extensions.

## Install On OpenCart 3.x

1. Download the OpenCart 3 release file named like:

   ```text
   oc_mcp-opencart3-vX.Y.Z.ocmod.zip
   ```

2. Sign in to your OpenCart admin panel.
3. Go to `Extensions > Installer`.
4. Upload the `oc_mcp-opencart3-vX.Y.Z.ocmod.zip` file.
5. Go to `Extensions > Modifications` and click refresh.
6. Go to `Extensions > Extensions`.
7. Choose `Modules` from the extension type dropdown.
8. Find `MCP Server` and click install.
9. Open the `MCP Server` module settings.
10. Keep the server disabled until you have reviewed the security settings.
11. Enable the server, save settings, then create a scoped MCP client.
12. Copy the generated token immediately. It is shown only once.

## Install On OpenCart 4.x

1. Download the OpenCart 4 release file:

   ```text
   mcp.ocmod.zip
   ```

2. Sign in to your OpenCart admin panel.
3. Go to `Extensions > Installer`.
4. Upload `mcp.ocmod.zip`.
5. In the installed extensions list, click install for `OpenCart MCP Server`.
6. Go to `Extensions > Extensions`.
7. Choose `Modules` from the extension type dropdown.
8. Find `MCP Server` and click install if it is not already installed.
9. Open the `MCP Server` module settings.
10. Keep the server disabled until you have reviewed the security settings.
11. Enable the server, save settings, then create a scoped MCP client.
12. Copy the generated token immediately. It is shown only once.

## First Configuration

1. Open `Extensions > Extensions > Modules > MCP Server`.
2. Review the endpoint URL and health URL.
3. Enable audit alerts.
4. Leave advanced tools hidden unless you need write/R4 tools.
5. Enable the MCP server.
6. Create a client with only the capability packs needed by that integration.
7. Review the exact tools and risk tiers before creating the client.
8. For write or R4 tools, enable advanced tools and acknowledge the high-risk warning.
9. Add IP and Origin restrictions where possible.
10. Save the generated token in your MCP client.

Default endpoint:

```text
https://{store-domain}/index.php?route=extension/mcp/server
```

Health endpoint:

```text
https://{store-domain}/index.php?route=extension/mcp/health
```

## Example MCP Request

```bash
curl -X POST "https://store.example/index.php?route=extension/mcp/server" \
  -H "Authorization: Bearer ocmcp_..." \
  -H "Content-Type: application/json" \
  --data '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
```

Tokens in query strings are rejected.

## Security Notes

- The server is disabled after installation.
- No tools are callable while the server is disabled.
- Tokens are stored hashed and can be revoked from the admin module.
- Clients only see tools allowed by their capability packs, scopes, and selected tool permissions.
- Mutating admin tools require dry-run first, then a confirmation token, idempotency key, and reason.
- Audit logs should be reviewed regularly, especially alert rows and denied requests.

## End-to-End Testing

Run deterministic live OpenCart checks with Docker:

```bash
tools/e2e/opencart3.sh
tools/e2e/opencart4.sh
```

The scripts build this extension, install OpenCart with its CLI installer, copy the MCP package into the correct extension location, enable MCP, create a catalog-read client, and call the live MCP health, auth, `initialize`, `tools/list`, and `tools/call` endpoints.

Useful overrides:

```bash
OC_VERSION=3.0.5.0 tools/e2e/opencart3.sh
OC_DOWNLOAD_URL=https://example.test/opencart.zip tools/e2e/opencart4.sh
OC_HTTP_PORT=8080 tools/e2e/opencart3.sh
E2E_ARTIFACT_DIR=/tmp/oc-mcp-artifacts tools/e2e/opencart4.sh
KEEP_E2E=1 tools/e2e/opencart3.sh
```

Failure details are written under `artifacts/e2e/`, including `failure.json`, `summary.md`, HTTP responses, Docker logs, OpenCart logs, and recent MCP audit rows.
