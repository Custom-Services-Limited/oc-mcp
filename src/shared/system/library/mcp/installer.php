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

class Installer {
    private $repository;

    public function __construct($repository) {
        $this->repository = $repository;
    }

    public function install() {
        $this->repository->install();
        $this->repository->auditSystem('install', 'Extension installed; server disabled by default.');
    }

    public function uninstall($dropTables = false) {
        $this->repository->disableServer();
        $this->repository->revokeAllClients();
        $this->repository->auditSystem('uninstall', 'Extension disabled and all MCP clients revoked.');
        if ($dropTables) {
            $this->repository->dropTables();
        }
    }
}

