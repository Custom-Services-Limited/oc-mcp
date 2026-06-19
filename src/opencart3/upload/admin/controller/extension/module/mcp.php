<?php
/**
 * OpenCart MCP Server
 *
 * Copyright (c) Custom Services Limited.
 * Credits: Custom Services Limited.
 * License: GPL-3.0-or-later.
 * Support: https://support.opencartgreece.gr/
 */
class ControllerExtensionModuleMcp extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/mcp');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/module/mcp');

        if (($this->request->server['REQUEST_METHOD'] ?? 'GET') === 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('module_mcp', array(
                'module_mcp_status' => (int)($this->request->post['module_mcp_status'] ?? 0),
                'module_mcp_display_name' => $this->request->post['module_mcp_display_name'] ?? 'OpenCart MCP Server',
                'module_mcp_max_body_bytes' => (int)($this->request->post['module_mcp_max_body_bytes'] ?? 1048576),
                'module_mcp_alerts_enabled' => (int)($this->request->post['module_mcp_alerts_enabled'] ?? 1),
                'module_mcp_alert_email' => $this->request->post['module_mcp_alert_email'] ?? '',
                'module_mcp_advanced_tools_visible' => (int)($this->request->post['module_mcp_advanced_tools_visible'] ?? 0),
            ));
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/mcp', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $auditFilters = $this->auditFilters();
        $data = $this->baseData();
        $data['module_mcp_status'] = $this->config->get('module_mcp_status');
        $data['module_mcp_display_name'] = $this->config->get('module_mcp_display_name') ?: 'OpenCart MCP Server';
        $data['module_mcp_max_body_bytes'] = $this->config->get('module_mcp_max_body_bytes') ?: 1048576;
        $data['module_mcp_alerts_enabled'] = $this->config->get('module_mcp_alerts_enabled') === null ? 1 : $this->config->get('module_mcp_alerts_enabled');
        $data['module_mcp_alert_email'] = $this->config->get('module_mcp_alert_email') ?: $this->config->get('config_email');
        $data['module_mcp_advanced_tools_visible'] = $this->config->get('module_mcp_advanced_tools_visible');
        $data['clients'] = $this->model_extension_module_mcp->listClients();
        $data['audit_logs'] = $this->model_extension_module_mcp->listAudit(50, $auditFilters);
        $data['audit_filters'] = $auditFilters;
        $data['audit_filter_query'] = $auditFilters ? '&' . http_build_query($auditFilters) : '';
        $data['capability_packs'] = $this->model_extension_module_mcp->capabilityPacks();
        $data['tools_by_capability'] = $this->model_extension_module_mcp->toolsByCapability();
        $data['endpoint_url'] = (defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER) . 'index.php?route=extension/mcp/server';
        $data['health_url'] = (defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER) . 'index.php?route=extension/mcp/health';
        $data['plain_token'] = $this->session->data['mcp_plain_token'] ?? '';
        unset($this->session->data['mcp_plain_token']);

        $this->response->setOutput($this->load->view('extension/module/mcp', $data));
    }

    public function createClient() {
        $this->load->language('extension/module/mcp');
        $this->load->model('extension/module/mcp');
        require_once DIR_SYSTEM . 'library/mcp/bootstrap.php';

        if (($this->request->server['REQUEST_METHOD'] ?? 'GET') === 'POST' && $this->validate()) {
            try {
                $result = $this->model_extension_module_mcp->createClient(array(
                'name' => $this->request->post['name'] ?? 'MCP Client',
                'client_type' => $this->request->post['client_type'] ?? 'automation',
                'environment' => $this->request->post['environment'] ?? 'production',
                'capability_packs' => $this->request->post['capability_packs'] ?? array(),
                'allowed_tools' => $this->request->post['allowed_tools'] ?? array(),
                'allowed_store_ids' => \OpenCartMcp\Util::splitList($this->request->post['allowed_store_ids'] ?? ''),
                'ip_allowlist' => $this->request->post['ip_allowlist'] ?? '',
                'origin_allowlist' => $this->request->post['origin_allowlist'] ?? '',
                'rate_limit_per_minute' => (int)($this->request->post['rate_limit_per_minute'] ?? 60),
                'advanced_tools_visible' => (int)$this->config->get('module_mcp_advanced_tools_visible'),
                'alerts_enabled' => (int)($this->config->get('module_mcp_alerts_enabled') === null ? 1 : $this->config->get('module_mcp_alerts_enabled')),
                'high_risk_ack' => (int)($this->request->post['high_risk_ack'] ?? 0),
                'user_group_id' => $this->user->getGroupId(),
                'created_by_user_id' => method_exists($this->user, 'getId') ? $this->user->getId() : 0,
                ));
                $this->session->data['mcp_plain_token'] = $result['token'];
                $this->session->data['success'] = $this->language->get('text_client_created');
            } catch (\OpenCartMcp\McpException $e) {
                $this->session->data['error_warning'] = $e->getMessage();
            }
        } elseif (!empty($this->error['warning'])) {
            $this->session->data['error_warning'] = $this->error['warning'];
        }

        $this->response->redirect($this->url->link('extension/module/mcp', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function revoke() {
        $this->load->model('extension/module/mcp');
        if ($this->validate()) {
            $this->model_extension_module_mcp->revokeClient((int)($this->request->get['client_id'] ?? 0));
            $this->session->data['success'] = $this->language->get('text_client_revoked');
        }
        $this->response->redirect($this->url->link('extension/module/mcp', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function reviewAudit() {
        $this->load->language('extension/module/mcp');
        $this->load->model('extension/module/mcp');

        if ($this->validate()) {
            $this->model_extension_module_mcp->markAuditReviewed((int)($this->request->post['audit_id'] ?? 0), $this->request->post['admin_note'] ?? '');
            $this->session->data['success'] = $this->language->get('text_audit_reviewed');
        }

        $this->response->redirect($this->url->link('extension/module/mcp', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function auditDetail() {
        $this->load->model('extension/module/mcp');
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($this->model_extension_module_mcp->getAudit((int)($this->request->get['audit_id'] ?? 0)) ?: array()));
    }

    public function exportAudit() {
        $this->load->model('extension/module/mcp');
        $rows = $this->model_extension_module_mcp->auditCsvRows($this->auditFilters(), 1000);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array('audit_id', 'request_id', 'created_at', 'client_id', 'method', 'tool_name', 'risk_tier', 'status', 'entity_type', 'entity_id', 'ip_address', 'error_code', 'duration_ms', 'reviewed', 'admin_note'));
        foreach ($rows as $row) {
            fputcsv($handle, array($row['audit_id'], $row['request_id'], $row['created_at'], $row['client_id'], $row['method'], $row['tool_name'], $row['risk_tier'], $row['status'], $row['entity_type'], $row['entity_id'], $row['ip_address'], $row['error_code'], $row['duration_ms'], $row['reviewed'], $row['admin_note']));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->response->addHeader('Content-Type: text/csv');
        $this->response->addHeader('Content-Disposition: attachment; filename="mcp-audit.csv"');
        $this->response->setOutput($csv);
    }

    public function install() {
        $this->load->model('extension/module/mcp');
        $this->model_extension_module_mcp->install();
        $this->load->model('user/user_group');
        $group_id = $this->user->getGroupId();
        $this->model_user_user_group->addPermission($group_id, 'access', 'extension/module/mcp');
        $this->model_user_user_group->addPermission($group_id, 'modify', 'extension/module/mcp');
    }

    public function uninstall() {
        $this->load->model('extension/module/mcp');
        $this->model_extension_module_mcp->uninstall();
    }

    private function baseData() {
        $data = array();
        foreach (array('heading_title', 'text_home', 'text_extension', 'text_enabled', 'text_disabled', 'entry_status', 'entry_display_name', 'entry_max_body', 'entry_alerts', 'entry_alert_email', 'entry_advanced_tools', 'button_save', 'button_back', 'button_create_client', 'button_revoke') as $key) {
            $data[$key] = $this->language->get($key);
        }
        $token = 'user_token=' . $this->session->data['user_token'];
        $data['user_token'] = $this->session->data['user_token'];
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module', true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/mcp', $token, true)),
        );
        $data['action'] = $this->url->link('extension/module/mcp', $token, true);
        $data['create_client'] = $this->url->link('extension/module/mcp/createClient', $token, true);
        $data['revoke_client'] = $this->url->link('extension/module/mcp/revoke', $token, true);
        $data['audit_export'] = $this->url->link('extension/module/mcp/exportAudit', $token, true);
        $data['audit_detail'] = $this->url->link('extension/module/mcp/auditDetail', $token, true);
        $data['audit_review'] = $this->url->link('extension/module/mcp/reviewAudit', $token, true);
        $data['back'] = $this->url->link('marketplace/extension', $token . '&type=module', true);
        $data['success'] = $this->session->data['success'] ?? '';
        unset($this->session->data['success']);
        $data['error_warning'] = $this->error['warning'] ?? ($this->session->data['error_warning'] ?? '');
        unset($this->session->data['error_warning']);
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        return $data;
    }

    private function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/mcp')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    private function auditFilters() {
        $filters = array();
        foreach (array('client_id', 'tool_name', 'risk_tier', 'status', 'entity_type', 'entity_id', 'ip_address', 'request_id', 'date_from', 'date_to') as $key) {
            if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
                $filters[$key] = $this->request->get[$key];
            }
        }
        return $filters;
    }
}
