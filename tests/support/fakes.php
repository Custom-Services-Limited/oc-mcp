<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */

require_once __DIR__ . '/assertions.php';
mcp_test_bootstrap();

class McpTestConfig {
    private $values;

    public function __construct($values = array()) {
        $this->values = array_merge(array(
            'module_mcp_status' => '1',
            'module_mcp_display_name' => 'Test MCP',
            'module_mcp_max_body_bytes' => 1048576,
        ), $values);
    }

    public function get($key) {
        return array_key_exists($key, $this->values) ? $this->values[$key] : null;
    }
}

class McpTestProtocolRepository {
    public $audits = array();
    public $enabled = true;
    public $rateLimitAllowed = true;
    public $modifyPermission = true;
    public $client;
    private $config;

    public function __construct($client = null, $config = array()) {
        $this->client = $client ?: array(
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
        $this->config = new McpTestConfig($config);
    }

    public function db() {
        return null;
    }

    public function prefix() {
        return 'oc_';
    }

    public function isEnabled() {
        return $this->enabled;
    }

    public function config($key, $default = null) {
        $value = $this->config->get($key);
        return $value === null ? $default : $value;
    }

    public function findClientByToken($token) {
        return $token === 'valid' ? $this->client : null;
    }

    public function checkRateLimit($client) {
        return $this->rateLimitAllowed;
    }

    public function clientHasOpenCartModifyPermission($client) {
        return $this->modifyPermission;
    }

    public function audit($data) {
        $this->audits[] = $data;
        return count($this->audits);
    }

    public function getIdempotency($clientId, $tool, $keyHash) {
        return null;
    }

    public function consumeConfirmation($clientId, $tool, $token, $inputHash) {
        return false;
    }
}

class McpTestQueryResult {
    public $row;
    public $rows;

    public function __construct($row = array(), $rows = array()) {
        $this->row = $row;
        $this->rows = $rows;
    }
}

class McpTestDb {
    public $queries = array();
    public $rows = array();

    public function escape($value) {
        return addslashes((string)$value);
    }

    public function query($sql) {
        $this->queries[] = $sql;
        foreach ($this->rows as $needle => $result) {
            if (strpos($sql, $needle) !== false) {
                return $result;
            }
        }
        return new McpTestQueryResult();
    }
}

class McpTestRegistry {
    private $items;

    public function __construct($items) {
        $this->items = $items;
    }

    public function get($key) {
        return array_key_exists($key, $this->items) ? $this->items[$key] : null;
    }
}
