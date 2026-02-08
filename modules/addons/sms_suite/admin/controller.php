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

    // Handle AJAX routes before rendering navigation (they return JSON and exit)
    switch ($action) {
        case 'ajax_search_clients':
            sms_suite_ajax_search_clients();
            return;
        case 'ajax_search_sender_ids':
            sms_suite_ajax_search_sender_ids();
            return;
    }

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

        case 'campaign_create':
        case 'campaign_edit':
            sms_suite_admin_campaign_edit($vars, $lang);
            break;

        case 'campaign_view':
            sms_suite_admin_campaign_view($vars, $lang);
            break;

        case 'contacts':
            sms_suite_admin_contacts($vars, $lang);
            break;

        case 'contact_groups':
            sms_suite_admin_contact_groups($vars, $lang);
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

        case 'billing_rates':
            sms_suite_admin_billing_rates($vars, $lang);
            break;

        case 'sender_id_pool':
            sms_suite_admin_sender_id_pool($vars, $lang);
            break;

        case 'sender_id_requests':
            sms_suite_admin_sender_id_requests($vars, $lang);
            break;

        case 'network_prefixes':
            sms_suite_admin_network_prefixes($vars, $lang);
            break;

        case 'client_rates':
            sms_suite_admin_client_rates($vars, $lang);
            break;

        case 'download_doc':
            sms_suite_admin_download_document();
            break;

        // AJAX handlers
        case 'ajax_message_detail':
            sms_suite_ajax_message_detail();
            break;

        case 'ajax_retry_message':
            sms_suite_ajax_retry_message();
            break;

        case 'dashboard':
        default:
            sms_suite_admin_dashboard($vars, $lang);
            break;
    }

    // Global Select2 initialization for all searchable dropdowns
    sms_suite_admin_select2_init();
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
        'billing_rates' => ['icon' => 'fa-dollar', 'label' => 'Billing Rates'],
        'network_prefixes' => ['icon' => 'fa-globe', 'label' => 'Network Prefixes'],
        'client_rates' => ['icon' => 'fa-user-circle', 'label' => 'Client Rates'],
        'contacts' => ['icon' => 'fa-address-book', 'label' => 'Contacts'],
        'contact_groups' => ['icon' => 'fa-users', 'label' => 'Contact Groups'],
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

    // Load Select2 CSS/JS for searchable dropdowns
    echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
    echo '<style>.select2-container { min-width: 100%; } .select2-container .select2-selection--single { height: 34px; border: 1px solid #ccc; border-radius: 4px; } .select2-container .select2-selection--single .select2-selection__rendered { line-height: 34px; } .select2-container .select2-selection--single .select2-selection__arrow { height: 32px; } .select2-container--default .select2-selection--multiple { border: 1px solid #ccc; border-radius: 4px; min-height: 34px; }</style>';
    echo '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';

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
 * Global Select2 initialization for all searchable dropdowns
 */
function sms_suite_admin_select2_init()
{
    // Get the modulelink for AJAX URLs
    $modulelink = isset($_GET['module']) ? 'addonmodules.php?module=' . urlencode($_GET['module']) : 'addonmodules.php?module=sms_suite';

    echo '<script>
    jQuery(document).ready(function($) {
        if (!$.fn.select2) { return; }

        var ajaxBase = "' . $modulelink . '";

        // Helper: init Select2 on elements, with modal dropdownParent if inside a .modal
        function initS2(selector, opts) {
            $(selector).each(function() {
                if ($(this).data("select2")) { return; }
                var o = $.extend({ width: "100%", allowClear: true, placeholder: $(this).find("option:first").text() || "Select..." }, opts || {});
                var modal = $(this).closest(".modal");
                if (modal.length) { o.dropdownParent = modal; }
                $(this).select2(o);
            });
        }

        // Helper: init AJAX-based Select2 for large datasets
        function initS2Ajax(selector, ajaxAction, placeholder) {
            $(selector).each(function() {
                if ($(this).data("select2")) { return; }
                var opts = {
                    width: "100%",
                    allowClear: true,
                    placeholder: placeholder || "Search...",
                    minimumInputLength: 0,
                    ajax: {
                        url: ajaxBase + "&action=" + ajaxAction,
                        dataType: "json",
                        delay: 300,
                        data: function(params) { return { q: params.term || "", page: params.page || 1 }; },
                        processResults: function(data) { return { results: data.results || [], pagination: { more: data.pagination ? data.pagination.more : false } }; },
                        cache: true
                    }
                };
                var modal = $(this).closest(".modal");
                if (modal.length) { opts.dropdownParent = modal; }
                $(this).select2(opts);
            });
        }

        // --- Client selectors: AJAX-based for reliable search across all clients ---
        initS2Ajax("#assign_client_select", "ajax_search_clients", "Search for a client...");
        initS2Ajax("select[name=\"client_id\"]", "ajax_search_clients", "Search for a client...");

        // --- Sender ID selectors: AJAX-based to include both tables ---
        initS2Ajax("#edit_sender", "ajax_search_sender_ids", "Search sender ID...");
        initS2Ajax("select[name=\"assigned_sender_id\"]", "ajax_search_sender_ids", "Search sender ID...");
        initS2Ajax("select[name=\"sender_id\"]", "ajax_search_sender_ids", "Search sender ID...");

        // --- Gateway selectors ---
        initS2("select[name=\"gateway_id\"]", { placeholder: "Select gateway..." });
        initS2("#auto_gateway", { placeholder: "Select gateway..." });
        initS2("#edit_pool_gateway", { placeholder: "Select gateway..." });
        initS2("#approve_gateway", { placeholder: "Select gateway..." });
        initS2("#edit_gateway", { placeholder: "Select gateway..." });
        initS2("select[name=\"assigned_gateway_id\"]", { placeholder: "Select gateway..." });

        // --- WHMCS Hook / Trigger selectors (large lists) ---
        initS2("#tpl_trigger", { placeholder: "Select trigger hook..." });
        initS2("#auto_hook", { placeholder: "Select WHMCS hook..." });
        initS2("#auto_trigger", { placeholder: "Select trigger type..." });

        // --- Country / Operator selectors ---
        initS2("select[name=\"country_code\"]", { placeholder: "Select country..." });
        initS2("select[name=\"filter_country\"]", { placeholder: "All Countries" });
        initS2("select[name=\"filter_operator\"]", { placeholder: "All Operators" });

        // --- Contact group selectors ---
        initS2("select[name=\"group_id\"]", { placeholder: "Select group..." });
        initS2("select[name=\"import_group_id\"]", { placeholder: "Select group..." });

        // --- Template category ---
        initS2("#tpl_category", { placeholder: "Select category..." });

        // --- Gateway type on gateway edit ---
        initS2("#gateway_type", { placeholder: "Select gateway type..." });

        // --- Currency selectors ---
        initS2("select[name=\"currency_id\"]", { placeholder: "Select currency..." });
        initS2("#edit_pkg_currency", { placeholder: "Select currency..." });

        // --- Network prefix modals ---
        initS2("select[name=\"dest_network\"]", { placeholder: "Select network..." });

        // --- Reports gateway filter ---
        initS2("#report_gateway", { placeholder: "All Gateways" });

        // Re-init Select2 when modals open (for dynamically populated selects)
        $(document).on("shown.bs.modal", function(e) {
            $(e.target).find("select").each(function() {
                if (!$(this).data("select2")) {
                    var opts = { width: "100%", allowClear: true, dropdownParent: $(e.target), placeholder: $(this).find("option:first").text() || "Select..." };
                    if ($(this).find("option").length > 4) {
                        $(this).select2(opts);
                    }
                }
            });
        });
    });
    </script>';
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
        'generic_http' => ['name' => 'Custom HTTP Gateway (Create Your Own)', 'channels' => ['sms', 'whatsapp']],
        'airtouch' => ['name' => 'Airtouch Kenya', 'channels' => ['sms']],
        'africastalking' => ['name' => 'Africa\'s Talking', 'channels' => ['sms']],
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
    $debugInfo = [];
    if ($gateway && !empty($gateway->credentials)) {
        $decrypted = sms_suite_decrypt($gateway->credentials);
        $decoded = json_decode($decrypted, true);
        $jsonError = json_last_error();
        $credentials = is_array($decoded) ? $decoded : [];

        // Debug info (remove in production)
        $debugInfo['encrypted_length'] = strlen($gateway->credentials);
        $debugInfo['decrypted_length'] = strlen($decrypted);
        $debugInfo['decrypted_raw'] = $decrypted;
        $debugInfo['json_valid'] = ($jsonError === JSON_ERROR_NONE);
        $debugInfo['json_error'] = $jsonError !== JSON_ERROR_NONE ? json_last_error_msg() : '';
        $debugInfo['credentials_count'] = count($credentials);
    }

    // Parse settings
    $settings = [];
    if ($gateway && !empty($gateway->settings)) {
        $settings = json_decode($gateway->settings, true) ?: [];
    }

    // Show debug info for troubleshooting (can be removed later)
    if (!$isNew && isset($_GET['debug'])) {
        $decryptedPreview = isset($debugInfo['decrypted_raw']) ? $debugInfo['decrypted_raw'] : '';
        $jsonError = isset($debugInfo['json_error']) ? $debugInfo['json_error'] : '';

        echo '<div class="alert alert-info"><strong>Debug Info:</strong><br>';
        echo 'Gateway ID: ' . $gateway->id . '<br>';
        echo 'Encrypted Length: ' . ($debugInfo['encrypted_length'] ?? 'N/A') . '<br>';
        echo 'Decrypted Length: ' . ($debugInfo['decrypted_length'] ?? 'N/A') . '<br>';
        echo 'JSON Valid: ' . (($debugInfo['json_valid'] ?? false) ? 'Yes' : 'No') . '<br>';
        if ($jsonError) {
            echo 'JSON Error: ' . htmlspecialchars($jsonError) . '<br>';
        }
        echo 'Credentials Count: ' . ($debugInfo['credentials_count'] ?? 0) . '<br>';
        echo 'Credential Keys: ' . (count($credentials) > 0 ? implode(', ', array_keys($credentials)) : 'None') . '<br>';
        echo '<hr>';
        echo '<strong>Raw Encrypted (first 100 chars):</strong><br>';
        echo '<code>' . htmlspecialchars(substr($gateway->credentials, 0, 100)) . '...</code><br>';
        echo '<strong>Raw Decrypted (first 200 chars):</strong><br>';
        echo '<code>' . htmlspecialchars(substr($decryptedPreview, 0, 200)) . '...</code><br>';
        echo '</div>';
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
            // Handle section headers
            if (field.type === "section") {
                html += "<hr><h5 class=\"text-primary\"><i class=\"fa fa-cog\"></i> " + field.label + "</h5>";
                return;
            }

            html += "<div class=\"form-group\">";
            html += "<label class=\"col-sm-3 control-label\">" + field.label + (field.required ? " *" : "") + "</label>";
            html += "<div class=\"col-sm-6\">";

            if (field.type === "select") {
                html += "<select name=\"credentials[" + field.name + "]\" class=\"form-control\">";
                for (var key in field.options) {
                    var defaultVal = field.default || "";
                    var selected = (key === defaultVal) ? " selected" : "";
                    html += "<option value=\"" + key + "\"" + selected + ">" + field.options[key] + "</option>";
                }
                html += "</select>";
            } else if (field.type === "textarea") {
                html += "<textarea name=\"credentials[" + field.name + "]\" class=\"form-control\" rows=\"4\">" + (field.default || "") + "</textarea>";
            } else {
                var inputType = field.type === "password" ? "password" : "text";
                var defaultVal = field.default || "";
                html += "<input type=\"" + inputType + "\" name=\"credentials[" + field.name + "]\" class=\"form-control\" value=\"" + defaultVal + "\" placeholder=\"" + (field.placeholder || "") + "\">";
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

        // Handle section headers
        if ($field['type'] === 'section') {
            echo '<hr><h5 class="text-primary"><i class="fa fa-cog"></i> ' . htmlspecialchars($field['label']) . '</h5>';
            continue;
        }

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
            // Basic Configuration
            ['name' => 'api_endpoint', 'label' => 'Base URL / API Endpoint', 'type' => 'text', 'required' => true, 'placeholder' => 'https://api.provider.com/sms/send', 'description' => 'Full URL to the SMS API endpoint'],
            ['name' => 'http_method', 'label' => 'HTTP Request Method', 'type' => 'select', 'options' => ['POST' => 'POST', 'GET' => 'GET', 'PUT' => 'PUT'], 'default' => 'POST'],
            ['name' => 'success_keyword', 'label' => 'Success Keyword', 'type' => 'text', 'placeholder' => '200', 'description' => 'Text/code that appears in response to indicate success (e.g., 200, success, OK)'],

            // Request Configuration
            ['name' => '_section_request', 'label' => 'Request Configuration', 'type' => 'section'],
            ['name' => 'json_encoded', 'label' => 'Enable JSON Encoded POST', 'type' => 'select', 'options' => ['no' => 'No (Form Data)', 'yes' => 'Yes (JSON Body)'], 'default' => 'no'],
            ['name' => 'content_type', 'label' => 'Content Type', 'type' => 'select', 'options' => [
                'application/x-www-form-urlencoded' => 'application/x-www-form-urlencoded',
                'application/json' => 'application/json',
                'multipart/form-data' => 'multipart/form-data',
                'text/plain' => 'text/plain',
            ], 'default' => 'application/x-www-form-urlencoded'],
            ['name' => 'accept_header', 'label' => 'Content Type Accept', 'type' => 'select', 'options' => [
                'application/json' => 'application/json',
                'text/plain' => 'text/plain',
                'text/xml' => 'text/xml',
                '*/*' => '*/* (Any)',
            ], 'default' => 'application/json'],
            ['name' => 'character_encoding', 'label' => 'Character Encoding', 'type' => 'select', 'options' => [
                'none' => 'None',
                'utf-8' => 'UTF-8',
                'iso-8859-1' => 'ISO-8859-1',
            ], 'default' => 'none'],
            ['name' => 'ignore_ssl', 'label' => 'Ignore SSL Certificate Verification', 'type' => 'select', 'options' => ['no' => 'No', 'yes' => 'Yes'], 'default' => 'no', 'description' => 'Enable only for testing or if provider has self-signed cert'],

            // Authentication
            ['name' => '_section_auth', 'label' => 'Authentication', 'type' => 'section'],
            ['name' => 'auth_type', 'label' => 'Authorization Type', 'type' => 'select', 'options' => [
                'params' => 'Authentication via Parameters',
                'basic' => 'Basic Auth (Header)',
                'bearer' => 'Bearer Token (Header)',
                'api_key_header' => 'API Key (Custom Header)',
                'none' => 'None',
            ], 'default' => 'params'],
            ['name' => 'auth_header_name', 'label' => 'API Key Header Name', 'type' => 'text', 'default' => 'Authorization', 'description' => 'For API Key header auth'],

            // Rate Limiting
            ['name' => '_section_rate', 'label' => 'Rate Limiting', 'type' => 'section'],
            ['name' => 'rate_limit', 'label' => 'Sending Credit (max messages)', 'type' => 'text', 'default' => '60', 'description' => 'Maximum number of SMS per time period'],
            ['name' => 'rate_time_value', 'label' => 'Time Base', 'type' => 'text', 'default' => '1'],
            ['name' => 'rate_time_unit', 'label' => 'Time Unit', 'type' => 'select', 'options' => ['second' => 'Second', 'minute' => 'Minute', 'hour' => 'Hour'], 'default' => 'minute'],
            ['name' => 'sms_per_request', 'label' => 'SMS Per Single Request', 'type' => 'text', 'default' => '1', 'description' => 'Number of SMS in single API request (for bulk)'],
            ['name' => 'bulk_delimiter', 'label' => 'Delimiter (for bulk)', 'type' => 'select', 'options' => [',' => 'Comma (,)', ';' => 'Semicolon (;)', '|' => 'Pipe (|)', '\n' => 'New Line'], 'default' => ','],

            // Features
            ['name' => '_section_features', 'label' => 'Features', 'type' => 'section'],
            ['name' => 'support_plain', 'label' => 'Plain Text Messages', 'type' => 'select', 'options' => ['yes' => 'Yes', 'no' => 'No'], 'default' => 'yes'],
            ['name' => 'support_unicode', 'label' => 'Unicode Messages', 'type' => 'select', 'options' => ['yes' => 'Yes', 'no' => 'No'], 'default' => 'yes'],
            ['name' => 'support_schedule', 'label' => 'Scheduled Messages', 'type' => 'select', 'options' => ['yes' => 'Yes', 'no' => 'No'], 'default' => 'no'],

            // Parameter Mapping
            ['name' => '_section_params', 'label' => 'Parameter Mapping', 'type' => 'section'],
            // Username/API Key
            ['name' => 'param_username_key', 'label' => 'Username/API Key - Parameter Name', 'type' => 'text', 'placeholder' => 'username'],
            ['name' => 'param_username_value', 'label' => 'Username/API Key - Value', 'type' => 'text', 'placeholder' => 'your_username'],
            ['name' => 'param_username_location', 'label' => 'Username/API Key - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL'], 'default' => 'body'],
            // Password
            ['name' => 'param_password_key', 'label' => 'Password - Parameter Name', 'type' => 'text', 'placeholder' => 'password'],
            ['name' => 'param_password_value', 'label' => 'Password - Value', 'type' => 'password'],
            ['name' => 'param_password_location', 'label' => 'Password - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'], 'default' => 'body'],
            // Action
            ['name' => 'param_action_key', 'label' => 'Action - Parameter Name', 'type' => 'text', 'placeholder' => 'action'],
            ['name' => 'param_action_value', 'label' => 'Action - Value', 'type' => 'text', 'placeholder' => 'send'],
            ['name' => 'param_action_location', 'label' => 'Action - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'], 'default' => 'blank'],
            // Source (Sender ID)
            ['name' => 'param_source_key', 'label' => 'Source/Sender ID - Parameter Name', 'type' => 'text', 'default' => 'from', 'placeholder' => 'from, sender, source'],
            ['name' => 'param_source_value', 'label' => 'Source/Sender ID - Default Value', 'type' => 'text', 'description' => 'Default sender ID (can be overridden per message)'],
            ['name' => 'param_source_location', 'label' => 'Source - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL'], 'default' => 'body'],
            // Destination (Phone Number)
            ['name' => 'param_destination_key', 'label' => 'Destination - Parameter Name', 'type' => 'text', 'default' => 'to', 'placeholder' => 'to, msisdn, destination, phone'],
            // Message
            ['name' => 'param_message_key', 'label' => 'Message - Parameter Name', 'type' => 'text', 'default' => 'message', 'placeholder' => 'message, text, body, content'],
            // Unicode
            ['name' => 'param_unicode_key', 'label' => 'Unicode - Parameter Name', 'type' => 'text', 'placeholder' => 'unicode, encoding, type'],
            ['name' => 'param_unicode_value', 'label' => 'Unicode - Value (when unicode)', 'type' => 'text', 'placeholder' => '1, true, unicode'],
            ['name' => 'param_unicode_location', 'label' => 'Unicode - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'], 'default' => 'blank'],
            // Type/Route
            ['name' => 'param_type_key', 'label' => 'Type/Route - Parameter Name', 'type' => 'text', 'placeholder' => 'type, route'],
            ['name' => 'param_type_value', 'label' => 'Type/Route - Value', 'type' => 'text'],
            ['name' => 'param_type_location', 'label' => 'Type/Route - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'], 'default' => 'blank'],
            // Language
            ['name' => 'param_language_key', 'label' => 'Language - Parameter Name', 'type' => 'text', 'placeholder' => 'lang, language'],
            ['name' => 'param_language_value', 'label' => 'Language - Value', 'type' => 'text'],
            ['name' => 'param_language_location', 'label' => 'Language - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'], 'default' => 'blank'],
            // Schedule
            ['name' => 'param_schedule_key', 'label' => 'Schedule - Parameter Name', 'type' => 'text', 'placeholder' => 'schedule, send_at, datetime'],
            ['name' => 'param_schedule_format', 'label' => 'Schedule - Date Format', 'type' => 'text', 'default' => 'Y-m-d H:i:s', 'placeholder' => 'Y-m-d H:i:s'],
            // Custom Values 1-3
            ['name' => 'param_custom1_key', 'label' => 'Custom Value 1 - Parameter Name', 'type' => 'text'],
            ['name' => 'param_custom1_value', 'label' => 'Custom Value 1 - Value', 'type' => 'text'],
            ['name' => 'param_custom1_location', 'label' => 'Custom Value 1 - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'], 'default' => 'blank'],
            ['name' => 'param_custom2_key', 'label' => 'Custom Value 2 - Parameter Name', 'type' => 'text'],
            ['name' => 'param_custom2_value', 'label' => 'Custom Value 2 - Value', 'type' => 'text'],
            ['name' => 'param_custom2_location', 'label' => 'Custom Value 2 - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'], 'default' => 'blank'],
            ['name' => 'param_custom3_key', 'label' => 'Custom Value 3 - Parameter Name', 'type' => 'text'],
            ['name' => 'param_custom3_value', 'label' => 'Custom Value 3 - Value', 'type' => 'text'],
            ['name' => 'param_custom3_location', 'label' => 'Custom Value 3 - Location', 'type' => 'select', 'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'], 'default' => 'blank'],

            // Response Handling
            ['name' => '_section_response', 'label' => 'Response Handling', 'type' => 'section'],
            ['name' => 'success_codes', 'label' => 'Success HTTP Codes', 'type' => 'text', 'default' => '200,201,202', 'description' => 'Comma-separated HTTP status codes indicating success'],
            ['name' => 'response_message_id_path', 'label' => 'Message ID Path in Response', 'type' => 'text', 'default' => 'message_id', 'description' => 'JSON path to message ID (e.g., data.id or messages.0.id)'],
            ['name' => 'phone_format', 'label' => 'Phone Number Format', 'type' => 'select', 'options' => [
                'as_is' => 'As Is (no modification)',
                'plus_prefix' => 'With + Prefix',
                'no_plus' => 'Without + Prefix',
                'digits_only' => 'Digits Only',
            ], 'default' => 'as_is'],

            // Balance Check
            ['name' => '_section_balance', 'label' => 'Balance Check (Optional)', 'type' => 'section'],
            ['name' => 'balance_endpoint', 'label' => 'Balance Check Endpoint', 'type' => 'text', 'description' => 'URL to check account balance'],
            ['name' => 'balance_path', 'label' => 'Balance Path in Response', 'type' => 'text', 'default' => 'balance', 'description' => 'JSON path to balance value'],

            // Custom Headers
            ['name' => '_section_headers', 'label' => 'Custom Headers', 'type' => 'section'],
            ['name' => 'custom_headers', 'label' => 'Additional Headers', 'type' => 'textarea', 'description' => 'One header per line: Header-Name: Value'],
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
        'airtouch' => [
            ['name' => 'api_endpoint', 'label' => 'API Endpoint URL', 'type' => 'text', 'required' => true, 'default' => 'https://client.airtouch.co.ke:9012/sms/api/', 'description' => 'Airtouch API endpoint (default provided)'],
            ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'placeholder' => 'Your Airtouch username'],
            ['name' => 'password', 'label' => 'Password / API Key', 'type' => 'password', 'required' => true, 'description' => 'Your Airtouch password or API key hash'],
            ['name' => 'sender_id', 'label' => 'Default Sender ID (ISSN)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g., XCOBEAN', 'description' => 'Your registered sender ID'],
            ['name' => 'ignore_ssl', 'label' => 'Ignore SSL Errors', 'type' => 'select', 'options' => ['no' => 'No', 'yes' => 'Yes'], 'default' => 'no', 'description' => 'Enable if you get SSL certificate errors'],
            ['name' => 'success_keyword', 'label' => 'Success Keyword', 'type' => 'text', 'description' => 'Text indicating success in response (leave blank for HTTP code check)'],
        ],
        'africastalking' => [
            ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'description' => 'Your Africa\'s Talking username'],
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
            ['name' => 'sender_id', 'label' => 'Sender ID', 'type' => 'text', 'description' => 'Optional: Leave blank to use default'],
            ['name' => 'environment', 'label' => 'Environment', 'type' => 'select', 'options' => ['production' => 'Production', 'sandbox' => 'Sandbox'], 'default' => 'production'],
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

        // Check if table exists first
        $schema = Capsule::schema();
        if (!$schema->hasTable('mod_sms_gateways')) {
            // Try to create the table
            logActivity('SMS Suite: mod_sms_gateways table does not exist, attempting to create');
            sms_suite_create_tables_sql();

            // Check again
            if (!$schema->hasTable('mod_sms_gateways')) {
                logActivity('SMS Suite: Failed to create mod_sms_gateways table');
                return ['success' => false, 'error' => 'Database table does not exist. Please deactivate and reactivate the module.'];
            }
        }

        // Encrypt credentials
        $credentials = $data['credentials'] ?? [];
        $credentialsJson = json_encode($credentials);
        $encryptedCredentials = sms_suite_encrypt($credentialsJson);

        // Log credential info for debugging
        $credKeys = is_array($credentials) ? array_keys($credentials) : [];
        logActivity("SMS Suite: Saving gateway - Credentials received: " . count($credKeys) . " fields (" . implode(', ', $credKeys) . ")");

        // Log credential encryption status for debugging
        if (empty($encryptedCredentials) && !empty($credentialsJson) && $credentialsJson !== '[]') {
            logActivity('SMS Suite: Warning - credential encryption returned empty');
        }

        // Log encrypted data length
        logActivity("SMS Suite: Encrypted data length: " . strlen($encryptedCredentials) . " chars");

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
            logActivity("SMS Suite: Gateway #{$id} updated: {$name} ({$type})");
        } else {
            $record['created_at'] = date('Y-m-d H:i:s');
            $id = Capsule::table('mod_sms_gateways')->insertGetId($record);
            logActivity("SMS Suite: New gateway #{$id} created: {$name} ({$type})");
        }

        return ['success' => true, 'id' => $id];

    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        logActivity('SMS Suite: Gateway save failed - ' . $errorMsg);

        // Provide more helpful error messages
        if (strpos($errorMsg, "doesn't exist") !== false || strpos($errorMsg, 'Table') !== false) {
            return ['success' => false, 'error' => 'Database table error. Please deactivate and reactivate the module to recreate tables. Technical: ' . $errorMsg];
        }

        return ['success' => false, 'error' => $errorMsg];
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
 * Campaigns page - Campaign Management
 */
function sms_suite_admin_campaigns($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $message = '';
    $messageType = '';

    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['delete_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            Capsule::table('mod_sms_campaign_recipients')->where('campaign_id', $campaignId)->delete();
            Capsule::table('mod_sms_campaign_lists')->where('campaign_id', $campaignId)->delete();
            Capsule::table('mod_sms_campaigns')->where('id', $campaignId)->delete();
            $message = 'Campaign deleted successfully.';
            $messageType = 'success';
        }

        if (isset($_POST['cancel_campaign'])) {
            Capsule::table('mod_sms_campaigns')->where('id', (int)$_POST['campaign_id'])->update([
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $message = 'Campaign cancelled.';
            $messageType = 'success';
        }
    }

    // Filters
    $filterStatus = $_GET['status'] ?? '';
    $filterClient = $_GET['client_id'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    // Build query
    $query = Capsule::table('mod_sms_campaigns')
        ->leftJoin('tblclients', 'mod_sms_campaigns.client_id', '=', 'tblclients.id')
        ->leftJoin('mod_sms_gateways', 'mod_sms_campaigns.gateway_id', '=', 'mod_sms_gateways.id')
        ->select([
            'mod_sms_campaigns.*',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.companyname',
            'mod_sms_gateways.name as gateway_name',
        ]);

    if (!empty($filterStatus)) {
        $query->where('mod_sms_campaigns.status', $filterStatus);
    }
    if (!empty($filterClient)) {
        $query->where('mod_sms_campaigns.client_id', (int)$filterClient);
    }

    $total = $query->count();
    $campaigns = $query->orderBy('mod_sms_campaigns.created_at', 'desc')
        ->skip(($page - 1) * $perPage)
        ->take($perPage)
        ->get();
    $totalPages = ceil($total / $perPage);

    // Status counts
    $statusCounts = Capsule::table('mod_sms_campaigns')
        ->select('status', Capsule::raw('COUNT(*) as count'))
        ->groupBy('status')
        ->pluck('count', 'status');

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title" style="display: inline-block;"><i class="fa fa-bullhorn"></i> ' . $lang['campaigns'] . '</h3>';
    echo '<a href="' . $modulelink . '&action=campaign_create" class="btn btn-success btn-sm pull-right"><i class="fa fa-plus"></i> New Campaign</a>';
    echo '</div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    // Status filter tabs
    $statuses = ['draft', 'scheduled', 'queued', 'sending', 'completed', 'failed', 'cancelled'];
    echo '<ul class="nav nav-pills" style="margin-bottom: 15px;">';
    $allActive = empty($filterStatus) ? 'active' : '';
    echo '<li class="' . $allActive . '"><a href="' . $modulelink . '&action=campaigns">All (' . $total . ')</a></li>';
    foreach ($statuses as $status) {
        $count = $statusCounts[$status] ?? 0;
        $active = ($filterStatus === $status) ? 'active' : '';
        echo '<li class="' . $active . '"><a href="' . $modulelink . '&action=campaigns&status=' . $status . '">' . ucfirst($status) . ' (' . $count . ')</a></li>';
    }
    echo '</ul>';

    // Campaigns table
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped">';
    echo '<thead><tr>';
    echo '<th>Campaign</th>';
    echo '<th>Client</th>';
    echo '<th>Recipients</th>';
    echo '<th>Sent/Delivered/Failed</th>';
    echo '<th>Schedule</th>';
    echo '<th>Status</th>';
    echo '<th>Actions</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($campaigns as $campaign) {
        $clientName = trim($campaign->firstname . ' ' . $campaign->lastname);
        if (empty($clientName)) $clientName = 'Admin';

        // Status badge
        $statusColors = [
            'draft' => 'default',
            'scheduled' => 'info',
            'queued' => 'warning',
            'sending' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'default',
            'paused' => 'warning',
        ];
        $statusColor = $statusColors[$campaign->status] ?? 'default';
        $statusBadge = '<span class="label label-' . $statusColor . '">' . ucfirst($campaign->status) . '</span>';

        // Progress
        $deliveryRate = $campaign->sent_count > 0
            ? round(($campaign->delivered_count / $campaign->sent_count) * 100) . '%'
            : '-';

        // Schedule
        $scheduleText = '-';
        if ($campaign->schedule_time) {
            $scheduleText = date('M d, Y H:i', strtotime($campaign->schedule_time));
            if ($campaign->schedule_type === 'recurring') {
                $scheduleText .= ' <small>(Recurring)</small>';
            }
        }

        echo '<tr>';
        echo '<td>';
        echo '<strong>' . htmlspecialchars($campaign->name) . '</strong><br>';
        echo '<small class="text-muted">' . htmlspecialchars($campaign->sender_id ?? 'Default') . ' | ' . strtoupper($campaign->channel) . '</small>';
        echo '</td>';
        echo '<td>' . htmlspecialchars($clientName) . '</td>';
        echo '<td><span class="badge">' . number_format($campaign->total_recipients) . '</span></td>';
        echo '<td>';
        echo '<span class="text-primary">' . number_format($campaign->sent_count) . '</span> / ';
        echo '<span class="text-success">' . number_format($campaign->delivered_count) . '</span> / ';
        echo '<span class="text-danger">' . number_format($campaign->failed_count) . '</span>';
        echo '</td>';
        echo '<td><small>' . $scheduleText . '</small></td>';
        echo '<td>' . $statusBadge . '</td>';
        echo '<td>';

        // Actions based on status
        echo '<a href="' . $modulelink . '&action=campaign_view&id=' . $campaign->id . '" class="btn btn-xs btn-info" title="View"><i class="fa fa-eye"></i></a> ';

        if (in_array($campaign->status, ['draft', 'scheduled'])) {
            echo '<a href="' . $modulelink . '&action=campaign_edit&id=' . $campaign->id . '" class="btn btn-xs btn-primary" title="Edit"><i class="fa fa-edit"></i></a> ';
        }

        if (in_array($campaign->status, ['scheduled', 'queued', 'sending'])) {
            echo '<form method="post" style="display:inline;"><input type="hidden" name="campaign_id" value="' . $campaign->id . '">';
            echo '<button type="submit" name="cancel_campaign" class="btn btn-xs btn-warning" title="Cancel" onclick="return confirm(\'Cancel this campaign?\')"><i class="fa fa-stop"></i></button></form> ';
        }

        if (in_array($campaign->status, ['draft', 'completed', 'failed', 'cancelled'])) {
            echo '<form method="post" style="display:inline;"><input type="hidden" name="campaign_id" value="' . $campaign->id . '">';
            echo '<button type="submit" name="delete_campaign" class="btn btn-xs btn-danger" title="Delete" onclick="return confirm(\'Delete this campaign? This cannot be undone.\')"><i class="fa fa-trash"></i></button></form>';
        }

        echo '</td>';
        echo '</tr>';
    }

    if (count($campaigns) == 0) {
        echo '<tr><td colspan="7" class="text-center text-muted">No campaigns found.</td></tr>';
    }

    echo '</tbody></table></div>';

    // Pagination
    if ($totalPages > 1) {
        echo '<nav><ul class="pagination">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<li class="' . $active . '"><a href="' . $modulelink . '&action=campaigns&page=' . $i . '&status=' . urlencode($filterStatus) . '">' . $i . '</a></li>';
        }
        echo '</ul></nav>';
    }

    echo '</div></div></div>';
}

/**
 * Campaign Create/Edit page
 */
function sms_suite_admin_campaign_edit($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $campaignId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $campaign = null;
    $message = '';
    $messageType = '';

    if ($campaignId > 0) {
        $campaign = Capsule::table('mod_sms_campaigns')->where('id', $campaignId)->first();
    }

    // Get admin contact groups (client_id = 0)
    $groups = Capsule::table('mod_sms_contact_groups')
        ->where('client_id', 0)
        ->where('status', 1)
        ->orderBy('name')
        ->get();

    // Get gateways
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->orderBy('name')->get();

    // Get sender IDs
    $senderIds = Capsule::table('mod_sms_sender_ids')->where('status', 'active')->orderBy('sender_id')->get();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_campaign'])) {
        $data = [
            'client_id' => 0, // Admin campaign
            'name' => trim($_POST['name']),
            'channel' => $_POST['channel'] ?? 'sms',
            'gateway_id' => !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null,
            'sender_id' => $_POST['sender_id'] ?: null,
            'message' => $_POST['message'],
            'status' => $_POST['action_type'] === 'schedule' ? 'scheduled' : 'draft',
            'schedule_time' => !empty($_POST['schedule_time']) ? $_POST['schedule_time'] : null,
            'schedule_type' => $_POST['schedule_type'] ?? 'onetime',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($campaignId > 0) {
            Capsule::table('mod_sms_campaigns')->where('id', $campaignId)->update($data);
            $message = 'Campaign updated.';
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['total_recipients'] = 0;
            $data['sent_count'] = 0;
            $data['delivered_count'] = 0;
            $data['failed_count'] = 0;
            $campaignId = Capsule::table('mod_sms_campaigns')->insertGetId($data);
            $message = 'Campaign created.';
        }

        // Update contact groups
        Capsule::table('mod_sms_campaign_lists')->where('campaign_id', $campaignId)->delete();
        if (!empty($_POST['group_ids'])) {
            $totalRecipients = 0;
            foreach ($_POST['group_ids'] as $groupId) {
                Capsule::table('mod_sms_campaign_lists')->insert([
                    'campaign_id' => $campaignId,
                    'group_id' => (int)$groupId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $groupCount = Capsule::table('mod_sms_contacts')
                    ->where('group_id', (int)$groupId)
                    ->where('status', 'subscribed')
                    ->count();
                $totalRecipients += $groupCount;
            }
            Capsule::table('mod_sms_campaigns')->where('id', $campaignId)->update(['total_recipients' => $totalRecipients]);
        }

        $messageType = 'success';
        $campaign = Capsule::table('mod_sms_campaigns')->where('id', $campaignId)->first();
    }

    // Get selected groups
    $selectedGroups = [];
    if ($campaignId > 0) {
        $selectedGroups = Capsule::table('mod_sms_campaign_lists')
            ->where('campaign_id', $campaignId)
            ->pluck('group_id')
            ->toArray();
    }

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-bullhorn"></i> ' . ($campaign ? 'Edit Campaign' : 'Create Campaign') . '</h3></div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    echo '<form method="post" class="form-horizontal">';

    echo '<div class="form-group">';
    echo '<label class="col-sm-2 control-label">Campaign Name *</label>';
    echo '<div class="col-sm-6"><input type="text" name="name" class="form-control" value="' . htmlspecialchars($campaign->name ?? '') . '" required></div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-2 control-label">Channel</label>';
    echo '<div class="col-sm-3"><select name="channel" class="form-control">';
    echo '<option value="sms"' . (($campaign->channel ?? 'sms') === 'sms' ? ' selected' : '') . '>SMS</option>';
    echo '<option value="whatsapp"' . (($campaign->channel ?? '') === 'whatsapp' ? ' selected' : '') . '>WhatsApp</option>';
    echo '</select></div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-2 control-label">Gateway</label>';
    echo '<div class="col-sm-4"><select name="gateway_id" class="form-control"><option value="">Default Gateway</option>';
    foreach ($gateways as $gw) {
        $sel = (($campaign->gateway_id ?? '') == $gw->id) ? 'selected' : '';
        echo '<option value="' . $gw->id . '" ' . $sel . '>' . htmlspecialchars($gw->name) . '</option>';
    }
    echo '</select></div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-2 control-label">Sender ID</label>';
    echo '<div class="col-sm-3"><select name="sender_id" class="form-control"><option value="">Default</option>';
    foreach ($senderIds as $sid) {
        $sel = (($campaign->sender_id ?? '') == $sid->sender_id) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($sid->sender_id) . '" ' . $sel . '>' . htmlspecialchars($sid->sender_id) . '</option>';
    }
    echo '</select></div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-2 control-label">Contact Groups *</label>';
    echo '<div class="col-sm-6">';
    foreach ($groups as $group) {
        $checked = in_array($group->id, $selectedGroups) ? 'checked' : '';
        echo '<div class="checkbox"><label><input type="checkbox" name="group_ids[]" value="' . $group->id . '" ' . $checked . '> ';
        echo htmlspecialchars($group->name) . ' <small class="text-muted">(' . number_format($group->contact_count) . ' contacts)</small></label></div>';
    }
    if (count($groups) == 0) {
        echo '<p class="text-muted">No contact groups available. <a href="' . $modulelink . '&action=contacts">Create a group first</a>.</p>';
    }
    echo '</div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-2 control-label">Message *</label>';
    echo '<div class="col-sm-8">';
    echo '<textarea name="message" class="form-control" rows="5" required>' . htmlspecialchars($campaign->message ?? '') . '</textarea>';
    echo '<small class="text-muted">Variables: {first_name}, {last_name}, {phone}, {email}</small>';
    echo '</div>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="col-sm-2 control-label">Schedule</label>';
    echo '<div class="col-sm-4">';
    echo '<input type="datetime-local" name="schedule_time" class="form-control" value="' . ($campaign->schedule_time ? date('Y-m-d\TH:i', strtotime($campaign->schedule_time)) : '') . '">';
    echo '<small class="text-muted">Leave blank to save as draft</small>';
    echo '</div>';
    echo '<div class="col-sm-2">';
    echo '<select name="schedule_type" class="form-control">';
    echo '<option value="onetime"' . (($campaign->schedule_type ?? 'onetime') === 'onetime' ? ' selected' : '') . '>One-time</option>';
    echo '<option value="recurring"' . (($campaign->schedule_type ?? '') === 'recurring' ? ' selected' : '') . '>Recurring</option>';
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<hr>';
    echo '<div class="form-group">';
    echo '<div class="col-sm-offset-2 col-sm-8">';
    echo '<button type="submit" name="save_campaign" value="1" class="btn btn-primary"><i class="fa fa-save"></i> Save as Draft</button> ';
    echo '<input type="hidden" name="action_type" id="action_type" value="draft">';
    echo '<button type="submit" name="save_campaign" value="1" class="btn btn-success" onclick="document.getElementById(\'action_type\').value=\'schedule\'"><i class="fa fa-clock-o"></i> Save & Schedule</button> ';
    echo '<a href="' . $modulelink . '&action=campaigns" class="btn btn-default">Cancel</a>';
    echo '</div>';
    echo '</div>';

    echo '</form>';
    echo '</div></div></div>';
}

/**
 * Campaign View page
 */
function sms_suite_admin_campaign_view($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $campaignId = (int)$_GET['id'];

    $campaign = Capsule::table('mod_sms_campaigns')
        ->leftJoin('tblclients', 'mod_sms_campaigns.client_id', '=', 'tblclients.id')
        ->leftJoin('mod_sms_gateways', 'mod_sms_campaigns.gateway_id', '=', 'mod_sms_gateways.id')
        ->select([
            'mod_sms_campaigns.*',
            'tblclients.firstname',
            'tblclients.lastname',
            'mod_sms_gateways.name as gateway_name',
        ])
        ->where('mod_sms_campaigns.id', $campaignId)
        ->first();

    if (!$campaign) {
        echo '<div class="alert alert-danger">Campaign not found.</div>';
        echo '<a href="' . $modulelink . '&action=campaigns" class="btn btn-default">Back to Campaigns</a>';
        return;
    }

    // Get recipient groups
    $groups = Capsule::table('mod_sms_campaign_lists')
        ->leftJoin('mod_sms_contact_groups', 'mod_sms_campaign_lists.group_id', '=', 'mod_sms_contact_groups.id')
        ->where('campaign_id', $campaignId)
        ->select('mod_sms_contact_groups.name', 'mod_sms_contact_groups.contact_count')
        ->get();

    // Get recent messages for this campaign
    $messages = Capsule::table('mod_sms_messages')
        ->where('campaign_id', $campaignId)
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();

    // Status colors
    $statusColors = [
        'draft' => 'default', 'scheduled' => 'info', 'queued' => 'warning',
        'sending' => 'primary', 'completed' => 'success', 'failed' => 'danger', 'cancelled' => 'default'
    ];

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title"><i class="fa fa-bullhorn"></i> Campaign: ' . htmlspecialchars($campaign->name) . '</h3>';
    echo '</div>';
    echo '<div class="panel-body">';

    // Campaign details
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<table class="table table-bordered">';
    echo '<tr><td width="30%"><strong>Status</strong></td><td><span class="label label-' . ($statusColors[$campaign->status] ?? 'default') . '">' . ucfirst($campaign->status) . '</span></td></tr>';
    echo '<tr><td><strong>Channel</strong></td><td>' . strtoupper($campaign->channel) . '</td></tr>';
    echo '<tr><td><strong>Gateway</strong></td><td>' . htmlspecialchars($campaign->gateway_name ?? 'Default') . '</td></tr>';
    echo '<tr><td><strong>Sender ID</strong></td><td>' . htmlspecialchars($campaign->sender_id ?? 'Default') . '</td></tr>';
    echo '<tr><td><strong>Scheduled</strong></td><td>' . ($campaign->schedule_time ? date('M d, Y H:i', strtotime($campaign->schedule_time)) : 'Not scheduled') . '</td></tr>';
    echo '<tr><td><strong>Created</strong></td><td>' . date('M d, Y H:i', strtotime($campaign->created_at)) . '</td></tr>';
    echo '</table>';
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<div class="row text-center">';
    echo '<div class="col-xs-3"><div class="panel panel-info"><div class="panel-body"><h3>' . number_format($campaign->total_recipients) . '</h3>Recipients</div></div></div>';
    echo '<div class="col-xs-3"><div class="panel panel-primary"><div class="panel-body"><h3>' . number_format($campaign->sent_count) . '</h3>Sent</div></div></div>';
    echo '<div class="col-xs-3"><div class="panel panel-success"><div class="panel-body"><h3>' . number_format($campaign->delivered_count) . '</h3>Delivered</div></div></div>';
    echo '<div class="col-xs-3"><div class="panel panel-danger"><div class="panel-body"><h3>' . number_format($campaign->failed_count) . '</h3>Failed</div></div></div>';
    echo '</div>';
    $deliveryRate = $campaign->sent_count > 0 ? round(($campaign->delivered_count / $campaign->sent_count) * 100, 1) : 0;
    echo '<div class="progress" style="height: 25px;">';
    echo '<div class="progress-bar progress-bar-success" style="width: ' . $deliveryRate . '%">' . $deliveryRate . '% Delivered</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Message content
    echo '<hr><h4>Message Content</h4>';
    echo '<div class="well">' . nl2br(htmlspecialchars($campaign->message)) . '</div>';

    // Target groups
    if (count($groups) > 0) {
        echo '<h4>Target Groups</h4>';
        echo '<ul>';
        foreach ($groups as $g) {
            echo '<li>' . htmlspecialchars($g->name) . ' (' . number_format($g->contact_count) . ' contacts)</li>';
        }
        echo '</ul>';
    }

    // Recent messages
    if (count($messages) > 0) {
        echo '<hr><h4>Recent Messages (Last 50)</h4>';
        echo '<div class="table-responsive"><table class="table table-sm table-striped">';
        echo '<thead><tr><th>To</th><th>Status</th><th>Segments</th><th>Cost</th><th>Sent At</th></tr></thead><tbody>';
        foreach ($messages as $msg) {
            $msgStatus = $msg->status === 'delivered' ? '<span class="label label-success">Delivered</span>'
                : ($msg->status === 'failed' ? '<span class="label label-danger">Failed</span>'
                : '<span class="label label-default">' . ucfirst($msg->status) . '</span>');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($msg->to_number) . '</td>';
            echo '<td>' . $msgStatus . '</td>';
            echo '<td>' . $msg->segments . '</td>';
            echo '<td>' . number_format($msg->cost, 4) . '</td>';
            echo '<td>' . date('M d H:i', strtotime($msg->created_at)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '<hr>';
    echo '<a href="' . $modulelink . '&action=campaigns" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Campaigns</a>';

    echo '</div></div></div>';
}

/**
 * Messages page
 */
function sms_suite_admin_messages($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    // Get filter values
    $filterStatus = $_GET['status'] ?? '';
    $filterGateway = $_GET['gateway_id'] ?? '';
    $filterClient = $_GET['client_id'] ?? '';
    $filterDateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
    $filterDateTo = $_GET['date_to'] ?? date('Y-m-d');
    $filterSearch = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 50;

    // Get gateways for filter dropdown
    $gateways = Capsule::table('mod_sms_gateways')->orderBy('name')->get();

    // Build query
    $query = Capsule::table('mod_sms_messages')
        ->leftJoin('mod_sms_gateways', 'mod_sms_messages.gateway_id', '=', 'mod_sms_gateways.id')
        ->leftJoin('tblclients', 'mod_sms_messages.client_id', '=', 'tblclients.id')
        ->select([
            'mod_sms_messages.*',
            'mod_sms_gateways.name as gateway_name',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.email as client_email',
        ]);

    // Apply filters
    if (!empty($filterStatus)) {
        $query->where('mod_sms_messages.status', $filterStatus);
    }
    if (!empty($filterGateway)) {
        $query->where('mod_sms_messages.gateway_id', $filterGateway);
    }
    if (!empty($filterClient)) {
        $query->where('mod_sms_messages.client_id', $filterClient);
    }
    if (!empty($filterDateFrom)) {
        $query->whereDate('mod_sms_messages.created_at', '>=', $filterDateFrom);
    }
    if (!empty($filterDateTo)) {
        $query->whereDate('mod_sms_messages.created_at', '<=', $filterDateTo);
    }
    if (!empty($filterSearch)) {
        $query->where(function($q) use ($filterSearch) {
            $q->where('mod_sms_messages.to_number', 'like', "%{$filterSearch}%")
              ->orWhere('mod_sms_messages.message', 'like', "%{$filterSearch}%")
              ->orWhere('mod_sms_messages.sender_id', 'like', "%{$filterSearch}%");
        });
    }

    // Get counts by status
    $statusCounts = Capsule::table('mod_sms_messages')
        ->selectRaw('status, COUNT(*) as count')
        ->whereDate('created_at', '>=', $filterDateFrom)
        ->whereDate('created_at', '<=', $filterDateTo)
        ->groupBy('status')
        ->pluck('count', 'status')
        ->toArray();

    $totalCount = array_sum($statusCounts);
    $deliveredCount = $statusCounts['delivered'] ?? 0;
    $failedCount = $statusCounts['failed'] ?? 0;
    $pendingCount = ($statusCounts['queued'] ?? 0) + ($statusCounts['sending'] ?? 0) + ($statusCounts['sent'] ?? 0);

    // Paginate
    $total = (clone $query)->count();
    $messages = $query->orderBy('mod_sms_messages.created_at', 'desc')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

    $totalPages = ceil($total / $perPage);

    // Statistics cards
    echo '<div class="row" style="margin-bottom: 20px;">';
    echo '<div class="col-md-3"><div class="panel panel-info"><div class="panel-body text-center">';
    echo '<h3 style="margin:0;">' . number_format($totalCount) . '</h3><small>Total Messages</small>';
    echo '</div></div></div>';
    echo '<div class="col-md-3"><div class="panel panel-success"><div class="panel-body text-center">';
    echo '<h3 style="margin:0;">' . number_format($deliveredCount) . '</h3><small>Delivered</small>';
    echo '</div></div></div>';
    echo '<div class="col-md-3"><div class="panel panel-danger"><div class="panel-body text-center">';
    echo '<h3 style="margin:0;">' . number_format($failedCount) . '</h3><small>Failed</small>';
    echo '</div></div></div>';
    echo '<div class="col-md-3"><div class="panel panel-warning"><div class="panel-body text-center">';
    echo '<h3 style="margin:0;">' . number_format($pendingCount) . '</h3><small>Pending/Sending</small>';
    echo '</div></div></div>';
    echo '</div>';

    // Filter form
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-filter"></i> Filters & Search</h3></div>';
    echo '<div class="panel-body">';
    echo '<form method="get" class="form-inline">';
    echo '<input type="hidden" name="module" value="sms_suite">';
    echo '<input type="hidden" name="action" value="messages">';

    // Status filter
    echo '<div class="form-group" style="margin-right: 10px;">';
    echo '<select name="status" class="form-control">';
    echo '<option value="">All Status</option>';
    $statuses = ['queued', 'sending', 'sent', 'delivered', 'failed', 'rejected', 'expired'];
    foreach ($statuses as $status) {
        $selected = ($filterStatus === $status) ? 'selected' : '';
        $count = $statusCounts[$status] ?? 0;
        echo '<option value="' . $status . '" ' . $selected . '>' . ucfirst($status) . ' (' . $count . ')</option>';
    }
    echo '</select></div>';

    // Gateway filter
    echo '<div class="form-group" style="margin-right: 10px;">';
    echo '<select name="gateway_id" class="form-control">';
    echo '<option value="">All Gateways</option>';
    foreach ($gateways as $gw) {
        $selected = ($filterGateway == $gw->id) ? 'selected' : '';
        echo '<option value="' . $gw->id . '" ' . $selected . '>' . htmlspecialchars($gw->name) . '</option>';
    }
    echo '</select></div>';

    // Date range
    echo '<div class="form-group" style="margin-right: 10px;">';
    echo '<input type="date" name="date_from" class="form-control" value="' . $filterDateFrom . '">';
    echo ' to ';
    echo '<input type="date" name="date_to" class="form-control" value="' . $filterDateTo . '">';
    echo '</div>';

    // Search
    echo '<div class="form-group" style="margin-right: 10px;">';
    echo '<input type="text" name="search" class="form-control" placeholder="Search phone/message..." value="' . htmlspecialchars($filterSearch) . '">';
    echo '</div>';

    echo '<button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>';
    echo ' <a href="' . $modulelink . '&action=messages" class="btn btn-default">Reset</a>';
    echo '</form>';
    echo '</div></div>';

    // Messages table
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title" style="display:inline-block;"><i class="fa fa-list"></i> ' . $lang['messages'] . ' (' . number_format($total) . ' results)</h3>';
    echo '<div class="pull-right">';
    echo '<a href="' . $modulelink . '&action=messages&export=csv&' . http_build_query($_GET) . '" class="btn btn-xs btn-info"><i class="fa fa-download"></i> Export CSV</a>';
    echo '</div>';
    echo '</div>';
    echo '<div class="panel-body" style="padding:0;">';

    if (count($messages) > 0) {
        echo '<table class="table table-striped table-hover" style="margin:0;">';
        echo '<thead><tr>';
        echo '<th width="50">ID</th>';
        echo '<th>Recipient</th>';
        echo '<th>Sender</th>';
        echo '<th>Message</th>';
        echo '<th width="100">Status</th>';
        echo '<th>Gateway</th>';
        echo '<th width="80">Segments</th>';
        echo '<th width="120">Date</th>';
        echo '<th width="80">Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($messages as $msg) {
            $statusClass = sms_suite_status_class($msg->status);
            $msgPreview = strlen($msg->message) > 40 ? substr($msg->message, 0, 40) . '...' : $msg->message;
            $hasError = !empty($msg->error);
            $rowClass = $hasError ? 'danger' : '';

            echo '<tr class="' . $rowClass . '">';
            echo '<td><small>' . $msg->id . '</small></td>';
            echo '<td><code>' . htmlspecialchars($msg->to_number) . '</code></td>';
            echo '<td>' . htmlspecialchars($msg->sender_id ?: '-') . '</td>';
            echo '<td title="' . htmlspecialchars($msg->message) . '">' . htmlspecialchars($msgPreview) . '</td>';
            echo '<td>';
            echo '<span class="label label-' . $statusClass . '">' . ucfirst($msg->status) . '</span>';
            if ($hasError) {
                echo ' <i class="fa fa-exclamation-triangle text-danger" title="Has error"></i>';
            }
            echo '</td>';
            echo '<td><small>' . htmlspecialchars($msg->gateway_name ?: 'N/A') . '</small></td>';
            echo '<td>' . $msg->segments . '</td>';
            echo '<td><small>' . date('M j, H:i', strtotime($msg->created_at)) . '</small></td>';
            echo '<td>';
            echo '<button type="button" class="btn btn-xs btn-info" onclick="viewMessageDetails(' . $msg->id . ')" title="View Details"><i class="fa fa-eye"></i></button> ';
            if ($msg->status === 'failed') {
                echo '<button type="button" class="btn btn-xs btn-warning" onclick="retryMessage(' . $msg->id . ')" title="Retry"><i class="fa fa-refresh"></i></button>';
            }
            echo '</td>';
            echo '</tr>';

            // Show error row if exists
            if ($hasError) {
                echo '<tr class="danger"><td colspan="9" style="padding: 5px 15px; background: #f2dede;">';
                echo '<small><strong><i class="fa fa-exclamation-circle"></i> Error:</strong> ' . htmlspecialchars($msg->error) . '</small>';
                echo '</td></tr>';
            }
        }

        echo '</tbody></table>';

        // Pagination
        if ($totalPages > 1) {
            echo '<div class="panel-footer">';
            echo '<nav><ul class="pagination" style="margin:0;">';
            for ($i = 1; $i <= $totalPages; $i++) {
                $active = ($i === $page) ? 'active' : '';
                $pageUrl = $modulelink . '&action=messages&page=' . $i . '&status=' . urlencode($filterStatus) . '&gateway_id=' . $filterGateway . '&date_from=' . $filterDateFrom . '&date_to=' . $filterDateTo . '&search=' . urlencode($filterSearch);
                echo '<li class="' . $active . '"><a href="' . $pageUrl . '">' . $i . '</a></li>';
            }
            echo '</ul></nav>';
            echo '</div>';
        }
    } else {
        echo '<div class="text-center" style="padding: 40px;"><p class="text-muted"><i class="fa fa-inbox fa-3x"></i><br><br>No messages found matching your criteria.</p></div>';
    }

    echo '</div></div>';

    // Message detail modal
    echo '
    <div class="modal fade" id="messageDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-envelope"></i> Message Details</h4>
                </div>
                <div class="modal-body" id="messageDetailContent">
                    <div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>';

    // JavaScript for viewing details
    echo '
    <script>
    function viewMessageDetails(msgId) {
        jQuery("#messageDetailContent").html("<div class=\"text-center\"><i class=\"fa fa-spinner fa-spin fa-2x\"></i> Loading...</div>");
        jQuery("#messageDetailModal").modal("show");

        jQuery.ajax({
            url: "addonmodules.php?module=sms_suite&action=ajax_message_detail&id=" + msgId,
            success: function(data) {
                jQuery("#messageDetailContent").html(data);
            },
            error: function() {
                jQuery("#messageDetailContent").html("<div class=\"alert alert-danger\">Failed to load message details.</div>");
            }
        });
    }

    function retryMessage(msgId) {
        if (!confirm("Retry sending this message?")) return;

        jQuery.ajax({
            url: "addonmodules.php?module=sms_suite&action=ajax_retry_message&id=" + msgId,
            method: "POST",
            success: function(data) {
                alert(data.message || "Message queued for retry");
                location.reload();
            },
            error: function() {
                alert("Failed to retry message");
            }
        });
    }
    </script>';

    echo '</div>';
}

/**
 * Templates page - Notification Template Management
 */
function sms_suite_admin_templates($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $message = '';
    $messageType = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_template'])) {
            $data = [
                'name' => trim($_POST['name']),
                'category' => $_POST['category'] ?? 'general',
                'type' => $_POST['type'] ?? 'sms',
                'trigger_hook' => $_POST['trigger_hook'] ?? null,
                'content' => $_POST['content'],
                'variables' => $_POST['variables'] ?? null,
                'status' => isset($_POST['status']) ? 1 : 0,
                'send_to_client' => isset($_POST['send_to_client']) ? 1 : 0,
                'send_to_admin' => isset($_POST['send_to_admin']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if (!empty($_POST['template_id'])) {
                Capsule::table('mod_sms_notification_templates')->where('id', (int)$_POST['template_id'])->update($data);
                $message = 'Template updated successfully.';
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('mod_sms_notification_templates')->insert($data);
                $message = 'Template created successfully.';
            }
            $messageType = 'success';
        }

        if (isset($_POST['delete_template'])) {
            Capsule::table('mod_sms_notification_templates')->where('id', (int)$_POST['template_id'])->delete();
            $message = 'Template deleted.';
            $messageType = 'success';
        }
    }

    // Get templates
    $templates = Capsule::table('mod_sms_notification_templates')->orderBy('category')->orderBy('name')->get();

    // Available WHMCS hooks for triggers
    $availableHooks = [
        'ClientAdd' => 'New Client Registration',
        'ClientLogin' => 'Client Login',
        'InvoiceCreated' => 'Invoice Created',
        'InvoicePaid' => 'Invoice Paid',
        'InvoicePaymentReminder' => 'Invoice Payment Reminder',
        'InvoiceCancelled' => 'Invoice Cancelled',
        'OrderPaid' => 'Order Paid',
        'AfterModuleCreate' => 'Service Activated',
        'AfterModuleSuspend' => 'Service Suspended',
        'AfterModuleUnsuspend' => 'Service Unsuspended',
        'AfterModuleTerminate' => 'Service Terminated',
        'TicketOpen' => 'Ticket Opened',
        'TicketAdminReply' => 'Ticket Admin Reply',
        'TicketStatusChange' => 'Ticket Status Changed',
        'DomainRegister' => 'Domain Registered',
        'DomainTransferCompleted' => 'Domain Transfer Complete',
        'DomainRenewal' => 'Domain Renewed',
        'DomainExpiryNotice' => 'Domain Expiry Notice',
    ];

    // Template categories
    $categories = ['general' => 'General', 'billing' => 'Billing', 'support' => 'Support', 'services' => 'Services', 'domains' => 'Domains', 'marketing' => 'Marketing'];

    // Available variables
    $variableGroups = [
        'Client' => ['{client_name}', '{client_firstname}', '{client_lastname}', '{client_email}', '{client_phone}', '{client_company}'],
        'Invoice' => ['{invoice_id}', '{invoice_num}', '{invoice_total}', '{invoice_due_date}', '{invoice_status}'],
        'Service' => ['{service_name}', '{service_domain}', '{service_status}', '{service_next_due}'],
        'Ticket' => ['{ticket_id}', '{ticket_subject}', '{ticket_status}', '{ticket_department}'],
        'Domain' => ['{domain_name}', '{domain_expiry}', '{domain_status}'],
        'System' => ['{company_name}', '{whmcs_url}', '{date}', '{time}'],
    ];

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title" style="display: inline-block;"><i class="fa fa-file-text"></i> ' . $lang['templates'] . '</h3>';
    echo '<button class="btn btn-success btn-sm pull-right" onclick="openTemplateModal()"><i class="fa fa-plus"></i> New Template</button>';
    echo '</div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    // Templates table
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Name</th><th>Category</th><th>Trigger</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    foreach ($templates as $tpl) {
        $statusLabel = $tpl->status ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';
        $triggerName = $availableHooks[$tpl->trigger_hook] ?? ($tpl->trigger_hook ?: 'Manual');

        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($tpl->name) . '</strong></td>';
        echo '<td>' . ucfirst($tpl->category) . '</td>';
        echo '<td><small>' . htmlspecialchars($triggerName) . '</small></td>';
        echo '<td>' . strtoupper($tpl->type) . '</td>';
        echo '<td>' . $statusLabel . '</td>';
        echo '<td>';
        echo '<button class="btn btn-xs btn-primary" onclick=\'editTemplate(' . json_encode($tpl) . ')\'><i class="fa fa-edit"></i></button> ';
        echo '<button class="btn btn-xs btn-danger" onclick="deleteTemplate(' . $tpl->id . ')"><i class="fa fa-trash"></i></button>';
        echo '</td>';
        echo '</tr>';
    }

    if (count($templates) == 0) {
        echo '<tr><td colspan="6" class="text-center text-muted">No templates found. Create your first notification template.</td></tr>';
    }

    echo '</tbody></table></div>';

    // Variables Reference
    echo '<hr><h4>Available Variables</h4>';
    echo '<div class="row">';
    foreach ($variableGroups as $group => $vars) {
        echo '<div class="col-md-2"><strong>' . $group . '</strong><br>';
        foreach ($vars as $v) {
            echo '<code style="font-size: 10px;">' . $v . '</code><br>';
        }
        echo '</div>';
    }
    echo '</div>';

    echo '</div></div>';

    // Template Modal
    echo '<div class="modal fade" id="templateModal" tabindex="-1">';
    echo '<div class="modal-dialog modal-lg"><div class="modal-content">';
    echo '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>';
    echo '<h4 class="modal-title" id="templateModalTitle">New Template</h4></div>';
    echo '<form method="post">';
    echo '<div class="modal-body">';
    echo '<input type="hidden" name="template_id" id="tpl_id">';

    echo '<div class="row">';
    echo '<div class="col-md-6"><div class="form-group"><label>Template Name *</label>';
    echo '<input type="text" name="name" id="tpl_name" class="form-control" required></div></div>';
    echo '<div class="col-md-3"><div class="form-group"><label>Category</label>';
    echo '<select name="category" id="tpl_category" class="form-control">';
    foreach ($categories as $k => $v) {
        echo '<option value="' . $k . '">' . $v . '</option>';
    }
    echo '</select></div></div>';
    echo '<div class="col-md-3"><div class="form-group"><label>Type</label>';
    echo '<select name="type" id="tpl_type" class="form-control">';
    echo '<option value="sms">SMS</option><option value="whatsapp">WhatsApp</option>';
    echo '</select></div></div>';
    echo '</div>';

    echo '<div class="form-group"><label>Trigger Hook (WHMCS Event)</label>';
    echo '<select name="trigger_hook" id="tpl_trigger" class="form-control"><option value="">Manual Only</option>';
    foreach ($availableHooks as $hook => $label) {
        echo '<option value="' . $hook . '">' . $label . ' (' . $hook . ')</option>';
    }
    echo '</select></div>';

    echo '<div class="form-group"><label>Message Content *</label>';
    echo '<textarea name="content" id="tpl_content" class="form-control" rows="5" required placeholder="Use variables like {client_name}, {invoice_total}, etc."></textarea>';
    echo '<small class="text-muted">Character count: <span id="charCount">0</span> | SMS segments: <span id="segmentCount">1</span></small></div>';

    echo '<div class="row">';
    echo '<div class="col-md-4"><div class="checkbox"><label><input type="checkbox" name="status" id="tpl_status" value="1" checked> Active</label></div></div>';
    echo '<div class="col-md-4"><div class="checkbox"><label><input type="checkbox" name="send_to_client" id="tpl_client" value="1" checked> Send to Client</label></div></div>';
    echo '<div class="col-md-4"><div class="checkbox"><label><input type="checkbox" name="send_to_admin" id="tpl_admin" value="1"> Send to Admin</label></div></div>';
    echo '</div>';

    echo '</div>';
    echo '<div class="modal-footer">';
    echo '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>';
    echo '<button type="submit" name="save_template" class="btn btn-primary">Save Template</button>';
    echo '</div></form></div></div></div>';

    // Delete form
    echo '<form method="post" id="deleteForm"><input type="hidden" name="template_id" id="delete_id"><input type="hidden" name="delete_template" value="1"></form>';

    // JavaScript
    echo '<script>
    function openTemplateModal() {
        document.getElementById("templateModalTitle").textContent = "New Template";
        document.getElementById("tpl_id").value = "";
        document.getElementById("tpl_name").value = "";
        document.getElementById("tpl_category").value = "general";
        document.getElementById("tpl_type").value = "sms";
        document.getElementById("tpl_trigger").value = "";
        document.getElementById("tpl_content").value = "";
        document.getElementById("tpl_status").checked = true;
        document.getElementById("tpl_client").checked = true;
        document.getElementById("tpl_admin").checked = false;
        $("#templateModal").modal("show");
    }

    function editTemplate(tpl) {
        document.getElementById("templateModalTitle").textContent = "Edit Template";
        document.getElementById("tpl_id").value = tpl.id;
        document.getElementById("tpl_name").value = tpl.name;
        document.getElementById("tpl_category").value = tpl.category;
        document.getElementById("tpl_type").value = tpl.type;
        document.getElementById("tpl_trigger").value = tpl.trigger_hook || "";
        document.getElementById("tpl_content").value = tpl.content;
        document.getElementById("tpl_status").checked = tpl.status == 1;
        document.getElementById("tpl_client").checked = tpl.send_to_client == 1;
        document.getElementById("tpl_admin").checked = tpl.send_to_admin == 1;
        updateCharCount();
        $("#templateModal").modal("show");
    }

    function deleteTemplate(id) {
        if (confirm("Are you sure you want to delete this template?")) {
            document.getElementById("delete_id").value = id;
            document.getElementById("deleteForm").submit();
        }
    }

    function updateCharCount() {
        var content = document.getElementById("tpl_content").value;
        var len = content.length;
        document.getElementById("charCount").textContent = len;
        var segments = len <= 160 ? 1 : Math.ceil(len / 153);
        document.getElementById("segmentCount").textContent = segments;
    }

    document.getElementById("tpl_content").addEventListener("input", updateCharCount);
    </script>';

    echo '</div>';
}

/**
 * Automation page - SMS Automation Rules
 */
function sms_suite_admin_automation($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $message = '';
    $messageType = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_automation'])) {
            $data = [
                'name' => trim($_POST['name']),
                'trigger_type' => $_POST['trigger_type'],
                'trigger_config' => json_encode($_POST['trigger_config'] ?? []),
                'message_template' => $_POST['message_template'],
                'sender_id' => $_POST['sender_id'] ?: null,
                'gateway_id' => !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null,
                'status' => $_POST['status'] ?? 'active',
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if (!empty($_POST['automation_id'])) {
                Capsule::table('mod_sms_automations')->where('id', (int)$_POST['automation_id'])->update($data);
                $message = 'Automation updated successfully.';
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['run_count'] = 0;
                Capsule::table('mod_sms_automations')->insert($data);
                $message = 'Automation created successfully.';
            }
            $messageType = 'success';
        }

        if (isset($_POST['delete_automation'])) {
            Capsule::table('mod_sms_automations')->where('id', (int)$_POST['automation_id'])->delete();
            $message = 'Automation deleted.';
            $messageType = 'success';
        }

        if (isset($_POST['toggle_status'])) {
            $auto = Capsule::table('mod_sms_automations')->where('id', (int)$_POST['automation_id'])->first();
            $newStatus = $auto->status === 'active' ? 'inactive' : 'active';
            Capsule::table('mod_sms_automations')->where('id', (int)$_POST['automation_id'])->update(['status' => $newStatus]);
            $message = 'Automation ' . ($newStatus === 'active' ? 'activated' : 'deactivated') . '.';
            $messageType = 'success';
        }
    }

    // Get automations
    $automations = Capsule::table('mod_sms_automations')
        ->leftJoin('mod_sms_gateways', 'mod_sms_automations.gateway_id', '=', 'mod_sms_gateways.id')
        ->select('mod_sms_automations.*', 'mod_sms_gateways.name as gateway_name')
        ->orderBy('mod_sms_automations.name')
        ->get();

    // Get gateways
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->orderBy('name')->get();

    // Available trigger types
    $triggerTypes = [
        'whmcs_hook' => ['name' => 'WHMCS Event Hook', 'description' => 'Triggered by WHMCS system events'],
        'invoice_overdue' => ['name' => 'Invoice Overdue', 'description' => 'When invoice becomes overdue by X days'],
        'service_expiry' => ['name' => 'Service Expiry Warning', 'description' => 'X days before service expires'],
        'domain_expiry' => ['name' => 'Domain Expiry Warning', 'description' => 'X days before domain expires'],
        'credit_low' => ['name' => 'Low Credit Balance', 'description' => 'When credit balance falls below threshold'],
        'birthday' => ['name' => 'Client Birthday', 'description' => 'On client birthday (requires date of birth field)'],
        'scheduled' => ['name' => 'Scheduled Time', 'description' => 'Run at specific times/dates'],
    ];

    // WHMCS hooks
    $whmcsHooks = [
        'ClientAdd' => 'New Client Registration',
        'ClientLogin' => 'Client Login',
        'InvoiceCreated' => 'Invoice Created',
        'InvoicePaid' => 'Invoice Paid',
        'InvoiceUnpaid' => 'Invoice Unpaid',
        'OrderPaid' => 'Order Paid',
        'AfterModuleCreate' => 'Service Activated',
        'AfterModuleSuspend' => 'Service Suspended',
        'AfterModuleUnsuspend' => 'Service Unsuspended',
        'AfterModuleTerminate' => 'Service Terminated',
        'TicketOpen' => 'Ticket Opened',
        'TicketAdminReply' => 'Admin Replied to Ticket',
        'TicketUserReply' => 'Client Replied to Ticket',
    ];

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title" style="display: inline-block;"><i class="fa fa-magic"></i> ' . $lang['automation'] . '</h3>';
    echo '<button class="btn btn-success btn-sm pull-right" onclick="openAutomationModal()"><i class="fa fa-plus"></i> New Automation</button>';
    echo '</div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    // Automations table
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Name</th><th>Trigger</th><th>Gateway</th><th>Run Count</th><th>Last Run</th><th>Status</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    foreach ($automations as $auto) {
        $statusLabel = $auto->status === 'active'
            ? '<span class="label label-success">Active</span>'
            : '<span class="label label-default">Inactive</span>';
        $triggerName = $triggerTypes[$auto->trigger_type]['name'] ?? $auto->trigger_type;
        $lastRun = $auto->last_run ? date('M d, Y H:i', strtotime($auto->last_run)) : 'Never';

        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($auto->name) . '</strong></td>';
        echo '<td>' . htmlspecialchars($triggerName) . '</td>';
        echo '<td>' . htmlspecialchars($auto->gateway_name ?? 'Default') . '</td>';
        echo '<td><span class="badge">' . number_format($auto->run_count) . '</span></td>';
        echo '<td><small>' . $lastRun . '</small></td>';
        echo '<td>' . $statusLabel . '</td>';
        echo '<td>';
        echo '<button class="btn btn-xs btn-primary" onclick=\'editAutomation(' . json_encode($auto) . ')\'><i class="fa fa-edit"></i></button> ';
        echo '<form method="post" style="display:inline;"><input type="hidden" name="automation_id" value="' . $auto->id . '">';
        echo '<button type="submit" name="toggle_status" class="btn btn-xs btn-warning"><i class="fa fa-power-off"></i></button></form> ';
        echo '<button class="btn btn-xs btn-danger" onclick="deleteAutomation(' . $auto->id . ')"><i class="fa fa-trash"></i></button>';
        echo '</td>';
        echo '</tr>';
    }

    if (count($automations) == 0) {
        echo '<tr><td colspan="7" class="text-center text-muted">No automations configured. Create your first automation rule.</td></tr>';
    }

    echo '</tbody></table></div>';

    // Recent automation logs
    $recentLogs = Capsule::table('mod_sms_automation_logs')
        ->leftJoin('mod_sms_automations', 'mod_sms_automation_logs.automation_id', '=', 'mod_sms_automations.id')
        ->select('mod_sms_automation_logs.*', 'mod_sms_automations.name as automation_name')
        ->orderBy('mod_sms_automation_logs.created_at', 'desc')
        ->limit(10)
        ->get();

    if (count($recentLogs) > 0) {
        echo '<hr><h4>Recent Automation Activity</h4>';
        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Automation</th><th>Status</th><th>Error</th><th>Date</th></tr></thead><tbody>';
        foreach ($recentLogs as $log) {
            $logStatus = $log->status === 'sent'
                ? '<span class="label label-success">Sent</span>'
                : '<span class="label label-danger">' . ucfirst($log->status) . '</span>';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($log->automation_name ?? 'Unknown') . '</td>';
            echo '<td>' . $logStatus . '</td>';
            echo '<td><small>' . htmlspecialchars($log->error ?: '-') . '</small></td>';
            echo '<td><small>' . date('M d H:i', strtotime($log->created_at)) . '</small></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div></div>';

    // Automation Modal
    echo '<div class="modal fade" id="automationModal" tabindex="-1">';
    echo '<div class="modal-dialog modal-lg"><div class="modal-content">';
    echo '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>';
    echo '<h4 class="modal-title" id="automationModalTitle">New Automation</h4></div>';
    echo '<form method="post">';
    echo '<div class="modal-body">';
    echo '<input type="hidden" name="automation_id" id="auto_id">';

    echo '<div class="row">';
    echo '<div class="col-md-8"><div class="form-group"><label>Automation Name *</label>';
    echo '<input type="text" name="name" id="auto_name" class="form-control" required></div></div>';
    echo '<div class="col-md-4"><div class="form-group"><label>Status</label>';
    echo '<select name="status" id="auto_status" class="form-control">';
    echo '<option value="active">Active</option><option value="inactive">Inactive</option>';
    echo '</select></div></div>';
    echo '</div>';

    echo '<div class="row">';
    echo '<div class="col-md-6"><div class="form-group"><label>Trigger Type *</label>';
    echo '<select name="trigger_type" id="auto_trigger" class="form-control" onchange="showTriggerConfig()">';
    foreach ($triggerTypes as $key => $type) {
        echo '<option value="' . $key . '">' . $type['name'] . '</option>';
    }
    echo '</select></div></div>';
    echo '<div class="col-md-6"><div class="form-group"><label>Gateway</label>';
    echo '<select name="gateway_id" id="auto_gateway" class="form-control"><option value="">Default Gateway</option>';
    foreach ($gateways as $gw) {
        echo '<option value="' . $gw->id . '">' . htmlspecialchars($gw->name) . '</option>';
    }
    echo '</select></div></div>';
    echo '</div>';

    // Trigger config section (dynamic)
    echo '<div id="triggerConfigSection">';
    echo '<div class="form-group" id="hookSelect" style="display:none;"><label>WHMCS Hook</label>';
    echo '<select name="trigger_config[hook]" id="auto_hook" class="form-control">';
    foreach ($whmcsHooks as $hook => $label) {
        echo '<option value="' . $hook . '">' . $label . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group" id="daysInput" style="display:none;"><label>Days Before/After</label>';
    echo '<input type="number" name="trigger_config[days]" id="auto_days" class="form-control" value="3" min="1"></div>';
    echo '<div class="form-group" id="thresholdInput" style="display:none;"><label>Credit Threshold</label>';
    echo '<input type="number" name="trigger_config[threshold]" id="auto_threshold" class="form-control" value="100" min="1"></div>';
    echo '</div>';

    echo '<div class="form-group"><label>Sender ID</label>';
    echo '<input type="text" name="sender_id" id="auto_sender" class="form-control" placeholder="Leave blank for default"></div>';

    echo '<div class="form-group"><label>Message Template *</label>';
    echo '<textarea name="message_template" id="auto_message" class="form-control" rows="4" required placeholder="Use variables like {client_name}, {invoice_total}, etc."></textarea></div>';

    echo '</div>';
    echo '<div class="modal-footer">';
    echo '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>';
    echo '<button type="submit" name="save_automation" class="btn btn-primary">Save Automation</button>';
    echo '</div></form></div></div></div>';

    // Delete form
    echo '<form method="post" id="deleteAutoForm"><input type="hidden" name="automation_id" id="delete_auto_id"><input type="hidden" name="delete_automation" value="1"></form>';

    // JavaScript
    echo '<script>
    function showTriggerConfig() {
        var type = document.getElementById("auto_trigger").value;
        document.getElementById("hookSelect").style.display = (type === "whmcs_hook") ? "block" : "none";
        document.getElementById("daysInput").style.display = (["invoice_overdue", "service_expiry", "domain_expiry"].includes(type)) ? "block" : "none";
        document.getElementById("thresholdInput").style.display = (type === "credit_low") ? "block" : "none";
    }

    function openAutomationModal() {
        document.getElementById("automationModalTitle").textContent = "New Automation";
        document.getElementById("auto_id").value = "";
        document.getElementById("auto_name").value = "";
        document.getElementById("auto_trigger").value = "whmcs_hook";
        document.getElementById("auto_status").value = "active";
        document.getElementById("auto_gateway").value = "";
        document.getElementById("auto_sender").value = "";
        document.getElementById("auto_message").value = "";
        showTriggerConfig();
        $("#automationModal").modal("show");
    }

    function editAutomation(auto) {
        document.getElementById("automationModalTitle").textContent = "Edit Automation";
        document.getElementById("auto_id").value = auto.id;
        document.getElementById("auto_name").value = auto.name;
        document.getElementById("auto_trigger").value = auto.trigger_type;
        document.getElementById("auto_status").value = auto.status;
        document.getElementById("auto_gateway").value = auto.gateway_id || "";
        document.getElementById("auto_sender").value = auto.sender_id || "";
        document.getElementById("auto_message").value = auto.message_template;

        var config = {};
        try { config = JSON.parse(auto.trigger_config || "{}"); } catch(e) {}
        if (config.hook) document.getElementById("auto_hook").value = config.hook;
        if (config.days) document.getElementById("auto_days").value = config.days;
        if (config.threshold) document.getElementById("auto_threshold").value = config.threshold;

        showTriggerConfig();
        $("#automationModal").modal("show");
    }

    function deleteAutomation(id) {
        if (confirm("Are you sure you want to delete this automation?")) {
            document.getElementById("delete_auto_id").value = id;
            document.getElementById("deleteAutoForm").submit();
        }
    }

    showTriggerConfig();
    </script>';

    echo '</div>';
}

/**
 * Reports page - SMS Analytics and Statistics
 */
function sms_suite_admin_reports($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    // Date range filter
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $gatewayFilter = $_GET['gateway_id'] ?? '';

    // Get gateways for filter
    $gateways = Capsule::table('mod_sms_gateways')->orderBy('name')->get();

    // Build base query conditions (with table prefix for JOINs)
    $dateCondition = function ($query) use ($dateFrom, $dateTo) {
        $query->whereDate('mod_sms_messages.created_at', '>=', $dateFrom)
              ->whereDate('mod_sms_messages.created_at', '<=', $dateTo);
    };

    // Overall Statistics
    $totalMessages = Capsule::table('mod_sms_messages')->where($dateCondition)->count();
    $sentMessages = Capsule::table('mod_sms_messages')->where($dateCondition)->where('status', 'sent')->count();
    $deliveredMessages = Capsule::table('mod_sms_messages')->where($dateCondition)->where('status', 'delivered')->count();
    $failedMessages = Capsule::table('mod_sms_messages')->where($dateCondition)->where('status', 'failed')->count();
    $totalCost = Capsule::table('mod_sms_messages')->where($dateCondition)->sum('cost');
    $totalSegments = Capsule::table('mod_sms_messages')->where($dateCondition)->sum('segments');

    // Delivery rate
    $deliveryRate = $totalMessages > 0 ? round(($deliveredMessages / $totalMessages) * 100, 1) : 0;

    // Messages by status
    $statusStats = Capsule::table('mod_sms_messages')
        ->select('status', Capsule::raw('COUNT(*) as count'))
        ->where($dateCondition)
        ->groupBy('status')
        ->get();

    // Messages by gateway
    $gatewayStats = Capsule::table('mod_sms_messages')
        ->leftJoin('mod_sms_gateways', 'mod_sms_messages.gateway_id', '=', 'mod_sms_gateways.id')
        ->select('mod_sms_gateways.name', Capsule::raw('COUNT(*) as count'), Capsule::raw('SUM(mod_sms_messages.cost) as total_cost'))
        ->where($dateCondition)
        ->groupBy('mod_sms_messages.gateway_id', 'mod_sms_gateways.name')
        ->get();

    // Daily message volume (last 30 days)
    $dailyStats = Capsule::table('mod_sms_messages')
        ->select(Capsule::raw('DATE(mod_sms_messages.created_at) as date'), Capsule::raw('COUNT(*) as count'))
        ->where($dateCondition)
        ->groupBy(Capsule::raw('DATE(mod_sms_messages.created_at)'))
        ->orderBy('date')
        ->get();

    // Top clients by volume
    $topClients = Capsule::table('mod_sms_messages')
        ->leftJoin('tblclients', 'mod_sms_messages.client_id', '=', 'tblclients.id')
        ->select(
            'mod_sms_messages.client_id',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.companyname',
            Capsule::raw('COUNT(*) as message_count'),
            Capsule::raw('SUM(mod_sms_messages.cost) as total_cost')
        )
        ->where($dateCondition)
        ->groupBy('mod_sms_messages.client_id', 'tblclients.firstname', 'tblclients.lastname', 'tblclients.companyname')
        ->orderBy('message_count', 'desc')
        ->limit(10)
        ->get();

    // Top destinations (countries)
    $topDestinations = Capsule::table('mod_sms_messages')
        ->select(Capsule::raw('LEFT(to_number, 3) as prefix'), Capsule::raw('COUNT(*) as count'))
        ->where($dateCondition)
        ->groupBy(Capsule::raw('LEFT(to_number, 3)'))
        ->orderBy('count', 'desc')
        ->limit(10)
        ->get();

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-bar-chart"></i> ' . $lang['reports'] . '</h3></div>';
    echo '<div class="panel-body">';

    // Date filter form
    echo '<form method="get" class="form-inline" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="module" value="sms_suite">';
    echo '<input type="hidden" name="action" value="reports">';
    echo '<div class="form-group"><label>From:</label> <input type="date" name="date_from" class="form-control" value="' . $dateFrom . '"></div> ';
    echo '<div class="form-group"><label>To:</label> <input type="date" name="date_to" class="form-control" value="' . $dateTo . '"></div> ';
    echo '<div class="form-group"><label>Gateway:</label> <select name="gateway_id" class="form-control"><option value="">All Gateways</option>';
    foreach ($gateways as $gw) {
        $sel = ($gatewayFilter == $gw->id) ? 'selected' : '';
        echo '<option value="' . $gw->id . '" ' . $sel . '>' . htmlspecialchars($gw->name) . '</option>';
    }
    echo '</select></div> ';
    echo '<button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>';
    echo '</form>';

    // Summary Cards
    echo '<div class="row">';
    echo '<div class="col-md-2"><div class="panel panel-info"><div class="panel-heading">Total Messages</div>';
    echo '<div class="panel-body text-center"><h2>' . number_format($totalMessages) . '</h2></div></div></div>';
    echo '<div class="col-md-2"><div class="panel panel-success"><div class="panel-heading">Delivered</div>';
    echo '<div class="panel-body text-center"><h2>' . number_format($deliveredMessages) . '</h2></div></div></div>';
    echo '<div class="col-md-2"><div class="panel panel-danger"><div class="panel-heading">Failed</div>';
    echo '<div class="panel-body text-center"><h2>' . number_format($failedMessages) . '</h2></div></div></div>';
    echo '<div class="col-md-2"><div class="panel panel-warning"><div class="panel-heading">Delivery Rate</div>';
    echo '<div class="panel-body text-center"><h2>' . $deliveryRate . '%</h2></div></div></div>';
    echo '<div class="col-md-2"><div class="panel panel-default"><div class="panel-heading">Total Segments</div>';
    echo '<div class="panel-body text-center"><h2>' . number_format($totalSegments) . '</h2></div></div></div>';
    echo '<div class="col-md-2"><div class="panel panel-primary"><div class="panel-heading">Total Cost</div>';
    echo '<div class="panel-body text-center"><h2>' . number_format($totalCost, 2) . '</h2></div></div></div>';
    echo '</div>';

    // Charts row
    echo '<div class="row">';

    // Daily Volume Chart
    echo '<div class="col-md-8">';
    echo '<div class="panel panel-default"><div class="panel-heading"><h4>Daily Message Volume</h4></div>';
    echo '<div class="panel-body"><canvas id="dailyChart" height="100"></canvas></div></div>';
    echo '</div>';

    // Status Breakdown
    echo '<div class="col-md-4">';
    echo '<div class="panel panel-default"><div class="panel-heading"><h4>Status Breakdown</h4></div>';
    echo '<div class="panel-body"><canvas id="statusChart" height="200"></canvas></div></div>';
    echo '</div>';

    echo '</div>';

    // Tables row
    echo '<div class="row">';

    // Top Clients
    echo '<div class="col-md-6">';
    echo '<div class="panel panel-default"><div class="panel-heading"><h4>Top 10 Clients by Volume</h4></div>';
    echo '<div class="panel-body"><table class="table table-striped table-sm">';
    echo '<thead><tr><th>Client</th><th>Messages</th><th>Cost</th></tr></thead><tbody>';
    foreach ($topClients as $client) {
        $clientName = trim($client->firstname . ' ' . $client->lastname);
        if (empty($clientName)) $clientName = 'Client #' . $client->client_id;
        echo '<tr><td>' . htmlspecialchars($clientName) . '</td>';
        echo '<td>' . number_format($client->message_count) . '</td>';
        echo '<td>' . number_format($client->total_cost, 2) . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
    echo '</div>';

    // Gateway Performance
    echo '<div class="col-md-6">';
    echo '<div class="panel panel-default"><div class="panel-heading"><h4>Gateway Performance</h4></div>';
    echo '<div class="panel-body"><table class="table table-striped table-sm">';
    echo '<thead><tr><th>Gateway</th><th>Messages</th><th>Cost</th></tr></thead><tbody>';
    foreach ($gatewayStats as $gw) {
        $gwName = $gw->name ?? 'Unknown';
        echo '<tr><td>' . htmlspecialchars($gwName) . '</td>';
        echo '<td>' . number_format($gw->count) . '</td>';
        echo '<td>' . number_format($gw->total_cost ?? 0, 2) . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
    echo '</div>';

    echo '</div>';

    echo '</div></div>';

    // Chart.js
    $dailyLabels = [];
    $dailyData = [];
    foreach ($dailyStats as $day) {
        $dailyLabels[] = date('M d', strtotime($day->date));
        $dailyData[] = $day->count;
    }

    $statusLabels = [];
    $statusData = [];
    $statusColors = ['delivered' => '#27ae60', 'sent' => '#3498db', 'failed' => '#e74c3c', 'queued' => '#f39c12', 'pending' => '#95a5a6'];
    foreach ($statusStats as $stat) {
        $statusLabels[] = ucfirst($stat->status);
        $statusData[] = $stat->count;
    }

    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script>
    new Chart(document.getElementById("dailyChart"), {
        type: "line",
        data: {
            labels: ' . json_encode($dailyLabels) . ',
            datasets: [{
                label: "Messages",
                data: ' . json_encode($dailyData) . ',
                borderColor: "#3498db",
                backgroundColor: "rgba(52, 152, 219, 0.1)",
                fill: true,
                tension: 0.3
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById("statusChart"), {
        type: "doughnut",
        data: {
            labels: ' . json_encode($statusLabels) . ',
            datasets: [{
                data: ' . json_encode($statusData) . ',
                backgroundColor: ["#27ae60", "#3498db", "#e74c3c", "#f39c12", "#95a5a6"]
            }]
        },
        options: { responsive: true }
    });
    </script>';

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
 * Clients page - Per-client SMS settings
 */
function sms_suite_admin_clients($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $message = '';
    $messageType = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_client_settings'])) {
            $clientId = (int)$_POST['client_id'];
            $settings = [
                'billing_mode' => $_POST['billing_mode'] ?? 'per_segment',
                'default_gateway_id' => !empty($_POST['default_gateway_id']) ? (int)$_POST['default_gateway_id'] : null,
                'assigned_sender_id' => $_POST['assigned_sender_id'] ?? null,
                'monthly_limit' => !empty($_POST['monthly_limit']) ? (int)$_POST['monthly_limit'] : null,
                'api_enabled' => isset($_POST['api_enabled']) ? 1 : 0,
                'accept_sms' => isset($_POST['accept_sms']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $existing = Capsule::table('mod_sms_settings')->where('client_id', $clientId)->first();
            if ($existing) {
                Capsule::table('mod_sms_settings')->where('client_id', $clientId)->update($settings);
            } else {
                $settings['client_id'] = $clientId;
                $settings['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('mod_sms_settings')->insert($settings);
            }
            $message = 'Client settings saved successfully.';
            $messageType = 'success';
        }

        if (isset($_POST['add_credits'])) {
            $clientId = (int)$_POST['client_id'];
            $credits = (int)$_POST['credits'];
            $description = $_POST['description'] ?? 'Admin credit adjustment';

            // Update or create credit balance
            $balanceRow = Capsule::table('mod_sms_credit_balance')->where('client_id', $clientId)->first();
            if ($balanceRow) {
                $newBalance = $balanceRow->balance + $credits;
                $updateData = [
                    'balance' => max(0, $newBalance),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if ($credits > 0) {
                    $updateData['total_purchased'] = $balanceRow->total_purchased + $credits;
                }
                Capsule::table('mod_sms_credit_balance')->where('client_id', $clientId)->update($updateData);
            } else {
                $newBalance = max(0, $credits);
                Capsule::table('mod_sms_credit_balance')->insert([
                    'client_id' => $clientId,
                    'balance' => $newBalance,
                    'total_purchased' => max(0, $credits),
                    'total_used' => 0,
                    'total_expired' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Log transaction
            $balanceBefore = $balanceRow ? $balanceRow->balance : 0;
            Capsule::table('mod_sms_credit_transactions')->insert([
                'client_id' => $clientId,
                'type' => $credits > 0 ? 'admin_add' : 'admin_deduct',
                'credits' => $credits,
                'balance_before' => $balanceBefore,
                'balance_after' => max(0, $newBalance),
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $message = 'Credits adjusted successfully. New balance: ' . $newBalance;
            $messageType = 'success';
        }
    }

    // Get search/filter
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 25;

    // Build query
    $query = Capsule::table('tblclients')
        ->leftJoin('mod_sms_settings', 'tblclients.id', '=', 'mod_sms_settings.client_id')
        ->leftJoin('mod_sms_credit_balance', 'tblclients.id', '=', 'mod_sms_credit_balance.client_id')
        ->select([
            'tblclients.id',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.companyname',
            'tblclients.email',
            'tblclients.phonenumber',
            'mod_sms_settings.billing_mode',
            'mod_sms_settings.api_enabled',
            'mod_sms_settings.accept_sms',
            'mod_sms_settings.monthly_limit',
            'mod_sms_settings.monthly_used',
            Capsule::raw('COALESCE(mod_sms_credit_balance.balance, 0) as credit_balance'),
        ]);

    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->where('tblclients.firstname', 'LIKE', "%{$search}%")
              ->orWhere('tblclients.lastname', 'LIKE', "%{$search}%")
              ->orWhere('tblclients.email', 'LIKE', "%{$search}%")
              ->orWhere('tblclients.companyname', 'LIKE', "%{$search}%");
        });
    }

    $total = $query->count();
    $clients = $query->orderBy('tblclients.firstname')->skip(($page - 1) * $perPage)->take($perPage)->get();
    $totalPages = ceil($total / $perPage);

    // Get gateways for dropdown
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->orderBy('name')->get();

    // Get sender IDs for dropdown (from both old table and pool)
    $senderIds = Capsule::table('mod_sms_sender_ids')->where('status', 'active')->orderBy('sender_id')->get();
    try {
        $poolSenderIds = Capsule::table('mod_sms_sender_id_pool')->where('status', 'active')->orderBy('sender_id')->get();
        // Merge pool IDs, avoiding duplicates
        $existingIds = $senderIds->pluck('sender_id')->toArray();
        foreach ($poolSenderIds as $pid) {
            if (!in_array($pid->sender_id, $existingIds)) {
                $pid->client_id = null; // Pool IDs are global
                $senderIds->push($pid);
                $existingIds[] = $pid->sender_id;
            }
        }
    } catch (\Exception $e) {
        // Pool table might not exist
    }

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-users"></i> Client SMS Settings</h3></div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    // Search form
    echo '<form method="get" class="form-inline" style="margin-bottom: 15px;">';
    echo '<input type="hidden" name="module" value="sms_suite">';
    echo '<input type="hidden" name="action" value="clients">';
    echo '<div class="form-group">';
    echo '<input type="text" name="search" class="form-control" placeholder="Search clients..." value="' . htmlspecialchars($search) . '">';
    echo '</div> ';
    echo '<button type="submit" class="btn btn-default"><i class="fa fa-search"></i> Search</button>';
    echo '</form>';

    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover">';
    echo '<thead><tr>';
    echo '<th>Client</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>Credits</th>';
    echo '<th>Billing Mode</th>';
    echo '<th>API</th>';
    echo '<th>SMS Opt-in</th>';
    echo '<th>Actions</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($clients as $client) {
        $name = trim($client->firstname . ' ' . $client->lastname);
        if (!empty($client->companyname)) {
            $name .= ' (' . $client->companyname . ')';
        }
        $apiStatus = $client->api_enabled ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>';
        $smsStatus = $client->accept_sms ? '<span class="label label-success">Yes</span>' : '<span class="label label-warning">No</span>';
        $creditBalance = $client->credit_balance ?? 0;

        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($name) . '</strong></td>';
        echo '<td>' . htmlspecialchars($client->email) . '</td>';
        echo '<td>' . htmlspecialchars($client->phonenumber) . '</td>';
        echo '<td><span class="badge">' . number_format($creditBalance) . '</span></td>';
        echo '<td>' . ucfirst(str_replace('_', ' ', $client->billing_mode ?? 'per_segment')) . '</td>';
        echo '<td>' . $apiStatus . '</td>';
        echo '<td>' . $smsStatus . '</td>';
        echo '<td>';
        echo '<button class="btn btn-xs btn-primary" onclick="editClient(' . $client->id . ')"><i class="fa fa-edit"></i></button> ';
        echo '<button class="btn btn-xs btn-success" onclick="addCredits(' . $client->id . ')"><i class="fa fa-plus"></i></button>';
        echo '</td>';
        echo '</tr>';
    }

    if (count($clients) == 0) {
        echo '<tr><td colspan="8" class="text-center text-muted">No clients found.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    // Pagination
    if ($totalPages > 1) {
        echo '<nav><ul class="pagination">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? 'active' : '';
            echo '<li class="' . $active . '"><a href="' . $modulelink . '&action=clients&page=' . $i . '&search=' . urlencode($search) . '">' . $i . '</a></li>';
        }
        echo '</ul></nav>';
    }

    echo '</div></div>';

    // Edit Client Modal
    echo '<div class="modal fade" id="editClientModal" tabindex="-1">';
    echo '<div class="modal-dialog"><div class="modal-content">';
    echo '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>';
    echo '<h4 class="modal-title">Edit Client SMS Settings</h4></div>';
    echo '<form method="post">';
    echo '<div class="modal-body">';
    echo '<input type="hidden" name="client_id" id="edit_client_id">';
    echo '<div class="form-group"><label>Billing Mode</label>';
    echo '<select name="billing_mode" id="edit_billing_mode" class="form-control">';
    echo '<option value="per_message">Per Message</option>';
    echo '<option value="per_segment">Per Segment</option>';
    echo '<option value="wallet">Wallet</option>';
    echo '<option value="plan">Plan/Package</option>';
    echo '</select></div>';
    echo '<div class="form-group"><label>Default Gateway</label>';
    echo '<select name="default_gateway_id" id="edit_gateway" class="form-control"><option value="">-- Use System Default --</option>';
    foreach ($gateways as $gw) {
        echo '<option value="' . $gw->id . '">' . htmlspecialchars($gw->name) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Assigned Sender ID</label>';
    echo '<select name="assigned_sender_id" id="edit_sender" class="form-control"><option value="">-- None --</option>';
    foreach ($senderIds as $sid) {
        echo '<option value="' . htmlspecialchars($sid->sender_id) . '">' . htmlspecialchars($sid->sender_id) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Monthly Limit (0 = unlimited)</label>';
    echo '<input type="number" name="monthly_limit" id="edit_limit" class="form-control" min="0"></div>';
    echo '<div class="checkbox"><label><input type="checkbox" name="api_enabled" id="edit_api" value="1"> API Access Enabled</label></div>';
    echo '<div class="checkbox"><label><input type="checkbox" name="accept_sms" id="edit_sms" value="1"> SMS Notifications Enabled</label></div>';
    echo '</div>';
    echo '<div class="modal-footer">';
    echo '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>';
    echo '<button type="submit" name="save_client_settings" class="btn btn-primary">Save Settings</button>';
    echo '</div></form></div></div></div>';

    // Add Credits Modal
    echo '<div class="modal fade" id="addCreditsModal" tabindex="-1">';
    echo '<div class="modal-dialog"><div class="modal-content">';
    echo '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>';
    echo '<h4 class="modal-title">Adjust Client Credits</h4></div>';
    echo '<form method="post">';
    echo '<div class="modal-body">';
    echo '<input type="hidden" name="client_id" id="credit_client_id">';
    echo '<div class="form-group"><label>Credits to Add/Remove</label>';
    echo '<input type="number" name="credits" class="form-control" placeholder="Enter positive to add, negative to remove" required></div>';
    echo '<div class="form-group"><label>Description</label>';
    echo '<input type="text" name="description" class="form-control" placeholder="Reason for adjustment"></div>';
    echo '</div>';
    echo '<div class="modal-footer">';
    echo '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>';
    echo '<button type="submit" name="add_credits" class="btn btn-success">Adjust Credits</button>';
    echo '</div></form></div></div></div>';

    // JavaScript
    echo '<script>
    function editClient(id) {
        document.getElementById("edit_client_id").value = id;
        $("#editClientModal").modal("show");
    }
    function addCredits(id) {
        document.getElementById("credit_client_id").value = id;
        $("#addCreditsModal").modal("show");
    }
    </script>';

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
    $modulelink = $vars['modulelink'];
    $repairMessage = '';
    $repairType = '';
    $autoRepaired = false;

    // Auto-repair: Check for missing tables/columns and repair automatically
    $diagnosis = sms_suite_diagnose_tables();
    $colDiagnosis = sms_suite_diagnose_columns();
    if (count($diagnosis['missing']) > 0 || count($colDiagnosis['missing']) > 0) {
        // Automatically attempt repair
        $result = sms_suite_repair_tables();
        $autoRepaired = true;
        $parts = [];
        if ($result['repaired'] > 0) $parts[] = $result['repaired'] . ' tables created';
        if (($result['columns_repaired'] ?? 0) > 0) $parts[] = ($result['columns_repaired'] ?? 0) . ' columns added';
        if ($result['success']) {
            $repairMessage = 'Auto-repair completed: ' . (implode(', ', $parts) ?: 'schema verified') . '.';
            $repairType = 'success';
        } else {
            $issues = [];
            if (!empty($result['still_missing'])) $issues[] = 'Tables: ' . implode(', ', $result['still_missing']);
            if (!empty($result['columns_still_missing'])) $issues[] = 'Columns: ' . implode(', ', $result['columns_still_missing']);
            if (!empty($result['errors'])) $issues[] = 'Errors: ' . implode('; ', $result['errors']);
            $repairMessage = 'Auto-repair attempted but some issues remain. ' . implode('. ', $issues);
            $repairType = 'warning';
        }
        // Re-diagnose after repair
        $diagnosis = sms_suite_diagnose_tables();
        $colDiagnosis = sms_suite_diagnose_columns();
    }

    // Handle manual repair action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_database'])) {
        $result = sms_suite_repair_tables();

        // Fallback: directly add any still-missing columns via Capsule::statement
        $colCheck = sms_suite_diagnose_columns();
        $directFixed = 0;
        if (!empty($colCheck['missing'])) {
            $columnDefs = [
                'mod_sms_sender_id_pool.country_codes' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `country_codes` TEXT NULL",
                'mod_sms_sender_id_pool.description' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `description` TEXT NULL",
                'mod_sms_sender_id_pool.price_setup' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `price_setup` DECIMAL(10,2) NOT NULL DEFAULT 0",
                'mod_sms_sender_id_pool.price_monthly' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `price_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0",
                'mod_sms_sender_id_pool.price_yearly' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `price_yearly` DECIMAL(10,2) NOT NULL DEFAULT 0",
                'mod_sms_sender_id_pool.requires_approval' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `requires_approval` TINYINT(1) NOT NULL DEFAULT 1",
                'mod_sms_sender_id_pool.is_shared' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `is_shared` TINYINT(1) NOT NULL DEFAULT 0",
                'mod_sms_sender_id_pool.network' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `network` VARCHAR(20) NOT NULL DEFAULT 'all'",
                'mod_sms_sender_id_pool.telco_status' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `telco_status` VARCHAR(20) NOT NULL DEFAULT 'approved'",
                'mod_sms_sender_id_pool.telco_approved_date' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `telco_approved_date` DATE NULL",
                'mod_sms_sender_id_pool.telco_reference' => "ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `telco_reference` VARCHAR(100) NULL",
            ];
            foreach ($colCheck['missing'] as $missingCol) {
                if (isset($columnDefs[$missingCol])) {
                    try {
                        Capsule::statement($columnDefs[$missingCol]);
                        $directFixed++;
                    } catch (\Exception $e) {
                        $result['errors'][] = "Direct fix {$missingCol}: " . $e->getMessage();
                    }
                }
            }
        }

        $parts = [];
        if ($result['repaired'] > 0) $parts[] = $result['repaired'] . ' tables created';
        $totalColFixed = ($result['columns_repaired'] ?? 0) + $directFixed;
        if ($totalColFixed > 0) $parts[] = $totalColFixed . ' columns added';
        // Re-diagnose after repair
        $diagnosis = sms_suite_diagnose_tables();
        $colDiagnosis = sms_suite_diagnose_columns();
        if (empty($diagnosis['missing']) && empty($colDiagnosis['missing'])) {
            $repairMessage = 'Database repair completed successfully. ' . (implode(', ', $parts) ?: 'All tables and columns verified.');
            $repairType = 'success';
        } else {
            $issues = [];
            if (!empty($diagnosis['missing'])) $issues[] = 'Tables: ' . implode(', ', $diagnosis['missing']);
            if (!empty($colDiagnosis['missing'])) $issues[] = 'Columns: ' . implode(', ', $colDiagnosis['missing']);
            if (!empty($result['errors'])) $issues[] = 'Errors: ' . implode('; ', $result['errors']);
            $repairMessage = 'Database repair completed with issues. ' . implode('. ', $issues);
            $repairType = 'warning';
        }
    }

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-stethoscope"></i> Diagnostics</h3></div>';
    echo '<div class="panel-body">';

    if ($repairMessage) {
        echo '<div class="alert alert-' . $repairType . '">';
        if ($autoRepaired) {
            echo '<i class="fa fa-magic"></i> <strong>Auto-Repair:</strong> ';
        }
        echo htmlspecialchars($repairMessage) . '</div>';
    }

    // PHP version
    echo '<h4>Environment</h4>';
    echo '<table class="table table-bordered">';
    echo '<tr><td width="30%">PHP Version</td><td>' . PHP_VERSION . '</td></tr>';
    echo '<tr><td>Module Version</td><td>' . SMS_SUITE_VERSION . '</td></tr>';
    echo '<tr><td>WHMCS Version</td><td>' . ($GLOBALS['CONFIG']['Version'] ?? 'Unknown') . '</td></tr>';
    echo '</table>';

    // Database Tables Status
    echo '<h4><i class="fa fa-database"></i> Database Tables</h4>';

    echo '<table class="table table-bordered">';
    echo '<tr><td width="30%">Tables Checked</td><td>' . $diagnosis['total_checked'] . '</td></tr>';
    echo '<tr><td>Tables Found</td><td><span class="text-success"><i class="fa fa-check"></i> ' . count($diagnosis['existing']) . '</span></td></tr>';
    echo '<tr><td>Tables Missing</td><td>';
    if (count($diagnosis['missing']) > 0) {
        echo '<span class="text-danger"><i class="fa fa-warning"></i> ' . count($diagnosis['missing']) . '</span>';
        echo '<br><small class="text-muted">' . implode(', ', $diagnosis['missing']) . '</small>';
    } else {
        echo '<span class="text-success"><i class="fa fa-check"></i> 0</span>';
    }
    echo '</td></tr>';
    echo '</table>';

    if (count($diagnosis['missing']) > 0) {
        echo '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Some tables are missing. Click repair to create them.</div>';
    } else {
        echo '<div class="alert alert-success"><i class="fa fa-check-circle"></i> All required database tables are present.</div>';
    }

    // Column-level diagnostics
    echo '<h4><i class="fa fa-columns"></i> Column Schema Check</h4>';
    echo '<table class="table table-bordered">';
    echo '<tr><td width="30%">Columns Checked</td><td>' . $colDiagnosis['total_checked'] . '</td></tr>';
    echo '<tr><td>Columns OK</td><td><span class="text-success"><i class="fa fa-check"></i> ' . count($colDiagnosis['ok']) . '</span></td></tr>';
    echo '<tr><td>Columns Missing</td><td>';
    if (count($colDiagnosis['missing']) > 0) {
        echo '<span class="text-danger"><i class="fa fa-warning"></i> ' . count($colDiagnosis['missing']) . '</span>';
        echo '<ul style="margin-top:5px; margin-bottom:0;">';
        foreach ($colDiagnosis['missing'] as $col) {
            echo '<li><code>' . htmlspecialchars($col) . '</code></li>';
        }
        echo '</ul>';
    } else {
        echo '<span class="text-success"><i class="fa fa-check"></i> 0</span>';
    }
    echo '</td></tr>';
    echo '</table>';

    if (count($colDiagnosis['missing']) > 0) {
        echo '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Some columns are missing. Click repair to add them.</div>';
    } elseif (count($diagnosis['missing']) == 0) {
        echo '<div class="alert alert-success"><i class="fa fa-check-circle"></i> All database tables and columns are correct.</div>';
    }

    // Always show repair button - it also updates column schemas
    echo '<form method="post" style="margin-top:10px;">';
    echo '<button type="submit" name="repair_database" class="btn btn-warning">';
    echo '<i class="fa fa-wrench"></i> Repair / Update Database Schema';
    echo '</button>';
    echo ' <small class="text-muted">Creates missing tables and adds any missing columns to existing tables.</small>';
    echo '</form>';
    echo '<br>';

    // Cron status
    echo '<h4>Cron Status</h4>';
    try {
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
        } else {
            echo '<p class="text-muted">No cron tasks have run yet.</p>';
        }
    } catch (Exception $e) {
        echo '<p class="text-warning">Cron status table not available.</p>';
    }

    // Queue status
    echo '<h4>Queue Status</h4>';
    try {
        $queuedMessages = Capsule::table('mod_sms_messages')->where('status', 'queued')->count();
        $pendingCampaigns = Capsule::table('mod_sms_campaigns')->whereIn('status', ['scheduled', 'queued'])->count();

        echo '<table class="table table-bordered">';
        echo '<tr><td width="30%">Queued Messages</td><td>' . $queuedMessages . '</td></tr>';
        echo '<tr><td>Pending Campaigns</td><td>' . $pendingCampaigns . '</td></tr>';
        echo '</table>';
    } catch (Exception $e) {
        echo '<p class="text-warning">Queue status not available. Please repair database tables first.</p>';
    }

    // Activity Log
    echo '<h4>Recent Activity Log</h4>';
    try {
        $logs = Capsule::table('tblactivitylog')
            ->where('description', 'LIKE', 'SMS Suite%')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        if (count($logs) > 0) {
            echo '<table class="table table-bordered table-sm">';
            echo '<thead><tr><th>Date</th><th>Description</th></tr></thead>';
            echo '<tbody>';
            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td style="width: 150px;"><small>' . $log->date . '</small></td>';
                echo '<td><small>' . htmlspecialchars($log->description) . '</small></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p class="text-muted">No recent SMS Suite activity logged.</p>';
        }
    } catch (Exception $e) {
        echo '<p class="text-muted">Activity log not available.</p>';
    }

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

    // Handle add balance (wallet)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_balance'])) {
        $amount = (float)$_POST['amount'];
        if ($amount > 0) {
            require_once __DIR__ . '/../lib/Billing/BillingService.php';
            $result = \SMSSuite\Billing\BillingService::topUp($clientId, $amount, 'Admin credit');
            if ($result['success']) {
                $success = 'Wallet balance added: $' . number_format($amount, 2);
            } else {
                $error = $result['error'] ?? 'Failed to add balance';
            }
        }
    }

    // Handle add credits (plan mode)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_credits'])) {
        $credits = (int)$_POST['credits'];
        if ($credits > 0) {
            require_once __DIR__ . '/../lib/Billing/BillingService.php';
            $adminId = $_SESSION['adminid'] ?? 1;
            $result = \SMSSuite\Billing\BillingService::addCreditsToClient($clientId, $credits, $adminId, 'Admin credit');
            if ($result['success']) {
                $success = 'Credits added: ' . number_format($credits) . ' (New balance: ' . number_format($result['new_balance']) . ')';
            } else {
                $error = $result['error'] ?? 'Failed to add credits';
            }
        }
    }

    // Get gateways and sender IDs
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->orderBy('name')->get();
    $senderIds = Capsule::table('mod_sms_sender_ids')
        ->where(function($q) use ($clientId) {
            $q->whereNull('client_id')->orWhere('client_id', 0)->orWhere('client_id', $clientId);
        })
        ->where('status', 'active')
        ->orderBy('sender_id')
        ->get();

    // Also include sender IDs assigned from the pool
    $poolAssigned = Capsule::table('mod_sms_client_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->get();
    foreach ($poolAssigned as $pa) {
        // Avoid duplicates
        $exists = false;
        foreach ($senderIds as $existing) {
            if ($existing->sender_id === $pa->sender_id) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $obj = new \stdClass();
            $obj->sender_id = $pa->sender_id;
            $obj->client_id = $clientId;
            $senderIds[] = $obj;
        }
    }

    // Get wallet balance
    $wallet = Capsule::table('mod_sms_wallet')->where('client_id', $clientId)->first();
    $balance = $wallet ? $wallet->balance : 0;

    // Get credit balance
    require_once __DIR__ . '/../lib/Billing/BillingService.php';
    $creditBalance = \SMSSuite\Billing\BillingService::getClientCreditBalance($clientId);

    // Get client sender IDs
    $clientSenderIds = \SMSSuite\Billing\BillingService::getClientSenderIdsAdmin($clientId);

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
    echo '<tr><td>SMS Credits</td><td><strong>' . number_format($creditBalance) . '</strong></td></tr>';
    echo '<tr><td>Total Messages</td><td>' . ($stats->total ?? 0) . '</td></tr>';
    echo '<tr><td>Delivered</td><td>' . ($stats->delivered ?? 0) . '</td></tr>';
    echo '<tr><td>Total Segments</td><td>' . ($stats->segments ?? 0) . '</td></tr>';
    if (isset($settings->monthly_limit) && $settings->monthly_limit) {
        echo '<tr><td>Monthly Used</td><td>' . ($settings->monthly_used ?? 0) . ' / ' . $settings->monthly_limit . '</td></tr>';
    }
    echo '</table>';
    echo '</div>';
    echo '</div>';

    // Add balance form (Wallet)
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-wallet"></i> Add Wallet Balance</h4></div>';
    echo '<div class="panel-body">';
    echo '<form method="post" class="form-inline">';
    echo '<input type="hidden" name="add_balance" value="1">';
    echo '<div class="form-group">';
    echo '<div class="input-group">';
    echo '<span class="input-group-addon">$</span>';
    echo '<input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" style="width: 80px;">';
    echo '</div>';
    echo '</div> ';
    echo '<button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Add credits form (Plan mode)
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-ticket"></i> Add SMS Credits</h4></div>';
    echo '<div class="panel-body">';
    echo '<form method="post" class="form-inline">';
    echo '<input type="hidden" name="add_credits" value="1">';
    echo '<div class="form-group">';
    echo '<input type="number" name="credits" class="form-control" min="1" placeholder="Credits" style="width: 80px;">';
    echo '</div> ';
    echo '<button type="submit" class="btn btn-info"><i class="fa fa-plus"></i> Add</button>';
    echo '</form>';
    echo '<p class="help-block" style="margin-top:5px;">For Plan/Credits billing mode</p>';
    echo '</div>';
    echo '</div>';

    // Client Sender IDs
    if (!empty($clientSenderIds)) {
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-id-card"></i> Assigned Sender IDs</h4></div>';
        echo '<div class="panel-body">';
        echo '<table class="table table-condensed">';
        foreach ($clientSenderIds as $csid) {
            $default = $csid->is_default ? ' <span class="label label-primary">Default</span>' : '';
            $statusLabel = $csid->status === 'active' ? '<span class="label label-success">Active</span>' : '<span class="label label-default">' . ucfirst($csid->status) . '</span>';
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($csid->sender_id) . '</strong>' . $default . '</td>';
            echo '<td>' . $statusLabel . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }

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
                echo '<td><button class="btn btn-xs btn-default" onclick="editTemplate(' . $t->id . ', ' . htmlspecialchars(json_encode($t->name), ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars(json_encode($t->message), ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars(json_encode($t->status), ENT_QUOTES, 'UTF-8') . ')">Edit</button></td>';
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
                'currency_id' => (int)($_POST['currency_id'] ?? 0) ?: null,
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
                'currency_id' => (int)($_POST['currency_id'] ?? 0) ?: null,
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
    $currencyMap = [];
    foreach ($currencies as $curr) {
        $currencyMap[$curr->id] = $curr;
    }

    // Build currency options HTML
    $currencyOptions = '<option value="">Default (Client Currency)</option>';
    foreach ($currencies as $curr) {
        $currencyOptions .= '<option value="' . $curr->id . '">' . htmlspecialchars($curr->code) . ' - ' . htmlspecialchars($curr->prefix ?: $curr->suffix) . '</option>';
    }

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
                        <th>Currency</th>
                        <th>Validity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if (empty($packages)) {
        echo '<tr><td colspan="8" class="text-center text-muted">No packages created yet.</td></tr>';
    } else {
        foreach ($packages as $pkg) {
            $featured = $pkg->is_featured ? ' <span class="label label-warning">Featured</span>' : '';
            $status = $pkg->status ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';
            $validity = $pkg->validity_days > 0 ? $pkg->validity_days . ' days' : 'Never expires';

            // Get currency symbol
            $currSymbol = '$';
            $currCode = 'Default';
            if ($pkg->currency_id && isset($currencyMap[$pkg->currency_id])) {
                $curr = $currencyMap[$pkg->currency_id];
                $currSymbol = $curr->prefix ?: $curr->suffix;
                $currCode = $curr->code;
            }

            echo '<tr>
                <td><strong>' . htmlspecialchars($pkg->name) . '</strong>' . $featured . '<br><small class="text-muted">' . htmlspecialchars($pkg->description ?? '') . '</small></td>
                <td>' . number_format($pkg->credits) . '</td>
                <td>' . ($pkg->bonus_credits > 0 ? '+' . number_format($pkg->bonus_credits) : '-') . '</td>
                <td>' . htmlspecialchars($currSymbol) . number_format($pkg->price, 2) . '</td>
                <td><small>' . $currCode . '</small></td>
                <td>' . $validity . '</td>
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Price <span class="text-danger">*</span></label>
                                    <input type="number" name="price" class="form-control" required step="0.01" min="0" placeholder="9.99">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select name="currency_id" class="form-control">
                                        ' . $currencyOptions . '
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Price <span class="text-danger">*</span></label>
                                    <input type="number" name="price" id="edit_pkg_price" class="form-control" required step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select name="currency_id" id="edit_pkg_currency" class="form-control">
                                        ' . $currencyOptions . '
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
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
        document.getElementById("edit_pkg_currency").value = pkg.currency_id || "";
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
 * Manual workflow: Admin creates Sender IDs after telco approval, then assigns to clients
 */
function sms_suite_admin_sender_id_pool($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    require_once __DIR__ . '/../lib/Billing/BillingService.php';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'create') {
            // Add to pool with network info
            $poolData = [
                'sender_id' => SecurityHelper::sanitize($_POST['sender_id']),
                'type' => $_POST['type'] ?? 'alphanumeric',
                'network' => $_POST['network'] ?? 'all',
                'description' => SecurityHelper::sanitize($_POST['description']),
                'gateway_id' => (int)$_POST['gateway_id'],
                'country_codes' => !empty($_POST['country_codes']) ? explode(',', $_POST['country_codes']) : null,
                'price_setup' => (float)($_POST['price_setup'] ?? 0),
                'price_monthly' => (float)($_POST['price_monthly'] ?? 0),
                'price_yearly' => (float)($_POST['price_yearly'] ?? 0),
                'requires_approval' => isset($_POST['requires_approval']),
                'is_shared' => isset($_POST['is_shared']),
                'telco_status' => $_POST['telco_status'] ?? 'approved',
                'telco_approved_date' => !empty($_POST['telco_approved_date']) ? $_POST['telco_approved_date'] : null,
                'telco_reference' => SecurityHelper::sanitize($_POST['telco_reference'] ?? ''),
                'status' => $_POST['status'] ?? 'active',
            ];

            $result = sms_suite_add_sender_id_to_pool_extended($poolData);

            if ($result['success']) {
                echo '<div class="alert alert-success">Sender ID added to pool successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        } elseif ($action === 'update') {
            $updateData = [
                'sender_id' => SecurityHelper::sanitize($_POST['sender_id']),
                'type' => $_POST['type'] ?? 'alphanumeric',
                'network' => $_POST['network'] ?? 'all',
                'description' => SecurityHelper::sanitize($_POST['description']),
                'gateway_id' => (int)$_POST['gateway_id'],
                'country_codes' => !empty($_POST['country_codes']) ? explode(',', $_POST['country_codes']) : null,
                'price_setup' => (float)($_POST['price_setup'] ?? 0),
                'price_monthly' => (float)($_POST['price_monthly'] ?? 0),
                'price_yearly' => (float)($_POST['price_yearly'] ?? 0),
                'requires_approval' => isset($_POST['requires_approval']),
                'is_shared' => isset($_POST['is_shared']),
                'telco_status' => $_POST['telco_status'] ?? 'approved',
                'telco_approved_date' => !empty($_POST['telco_approved_date']) ? $_POST['telco_approved_date'] : null,
                'telco_reference' => SecurityHelper::sanitize($_POST['telco_reference'] ?? ''),
                'status' => $_POST['status'] ?? 'active',
            ];

            $result = sms_suite_update_sender_id_pool_extended((int)$_POST['pool_id'], $updateData);

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
        } elseif ($action === 'assign_to_client') {
            // Assign sender ID from pool to a client
            $result = sms_suite_assign_sender_id_to_client(
                (int)$_POST['client_id'],
                (int)$_POST['pool_id'],
                $_POST['network'] ?? 'all',
                !empty($_POST['expires_at']) ? $_POST['expires_at'] : null
            );

            if ($result['success']) {
                echo '<div class="alert alert-success">Sender ID assigned to client successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
            }
        }
    }

    // Get all sender IDs in pool with extended info
    $pool = Capsule::table('mod_sms_sender_id_pool as p')
        ->leftJoin('mod_sms_gateways as g', 'p.gateway_id', '=', 'g.id')
        ->select(['p.*', 'g.name as gateway_name'])
        ->orderBy('p.sender_id')
        ->orderBy('p.network')
        ->get();

    // Get gateways for dropdown
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->get();

    // Get clients for assignment dropdown
    $clients = sms_suite_get_clients_dropdown();

    // Count assignments per pool item
    $assignmentCounts = Capsule::table('mod_sms_client_sender_ids')
        ->whereNotNull('pool_id')
        ->where('status', 'active')
        ->selectRaw('pool_id, COUNT(*) as count')
        ->groupBy('pool_id')
        ->pluck('count', 'pool_id')
        ->toArray();

    echo '<div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-id-card"></i> Sender ID Pool (Manual Telco Management)
                <button class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#createSenderIdModal">
                    <i class="fa fa-plus"></i> Add Sender ID
                </button>
            </h3>
        </div>
        <div class="panel-body">
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> <strong>Workflow:</strong> After receiving telco approval (Safaricom/Airtel/Telkom), add the Sender ID here, then assign it to clients.
            </div>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Sender ID</th>
                        <th>Network</th>
                        <th>Type</th>
                        <th>Gateway</th>
                        <th>Telco Status</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if (count($pool) === 0) {
        echo '<tr><td colspan="8" class="text-center text-muted">No sender IDs in pool. Add sender IDs after telco approval.</td></tr>';
    } else {
        foreach ($pool as $item) {
            $statusLabel = match($item->status) {
                'active' => '<span class="label label-success">Active</span>',
                'inactive' => '<span class="label label-default">Inactive</span>',
                'reserved' => '<span class="label label-warning">Reserved</span>',
                default => '<span class="label label-default">' . ucfirst($item->status) . '</span>',
            };

            $networkLabel = match($item->network ?? 'all') {
                'safaricom' => '<span class="label label-success">Safaricom</span>',
                'airtel' => '<span class="label label-danger">Airtel</span>',
                'telkom' => '<span class="label label-info">Telkom</span>',
                default => '<span class="label label-default">All Networks</span>',
            };

            $telcoLabel = match($item->telco_status ?? 'approved') {
                'approved' => '<span class="label label-success">Approved</span>',
                'pending' => '<span class="label label-warning">Pending</span>',
                'rejected' => '<span class="label label-danger">Rejected</span>',
                default => '<span class="label label-default">' . ucfirst($item->telco_status ?? 'Unknown') . '</span>',
            };

            $assignedCount = $assignmentCounts[$item->id] ?? 0;

            echo '<tr>
                <td>
                    <strong>' . htmlspecialchars($item->sender_id) . '</strong>
                    ' . ($item->is_shared ? '<span class="label label-info" title="Shared">S</span>' : '') . '
                    <br><small class="text-muted">' . htmlspecialchars($item->description ?? '') . '</small>
                    ' . ($item->telco_reference ? '<br><small class="text-muted">Ref: ' . htmlspecialchars($item->telco_reference) . '</small>' : '') . '
                </td>
                <td>' . $networkLabel . '</td>
                <td>' . ucfirst($item->type) . '</td>
                <td>' . htmlspecialchars($item->gateway_name ?? 'Not Set') . '</td>
                <td>' . $telcoLabel . '
                    ' . ($item->telco_approved_date ? '<br><small>' . date('M j, Y', strtotime($item->telco_approved_date)) . '</small>' : '') . '
                </td>
                <td>
                    <span class="badge">' . $assignedCount . '</span> clients
                </td>
                <td>' . $statusLabel . '</td>
                <td>
                    <button class="btn btn-xs btn-success" onclick=\'openAssignModal(' . $item->id . ', "' . htmlspecialchars($item->sender_id) . '", "' . ($item->network ?? 'all') . '")\' title="Assign to Client">
                        <i class="fa fa-user-plus"></i>
                    </button>
                    <button class="btn btn-xs btn-primary" onclick=\'editPoolItem(' . json_encode($item) . ')\' title="Edit">
                        <i class="fa fa-edit"></i>
                    </button>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this Sender ID from pool?\');">
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="pool_id" value="' . $item->id . '">
                        <button type="submit" class="btn btn-xs btn-danger" title="Delete"><i class="fa fa-trash"></i></button>
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

    // Client options for assignment modal
    $clientOptions = '';
    foreach ($clients as $c) {
        $clientName = trim($c->firstname . ' ' . $c->lastname);
        if ($c->companyname) {
            $clientName .= ' (' . $c->companyname . ')';
        }
        $clientOptions .= '<option value="' . $c->id . '">' . htmlspecialchars($clientName) . ' - ' . htmlspecialchars($c->email) . '</option>';
    }

    // Create Sender ID Modal
    echo '<div class="modal fade" id="createSenderIdModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="create">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-plus"></i> Add Sender ID (After Telco Approval)</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Sender ID <span class="text-danger">*</span></label>
                                    <input type="text" name="sender_id" class="form-control" required maxlength="11" placeholder="e.g., MYCOMPANY">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Network <span class="text-danger">*</span></label>
                                    <select name="network" class="form-control" required>
                                        <option value="all">All Networks</option>
                                        <option value="safaricom">Safaricom</option>
                                        <option value="airtel">Airtel</option>
                                        <option value="telkom">Telkom</option>
                                    </select>
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
                            <label>Description / Notes</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Internal notes about this sender ID..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Gateway <span class="text-danger">*</span></label>
                            <select name="gateway_id" class="form-control" required>
                                <option value="">-- Select Gateway --</option>
                                ' . $gatewayOptions . '
                            </select>
                        </div>

                        <hr>
                        <h5><i class="fa fa-building"></i> Telco Approval Details</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Telco Status</label>
                                    <select name="telco_status" class="form-control">
                                        <option value="approved" selected>Approved</option>
                                        <option value="pending">Pending Approval</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Approval Date</label>
                                    <input type="date" name="telco_approved_date" class="form-control" value="' . date('Y-m-d') . '">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Telco Reference #</label>
                                    <input type="text" name="telco_reference" class="form-control" placeholder="e.g., SAF-2024-001">
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h5><i class="fa fa-dollar"></i> Pricing (Optional)</h5>
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
                        <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add to Pool</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Assign to Client Modal
    echo '<div class="modal fade" id="assignSenderIdModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="assign_to_client">
                    <input type="hidden" name="pool_id" id="assign_pool_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-user-plus"></i> Assign Sender ID to Client</h4>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Sender ID:</strong> <span id="assign_sender_id_display"></span>
                            <br><strong>Network:</strong> <span id="assign_network_display"></span>
                        </div>

                        <div class="form-group">
                            <label>Select Client <span class="text-danger">*</span></label>
                            <select name="client_id" class="form-control" required id="assign_client_select">
                                <option value="">-- Select Client --</option>
                                ' . $clientOptions . '
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Network</label>
                            <select name="network" id="assign_network_select" class="form-control">
                                <option value="all">All Networks</option>
                                <option value="safaricom">Safaricom Only</option>
                                <option value="airtel">Airtel Only</option>
                                <option value="telkom">Telkom Only</option>
                            </select>
                            <p class="help-block">Restrict this assignment to a specific network</p>
                        </div>

                        <div class="form-group">
                            <label>Expiry Date (Optional)</label>
                            <input type="date" name="expires_at" class="form-control">
                            <p class="help-block">Leave empty for no expiry</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-user-plus"></i> Assign to Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Edit Sender ID Modal
    echo '<div class="modal fade" id="editPoolModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Sender ID <span class="text-danger">*</span></label>
                                    <input type="text" name="sender_id" id="edit_pool_sender_id" class="form-control" required maxlength="11">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Network</label>
                                    <select name="network" id="edit_pool_network" class="form-control">
                                        <option value="all">All Networks</option>
                                        <option value="safaricom">Safaricom</option>
                                        <option value="airtel">Airtel</option>
                                        <option value="telkom">Telkom</option>
                                    </select>
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

                        <hr>
                        <h5>Telco Approval</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Telco Status</label>
                                    <select name="telco_status" id="edit_pool_telco_status" class="form-control">
                                        <option value="approved">Approved</option>
                                        <option value="pending">Pending</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Approval Date</label>
                                    <input type="date" name="telco_approved_date" id="edit_pool_telco_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Reference #</label>
                                    <input type="text" name="telco_reference" id="edit_pool_telco_ref" class="form-control">
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h5>Pricing</h5>
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
                            <label><input type="checkbox" name="is_shared" id="edit_pool_shared" value="1"> Shared (can be used by multiple clients)</label>
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
        document.getElementById("edit_pool_network").value = item.network || "all";
        document.getElementById("edit_pool_type").value = item.type;
        document.getElementById("edit_pool_desc").value = item.description || "";
        document.getElementById("edit_pool_gateway").value = item.gateway_id;
        document.getElementById("edit_pool_telco_status").value = item.telco_status || "approved";
        document.getElementById("edit_pool_telco_date").value = item.telco_approved_date || "";
        document.getElementById("edit_pool_telco_ref").value = item.telco_reference || "";
        document.getElementById("edit_pool_setup").value = item.price_setup || 0;
        document.getElementById("edit_pool_monthly").value = item.price_monthly || 0;
        document.getElementById("edit_pool_yearly").value = item.price_yearly || 0;
        document.getElementById("edit_pool_shared").checked = item.is_shared == 1;
        document.getElementById("edit_pool_status").value = item.status;
        jQuery("#editPoolModal").modal("show");
    }

    function openAssignModal(poolId, senderId, network) {
        document.getElementById("assign_pool_id").value = poolId;
        document.getElementById("assign_sender_id_display").textContent = senderId;
        document.getElementById("assign_network_display").textContent = network === "all" ? "All Networks" : network.charAt(0).toUpperCase() + network.slice(1);
        document.getElementById("assign_network_select").value = network;
        jQuery("#assignSenderIdModal").modal("show");
    }

    </script>';
}

/**
 * Add Sender ID to pool with extended fields (network, telco status)
 */
function sms_suite_add_sender_id_to_pool_extended(array $data): array
{
    try {
        // Check if sender ID already exists for this network
        $existing = Capsule::table('mod_sms_sender_id_pool')
            ->where('sender_id', $data['sender_id'])
            ->where('network', $data['network'] ?? 'all')
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'Sender ID already exists for this network'];
        }

        $poolId = Capsule::table('mod_sms_sender_id_pool')->insertGetId([
            'sender_id' => $data['sender_id'],
            'type' => $data['type'] ?? 'alphanumeric',
            'network' => $data['network'] ?? 'all',
            'description' => $data['description'] ?? null,
            'gateway_id' => $data['gateway_id'],
            'country_codes' => isset($data['country_codes']) ? json_encode($data['country_codes']) : null,
            'price_setup' => $data['price_setup'] ?? 0,
            'price_monthly' => $data['price_monthly'] ?? 0,
            'price_yearly' => $data['price_yearly'] ?? 0,
            'requires_approval' => $data['requires_approval'] ?? true,
            'is_shared' => $data['is_shared'] ?? false,
            'telco_status' => $data['telco_status'] ?? 'approved',
            'telco_approved_date' => $data['telco_approved_date'] ?? null,
            'telco_reference' => $data['telco_reference'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'pool_id' => $poolId];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update Sender ID in pool with extended fields
 */
function sms_suite_update_sender_id_pool_extended(int $poolId, array $data): array
{
    try {
        if (isset($data['country_codes']) && is_array($data['country_codes'])) {
            $data['country_codes'] = json_encode($data['country_codes']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        Capsule::table('mod_sms_sender_id_pool')
            ->where('id', $poolId)
            ->update($data);

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Assign Sender ID from pool to a client (manual assignment by admin)
 */
function sms_suite_assign_sender_id_to_client(int $clientId, int $poolId, string $network = 'all', ?string $expiresAt = null): array
{
    try {
        // Get pool item
        $poolItem = Capsule::table('mod_sms_sender_id_pool')
            ->where('id', $poolId)
            ->first();

        if (!$poolItem) {
            return ['success' => false, 'error' => 'Sender ID not found in pool'];
        }

        // Check if already assigned to this client for this network
        $existing = Capsule::table('mod_sms_client_sender_ids')
            ->where('client_id', $clientId)
            ->where('sender_id', $poolItem->sender_id)
            ->where('network', $network)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'Sender ID already assigned to this client for this network'];
        }

        // Check if non-shared and already assigned to another client
        if (!$poolItem->is_shared) {
            $otherAssignment = Capsule::table('mod_sms_client_sender_ids')
                ->where('pool_id', $poolId)
                ->where('client_id', '!=', $clientId)
                ->where('network', $network)
                ->where('status', 'active')
                ->first();

            if ($otherAssignment) {
                return ['success' => false, 'error' => 'This Sender ID is not shared and is already assigned to another client'];
            }
        }

        // Create assignment
        $assignmentId = Capsule::table('mod_sms_client_sender_ids')->insertGetId([
            'client_id' => $clientId,
            'pool_id' => $poolId,
            'sender_id' => $poolItem->sender_id,
            'type' => $poolItem->type,
            'network' => $network,
            'gateway_id' => $poolItem->gateway_id,
            'status' => 'active',
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Check if client has a default sender ID, if not make this one default
        $hasDefault = Capsule::table('mod_sms_client_sender_ids')
            ->where('client_id', $clientId)
            ->where('is_default', true)
            ->where('status', 'active')
            ->where('id', '!=', $assignmentId)
            ->exists();

        if (!$hasDefault) {
            Capsule::table('mod_sms_client_sender_ids')
                ->where('id', $assignmentId)
                ->update(['is_default' => true]);

            // Update client settings
            Capsule::table('mod_sms_settings')->updateOrInsert(
                ['client_id' => $clientId],
                [
                    'assigned_sender_id' => $poolItem->sender_id,
                    'assigned_gateway_id' => $poolItem->gateway_id,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }

        logActivity("SMS Suite: Sender ID '{$poolItem->sender_id}' assigned to client #{$clientId} for {$network} network");

        return ['success' => true, 'assignment_id' => $assignmentId];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get clients for dropdown (used in assignment modal)
 */
function sms_suite_get_clients_dropdown(): array
{
    return Capsule::table('tblclients')
        ->select(['id', 'firstname', 'lastname', 'companyname', 'email'])
        ->orderBy('firstname')
        ->orderBy('lastname')
        ->limit(500)
        ->get()
        ->toArray();
}

/**
 * Get client's assigned sender IDs with details
 */
function sms_suite_get_client_sender_ids_detailed(int $clientId): array
{
    return Capsule::table('mod_sms_client_sender_ids as csi')
        ->leftJoin('mod_sms_sender_id_pool as p', 'csi.pool_id', '=', 'p.id')
        ->leftJoin('mod_sms_gateways as g', 'csi.gateway_id', '=', 'g.id')
        ->where('csi.client_id', $clientId)
        ->select([
            'csi.*',
            'p.description as pool_description',
            'p.telco_status',
            'p.telco_reference',
            'g.name as gateway_name',
        ])
        ->orderBy('csi.is_default', 'desc')
        ->orderBy('csi.created_at', 'desc')
        ->get()
        ->toArray();
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
                        <th>Company / Use Case</th>
                        <th>Documents</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if (empty($requests)) {
        echo '<tr><td colspan="7" class="text-center text-muted">No requests found.</td></tr>';
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

            // Count documents
            $docCount = 0;
            $docs = [];
            if (!empty($req->doc_certificate)) { $docCount++; $docs['Certificate'] = $req->doc_certificate; }
            if (!empty($req->doc_vat_cert)) { $docCount++; $docs['VAT Cert'] = $req->doc_vat_cert; }
            if (!empty($req->doc_authorization)) { $docCount++; $docs['Authorization'] = $req->doc_authorization; }
            if (!empty($req->doc_other)) { $docCount++; $docs['KYC/Other'] = $req->doc_other; }

            echo '<tr>
                <td>
                    <a href="clientssummary.php?userid=' . $req->client_id . '" target="_blank">' . htmlspecialchars($clientName) . '</a>
                    <br><small class="text-muted">' . htmlspecialchars($req->email ?? '') . '</small>
                </td>
                <td>
                    <strong>' . htmlspecialchars($req->sender_id) . '</strong>
                    <br><small class="text-muted">' . ucfirst($req->type) . '</small>
                </td>
                <td>
                    <strong>' . htmlspecialchars($req->company_name ?? '-') . '</strong>
                    <br><small class="text-muted">' . htmlspecialchars(substr($req->use_case ?? '', 0, 50)) . (strlen($req->use_case ?? '') > 50 ? '...' : '') . '</small>
                </td>
                <td>';

            if ($docCount > 0) {
                echo '<button class="btn btn-xs btn-info" onclick=\'showDocumentsModal(' . $req->id . ', ' . json_encode($docs) . ', ' . $req->client_id . ')\'>';
                echo '<i class="fa fa-file"></i> ' . $docCount . ' docs</button>';
            } else {
                echo '<span class="text-muted">No docs</span>';
            }

            echo '</td>
                <td>' . $statusLabel . '</td>
                <td>' . date('M d, Y', strtotime($req->created_at)) . '</td>
                <td>';

            if ($req->status === 'pending') {
                echo '<button class="btn btn-xs btn-success" onclick=\'showApproveModal(' . json_encode($req) . ')\'><i class="fa fa-check"></i></button> ';
                echo '<button class="btn btn-xs btn-danger" onclick=\'showRejectModal(' . $req->id . ')\'><i class="fa fa-times"></i></button> ';
                echo '<button class="btn btn-xs btn-default" onclick=\'showDetailsModal(' . json_encode($req) . ')\'><i class="fa fa-eye"></i></button>';
            } elseif ($req->status === 'approved' && $req->invoice_id) {
                echo '<a href="invoices.php?action=edit&id=' . $req->invoice_id . '" class="btn btn-xs btn-info" target="_blank"><i class="fa fa-file-text"></i> #' . $req->invoice_id . '</a>';
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
    <!-- Documents Modal -->
    <div class="modal fade" id="documentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-file"></i> Uploaded Documents</h4>
                </div>
                <div class="modal-body">
                    <div id="documentsContent"></div>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> Click on document names to download. Review documents before approving the Sender ID request for telco submission.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-info-circle"></i> Request Details</h4>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <tr><th width="150">Sender ID</th><td id="detail_sender_id"></td></tr>
                        <tr><th>Type</th><td id="detail_type"></td></tr>
                        <tr><th>Company Name</th><td id="detail_company"></td></tr>
                        <tr><th>Use Case</th><td id="detail_use_case"></td></tr>
                        <tr><th>Submitted</th><td id="detail_created"></td></tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
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

    function showDocumentsModal(requestId, docs, clientId) {
        var html = "<table class=\"table table-striped\">";
        html += "<thead><tr><th>Document Type</th><th>Actions</th></tr></thead><tbody>";

        for (var docType in docs) {
            if (docs.hasOwnProperty(docType) && docs[docType]) {
                var path = docs[docType];
                var downloadUrl = "addonmodules.php?module=sms_suite&action=download_doc&path=" + encodeURIComponent(path);
                html += "<tr>";
                html += "<td><i class=\"fa fa-file-pdf-o text-danger\"></i> <strong>" + docType + "</strong></td>";
                html += "<td><a href=\"" + downloadUrl + "\" class=\"btn btn-sm btn-primary\" target=\"_blank\"><i class=\"fa fa-download\"></i> Download</a> ";
                html += "<a href=\"" + downloadUrl + "&view=1\" class=\"btn btn-sm btn-info\" target=\"_blank\"><i class=\"fa fa-eye\"></i> View</a></td>";
                html += "</tr>";
            }
        }

        html += "</tbody></table>";
        document.getElementById("documentsContent").innerHTML = html;
        jQuery("#documentsModal").modal("show");
    }

    function showDetailsModal(req) {
        document.getElementById("detail_sender_id").textContent = req.sender_id || "-";
        document.getElementById("detail_type").textContent = req.type || "-";
        document.getElementById("detail_company").textContent = req.company_name || "-";
        document.getElementById("detail_use_case").textContent = req.use_case || "-";
        document.getElementById("detail_created").textContent = req.created_at || "-";
        jQuery("#detailsModal").modal("show");
    }
    </script>';
}

// ============================================================
// Billing Rates Configuration
// ============================================================

/**
 * Billing Rates Configuration Page
 */
function sms_suite_admin_billing_rates($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'save_rates') {
            // Save billing rates to module settings
            $settings = [
                'default_billing_mode' => $_POST['default_billing_mode'] ?? 'per_segment',
                'default_sms_rate' => (float)($_POST['default_sms_rate'] ?? 0.05),
                'default_whatsapp_rate' => (float)($_POST['default_whatsapp_rate'] ?? 0.08),
                'default_segment_rate' => (float)($_POST['default_segment_rate'] ?? 0.03),
                'credit_per_sms' => (int)($_POST['credit_per_sms'] ?? 1),
                'credit_per_segment' => (int)($_POST['credit_per_segment'] ?? 1),
            ];

            foreach ($settings as $key => $value) {
                Capsule::table('tbladdonmodules')
                    ->updateOrInsert(
                        ['module' => 'sms_suite', 'setting' => $key],
                        ['value' => $value]
                    );
            }

            echo '<div class="alert alert-success">Billing rates saved successfully.</div>';
        } elseif ($action === 'save_country_rate') {
            // Save country-specific rate
            $gatewayId = (int)$_POST['gateway_id'];
            $countryCode = strtoupper(trim($_POST['country_code']));
            $countryName = SecurityHelper::sanitize($_POST['country_name']);

            Capsule::table('mod_sms_gateway_countries')
                ->updateOrInsert(
                    ['gateway_id' => $gatewayId, 'country_code' => $countryCode],
                    [
                        'country_name' => $countryName,
                        'sms_rate' => (float)$_POST['sms_rate'],
                        'whatsapp_rate' => (float)$_POST['whatsapp_rate'],
                        'status' => isset($_POST['status']) ? 1 : 0,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );

            echo '<div class="alert alert-success">Country rate saved.</div>';
        } elseif ($action === 'delete_country_rate') {
            Capsule::table('mod_sms_gateway_countries')
                ->where('id', (int)$_POST['rate_id'])
                ->delete();

            echo '<div class="alert alert-success">Country rate deleted.</div>';
        } elseif ($action === 'save_destination_rate') {
            $countryCode = strtoupper(trim($_POST['dest_country_code'] ?? ''));
            $network = trim($_POST['dest_network'] ?? '') ?: null;

            if (!empty($countryCode)) {
                Capsule::table('mod_sms_destination_rates')
                    ->updateOrInsert(
                        ['country_code' => $countryCode, 'network' => $network],
                        [
                            'sms_rate' => (float)($_POST['dest_sms_rate'] ?? 0),
                            'whatsapp_rate' => (float)($_POST['dest_whatsapp_rate'] ?? 0),
                            'credit_cost' => max(1, (int)($_POST['dest_credit_cost'] ?? 1)),
                            'status' => isset($_POST['dest_status']) ? 1 : 0,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                echo '<div class="alert alert-success">Destination rate saved.</div>';
            } else {
                echo '<div class="alert alert-danger">Country code is required.</div>';
            }
        } elseif ($action === 'delete_destination_rate') {
            Capsule::table('mod_sms_destination_rates')
                ->where('id', (int)$_POST['dest_rate_id'])
                ->delete();

            echo '<div class="alert alert-success">Destination rate deleted.</div>';
        }
    }

    // Get current settings
    $moduleSettings = Capsule::table('tbladdonmodules')
        ->where('module', 'sms_suite')
        ->pluck('value', 'setting');

    $defaultBillingMode = $moduleSettings['default_billing_mode'] ?? 'per_segment';
    $defaultSmsRate = $moduleSettings['default_sms_rate'] ?? '0.05';
    $defaultWhatsappRate = $moduleSettings['default_whatsapp_rate'] ?? '0.08';
    $defaultSegmentRate = $moduleSettings['default_segment_rate'] ?? '0.03';
    $creditPerSms = $moduleSettings['credit_per_sms'] ?? '1';
    $creditPerSegment = $moduleSettings['credit_per_segment'] ?? '1';

    // Get gateways
    $gateways = Capsule::table('mod_sms_gateways')->where('status', 1)->get();

    // Get country rates
    $countryRates = Capsule::table('mod_sms_gateway_countries as gc')
        ->leftJoin('mod_sms_gateways as g', 'gc.gateway_id', '=', 'g.id')
        ->select(['gc.*', 'g.name as gateway_name'])
        ->orderBy('g.name')
        ->orderBy('gc.country_name')
        ->get();

    // Get destination rates
    $destinationRates = Capsule::table('mod_sms_destination_rates')
        ->orderBy('country_code')
        ->orderBy('network')
        ->get();

    // Get networks for dropdown
    $networks = Capsule::table('mod_sms_network_prefixes')
        ->select('operator_code', 'operator', 'country_code')
        ->where('status', 1)
        ->groupBy('operator_code', 'operator', 'country_code')
        ->orderBy('country_code')
        ->orderBy('operator')
        ->get();

    echo '<div class="row">';

    // Default Billing Settings
    echo '<div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-cogs"></i> Default Billing Settings</h3>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="form_action" value="save_rates">

                    <div class="form-group">
                        <label>Default Billing Mode</label>
                        <select name="default_billing_mode" class="form-control">
                            <option value="per_message"' . ($defaultBillingMode === 'per_message' ? ' selected' : '') . '>Per Message (flat rate per SMS)</option>
                            <option value="per_segment"' . ($defaultBillingMode === 'per_segment' ? ' selected' : '') . '>Per Segment (rate x segments)</option>
                            <option value="wallet"' . ($defaultBillingMode === 'wallet' ? ' selected' : '') . '>Wallet (prepaid balance)</option>
                            <option value="plan"' . ($defaultBillingMode === 'plan' ? ' selected' : '') . '>Plan/Credits (deduct credits)</option>
                        </select>
                        <p class="help-block">
                            <strong>Per Message:</strong> Fixed rate per SMS regardless of length<br>
                            <strong>Per Segment:</strong> Rate multiplied by number of segments (160 chars = 1 segment)<br>
                            <strong>Wallet:</strong> Deduct from prepaid balance (uses segment rate)<br>
                            <strong>Plan/Credits:</strong> Deduct credits from purchased packages
                        </p>
                    </div>

                    <hr>
                    <h5>Currency Rates (for Wallet mode)</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Default SMS Rate</label>
                                <div class="input-group">
                                    <span class="input-group-addon">$</span>
                                    <input type="number" name="default_sms_rate" class="form-control" step="0.0001" value="' . htmlspecialchars($defaultSmsRate) . '">
                                </div>
                                <p class="help-block">Per message (flat rate mode)</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Default Segment Rate</label>
                                <div class="input-group">
                                    <span class="input-group-addon">$</span>
                                    <input type="number" name="default_segment_rate" class="form-control" step="0.0001" value="' . htmlspecialchars($defaultSegmentRate) . '">
                                </div>
                                <p class="help-block">Per segment (segment mode)</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Default WhatsApp Rate</label>
                        <div class="input-group">
                            <span class="input-group-addon">$</span>
                            <input type="number" name="default_whatsapp_rate" class="form-control" step="0.0001" value="' . htmlspecialchars($defaultWhatsappRate) . '">
                        </div>
                    </div>

                    <hr>
                    <h5>Credit Settings (for Plan mode)</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Credits per SMS</label>
                                <input type="number" name="credit_per_sms" class="form-control" min="1" value="' . htmlspecialchars($creditPerSms) . '">
                                <p class="help-block">Flat rate mode</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Credits per Segment</label>
                                <input type="number" name="credit_per_segment" class="form-control" min="1" value="' . htmlspecialchars($creditPerSegment) . '">
                                <p class="help-block">Segment mode</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Settings</button>
                </form>
            </div>
        </div>
    </div>';

    // Billing Mode Explanation
    echo '<div class="col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-info-circle"></i> Billing Modes Explained</h3>
            </div>
            <div class="panel-body">
                <h5><i class="fa fa-envelope"></i> Per Message</h5>
                <p>Client is charged a flat rate per SMS, regardless of message length. A 300-character message costs the same as a 50-character message.</p>

                <h5><i class="fa fa-puzzle-piece"></i> Per Segment</h5>
                <p>Messages are split into segments (160 chars GSM / 70 chars Unicode). Client is charged per segment. A 300-character message = 2 segments = 2x rate.</p>

                <h5><i class="fa fa-wallet"></i> Wallet</h5>
                <p>Client pre-pays into a wallet balance. Each message deducts from their balance based on segment rate. They can top-up via invoice.</p>

                <h5><i class="fa fa-ticket"></i> Plan/Credits</h5>
                <p>Client purchases credit packages. Each message deducts credits. No currency involved - pure credit-based billing.</p>

                <hr>
                <div class="alert alert-warning">
                    <i class="fa fa-user"></i> <strong>Per-Client Override:</strong> You can override the billing mode for individual clients in their SMS Suite settings (Client Profile > SMS Panel > Settings).
                </div>
            </div>
        </div>
    </div>';

    echo '</div>'; // End row

    // Country-Specific Rates
    echo '<div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-globe"></i> Country-Specific Rates
                <button class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#addCountryRateModal">
                    <i class="fa fa-plus"></i> Add Country Rate
                </button>
            </h3>
        </div>
        <div class="panel-body">
            <p class="text-muted">Set different rates per country per gateway. These override the default rates.</p>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Gateway</th>
                        <th>Country</th>
                        <th>Code</th>
                        <th>SMS Rate</th>
                        <th>WhatsApp Rate</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if ($countryRates->isEmpty()) {
        echo '<tr><td colspan="7" class="text-center text-muted">No country-specific rates configured. Default rates will be used.</td></tr>';
    } else {
        foreach ($countryRates as $rate) {
            $status = $rate->status ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';
            echo '<tr>
                <td>' . htmlspecialchars($rate->gateway_name ?? 'Unknown') . '</td>
                <td>' . htmlspecialchars($rate->country_name) . '</td>
                <td><code>' . htmlspecialchars($rate->country_code) . '</code></td>
                <td>$' . number_format($rate->sms_rate, 4) . '</td>
                <td>$' . number_format($rate->whatsapp_rate, 4) . '</td>
                <td>' . $status . '</td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this rate?\');">
                        <input type="hidden" name="form_action" value="delete_country_rate">
                        <input type="hidden" name="rate_id" value="' . $rate->id . '">
                        <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
            </tr>';
        }
    }

    echo '</tbody></table></div></div>';

    // ==========================================
    // Destination Rates Section
    // ==========================================
    echo '<div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-map-marker"></i> Destination Rates
                <button class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#addDestinationRateModal">
                    <i class="fa fa-plus"></i> Add Destination Rate
                </button>
            </h3>
        </div>
        <div class="panel-body">
            <p class="text-muted">Set different rates per destination country and network. These are used when no client-specific rate exists. The <code>credit_cost</code> column controls how many credits are deducted per segment in Plan mode.</p>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>Network</th>
                        <th>SMS Rate</th>
                        <th>WhatsApp Rate</th>
                        <th>Credit Cost</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if ($destinationRates->isEmpty()) {
        echo '<tr><td colspan="7" class="text-center text-muted">No destination rates configured. Default rates will be used for all destinations.</td></tr>';
    } else {
        foreach ($destinationRates as $dr) {
            $drStatus = $dr->status ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';
            $networkDisplay = $dr->network ? htmlspecialchars(ucfirst($dr->network)) : '<em class="text-muted">All networks</em>';
            echo '<tr>
                <td><code>' . htmlspecialchars($dr->country_code) . '</code></td>
                <td>' . $networkDisplay . '</td>
                <td>$' . number_format($dr->sms_rate, 6) . '</td>
                <td>$' . number_format($dr->whatsapp_rate, 6) . '</td>
                <td><span class="badge">' . (int)$dr->credit_cost . '</span> credit' . ($dr->credit_cost != 1 ? 's' : '') . '/segment</td>
                <td>' . $drStatus . '</td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this destination rate?\');">
                        <input type="hidden" name="form_action" value="delete_destination_rate">
                        <input type="hidden" name="dest_rate_id" value="' . $dr->id . '">
                        <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
            </tr>';
        }
    }

    echo '</tbody></table></div></div>';

    // Build network options for destination rate modal
    $networkOptions = '<option value="">All networks (country-wide default)</option>';
    foreach ($networks as $nw) {
        $networkOptions .= '<option value="' . htmlspecialchars(strtolower($nw->operator_code ?: $nw->operator)) . '">'
            . htmlspecialchars($nw->operator) . ' (' . htmlspecialchars($nw->country_code) . ')</option>';
    }

    // Add Destination Rate Modal
    echo '<div class="modal fade" id="addDestinationRateModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="save_destination_rate">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-plus"></i> Add Destination Rate</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Country Code <span class="text-danger">*</span></label>
                            <input type="text" name="dest_country_code" class="form-control" required maxlength="5" placeholder="e.g. KE, US, GB">
                            <p class="help-block">ISO country dial code (254, 1, 44) or ISO alpha-2 (KE, US, GB)</p>
                        </div>
                        <div class="form-group">
                            <label>Network</label>
                            <select name="dest_network" class="form-control">
                                ' . $networkOptions . '
                            </select>
                            <p class="help-block">Leave blank for country-wide default rate</p>
                        </div>
                        <div class="form-group">
                            <label>SMS Rate</label>
                            <div class="input-group">
                                <span class="input-group-addon">$</span>
                                <input type="number" name="dest_sms_rate" class="form-control" step="0.000001" value="0.05">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>WhatsApp Rate</label>
                            <div class="input-group">
                                <span class="input-group-addon">$</span>
                                <input type="number" name="dest_whatsapp_rate" class="form-control" step="0.000001" value="0.08">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Credit Cost (Plan mode)</label>
                            <input type="number" name="dest_credit_cost" class="form-control" min="1" value="1">
                            <p class="help-block">Credits deducted per segment. e.g. 1 for local, 3 for international.</p>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="dest_status" value="1" checked> Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Destination Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Gateway options for modal
    $gatewayOptions = '';
    foreach ($gateways as $gw) {
        $gatewayOptions .= '<option value="' . $gw->id . '">' . htmlspecialchars($gw->name) . '</option>';
    }

    // Add Country Rate Modal
    echo '<div class="modal fade" id="addCountryRateModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="save_country_rate">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-plus"></i> Add Country Rate</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Gateway <span class="text-danger">*</span></label>
                            <select name="gateway_id" class="form-control" required>
                                ' . $gatewayOptions . '
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Country Code <span class="text-danger">*</span></label>
                                    <input type="text" name="country_code" class="form-control" required maxlength="5" placeholder="US">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Country Name</label>
                                    <input type="text" name="country_name" class="form-control" placeholder="United States">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>SMS Rate</label>
                            <div class="input-group">
                                <span class="input-group-addon">$</span>
                                <input type="number" name="sms_rate" class="form-control" step="0.0001" value="0.05">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>WhatsApp Rate</label>
                            <div class="input-group">
                                <span class="input-group-addon">$</span>
                                <input type="number" name="whatsapp_rate" class="form-control" step="0.0001" value="0.08">
                            </div>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="status" value="1" checked> Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';
}

/**
 * AJAX: Get message details
 */
/**
 * AJAX: Search clients for Select2 dropdowns
 */
function sms_suite_ajax_search_clients()
{
    header('Content-Type: application/json; charset=utf-8');

    $term = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 25;

    $query = Capsule::table('tblclients')
        ->select(['id', 'firstname', 'lastname', 'companyname', 'email']);

    if (!empty($term)) {
        $query->where(function ($q) use ($term) {
            $q->where('firstname', 'LIKE', "%{$term}%")
              ->orWhere('lastname', 'LIKE', "%{$term}%")
              ->orWhere('companyname', 'LIKE', "%{$term}%")
              ->orWhere('email', 'LIKE', "%{$term}%")
              ->orWhere('id', '=', $term);
        });
    }

    $total = $query->count();
    $clients = $query->orderBy('firstname')->orderBy('lastname')
        ->skip(($page - 1) * $perPage)->take($perPage)->get();

    $results = [];
    foreach ($clients as $c) {
        $name = trim($c->firstname . ' ' . $c->lastname);
        if ($c->companyname) {
            $name .= ' (' . $c->companyname . ')';
        }
        $results[] = [
            'id' => $c->id,
            'text' => $name . ' - ' . $c->email,
        ];
    }

    echo json_encode([
        'results' => $results,
        'pagination' => ['more' => ($page * $perPage) < $total],
    ]);
    exit;
}

/**
 * AJAX: Search sender IDs for Select2 dropdowns
 */
function sms_suite_ajax_search_sender_ids()
{
    header('Content-Type: application/json; charset=utf-8');

    $term = trim($_GET['q'] ?? '');

    // Get from both mod_sms_sender_ids AND mod_sms_sender_id_pool
    $results = [];

    // Old table
    $senderIds = Capsule::table('mod_sms_sender_ids')
        ->where('status', 'active')
        ->when(!empty($term), function ($q) use ($term) {
            $q->where('sender_id', 'LIKE', "%{$term}%");
        })
        ->orderBy('sender_id')
        ->limit(50)
        ->get();

    foreach ($senderIds as $sid) {
        $label = $sid->sender_id;
        if ($sid->client_id) {
            $label .= ' (Client)';
        } else {
            $label .= ' (Global)';
        }
        $results[] = ['id' => $sid->sender_id, 'text' => $label];
    }

    // Pool table
    try {
        $poolIds = Capsule::table('mod_sms_sender_id_pool')
            ->where('status', 'active')
            ->when(!empty($term), function ($q) use ($term) {
                $q->where('sender_id', 'LIKE', "%{$term}%");
            })
            ->orderBy('sender_id')
            ->limit(50)
            ->get();

        foreach ($poolIds as $pid) {
            // Avoid duplicates
            $existing = array_column($results, 'id');
            $label = $pid->sender_id . ' [' . ($pid->network ?? 'all') . ']';
            if (!in_array($pid->sender_id, $existing)) {
                $results[] = ['id' => $pid->sender_id, 'text' => $label . ' (Pool)'];
            }
        }
    } catch (\Exception $e) {
        // Pool table might not exist yet
    }

    echo json_encode(['results' => $results]);
    exit;
}

function sms_suite_ajax_message_detail()
{
    header('Content-Type: text/html; charset=utf-8');

    $msgId = (int)($_GET['id'] ?? 0);
    if (!$msgId) {
        echo '<div class="alert alert-danger">Invalid message ID</div>';
        exit;
    }

    $msg = Capsule::table('mod_sms_messages')
        ->leftJoin('mod_sms_gateways', 'mod_sms_messages.gateway_id', '=', 'mod_sms_gateways.id')
        ->leftJoin('tblclients', 'mod_sms_messages.client_id', '=', 'tblclients.id')
        ->leftJoin('mod_sms_campaigns', 'mod_sms_messages.campaign_id', '=', 'mod_sms_campaigns.id')
        ->select([
            'mod_sms_messages.*',
            'mod_sms_gateways.name as gateway_name',
            'mod_sms_gateways.type as gateway_type',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.email as client_email',
            'mod_sms_campaigns.name as campaign_name',
        ])
        ->where('mod_sms_messages.id', $msgId)
        ->first();

    if (!$msg) {
        echo '<div class="alert alert-danger">Message not found</div>';
        exit;
    }

    $statusClass = sms_suite_status_class($msg->status);

    // Parse gateway response if exists
    $gatewayResponse = '';
    if (!empty($msg->gateway_response)) {
        $decoded = json_decode($msg->gateway_response, true);
        if ($decoded) {
            $gatewayResponse = '<pre style="max-height:200px; overflow:auto; font-size:11px;">' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . '</pre>';
        } else {
            $gatewayResponse = '<pre style="max-height:200px; overflow:auto; font-size:11px;">' . htmlspecialchars($msg->gateway_response) . '</pre>';
        }
    }

    echo '<div class="row">';

    // Left column - Basic info
    echo '<div class="col-md-6">';
    echo '<h5><i class="fa fa-info-circle"></i> Message Information</h5>';
    echo '<table class="table table-condensed">';
    echo '<tr><th width="120">Message ID:</th><td>' . $msg->id . '</td></tr>';
    echo '<tr><th>Provider ID:</th><td><code>' . htmlspecialchars($msg->provider_message_id ?: 'N/A') . '</code></td></tr>';
    echo '<tr><th>Status:</th><td><span class="label label-' . $statusClass . '">' . ucfirst($msg->status) . '</span></td></tr>';
    echo '<tr><th>To:</th><td><code>' . htmlspecialchars($msg->to_number) . '</code></td></tr>';
    echo '<tr><th>From:</th><td>' . htmlspecialchars($msg->sender_id ?: 'Default') . '</td></tr>';
    echo '<tr><th>Channel:</th><td>' . ucfirst($msg->channel ?? 'sms') . '</td></tr>';
    echo '<tr><th>Encoding:</th><td>' . ($msg->encoding ?? 'GSM7') . '</td></tr>';
    echo '<tr><th>Segments:</th><td>' . $msg->segments . '</td></tr>';
    echo '<tr><th>Cost:</th><td>$' . number_format($msg->cost, 4) . '</td></tr>';
    echo '</table>';
    echo '</div>';

    // Right column - Gateway/Client info
    echo '<div class="col-md-6">';
    echo '<h5><i class="fa fa-server"></i> Delivery Details</h5>';
    echo '<table class="table table-condensed">';
    echo '<tr><th width="120">Gateway:</th><td>' . htmlspecialchars($msg->gateway_name ?: 'N/A') . ' <small class="text-muted">(' . ($msg->gateway_type ?? 'unknown') . ')</small></td></tr>';
    if (!empty($msg->firstname)) {
        echo '<tr><th>Client:</th><td>' . htmlspecialchars($msg->firstname . ' ' . $msg->lastname) . ' <small class="text-muted">(' . htmlspecialchars($msg->client_email) . ')</small></td></tr>';
    }
    if (!empty($msg->campaign_name)) {
        echo '<tr><th>Campaign:</th><td>' . htmlspecialchars($msg->campaign_name) . '</td></tr>';
    }
    echo '<tr><th>Created:</th><td>' . $msg->created_at . '</td></tr>';
    if (!empty($msg->sent_at)) {
        echo '<tr><th>Sent:</th><td>' . $msg->sent_at . '</td></tr>';
    }
    if (!empty($msg->delivered_at)) {
        echo '<tr><th>Delivered:</th><td>' . $msg->delivered_at . '</td></tr>';
    }
    echo '</table>';
    echo '</div>';

    echo '</div>';

    // Message content
    echo '<hr>';
    echo '<h5><i class="fa fa-comment"></i> Message Content</h5>';
    echo '<div class="well" style="word-wrap:break-word;">' . nl2br(htmlspecialchars($msg->message)) . '</div>';

    // Error details (if any)
    if (!empty($msg->error)) {
        echo '<div class="alert alert-danger">';
        echo '<h5><i class="fa fa-exclamation-triangle"></i> Error Details</h5>';
        echo '<p><strong>Error Message:</strong></p>';
        echo '<pre style="background:#f5f5f5; padding:10px; border-radius:4px;">' . htmlspecialchars($msg->error) . '</pre>';
        echo '</div>';
    }

    // Gateway response
    if (!empty($gatewayResponse)) {
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading"><h5 class="panel-title" style="margin:0;"><i class="fa fa-code"></i> Gateway Response (Debug)</h5></div>';
        echo '<div class="panel-body" style="padding:10px;">' . $gatewayResponse . '</div>';
        echo '</div>';
    }

    // Timeline
    echo '<h5><i class="fa fa-clock-o"></i> Message Timeline</h5>';
    echo '<ul class="list-unstyled">';
    echo '<li><i class="fa fa-plus-circle text-info"></i> <strong>Created:</strong> ' . $msg->created_at . '</li>';
    if (!empty($msg->sent_at)) {
        echo '<li><i class="fa fa-paper-plane text-primary"></i> <strong>Sent to Gateway:</strong> ' . $msg->sent_at . '</li>';
    }
    if (!empty($msg->delivered_at)) {
        echo '<li><i class="fa fa-check-circle text-success"></i> <strong>Delivered:</strong> ' . $msg->delivered_at . '</li>';
    }
    if ($msg->status === 'failed' && !empty($msg->updated_at)) {
        echo '<li><i class="fa fa-times-circle text-danger"></i> <strong>Failed:</strong> ' . $msg->updated_at . '</li>';
    }
    echo '</ul>';

    exit;
}

/**
 * AJAX: Retry failed message
 */
function sms_suite_ajax_retry_message()
{
    header('Content-Type: application/json');

    $msgId = (int)($_GET['id'] ?? 0);
    if (!$msgId) {
        echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
        exit;
    }

    $msg = Capsule::table('mod_sms_messages')
        ->where('id', $msgId)
        ->first();

    if (!$msg) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit;
    }

    if ($msg->status !== 'failed') {
        echo json_encode(['success' => false, 'message' => 'Only failed messages can be retried']);
        exit;
    }

    // Reset message to queued status for retry
    Capsule::table('mod_sms_messages')
        ->where('id', $msgId)
        ->update([
            'status' => 'queued',
            'error' => null,
            'provider_message_id' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'gateway_response' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    logActivity('SMS Suite: Message #' . $msgId . ' queued for retry');

    echo json_encode(['success' => true, 'message' => 'Message queued for retry. It will be processed shortly.']);
    exit;
}

/**
 * Network Prefixes Management Page
 */
function sms_suite_admin_network_prefixes($vars, $lang)
{
    $modulelink = $vars['modulelink'];

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'create') {
            try {
                Capsule::table('mod_sms_network_prefixes')->insert([
                    'country_code' => SecurityHelper::sanitize($_POST['country_code']),
                    'country_name' => SecurityHelper::sanitize($_POST['country_name']),
                    'prefix' => SecurityHelper::sanitize($_POST['prefix']),
                    'operator' => SecurityHelper::sanitize($_POST['operator']),
                    'operator_code' => SecurityHelper::sanitize($_POST['operator_code'] ?? ''),
                    'network_type' => $_POST['network_type'] ?? 'mobile',
                    'mcc' => SecurityHelper::sanitize($_POST['mcc'] ?? ''),
                    'mnc' => SecurityHelper::sanitize($_POST['mnc'] ?? ''),
                    'status' => (int)($_POST['status'] ?? 1),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                echo '<div class="alert alert-success">Network prefix added successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } elseif ($action === 'update') {
            try {
                Capsule::table('mod_sms_network_prefixes')
                    ->where('id', (int)$_POST['prefix_id'])
                    ->update([
                        'country_code' => SecurityHelper::sanitize($_POST['country_code']),
                        'country_name' => SecurityHelper::sanitize($_POST['country_name']),
                        'prefix' => SecurityHelper::sanitize($_POST['prefix']),
                        'operator' => SecurityHelper::sanitize($_POST['operator']),
                        'operator_code' => SecurityHelper::sanitize($_POST['operator_code'] ?? ''),
                        'network_type' => $_POST['network_type'] ?? 'mobile',
                        'mcc' => SecurityHelper::sanitize($_POST['mcc'] ?? ''),
                        'mnc' => SecurityHelper::sanitize($_POST['mnc'] ?? ''),
                        'status' => (int)($_POST['status'] ?? 1),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                echo '<div class="alert alert-success">Network prefix updated successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } elseif ($action === 'delete') {
            try {
                Capsule::table('mod_sms_network_prefixes')
                    ->where('id', (int)$_POST['prefix_id'])
                    ->delete();
                echo '<div class="alert alert-success">Network prefix deleted.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } elseif ($action === 'bulk_import') {
            $imported = 0;
            $skipped = 0;
            $errors = [];

            $importData = $_POST['import_data'] ?? '';
            $lines = array_filter(array_map('trim', explode("\n", $importData)));

            foreach ($lines as $line) {
                // Skip comments and headers
                if (empty($line) || $line[0] === '#' || stripos($line, 'country_code') === 0) {
                    continue;
                }

                // Parse CSV line: country_code,country_name,prefix,operator,operator_code,network_type,mcc,mnc
                $parts = str_getcsv($line);
                if (count($parts) < 4) {
                    $skipped++;
                    continue;
                }

                try {
                    Capsule::table('mod_sms_network_prefixes')->insertOrIgnore([
                        'country_code' => trim($parts[0]),
                        'country_name' => trim($parts[1] ?? ''),
                        'prefix' => trim($parts[2]),
                        'operator' => trim($parts[3]),
                        'operator_code' => trim($parts[4] ?? ''),
                        'network_type' => trim($parts[5] ?? 'mobile'),
                        'mcc' => trim($parts[6] ?? ''),
                        'mnc' => trim($parts[7] ?? ''),
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    // Check if it's a duplicate
                    if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                        $skipped++;
                    } else {
                        $errors[] = "Line: $line - " . $e->getMessage();
                    }
                }
            }

            if ($imported > 0) {
                echo '<div class="alert alert-success">Imported ' . $imported . ' prefixes successfully. Skipped ' . $skipped . ' duplicates/invalid lines.</div>';
            }
            if (!empty($errors)) {
                echo '<div class="alert alert-warning">Some errors occurred:<br>' . implode('<br>', array_slice($errors, 0, 5)) . '</div>';
            }
        } elseif ($action === 'import_kenya') {
            // Pre-built Kenya prefixes
            $kenyaPrefixes = sms_suite_get_kenya_prefixes();
            $imported = 0;
            $skipped = 0;

            foreach ($kenyaPrefixes as $prefix) {
                try {
                    Capsule::table('mod_sms_network_prefixes')->insertOrIgnore([
                        'country_code' => '254',
                        'country_name' => 'Kenya',
                        'prefix' => $prefix['prefix'],
                        'operator' => $prefix['operator'],
                        'operator_code' => $prefix['operator_code'] ?? '',
                        'network_type' => 'mobile',
                        'mcc' => '639',
                        'mnc' => $prefix['mnc'] ?? '',
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                }
            }

            echo '<div class="alert alert-success">Imported ' . $imported . ' Kenya prefixes. Skipped ' . $skipped . ' duplicates.</div>';
        }
    }

    // Get filter parameters
    $filterCountry = $_GET['filter_country'] ?? '';
    $filterOperator = $_GET['filter_operator'] ?? '';

    // Build query
    $query = Capsule::table('mod_sms_network_prefixes');

    if (!empty($filterCountry)) {
        $query->where('country_code', $filterCountry);
    }
    if (!empty($filterOperator)) {
        $query->where('operator', 'like', '%' . $filterOperator . '%');
    }

    $prefixes = $query->orderBy('country_code')
        ->orderBy('operator')
        ->orderBy('prefix')
        ->get();

    // Get distinct countries for filter
    $countries = Capsule::table('mod_sms_network_prefixes')
        ->select(['country_code', 'country_name'])
        ->distinct()
        ->orderBy('country_name')
        ->get();

    // Get distinct operators for filter
    $operators = Capsule::table('mod_sms_network_prefixes')
        ->select('operator')
        ->distinct()
        ->orderBy('operator')
        ->pluck('operator')
        ->toArray();

    // Statistics
    $totalPrefixes = Capsule::table('mod_sms_network_prefixes')->count();
    $countryCount = Capsule::table('mod_sms_network_prefixes')->distinct('country_code')->count('country_code');
    $operatorCount = Capsule::table('mod_sms_network_prefixes')->distinct('operator')->count('operator');

    echo '<div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-globe"></i> Network Prefixes Management
                <div class="btn-group pull-right">
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#createPrefixModal">
                        <i class="fa fa-plus"></i> Add Prefix
                    </button>
                    <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#importModal">
                        <i class="fa fa-upload"></i> Bulk Import
                    </button>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="form_action" value="import_kenya">
                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm(\'Import all Kenya (254) network prefixes?\');">
                            <i class="fa fa-flag"></i> Import Kenya Prefixes
                        </button>
                    </form>
                </div>
            </h3>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="well text-center">
                        <h4>' . number_format($totalPrefixes) . '</h4>
                        <small>Total Prefixes</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="well text-center">
                        <h4>' . number_format($countryCount) . '</h4>
                        <small>Countries</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="well text-center">
                        <h4>' . number_format($operatorCount) . '</h4>
                        <small>Operators</small>
                    </div>
                </div>
            </div>

            <form method="get" class="form-inline" style="margin-bottom:15px;">
                <input type="hidden" name="module" value="sms_suite">
                <input type="hidden" name="action" value="network_prefixes">
                <div class="form-group">
                    <label>Country:</label>
                    <select name="filter_country" class="form-control input-sm">
                        <option value="">All Countries</option>';

    foreach ($countries as $c) {
        $selected = ($filterCountry === $c->country_code) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($c->country_code) . '" ' . $selected . '>+' . htmlspecialchars($c->country_code) . ' ' . htmlspecialchars($c->country_name) . '</option>';
    }

    echo '</select>
                </div>
                <div class="form-group">
                    <label>Operator:</label>
                    <select name="filter_operator" class="form-control input-sm">
                        <option value="">All Operators</option>';

    foreach ($operators as $op) {
        $selected = ($filterOperator === $op) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($op) . '" ' . $selected . '>' . htmlspecialchars($op) . '</option>';
    }

    echo '</select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="' . $modulelink . '&action=network_prefixes" class="btn btn-default btn-sm">Reset</a>
            </form>

            <table class="table table-striped table-condensed">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>Prefix</th>
                        <th>Operator</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>MCC/MNC</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if (count($prefixes) === 0) {
        echo '<tr><td colspan="8" class="text-center text-muted">No prefixes found. Use "Import Kenya Prefixes" to get started.</td></tr>';
    } else {
        foreach ($prefixes as $p) {
            $statusLabel = $p->status ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';

            // Color code operators
            $operatorClass = '';
            $opLower = strtolower($p->operator);
            if (strpos($opLower, 'safaricom') !== false) {
                $operatorClass = 'text-success';
            } elseif (strpos($opLower, 'airtel') !== false) {
                $operatorClass = 'text-danger';
            } elseif (strpos($opLower, 'telkom') !== false) {
                $operatorClass = 'text-info';
            }

            echo '<tr>
                <td><strong>+' . htmlspecialchars($p->country_code) . '</strong> <small class="text-muted">' . htmlspecialchars($p->country_name) . '</small></td>
                <td><code>' . htmlspecialchars($p->prefix) . '</code></td>
                <td class="' . $operatorClass . '"><strong>' . htmlspecialchars($p->operator) . '</strong></td>
                <td><small>' . htmlspecialchars($p->operator_code) . '</small></td>
                <td>' . ucfirst($p->network_type) . '</td>
                <td><small>' . htmlspecialchars($p->mcc) . '/' . htmlspecialchars($p->mnc) . '</small></td>
                <td>' . $statusLabel . '</td>
                <td>
                    <button class="btn btn-xs btn-primary" onclick=\'editPrefix(' . json_encode($p) . ')\' title="Edit">
                        <i class="fa fa-edit"></i>
                    </button>
                    <form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this prefix?\');">
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="prefix_id" value="' . $p->id . '">
                        <button type="submit" class="btn btn-xs btn-danger" title="Delete"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
            </tr>';
        }
    }

    echo '</tbody></table>
        </div>
    </div>';

    // Create Prefix Modal
    echo '<div class="modal fade" id="createPrefixModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="create">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-plus"></i> Add Network Prefix</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Country Code <span class="text-danger">*</span></label>
                                    <input type="text" name="country_code" class="form-control" required maxlength="5" placeholder="e.g., 254">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Country Name</label>
                                    <input type="text" name="country_name" class="form-control" placeholder="e.g., Kenya">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Prefix <span class="text-danger">*</span></label>
                                    <input type="text" name="prefix" class="form-control" required maxlength="10" placeholder="e.g., 7XX">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Operator <span class="text-danger">*</span></label>
                                    <input type="text" name="operator" class="form-control" required placeholder="e.g., Safaricom">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Operator Code</label>
                                    <input type="text" name="operator_code" class="form-control" placeholder="e.g., safaricom">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Network Type</label>
                                    <select name="network_type" class="form-control">
                                        <option value="mobile">Mobile</option>
                                        <option value="fixed">Fixed</option>
                                        <option value="voip">VoIP</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>MCC</label>
                                    <input type="text" name="mcc" class="form-control" maxlength="5" placeholder="e.g., 639">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>MNC</label>
                                    <input type="text" name="mnc" class="form-control" maxlength="5" placeholder="e.g., 02">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Prefix</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Edit Prefix Modal
    echo '<div class="modal fade" id="editPrefixModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="update">
                    <input type="hidden" name="prefix_id" id="edit_prefix_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-edit"></i> Edit Network Prefix</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Country Code <span class="text-danger">*</span></label>
                                    <input type="text" name="country_code" id="edit_country_code" class="form-control" required maxlength="5">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Country Name</label>
                                    <input type="text" name="country_name" id="edit_country_name" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Prefix <span class="text-danger">*</span></label>
                                    <input type="text" name="prefix" id="edit_prefix" class="form-control" required maxlength="10">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Operator <span class="text-danger">*</span></label>
                                    <input type="text" name="operator" id="edit_operator" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Operator Code</label>
                                    <input type="text" name="operator_code" id="edit_operator_code" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Network Type</label>
                                    <select name="network_type" id="edit_network_type" class="form-control">
                                        <option value="mobile">Mobile</option>
                                        <option value="fixed">Fixed</option>
                                        <option value="voip">VoIP</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>MCC</label>
                                    <input type="text" name="mcc" id="edit_mcc" class="form-control" maxlength="5">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>MNC</label>
                                    <input type="text" name="mnc" id="edit_mnc" class="form-control" maxlength="5">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" id="edit_status" class="form-control">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Import Modal
    echo '<div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="form_action" value="bulk_import">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-upload"></i> Bulk Import Network Prefixes</h4>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>CSV Format:</strong> country_code,country_name,prefix,operator,operator_code,network_type,mcc,mnc<br>
                            <strong>Example:</strong> 254,Kenya,7XX,Safaricom,safaricom,mobile,639,02
                        </div>
                        <div class="form-group">
                            <label>Paste CSV Data (one prefix per line)</label>
                            <textarea name="import_data" class="form-control" rows="15" placeholder="254,Kenya,700,Safaricom,safaricom,mobile,639,02
254,Kenya,701,Safaricom,safaricom,mobile,639,02
254,Kenya,722,Safaricom,safaricom,mobile,639,02"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Import Prefixes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // JavaScript for edit modal
    echo '<script>
    function editPrefix(data) {
        document.getElementById("edit_prefix_id").value = data.id;
        document.getElementById("edit_country_code").value = data.country_code;
        document.getElementById("edit_country_name").value = data.country_name || "";
        document.getElementById("edit_prefix").value = data.prefix;
        document.getElementById("edit_operator").value = data.operator;
        document.getElementById("edit_operator_code").value = data.operator_code || "";
        document.getElementById("edit_network_type").value = data.network_type || "mobile";
        document.getElementById("edit_mcc").value = data.mcc || "";
        document.getElementById("edit_mnc").value = data.mnc || "";
        document.getElementById("edit_status").value = data.status;
        $("#editPrefixModal").modal("show");
    }
    </script>';
}

/**
 * Download document handler for admin
 */
function sms_suite_admin_download_document()
{
    // Security check - only admins can access
    if (empty($_SESSION['adminid'])) {
        die('Unauthorized access');
    }

    $path = $_GET['path'] ?? '';
    $viewOnly = isset($_GET['view']);

    if (empty($path)) {
        die('No document specified');
    }

    // Sanitize path - prevent directory traversal
    $path = str_replace(['..', '\\'], ['', '/'], $path);

    // Build full path
    $basePath = realpath(__DIR__ . '/../');
    $fullPath = $basePath . '/' . $path;

    // Verify the file is within the allowed directory
    $realFullPath = realpath($fullPath);
    if ($realFullPath === false || strpos($realFullPath, $basePath) !== 0) {
        die('Invalid document path');
    }

    if (!file_exists($realFullPath)) {
        die('Document not found');
    }

    // Get file info
    $filename = basename($realFullPath);
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($realFullPath);

    // Only allow safe file types
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg',
    ];

    if (!in_array($mimeType, $allowedTypes)) {
        die('Invalid file type');
    }

    // Set headers
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($realFullPath));

    if ($viewOnly) {
        // Display inline for viewing
        header('Content-Disposition: inline; filename="' . $filename . '"');
    } else {
        // Force download
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }

    // Output file
    readfile($realFullPath);
    exit;
}

/**
 * Get Kenya mobile network prefixes
 */
function sms_suite_get_kenya_prefixes(): array
{
    return [
        // Safaricom
        ['prefix' => '700', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '701', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '702', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '703', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '704', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '705', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '706', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '707', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '708', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '709', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '710', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '711', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '712', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '713', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '714', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '715', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '716', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '717', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '718', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '719', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '720', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '721', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '722', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '723', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '724', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '725', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '726', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '727', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '728', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '729', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '740', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '741', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '742', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '743', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '745', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '746', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '748', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '757', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '758', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '759', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '768', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '769', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '790', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '791', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '792', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '793', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '794', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '795', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '796', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '797', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '798', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '799', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '110', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '111', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '112', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '113', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '114', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],
        ['prefix' => '115', 'operator' => 'Safaricom', 'operator_code' => 'safaricom', 'mnc' => '02'],

        // Airtel Kenya
        ['prefix' => '730', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '731', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '732', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '733', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '734', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '735', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '736', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '737', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '738', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '739', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '750', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '751', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '752', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '753', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '754', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '755', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '756', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '780', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '781', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '782', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '783', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '784', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '785', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '786', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '787', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '788', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '789', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '100', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '101', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '102', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '103', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '104', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '105', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],
        ['prefix' => '106', 'operator' => 'Airtel Kenya', 'operator_code' => 'airtel', 'mnc' => '03'],

        // Telkom Kenya
        ['prefix' => '770', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '771', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '772', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '773', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '774', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '775', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '776', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '777', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '778', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],
        ['prefix' => '779', 'operator' => 'Telkom Kenya', 'operator_code' => 'telkom', 'mnc' => '07'],

        // Faiba 4G (Jamii Telecom)
        ['prefix' => '747', 'operator' => 'Faiba 4G', 'operator_code' => 'faiba', 'mnc' => '04'],

        // Equitel (Finserve)
        ['prefix' => '763', 'operator' => 'Equitel', 'operator_code' => 'equitel', 'mnc' => '05'],
        ['prefix' => '764', 'operator' => 'Equitel', 'operator_code' => 'equitel', 'mnc' => '05'],
        ['prefix' => '765', 'operator' => 'Equitel', 'operator_code' => 'equitel', 'mnc' => '05'],
        ['prefix' => '766', 'operator' => 'Equitel', 'operator_code' => 'equitel', 'mnc' => '05'],

        // Mobile Pay
        ['prefix' => '760', 'operator' => 'Mobile Pay', 'operator_code' => 'mobilepay', 'mnc' => ''],
        ['prefix' => '761', 'operator' => 'Mobile Pay', 'operator_code' => 'mobilepay', 'mnc' => ''],
        ['prefix' => '762', 'operator' => 'Mobile Pay', 'operator_code' => 'mobilepay', 'mnc' => ''],

        // Homeland Media
        ['prefix' => '767', 'operator' => 'Homeland Media', 'operator_code' => 'homeland', 'mnc' => ''],
    ];
}

/**
 * Admin Contact Groups Management
 */
function sms_suite_admin_contact_groups($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $message = '';
    $messageType = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create group
        if (isset($_POST['create_group'])) {
            $name = trim($_POST['group_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $message = 'Group name is required.';
                $messageType = 'danger';
            } else {
                Capsule::table('mod_sms_contact_groups')->insert([
                    'client_id' => 0, // Admin group
                    'name' => $name,
                    'description' => $description,
                    'status' => 1,
                    'contact_count' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $message = 'Contact group created successfully.';
                $messageType = 'success';
            }
        }

        // Update group
        if (isset($_POST['update_group'])) {
            $groupId = (int)$_POST['group_id'];
            $name = trim($_POST['group_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $message = 'Group name is required.';
                $messageType = 'danger';
            } else {
                Capsule::table('mod_sms_contact_groups')
                    ->where('id', $groupId)
                    ->where('client_id', 0)
                    ->update([
                        'name' => $name,
                        'description' => $description,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                $message = 'Contact group updated.';
                $messageType = 'success';
            }
        }

        // Delete group
        if (isset($_POST['delete_group'])) {
            $groupId = (int)$_POST['group_id'];
            $contactCount = Capsule::table('mod_sms_contacts')
                ->where('group_id', $groupId)
                ->where('client_id', 0)
                ->count();

            if ($contactCount > 0) {
                $message = "Cannot delete group with {$contactCount} contacts. Remove contacts first.";
                $messageType = 'danger';
            } else {
                Capsule::table('mod_sms_contact_groups')
                    ->where('id', $groupId)
                    ->where('client_id', 0)
                    ->delete();
                $message = 'Contact group deleted.';
                $messageType = 'success';
            }
        }
    }

    // Get admin groups (client_id = 0)
    $groups = Capsule::table('mod_sms_contact_groups')
        ->where('client_id', 0)
        ->orderBy('name')
        ->get();

    // Update contact counts
    foreach ($groups as $group) {
        $count = Capsule::table('mod_sms_contacts')
            ->where('group_id', $group->id)
            ->count();
        if ($count != $group->contact_count) {
            Capsule::table('mod_sms_contact_groups')
                ->where('id', $group->id)
                ->update(['contact_count' => $count]);
            $group->contact_count = $count;
        }
    }

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<div class="row">';
    echo '<div class="col-sm-6"><h3 class="panel-title"><i class="fa fa-users"></i> Contact Groups (Admin)</h3></div>';
    echo '<div class="col-sm-6 text-right">';
    echo '<button class="btn btn-success btn-sm" data-toggle="modal" data-target="#createGroupModal"><i class="fa fa-plus"></i> Create Group</button>';
    echo '</div></div></div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    echo '<p class="text-muted">These are admin-level contact groups for your internal SMS campaigns.</p>';

    if (count($groups) > 0) {
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>Group Name</th><th>Description</th><th>Contacts</th><th>Created</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($groups as $group) {
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($group->name) . '</strong></td>';
            echo '<td>' . htmlspecialchars($group->description ?: '-') . '</td>';
            echo '<td><span class="badge">' . number_format($group->contact_count) . '</span>';
            if ($group->contact_count > 0) {
                echo ' <a href="' . $modulelink . '&action=contacts&group_id=' . $group->id . '" class="btn btn-xs btn-link">View</a>';
            }
            echo '</td>';
            echo '<td>' . date('Y-m-d', strtotime($group->created_at)) . '</td>';
            echo '<td>';
            echo '<button class="btn btn-xs btn-primary" onclick="editGroup(' . $group->id . ', \'' . addslashes($group->name) . '\', \'' . addslashes($group->description ?? '') . '\')"><i class="fa fa-edit"></i></button> ';
            echo '<button class="btn btn-xs btn-danger" onclick="deleteGroup(' . $group->id . ', \'' . addslashes($group->name) . '\', ' . $group->contact_count . ')"><i class="fa fa-trash"></i></button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<div class="text-center text-muted" style="padding: 40px;">';
        echo '<i class="fa fa-folder-open fa-3x"></i>';
        echo '<p style="margin-top: 15px;">No contact groups yet. Create your first group to organize contacts for campaigns.</p>';
        echo '</div>';
    }

    echo '</div></div>';

    // Create Group Modal
    echo '
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-folder-plus"></i> Create Contact Group</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Group Name <span class="text-danger">*</span></label>
                            <input type="text" name="group_name" class="form-control" required placeholder="e.g., Newsletter Subscribers">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional description..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_group" value="1" class="btn btn-success">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Edit Group Modal
    echo '
    <div class="modal fade" id="editGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="group_id" id="edit_group_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-edit"></i> Edit Contact Group</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Group Name <span class="text-danger">*</span></label>
                            <input type="text" name="group_name" id="edit_group_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_group" value="1" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Delete Group Modal
    echo '
    <div class="modal fade" id="deleteGroupModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="group_id" id="delete_group_id">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-trash"></i> Delete Group</h4>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete "<strong id="delete_group_name"></strong>"?</p>
                        <p id="delete_warning" class="text-danger" style="display:none;"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_group" value="1" class="btn btn-danger" id="delete_btn">Delete Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // JavaScript
    echo '
    <script>
    function editGroup(id, name, description) {
        document.getElementById("edit_group_id").value = id;
        document.getElementById("edit_group_name").value = name;
        document.getElementById("edit_description").value = description || "";
        $("#editGroupModal").modal("show");
    }

    function deleteGroup(id, name, contactCount) {
        document.getElementById("delete_group_id").value = id;
        document.getElementById("delete_group_name").textContent = name;

        var warning = document.getElementById("delete_warning");
        var btn = document.getElementById("delete_btn");

        if (contactCount > 0) {
            warning.textContent = "This group has " + contactCount + " contact(s). Remove them first.";
            warning.style.display = "block";
            btn.disabled = true;
        } else {
            warning.style.display = "none";
            btn.disabled = false;
        }

        $("#deleteGroupModal").modal("show");
    }
    </script>';
}

/**
 * Admin Contacts Management
 */
function sms_suite_admin_contacts($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $message = '';
    $messageType = '';
    $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add contact
        if (isset($_POST['add_contact'])) {
            $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contactGroupId = (int)($_POST['group_id'] ?? 0);

            if (empty($phone)) {
                $message = 'Phone number is required.';
                $messageType = 'danger';
            } else {
                // Check duplicate
                $exists = Capsule::table('mod_sms_contacts')
                    ->where('client_id', 0)
                    ->where('phone', $phone)
                    ->exists();

                if ($exists) {
                    $message = 'Contact with this phone number already exists.';
                    $messageType = 'warning';
                } else {
                    Capsule::table('mod_sms_contacts')->insert([
                        'client_id' => 0,
                        'group_id' => $contactGroupId ?: null,
                        'phone' => $phone,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'status' => 'subscribed',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $message = 'Contact added successfully.';
                    $messageType = 'success';

                    // Update group count
                    if ($contactGroupId) {
                        $count = Capsule::table('mod_sms_contacts')->where('group_id', $contactGroupId)->count();
                        Capsule::table('mod_sms_contact_groups')->where('id', $contactGroupId)->update(['contact_count' => $count]);
                    }
                }
            }
        }

        // Delete contact
        if (isset($_POST['delete_contact'])) {
            $contactId = (int)$_POST['contact_id'];
            $contact = Capsule::table('mod_sms_contacts')->where('id', $contactId)->where('client_id', 0)->first();
            if ($contact) {
                Capsule::table('mod_sms_contacts')->where('id', $contactId)->delete();
                $message = 'Contact deleted.';
                $messageType = 'success';

                // Update group count
                if ($contact->group_id) {
                    $count = Capsule::table('mod_sms_contacts')->where('group_id', $contact->group_id)->count();
                    Capsule::table('mod_sms_contact_groups')->where('id', $contact->group_id)->update(['contact_count' => $count]);
                }
            }
        }

        // Bulk import
        if (isset($_POST['import_contacts']) && isset($_FILES['csv_file'])) {
            $importGroupId = (int)($_POST['import_group_id'] ?? 0);
            $file = $_FILES['csv_file'];

            if ($file['error'] === UPLOAD_ERR_OK) {
                $handle = fopen($file['tmp_name'], 'r');
                $imported = 0;
                $skipped = 0;
                $row = 0;

                while (($data = fgetcsv($handle)) !== false) {
                    $row++;
                    if ($row === 1 && (stripos($data[0], 'phone') !== false || stripos($data[0], 'number') !== false)) {
                        continue; // Skip header
                    }

                    $phone = preg_replace('/[^0-9+]/', '', $data[0] ?? '');
                    if (empty($phone)) {
                        $skipped++;
                        continue;
                    }

                    $exists = Capsule::table('mod_sms_contacts')
                        ->where('client_id', 0)
                        ->where('phone', $phone)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    Capsule::table('mod_sms_contacts')->insert([
                        'client_id' => 0,
                        'group_id' => $importGroupId ?: null,
                        'phone' => $phone,
                        'first_name' => trim($data[1] ?? ''),
                        'last_name' => trim($data[2] ?? ''),
                        'email' => trim($data[3] ?? ''),
                        'status' => 'subscribed',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $imported++;
                }
                fclose($handle);

                // Update group count
                if ($importGroupId) {
                    $count = Capsule::table('mod_sms_contacts')->where('group_id', $importGroupId)->count();
                    Capsule::table('mod_sms_contact_groups')->where('id', $importGroupId)->update(['contact_count' => $count]);
                }

                $message = "Imported {$imported} contacts. Skipped {$skipped} (duplicates or invalid).";
                $messageType = 'success';
            } else {
                $message = 'Failed to upload file.';
                $messageType = 'danger';
            }
        }
    }

    // Get admin groups
    $groups = Capsule::table('mod_sms_contact_groups')
        ->where('client_id', 0)
        ->orderBy('name')
        ->get();

    // Get contacts
    $query = Capsule::table('mod_sms_contacts')
        ->where('client_id', 0);

    if ($groupId > 0) {
        $query->where('group_id', $groupId);
    }

    $contacts = $query->orderBy('created_at', 'desc')->limit(500)->get();
    $totalContacts = Capsule::table('mod_sms_contacts')->where('client_id', 0)->count();

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<div class="row">';
    echo '<div class="col-sm-4"><h3 class="panel-title"><i class="fa fa-address-book"></i> Contacts (Admin)</h3></div>';
    echo '<div class="col-sm-8 text-right">';
    echo '<button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addContactModal"><i class="fa fa-plus"></i> Add Contact</button> ';
    echo '<button class="btn btn-default btn-sm" data-toggle="modal" data-target="#importModal"><i class="fa fa-upload"></i> Import CSV</button>';
    echo '</div></div></div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    // Filter by group
    echo '<form method="get" class="form-inline" style="margin-bottom: 15px;">';
    echo '<input type="hidden" name="module" value="sms_suite">';
    echo '<input type="hidden" name="action" value="contacts">';
    echo '<div class="form-group">';
    echo '<label>Filter by Group: </label> ';
    echo '<select name="group_id" class="form-control" onchange="this.form.submit()">';
    echo '<option value="">All Groups (' . $totalContacts . ' contacts)</option>';
    foreach ($groups as $group) {
        $sel = ($groupId == $group->id) ? 'selected' : '';
        echo '<option value="' . $group->id . '" ' . $sel . '>' . htmlspecialchars($group->name) . ' (' . $group->contact_count . ')</option>';
    }
    echo '</select>';
    echo '</div></form>';

    if (count($contacts) > 0) {
        echo '<table class="table table-striped table-condensed">';
        echo '<thead><tr><th>Phone</th><th>Name</th><th>Email</th><th>Group</th><th>Status</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($contacts as $contact) {
            $groupName = '-';
            if ($contact->group_id) {
                foreach ($groups as $g) {
                    if ($g->id == $contact->group_id) {
                        $groupName = $g->name;
                        break;
                    }
                }
            }
            echo '<tr>';
            echo '<td>' . htmlspecialchars($contact->phone) . '</td>';
            echo '<td>' . htmlspecialchars(trim($contact->first_name . ' ' . $contact->last_name) ?: '-') . '</td>';
            echo '<td>' . htmlspecialchars($contact->email ?: '-') . '</td>';
            echo '<td>' . htmlspecialchars($groupName) . '</td>';
            echo '<td><span class="label label-' . ($contact->status === 'subscribed' ? 'success' : 'default') . '">' . ucfirst($contact->status) . '</span></td>';
            echo '<td>';
            echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this contact?\');">';
            echo '<input type="hidden" name="contact_id" value="' . $contact->id . '">';
            echo '<button type="submit" name="delete_contact" value="1" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        if (count($contacts) >= 500) {
            echo '<p class="text-muted">Showing first 500 contacts. Use group filter to narrow results.</p>';
        }
    } else {
        echo '<div class="text-center text-muted" style="padding: 40px;">';
        echo '<i class="fa fa-address-book fa-3x"></i>';
        echo '<p style="margin-top: 15px;">No contacts yet. Add contacts manually or import from CSV.</p>';
        echo '</div>';
    }

    echo '</div></div>';

    // Add Contact Modal
    echo '
    <div class="modal fade" id="addContactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-user-plus"></i> Add Contact</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" required placeholder="+254712345678">
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" class="form-control">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Group</label>
                            <select name="group_id" class="form-control">
                                <option value="">No Group</option>';
    foreach ($groups as $group) {
        echo '<option value="' . $group->id . '">' . htmlspecialchars($group->name) . '</option>';
    }
    echo '
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_contact" value="1" class="btn btn-success">Add Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    // Import Modal
    echo '
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-upload"></i> Import Contacts from CSV</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>CSV File <span class="text-danger">*</span></label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                            <small class="help-block">Format: phone, first_name, last_name, email (one per line)</small>
                        </div>
                        <div class="form-group">
                            <label>Import to Group</label>
                            <select name="import_group_id" class="form-control">
                                <option value="">No Group</option>';
    foreach ($groups as $group) {
        echo '<option value="' . $group->id . '">' . htmlspecialchars($group->name) . '</option>';
    }
    echo '
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="import_contacts" value="1" class="btn btn-primary">Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';
}

/**
 * Client Rates admin page
 * Manages per-client rate overrides (mod_sms_client_rates)
 */
function sms_suite_admin_client_rates($vars, $lang)
{
    $modulelink = $vars['modulelink'];
    $message = '';
    $messageType = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_rate'])) {
            $clientId = (int)($_POST['client_id'] ?? 0);
            $countryCode = trim($_POST['country_code'] ?? '');
            $networkPrefix = trim($_POST['network_prefix'] ?? '');
            $smsRate = (float)($_POST['sms_rate'] ?? 0);
            $whatsappRate = (float)($_POST['whatsapp_rate'] ?? 0);
            $priority = (int)($_POST['priority'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;

            if ($clientId > 0 && $smsRate >= 0) {
                try {
                    Capsule::table('mod_sms_client_rates')->insert([
                        'client_id' => $clientId,
                        'country_code' => $countryCode ?: null,
                        'network_prefix' => $networkPrefix ?: null,
                        'sms_rate' => $smsRate,
                        'whatsapp_rate' => $whatsappRate,
                        'priority' => $priority,
                        'status' => $status,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $message = 'Client rate added successfully.';
                    $messageType = 'success';
                } catch (\Exception $e) {
                    $message = 'Error adding rate: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            } else {
                $message = 'Client and SMS Rate are required.';
                $messageType = 'danger';
            }
        } elseif (isset($_POST['update_rates'])) {
            $updated = 0;
            if (!empty($_POST['rates']) && is_array($_POST['rates'])) {
                foreach ($_POST['rates'] as $rateId => $rateData) {
                    Capsule::table('mod_sms_client_rates')
                        ->where('id', (int)$rateId)
                        ->update([
                            'sms_rate' => (float)($rateData['sms'] ?? 0),
                            'whatsapp_rate' => (float)($rateData['whatsapp'] ?? 0),
                            'priority' => (int)($rateData['priority'] ?? 0),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    $updated++;
                }
            }
            $message = $updated . ' rate(s) updated successfully.';
            $messageType = 'success';
        } elseif (isset($_POST['delete_rate'])) {
            $rateId = (int)($_POST['rate_id'] ?? 0);
            if ($rateId > 0) {
                Capsule::table('mod_sms_client_rates')->where('id', $rateId)->delete();
                $message = 'Rate deleted.';
                $messageType = 'success';
            }
        }
    }

    // Filter by client
    $filterClientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

    // Build query
    $query = Capsule::table('mod_sms_client_rates as cr')
        ->leftJoin('tblclients as c', 'cr.client_id', '=', 'c.id')
        ->select(['cr.*', Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"), 'c.companyname']);

    if ($filterClientId > 0) {
        $query->where('cr.client_id', $filterClientId);
    }

    $rates = $query->orderBy('cr.client_id')
        ->orderBy('cr.priority', 'desc')
        ->orderBy('cr.country_code')
        ->get();

    // Get clients for dropdowns
    $clients = Capsule::table('tblclients')
        ->select(['id', 'firstname', 'lastname', 'companyname'])
        ->where('status', 'Active')
        ->orderBy('firstname')
        ->orderBy('lastname')
        ->get();

    // Page header
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title"><i class="fa fa-user-circle"></i> Client Rates</h3>';
    echo '</div>';
    echo '<div class="panel-body">';

    if ($message) {
        echo '<div class="alert alert-' . $messageType . '">' . htmlspecialchars($message) . '</div>';
    }

    // Help text
    echo '<div class="alert alert-info">
        <strong>Client Rate Hierarchy (highest to lowest priority):</strong><br>
        1. <strong>Client + Country + Network</strong> &mdash; Most specific: rate for a specific client, country, and network prefix<br>
        2. <strong>Client + Country</strong> &mdash; Country-level override for a specific client<br>
        3. <strong>Client Flat Rate</strong> &mdash; Blanket rate for all messages from this client (no country/network set)<br>
        <small class="text-muted">If no client rate matches, the system falls back to gateway country rates, destination rates, then default rates.</small>
    </div>';

    // Filter bar
    echo '<form method="get" class="form-inline" style="margin-bottom: 15px;">';
    // Preserve modulelink params
    echo '<input type="hidden" name="module" value="sms_suite">';
    echo '<input type="hidden" name="action" value="client_rates">';
    echo '<label>Filter by Client: </label> ';
    echo '<select name="client_id" class="form-control" style="width: 250px;" onchange="this.form.submit()">';
    echo '<option value="0">-- Show All Clients --</option>';
    foreach ($clients as $client) {
        $clientLabel = htmlspecialchars($client->firstname . ' ' . $client->lastname);
        if ($client->companyname) {
            $clientLabel .= ' (' . htmlspecialchars($client->companyname) . ')';
        }
        $selected = ($filterClientId == $client->id) ? ' selected' : '';
        echo '<option value="' . $client->id . '"' . $selected . '>' . $clientLabel . '</option>';
    }
    echo '</select>';
    echo '</form>';

    // Add rate form
    echo '<div class="panel panel-success" style="margin-top: 10px;">';
    echo '<div class="panel-heading"><strong>Add New Client Rate</strong></div>';
    echo '<div class="panel-body">';
    echo '<form method="post" class="form-inline">';
    echo '<input type="hidden" name="add_rate" value="1">';

    echo '<select name="client_id" class="form-control" required style="width: 200px;">';
    echo '<option value="">Select Client...</option>';
    foreach ($clients as $client) {
        $clientLabel = htmlspecialchars($client->firstname . ' ' . $client->lastname);
        if ($client->companyname) {
            $clientLabel .= ' (' . htmlspecialchars($client->companyname) . ')';
        }
        echo '<option value="' . $client->id . '">' . $clientLabel . '</option>';
    }
    echo '</select> ';

    echo '<input type="text" name="country_code" class="form-control" placeholder="Country Code" style="width: 120px;" title="e.g. 1, 44, 234 (leave blank for flat rate)"> ';
    echo '<input type="text" name="network_prefix" class="form-control" placeholder="Network Prefix" style="width: 120px;" title="e.g. 2348, 4479 (leave blank for country-level)"> ';
    echo '<input type="number" name="sms_rate" class="form-control" placeholder="SMS Rate" step="0.000001" min="0" style="width: 110px;" required> ';
    echo '<input type="number" name="whatsapp_rate" class="form-control" placeholder="WA Rate" step="0.000001" min="0" style="width: 110px;"> ';
    echo '<input type="number" name="priority" class="form-control" placeholder="Priority" value="0" min="0" style="width: 90px;" title="Higher priority = checked first"> ';
    echo '<label style="margin-left: 5px;"><input type="checkbox" name="status" value="1" checked> Active</label> ';
    echo '<button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add Rate</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Rates table
    if (count($rates) > 0) {
        echo '<form method="post">';
        echo '<input type="hidden" name="update_rates" value="1">';
        echo '<table class="table table-striped table-bordered">';
        echo '<thead><tr>';
        echo '<th>Client</th>';
        echo '<th>Country Code</th>';
        echo '<th>Network Prefix</th>';
        echo '<th>SMS Rate</th>';
        echo '<th>WhatsApp Rate</th>';
        echo '<th>Priority</th>';
        echo '<th>Status</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($rates as $rate) {
            $clientDisplay = htmlspecialchars($rate->client_name ?: 'Unknown');
            if ($rate->companyname) {
                $clientDisplay .= ' <small class="text-muted">(' . htmlspecialchars($rate->companyname) . ')</small>';
            }

            echo '<tr>';
            echo '<td>' . $clientDisplay . ' <small class="text-muted">#' . $rate->client_id . '</small></td>';
            echo '<td>' . ($rate->country_code ? htmlspecialchars($rate->country_code) : '<span class="text-muted">Any</span>') . '</td>';
            echo '<td>' . ($rate->network_prefix ? htmlspecialchars($rate->network_prefix) : '<span class="text-muted">Any</span>') . '</td>';
            echo '<td><input type="number" name="rates[' . $rate->id . '][sms]" class="form-control input-sm" value="' . $rate->sms_rate . '" step="0.000001" min="0" style="width: 110px;"></td>';
            echo '<td><input type="number" name="rates[' . $rate->id . '][whatsapp]" class="form-control input-sm" value="' . $rate->whatsapp_rate . '" step="0.000001" min="0" style="width: 110px;"></td>';
            echo '<td><input type="number" name="rates[' . $rate->id . '][priority]" class="form-control input-sm" value="' . $rate->priority . '" min="0" style="width: 70px;"></td>';
            echo '<td>' . ($rate->status ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>') . '</td>';
            echo '<td><button type="button" class="btn btn-xs btn-danger" onclick="deleteClientRate(' . $rate->id . ')"><i class="fa fa-trash"></i></button></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Rates</button>';
        echo '</form>';
    } else {
        if ($filterClientId > 0) {
            echo '<p class="text-muted">No rates configured for this client.</p>';
        } else {
            echo '<p class="text-muted">No client-specific rates configured. Add a rate above to set per-client pricing.</p>';
        }
    }

    echo '</div>'; // panel-body
    echo '</div>'; // panel
    echo '</div>'; // tab-content wrapper

    // JavaScript for delete
    echo '<script>
    function deleteClientRate(id) {
        if (confirm("Delete this client rate?")) {
            var form = document.createElement("form");
            form.method = "POST";
            form.innerHTML = \'<input type="hidden" name="delete_rate" value="1"><input type="hidden" name="rate_id" value="\' + id + \'">\';
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>';
}
