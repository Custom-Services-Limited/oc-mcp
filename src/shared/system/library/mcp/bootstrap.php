<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */
// Shared OpenCart MCP runtime bootstrap. Kept require_once based for OC3/OC4.
$mcp_files = array(
    'util.php',
    'schema_validator.php',
    'token_service.php',
    'tool_registry.php',
    'repository.php',
    'tools.php',
    'protocol_server.php',
    'installer.php',
);

foreach ($mcp_files as $mcp_file) {
    require_once __DIR__ . '/' . $mcp_file;
}

