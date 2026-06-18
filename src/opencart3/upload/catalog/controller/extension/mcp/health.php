<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */
class ControllerExtensionMcpHealth extends Controller {
    public function index() {
        require_once DIR_SYSTEM . 'library/mcp/bootstrap.php';

        $repository = new \OpenCartMcp\Repository($this->registry);
        $server = new \OpenCartMcp\ProtocolServer($repository);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(\OpenCartMcp\Util::jsonEncode($server->health()));
    }
}

