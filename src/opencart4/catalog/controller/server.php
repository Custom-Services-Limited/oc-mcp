<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */
namespace Opencart\Catalog\Controller\Extension\Mcp;

class Server extends \Opencart\System\Engine\Controller {
    public function index() {
        $this->response->addHeader('Content-Type: application/json');

        if (($this->request->server['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            $this->response->setOutput('{}');
            return;
        }

        if (($this->request->server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
            $this->response->setOutput(json_encode(array('error' => 'POST required')));
            return;
        }

        require_once DIR_EXTENSION . 'mcp/system/library/mcp/bootstrap.php';

        $repository = new \OpenCartMcp\Repository($this->registry);
        $server = new \OpenCartMcp\ProtocolServer($repository);
        $result = $server->handle(file_get_contents('php://input'), $this->request->server, $this->headers(), $this->request->get);

        $this->response->setOutput(\OpenCartMcp\Util::jsonEncode($result));
    }

    private function headers() {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                return $headers;
            }
        }

        $headers = array();
        foreach ($this->request->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}

