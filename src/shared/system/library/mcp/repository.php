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

class Repository {
    private $registry;
    private $db;
    private $config;
    private $prefix;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $this->fromRegistry('db');
        $this->config = $this->fromRegistry('config');
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    }

    public function db() {
        return $this->db;
    }

    public function prefix() {
        return $this->prefix;
    }

    public function install() {
        $this->query("CREATE TABLE IF NOT EXISTS `" . $this->prefix . "mcp_client` (
            `client_id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(128) NOT NULL,
            `client_type` varchar(32) NOT NULL DEFAULT 'automation',
            `status` tinyint(1) NOT NULL DEFAULT 1,
            `environment` varchar(32) NOT NULL DEFAULT 'production',
            `user_group_id` int(11) NOT NULL DEFAULT 0,
            `token_hash` char(64) NOT NULL,
            `token_hint` varchar(32) NOT NULL,
            `scopes` text NOT NULL,
            `capability_packs` text NOT NULL,
            `allowed_tools` text NOT NULL,
            `allowed_store_ids` text NOT NULL,
            `ip_allowlist` text NOT NULL,
            `origin_allowlist` text NOT NULL,
            `rate_limit_per_minute` int(11) NOT NULL DEFAULT 60,
            `expires_at` datetime DEFAULT NULL,
            `created_by_user_id` int(11) NOT NULL DEFAULT 0,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            `last_used_at` datetime DEFAULT NULL,
            PRIMARY KEY (`client_id`),
            UNIQUE KEY `token_hash` (`token_hash`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->query("CREATE TABLE IF NOT EXISTS `" . $this->prefix . "mcp_audit_log` (
            `audit_id` int(11) NOT NULL AUTO_INCREMENT,
            `request_id` varchar(64) NOT NULL,
            `client_id` int(11) NOT NULL DEFAULT 0,
            `store_id` int(11) NOT NULL DEFAULT 0,
            `actor_type` varchar(32) NOT NULL DEFAULT 'client',
            `actor_reference` varchar(128) NOT NULL DEFAULT '',
            `ip_address` varchar(64) NOT NULL DEFAULT '',
            `origin` varchar(255) NOT NULL DEFAULT '',
            `user_agent` varchar(255) NOT NULL DEFAULT '',
            `method` varchar(128) NOT NULL DEFAULT '',
            `tool_name` varchar(128) NOT NULL DEFAULT '',
            `resource_uri` varchar(255) NOT NULL DEFAULT '',
            `risk_tier` varchar(8) NOT NULL DEFAULT '',
            `scope_result` varchar(32) NOT NULL DEFAULT '',
            `permission_result` varchar(32) NOT NULL DEFAULT '',
            `status` varchar(32) NOT NULL DEFAULT '',
            `entity_type` varchar(64) NOT NULL DEFAULT '',
            `entity_id` varchar(64) NOT NULL DEFAULT '',
            `input_hash` char(64) NOT NULL DEFAULT '',
            `input_redacted` mediumtext NOT NULL,
            `output_summary` text NOT NULL,
            `error_code` varchar(64) NOT NULL DEFAULT '',
            `duration_ms` int(11) NOT NULL DEFAULT 0,
            `reviewed` tinyint(1) NOT NULL DEFAULT 0,
            `admin_note` text NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`audit_id`),
            KEY `request_id` (`request_id`),
            KEY `client_id` (`client_id`),
            KEY `tool_name` (`tool_name`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->query("CREATE TABLE IF NOT EXISTS `" . $this->prefix . "mcp_rate_limit` (
            `rate_limit_id` int(11) NOT NULL AUTO_INCREMENT,
            `client_id` int(11) NOT NULL,
            `window_key` varchar(32) NOT NULL,
            `request_count` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`rate_limit_id`),
            UNIQUE KEY `client_window` (`client_id`, `window_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->query("CREATE TABLE IF NOT EXISTS `" . $this->prefix . "mcp_idempotency` (
            `idempotency_id` int(11) NOT NULL AUTO_INCREMENT,
            `client_id` int(11) NOT NULL,
            `tool_name` varchar(128) NOT NULL,
            `idempotency_key` char(64) NOT NULL,
            `input_hash` char(64) NOT NULL,
            `status` varchar(32) NOT NULL,
            `result_hash` char(64) NOT NULL DEFAULT '',
            `result_summary` mediumtext NOT NULL,
            `audit_id` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `expires_at` datetime NOT NULL,
            PRIMARY KEY (`idempotency_id`),
            UNIQUE KEY `client_tool_key` (`client_id`, `tool_name`, `idempotency_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->query("CREATE TABLE IF NOT EXISTS `" . $this->prefix . "mcp_confirmation` (
            `confirmation_id` int(11) NOT NULL AUTO_INCREMENT,
            `client_id` int(11) NOT NULL,
            `tool_name` varchar(128) NOT NULL,
            `entity_type` varchar(64) NOT NULL DEFAULT '',
            `entity_id` varchar(64) NOT NULL DEFAULT '',
            `input_hash` char(64) NOT NULL,
            `token_hash` char(64) NOT NULL,
            `status` varchar(32) NOT NULL DEFAULT 'active',
            `created_at` datetime NOT NULL,
            `expires_at` datetime NOT NULL,
            `used_at` datetime DEFAULT NULL,
            PRIMARY KEY (`confirmation_id`),
            KEY `client_tool` (`client_id`, `tool_name`),
            KEY `token_hash` (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->query("CREATE TABLE IF NOT EXISTS `" . $this->prefix . "mcp_inventory_movement` (
            `movement_id` int(11) NOT NULL AUTO_INCREMENT,
            `client_id` int(11) NOT NULL,
            `product_id` int(11) NOT NULL,
            `previous_quantity` int(11) NOT NULL,
            `new_quantity` int(11) NOT NULL,
            `delta` int(11) NOT NULL,
            `reason` varchar(512) NOT NULL,
            `request_id` varchar(64) NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`movement_id`),
            KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->query("CREATE TABLE IF NOT EXISTS `" . $this->prefix . "mcp_cart_session` (
            `cart_id` varchar(64) NOT NULL,
            `client_id` int(11) NOT NULL,
            `store_id` int(11) NOT NULL DEFAULT 0,
            `currency_code` varchar(3) NOT NULL DEFAULT '',
            `status` varchar(32) NOT NULL DEFAULT 'active',
            `data` mediumtext NOT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `expires_at` datetime NOT NULL,
            PRIMARY KEY (`cart_id`),
            KEY `client_id` (`client_id`),
            KEY `expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    public function dropTables() {
        foreach (array('mcp_cart_session', 'mcp_inventory_movement', 'mcp_confirmation', 'mcp_idempotency', 'mcp_rate_limit', 'mcp_audit_log', 'mcp_client') as $table) {
            $this->query("DROP TABLE IF EXISTS `" . $this->prefix . $table . "`");
        }
    }

    public function disableServer() {
        $this->query("UPDATE `" . $this->prefix . "setting` SET `value` = '0' WHERE `key` = 'module_mcp_status'");
    }

    public function isEnabled() {
        return (string)$this->config('module_mcp_status', '0') === '1';
    }

    public function config($key, $default = null) {
        if ($this->config && method_exists($this->config, 'get')) {
            $value = $this->config->get($key);
            return $value === null ? $default : $value;
        }

        return $default;
    }

    public function secret() {
        $secret = $this->config('config_encryption', '');
        if (!$secret && defined('HTTP_SERVER')) {
            $secret = HTTP_SERVER;
        }
        if (!$secret && defined('DIR_APPLICATION')) {
            $secret = DIR_APPLICATION;
        }
        return $secret ?: 'opencart-mcp-local-secret';
    }

    public function tokenService() {
        return new TokenService($this->secret());
    }

    public function createClient($data) {
        $tokenService = $this->tokenService();
        $token = $tokenService->generateToken();
        $tokenHash = $tokenService->hashToken($token);
        $tokenHint = $tokenService->hint($token);

        $registry = new ToolRegistry();
        $packs = isset($data['capability_packs']) ? (array)$data['capability_packs'] : array('catalog_read', 'diagnostics');
        $allowedTools = isset($data['allowed_tools']) ? (array)$data['allowed_tools'] : array();
        $this->validateClientRiskControls($registry, $packs, $allowedTools, $data);
        $scopes = $registry->scopesForPacks($packs);
        $expiresAt = !empty($data['expires_at']) ? "'" . $this->escape($data['expires_at']) . "'" : "DATE_ADD(NOW(), INTERVAL 90 DAY)";

        $this->query("INSERT INTO `" . $this->prefix . "mcp_client` SET
            `name` = '" . $this->escape($data['name'] ?? 'MCP Client') . "',
            `client_type` = '" . $this->escape($data['client_type'] ?? 'automation') . "',
            `status` = 1,
            `environment` = '" . $this->escape($data['environment'] ?? 'production') . "',
            `user_group_id` = '" . (int)($data['user_group_id'] ?? 0) . "',
            `token_hash` = '" . $this->escape($tokenHash) . "',
            `token_hint` = '" . $this->escape($tokenHint) . "',
            `scopes` = '" . $this->escape(Util::jsonEncode($scopes)) . "',
            `capability_packs` = '" . $this->escape(Util::jsonEncode($packs)) . "',
            `allowed_tools` = '" . $this->escape(Util::jsonEncode($allowedTools)) . "',
            `allowed_store_ids` = '" . $this->escape(Util::jsonEncode($data['allowed_store_ids'] ?? array())) . "',
            `ip_allowlist` = '" . $this->escape($data['ip_allowlist'] ?? '') . "',
            `origin_allowlist` = '" . $this->escape($data['origin_allowlist'] ?? '') . "',
            `rate_limit_per_minute` = '" . (int)($data['rate_limit_per_minute'] ?? 60) . "',
            `expires_at` = " . $expiresAt . ",
            `created_by_user_id` = '" . (int)($data['created_by_user_id'] ?? 0) . "',
            `date_added` = NOW(),
            `date_modified` = NOW()");

        $clientId = $this->lastId();
        $this->auditSystem('client.created', 'MCP client created: ' . $clientId);
        $this->auditSystem('alert.client.created', 'Alert: MCP client created: ' . $clientId);

        return array('client_id' => $clientId, 'token' => $token, 'token_hint' => $tokenHint);
    }

    public function findClientByToken($token) {
        if (!$token) {
            return null;
        }

        $hash = $this->tokenService()->hashToken($token);
        $query = $this->query("SELECT * FROM `" . $this->prefix . "mcp_client`
            WHERE `token_hash` = '" . $this->escape($hash) . "'
            AND `status` = 1
            AND (`expires_at` IS NULL OR `expires_at` > NOW())
            LIMIT 1");

        if (empty($query->row)) {
            return null;
        }

        $this->query("UPDATE `" . $this->prefix . "mcp_client` SET `last_used_at` = NOW() WHERE `client_id` = '" . (int)$query->row['client_id'] . "'");
        return $this->normalizeClient($query->row);
    }

    public function listClients() {
        $query = $this->query("SELECT * FROM `" . $this->prefix . "mcp_client` ORDER BY `client_id` DESC LIMIT 100");
        $clients = array();
        foreach ($query->rows as $row) {
            $clients[] = $this->normalizeClient($row);
        }
        return $clients;
    }

    public function revokeClient($clientId) {
        $this->query("UPDATE `" . $this->prefix . "mcp_client` SET `status` = 0, `date_modified` = NOW() WHERE `client_id` = '" . (int)$clientId . "'");
        $this->auditSystem('client.revoked', 'MCP client revoked: ' . (int)$clientId);
        $this->auditSystem('alert.client.revoked', 'Alert: MCP client revoked: ' . (int)$clientId);
    }

    public function revokeAllClients() {
        $this->query("UPDATE `" . $this->prefix . "mcp_client` SET `status` = 0, `date_modified` = NOW()");
    }

    public function listAudit($limit = 100, $filters = array()) {
        $where = $this->auditWhere($filters);
        $query = $this->query("SELECT * FROM `" . $this->prefix . "mcp_audit_log` " . $where . " ORDER BY `audit_id` DESC LIMIT " . (int)$limit);
        return $query->rows;
    }

    public function getAudit($auditId) {
        $query = $this->query("SELECT * FROM `" . $this->prefix . "mcp_audit_log` WHERE `audit_id` = '" . (int)$auditId . "' LIMIT 1");
        return $query->row ?: null;
    }

    public function markAuditReviewed($auditId, $note = '') {
        $this->query("UPDATE `" . $this->prefix . "mcp_audit_log` SET
            `reviewed` = 1,
            `admin_note` = '" . $this->escape($note) . "'
            WHERE `audit_id` = '" . (int)$auditId . "'");
    }

    public function auditCsvRows($filters = array(), $limit = 1000) {
        return $this->listAudit($limit, $filters);
    }

    public function audit($data) {
        $this->query("INSERT INTO `" . $this->prefix . "mcp_audit_log` SET
            `request_id` = '" . $this->escape($data['request_id'] ?? '') . "',
            `client_id` = '" . (int)($data['client_id'] ?? 0) . "',
            `store_id` = '" . (int)($data['store_id'] ?? 0) . "',
            `actor_type` = '" . $this->escape($data['actor_type'] ?? 'client') . "',
            `actor_reference` = '" . $this->escape($data['actor_reference'] ?? '') . "',
            `ip_address` = '" . $this->escape($data['ip_address'] ?? '') . "',
            `origin` = '" . $this->escape($data['origin'] ?? '') . "',
            `user_agent` = '" . $this->escape($data['user_agent'] ?? '') . "',
            `method` = '" . $this->escape($data['method'] ?? '') . "',
            `tool_name` = '" . $this->escape($data['tool_name'] ?? '') . "',
            `resource_uri` = '" . $this->escape($data['resource_uri'] ?? '') . "',
            `risk_tier` = '" . $this->escape($data['risk_tier'] ?? '') . "',
            `scope_result` = '" . $this->escape($data['scope_result'] ?? '') . "',
            `permission_result` = '" . $this->escape($data['permission_result'] ?? '') . "',
            `status` = '" . $this->escape($data['status'] ?? '') . "',
            `entity_type` = '" . $this->escape($data['entity_type'] ?? '') . "',
            `entity_id` = '" . $this->escape($data['entity_id'] ?? '') . "',
            `input_hash` = '" . $this->escape($data['input_hash'] ?? '') . "',
            `input_redacted` = '" . $this->escape($data['input_redacted'] ?? '') . "',
            `output_summary` = '" . $this->escape($data['output_summary'] ?? '') . "',
            `error_code` = '" . $this->escape($data['error_code'] ?? '') . "',
            `duration_ms` = '" . (int)($data['duration_ms'] ?? 0) . "',
            `reviewed` = 0,
            `admin_note` = '',
            `created_at` = NOW()");

        $auditId = $this->lastId();
        $this->maybeAlert($data);
        return $auditId;
    }

    public function auditSystem($method, $summary) {
        $auditId = $this->audit(array(
            'request_id' => Util::requestId(),
            'actor_type' => 'system',
            'method' => $method,
            'status' => 'ok',
            'output_summary' => $summary,
        ));
        if (strpos((string)$method, 'alert.') === 0) {
            $this->sendAuditAlert($method, $summary);
        }
        return $auditId;
    }

    public function checkRateLimit($client) {
        $clientId = (int)$client['client_id'];
        $limit = max(1, (int)($client['rate_limit_per_minute'] ?? 60));
        $windowKey = gmdate('YmdHi');
        $hashKey = $this->escape($windowKey);

        $this->query("INSERT INTO `" . $this->prefix . "mcp_rate_limit` SET
            `client_id` = '" . $clientId . "',
            `window_key` = '" . $hashKey . "',
            `request_count` = 1,
            `created_at` = NOW()
            ON DUPLICATE KEY UPDATE `request_count` = `request_count` + 1");

        $query = $this->query("SELECT `request_count` FROM `" . $this->prefix . "mcp_rate_limit`
            WHERE `client_id` = '" . $clientId . "' AND `window_key` = '" . $hashKey . "'");

        return empty($query->row['request_count']) || (int)$query->row['request_count'] <= $limit;
    }

    public function getIdempotency($clientId, $tool, $keyHash) {
        $query = $this->query("SELECT * FROM `" . $this->prefix . "mcp_idempotency`
            WHERE `client_id` = '" . (int)$clientId . "'
            AND `tool_name` = '" . $this->escape($tool) . "'
            AND `idempotency_key` = '" . $this->escape($keyHash) . "'
            AND `expires_at` > NOW()
            LIMIT 1");

        return $query->row ?: null;
    }

    public function saveIdempotency($clientId, $tool, $keyHash, $inputHash, $status, $result) {
        $this->query("INSERT INTO `" . $this->prefix . "mcp_idempotency` SET
            `client_id` = '" . (int)$clientId . "',
            `tool_name` = '" . $this->escape($tool) . "',
            `idempotency_key` = '" . $this->escape($keyHash) . "',
            `input_hash` = '" . $this->escape($inputHash) . "',
            `status` = '" . $this->escape($status) . "',
            `result_hash` = '" . $this->escape(Util::hashInput($result)) . "',
            `result_summary` = '" . $this->escape(Util::jsonEncode($result)) . "',
            `created_at` = NOW(),
            `expires_at` = DATE_ADD(NOW(), INTERVAL 24 HOUR)");
    }

    public function createConfirmation($clientId, $tool, $entityType, $entityId, $inputHash) {
        $token = bin2hex(random_bytes(16));
        $hash = hash_hmac('sha256', $token, $this->secret());
        $this->query("INSERT INTO `" . $this->prefix . "mcp_confirmation` SET
            `client_id` = '" . (int)$clientId . "',
            `tool_name` = '" . $this->escape($tool) . "',
            `entity_type` = '" . $this->escape($entityType) . "',
            `entity_id` = '" . $this->escape($entityId) . "',
            `input_hash` = '" . $this->escape($inputHash) . "',
            `token_hash` = '" . $this->escape($hash) . "',
            `status` = 'active',
            `created_at` = NOW(),
            `expires_at` = DATE_ADD(NOW(), INTERVAL 10 MINUTE)");

        return $token;
    }

    public function consumeConfirmation($clientId, $tool, $token, $inputHash) {
        $hash = hash_hmac('sha256', (string)$token, $this->secret());
        $query = $this->query("SELECT * FROM `" . $this->prefix . "mcp_confirmation`
            WHERE `client_id` = '" . (int)$clientId . "'
            AND `tool_name` = '" . $this->escape($tool) . "'
            AND `token_hash` = '" . $this->escape($hash) . "'
            AND `input_hash` = '" . $this->escape($inputHash) . "'
            AND `status` = 'active'
            AND `expires_at` > NOW()
            LIMIT 1");

        if (empty($query->row)) {
            return false;
        }

        $this->query("UPDATE `" . $this->prefix . "mcp_confirmation` SET `status` = 'used', `used_at` = NOW()
            WHERE `confirmation_id` = '" . (int)$query->row['confirmation_id'] . "'");
        return true;
    }

    public function recordInventoryMovement($data) {
        $this->query("INSERT INTO `" . $this->prefix . "mcp_inventory_movement` SET
            `client_id` = '" . (int)($data['client_id'] ?? 0) . "',
            `product_id` = '" . (int)$data['product_id'] . "',
            `previous_quantity` = '" . (int)$data['previous_quantity'] . "',
            `new_quantity` = '" . (int)$data['new_quantity'] . "',
            `delta` = '" . (int)$data['delta'] . "',
            `reason` = '" . $this->escape($data['reason']) . "',
            `request_id` = '" . $this->escape($data['request_id'] ?? '') . "',
            `created_at` = NOW()");
    }

    public function createCart($clientId, $storeId, $currencyCode, $data) {
        $cartId = Util::requestId();
        $this->query("INSERT INTO `" . $this->prefix . "mcp_cart_session` SET
            `cart_id` = '" . $this->escape($cartId) . "',
            `client_id` = '" . (int)$clientId . "',
            `store_id` = '" . (int)$storeId . "',
            `currency_code` = '" . $this->escape($currencyCode) . "',
            `status` = 'active',
            `data` = '" . $this->escape(Util::jsonEncode($data)) . "',
            `created_at` = NOW(),
            `updated_at` = NOW(),
            `expires_at` = DATE_ADD(NOW(), INTERVAL 24 HOUR)");

        return $cartId;
    }

    public function getCart($cartId, $clientId) {
        $query = $this->query("SELECT * FROM `" . $this->prefix . "mcp_cart_session`
            WHERE `cart_id` = '" . $this->escape($cartId) . "'
            AND `client_id` = '" . (int)$clientId . "'
            AND `status` = 'active'
            AND `expires_at` > NOW()
            LIMIT 1");

        if (empty($query->row)) {
            return null;
        }

        $cart = $query->row;
        $cart['data'] = Util::jsonDecodeArray($cart['data'], array('items' => array()));
        return $cart;
    }

    public function saveCart($cartId, $clientId, $data) {
        $this->query("UPDATE `" . $this->prefix . "mcp_cart_session` SET
            `data` = '" . $this->escape(Util::jsonEncode($data)) . "',
            `updated_at` = NOW(),
            `expires_at` = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            WHERE `cart_id` = '" . $this->escape($cartId) . "'
            AND `client_id` = '" . (int)$clientId . "'");
    }

    public function verifyCustomerContext($token) {
        $parts = explode(':', (string)$token);
        if (count($parts) !== 3) {
            return 0;
        }

        $customerId = (int)$parts[0];
        $expires = (int)$parts[1];
        $signature = $parts[2];
        if ($customerId <= 0 || $expires < time()) {
            return 0;
        }

        $expected = hash_hmac('sha256', $customerId . '|' . $expires, $this->secret());
        $valid = function_exists('hash_equals') ? hash_equals($expected, $signature) : $expected === $signature;
        return $valid ? $customerId : 0;
    }

    public function normalizeClient($row) {
        $row['client_id'] = (int)$row['client_id'];
        $row['status'] = (int)$row['status'];
        $row['user_group_id'] = (int)($row['user_group_id'] ?? 0);
        $row['rate_limit_per_minute'] = (int)$row['rate_limit_per_minute'];
        $row['scopes'] = Util::jsonDecodeArray($row['scopes'], array());
        $row['capability_packs'] = Util::jsonDecodeArray($row['capability_packs'], array());
        $row['allowed_tools'] = Util::jsonDecodeArray($row['allowed_tools'], array());
        $row['allowed_store_ids'] = Util::jsonDecodeArray($row['allowed_store_ids'], array());
        return $row;
    }

    public function clientHasOpenCartModifyPermission($client) {
        $groupId = (int)($client['user_group_id'] ?? 0);
        if ($groupId <= 0) {
            return false;
        }

        $query = $this->query("SELECT `permission` FROM `" . $this->prefix . "user_group` WHERE `user_group_id` = '" . $groupId . "' LIMIT 1");
        if (empty($query->row['permission'])) {
            return false;
        }

        $permission = $this->decodePermission($query->row['permission']);
        $modify = isset($permission['modify']) && is_array($permission['modify']) ? $permission['modify'] : array();
        $routes = array('extension/module/mcp', 'extension/mcp/module/mcp');
        foreach ($routes as $route) {
            if (in_array($route, $modify, true)) {
                return true;
            }
        }

        return false;
    }

    private function decodePermission($value) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $decoded = @unserialize($value);
        return is_array($decoded) ? $decoded : array();
    }

    private function auditWhere($filters) {
        $where = array();
        foreach (array('client_id', 'audit_id') as $intField) {
            if (isset($filters[$intField]) && (int)$filters[$intField] > 0) {
                $where[] = "`" . $intField . "` = '" . (int)$filters[$intField] . "'";
            }
        }

        foreach (array('tool_name', 'risk_tier', 'status', 'entity_type', 'entity_id', 'ip_address', 'request_id') as $field) {
            if (!empty($filters[$field])) {
                $where[] = "`" . $field . "` = '" . $this->escape($filters[$field]) . "'";
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = "`created_at` >= '" . $this->escape($filters['date_from']) . "'";
        }

        if (!empty($filters['date_to'])) {
            $where[] = "`created_at` <= '" . $this->escape($filters['date_to']) . "'";
        }

        return $where ? 'WHERE ' . implode(' AND ', $where) : '';
    }

    private function validateClientRiskControls($registry, $packs, $allowedTools, $data) {
        if (!$registry->selectionHasHighRisk($packs, $allowedTools)) {
            return;
        }

        if (empty($data['advanced_tools_visible'])) {
            throw new McpException('ADVANCED_MODE_REQUIRED', 'Advanced tools must be visible before enabling R4/R5 or write tools.');
        }

        if (empty($data['high_risk_ack'])) {
            throw new McpException('HIGH_RISK_ACK_REQUIRED', 'High-risk capability acknowledgement is required.');
        }

        if (empty($data['alerts_enabled'])) {
            throw new McpException('AUDIT_ALERT_REQUIRED', 'Audit alerts must remain enabled for high-risk clients.');
        }
    }

    private function maybeAlert($data) {
        if ((string)$this->config('module_mcp_alerts_enabled', '1') !== '1') {
            return;
        }

        $method = (string)($data['method'] ?? '');
        if (strpos($method, 'alert.') === 0) {
            return;
        }

        $risk = (string)($data['risk_tier'] ?? '');
        $status = (string)($data['status'] ?? '');
        $error = (string)($data['error_code'] ?? '');
        $securityErrors = array(
            'SCOPE_MISSING',
            'OPENCART_PERMISSION_MISSING',
            'AUTHENTICATION_FAILED',
            'RATE_LIMITED',
            'ORIGIN_NOT_ALLOWED',
            'IP_NOT_ALLOWED',
            'REQUEST_TOO_LARGE',
            'TOKEN_IN_QUERY_REJECTED',
        );

        if ($status === 'ok' && in_array($risk, array('R4', 'R5'), true)) {
            $this->auditSystem('alert.high_risk_tool', 'Alert: high-risk MCP tool executed: ' . ($data['tool_name'] ?? ''));
            return;
        }

        if ($error && in_array($error, $securityErrors, true)) {
            $this->auditSystem('alert.security_failure', 'Alert: MCP security event: ' . $error);
        }
    }

    public function query($sql) {
        return $this->db->query($sql);
    }

    public function escape($value) {
        return $this->db ? $this->db->escape((string)$value) : addslashes((string)$value);
    }

    public function lastId() {
        if ($this->db && method_exists($this->db, 'getLastId')) {
            return (int)$this->db->getLastId();
        }
        return 0;
    }

    private function sendAuditAlert($method, $summary) {
        if ((string)$this->config('module_mcp_alerts_enabled', '1') !== '1') {
            return false;
        }

        $to = trim((string)$this->config('module_mcp_alert_email', ''));
        if ($to === '') {
            $to = trim((string)$this->config('config_email', ''));
        }
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $mail = $this->fromRegistry('mail');
        if (!is_object($mail)) {
            return false;
        }

        $from = trim((string)$this->config('config_email', $to));
        $sender = trim((string)$this->config('config_name', 'OpenCart MCP Server'));
        $subject = '[OpenCart MCP] ' . (string)$method;
        $text = (string)$summary . "\n\nStore: " . $sender . "\nTime: " . gmdate('c');

        foreach (array('setTo', 'setFrom', 'setSender', 'setSubject', 'setText', 'send') as $methodName) {
            if (!method_exists($mail, $methodName)) {
                return false;
            }
        }

        try {
            $mail->setTo($to);
            $mail->setFrom($from !== '' ? $from : $to);
            $mail->setSender($sender);
            $mail->setSubject($subject);
            $mail->setText($text);
            $mail->send();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function fromRegistry($key) {
        if ($this->registry && method_exists($this->registry, 'get')) {
            return $this->registry->get($key);
        }
        if (is_array($this->registry) && isset($this->registry[$key])) {
            return $this->registry[$key];
        }
        return null;
    }
}
