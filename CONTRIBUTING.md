# Contributing

## Prerequisites

- PHP 7.4 compatible code for runtime changes.
- Composer for metadata validation.
- Docker for OpenCart 3.x and 4.x end-to-end checks.
- PHP `ZipArchive` extension for package builds.

## Workflow

1. Create a feature branch from `main`.
2. Keep shared MCP runtime changes in `src/shared/system/library/mcp/` unless the behavior is OpenCart-version-specific.
3. Keep OpenCart 3 and OpenCart 4 adapters aligned when changing admin or catalog integration.
4. Open a pull request and wait for all checks to pass before merging.

## Required Validation

Run targeted checks before opening or updating a pull request:

```bash
composer validate --strict --no-check-publish
php tools/test.php
php tools/build.php --version=0.0.0-ci
```

Run live OpenCart checks for packaging, installation, MCP auth, tool listing, tool calls, CORS preflight, and audit logging:

```bash
tools/e2e/opencart3.sh
tools/e2e/opencart4.sh
```

The E2E scripts write agent-readable diagnostics under `artifacts/e2e/` when a failure occurs.

## Release Versioning

Releases are created automatically when changes land on `main`.

- The first release tag is `v1.0.0` when no release tags exist.
- Each main-merge release increments the minor version: `1.0.0`, `1.1.0`, ..., `1.9.0`.
- After `.9`, the next release rolls to the next major: `2.0.0`.
- Release assets are generated from `tools/build.php` and are not committed to the repository.

Maintainers can preview version calculation locally:

```bash
php tools/next-version.php --latest=none
php tools/next-version.php --latest=1.9.0
```

## MCP Security Boundaries

Preserve the explicit-tool security model.

- Do not add generic SQL, shell execution, arbitrary PHP execution, arbitrary file read/write, admin user management, extension management, payment capture/refund/void, or broad store-setting writes.
- Mutating admin tools must keep dry-run-first behavior, confirmation token checks, idempotency keys, reasons, before/after diffs, and audit logging.
- Tokens must not be accepted in query strings.
- Token storage must remain hashed with one-time display semantics.
- Customer self-service access must use signed customer context tokens, not raw customer IDs.
