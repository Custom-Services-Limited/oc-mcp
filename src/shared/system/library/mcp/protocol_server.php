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

class ProtocolServer {
    const SERVER_VERSION = '0.1.0';

    private $repository;
    private $registry;
    private $validator;
    private $executor;

    public function __construct($repository) {
        $this->repository = $repository;
        $this->registry = new ToolRegistry();
        $this->validator = new SchemaValidator();
        $this->executor = new ToolExecutor($repository);
    }

    public function handle($rawBody, $server = array(), $headers = array(), $query = array()) {
        $started = microtime(true);
        $requestId = Util::requestId();
        $id = null;
        $method = '';
        $toolName = '';
        $riskTier = '';
        $client = null;
        $status = 'error';
        $errorCode = '';
        $resultSummary = '';
        $payload = array();

        try {
            $this->rejectQueryTokens($query);
            $this->enforceBodySize($rawBody);

            $payload = json_decode((string)$rawBody, true);
            if (!is_array($payload)) {
                throw new McpException('INVALID_JSON', 'Request body must be valid JSON.');
            }

            $id = $payload['id'] ?? null;
            $method = (string)($payload['method'] ?? '');
            if ($method === '') {
                throw new McpException('INVALID_REQUEST', 'JSON-RPC method is required.');
            }

            if (!$this->repository->isEnabled()) {
                throw new McpException('SERVER_DISABLED', 'MCP server is disabled.');
            }

            $client = $this->authenticate($headers);
            $this->enforceClientPolicy($client, $server, $headers);

            if (!$this->repository->checkRateLimit($client)) {
                throw new McpException('RATE_LIMITED', 'Client exceeded configured rate limit.');
            }

            $params = isset($payload['params']) && is_array($payload['params']) ? $payload['params'] : array();
            $result = $this->dispatch($method, $params, $client, $requestId, $toolName, $riskTier);
            $status = 'ok';
            $resultSummary = $this->summarize($result);
            return $this->success($id, $result, $requestId);
        } catch (McpException $e) {
            $errorCode = $e->getMcpCode();
            $resultSummary = $e->getMessage();
            return $this->error($id, $e->getMcpCode(), $e->getMessage(), $requestId, $e->getSafeData());
        } catch (\Throwable $e) {
            $errorCode = 'INTERNAL_SERVER_ERROR';
            $resultSummary = 'Internal server error.';
            return $this->error($id, 'INTERNAL_SERVER_ERROR', 'Internal server error. Reference request_id for support.', $requestId);
        } finally {
            $this->writeAudit($requestId, $client, $server, $headers, $method, $toolName, $riskTier, $status, $errorCode, $payload, $resultSummary, $started);
        }
    }

    public function health() {
        return array(
            'server' => 'OpenCart MCP Server',
            'version' => self::SERVER_VERSION,
            'enabled' => $this->repository->isEnabled(),
            'protocol' => array('2024-11-05', '2025-03-26'),
        );
    }

    private function dispatch($method, $params, $client, $requestId, &$toolName, &$riskTier) {
        switch ($method) {
            case 'initialize':
                return $this->initialize($client);
            case 'ping':
                return array('ok' => true);
            case 'tools/list':
                return array('tools' => $this->registry->listResponse($client));
            case 'tools/call':
                return $this->callTool($params, $client, $requestId, $toolName, $riskTier);
            case 'resources/list':
                return array('resources' => $this->resources($client));
            case 'resources/read':
                return $this->readResource($params, $client);
            case 'prompts/list':
                return array('prompts' => $this->prompts($client));
            case 'prompts/get':
                return $this->getPrompt($params, $client);
            default:
                throw new McpException('UNSUPPORTED_METHOD', 'Unsupported MCP method.');
        }
    }

    private function initialize($client) {
        return array(
            'protocolVersion' => '2025-03-26',
            'serverInfo' => array(
                'name' => 'opencart-mcp-server',
                'version' => self::SERVER_VERSION,
                'displayName' => $this->repository->config('module_mcp_display_name', 'OpenCart MCP Server'),
            ),
            'capabilities' => array(
                'tools' => array('listChanged' => false),
                'resources' => array('subscribe' => false, 'listChanged' => false),
                'prompts' => array('listChanged' => false),
                'logging' => new \stdClass(),
            ),
            'transport' => array(
                'streamable_http' => true,
                'sse' => false,
            ),
            'client' => array(
                'id' => $client['client_id'],
                'name' => $client['name'],
                'capability_packs' => $client['capability_packs'],
            ),
        );
    }

    private function callTool($params, $client, $requestId, &$toolName, &$riskTier) {
        $toolName = (string)($params['name'] ?? '');
        $arguments = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : array();
        $tool = $this->assertToolAllowed($toolName, $client);
        $riskTier = $tool['risk_tier'];

        $errors = $this->validator->validate($tool['inputSchema'], $arguments);
        if ($errors) {
            throw new McpException('INVALID_INPUT_SCHEMA', 'Tool input is invalid.', array('errors' => $errors));
        }

        if (!empty($tool['write']) && !$this->repository->clientHasOpenCartModifyPermission($client)) {
            throw new McpException('OPENCART_PERMISSION_MISSING', 'Client creator user group no longer has MCP modify permission.');
        }

        if (!empty($tool['write'])) {
            return $this->callWriteTool($tool, $arguments, $client, $requestId);
        }

        return array('content' => array(array('type' => 'json', 'json' => $this->executor->execute($toolName, $arguments, $client, $requestId))));
    }

    private function callWriteTool($tool, $arguments, $client, $requestId) {
        $toolName = $tool['name'];
        $semanticInput = $this->semanticInput($arguments);
        $inputHash = Util::hashInput($semanticInput);

        if (!empty($arguments['dry_run'])) {
            $result = $this->executor->execute($toolName, $arguments, $client, $requestId);
            $result['confirmation_token'] = $this->repository->createConfirmation(
                $client['client_id'],
                $toolName,
                $this->entityTypeForTool($toolName),
                $this->entityIdForArguments($arguments),
                $inputHash
            );
            $result['confirmation_expires_in_seconds'] = 600;
            return array('content' => array(array('type' => 'json', 'json' => $result)));
        }

        if (empty($arguments['idempotency_key'])) {
            throw new McpException('IDEMPOTENCY_KEY_REQUIRED', 'Write tools require idempotency_key.');
        }

        $keyHash = hash('sha256', (string)$arguments['idempotency_key']);
        $existing = $this->repository->getIdempotency($client['client_id'], $toolName, $keyHash);
        if ($existing) {
            if ($existing['input_hash'] !== $inputHash) {
                throw new McpException('IDEMPOTENCY_CONFLICT', 'Same idempotency key was used with different input.');
            }

            return array('content' => array(array('type' => 'json', 'json' => Util::jsonDecodeArray($existing['result_summary'], array()))));
        }

        if (!empty($tool['confirmation'])) {
            if (empty($arguments['confirmation_token'])) {
                throw new McpException('CONFIRMATION_REQUIRED', 'Write tool requires confirmation_token from a dry-run.');
            }

            if (!$this->repository->consumeConfirmation($client['client_id'], $toolName, $arguments['confirmation_token'], $inputHash)) {
                throw new McpException('CONFIRMATION_INVALID', 'Confirmation token is invalid, expired, already used, or not bound to this input.');
            }
        }

        $result = $this->executor->execute($toolName, $arguments, $client, $requestId);
        $this->repository->saveIdempotency($client['client_id'], $toolName, $keyHash, $inputHash, 'ok', $result);
        return array('content' => array(array('type' => 'json', 'json' => $result)));
    }

    private function semanticInput($arguments) {
        unset($arguments['dry_run'], $arguments['idempotency_key'], $arguments['confirmation_token']);
        return $arguments;
    }

    private function entityTypeForTool($toolName) {
        if (strpos($toolName, 'product.') !== false || strpos($toolName, 'inventory.') !== false) {
            return 'product';
        }
        if (strpos($toolName, 'order.') !== false) {
            return 'order';
        }
        if (strpos($toolName, 'customer.') !== false) {
            return 'customer';
        }
        if (strpos($toolName, 'coupon.') !== false) {
            return 'coupon';
        }
        return 'entity';
    }

    private function entityIdForArguments($arguments) {
        foreach (array('product_id', 'order_id', 'customer_id', 'coupon_id') as $key) {
            if (isset($arguments[$key])) {
                return (string)$arguments[$key];
            }
        }
        return '';
    }

    private function assertToolAllowed($toolName, $client) {
        if ($toolName === '') {
            throw new McpException('INVALID_REQUEST', 'Tool name is required.');
        }

        $tool = $this->registry->find($toolName);
        if (!$tool) {
            throw new McpException('TOOL_NOT_FOUND', 'Tool not found.');
        }

        foreach ($this->registry->availableForClient($client) as $allowed) {
            if ($allowed['name'] === $toolName) {
                return $tool;
            }
        }

        if (array_diff($tool['scopes'], (array)$client['scopes'])) {
            throw new McpException('SCOPE_MISSING', 'Client is missing required scope for this tool.');
        }

        throw new McpException('TOOL_DISABLED', 'Tool is disabled for this client.');
    }

    private function resources($client) {
        $resources = array();
        $this->addResource($resources, $client, 'catalog:read', 'opencart://store/config', 'Store configuration', 'Safe storefront configuration context.');
        $this->addResource($resources, $client, 'admin:catalog_read', 'opencart://catalog/schema/product', 'Product schema', 'Product fields exposed by MCP tools.');
        $this->addResource($resources, $client, 'catalog:cart', 'opencart://catalog/schema/cart', 'MCP cart schema', 'Dedicated MCP cart context schema.');
        $this->addResource($resources, $client, 'customer:self_read', 'opencart://customer/schema/self-service', 'Customer self-service schema', 'Signed customer-context tool schema.');
        $this->addResource($resources, $client, 'admin:order_read', 'opencart://admin/schema/order', 'Order schema', 'Order fields exposed by admin MCP tools.');
        $this->addResource($resources, $client, 'admin:customer_read', 'opencart://admin/schema/customer', 'Customer schema', 'Masked customer fields exposed by admin MCP tools.');
        $this->addResource($resources, $client, 'admin:marketing_read', 'opencart://admin/schema/coupon', 'Coupon schema', 'Coupon fields exposed by marketing MCP tools.');
        $this->addResource($resources, $client, 'admin:report_read', 'opencart://admin/schema/report', 'Report schema', 'Bounded aggregate reporting schemas.');
        $this->addResource($resources, $client, 'admin:setting_read', 'opencart://admin/schema/setting', 'Setting schema', 'Allowlisted non-secret setting reads.');
        return $resources;
    }

    private function addResource(&$resources, $client, $scope, $uri, $name, $description) {
        if (in_array($scope, (array)$client['scopes'], true)) {
            $resources[] = array(
                'uri' => $uri,
                'name' => $name,
                'description' => $description,
                'mimeType' => 'application/json',
            );
        }
    }

    private function readResource($params, $client) {
        $uri = (string)($params['uri'] ?? '');
        $schemas = $this->resourceSchemas($client);
        if (!isset($schemas[$uri])) {
            throw new McpException('RESOURCE_NOT_FOUND', 'Resource not found or not allowed.');
        }

        if ($uri === 'opencart://store/config') {
            $content = $this->executor->execute('catalog.store.get', array(), $client, Util::requestId());
        } else {
            $content = $schemas[$uri];
        }

        return array('contents' => array(array('uri' => $uri, 'mimeType' => 'application/json', 'text' => Util::jsonEncode($content))));
    }

    private function resourceSchemas($client) {
        $schemas = array();
        if (in_array('catalog:read', (array)$client['scopes'], true)) {
            $schemas['opencart://store/config'] = array();
        }
        if (in_array('admin:catalog_read', (array)$client['scopes'], true)) {
            $schemas['opencart://catalog/schema/product'] = array('fields' => array('product_id', 'model', 'sku', 'quantity', 'price', 'status', 'date_available', 'descriptions', 'stores'));
        }
        if (in_array('catalog:cart', (array)$client['scopes'], true)) {
            $schemas['opencart://catalog/schema/cart'] = array('fields' => array('cart_id', 'store_id', 'currency_code', 'items', 'subtotal', 'total'));
        }
        if (in_array('customer:self_read', (array)$client['scopes'], true)) {
            $schemas['opencart://customer/schema/self-service'] = array('identity' => 'signed customer_context_token', 'fields' => array('customer_id', 'orders', 'order_products'));
        }
        if (in_array('admin:order_read', (array)$client['scopes'], true)) {
            $schemas['opencart://admin/schema/order'] = array('fields' => array('order_id', 'store_id', 'masked_email', 'masked_telephone', 'total', 'currency_code', 'order_status_id', 'products', 'totals', 'history'));
        }
        if (in_array('admin:customer_read', (array)$client['scopes'], true)) {
            $schemas['opencart://admin/schema/customer'] = array('fields' => array('customer_id', 'customer_group_id', 'firstname', 'lastname', 'masked_email', 'masked_telephone', 'status'));
        }
        if (in_array('admin:marketing_read', (array)$client['scopes'], true)) {
            $schemas['opencart://admin/schema/coupon'] = array('fields' => array('coupon_id', 'name', 'code', 'type', 'discount', 'date_start', 'date_end', 'uses_total', 'uses_customer', 'status'));
        }
        if (in_array('admin:report_read', (array)$client['scopes'], true)) {
            $schemas['opencart://admin/schema/report'] = array('reports' => array('sales_summary', 'orders_by_status', 'low_stock_value'), 'bounded_date_range_required' => true);
        }
        if (in_array('admin:setting_read', (array)$client['scopes'], true)) {
            $schemas['opencart://admin/schema/setting'] = array('secret_keys_blocked' => true);
        }
        return $schemas;
    }

    private function prompts($client) {
        $prompts = array();
        if (in_array('admin:catalog_read', (array)$client['scopes'], true)) {
            $prompts[] = array(
                'name' => 'diagnose_invisible_product',
                'description' => 'Guide an agent through checking why a product is not visible in the storefront.',
                'arguments' => array(array('name' => 'product_id', 'required' => true)),
            );
        }
        if (in_array('admin:order_read', (array)$client['scopes'], true)) {
            $prompts[] = array(
                'name' => 'prepare_order_support_summary',
                'description' => 'Summarize order state, products, totals, and latest history for support.',
                'arguments' => array(array('name' => 'order_id', 'required' => true)),
            );
        }
        return $prompts;
    }

    private function getPrompt($params, $client) {
        $name = (string)($params['name'] ?? '');
        if ($name === 'diagnose_invisible_product' && in_array('admin:catalog_read', (array)$client['scopes'], true)) {
            return array(
                'description' => 'Diagnose product storefront visibility.',
                'messages' => array(array(
                    'role' => 'user',
                    'content' => array(
                        'type' => 'text',
                        'text' => 'Use admin.product.get, admin.product.diagnose_visibility, and catalog.product.get to compare status, date_available, store assignment, language description, price, quantity, and stock settings for product {{product_id}}.',
                    ),
                )),
            );
        }

        if ($name === 'prepare_order_support_summary' && in_array('admin:order_read', (array)$client['scopes'], true)) {
            return array(
                'description' => 'Prepare order support summary.',
                'messages' => array(array(
                    'role' => 'user',
                    'content' => array(
                        'type' => 'text',
                        'text' => 'Use admin.order.get for order {{order_id}}. Summarize status, masked customer contact, products, totals, and recent history. Do not infer payment capture/refund actions.',
                    ),
                )),
            );
        }

        throw new McpException('PROMPT_NOT_FOUND', 'Prompt not found or not allowed.');
    }

    private function authenticate($headers) {
        $token = Util::bearerToken($headers);
        if (!$token) {
            throw new McpException('AUTHENTICATION_FAILED', 'Bearer token is required.');
        }

        $client = $this->repository->findClientByToken($token);
        if (!$client) {
            throw new McpException('AUTHENTICATION_FAILED', 'Bearer token is invalid, expired, or revoked.');
        }

        return $client;
    }

    private function enforceClientPolicy($client, $server, $headers) {
        $ip = Util::clientIp($server);
        if (!Util::ipAllowed($ip, $client['ip_allowlist'] ?? '')) {
            throw new McpException('IP_NOT_ALLOWED', 'Client IP is not allowed.');
        }

        $origin = Util::headerValue($headers, 'Origin');
        if (!Util::originAllowed($origin, $client['origin_allowlist'] ?? '')) {
            throw new McpException('ORIGIN_NOT_ALLOWED', 'Origin is not allowed.');
        }
    }

    private function rejectQueryTokens($query) {
        foreach (array('token', 'access_token', 'bearer_token', 'authorization') as $key) {
            if (isset($query[$key])) {
                throw new McpException('TOKEN_IN_QUERY_REJECTED', 'Tokens are not accepted in query strings.');
            }
        }
    }

    private function enforceBodySize($rawBody) {
        $max = (int)$this->repository->config('module_mcp_max_body_bytes', 1048576);
        if (strlen((string)$rawBody) > $max) {
            throw new McpException('REQUEST_TOO_LARGE', 'Request body exceeds configured maximum size.');
        }
    }

    private function success($id, $result, $requestId) {
        return array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result, 'request_id' => $requestId);
    }

    private function error($id, $code, $message, $requestId, $data = array()) {
        $data['code'] = $code;
        $data['request_id'] = $requestId;
        return array(
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => array('code' => -32000, 'message' => $message, 'data' => $data),
        );
    }

    private function summarize($result) {
        $encoded = Util::jsonEncode($result);
        return strlen($encoded) > 1000 ? substr($encoded, 0, 1000) : $encoded;
    }

    private function writeAudit($requestId, $client, $server, $headers, $method, $toolName, $riskTier, $status, $errorCode, $payload, $summary, $started) {
        try {
            $params = isset($payload['params']) && is_array($payload['params']) ? $payload['params'] : array();
            $arguments = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : $params;
            $this->repository->audit(array(
                'request_id' => $requestId,
                'client_id' => $client ? $client['client_id'] : 0,
                'store_id' => (int)($arguments['store_id'] ?? 0),
                'ip_address' => Util::clientIp($server),
                'origin' => Util::headerValue($headers, 'Origin'),
                'user_agent' => $server['HTTP_USER_AGENT'] ?? '',
                'method' => $method,
                'tool_name' => $toolName,
                'risk_tier' => $riskTier,
                'scope_result' => $errorCode === 'SCOPE_MISSING' ? 'denied' : 'allowed',
                'permission_result' => $errorCode === 'OPENCART_PERMISSION_MISSING' ? 'denied' : 'allowed',
                'status' => $status,
                'entity_type' => $toolName ? $this->entityTypeForTool($toolName) : '',
                'entity_id' => $this->entityIdForArguments($arguments),
                'input_hash' => Util::hashInput($payload),
                'input_redacted' => Util::jsonEncode(Util::redact($payload)),
                'output_summary' => $summary,
                'error_code' => $errorCode,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            ));
        } catch (\Throwable $e) {
            // Audit failures must not leak internals or alter the client response.
        }
    }
}

