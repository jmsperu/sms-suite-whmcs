<?php
/**
 * SMS Suite - Admin Controller
 *
 * Handles admin area routing and dispatch
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

// Load security helper
require_once __DIR__ . '/../lib/Core/SecurityHelper.php';
use SMSSuite\Core\SecurityHelper;

/**
 * Admin dispatch handler
 */
function sms_suite_admin_dispatch($vars, $action, $lang)
{
    $modulelink = $vars['modulelink'];

    // Build navigation
    sms_suite_admin_nav($modulelink, $action, $lang);

    // Route to appropriate action
    switch ($action) {
        case 'gateways':
            sms_suite_admin_gateways($vars, $lang);
            break;

        case 'gateway_edit':
            sms_suite_admin_gateway_edit($vars, $lang);
            break;

        case 'gateway_countries':
            sms_suite_admin_gateway_countries($vars, $lang);
            break;

        case 'sender_ids':
            sms_suite_admin_sender_ids($vars, $lang);
            break;

        case 'campaigns':
            sms_suite_admin_campaigns($vars, $lang);
            break;

        case 'messages':
            sms_suite_admin_messages($vars, $lang);
            break;

        case 'templates':
            sms_suite_admin_templates($vars, $lang);
            break;

        case 'automation':
            sms_suite_admin_automation($vars, $lang);
            break;

        case 'reports':
            sms_suite_admin_reports($vars, $lang);
            break;

        case 'settings':
            sms_suite_admin_settings($vars, $lang);
            break;

        case 'clients':
            sms_suite_admin_clients($vars, $lang);
            break;

        case 'webhooks':
            sms_suite_admin_webhooks($vars, $lang);
            break;

        case 'diagnostics':
            sms_suite_admin_diagnostics($vars, $lang);
            break;

        case 'send':
            sms_suite_admin_send($vars, $lang);
            break;

        case 'client_settings':
            sms_suite_admin_client_settings($vars, $lang);
            break;

        case 'client_messages':
            sms_suite_admin_client_messages($vars, $lang);
            break;

        case 'send_to_client':
            sms_suite_admin_send_to_client($vars, $lang);
            break;

        case 'notifications':
            sms_suite_admin_notifications($vars, $lang);
            break;

        case 'credit_packages':
            sms_suite_admin_credit_packages($vars, $lang);
            break;

        case 'sender_id_pool':
            sms_suite_admin_sender_id_pool($vars, $lang);
            break;

        case 'sender_id_requests':
            sms_suite_admin_sender_id_requests($vars, $lang);
            break;

        case 'dashboard':
        default:
            sms_suite_admin_dashboard($vars, $lang);
            break;
    }
}

/**
 * Render admin navigation
 */
function sms_suite_admin_nav($modulelink, $currentAction, $lang)
{
    $menuItems = [
        'dashboard' => ['icon' => 'fa-dashboard', 'label' => $lang['menu_dashboard']],
        'send' => ['icon' => 'fa-paper-plane', 'label' => $lang['menu_send_sms']],
        'gateways' => ['icon' => 'fa-server', 'label' => $lang['menu_gateways']],
        'sender_id_pool' => ['icon' => 'fa-id-card', 'label' => 'Sender IDs'],
        'sender_id_requests' => ['icon' => 'fa-inbox', 'label' => 'ID Requests'],
        'credit_packages' => ['icon' => 'fa-credit-card', 'label' => 'SMS Packages'],
        'campaigns' => ['icon' => 'fa-bullhorn', 'label' => $lang['menu_campaigns']],
        'messages' => ['icon' => 'fa-envelope', 'label' => $lang['menu_messages']],
        'templates' => ['icon' => 'fa-file-text', 'label' => $lang['menu_templates']],
        'notifications' => ['icon' => 'fa-bell', 'label' => 'Notifications'],
        'automation' => ['icon' => 'fa-magic', 'label' => $lang['menu_automation']],
        'reports' => ['icon' => 'fa-bar-chart', 'label' => $lang['menu_reports']],
        'clients' => ['icon' => 'fa-users', 'label' => 'Clients'],
        'webhooks' => ['icon' => 'fa-exchange', 'label' => 'Webhooks'],
        'diagnostics' => ['icon' => 'fa-stethoscope', 'label' => 'Diagnostics'],
        'settings' => ['icon' => 'fa-cog', 'label' => $lang['menu_settings']],
    ];

    echo '<ul class="nav nav-tabs admin-tabs" role="tablist">';
    foreach ($menuItems as $action => $item) {
        $active = ($currentAction === $action) ? 'active' : '';
        echo '<li role="presentation" class="' . $active . '">';
        echo '<a href="' . $modulelink . '&action=' . $action . '">';
        echo '<i class="fa ' . $item['icon'] . '"></i> ' . $item['label'];
        echo '</a>';
        echo '</li>';
    }
    echo '</ul>';
    echo '<div class="tab-content admin-tabs-content" style="margin-top: 20px;">';
}

/**
 * Dashboard page
 */
function sms_suite_admin_dashboard($vars, $lang)
{
    $stats = sms_suite_get_stats();

    // Get today's messages
    $todayStart = date('Y-m-d 00:00:00');
    $todayMessages = Capsule::table('mod_sms_messages')
        ->where('created_at', '>=', $todayStart)
        ->count();

    $todayDelivered = Capsule::table('mod_sms_messages')
        ->where('created_at', '>=', $todayStart)
        ->where('status', 'delivered')
        ->count();

    $todayFailed = Capsule::table('mod_sms_messages')
        ->where('created_at', '>=', $todayStart)
        ->whereIn('status', ['failed', 'rejected', 'undelivered'])
        ->count();

    echo '<div class="row">';

    // Stats cards
    $cards = [
        ['value' => $stats['total_messages'], 'label' => $lang['total_messages'], 'color' => 'primary', 'icon' => 'fa-envelope'],
        ['value' => $todayMessages, 'label' => $lang['messages_today'], 'color' => 'info', 'icon' => 'fa-calendar'],
        ['value' => $stats['total_gateways'], 'label' => $lang['total_gateways'], 'color' => 'success', 'icon' => 'fa-server'],
        ['value' => $stats['total_campaigns'], 'label' => $lang['total_campaigns'], 'color' => 'warning', 'icon' => 'fa-bullhorn'],
    ];

    foreach ($cards as $card) {
        echo '<div class="col-md-3">';
        echo '<div class="panel panel-' . $card['color'] . '">';
        echo '<div class="panel-heading">';
        echo '<div class="row">';
        echo '<div class="col-xs-3"><i class="fa ' . $card['icon'] . ' fa-3x"></i></div>';
        echo '<div class="col-xs-9 text-right">';
        echo '<div class="huge">' . number_format($card['value']) . '</div>';
        echo '<div>' . $card['label'] . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>';

    // Recent messages
    echo '<div class="row">';
    echo '<div class="col-md-8">';
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Recent Messages</h3></div>';
    echo '<div class="panel-body">';

    $recentMessages = Capsule::table('mod_sms_messages')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    if (count($recentMessages) > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>To</th><th>Status</th><th>Gateway</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        foreach ($recentMessages as $msg) {
            $statusClass = sms_suite_status_class($msg->status);
            echo '<tr>';
            echo '<td>' . htmlspecialchars($msg->to_number, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td><span class="label label-' . htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(ucfirst($msg->status), ENT_QUOTES, 'UTF-8') . '</span></td>';
            echo '<td>' . (int)($msg->gateway_id ?: 0) . '</td>';
            echo '<td>' . htmlspecialchars($msg->created_at, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p class="text-muted">No messages yet.</p>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Quick actions
    echo '<div class="col-md-4">';
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['quick_links'] . '</h3></div>';
    echo '<div class="panel-body">';
    echo '<a href="' . $vars['modulelink'] . '&action=gateways" class="btn btn-default btn-block"><i class="fa fa-plus"></i> Add Gateway</a>';
    echo '<a href="' . $vars['modulelink'] . '&action=sender_ids" class="btn btn-default btn-block"><i class="fa fa-check"></i> Manage Sender IDs</a>';
    echo '<a href="' . $vars['modulelink'] . '&action=messages" class="btn btn-default btn-block"><i class="fa fa-search"></i> Search Messages</a>';
    echo '<a href="' . $vars['modulelink'] . '&action=reports" class="btn btn-default btn-block"><i class="fa fa-bar-chart"></i> View Reports</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>'; // Close tab-content
}

/**
 * Gateways list page
 */
function sms_suite_admin_gateways($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        sms_suite_admin_handle_gateway_action($_POST);
    }

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title" style="display: inline-block;">' . $lang['gateways'] . '</h3>';
    echo '<a href="' . $modulelink . '&action=gateway_edit&id=new" class="btn btn-success btn-sm pull-right"><i class="fa fa-plus"></i> ' . $lang['gateway_add'] . '</a>';
    echo '</div>';
    echo '<div class="panel-body">';

    $gateways = Capsule::table('mod_sms_gateways')->orderBy('name')->get();

    if (count($gateways) > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr>';
        echo '<th>' . $lang['gateway_name'] . '</th>';
        echo '<th>' . $lang['gateway_type'] . '</th>';
        echo '<th>' . $lang['gateway_channel'] . '</th>';
        echo '<th>' . $lang['gateway_balance'] . '</th>';
        echo '<th>' . $lang['status'] . '</th>';
        echo '<th>' . $lang['actions'] . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($gateways as $gateway) {
            $statusLabel = $gateway->status ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';
            $channelLabel = ucfirst($gateway->channel);

            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($gateway->name) . '</strong></td>';
            echo '<td>' . htmlspecialchars($gateway->type) . '</td>';
            echo '<td>' . $channelLabel . '</td>';
            echo '<td>' . ($gateway->balance !== null ? number_format($gateway->balance, 2) : '-') . '</td>';
            echo '<td>' . $statusLabel . '</td>';
            echo '<td>';
            echo '<a href="' . $modulelink . '&action=gateway_edit&id=' . $gateway->id . '" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></a> ';
            echo '<a href="' . $modulelink . '&action=gateway_countries&id=' . $gateway->id . '" class="btn btn-xs btn-info"><i class="fa fa-globe"></i></a> ';
            echo '<button class="btn btn-xs btn-warning" onclick="testGateway(' . $gateway->id . ')"><i class="fa fa-bolt"></i></button> ';
            echo '<button class="btn btn-xs btn-danger" onclick="deleteGateway(' . $gateway->id . ')"><i class="fa fa-trash"></i></button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<div class="alert alert-info">No gateways configured. <a href="' . $modulelink . '&action=gateway_edit&id=new">Add your first gateway</a></div>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>'; // Close tab-content

    // JavaScript for gateway actions
    echo '<script>
    function deleteGateway(id) {
        if (confirm("' . $lang['confirm_delete'] . '")) {
            var form = document.createElement("form");
            form.method = "POST";
            form.innerHTML = "<input type=\"hidden\" name=\"action\" value=\"delete\"><input type=\"hidden\" name=\"gateway_id\" value=\"" + id + "\">";
            document.body.appendChild(form);
            form.submit();
        }
    }
    function testGateway(id) {
        if (confirm("Test gateway connection and check balance?")) {
            var form = document.createElement("form");
            form.method = "POST";
            form.innerHTML = "<input type=\"hidden\" name=\"action\" value=\"test\"><input type=\"hidden\" name=\"gateway_id\" value=\"" + id + "\">";
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>';

    // Handle test action result
    if (isset($_POST['action']) && $_POST['action'] === 'test' && isset($_POST['gateway_id'])) {
        $testResult = sms_suite_admin_test_gateway((int)$_POST['gateway_id']);
        // Use json_encode for safe JavaScript string escaping
        echo '<script>alert(' . json_encode($testResult, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ');</script>';
    }
}

/**
 * Gateway edit page
 */
function sms_suite_admin_gateway_edit($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $id = isset($_GET['id']) ? $_GET['id'] : 'new';
    $isNew = ($id === 'new');
    $gateway = null;
    $message = '';
    $messageType = '';

    // Load gateway drivers
    require_once __DIR__ . '/../lib/Gateways/GatewayInterface.php';
    require_once __DIR__ . '/../lib/Gateways/AbstractGateway.php';
    require_once __DIR__ . '/../lib/Gateways/GenericHttpGateway.php';
    require_once __DIR__ . '/../lib/Gateways/TwilioGateway.php';
    require_once __DIR__ . '/../lib/Gateways/PlivoGateway.php';
    require_once __DIR__ . '/../lib/Gateways/VonageGateway.php';
    require_once __DIR__ . '/../lib/Gateways/InfobipGateway.php';
    require_once __DIR__ . '/../lib/Gateways/GatewayRegistry.php';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gateway'])) {
        $result = sms_suite_admin_save_gateway($_POST, $isNew ? null : (int)$id);
        if ($result['success']) {
            $message = $lang['gateway_saved'];
            $messageType = 'success';
            if ($isNew) {
                header('Location: ' . $modulelink . '&action=gateway_edit&id=' . $result['id'] . '&saved=1');
                exit;
            }
            $id = $result['id'];
            $isNew = false;
        } else {
            $message = $result['error'];
            $messageType = 'danger';
        }
    }

    // Check for saved message
    if (isset($_GET['saved'])) {
        $message = $lang['gateway_saved'];
        $messageType = 'success';
    }

    // Load existing gateway
    if (!$isNew) {
        $gateway = Capsule::table('mod_sms_gateways')->where('id', (int)$id)->first();
        if (!$gateway) {
            echo '<div class="alert alert-danger">Gateway not found</div>';
            echo '<a href="' . $modulelink . '&action=gateways" class="btn btn-default">' . $lang['back'] . '</a>';
            echo '</div>';
            return;
        }
    }

    // Get available drivers
    $drivers = [
        'generic_http' => ['name' => 'Generic HTTP Gateway', 'channels' => ['sms', 'whatsapp']],
        'twilio' => ['name' => 'Twilio', 'channels' => ['sms', 'whatsapp', 'mms']],
        'plivo' => ['name' => 'Plivo', 'channels' => ['sms', 'mms']],
        'vonage' => ['name' => 'Vonage (Nexmo)', 'channels' => ['sms']],
        'infobip' => ['name' => 'Infobip', 'channels' => ['sms', 'whatsapp']],
    ];

    // Current values
    $currentType = $gateway ? $gateway->type : ($_POST['type'] ?? 'generic_http');
    $currentName = $gateway ? $gateway->name : ($_POST['name'] ?? '');
    $currentChannel = $gateway ? $gateway->channel : ($_POST['channel'] ?? 'sms');
    $currentStatus = $gateway ? $gateway->status : ($_POST['status'] ?? 1);

    // Decrypt credentials
    $credentials = [];
    if ($gateway && !empty($gateway->credentials)) {
        $decrypted = sms_suite_decrypt($gateway->credentials);
        $credentials = json_decode($decrypted, true) ?: [];
    }

    // Parse settings
    $settings = [];
    if ($gateway && !empty($gateway->settings)) {
        $settings = json_decode($gateway->settings, true) ?: [];
    }

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title">' . ($isNew ? $lang['gateway_add'] : $lang['gateway_edit'] . ': ' . htmlspecialchars($gateway->name)) . '</h3>';
    echo '</div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    echo '<form method="post" class="form-horizontal">';
    echo '<input type="hidden" name="save_gateway" value="1">';

    // Basic settings
    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">' . $lang['gateway_name'] . ' *</label>';
    echo '<div class="col-sm-6">';
    echo '<input type="text" name="name" class="form-control" value="' . htmlspecialchars($currentName) . '" required>';
    echo '</div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">' . $lang['gateway_type'] . ' *</label>';
    echo '<div class="col-sm-6">';
    echo '<select name="type" id="gateway_type" class="form-control" onchange="loadGatewayFields()">';
    foreach ($drivers as $type => $info) {
        $selected = ($type === $currentType) ? 'selected' : '';
        echo '<option value="' . $type . '" ' . $selected . '>' . $info['name'] . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">' . $lang['gateway_channel'] . '</label>';
    echo '<div class="col-sm-6">';
    echo '<select name="channel" class="form-control">';
    $channels = ['sms' => $lang['channel_sms'], 'whatsapp' => $lang['channel_whatsapp'], 'both' => $lang['channel_both']];
    foreach ($channels as $val => $label) {
        $selected = ($val === $currentChannel) ? 'selected' : '';
        echo '<option value="' . $val . '" ' . $selected . '>' . $label . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">' . $lang['status'] . '</label>';
    echo '<div class="col-sm-6">';
    echo '<select name="status" class="form-control">';
    echo '<option value="1" ' . ($currentStatus ? 'selected' : '') . '>' . $lang['active'] . '</option>';
    echo '<option value="0" ' . (!$currentStatus ? 'selected' : '') . '>' . $lang['inactive'] . '</option>';
    echo '</select>';
    echo '</div>';
    echo '</div>';

    // Credentials section
    echo '<hr><h4>' . $lang['gateway_credentials'] . '</h4>';
    echo '<div id="credential_fields">';

    // Render fields based on driver type
    sms_suite_render_gateway_fields($currentType, $credentials, $settings);

    echo '</div>';

    // Rate limiting
    echo '<hr><h4>Rate Limiting</h4>';
    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">Quota Value</label>';
    echo '<div class="col-sm-3">';
    $quotaValue = $gateway ? $gateway->quota_value : ($settings['quota_value'] ?? 100);
    echo '<input type="number" name="quota_value" class="form-control" value="' . (int)$quotaValue . '">';
    echo '</div>';
    echo '<div class="col-sm-3">';
    $quotaUnit = $gateway ? $gateway->quota_unit : ($settings['quota_unit'] ?? 'minute');
    echo '<select name="quota_unit" class="form-control">';
    echo '<option value="second" ' . ($quotaUnit === 'second' ? 'selected' : '') . '>per second</option>';
    echo '<option value="minute" ' . ($quotaUnit === 'minute' ? 'selected' : '') . '>per minute</option>';
    echo '<option value="hour" ' . ($quotaUnit === 'hour' ? 'selected' : '') . '>per hour</option>';
    echo '</select>';
    echo '</div>';
    echo '</div>';

    // Webhook token
    echo '<hr><h4>Webhook Settings</h4>';
    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">Webhook Token</label>';
    echo '<div class="col-sm-6">';
    $webhookToken = $gateway ? $gateway->webhook_token : bin2hex(random_bytes(16));
    echo '<input type="text" name="webhook_token" class="form-control" value="' . htmlspecialchars($webhookToken) . '">';
    echo '<p class="help-block">Use this token to authenticate incoming webhooks</p>';
    echo '</div>';
    echo '</div>';

    if (!$isNew) {
        $webhookUrl = rtrim($GLOBALS['CONFIG']['SystemURL'], '/') . '/index.php?m=sms_suite&webhook=dlr&gateway=' . $id . '&token=' . $webhookToken;
        echo '<div class="form-group">';
        echo '<label class="col-sm-3 control-label">Webhook URL</label>';
        echo '<div class="col-sm-9">';
        echo '<input type="text" class="form-control" value="' . htmlspecialchars($webhookUrl) . '" readonly onclick="this.select()">';
        echo '</div>';
        echo '</div>';
    }

    // Buttons
    echo '<hr>';
    echo '<div class="form-group">';
    echo '<div class="col-sm-offset-3 col-sm-6">';
    echo '<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> ' . $lang['save'] . '</button> ';
    echo '<a href="' . $modulelink . '&action=gateways" class="btn btn-default">' . $lang['cancel'] . '</a>';
    echo '</div>';
    echo '</div>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // JavaScript for dynamic fields
    echo '<script>
    var gatewayFields = ' . json_encode(sms_suite_get_all_gateway_fields()) . ';

    function loadGatewayFields() {
        var type = document.getElementById("gateway_type").value;
        var container = document.getElementById("credential_fields");
        var fields = gatewayFields[type] || [];
        var html = "";

        fields.forEach(function(field) {
            html += "<div class=\"form-group\">";
            html += "<label class=\"col-sm-3 control-label\">" + field.label + (field.required ? " *" : "") + "</label>";
            html += "<div class=\"col-sm-6\">";

            if (field.type === "select") {
                html += "<select name=\"credentials[" + field.name + "]\" class=\"form-control\">";
                for (var key in field.options) {
                    html += "<option value=\"" + key + "\">" + field.options[key] + "</option>";
                }
                html += "</select>";
            } else if (field.type === "textarea") {
                html += "<textarea name=\"credentials[" + field.name + "]\" class=\"form-control\" rows=\"4\"></textarea>";
            } else {
                var inputType = field.type === "password" ? "password" : "text";
                html += "<input type=\"" + inputType + "\" name=\"credentials[" + field.name + "]\" class=\"form-control\" placeholder=\"" + (field.placeholder || "") + "\">";
            }

            if (field.description) {
                html += "<p class=\"help-block\">" + field.description + "</p>";
            }
            html += "</div></div>";
        });

        container.innerHTML = html;
    }
    </script>';
}

/**
 * Render gateway credential fields
 */
function sms_suite_render_gateway_fields($type, $credentials, $settings)
{
    $allFields = sms_suite_get_all_gateway_fields();
    $fields = $allFields[$type] ?? [];

    foreach ($fields as $field) {
        $name = $field['name'];
        $value = $credentials[$name] ?? $settings[$name] ?? $field['default'] ?? '';
        $required = !empty($field['required']) ? 'required' : '';

        echo '<div class="form-group">';
        echo '<label class="col-sm-3 control-label">' . $field['label'] . (!empty($field['required']) ? ' *' : '') . '</label>';
        echo '<div class="col-sm-6">';

        if ($field['type'] === 'select') {
            echo '<select name="credentials[' . $name . ']" class="form-control">';
            foreach ($field['options'] as $optVal => $optLabel) {
                $selected = ($value === $optVal) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($optVal) . '" ' . $selected . '>' . htmlspecialchars($optLabel) . '</option>';
            }
            echo '</select>';
        } elseif ($field['type'] === 'textarea') {
            echo '<textarea name="credentials[' . $name . ']" class="form-control" rows="4">' . htmlspecialchars($value) . '</textarea>';
        } elseif ($field['type'] === 'password') {
            echo '<input type="password" name="credentials[' . $name . ']" class="form-control" value="' . htmlspecialchars($value) . '" ' . $required . ' placeholder="' . ($value ? '********' : '') . '">';
        } else {
            echo '<input type="text" name="credentials[' . $name . ']" class="form-control" value="' . htmlspecialchars($value) . '" ' . $required . ' placeholder="' . htmlspecialchars($field['placeholder'] ?? '') . '">';
        }

        if (!empty($field['description'])) {
            echo '<p class="help-block">' . $field['description'] . '</p>';
        }

        echo '</div>';
        echo '</div>';
    }
}

/**
 * Get all gateway fields configuration
 */
function sms_suite_get_all_gateway_fields()
{
    return [
        'generic_http' => [
            ['name' => 'api_endpoint', 'label' => 'API Endpoint URL', 'type' => 'text', 'required' => true, 'placeholder' => 'https://api.provider.com/sms/send'],
            ['name' => 'http_method', 'label' => 'HTTP Method', 'type' => 'select', 'options' => ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT'], 'default' => 'POST'],
            ['name' => 'auth_type', 'label' => 'Authentication Type', 'type' => 'select', 'options' => ['none' => 'None', 'basic' => 'Basic Auth', 'bearer' => 'Bearer Token', 'api_key_header' => 'API Key (Header)', 'api_key_query' => 'API Key (Query Param)'], 'default' => 'none'],
            ['name' => 'auth_username', 'label' => 'Username / API Key', 'type' => 'text'],
            ['name' => 'auth_password', 'label' => 'Password / Secret', 'type' => 'password'],
            ['name' => 'content_type', 'label' => 'Content Type', 'type' => 'select', 'options' => ['application/json' => 'JSON', 'application/x-www-form-urlencoded' => 'Form Encoded'], 'default' => 'application/json'],
            ['name' => 'param_to', 'label' => 'Recipient Parameter', 'type' => 'text', 'default' => 'to'],
            ['name' => 'param_from', 'label' => 'Sender Parameter', 'type' => 'text', 'default' => 'from'],
            ['name' => 'param_message', 'label' => 'Message Parameter', 'type' => 'text', 'default' => 'message'],
            ['name' => 'extra_params', 'label' => 'Extra Parameters', 'type' => 'textarea', 'description' => 'One per line: param=value'],
            ['name' => 'response_message_id_path', 'label' => 'Message ID Path', 'type' => 'text', 'default' => 'message_id', 'description' => 'JSON path to message ID in response'],
            ['name' => 'success_codes', 'label' => 'Success HTTP Codes', 'type' => 'text', 'default' => '200,201,202'],
            ['name' => 'success_keyword', 'label' => 'Success Keyword', 'type' => 'text', 'description' => 'Text that must appear in response'],
        ],
        'twilio' => [
            ['name' => 'account_sid', 'label' => 'Account SID', 'type' => 'text', 'required' => true],
            ['name' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password', 'required' => true],
            ['name' => 'messaging_service_sid', 'label' => 'Messaging Service SID', 'type' => 'text', 'description' => 'Optional: Use a Messaging Service'],
        ],
        'plivo' => [
            ['name' => 'auth_id', 'label' => 'Auth ID', 'type' => 'text', 'required' => true],
            ['name' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password', 'required' => true],
        ],
        'vonage' => [
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true],
            ['name' => 'api_secret', 'label' => 'API Secret', 'type' => 'password', 'required' => true],
        ],
        'infobip' => [
            ['name' => 'base_url', 'label' => 'Base URL', 'type' => 'text', 'default' => 'https://api.infobip.com'],
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
        ],
    ];
}

/**
 * Save gateway to database
 */
function sms_suite_admin_save_gateway($data, $existingId = null)
{
    try {
        $name = trim($data['name'] ?? '');
        $type = $data['type'] ?? 'generic_http';
        $channel = $data['channel'] ?? 'sms';
        $status = (int)($data['status'] ?? 1);
        $quotaValue = (int)($data['quota_value'] ?? 100);
        $quotaUnit = $data['quota_unit'] ?? 'minute';
        $webhookToken = $data['webhook_token'] ?? bin2hex(random_bytes(16));

        if (empty($name)) {
            return ['success' => false, 'error' => 'Gateway name is required'];
        }

        // Encrypt credentials
        $credentials = $data['credentials'] ?? [];
        $credentialsJson = json_encode($credentials);
        $encryptedCredentials = sms_suite_encrypt($credentialsJson);

        // Settings (non-sensitive)
        $settings = [
            'quota_value' => $quotaValue,
            'quota_unit' => $quotaUnit,
        ];

        $record = [
            'name' => $name,
            'type' => $type,
            'channel' => $channel,
            'status' => $status,
            'credentials' => $encryptedCredentials,
            'settings' => json_encode($settings),
            'quota_value' => $quotaValue,
            'quota_unit' => $quotaUnit,
            'webhook_token' => $webhookToken,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existingId) {
            Capsule::table('mod_sms_gateways')->where('id', $existingId)->update($record);
            $id = $existingId;
        } else {
            $record['created_at'] = date('Y-m-d H:i:s');
            $id = Capsule::table('mod_sms_gateways')->insertGetId($record);
        }

        return ['success' => true, 'id' => $id];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Gateway countries pricing
 */
function sms_suite_admin_gateway_countries($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $gatewayId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $message = '';
    $messageType = '';

    $gateway = Capsule::table('mod_sms_gateways')->where('id', $gatewayId)->first();
    if (!$gateway) {
        echo '<div class="alert alert-danger">Gateway not found</div>';
        echo '<a href="' . $modulelink . '&action=gateways" class="btn btn-default">' . $lang['back'] . '</a>';
        echo '</div>';
        return;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_country'])) {
            $countryCode = trim($_POST['country_code'] ?? '');
            $countryName = trim($_POST['country_name'] ?? '');
            $smsRate = (float)($_POST['sms_rate'] ?? 0);
            $whatsappRate = (float)($_POST['whatsapp_rate'] ?? 0);

            if (!empty($countryCode)) {
                try {
                    Capsule::table('mod_sms_gateway_countries')->insert([
                        'gateway_id' => $gatewayId,
                        'country_code' => $countryCode,
                        'country_name' => $countryName,
                        'sms_rate' => $smsRate,
                        'whatsapp_rate' => $whatsappRate,
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $message = 'Country added successfully';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        } elseif (isset($_POST['delete_country'])) {
            $countryId = (int)$_POST['country_id'];
            Capsule::table('mod_sms_gateway_countries')->where('id', $countryId)->delete();
            $message = 'Country removed';
            $messageType = 'success';
        } elseif (isset($_POST['update_rates'])) {
            foreach ($_POST['rates'] as $countryId => $rates) {
                Capsule::table('mod_sms_gateway_countries')
                    ->where('id', (int)$countryId)
                    ->update([
                        'sms_rate' => (float)$rates['sms'],
                        'whatsapp_rate' => (float)$rates['whatsapp'],
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
            $message = 'Rates updated successfully';
            $messageType = 'success';
        }
    }

    // Get countries for this gateway
    $countries = Capsule::table('mod_sms_gateway_countries')
        ->where('gateway_id', $gatewayId)
        ->orderBy('country_name')
        ->get();

    // Get available countries
    $availableCountries = Capsule::table('mod_sms_countries')
        ->where('status', 1)
        ->orderBy('name')
        ->get();

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title">' . $lang['gateway_countries'] . ': ' . htmlspecialchars($gateway->name) . '</h3>';
    echo '</div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    // Add country form
    echo '<form method="post" class="form-inline" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="add_country" value="1">';
    echo '<select name="country_code" class="form-control" style="width: 200px;">';
    echo '<option value="">Select Country...</option>';
    foreach ($availableCountries as $country) {
        echo '<option value="' . $country->phone_code . '" data-name="' . htmlspecialchars($country->name) . '">' . htmlspecialchars($country->name) . ' (+' . $country->phone_code . ')</option>';
    }
    echo '</select> ';
    echo '<input type="hidden" name="country_name" id="country_name_input">';
    echo '<input type="number" name="sms_rate" class="form-control" placeholder="SMS Rate" step="0.0001" style="width: 120px;"> ';
    echo '<input type="number" name="whatsapp_rate" class="form-control" placeholder="WhatsApp Rate" step="0.0001" style="width: 120px;"> ';
    echo '<button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add</button>';
    echo '</form>';

    if (count($countries) > 0) {
        echo '<form method="post">';
        echo '<input type="hidden" name="update_rates" value="1">';
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>Country</th><th>Code</th><th>SMS Rate</th><th>WhatsApp Rate</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        foreach ($countries as $country) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($country->country_name) . '</td>';
            echo '<td>+' . htmlspecialchars($country->country_code) . '</td>';
            echo '<td><input type="number" name="rates[' . $country->id . '][sms]" class="form-control input-sm" value="' . $country->sms_rate . '" step="0.0001" style="width: 100px;"></td>';
            echo '<td><input type="number" name="rates[' . $country->id . '][whatsapp]" class="form-control input-sm" value="' . $country->whatsapp_rate . '" step="0.0001" style="width: 100px;"></td>';
            echo '<td>';
            echo '<button type="button" class="btn btn-xs btn-danger" onclick="deleteCountry(' . $country->id . ')"><i class="fa fa-trash"></i></button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Rates</button> ';
        echo '</form>';
    } else {
        echo '<p class="text-muted">No countries configured for this gateway.</p>';
    }

    echo '<hr>';
    echo '<a href="' . $modulelink . '&action=gateways" class="btn btn-default">' . $lang['back'] . '</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // JavaScript
    echo '<script>
    document.querySelector("select[name=country_code]").addEventListener("change", function() {
        var option = this.options[this.selectedIndex];
        document.getElementById("country_name_input").value = option.getAttribute("data-name") || "";
    });

    function deleteCountry(id) {
        if (confirm("Remove this country?")) {
            var form = document.createElement("form");
            form.method = "POST";
            form.innerHTML = "<input type=\"hidden\" name=\"delete_country\" value=\"1\"><input type=\"hidden\" name=\"country_id\" value=\"" + id + "\">";
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>';
}

/**
 * Sender IDs page (stub)
 */
function sms_suite_admin_sender_ids($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['sender_ids'] . '</h3></div>';
    echo '<div class="panel-body">';

    $senderIds = Capsule::table('mod_sms_sender_ids')
        ->join('tblclients', 'mod_sms_sender_ids.client_id', '=', 'tblclients.id')
        ->select('mod_sms_sender_ids.*', 'tblclients.firstname', 'tblclients.lastname', 'tblclients.companyname')
        ->orderBy('mod_sms_sender_ids.created_at', 'desc')
        ->get();

    if (count($senderIds) > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>Sender ID</th><th>Client</th><th>Type</th><th>Status</th><th>Validity</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($senderIds as $sid) {
            $clientName = $sid->companyname ?: ($sid->firstname . ' ' . $sid->lastname);
            $statusClass = sms_suite_status_class($sid->status);
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($sid->sender_id) . '</strong></td>';
            echo '<td>' . htmlspecialchars($clientName) . '</td>';
            echo '<td>' . ucfirst($sid->type) . '</td>';
            echo '<td><span class="label label-' . $statusClass . '">' . ucfirst($sid->status) . '</span></td>';
            echo '<td>' . ($sid->validity_date ?: 'N/A') . '</td>';
            echo '<td>';
            if ($sid->status === 'pending') {
                echo '<button class="btn btn-xs btn-success">Approve</button> ';
                echo '<button class="btn btn-xs btn-danger">Reject</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p class="text-muted">No sender ID requests.</p>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Campaigns page (stub)
 */
function sms_suite_admin_campaigns($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['campaigns'] . '</h3></div>';
    echo '<div class="panel-body">';
    echo '<div class="alert alert-info">Campaign management will be implemented in Slice 8.</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Messages page (stub)
 */
function sms_suite_admin_messages($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['messages'] . '</h3></div>';
    echo '<div class="panel-body">';

    $messages = Capsule::table('mod_sms_messages')
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();

    if (count($messages) > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>To</th><th>From</th><th>Message</th><th>Status</th><th>Segments</th><th>Cost</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        foreach ($messages as $msg) {
            $statusClass = sms_suite_status_class($msg->status);
            $msgPreview = strlen($msg->message) > 50 ? substr($msg->message, 0, 50) . '...' : $msg->message;
            echo '<tr>';
            echo '<td>' . htmlspecialchars($msg->to_number) . '</td>';
            echo '<td>' . htmlspecialchars($msg->sender_id) . '</td>';
            echo '<td>' . htmlspecialchars($msgPreview) . '</td>';
            echo '<td><span class="label label-' . $statusClass . '">' . ucfirst($msg->status) . '</span></td>';
            echo '<td>' . $msg->segments . '</td>';
            echo '<td>' . number_format($msg->cost, 4) . '</td>';
            echo '<td>' . $msg->created_at . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p class="text-muted">No messages yet.</p>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Templates page (stub)
 */
function sms_suite_admin_templates($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['templates'] . '</h3></div>';
    echo '<div class="panel-body">';
    echo '<div class="alert alert-info">Template management will be implemented in Slice 11.</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Automation page (stub)
 */
function sms_suite_admin_automation($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['automation'] . '</h3></div>';
    echo '<div class="panel-body">';
    echo '<div class="alert alert-info">Automation triggers will be implemented in Slice 11.</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Reports page (stub)
 */
function sms_suite_admin_reports($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['reports'] . '</h3></div>';
    echo '<div class="panel-body">';
    echo '<div class="alert alert-info">Reports will be implemented in Slice 10.</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Settings page (stub)
 */
function sms_suite_admin_settings($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['settings'] . '</h3></div>';
    echo '<div class="panel-body">';
    echo '<p>Module settings are configured in Setup > Addon Modules > SMS Suite.</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Clients page (stub)
 */
function sms_suite_admin_clients($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Client Settings</h3></div>';
    echo '<div class="panel-body">';
    echo '<div class="alert alert-info">Per-client settings will be implemented in Slice 6.</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Webhooks inbox page (stub)
 */
function sms_suite_admin_webhooks($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Webhook Inbox</h3></div>';
    echo '<div class="panel-body">';

    $webhooks = Capsule::table('mod_sms_webhooks_inbox')
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();

    if (count($webhooks) > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>ID</th><th>Gateway</th><th>Processed</th><th>Error</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        foreach ($webhooks as $wh) {
            $processedLabel = $wh->processed ? '<span class="label label-success">Yes</span>' : '<span class="label label-warning">No</span>';
            echo '<tr>';
            echo '<td>' . $wh->id . '</td>';
            echo '<td>' . htmlspecialchars($wh->gateway_type) . '</td>';
            echo '<td>' . $processedLabel . '</td>';
            echo '<td>' . htmlspecialchars($wh->error ?: '-') . '</td>';
            echo '<td>' . $wh->created_at . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p class="text-muted">No webhooks received yet.</p>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Diagnostics page
 */
function sms_suite_admin_diagnostics($vars, $lang)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Diagnostics</h3></div>';
    echo '<div class="panel-body">';

    // PHP version
    echo '<h4>Environment</h4>';
    echo '<table class="table table-bordered">';
    echo '<tr><td width="30%">PHP Version</td><td>' . PHP_VERSION . '</td></tr>';
    echo '<tr><td>Module Version</td><td>' . SMS_SUITE_VERSION . '</td></tr>';
    echo '<tr><td>WHMCS Version</td><td>' . $GLOBALS['CONFIG']['Version'] . '</td></tr>';
    echo '</table>';

    // Cron status
    echo '<h4>Cron Status</h4>';
    $cronTasks = Capsule::table('mod_sms_cron_status')->get();

    if (count($cronTasks) > 0) {
        echo '<table class="table table-bordered">';
        echo '<thead><tr><th>Task</th><th>Last Run</th><th>Running</th></tr></thead>';
        echo '<tbody>';
        foreach ($cronTasks as $task) {
            $runningLabel = $task->is_running ? '<span class="label label-warning">Yes</span>' : '<span class="label label-default">No</span>';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($task->task) . '</td>';
            echo '<td>' . ($task->last_run ?: 'Never') . '</td>';
            echo '<td>' . $runningLabel . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    // Queue status
    echo '<h4>Queue Status</h4>';
    $queuedMessages = Capsule::table('mod_sms_messages')->where('status', 'queued')->count();
    $pendingCampaigns = Capsule::table('mod_sms_campaigns')->whereIn('status', ['scheduled', 'queued'])->count();

    echo '<table class="table table-bordered">';
    echo '<tr><td width="30%">Queued Messages</td><td>' . $queuedMessages . '</td></tr>';
    echo '<tr><td>Pending Campaigns</td><td>' . $pendingCampaigns . '</td></tr>';
    echo '</table>';

    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Admin send/broadcast page
 */
function sms_suite_admin_send($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;
    $results = [];

    // Load core classes
    require_once __DIR__ . '/../lib/Core/SegmentCounter.php';
    require_once __DIR__ . '/../lib/Core/MessageService.php';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
        $recipients = trim($_POST['recipients'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $channel = $_POST['channel'] ?? 'sms';
        $senderId = $_POST['sender_id'] ?? null;
        $gatewayId = !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null;
        $sendMode = $_POST['send_mode'] ?? 'immediate';

        // Validate
        if (empty($recipients)) {
            $error = $lang['error_recipient_required'];
        } elseif (empty($message)) {
            $error = $lang['error_message_required'];
        } else {
            // Parse recipients (one per line or comma-separated)
            $recipientList = preg_split('/[\n,]+/', $recipients);
            $recipientList = array_map('trim', $recipientList);
            $recipientList = array_filter($recipientList);

            $sent = 0;
            $failed = 0;
            $errors = [];

            foreach ($recipientList as $to) {
                // Admin broadcasts use clientId = 0
                $result = \SMSSuite\Core\MessageService::send(0, $to, $message, [
                    'channel' => $channel,
                    'sender_id' => $senderId,
                    'gateway_id' => $gatewayId,
                    'send_now' => ($sendMode === 'immediate'),
                ]);

                if ($result['success']) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = $to . ': ' . ($result['error'] ?? 'Unknown error');
                }
            }

            if ($sent > 0) {
                $success = sprintf('%d message(s) sent successfully.', $sent);
                if ($failed > 0) {
                    $success .= sprintf(' %d failed.', $failed);
                }
            } else {
                $error = 'All messages failed to send.';
            }

            $results = ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
        }
    }

    // Get gateways
    $gateways = Capsule::table('mod_sms_gateways')
        ->where('status', 1)
        ->orderBy('name')
        ->get();

    // Get global sender IDs (admin-level)
    $senderIds = Capsule::table('mod_sms_sender_ids')
        ->whereNull('client_id')
        ->where('status', 'active')
        ->orderBy('sender_id')
        ->get();

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['menu_send_sms'] . '</h3></div>';
    echo '<div class="panel-body">';

    // Success/Error messages
    if ($success) {
        echo '<div class="alert alert-success"><strong>' . $lang['success'] . '!</strong> ' . htmlspecialchars($success) . '</div>';
        if (!empty($results['errors'])) {
            echo '<div class="alert alert-warning"><strong>Errors:</strong><ul>';
            foreach (array_slice($results['errors'], 0, 10) as $err) {
                echo '<li>' . htmlspecialchars($err) . '</li>';
            }
            if (count($results['errors']) > 10) {
                echo '<li>... and ' . (count($results['errors']) - 10) . ' more</li>';
            }
            echo '</ul></div>';
        }
    }

    if ($error) {
        echo '<div class="alert alert-danger"><strong>' . $lang['error'] . '!</strong> ' . htmlspecialchars($error) . '</div>';
    }

    echo '<form method="post" id="broadcastForm">';
    echo '<input type="hidden" name="send_broadcast" value="1">';

    echo '<div class="row">';
    echo '<div class="col-md-8">';

    // Channel
    echo '<div class="form-group">';
    echo '<label>' . $lang['channel'] . '</label>';
    echo '<select name="channel" id="channel" class="form-control">';
    echo '<option value="sms">SMS</option>';
    echo '<option value="whatsapp">WhatsApp</option>';
    echo '</select>';
    echo '</div>';

    // Recipients
    echo '<div class="form-group">';
    echo '<label>' . $lang['recipient'] . 's <span class="text-danger">*</span></label>';
    echo '<textarea name="recipients" class="form-control" rows="4" placeholder="' . $lang['recipient_help'] . '&#10;One number per line or comma-separated" required>' . htmlspecialchars($_POST['recipients'] ?? '') . '</textarea>';
    echo '</div>';

    // Gateway
    echo '<div class="form-group">';
    echo '<label>' . $lang['gateway'] . '</label>';
    echo '<select name="gateway_id" class="form-control">';
    echo '<option value="">' . $lang['default_gateway'] . '</option>';
    foreach ($gateways as $gw) {
        $selected = (isset($_POST['gateway_id']) && $_POST['gateway_id'] == $gw->id) ? 'selected' : '';
        echo '<option value="' . $gw->id . '" ' . $selected . '>' . htmlspecialchars($gw->name) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Sender ID
    echo '<div class="form-group">';
    echo '<label>' . $lang['sender_id'] . '</label>';
    if (count($senderIds) > 0) {
        echo '<select name="sender_id" class="form-control">';
        echo '<option value="">' . $lang['default_sender'] . '</option>';
        foreach ($senderIds as $sid) {
            $selected = (isset($_POST['sender_id']) && $_POST['sender_id'] == $sid->sender_id) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($sid->sender_id) . '" ' . $selected . '>' . htmlspecialchars($sid->sender_id) . '</option>';
        }
        echo '</select>';
    } else {
        echo '<input type="text" name="sender_id" class="form-control" value="' . htmlspecialchars($_POST['sender_id'] ?? '') . '" placeholder="Enter sender ID">';
    }
    echo '</div>';

    // Message
    echo '<div class="form-group">';
    echo '<label>' . $lang['message'] . ' <span class="text-danger">*</span></label>';
    echo '<textarea name="message" id="message" class="form-control" rows="5" placeholder="' . $lang['message_placeholder'] . '" required>' . htmlspecialchars($_POST['message'] ?? '') . '</textarea>';
    echo '</div>';

    // Segment counter
    echo '<div class="well well-sm" id="segmentInfo">';
    echo '<div class="row text-center">';
    echo '<div class="col-xs-3"><strong id="charCount">0</strong><br><small>' . $lang['characters'] . '</small></div>';
    echo '<div class="col-xs-3"><strong id="segmentCount">0</strong><br><small>' . $lang['segments'] . '</small></div>';
    echo '<div class="col-xs-3"><strong id="encoding">GSM-7</strong><br><small>' . $lang['encoding'] . '</small></div>';
    echo '<div class="col-xs-3"><strong id="remaining">160</strong><br><small>' . $lang['remaining'] . '</small></div>';
    echo '</div>';
    echo '</div>';

    // Send mode
    echo '<div class="form-group">';
    echo '<label>Send Mode</label>';
    echo '<select name="send_mode" class="form-control">';
    echo '<option value="immediate">Send Immediately</option>';
    echo '<option value="queue">Queue for Processing</option>';
    echo '</select>';
    echo '</div>';

    // Submit
    echo '<div class="form-group">';
    echo '<button type="submit" class="btn btn-primary btn-lg"><i class="fa fa-paper-plane"></i> ' . $lang['send_message'] . '</button>';
    echo '</div>';

    echo '</div>'; // col-md-8

    // Sidebar
    echo '<div class="col-md-4">';
    echo '<div class="panel panel-info">';
    echo '<div class="panel-heading"><h4 class="panel-title">' . $lang['encoding_guide'] . '</h4></div>';
    echo '<div class="panel-body">';
    echo '<p><strong>GSM-7:</strong></p>';
    echo '<ul class="small"><li>160 ' . $lang['chars_single'] . '</li><li>153 ' . $lang['chars_per_segment'] . '</li></ul>';
    echo '<p><strong>UCS-2 (Unicode):</strong></p>';
    echo '<ul class="small"><li>70 ' . $lang['chars_single'] . '</li><li>67 ' . $lang['chars_per_segment'] . '</li></ul>';
    echo '</div>';
    echo '</div>';
    echo '</div>'; // col-md-4

    echo '</div>'; // row

    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Segment counter JavaScript
    echo '<script>
(function() {
    var gsm7Chars = [10, 12, 13, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126, 161, 163, 164, 165, 167, 191, 196, 197, 198, 199, 201, 209, 214, 216, 220, 223, 224, 228, 229, 230, 232, 233, 236, 241, 242, 246, 248, 249, 252, 915, 916, 920, 923, 926, 928, 931, 934, 936, 937, 8364];
    var gsm7ExtChars = [12, 91, 92, 93, 94, 123, 124, 125, 126, 8364];
    var allGsmChars = gsm7Chars.concat(gsm7ExtChars);

    function getCodePoints(str) {
        var points = [];
        for (var i = 0; i < str.length; i++) {
            var code = str.codePointAt(i);
            points.push(code);
            if (code > 0xFFFF) i++;
        }
        return points;
    }

    function detectEncoding(codePoints) {
        var hasExtended = false;
        for (var i = 0; i < codePoints.length; i++) {
            if (allGsmChars.indexOf(codePoints[i]) === -1) return "ucs2";
            if (gsm7ExtChars.indexOf(codePoints[i]) !== -1) hasExtended = true;
        }
        return hasExtended ? "gsm7ex" : "gsm7";
    }

    function countSegments(message, channel) {
        if (!message || message.length === 0) return { encoding: "gsm7", length: 0, segments: 0, remaining: 160 };
        if (channel === "whatsapp") {
            var len = message.length, segments = Math.ceil(len / 1000);
            return { encoding: "whatsapp", length: len, segments: segments, remaining: (1000 * segments) - len };
        }
        var codePoints = getCodePoints(message), encoding = detectEncoding(codePoints), length = codePoints.length;
        if (encoding === "gsm7ex") { for (var i = 0; i < codePoints.length; i++) if (gsm7ExtChars.indexOf(codePoints[i]) !== -1) length++; }
        else if (encoding === "ucs2") { for (var i = 0; i < codePoints.length; i++) if (codePoints[i] >= 65536) length++; }
        var singleLimit = (encoding === "gsm7" || encoding === "gsm7ex") ? 160 : 70;
        var multiLimit = (encoding === "gsm7" || encoding === "gsm7ex") ? 153 : 67;
        var segments = length <= singleLimit ? (length > 0 ? 1 : 0) : Math.ceil(length / multiLimit);
        var perMessage = length <= singleLimit ? singleLimit : multiLimit;
        return { encoding: encoding, length: length, segments: segments, remaining: (perMessage * Math.max(segments, 1)) - length };
    }

    function updateCounter() {
        var message = document.getElementById("message").value;
        var channel = document.getElementById("channel").value;
        var result = countSegments(message, channel);
        document.getElementById("charCount").textContent = result.length;
        document.getElementById("segmentCount").textContent = result.segments;
        document.getElementById("remaining").textContent = result.remaining;
        var encodingDisplay = result.encoding.toUpperCase();
        if (encodingDisplay === "GSM7EX") encodingDisplay = "GSM-7 EXT";
        if (encodingDisplay === "GSM7") encodingDisplay = "GSM-7";
        document.getElementById("encoding").textContent = encodingDisplay;
    }

    document.getElementById("message").addEventListener("input", updateCounter);
    document.getElementById("channel").addEventListener("change", updateCounter);
    updateCounter();
})();
</script>';
}

/**
 * Handle gateway POST actions
 */
function sms_suite_admin_handle_gateway_action($data)
{
    if (!isset($data['action'])) {
        return;
    }

    try {
        switch ($data['action']) {
            case 'delete':
                if (isset($data['gateway_id'])) {
                    Capsule::table('mod_sms_gateways')->where('id', (int)$data['gateway_id'])->delete();
                    Capsule::table('mod_sms_gateway_countries')->where('gateway_id', (int)$data['gateway_id'])->delete();
                }
                break;
        }
    } catch (Exception $e) {
        // Log error
        logActivity('SMS Suite: Gateway action failed - ' . $e->getMessage());
    }
}

/**
 * Test gateway connection
 */
function sms_suite_admin_test_gateway($gatewayId)
{
    try {
        // Load gateway classes
        require_once __DIR__ . '/../lib/Gateways/GatewayInterface.php';
        require_once __DIR__ . '/../lib/Gateways/AbstractGateway.php';
        require_once __DIR__ . '/../lib/Gateways/GenericHttpGateway.php';
        require_once __DIR__ . '/../lib/Gateways/TwilioGateway.php';
        require_once __DIR__ . '/../lib/Gateways/PlivoGateway.php';
        require_once __DIR__ . '/../lib/Gateways/VonageGateway.php';
        require_once __DIR__ . '/../lib/Gateways/InfobipGateway.php';
        require_once __DIR__ . '/../lib/Gateways/GatewayRegistry.php';

        $gateway = \SMSSuite\Gateways\GatewayRegistry::getById($gatewayId);

        $balance = $gateway->getBalance();

        if ($balance !== null) {
            // Update balance in database
            Capsule::table('mod_sms_gateways')
                ->where('id', $gatewayId)
                ->update(['balance' => $balance, 'updated_at' => date('Y-m-d H:i:s')]);

            return 'Gateway connection successful! Balance: ' . number_format($balance, 4);
        } else {
            return 'Gateway connection successful! (Balance check not supported by this gateway)';
        }

    } catch (Exception $e) {
        return 'Gateway test failed: ' . $e->getMessage();
    }
}

/**
 * Get CSS class for status
 */
function sms_suite_status_class($status)
{
    $map = [
        'delivered' => 'success',
        'active' => 'success',
        'sent' => 'info',
        'sending' => 'info',
        'queued' => 'warning',
        'pending' => 'warning',
        'scheduled' => 'warning',
        'failed' => 'danger',
        'rejected' => 'danger',
        'undelivered' => 'danger',
        'expired' => 'default',
        'inactive' => 'default',
        'cancelled' => 'default',
    ];

    return isset($map[$status]) ? $map[$status] : 'default';
}

/**
 * Client SMS Settings page - Manage individual client's sender ID and gateway
 */
function sms_suite_admin_client_settings($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

    if (!$clientId) {
        echo '<div class="alert alert-danger">Client ID required.</div>';
        return;
    }

    // Get client
    $client = Capsule::table('tblclients')->where('id', $clientId)->first();
    if (!$client) {
        echo '<div class="alert alert-danger">Client not found.</div>';
        return;
    }

    // Get or create settings
    $settings = Capsule::table('mod_sms_settings')->where('client_id', $clientId)->first();
    if (!$settings) {
        Capsule::table('mod_sms_settings')->insert([
            'client_id' => $clientId,
            'billing_mode' => 'per_segment',
            'api_enabled' => true,
            'accept_sms' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $settings = Capsule::table('mod_sms_settings')->where('client_id', $clientId)->first();
    }

    // Handle form submission
    $success = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_client_settings'])) {
        try {
            Capsule::table('mod_sms_settings')
                ->where('client_id', $clientId)
                ->update([
                    'assigned_sender_id' => $_POST['assigned_sender_id'] ?: null,
                    'assigned_gateway_id' => !empty($_POST['assigned_gateway_id']) ? (int)$_POST['assigned_gateway_id'] : null,
                    'billing_mode' => $_POST['billing_mode'] ?? 'per_segment',
                    'monthly_limit' => !empty($_POST['monthly_limit']) ? (int)$_POST['monthly_limit'] : null,
                    'api_enabled' => isset($_POST['api_enabled']) ? 1 : 0,
                    'accept_sms' => isset($_POST['accept_sms']) ? 1 : 0,
                    'accept_marketing_sms' => isset($_POST['accept_marketing_sms']) ? 1 : 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $success = 'Client SMS settings updated successfully.';
            $settings = Capsule::table('mod_sms_settings')->where('client_id', $clientId)->first();

        } catch (Exception $e) {
            $error = 'Failed to update settings: ' . $e->getMessage();
        }
    }

    // Handle add balance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_balance'])) {
        $amount = (float)$_POST['amount'];
        if ($amount > 0) {
            require_once __DIR__ . '/../lib/Billing/BillingService.php';
            $result = \SMSSuite\Billing\BillingService::addBalance($clientId, $amount, 'Admin credit');
            if ($result['success']) {
                $success = 'Balance added: $' . number_format($amount, 2);
            } else {
                $error = $result['error'];
            }
        }
    }

    // Get gateways and sender IDs
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->orderBy('name')->get();
    $senderIds = Capsule::table('mod_sms_sender_ids')
        ->where(function($q) use ($clientId) {
            $q->whereNull('client_id')->orWhere('client_id', $clientId);
        })
        ->where('status', 'active')
        ->orderBy('sender_id')
        ->get();

    // Get wallet balance
    $wallet = Capsule::table('mod_sms_wallet')->where('client_id', $clientId)->first();
    $balance = $wallet->balance ?? 0;

    // Get stats
    $stats = Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered, SUM(segments) as segments')
        ->first();

    $clientName = $client->companyname ?: ($client->firstname . ' ' . $client->lastname);

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title">SMS Settings for: ' . htmlspecialchars($clientName) . ' (ID: ' . $clientId . ')</h3>';
    echo '</div>';
    echo '<div class="panel-body">';

    if ($success) {
        echo '<div class="alert alert-success">' . htmlspecialchars($success) . '</div>';
    }
    if ($error) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
    }

    echo '<div class="row">';

    // Left column - Settings form
    echo '<div class="col-md-8">';
    echo '<form method="post">';
    echo '<input type="hidden" name="save_client_settings" value="1">';

    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<div class="form-group">';
    echo '<label>Assigned Sender ID</label>';
    echo '<select name="assigned_sender_id" class="form-control">';
    echo '<option value="">-- Use Default --</option>';
    foreach ($senderIds as $sid) {
        $selected = ($settings->assigned_sender_id === $sid->sender_id) ? 'selected' : '';
        $label = $sid->sender_id . ($sid->client_id ? ' (Client)' : ' (Global)');
        echo '<option value="' . htmlspecialchars($sid->sender_id) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '<p class="help-block">The sender ID used for this client\'s outgoing messages</p>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<div class="form-group">';
    echo '<label>Assigned Gateway</label>';
    echo '<select name="assigned_gateway_id" class="form-control">';
    echo '<option value="">-- Use Default --</option>';
    foreach ($gateways as $gw) {
        $selected = (isset($settings->assigned_gateway_id) && $settings->assigned_gateway_id == $gw->id) ? 'selected' : '';
        echo '<option value="' . $gw->id . '" ' . $selected . '>' . htmlspecialchars($gw->name) . ' (' . $gw->type . ')</option>';
    }
    echo '</select>';
    echo '<p class="help-block">The SMS gateway for this client</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<div class="form-group">';
    echo '<label>Billing Mode</label>';
    echo '<select name="billing_mode" class="form-control">';
    $modes = ['per_segment' => 'Per Segment', 'per_message' => 'Per Message', 'wallet' => 'Wallet', 'plan' => 'Plan/Bundle'];
    foreach ($modes as $val => $label) {
        $selected = ($settings->billing_mode === $val) ? 'selected' : '';
        echo '<option value="' . $val . '" ' . $selected . '>' . $label . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<div class="form-group">';
    echo '<label>Monthly SMS Limit</label>';
    echo '<input type="number" name="monthly_limit" class="form-control" value="' . ($settings->monthly_limit ?? '') . '" placeholder="Unlimited">';
    echo '<p class="help-block">Leave empty for unlimited</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="row">';
    echo '<div class="col-md-4">';
    echo '<div class="checkbox"><label>';
    echo '<input type="checkbox" name="api_enabled" ' . ($settings->api_enabled ? 'checked' : '') . '> API Access Enabled';
    echo '</label></div>';
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<div class="checkbox"><label>';
    echo '<input type="checkbox" name="accept_sms" ' . ($settings->accept_sms ? 'checked' : '') . '> SMS Notifications Enabled';
    echo '</label></div>';
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<div class="checkbox"><label>';
    echo '<input type="checkbox" name="accept_marketing_sms" ' . (isset($settings->accept_marketing_sms) && $settings->accept_marketing_sms ? 'checked' : '') . '> Marketing SMS Enabled';
    echo '</label></div>';
    echo '</div>';
    echo '</div>';

    echo '<hr>';
    echo '<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Settings</button> ';
    echo '<a href="clientssummary.php?userid=' . $clientId . '" class="btn btn-default">Back to Client</a>';
    echo '</form>';
    echo '</div>';

    // Right column - Stats and quick actions
    echo '<div class="col-md-4">';
    echo '<div class="panel panel-info">';
    echo '<div class="panel-heading"><h4 class="panel-title">Account Summary</h4></div>';
    echo '<div class="panel-body">';
    echo '<table class="table table-condensed">';
    echo '<tr><td>Wallet Balance</td><td><strong>$' . number_format($balance, 2) . '</strong></td></tr>';
    echo '<tr><td>Total Messages</td><td>' . ($stats->total ?? 0) . '</td></tr>';
    echo '<tr><td>Delivered</td><td>' . ($stats->delivered ?? 0) . '</td></tr>';
    echo '<tr><td>Total Segments</td><td>' . ($stats->segments ?? 0) . '</td></tr>';
    if (isset($settings->monthly_limit) && $settings->monthly_limit) {
        echo '<tr><td>Monthly Used</td><td>' . ($settings->monthly_used ?? 0) . ' / ' . $settings->monthly_limit . '</td></tr>';
    }
    echo '</table>';
    echo '</div>';
    echo '</div>';

    // Add balance form
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h4 class="panel-title">Add Balance</h4></div>';
    echo '<div class="panel-body">';
    echo '<form method="post" class="form-inline">';
    echo '<input type="hidden" name="add_balance" value="1">';
    echo '<div class="form-group">';
    echo '<input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="Amount" style="width: 100px;">';
    echo '</div> ';
    echo '<button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // col-md-4
    echo '</div>'; // row

    echo '</div>';
    echo '</div>';
}

/**
 * Client Messages History page
 */
function sms_suite_admin_client_messages($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

    if (!$clientId) {
        echo '<div class="alert alert-danger">Client ID required.</div>';
        return;
    }

    $client = Capsule::table('tblclients')->where('id', $clientId)->first();
    if (!$client) {
        echo '<div class="alert alert-danger">Client not found.</div>';
        return;
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $query = Capsule::table('mod_sms_messages')->where('client_id', $clientId);

    if (!empty($_GET['status'])) {
        $query->where('status', $_GET['status']);
    }

    $total = $query->count();
    $messages = $query->orderBy('created_at', 'desc')->limit($limit)->offset($offset)->get();

    $clientName = $client->companyname ?: ($client->firstname . ' ' . $client->lastname);

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title">Message History: ' . htmlspecialchars($clientName) . '</h3>';
    echo '</div>';
    echo '<div class="panel-body">';

    // Filter
    echo '<form method="get" class="form-inline" style="margin-bottom: 15px;">';
    echo '<input type="hidden" name="module" value="sms_suite">';
    echo '<input type="hidden" name="action" value="client_messages">';
    echo '<input type="hidden" name="client_id" value="' . $clientId . '">';
    echo '<select name="status" class="form-control">';
    echo '<option value="">All Statuses</option>';
    foreach (['queued', 'sending', 'sent', 'delivered', 'failed', 'rejected'] as $s) {
        $selected = (isset($_GET['status']) && $_GET['status'] === $s) ? 'selected' : '';
        echo '<option value="' . $s . '" ' . $selected . '>' . ucfirst($s) . '</option>';
    }
    echo '</select> ';
    echo '<button type="submit" class="btn btn-default">Filter</button>';
    echo '</form>';

    if (count($messages) > 0) {
        echo '<table class="table table-striped table-condensed">';
        echo '<thead><tr><th>Date</th><th>To</th><th>Message</th><th>Segments</th><th>Status</th><th>Cost</th></tr></thead>';
        echo '<tbody>';
        foreach ($messages as $msg) {
            $statusClass = sms_suite_status_class($msg->status);
            echo '<tr>';
            echo '<td style="white-space: nowrap;">' . date('M j, g:i A', strtotime($msg->created_at)) . '</td>';
            echo '<td>' . htmlspecialchars($msg->to_number) . '</td>';
            echo '<td>' . htmlspecialchars(substr($msg->message, 0, 50)) . (strlen($msg->message) > 50 ? '...' : '') . '</td>';
            echo '<td>' . $msg->segments . '</td>';
            echo '<td><span class="label label-' . $statusClass . '">' . ucfirst($msg->status) . '</span></td>';
            echo '<td>$' . number_format($msg->cost, 4) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Pagination
        $totalPages = ceil($total / $limit);
        if ($totalPages > 1) {
            echo '<nav><ul class="pagination">';
            for ($i = 1; $i <= $totalPages; $i++) {
                $active = ($i === $page) ? 'active' : '';
                echo '<li class="' . $active . '"><a href="' . $modulelink . '&action=client_messages&client_id=' . $clientId . '&page=' . $i . '">' . $i . '</a></li>';
            }
            echo '</ul></nav>';
        }
    } else {
        echo '<div class="alert alert-info">No messages found.</div>';
    }

    echo '<hr>';
    echo '<a href="clientssummary.php?userid=' . $clientId . '" class="btn btn-default">Back to Client</a>';
    echo '</div>';
    echo '</div>';
}

/**
 * Send SMS to specific client (from client profile)
 */
function sms_suite_admin_send_to_client($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    if (!$clientId || empty($message)) {
        echo '<div class="alert alert-danger">Client ID and message are required.</div>';
        return;
    }

    $client = Capsule::table('tblclients')->where('id', $clientId)->first();
    if (!$client) {
        echo '<div class="alert alert-danger">Client not found.</div>';
        return;
    }

    // Get phone if not provided
    if (empty($phone)) {
        require_once __DIR__ . '/../lib/Core/NotificationService.php';
        $phone = \SMSSuite\Core\NotificationService::getClientPhone($client);
    }

    if (empty($phone)) {
        echo '<div class="alert alert-danger">Client has no phone number on file.</div>';
        echo '<a href="clientssummary.php?userid=' . $clientId . '" class="btn btn-default">Back to Client</a>';
        return;
    }

    // Get client settings for sender ID and gateway
    $settings = Capsule::table('mod_sms_settings')->where('client_id', $clientId)->first();
    $senderId = $settings->assigned_sender_id ?? null;
    $gatewayId = $settings->assigned_gateway_id ?? null;

    // Send the message
    require_once __DIR__ . '/../lib/Core/MessageService.php';

    $result = \SMSSuite\Core\MessageService::sendDirect($phone, $message, [
        'sender_id' => $senderId,
        'gateway_id' => $gatewayId,
    ]);

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Send SMS to Client</h3></div>';
    echo '<div class="panel-body">';

    if ($result['success']) {
        echo '<div class="alert alert-success">';
        echo '<strong>Success!</strong> Message sent to ' . htmlspecialchars($phone) . '.';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '<strong>Failed!</strong> ' . htmlspecialchars($result['error'] ?? 'Unknown error');
        echo '</div>';
    }

    $clientName = $client->companyname ?: ($client->firstname . ' ' . $client->lastname);
    echo '<p><strong>Client:</strong> ' . htmlspecialchars($clientName) . '</p>';
    echo '<p><strong>Phone:</strong> ' . htmlspecialchars($phone) . '</p>';
    echo '<p><strong>Message:</strong> ' . htmlspecialchars($message) . '</p>';

    echo '<hr>';
    echo '<a href="clientssummary.php?userid=' . $clientId . '" class="btn btn-primary">Back to Client Profile</a>';
    echo '</div>';
    echo '</div>';
}

/**
 * SMS Notification Templates Management
 */
function sms_suite_admin_notifications($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    // Handle save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
        $templateId = (int)$_POST['template_id'];
        $message = trim($_POST['message'] ?? '');
        $status = isset($_POST['status']) ? 'active' : 'inactive';

        try {
            Capsule::table('mod_sms_notification_templates')
                ->where('id', $templateId)
                ->update([
                    'message' => $message,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            $success = 'Template updated successfully.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Create defaults if not exist
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_defaults'])) {
        require_once __DIR__ . '/../lib/Core/NotificationService.php';
        $created = \SMSSuite\Core\NotificationService::createDefaultTemplates();
        $success = $created . ' default templates created.';
    }

    // Get templates grouped by category
    $templates = Capsule::table('mod_sms_notification_templates')
        ->orderBy('category')
        ->orderBy('name')
        ->get();

    $categories = [];
    foreach ($templates as $t) {
        $categories[$t->category][] = $t;
    }

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title">SMS Notification Templates';
    echo '<form method="post" style="display: inline; float: right;">';
    echo '<input type="hidden" name="create_defaults" value="1">';
    echo '<button type="submit" class="btn btn-xs btn-default">Create Default Templates</button>';
    echo '</form>';
    echo '</h3>';
    echo '</div>';
    echo '<div class="panel-body">';

    if ($success) {
        echo '<div class="alert alert-success">' . htmlspecialchars($success) . '</div>';
    }
    if ($error) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
    }

    if (empty($templates)) {
        echo '<div class="alert alert-info">No templates found. Click "Create Default Templates" to get started.</div>';
    } else {
        echo '<p class="text-muted">These templates send SMS notifications alongside WHMCS emails. Enable or disable each notification type as needed.</p>';

        foreach ($categories as $category => $catTemplates) {
            echo '<h4 style="margin-top: 20px; text-transform: capitalize;">' . htmlspecialchars($category) . ' Notifications</h4>';
            echo '<table class="table table-striped table-condensed">';
            echo '<thead><tr><th>Type</th><th>Template Message</th><th>Status</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            foreach ($catTemplates as $t) {
                $statusClass = $t->status === 'active' ? 'success' : 'default';
                echo '<tr>';
                echo '<td style="width: 180px;"><strong>' . htmlspecialchars($t->name) . '</strong><br><small class="text-muted">' . $t->notification_type . '</small></td>';
                echo '<td>' . htmlspecialchars(substr($t->message, 0, 100)) . (strlen($t->message) > 100 ? '...' : '') . '</td>';
                echo '<td><span class="label label-' . $statusClass . '">' . ucfirst($t->status) . '</span></td>';
                echo '<td><button class="btn btn-xs btn-default" onclick="editTemplate(' . $t->id . ', \'' . addslashes($t->name) . '\', \'' . addslashes($t->message) . '\', \'' . $t->status . '\')">Edit</button></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }
    }

    echo '</div>';
    echo '</div>';

    // Edit modal
    echo '
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="save_template" value="1">
                    <input type="hidden" name="template_id" id="edit_template_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" id="edit_modal_title">Edit Template</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Message Template</label>
                            <textarea name="message" id="edit_message" class="form-control" rows="4" required></textarea>
                            <p class="help-block">Use merge tags like {first_name}, {invoice_number}, {total}, etc.</p>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="status" id="edit_status" value="1"> Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    function editTemplate(id, name, message, status) {
        document.getElementById("edit_template_id").value = id;
        document.getElementById("edit_modal_title").textContent = "Edit: " + name;
        document.getElementById("edit_message").value = message;
        document.getElementById("edit_status").checked = (status === "active");
        jQuery("#editModal").modal("show");
    }
    </script>';
}

// ============================================================
// SMS Credit Packages Management
// ============================================================

/**
 * Credit Packages Management Page
 */
function sms_suite_admin_credit_packages($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    require_once __DIR__ . '/../lib/Billing/BillingService.php';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'create') {
            $result = \SMSSuite\Billing\BillingService::createCreditPackage([
                'name' => SecurityHelper::sanitize($_POST['name']),
                'description' => SecurityHelper::sanitize($_POST['description']),
                'credits' => (int)$_POST['credits'],
                'price' => (float)$_POST['price'],
                'bonus_credits' => (int)($_POST['bonus_credits'] ?? 0),
                'validity_days' => (int)($_POST['validity_days'] ?? 0),
                'is_featured' => isset($_POST['is_featured']),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'status' => isset($_POST['status']),
            ]);

            if ($result['success']) {
                echo '<div class="alert alert-success">Package created successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        } elseif ($action === 'update') {
            $result = \SMSSuite\Billing\BillingService::updateCreditPackage((int)$_POST['package_id'], [
                'name' => SecurityHelper::sanitize($_POST['name']),
                'description' => SecurityHelper::sanitize($_POST['description']),
                'credits' => (int)$_POST['credits'],
                'price' => (float)$_POST['price'],
                'bonus_credits' => (int)($_POST['bonus_credits'] ?? 0),
                'validity_days' => (int)($_POST['validity_days'] ?? 0),
                'is_featured' => isset($_POST['is_featured']),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'status' => isset($_POST['status']),
            ]);

            if ($result['success']) {
                echo '<div class="alert alert-success">Package updated successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        } elseif ($action === 'delete') {
            $result = \SMSSuite\Billing\BillingService::deleteCreditPackage((int)$_POST['package_id']);

            if ($result['success']) {
                echo '<div class="alert alert-success">Package deleted successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        }
    }

    // Get all packages
    $packages = \SMSSuite\Billing\BillingService::getCreditPackages(false);

    // Get currencies for dropdown
    $currencies = Capsule::table('tblcurrencies')->get();

    echo '<div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-credit-card"></i> SMS Credit Packages
                <button class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#createPackageModal">
                    <i class="fa fa-plus"></i> Add Package
                </button>
            </h3>
        </div>
        <div class="panel-body">
            <p class="text-muted">Define SMS credit packages that clients can purchase. Credits are added to client balance after invoice payment.</p>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Credits</th>
                        <th>Bonus</th>
                        <th>Price</th>
                        <th>Validity</th>
                        <th>Featured</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if (empty($packages)) {
        echo '<tr><td colspan="8" class="text-center text-muted">No packages created yet.</td></tr>';
    } else {
        foreach ($packages as $pkg) {
            $featured = $pkg->is_featured ? '<span class="label label-warning">Featured</span>' : '';
            $status = $pkg->status ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';
            $validity = $pkg->validity_days > 0 ? $pkg->validity_days . ' days' : 'Never expires';

            echo '<tr>
                <td><strong>' . htmlspecialchars($pkg->name) . '</strong><br><small class="text-muted">' . htmlspecialchars($pkg->description ?? '') . '</small></td>
                <td>' . number_format($pkg->credits) . '</td>
                <td>' . ($pkg->bonus_credits > 0 ? '+' . number_format($pkg->bonus_credits) : '-') . '</td>
                <td>$' . number_format($pkg->price, 2) . '</td>
                <td>' . $validity . '</td>
                <td>' . $featured . '</td>
                <td>' . $status . '</td>
                <td>
                    <button class="btn btn-xs btn-primary" onclick=\'editPackage(' . json_encode($pkg) . ')\'>
                        <i class="fa fa-edit"></i>
                    </button>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this package?\');">
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="package_id" value="' . $pkg->id . '">
                        <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
            </tr>';
        }
    }

    echo '</tbody></table></div></div>';

    // Create Package Modal
    echo '<div class="modal fade" id="createPackageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="create">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-plus"></i> Create Credit Package</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Package Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., Starter Pack">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>SMS Credits <span class="text-danger">*</span></label>
                                    <input type="number" name="credits" class="form-control" required min="1" placeholder="e.g., 100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Bonus Credits</label>
                                    <input type="number" name="bonus_credits" class="form-control" value="0" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="price" class="form-control" required step="0.01" min="0" placeholder="9.99">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Validity (Days)</label>
                                    <input type="number" name="validity_days" class="form-control" value="0" min="0">
                                    <p class="help-block">0 = never expires</p>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="is_featured" value="1"> Featured Package</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="status" value="1" checked> Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Edit Package Modal
    echo '<div class="modal fade" id="editPackageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="update">
                    <input type="hidden" name="package_id" id="edit_pkg_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-edit"></i> Edit Credit Package</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Package Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_pkg_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_pkg_desc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>SMS Credits <span class="text-danger">*</span></label>
                                    <input type="number" name="credits" id="edit_pkg_credits" class="form-control" required min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Bonus Credits</label>
                                    <input type="number" name="bonus_credits" id="edit_pkg_bonus" class="form-control" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="price" id="edit_pkg_price" class="form-control" required step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Validity (Days)</label>
                                    <input type="number" name="validity_days" id="edit_pkg_validity" class="form-control" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" id="edit_pkg_sort" class="form-control">
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="is_featured" id="edit_pkg_featured" value="1"> Featured Package</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="status" id="edit_pkg_status" value="1"> Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    function editPackage(pkg) {
        document.getElementById("edit_pkg_id").value = pkg.id;
        document.getElementById("edit_pkg_name").value = pkg.name;
        document.getElementById("edit_pkg_desc").value = pkg.description || "";
        document.getElementById("edit_pkg_credits").value = pkg.credits;
        document.getElementById("edit_pkg_bonus").value = pkg.bonus_credits || 0;
        document.getElementById("edit_pkg_price").value = pkg.price;
        document.getElementById("edit_pkg_validity").value = pkg.validity_days || 0;
        document.getElementById("edit_pkg_sort").value = pkg.sort_order || 0;
        document.getElementById("edit_pkg_featured").checked = pkg.is_featured == 1;
        document.getElementById("edit_pkg_status").checked = pkg.status == 1;
        jQuery("#editPackageModal").modal("show");
    }
    </script>';
}

// ============================================================
// Sender ID Pool Management
// ============================================================

/**
 * Sender ID Pool Management Page
 */
function sms_suite_admin_sender_id_pool($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    require_once __DIR__ . '/../lib/Billing/BillingService.php';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'create') {
            $result = \SMSSuite\Billing\BillingService::addSenderIdToPool([
                'sender_id' => SecurityHelper::sanitize($_POST['sender_id']),
                'type' => $_POST['type'] ?? 'alphanumeric',
                'description' => SecurityHelper::sanitize($_POST['description']),
                'gateway_id' => (int)$_POST['gateway_id'],
                'country_codes' => !empty($_POST['country_codes']) ? explode(',', $_POST['country_codes']) : null,
                'price_setup' => (float)($_POST['price_setup'] ?? 0),
                'price_monthly' => (float)($_POST['price_monthly'] ?? 0),
                'price_yearly' => (float)($_POST['price_yearly'] ?? 0),
                'requires_approval' => isset($_POST['requires_approval']),
                'is_shared' => isset($_POST['is_shared']),
                'status' => $_POST['status'] ?? 'active',
            ]);

            if ($result['success']) {
                echo '<div class="alert alert-success">Sender ID added to pool successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        } elseif ($action === 'update') {
            $result = \SMSSuite\Billing\BillingService::updateSenderIdPool((int)$_POST['pool_id'], [
                'sender_id' => SecurityHelper::sanitize($_POST['sender_id']),
                'type' => $_POST['type'] ?? 'alphanumeric',
                'description' => SecurityHelper::sanitize($_POST['description']),
                'gateway_id' => (int)$_POST['gateway_id'],
                'country_codes' => !empty($_POST['country_codes']) ? explode(',', $_POST['country_codes']) : null,
                'price_setup' => (float)($_POST['price_setup'] ?? 0),
                'price_monthly' => (float)($_POST['price_monthly'] ?? 0),
                'price_yearly' => (float)($_POST['price_yearly'] ?? 0),
                'requires_approval' => isset($_POST['requires_approval']),
                'is_shared' => isset($_POST['is_shared']),
                'status' => $_POST['status'] ?? 'active',
            ]);

            if ($result['success']) {
                echo '<div class="alert alert-success">Sender ID updated successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        } elseif ($action === 'delete') {
            $result = \SMSSuite\Billing\BillingService::deleteSenderIdFromPool((int)$_POST['pool_id']);

            if ($result['success']) {
                echo '<div class="alert alert-success">Sender ID removed from pool.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        }
    }

    // Get all sender IDs in pool
    $pool = \SMSSuite\Billing\BillingService::getSenderIdPool();

    // Get gateways for dropdown
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->get();

    echo '<div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-id-card"></i> Sender ID Pool
                <button class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#createSenderIdModal">
                    <i class="fa fa-plus"></i> Add Sender ID
                </button>
            </h3>
        </div>
        <div class="panel-body">
            <p class="text-muted">Manage available Sender IDs that can be assigned to clients. Each Sender ID is mapped to a specific gateway.</p>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Sender ID</th>
                        <th>Type</th>
                        <th>Gateway</th>
                        <th>Setup Fee</th>
                        <th>Monthly</th>
                        <th>Yearly</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if (empty($pool)) {
        echo '<tr><td colspan="8" class="text-center text-muted">No sender IDs in pool. Add sender IDs that clients can request or be assigned.</td></tr>';
    } else {
        foreach ($pool as $item) {
            $statusLabel = match($item->status) {
                'active' => '<span class="label label-success">Active</span>',
                'inactive' => '<span class="label label-default">Inactive</span>',
                'reserved' => '<span class="label label-warning">Reserved</span>',
                default => '<span class="label label-default">' . ucfirst($item->status) . '</span>',
            };

            echo '<tr>
                <td>
                    <strong>' . htmlspecialchars($item->sender_id) . '</strong>
                    ' . ($item->is_shared ? '<span class="label label-info" title="Can be used by multiple clients">Shared</span>' : '') . '
                    <br><small class="text-muted">' . htmlspecialchars($item->description ?? '') . '</small>
                </td>
                <td>' . ucfirst($item->type) . '</td>
                <td>' . htmlspecialchars($item->gateway_name ?? 'Unknown') . '</td>
                <td>$' . number_format($item->price_setup, 2) . '</td>
                <td>$' . number_format($item->price_monthly, 2) . '</td>
                <td>$' . number_format($item->price_yearly, 2) . '</td>
                <td>' . $statusLabel . '</td>
                <td>
                    <button class="btn btn-xs btn-primary" onclick=\'editPoolItem(' . json_encode($item) . ')\'>
                        <i class="fa fa-edit"></i>
                    </button>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this Sender ID from pool?\');">
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="pool_id" value="' . $item->id . '">
                        <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
            </tr>';
        }
    }

    echo '</tbody></table></div></div>';

    // Gateway options for dropdowns
    $gatewayOptions = '';
    foreach ($gateways as $gw) {
        $gatewayOptions .= '<option value="' . $gw->id . '">' . htmlspecialchars($gw->name) . '</option>';
    }

    // Create Sender ID Modal
    echo '<div class="modal fade" id="createSenderIdModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="create">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-plus"></i> Add Sender ID to Pool</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Sender ID <span class="text-danger">*</span></label>
                                    <input type="text" name="sender_id" class="form-control" required maxlength="11" placeholder="e.g., MYCOMPANY">
                                    <p class="help-block">Max 11 characters for alphanumeric</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Type</label>
                                    <select name="type" class="form-control">
                                        <option value="alphanumeric">Alphanumeric</option>
                                        <option value="numeric">Numeric</option>
                                        <option value="shortcode">Shortcode</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Internal notes..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Gateway <span class="text-danger">*</span></label>
                            <select name="gateway_id" class="form-control" required>
                                <option value="">-- Select Gateway --</option>
                                ' . $gatewayOptions . '
                            </select>
                            <p class="help-block">Messages using this Sender ID will be sent through this gateway</p>
                        </div>
                        <div class="form-group">
                            <label>Country Codes</label>
                            <input type="text" name="country_codes" class="form-control" placeholder="e.g., US,GB,CA">
                            <p class="help-block">Comma-separated list of allowed countries (leave empty for all)</p>
                        </div>
                        <hr>
                        <h5>Pricing</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Setup Fee</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="price_setup" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Monthly Fee</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="price_monthly" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Yearly Fee</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="price_yearly" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="checkbox">
                            <label><input type="checkbox" name="requires_approval" value="1" checked> Requires Telco Approval</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="is_shared" value="1"> Shared (can be used by multiple clients)</label>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="reserved">Reserved</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add to Pool</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Edit Sender ID Modal
    echo '<div class="modal fade" id="editPoolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="update">
                    <input type="hidden" name="pool_id" id="edit_pool_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-edit"></i> Edit Sender ID</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Sender ID <span class="text-danger">*</span></label>
                                    <input type="text" name="sender_id" id="edit_pool_sender_id" class="form-control" required maxlength="11">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Type</label>
                                    <select name="type" id="edit_pool_type" class="form-control">
                                        <option value="alphanumeric">Alphanumeric</option>
                                        <option value="numeric">Numeric</option>
                                        <option value="shortcode">Shortcode</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_pool_desc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Gateway <span class="text-danger">*</span></label>
                            <select name="gateway_id" id="edit_pool_gateway" class="form-control" required>
                                ' . $gatewayOptions . '
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Country Codes</label>
                            <input type="text" name="country_codes" id="edit_pool_countries" class="form-control">
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Setup Fee</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="price_setup" id="edit_pool_setup" class="form-control" step="0.01">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Monthly Fee</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="price_monthly" id="edit_pool_monthly" class="form-control" step="0.01">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Yearly Fee</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="price_yearly" id="edit_pool_yearly" class="form-control" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="requires_approval" id="edit_pool_approval" value="1"> Requires Telco Approval</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="is_shared" id="edit_pool_shared" value="1"> Shared</label>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="edit_pool_status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="reserved">Reserved</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    function editPoolItem(item) {
        document.getElementById("edit_pool_id").value = item.id;
        document.getElementById("edit_pool_sender_id").value = item.sender_id;
        document.getElementById("edit_pool_type").value = item.type;
        document.getElementById("edit_pool_desc").value = item.description || "";
        document.getElementById("edit_pool_gateway").value = item.gateway_id;
        document.getElementById("edit_pool_countries").value = item.country_codes ? JSON.parse(item.country_codes).join(",") : "";
        document.getElementById("edit_pool_setup").value = item.price_setup || 0;
        document.getElementById("edit_pool_monthly").value = item.price_monthly || 0;
        document.getElementById("edit_pool_yearly").value = item.price_yearly || 0;
        document.getElementById("edit_pool_approval").checked = item.requires_approval == 1;
        document.getElementById("edit_pool_shared").checked = item.is_shared == 1;
        document.getElementById("edit_pool_status").value = item.status;
        jQuery("#editPoolModal").modal("show");
    }
    </script>';
}

// ============================================================
// Sender ID Requests Management
// ============================================================

/**
 * Sender ID Requests Management Page
 */
function sms_suite_admin_sender_id_requests($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    require_once __DIR__ . '/../lib/Billing/BillingService.php';

    // Get current admin ID
    $adminId = $_SESSION['adminid'] ?? 1;

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['form_action'] ?? '';
        $requestId = (int)($_POST['request_id'] ?? 0);

        if ($action === 'approve' && $requestId) {
            $result = \SMSSuite\Billing\BillingService::approveSenderIdRequest($requestId, $adminId, [
                'gateway_id' => (int)($_POST['gateway_id'] ?? 0),
                'setup_fee' => (float)($_POST['setup_fee'] ?? 0),
                'recurring_fee' => (float)($_POST['recurring_fee'] ?? 0),
                'admin_notes' => SecurityHelper::sanitize($_POST['admin_notes'] ?? ''),
            ]);

            if ($result['success']) {
                echo '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        } elseif ($action === 'reject' && $requestId) {
            $result = \SMSSuite\Billing\BillingService::rejectSenderIdRequest(
                $requestId,
                $adminId,
                SecurityHelper::sanitize($_POST['reject_reason'] ?? '')
            );

            if ($result['success']) {
                echo '<div class="alert alert-success">Request rejected.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        }
    }

    // Get filter
    $statusFilter = $_GET['status'] ?? '';

    // Get requests
    $requests = \SMSSuite\Billing\BillingService::getSenderIdRequests($statusFilter ? ['status' => $statusFilter] : []);

    // Get gateways for dropdown
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->get();

    // Count pending
    $pendingCount = Capsule::table('mod_sms_sender_id_requests')->where('status', 'pending')->count();

    echo '<div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-inbox"></i> Sender ID Requests
                ' . ($pendingCount > 0 ? '<span class="badge badge-warning">' . $pendingCount . ' pending</span>' : '') . '
            </h3>
        </div>
        <div class="panel-body">
            <div class="row" style="margin-bottom: 15px;">
                <div class="col-md-6">
                    <div class="btn-group">
                        <a href="' . $modulelink . '&action=sender_id_requests" class="btn btn-' . (empty($statusFilter) ? 'primary' : 'default') . '">All</a>
                        <a href="' . $modulelink . '&action=sender_id_requests&status=pending" class="btn btn-' . ($statusFilter === 'pending' ? 'primary' : 'default') . '">Pending</a>
                        <a href="' . $modulelink . '&action=sender_id_requests&status=approved" class="btn btn-' . ($statusFilter === 'approved' ? 'primary' : 'default') . '">Approved</a>
                        <a href="' . $modulelink . '&action=sender_id_requests&status=active" class="btn btn-' . ($statusFilter === 'active' ? 'primary' : 'default') . '">Active</a>
                        <a href="' . $modulelink . '&action=sender_id_requests&status=rejected" class="btn btn-' . ($statusFilter === 'rejected' ? 'primary' : 'default') . '">Rejected</a>
                    </div>
                </div>
            </div>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Sender ID</th>
                        <th>Type</th>
                        <th>Gateway</th>
                        <th>Billing</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if (empty($requests)) {
        echo '<tr><td colspan="8" class="text-center text-muted">No requests found.</td></tr>';
    } else {
        foreach ($requests as $req) {
            $statusLabel = match($req->status) {
                'pending' => '<span class="label label-warning">Pending</span>',
                'approved' => '<span class="label label-info">Awaiting Payment</span>',
                'active' => '<span class="label label-success">Active</span>',
                'rejected' => '<span class="label label-danger">Rejected</span>',
                'expired' => '<span class="label label-default">Expired</span>',
                default => '<span class="label label-default">' . ucfirst($req->status) . '</span>',
            };

            $clientName = trim(($req->firstname ?? '') . ' ' . ($req->lastname ?? ''));
            if ($req->companyname) {
                $clientName .= ' (' . $req->companyname . ')';
            }

            $billingInfo = ucfirst($req->billing_cycle);
            if ($req->setup_fee > 0 || $req->recurring_fee > 0) {
                $billingInfo .= '<br><small>Setup: $' . number_format($req->setup_fee, 2) . ', Recurring: $' . number_format($req->recurring_fee, 2) . '</small>';
            }

            echo '<tr>
                <td>
                    <a href="clientssummary.php?userid=' . $req->client_id . '" target="_blank">' . htmlspecialchars($clientName) . '</a>
                    <br><small class="text-muted">' . htmlspecialchars($req->email ?? '') . '</small>
                </td>
                <td><strong>' . htmlspecialchars($req->sender_id) . '</strong></td>
                <td>' . ucfirst($req->type) . '</td>
                <td>' . htmlspecialchars($req->gateway_name ?? 'Not assigned') . '</td>
                <td>' . $billingInfo . '</td>
                <td>' . $statusLabel . '</td>
                <td>' . date('M d, Y', strtotime($req->created_at)) . '</td>
                <td>';

            if ($req->status === 'pending') {
                echo '<button class="btn btn-xs btn-success" onclick=\'showApproveModal(' . json_encode($req) . ')\'><i class="fa fa-check"></i> Approve</button> ';
                echo '<button class="btn btn-xs btn-danger" onclick=\'showRejectModal(' . $req->id . ')\'><i class="fa fa-times"></i> Reject</button>';
            } elseif ($req->status === 'approved' && $req->invoice_id) {
                echo '<a href="invoices.php?action=edit&id=' . $req->invoice_id . '" class="btn btn-xs btn-info" target="_blank"><i class="fa fa-file-text"></i> Invoice #' . $req->invoice_id . '</a>';
            } elseif ($req->admin_notes) {
                echo '<span class="text-muted" title="' . htmlspecialchars($req->admin_notes) . '"><i class="fa fa-comment"></i></span>';
            }

            echo '</td></tr>';
        }
    }

    echo '</tbody></table></div></div>';

    // Gateway options for approve modal
    $gatewayOptions = '';
    foreach ($gateways as $gw) {
        $gatewayOptions .= '<option value="' . $gw->id . '">' . htmlspecialchars($gw->name) . '</option>';
    }

    // Approve Modal
    echo '<div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="approve">
                    <input type="hidden" name="request_id" id="approve_request_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-check"></i> Approve Sender ID Request</h4>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Sender ID:</strong> <span id="approve_sender_id"></span><br>
                            <strong>Client:</strong> <span id="approve_client"></span>
                        </div>

                        <div class="form-group">
                            <label>Assign Gateway <span class="text-danger">*</span></label>
                            <select name="gateway_id" id="approve_gateway" class="form-control" required>
                                ' . $gatewayOptions . '
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Setup Fee</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="setup_fee" id="approve_setup" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Recurring Fee</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">$</span>
                                        <input type="number" name="recurring_fee" id="approve_recurring" class="form-control" step="0.01" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Admin Notes</label>
                            <textarea name="admin_notes" class="form-control" rows="2" placeholder="Internal notes (not shown to client)"></textarea>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fa fa-info-circle"></i> If fees are set, an invoice will be created and the Sender ID will activate after payment.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Approve Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Reject Modal
    echo '<div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="reject">
                    <input type="hidden" name="request_id" id="reject_request_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-times"></i> Reject Request</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Rejection Reason</label>
                            <textarea name="reject_reason" class="form-control" rows="3" placeholder="Reason for rejection (optional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    function showApproveModal(req) {
        document.getElementById("approve_request_id").value = req.id;
        document.getElementById("approve_sender_id").textContent = req.sender_id;
        document.getElementById("approve_client").textContent = (req.firstname || "") + " " + (req.lastname || "") + " (" + req.email + ")";
        document.getElementById("approve_gateway").value = req.gateway_id || "";
        document.getElementById("approve_setup").value = req.setup_fee || 0;
        document.getElementById("approve_recurring").value = req.recurring_fee || 0;
        jQuery("#approveModal").modal("show");
    }

    function showRejectModal(requestId) {
        document.getElementById("reject_request_id").value = requestId;
        jQuery("#rejectModal").modal("show");
    }
    </script>';
}
