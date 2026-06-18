# AGENTS.md

## Project Facts
- This repo is an OpenCart extension that exposes a controlled Model Context Protocol server for merchant-approved AI clients.
- Runtime code is PHP with Composer metadata only; keep source compatible with PHP 7.4 through 8.3.
- `README.md`, `docs/architecture.md`, `docs/tools.md`, and `docs/internal_prd.md` are the source docs for scope, architecture, tool surface, and product constraints.

## Architecture
- `src/shared/system/library/mcp/` contains the OpenCart-version-neutral runtime: protocol handling, auth, registry, validation, repository, installer, utilities, and tool execution.
- `src/opencart3/` contains OpenCart 3 controllers, models, language, views, and `install.xml`.
- `src/opencart4/` contains OpenCart 4 controllers, models, language, views, and `install.json`.
- `tools/build.php` copies the shared runtime into both package layouts and writes installable `.ocmod.zip` artifacts under `dist/`.
- `dist/` is generated and ignored; do not edit it directly.

## Commands
- Validate Composer metadata: `composer validate --strict --no-check-publish`
- Fast tests: `php tools/test.php`
- Build packages: `php tools/build.php --version=0.1.0`
- CI equivalent: run Composer validation, `php tools/test.php`, and `php tools/build.php --version=0.0.0-ci`.
- Build requires PHP `ZipArchive`; GitHub Actions tests PHP `7.4`, `8.0`, `8.1`, `8.2`, and `8.3`.

## Conventions
- Keep shared MCP behavior in `src/shared/system/library/mcp/` unless a difference is specific to OpenCart 3 or OpenCart 4.
- When changing admin or catalog integration, keep the OpenCart 3 and OpenCart 4 adapters aligned unless the platform APIs require a documented difference.
- Preserve the explicit-tool security model: no generic SQL, shell/PHP execution, arbitrary file operations, payment capture/refund/void, admin user management, extension management, order creation, or broad store-setting writes.
- Preserve write controls for mutating admin tools: dry-run first, confirmation token, idempotency key, reason, before/after diff, and audit logging.
- Tokens must not be accepted in query strings; token storage must remain hashed with one-time display semantics.
- Customer self-service access must use signed customer context tokens, not raw customer IDs.

## Workflow
- Inspect the relevant docs and source files before editing; avoid duplicating long product or tool lists in this file.
- Prefer targeted validation first, then broader CI-equivalent commands when the change touches shared runtime, packaging, or security behavior.
- Update this file only for durable repo-specific instructions that change future agent behavior.
