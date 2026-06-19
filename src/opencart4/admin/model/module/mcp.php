<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */
namespace Opencart\Admin\Model\Extension\Mcp\Module;

class Mcp extends \Opencart\System\Engine\Model {
    private function repository() {
        require_once DIR_EXTENSION . 'mcp/system/library/mcp/bootstrap.php';
        return new \OpenCartMcp\Repository($this->registry);
    }

    public function install() {
        $installer = new \OpenCartMcp\Installer($this->repository());
        $installer->install();
    }

    public function uninstall() {
        $installer = new \OpenCartMcp\Installer($this->repository());
        $installer->uninstall(false);
    }

    public function createClient($data) {
        return $this->repository()->createClient($data);
    }

    public function revokeClient($client_id) {
        $this->repository()->revokeClient($client_id);
    }

    public function listClients() {
        return $this->repository()->listClients();
    }

    public function listAudit($limit = 100, $filters = array()) {
        return $this->repository()->listAudit($limit, $filters);
    }

    public function getAudit($audit_id) {
        return $this->repository()->getAudit($audit_id);
    }

    public function markAuditReviewed($audit_id, $note = '') {
        $this->repository()->markAuditReviewed($audit_id, $note);
    }

    public function auditCsvRows($filters = array(), $limit = 1000) {
        return $this->repository()->auditCsvRows($filters, $limit);
    }

    public function capabilityPacks() {
        require_once DIR_EXTENSION . 'mcp/system/library/mcp/bootstrap.php';
        $registry = new \OpenCartMcp\ToolRegistry();
        return $registry->capabilityPacks();
    }

    public function toolsByCapability() {
        require_once DIR_EXTENSION . 'mcp/system/library/mcp/bootstrap.php';
        $registry = new \OpenCartMcp\ToolRegistry();
        return $registry->toolsByCapability();
    }
}
