<?php
/**
 * SMS Suite - WHMCS Addon Module
 *
 * A comprehensive SMS and WhatsApp messaging addon for WHMCS
 *
 * @package    SMSSuite
 * @author     SMS Suite
 * @copyright  2024
 * @version    1.0.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

// Module constants
define('SMS_SUITE_VERSION', '1.0.0');
define('SMS_SUITE_TABLE_PREFIX', 'mod_sms_');

/**
 * Module configuration
 */
function sms_suite_config()
{
    return [
        'name' => 'Messaging Suite',
        'description' => 'Comprehensive messaging platform for SMS, WhatsApp, and more — with campaigns, contacts, billing, and API access.',
        'version' => SMS_SUITE_VERSION,
        'author' => 'Messaging Suite',
        'language' => 'english',
        'fields' => [
            'default_sender_id' => [
                'FriendlyName' => 'Default Sender ID',
                'Type' => 'text',
                'Size' => '20',
                'Default' => '',
                'Description' => 'Default sender ID for outgoing messages',
            ],
            'default_billing_mode' => [
                'FriendlyName' => 'Default Billing Mode',
                'Type' => 'dropdown',
                'Options' => 'per_message,per_segment,wallet,plan',
                'Default' => 'per_segment',
                'Description' => 'Default billing mode for new clients',
            ],
            'api_rate_limit' => [
                'FriendlyName' => 'API Rate Limit',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '100',
                'Description' => 'Default API requests per minute per key',
            ],
            'log_retention_days' => [
                'FriendlyName' => 'Log Retention (Days)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '90',
                'Description' => 'Number of days to retain message logs',
            ],
            'webhook_secret' => [
                'FriendlyName' => 'Webhook Secret',
                'Type' => 'password',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Secret key for webhook authentication',
            ],
            'enable_client_api' => [
                'FriendlyName' => 'Enable Client API',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Allow clients to create API keys',
            ],
            'purge_data_on_deactivate' => [
                'FriendlyName' => 'Purge Data on Deactivate',
                'Type' => 'yesno',
                'Default' => 'no',
                'Description' => 'WARNING: Delete all module data when deactivating',
            ],
            'meta_app_id' => [
                'FriendlyName' => 'Meta App ID',
                'Type' => 'text',
                'Size' => '30',
                'Default' => '598024678497863',
                'Description' => 'Facebook App ID for Embedded Signup',
            ],
            'meta_app_secret' => [
                'FriendlyName' => 'Meta App Secret',
                'Type' => 'password',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Facebook App Secret (from Meta Developer Dashboard)',
            ],
            'meta_config_id' => [
                'FriendlyName' => 'Meta Config ID',
                'Type' => 'text',
                'Size' => '30',
                'Default' => '1265471198823134',
                'Description' => 'Embedded Signup Configuration ID',
            ],
            'ai_provider' => [
                'FriendlyName' => 'AI Chatbot Provider',
                'Type' => 'dropdown',
                'Options' => 'none,claude,openai,gemini,deepseek,mistral,groq,cohere,xai',
                'Default' => 'none',
                'Description' => 'Default AI provider for chatbot auto-replies',
            ],
            'ai_api_key' => [
                'FriendlyName' => 'AI API Key',
                'Type' => 'password',
                'Size' => '80',
                'Default' => '',
                'Description' => 'System API key for the selected AI provider. Clients can optionally use their own keys.',
            ],
        ],
    ];
}

/**
 * Retrieve a module setting from tbladdonmodules
 */
function sms_suite_get_module_setting($key)
{
    return Capsule::table('tbladdonmodules')
        ->where('module', 'sms_suite')
        ->where('setting', $key)
        ->value('value');
}

/**
 * Module activation
 */
function sms_suite_activate()
{
    try {
        // Create all database tables using raw SQL for reliability
        $errors = sms_suite_create_tables_sql();

        if (!empty($errors)) {
            logActivity('SMS Suite activation - table creation had warnings: ' . implode('; ', $errors));
        }

        // Insert default data
        sms_suite_insert_defaults();

        logActivity('SMS Suite activated successfully');

        return [
            'status' => 'success',
            'description' => 'SMS Suite has been activated successfully. Please configure your gateways.',
        ];
    } catch (Exception $e) {
        logActivity('SMS Suite activation failed: ' . $e->getMessage());
        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module deactivation
 */
function sms_suite_deactivate()
{
    try {
        // Check if data purge is enabled
        $purgeData = false;

        $setting = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', 'purge_data_on_deactivate')
            ->first();

        if ($setting && $setting->value === 'on') {
            $purgeData = true;
        }

        if ($purgeData) {
            sms_suite_drop_tables_sql();
            logActivity('SMS Suite deactivated with data purge');
            return [
                'status' => 'success',
                'description' => 'SMS Suite has been deactivated and all data has been purged.',
            ];
        }

        logActivity('SMS Suite deactivated - data preserved');
        return [
            'status' => 'success',
            'description' => 'SMS Suite has been deactivated. Data has been preserved.',
        ];
    } catch (Exception $e) {
        logActivity('SMS Suite deactivation failed: ' . $e->getMessage());
        return [
            'status' => 'error',
            'description' => 'Deactivation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Drop all module tables using raw SQL
 */
function sms_suite_drop_tables_sql()
{
    $pdo = Capsule::connection()->getPdo();

    $tables = [
        // Billing and Credit tables
        'mod_sms_credit_usage',
        'mod_sms_credit_allocations',
        'mod_sms_network_prefixes',
        'mod_sms_sender_id_billing',
        'mod_sms_credit_transactions',
        'mod_sms_credit_balance',
        'mod_sms_client_sender_ids',
        'mod_sms_sender_id_requests',
        'mod_sms_credit_purchases',
        'mod_sms_credit_packages',
        'mod_sms_sender_id_pool',
        'mod_sms_client_rates',
        'mod_sms_destination_rates',
        // Verification and Notification tables
        'mod_sms_verification_logs',
        'mod_sms_verification_templates',
        'mod_sms_order_verification',
        'mod_sms_client_verification',
        'mod_sms_verification_tokens',
        'mod_sms_admin_notifications',
        'mod_sms_notification_templates',
        // Advanced campaign tables
        'mod_sms_scheduled',
        'mod_sms_recurring_log',
        'mod_sms_contact_tags',
        'mod_sms_tags',
        'mod_sms_segment_conditions',
        'mod_sms_segments',
        'mod_sms_link_clicks',
        'mod_sms_tracking_links',
        'mod_sms_drip_subscribers',
        'mod_sms_drip_steps',
        'mod_sms_drip_campaigns',
        'mod_sms_campaign_ab_tests',
        'mod_sms_auto_replies',
        'mod_sms_chatbox_messages',
        'mod_sms_chatbox',
        'mod_sms_messenger_sessions',
        'mod_sms_whatsapp_templates',
        // Original tables
        'mod_sms_rate_limits',
        'mod_sms_pending_topups',
        'mod_sms_automation_logs',
        'mod_sms_automations',
        'mod_sms_cron_status',
        'mod_sms_countries',
        'mod_sms_automation_triggers',
        'mod_sms_optouts',
        'mod_sms_blacklist',
        'mod_sms_plan_credits',
        'mod_sms_wallet_transactions',
        'mod_sms_wallet',
        'mod_sms_api_audit',
        'mod_sms_api_rate_limits',
        'mod_sms_api_keys',
        'mod_sms_templates',
        'mod_sms_webhooks_inbox',
        'mod_sms_messages',
        'mod_sms_campaign_recipients',
        'mod_sms_campaign_lists',
        'mod_sms_campaigns',
        'mod_sms_contacts',
        'mod_sms_contact_group_fields',
        'mod_sms_contact_groups',
        'mod_sms_sender_id_plans',
        'mod_sms_sender_ids',
        'mod_sms_gateway_countries',
        'mod_sms_gateways',
        'mod_sms_settings',
    ];

    // Disable foreign key checks for clean drop
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        } catch (Exception $e) {
            // Continue even if one table fails
            logActivity("SMS Suite: Warning dropping table {$table}: " . $e->getMessage());
        }
    }

    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    logActivity('SMS Suite: All module tables dropped');
}

/**
 * Module upgrade handler
 */
function sms_suite_upgrade($vars)
{
    $currentVersion = $vars['version'];

    try {
        logActivity("SMS Suite: Upgrading from version {$currentVersion}");

        // Always ensure tables exist and have correct structure
        $errors = sms_suite_create_tables_sql();

        if (!empty($errors)) {
            logActivity('SMS Suite upgrade warnings: ' . implode('; ', $errors));
        }

        // Add performance indexes
        sms_suite_add_performance_indexes();

        logActivity('SMS Suite: Upgrade completed successfully');

    } catch (Exception $e) {
        logActivity('SMS Suite upgrade failed: ' . $e->getMessage());
    }
}

/**
 * Add performance indexes to improve query performance
 */
function sms_suite_add_performance_indexes()
{
    $pdo = Capsule::connection()->getPdo();

    // Helper function to check if index exists
    $indexExists = function ($table, $indexName) use ($pdo) {
        try {
            $result = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'")->fetch();
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    };

    // Add index helper
    $addIndex = function ($table, $indexName, $columns) use ($pdo, $indexExists) {
        if (!$indexExists($table, $indexName)) {
            try {
                $cols = is_array($columns) ? implode(', ', $columns) : $columns;
                $pdo->exec("CREATE INDEX `{$indexName}` ON `{$table}` ({$cols})");
            } catch (Exception $e) {
                // Index may already exist or table doesn't exist
            }
        }
    };

    // Messages table - additional indexes for common queries
    $addIndex('mod_sms_messages', 'idx_messages_campaign_to', ['campaign_id', 'to_number']);
    $addIndex('mod_sms_messages', 'idx_messages_client_status', ['client_id', 'status']);
    $addIndex('mod_sms_messages', 'idx_messages_gateway', ['gateway_id', 'created_at']);

    // Contacts table - indexes for filtering
    $addIndex('mod_sms_contacts', 'idx_contacts_client_group', ['client_id', 'group_id']);
    $addIndex('mod_sms_contacts', 'idx_contacts_phone', ['phone']);
    $addIndex('mod_sms_contacts', 'idx_contacts_status', ['status']);

    // Campaigns table - indexes for status queries
    $addIndex('mod_sms_campaigns', 'idx_campaigns_client_status', ['client_id', 'status']);
    $addIndex('mod_sms_campaigns', 'idx_campaigns_schedule', ['status', 'schedule_time']);

    // Wallet transactions - for history queries
    $addIndex('mod_sms_wallet_transactions', 'idx_wallet_trans_client_date', ['client_id', 'created_at']);

    // API rate limits - for cleanup queries
    $addIndex('mod_sms_rate_limits', 'idx_rate_limits_window', ['window']);

    // Scheduled messages - for processing
    $addIndex('mod_sms_scheduled', 'idx_scheduled_status_time', ['status', 'scheduled_at']);

    // Drip subscribers - for processing
    $addIndex('mod_sms_drip_subscribers', 'idx_drip_sub_status', ['status', 'next_send_at']);
}

/**
 * Admin area output
 */
function sms_suite_output($vars)
{
    $modulelink = $vars['modulelink'];
    $action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

    // Load language file
    $lang = sms_suite_load_language();

    // Basic CSRF token
    $csrfToken = generate_token('link');

    // Include admin controller
    $adminController = __DIR__ . '/admin/controller.php';
    if (file_exists($adminController)) {
        require_once $adminController;
        if (function_exists('sms_suite_admin_dispatch')) {
            sms_suite_admin_dispatch($vars, $action, $lang);
            return;
        }
    }

    // Fallback basic output
    echo '<div class="alert alert-info">';
    echo '<h4>' . $lang['welcome_title'] . '</h4>';
    echo '<p>' . $lang['welcome_message'] . '</p>';
    echo '</div>';

    echo '<div class="row">';
    echo '<div class="col-md-4">';
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['quick_links'] . '</h3></div>';
    echo '<div class="panel-body">';
    echo '<ul class="list-unstyled">';
    echo '<li><a href="' . $modulelink . '&action=gateways">' . $lang['menu_gateways'] . '</a></li>';
    echo '<li><a href="' . $modulelink . '&action=sender_ids">' . $lang['menu_sender_ids'] . '</a></li>';
    echo '<li><a href="' . $modulelink . '&action=messages">' . $lang['menu_messages'] . '</a></li>';
    echo '<li><a href="' . $modulelink . '&action=campaigns">' . $lang['menu_campaigns'] . '</a></li>';
    echo '<li><a href="' . $modulelink . '&action=reports">' . $lang['menu_reports'] . '</a></li>';
    echo '<li><a href="' . $modulelink . '&action=settings">' . $lang['menu_settings'] . '</a></li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-8">';
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">' . $lang['statistics'] . '</h3></div>';
    echo '<div class="panel-body">';

    // Get basic stats
    $stats = sms_suite_get_stats();

    echo '<div class="row">';
    echo '<div class="col-md-3 text-center"><h3>' . $stats['total_messages'] . '</h3><p>' . $lang['total_messages'] . '</p></div>';
    echo '<div class="col-md-3 text-center"><h3>' . $stats['total_gateways'] . '</h3><p>' . $lang['total_gateways'] . '</p></div>';
    echo '<div class="col-md-3 text-center"><h3>' . $stats['total_campaigns'] . '</h3><p>' . $lang['total_campaigns'] . '</p></div>';
    echo '<div class="col-md-3 text-center"><h3>' . $stats['total_clients'] . '</h3><p>' . $lang['total_clients'] . '</p></div>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Client area output
 */
function sms_suite_clientarea($vars)
{
    $modulelink = $vars['modulelink'];
    $action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

    // Get client ID
    $clientId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;

    if (!$clientId) {
        return [
            'pagetitle' => 'Messaging Suite',
            'breadcrumb' => [$modulelink => 'Messaging Suite'],
            'templatefile' => 'templates/client/error',
            'vars' => [
                'error' => 'You must be logged in to access this page.',
            ],
        ];
    }

    // Load language
    $lang = sms_suite_load_language();

    // Nexus-theme-matching CSS for client templates
    $smsCss = '<style>
/* SMS Suite — Nexus Theme Integration */
/* ===== CSS Variables ===== */
[class*="sms-suite"] {
  --sms-primary: #667eea;
  --sms-primary-end: #764ba2;
  --sms-success: #00c853;
  --sms-info: #155dfc;
  --sms-warning: #ff9800;
  --sms-danger: #ef4444;
  --sms-dark: #1e293b;
  --sms-muted: #64748b;
  --sms-light: #f8fafc;
  --sms-border: #e2e8f0;
  --sms-radius: 12px;
  --sms-radius-sm: 8px;
  --sms-shadow: 0 4px 20px rgba(0,0,0,.08);
  --sms-shadow-sm: 0 2px 8px rgba(0,0,0,.06);
}

/* ===== Navigation ===== */
.sms-nav{display:flex;flex-wrap:wrap;gap:6px;padding:0;margin:0 0 24px;list-style:none}
.sms-nav li{list-style:none}
.sms-nav li a{display:block;padding:8px 18px;border-radius:20px;color:var(--sms-muted);text-decoration:none;font-size:.875rem;font-weight:500;background:#f1f5f9;border:1px solid transparent;transition:all .2s ease}
.sms-nav li a:hover{background:#e2e8f0;color:var(--sms-dark);text-decoration:none}
.sms-nav li.active a{background:linear-gradient(135deg,var(--sms-primary),var(--sms-primary-end));color:#fff;border-color:transparent;box-shadow:0 2px 8px rgba(102,126,234,.35)}

/* ===== Cards ===== */
[class*="sms-suite"] .card{background:#fff;border:none;border-radius:var(--sms-radius);margin-bottom:24px;box-shadow:var(--sms-shadow);overflow:hidden}
[class*="sms-suite"] .card-header{padding:16px 20px;background:#fff;border-bottom:1px solid var(--sms-border)}
[class*="sms-suite"] .card-body{padding:20px}
[class*="sms-suite"] .card-title{margin:0;font-size:.95rem;font-weight:600;color:var(--sms-dark)}
[class*="sms-suite"] .card-footer{padding:16px 20px;background:var(--sms-light);border-top:1px solid var(--sms-border)}
[class*="sms-suite"] .card-header.bg-primary{background:linear-gradient(135deg,var(--sms-primary),var(--sms-primary-end))!important;border-bottom:none}
[class*="sms-suite"] .card-header.bg-primary .card-title{color:#fff}
[class*="sms-suite"] .card-header.bg-success{background:linear-gradient(135deg,#00c853,#00e676)!important;border-bottom:none}
[class*="sms-suite"] .card-header.bg-success .card-title{color:#fff}
[class*="sms-suite"] .card-header.bg-info{background:linear-gradient(135deg,#155dfc,#4f8cff)!important;border-bottom:none}
[class*="sms-suite"] .card-header.bg-info .card-title{color:#fff}
[class*="sms-suite"] .card-header.bg-warning{background:linear-gradient(135deg,#ff9800,#ffb74d)!important;border-bottom:none}
[class*="sms-suite"] .card-header.bg-warning .card-title{color:#fff}
[class*="sms-suite"] .card-header.bg-danger{background:linear-gradient(135deg,#ef4444,#f87171)!important;border-bottom:none}
[class*="sms-suite"] .card-header.bg-danger .card-title{color:#fff}

/* ===== Badges ===== */
[class*="sms-suite"] .badge{display:inline-block;padding:5px 12px;font-size:.75rem;font-weight:600;line-height:1.2;color:#fff;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:20px;letter-spacing:.02em}
[class*="sms-suite"] .badge-secondary{background:#94a3b8;color:#fff}
[class*="sms-suite"] .badge-primary{background:linear-gradient(135deg,var(--sms-primary),var(--sms-primary-end))}
[class*="sms-suite"] .badge-success{background:var(--sms-success)}
[class*="sms-suite"] .badge-info{background:var(--sms-info)}
[class*="sms-suite"] .badge-warning{background:var(--sms-warning);color:#fff}
[class*="sms-suite"] .badge-danger{background:var(--sms-danger)}

/* ===== Buttons ===== */
[class*="sms-suite"] .btn{border-radius:var(--sms-radius-sm);font-weight:500;transition:all .2s ease;border:none}
[class*="sms-suite"] .btn:focus{box-shadow:0 0 0 3px rgba(102,126,234,.25)}
[class*="sms-suite"] .btn-primary{background:linear-gradient(135deg,var(--sms-primary),var(--sms-primary-end))!important;color:#fff!important;border:none!important}
[class*="sms-suite"] .btn-primary:hover{background:linear-gradient(135deg,#5a72d4,#6a4196)!important;color:#fff!important;box-shadow:0 4px 12px rgba(102,126,234,.4)}
[class*="sms-suite"] .btn-success{background:linear-gradient(135deg,#00c853,#00e676);color:#fff;border:none}
[class*="sms-suite"] .btn-success:hover{box-shadow:0 4px 12px rgba(0,200,83,.35)}
[class*="sms-suite"] .btn-info{background:linear-gradient(135deg,#155dfc,#4f8cff);color:#fff;border:none}
[class*="sms-suite"] .btn-info:hover{box-shadow:0 4px 12px rgba(21,93,252,.35)}
[class*="sms-suite"] .btn-warning{background:linear-gradient(135deg,#ff9800,#ffb74d);color:#fff;border:none}
[class*="sms-suite"] .btn-outline-secondary{color:var(--sms-dark);background:#f1f5f9;border:1px solid var(--sms-border)}
[class*="sms-suite"] .btn-outline-secondary:hover{background:#e2e8f0;color:var(--sms-dark)}
[class*="sms-suite"] .btn-danger{background:linear-gradient(135deg,#ef4444,#f87171);color:#fff;border:none}
[class*="sms-suite"] .btn-block{display:block;width:100%;margin-bottom:8px}
[class*="sms-suite"] .btn-lg{padding:12px 28px;font-size:1rem}

/* ===== Form Controls ===== */
[class*="sms-suite"] .form-group{margin-bottom:16px}
[class*="sms-suite"] .form-group>label{display:block;margin-bottom:6px;font-weight:600;font-size:.875rem;color:var(--sms-dark)}
[class*="sms-suite"] .form-control{display:block;width:100%;border-radius:var(--sms-radius-sm);border:2px solid var(--sms-border);padding:10px 15px;font-size:.9rem;color:var(--sms-dark);background:#fff;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;height:auto;line-height:1.5}
[class*="sms-suite"] .form-control:focus{border-color:var(--sms-primary);box-shadow:0 0 0 3px rgba(102,126,234,.15);outline:none}
[class*="sms-suite"] select.form-control{appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns=%27http%3A//www.w3.org/2000/svg%27 viewBox=%270 0 16 16%27%3E%3Cpath fill=%27none%27 stroke=%27%2364748b%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%272%27 d=%27m2 5 6 6 6-6%27/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;background-size:14px 10px;padding-right:36px;cursor:pointer}
[class*="sms-suite"] textarea.form-control{min-height:100px;resize:vertical}
[class*="sms-suite"] .input-group{display:flex;align-items:stretch}
[class*="sms-suite"] .input-group .form-control{flex:1 1 auto;width:1%}
[class*="sms-suite"] .input-group-addon,.input-group-addon{background:var(--sms-light);border:2px solid var(--sms-border);border-radius:var(--sms-radius-sm);padding:10px 15px;color:var(--sms-muted);display:flex;align-items:center}
[class*="sms-suite"] .form-text{display:block;margin-top:6px;color:var(--sms-muted);font-size:.8rem}
.form-control-static{min-height:calc(1.5em + .75rem + 2px);padding-top:calc(.375rem + 1px);margin-bottom:0}

/* ===== Well ===== */
.well{padding:16px;margin-bottom:16px;background:var(--sms-light);border:1px solid var(--sms-border);border-radius:var(--sms-radius-sm)}
.well-sm{padding:12px}

/* ===== Alerts ===== */
[class*="sms-suite"] .alert{border:none;border-radius:var(--sms-radius);padding:16px 20px;font-size:.9rem}
[class*="sms-suite"] .alert-success{background:linear-gradient(135deg,rgba(0,200,83,.1),rgba(0,230,118,.1));color:#1b5e20;border-left:4px solid var(--sms-success)}
[class*="sms-suite"] .alert-danger{background:linear-gradient(135deg,rgba(239,68,68,.1),rgba(248,113,113,.1));color:#b71c1c;border-left:4px solid var(--sms-danger)}
[class*="sms-suite"] .alert-warning{background:linear-gradient(135deg,rgba(255,152,0,.1),rgba(255,183,77,.1));color:#e65100;border-left:4px solid var(--sms-warning)}
[class*="sms-suite"] .alert-info{background:linear-gradient(135deg,rgba(21,93,252,.08),rgba(79,140,255,.08));color:#1565c0;border-left:4px solid var(--sms-info)}

/* ===== Tables ===== */
[class*="sms-suite"] .table{border-collapse:separate;border-spacing:0}
[class*="sms-suite"] .table thead th{background:var(--sms-light);color:var(--sms-muted);font-weight:600;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid var(--sms-border);padding:12px 16px}
[class*="sms-suite"] .table tbody td{padding:12px 16px;vertical-align:middle;border-bottom:1px solid #f1f5f9;color:var(--sms-dark);font-size:.875rem}
[class*="sms-suite"] .table-striped tbody tr:nth-of-type(odd){background:rgba(248,250,252,.5)}
[class*="sms-suite"] .table tbody tr:hover{background:rgba(102,126,234,.04)}

/* ===== Modals ===== */
[class*="sms-suite"] .modal-content{border:none;border-radius:var(--sms-radius);box-shadow:0 20px 60px rgba(0,0,0,.15)}
[class*="sms-suite"] .modal-header{border-bottom:1px solid var(--sms-border);padding:20px 24px}
[class*="sms-suite"] .modal-body{padding:24px}
[class*="sms-suite"] .modal-footer{border-top:1px solid var(--sms-border);padding:16px 24px}
[class*="sms-suite"] .modal-title{font-weight:600;color:var(--sms-dark)}

/* ===== Progress Bars ===== */
[class*="sms-suite"] .progress{height:8px;border-radius:4px;background:#e2e8f0;overflow:hidden}
[class*="sms-suite"] .progress-bar{border-radius:4px;transition:width .4s ease}
[class*="sms-suite"] .progress-bar-success{background:linear-gradient(90deg,var(--sms-success),#00e676)}
[class*="sms-suite"] .progress-bar-danger{background:linear-gradient(90deg,var(--sms-danger),#f87171)}

/* ===== Pagination ===== */
[class*="sms-suite"] .pagination{display:flex;gap:4px;padding:0;list-style:none;flex-wrap:wrap}
[class*="sms-suite"] .pagination li a,[class*="sms-suite"] .pagination li span{display:block;padding:8px 14px;border-radius:var(--sms-radius-sm);color:var(--sms-muted);text-decoration:none;font-size:.875rem;background:#f8fafc;border:1px solid var(--sms-border);transition:all .2s}
[class*="sms-suite"] .pagination li a:hover{background:var(--sms-primary);color:#fff;border-color:var(--sms-primary)}
[class*="sms-suite"] .pagination li.active a,[class*="sms-suite"] .pagination li.active span{background:linear-gradient(135deg,var(--sms-primary),var(--sms-primary-end));color:#fff;border-color:transparent}
[class*="sms-suite"] .pagination li.disabled span{opacity:.5;cursor:not-allowed}

/* ===== List Group ===== */
[class*="sms-suite"] .list-group-item{border:none;border-bottom:1px solid #f1f5f9;padding:16px 20px;transition:background .15s}
[class*="sms-suite"] .list-group-item:hover{background:#f8fafc}
[class*="sms-suite"] .list-group-item.list-group-item-info{background:rgba(102,126,234,.05);border-left:3px solid var(--sms-primary)}

/* ===== Stat Cards (dashboard) ===== */
.sms-stat-card{background:#fff;border-radius:var(--sms-radius);padding:24px;box-shadow:var(--sms-shadow);text-align:center;transition:transform .2s,box-shadow .2s}
.sms-stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(0,0,0,.12)}
.sms-stat-card .stat-icon{width:48px;height:48px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;font-size:1.25rem}
.sms-stat-card .stat-value{font-size:1.75rem;font-weight:700;color:var(--sms-dark);margin:0}
.sms-stat-card .stat-label{font-size:.8rem;color:var(--sms-muted);margin:4px 0 0;text-transform:uppercase;letter-spacing:.04em;font-weight:500}
.stat-icon.bg-purple{background:rgba(102,126,234,.12);color:var(--sms-primary)}
.stat-icon.bg-green{background:rgba(0,200,83,.12);color:var(--sms-success)}
.stat-icon.bg-blue{background:rgba(21,93,252,.12);color:var(--sms-info)}
.stat-icon.bg-orange{background:rgba(255,152,0,.12);color:var(--sms-warning)}

/* ===== Page Headers ===== */
.sms-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px}
.sms-page-header h2{margin:0;font-size:1.5rem;font-weight:700;color:var(--sms-dark)}

/* ===== Progress bar compat ===== */
[class*="sms-suite"] .progress-bar.bg-success{background:linear-gradient(90deg,var(--sms-success),#00e676)!important}

/* ===== Checkbox/Radio ===== */
[class*="sms-suite"] .checkbox label,[class*="sms-suite"] .radio label{font-weight:400;cursor:pointer}

/* ===== Responsive ===== */
@media(max-width:767px){
  .sms-page-header{flex-direction:column;align-items:flex-start}
  .sms-page-header .text-right{text-align:left!important}
  .sms-nav{gap:4px}
  .sms-nav li a{padding:6px 12px;font-size:.8rem}
  [class*="sms-suite"] .table{font-size:.8rem}
  [class*="sms-suite"] .table thead th,[class*="sms-suite"] .table tbody td{padding:8px 10px}
  .sms-stat-card{padding:16px}
  .sms-stat-card .stat-value{font-size:1.35rem}
  [class*="sms-suite"] .card-body{padding:16px}
}
@media(max-width:575px){
  .sms-nav li a{padding:5px 10px;font-size:.75rem}
  .btn-lg{padding:10px 20px;font-size:.9rem}
}
</style>';

    // Include client controller
    $clientController = __DIR__ . '/client/controller.php';
    if (file_exists($clientController)) {
        require_once $clientController;
        if (function_exists('sms_suite_client_dispatch')) {
            $result = sms_suite_client_dispatch($vars, $action, $clientId, $lang);
            // Ensure WHMCS login/SSL properties are set
            $result['requirelogin'] = true;
            $result['forcessl'] = false;
            // Inject CSS compatibility layer
            $result['vars']['sms_css'] = $smsCss;
            return $result;
        }
    }

    // Fallback basic output
    return [
        'pagetitle' => 'Messaging Suite',
        'breadcrumb' => [$modulelink => 'Messaging Suite'],
        'templatefile' => 'templates/client/dashboard',
        'requirelogin' => true,
        'forcessl' => false,
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'sms_css' => $smsCss,
        ],
    ];
}

/**
 * Create all database tables using raw SQL for reliability
 * Returns array of any errors encountered (empty if all successful)
 */
function sms_suite_create_tables_sql()
{
    $pdo = Capsule::connection()->getPdo();
    $errors = [];

    // Helper function to execute SQL and catch errors
    $execSql = function ($sql, $description) use ($pdo, &$errors) {
        try {
            $result = $pdo->exec($sql);
            if ($result === false) {
                $errorInfo = $pdo->errorInfo();
                $errors[] = "{$description}: " . ($errorInfo[2] ?? 'Unknown PDO error');
                return false;
            }
            return true;
        } catch (Exception $e) {
            $errors[] = "{$description}: " . $e->getMessage();
            return false;
        }
    };

    // Helper to check if table exists
    $tableExists = function ($tableName) use ($pdo) {
        try {
            $result = $pdo->query("SHOW TABLES LIKE '{$tableName}'")->fetch();
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    };

    // Helper to check if column exists
    $columnExists = function ($tableName, $columnName) use ($pdo) {
        try {
            $result = $pdo->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'")->fetch();
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    };

    // 1. Gateways table (CRITICAL - must exist for gateway save to work)
    if (!$tableExists('mod_sms_gateways')) {
        $execSql("
            CREATE TABLE `mod_sms_gateways` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED DEFAULT NULL,
                `name` VARCHAR(100) NOT NULL,
                `type` VARCHAR(50) NOT NULL,
                `channel` VARCHAR(20) DEFAULT 'sms',
                `status` TINYINT(1) DEFAULT 1,
                `credentials` TEXT,
                `settings` TEXT,
                `quota_value` INT DEFAULT 0,
                `quota_unit` VARCHAR(20) DEFAULT 'minute',
                `success_keyword` VARCHAR(100),
                `balance` DECIMAL(16,4),
                `webhook_token` VARCHAR(64),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_type` (`type`),
                INDEX `idx_status` (`status`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_gateways");
    } else {
        // Add client_id column for client-owned gateways
        if (!$columnExists('mod_sms_gateways', 'client_id')) {
            $execSql("ALTER TABLE `mod_sms_gateways` ADD COLUMN `client_id` INT UNSIGNED DEFAULT NULL AFTER `id`", "Add client_id to mod_sms_gateways");
            $execSql("ALTER TABLE `mod_sms_gateways` ADD INDEX `idx_client_id` (`client_id`)", "Add client_id index to mod_sms_gateways");
        }
    }

    // 2. Gateway country pricing
    if (!$tableExists('mod_sms_gateway_countries')) {
        $execSql("
            CREATE TABLE `mod_sms_gateway_countries` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `gateway_id` INT UNSIGNED NOT NULL,
                `country_code` VARCHAR(5) NOT NULL,
                `country_name` VARCHAR(100) NOT NULL,
                `sms_rate` DECIMAL(10,4) DEFAULT 0,
                `whatsapp_rate` DECIMAL(10,4) DEFAULT 0,
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_gateway_id` (`gateway_id`),
                INDEX `idx_country_code` (`country_code`),
                UNIQUE KEY `unique_gateway_country` (`gateway_id`, `country_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_gateway_countries");
    }

    // 3. Client settings table
    if (!$tableExists('mod_sms_settings')) {
        $execSql("
            CREATE TABLE `mod_sms_settings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `billing_mode` VARCHAR(20) DEFAULT 'per_segment',
                `default_gateway_id` INT UNSIGNED,
                `default_sender_id` VARCHAR(50),
                `assigned_sender_id` VARCHAR(50),
                `assigned_gateway_id` INT UNSIGNED,
                `monthly_limit` INT UNSIGNED,
                `monthly_used` INT UNSIGNED DEFAULT 0,
                `webhook_url` VARCHAR(500),
                `api_enabled` TINYINT(1) DEFAULT 1,
                `accept_sms` TINYINT(1) DEFAULT 1,
                `accept_marketing_sms` TINYINT(1) DEFAULT 0,
                `accept_whatsapp` TINYINT(1) DEFAULT 1,
                `whatsapp_number` VARCHAR(30),
                `two_factor_enabled` TINYINT(1) DEFAULT 0,
                `enabled_notifications` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_client` (`client_id`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_settings");
    } else {
        // Add missing columns to existing table
        $columnsToAdd = [
            'accept_sms' => "ALTER TABLE `mod_sms_settings` ADD COLUMN `accept_sms` TINYINT(1) DEFAULT 1 AFTER `api_enabled`",
            'accept_marketing_sms' => "ALTER TABLE `mod_sms_settings` ADD COLUMN `accept_marketing_sms` TINYINT(1) DEFAULT 0 AFTER `accept_sms`",
            'two_factor_enabled' => "ALTER TABLE `mod_sms_settings` ADD COLUMN `two_factor_enabled` TINYINT(1) DEFAULT 0 AFTER `accept_marketing_sms`",
            'enabled_notifications' => "ALTER TABLE `mod_sms_settings` ADD COLUMN `enabled_notifications` TEXT AFTER `two_factor_enabled`",
            'assigned_sender_id' => "ALTER TABLE `mod_sms_settings` ADD COLUMN `assigned_sender_id` VARCHAR(50) AFTER `default_sender_id`",
            'assigned_gateway_id' => "ALTER TABLE `mod_sms_settings` ADD COLUMN `assigned_gateway_id` INT UNSIGNED AFTER `default_gateway_id`",
            'monthly_limit' => "ALTER TABLE `mod_sms_settings` ADD COLUMN `monthly_limit` INT UNSIGNED AFTER `assigned_gateway_id`",
            'monthly_used' => "ALTER TABLE `mod_sms_settings` ADD COLUMN `monthly_used` INT UNSIGNED DEFAULT 0 AFTER `monthly_limit`",
        ];
        foreach ($columnsToAdd as $col => $sql) {
            if (!$columnExists('mod_sms_settings', $col)) {
                $execSql($sql, "Add column {$col} to mod_sms_settings");
            }
        }
    }

    // 4. Sender IDs
    if (!$tableExists('mod_sms_sender_ids')) {
        $execSql("
            CREATE TABLE `mod_sms_sender_ids` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `sender_id` VARCHAR(50) NOT NULL,
                `type` VARCHAR(20) DEFAULT 'alphanumeric',
                `network` VARCHAR(20) DEFAULT 'all',
                `status` VARCHAR(20) DEFAULT 'pending',
                `price` DECIMAL(10,2) DEFAULT 0,
                `currency_id` INT UNSIGNED,
                `invoice_id` INT UNSIGNED,
                `service_id` INT UNSIGNED,
                `gateway_ids` TEXT,
                `gateway_bindings` TEXT,
                `validity_date` DATE,
                `approved_at` TIMESTAMP NULL,
                `approved_by` INT UNSIGNED,
                `rejection_reason` TEXT,
                `notes` TEXT,
                `company_name` VARCHAR(255),
                `use_case` TEXT,
                `documents` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_network` (`network`),
                UNIQUE KEY `unique_client_sender_network` (`client_id`, `sender_id`, `network`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_sender_ids");
    } else {
        if (!$columnExists('mod_sms_sender_ids', 'service_id')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `service_id` INT UNSIGNED AFTER `invoice_id`", "Add service_id to mod_sms_sender_ids");
        }
        if (!$columnExists('mod_sms_sender_ids', 'network')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `network` VARCHAR(20) DEFAULT 'all' AFTER `type`", "Add network to mod_sms_sender_ids");
        }
        if (!$columnExists('mod_sms_sender_ids', 'company_name')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `company_name` VARCHAR(255) AFTER `notes`", "Add company_name to mod_sms_sender_ids");
        }
        if (!$columnExists('mod_sms_sender_ids', 'use_case')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `use_case` TEXT AFTER `company_name`", "Add use_case to mod_sms_sender_ids");
        }
        if (!$columnExists('mod_sms_sender_ids', 'documents')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `documents` TEXT AFTER `use_case`", "Add documents to mod_sms_sender_ids");
        }
        if (!$columnExists('mod_sms_sender_ids', 'gateway_bindings')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `gateway_bindings` TEXT AFTER `gateway_ids`", "Add gateway_bindings to mod_sms_sender_ids");
        }
        if (!$columnExists('mod_sms_sender_ids', 'approved_at')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `approved_at` TIMESTAMP NULL AFTER `validity_date`", "Add approved_at to mod_sms_sender_ids");
        }
        if (!$columnExists('mod_sms_sender_ids', 'approved_by')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `approved_by` INT UNSIGNED AFTER `approved_at`", "Add approved_by to mod_sms_sender_ids");
        }
        if (!$columnExists('mod_sms_sender_ids', 'rejection_reason')) {
            $execSql("ALTER TABLE `mod_sms_sender_ids` ADD COLUMN `rejection_reason` TEXT AFTER `approved_by`", "Add rejection_reason to mod_sms_sender_ids");
        }
    }

    // 5. Messages log table
    if (!$tableExists('mod_sms_messages')) {
        $execSql("
            CREATE TABLE `mod_sms_messages` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `campaign_id` INT UNSIGNED,
                `automation_id` INT UNSIGNED,
                `gateway_id` INT UNSIGNED,
                `channel` VARCHAR(20) DEFAULT 'sms',
                `direction` VARCHAR(10) DEFAULT 'outbound',
                `sender_id` VARCHAR(50),
                `from_number` VARCHAR(30),
                `to_number` VARCHAR(30) NOT NULL,
                `message` TEXT NOT NULL,
                `message_type` VARCHAR(30) DEFAULT 'text',
                `template_name` VARCHAR(100),
                `template_params` TEXT,
                `media_url` TEXT,
                `media_type` VARCHAR(20),
                `encoding` VARCHAR(10) DEFAULT 'gsm7',
                `segments` TINYINT UNSIGNED DEFAULT 1,
                `units` TINYINT UNSIGNED DEFAULT 1,
                `cost` DECIMAL(10,4) DEFAULT 0,
                `status` VARCHAR(20) DEFAULT 'queued',
                `provider_message_id` VARCHAR(100),
                `error` TEXT,
                `gateway_response` TEXT,
                `api_key_id` INT UNSIGNED,
                `sent_at` TIMESTAMP NULL,
                `delivered_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_created` (`client_id`, `created_at`),
                INDEX `idx_status` (`status`),
                INDEX `idx_provider_msg_id` (`provider_message_id`),
                INDEX `idx_gateway_created` (`gateway_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_messages");
    } else {
        // Add missing columns
        if (!$columnExists('mod_sms_messages', 'gateway_response')) {
            $execSql("ALTER TABLE `mod_sms_messages` ADD COLUMN `gateway_response` TEXT AFTER `error`", "Add gateway_response to mod_sms_messages");
        }
        if (!$columnExists('mod_sms_messages', 'sent_at')) {
            $execSql("ALTER TABLE `mod_sms_messages` ADD COLUMN `sent_at` TIMESTAMP NULL AFTER `api_key_id`", "Add sent_at to mod_sms_messages");
        }
        // WhatsApp columns
        if (!$columnExists('mod_sms_messages', 'message_type')) {
            $execSql("ALTER TABLE `mod_sms_messages` ADD COLUMN `message_type` VARCHAR(30) DEFAULT 'text' AFTER `message`", "Add message_type to mod_sms_messages");
        }
        if (!$columnExists('mod_sms_messages', 'template_name')) {
            $execSql("ALTER TABLE `mod_sms_messages` ADD COLUMN `template_name` VARCHAR(100) AFTER `message_type`", "Add template_name to mod_sms_messages");
        }
        if (!$columnExists('mod_sms_messages', 'template_params')) {
            $execSql("ALTER TABLE `mod_sms_messages` ADD COLUMN `template_params` TEXT AFTER `template_name`", "Add template_params to mod_sms_messages");
        }
        if (!$columnExists('mod_sms_messages', 'media_type')) {
            $execSql("ALTER TABLE `mod_sms_messages` ADD COLUMN `media_type` VARCHAR(20) AFTER `media_url`", "Add media_type to mod_sms_messages");
        }
        if (!$columnExists('mod_sms_messages', 'from_number')) {
            $execSql("ALTER TABLE `mod_sms_messages` ADD COLUMN `from_number` VARCHAR(30) AFTER `sender_id`", "Add from_number to mod_sms_messages");
        }
    }

    // 6. Wallet table
    if (!$tableExists('mod_sms_wallet')) {
        $execSql("
            CREATE TABLE `mod_sms_wallet` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `balance` DECIMAL(16,4) DEFAULT 0,
                `currency_id` INT UNSIGNED,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_client` (`client_id`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_wallet");
    }

    // 7. Wallet transactions
    if (!$tableExists('mod_sms_wallet_transactions')) {
        $execSql("
            CREATE TABLE `mod_sms_wallet_transactions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `type` VARCHAR(30) NOT NULL,
                `amount` DECIMAL(16,4) NOT NULL,
                `balance_before` DECIMAL(16,4),
                `balance_after` DECIMAL(16,4),
                `description` VARCHAR(255),
                `reference_type` VARCHAR(50),
                `reference_id` INT UNSIGNED,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_type` (`type`),
                INDEX `idx_client_date` (`client_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_wallet_transactions");
    } else {
        if (!$columnExists('mod_sms_wallet_transactions', 'balance_before')) {
            $execSql("ALTER TABLE `mod_sms_wallet_transactions` ADD COLUMN `balance_before` DECIMAL(16,4) AFTER `amount`", "Add balance_before to mod_sms_wallet_transactions");
        }
    }

    // 8. Contact groups
    if (!$tableExists('mod_sms_contact_groups')) {
        $execSql("
            CREATE TABLE `mod_sms_contact_groups` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `default_sender_id` VARCHAR(50),
                `welcome_sms` TEXT,
                `unsubscribe_sms` TEXT,
                `status` TINYINT(1) DEFAULT 1,
                `contact_count` INT UNSIGNED DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_client_group` (`client_id`, `name`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_contact_groups");
    } else {
        // Add unique constraint for client isolation
        try {
            $pdo->query("ALTER TABLE `mod_sms_contact_groups` ADD UNIQUE KEY `unique_client_group` (`client_id`, `name`)");
        } catch (Exception $e) {
            // Already exists or duplicate entries prevent it
        }
    }

    // 9. Contact group custom fields
    if (!$tableExists('mod_sms_contact_group_fields')) {
        $execSql("
            CREATE TABLE `mod_sms_contact_group_fields` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `group_id` INT UNSIGNED NOT NULL,
                `label` VARCHAR(100) NOT NULL,
                `tag` VARCHAR(50) NOT NULL,
                `type` VARCHAR(20) DEFAULT 'text',
                `default_value` VARCHAR(255),
                `required` TINYINT(1) DEFAULT 0,
                `visible` TINYINT(1) DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_group_id` (`group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_contact_group_fields");
    }

    // 10. Contacts
    if (!$tableExists('mod_sms_contacts')) {
        $execSql("
            CREATE TABLE `mod_sms_contacts` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `group_id` INT UNSIGNED,
                `phone` VARCHAR(30) NOT NULL,
                `first_name` VARCHAR(100),
                `last_name` VARCHAR(100),
                `email` VARCHAR(255),
                `status` VARCHAR(20) DEFAULT 'active',
                `custom_data` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_phone` (`client_id`, `phone`),
                INDEX `idx_group_status` (`group_id`, `status`),
                INDEX `idx_phone` (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_contacts");
    }

    // 11. Campaigns
    if (!$tableExists('mod_sms_campaigns')) {
        $execSql("
            CREATE TABLE `mod_sms_campaigns` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(200) NOT NULL,
                `channel` VARCHAR(20) DEFAULT 'sms',
                `gateway_id` INT UNSIGNED,
                `sender_id` VARCHAR(50),
                `message` TEXT NOT NULL,
                `media_url` TEXT,
                `recipient_type` VARCHAR(20) DEFAULT 'manual',
                `recipient_group_id` INT UNSIGNED,
                `segment_id` INT UNSIGNED,
                `recipient_tag_id` INT UNSIGNED,
                `recipient_list` LONGTEXT,
                `status` VARCHAR(20) DEFAULT 'draft',
                `schedule_time` DATETIME,
                `schedule_type` VARCHAR(20) DEFAULT 'onetime',
                `frequency_amount` INT,
                `frequency_unit` VARCHAR(10),
                `recurring_end` DATETIME,
                `total_recipients` INT UNSIGNED DEFAULT 0,
                `sent_count` INT UNSIGNED DEFAULT 0,
                `delivered_count` INT UNSIGNED DEFAULT 0,
                `failed_count` INT UNSIGNED DEFAULT 0,
                `cost_total` DECIMAL(16,4) DEFAULT 0,
                `batch_size` INT UNSIGNED DEFAULT 100,
                `batch_delay` INT UNSIGNED DEFAULT 1,
                `batch_id` VARCHAR(50),
                `started_at` DATETIME,
                `completed_at` DATETIME,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_status_schedule` (`status`, `schedule_time`),
                INDEX `idx_client_status` (`client_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_campaigns");
    } else {
        // Add columns for existing installs
        $campaignCols = Capsule::select("SHOW COLUMNS FROM `mod_sms_campaigns`");
        $existingCols = array_map(function($c) { return $c->Field; }, $campaignCols);
        $newCols = [
            'recipient_type' => "VARCHAR(20) DEFAULT 'manual' AFTER `media_url`",
            'recipient_group_id' => "INT UNSIGNED AFTER `recipient_type`",
            'segment_id' => "INT UNSIGNED AFTER `recipient_group_id`",
            'recipient_tag_id' => "INT UNSIGNED AFTER `segment_id`",
            'recipient_list' => "LONGTEXT AFTER `recipient_tag_id`",
            'batch_size' => "INT UNSIGNED DEFAULT 100 AFTER `cost_total`",
            'batch_delay' => "INT UNSIGNED DEFAULT 1 AFTER `batch_size`",
            'started_at' => "DATETIME AFTER `batch_id`",
            'completed_at' => "DATETIME AFTER `started_at`",
        ];
        foreach ($newCols as $col => $def) {
            if (!in_array($col, $existingCols)) {
                $execSql("ALTER TABLE `mod_sms_campaigns` ADD COLUMN `{$col}` {$def}", "Add {$col} to mod_sms_campaigns");
            }
        }
    }

    // 12. Campaign lists (junction)
    if (!$tableExists('mod_sms_campaign_lists')) {
        $execSql("
            CREATE TABLE `mod_sms_campaign_lists` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `campaign_id` INT UNSIGNED NOT NULL,
                `group_id` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_campaign_id` (`campaign_id`),
                UNIQUE KEY `unique_campaign_group` (`campaign_id`, `group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_campaign_lists");
    }

    // 13. Campaign recipients
    if (!$tableExists('mod_sms_campaign_recipients')) {
        $execSql("
            CREATE TABLE `mod_sms_campaign_recipients` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `campaign_id` INT UNSIGNED NOT NULL,
                `contact_id` INT UNSIGNED,
                `phone` VARCHAR(30) NOT NULL,
                `status` VARCHAR(20) DEFAULT 'pending',
                `message_id` INT UNSIGNED,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_campaign_status` (`campaign_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_campaign_recipients");
    }

    // 14. Templates
    if (!$tableExists('mod_sms_templates')) {
        $execSql("
            CREATE TABLE `mod_sms_templates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `type` VARCHAR(20) DEFAULT 'sms',
                `channel` VARCHAR(20) DEFAULT 'sms',
                `category` VARCHAR(50),
                `content` TEXT NOT NULL,
                `dlt_template_id` VARCHAR(50),
                `is_default` TINYINT(1) DEFAULT 0,
                `status` VARCHAR(20) DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_templates");
    } else {
        if (!$columnExists('mod_sms_templates', 'channel')) {
            $execSql("ALTER TABLE `mod_sms_templates` ADD COLUMN `channel` VARCHAR(20) DEFAULT 'sms' AFTER `type`", "Add channel to mod_sms_templates");
        }
        if (!$columnExists('mod_sms_templates', 'dlt_template_id')) {
            $execSql("ALTER TABLE `mod_sms_templates` ADD COLUMN `dlt_template_id` VARCHAR(50) AFTER `content`", "Add dlt_template_id to mod_sms_templates");
        }
        if (!$columnExists('mod_sms_templates', 'is_default')) {
            $execSql("ALTER TABLE `mod_sms_templates` ADD COLUMN `is_default` TINYINT(1) DEFAULT 0 AFTER `dlt_template_id`", "Add is_default to mod_sms_templates");
        }
    }

    // 15. API keys (must match ApiKeyService column names)
    if (!$tableExists('mod_sms_api_keys')) {
        $execSql("
            CREATE TABLE `mod_sms_api_keys` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `key_id` VARCHAR(64) NOT NULL,
                `secret_hash` VARCHAR(255) NOT NULL,
                `scopes` TEXT,
                `status` VARCHAR(20) DEFAULT 'active',
                `rate_limit` INT UNSIGNED DEFAULT 60,
                `allowed_ips` TEXT,
                `last_used_at` TIMESTAMP NULL,
                `expires_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                UNIQUE KEY `unique_key_id` (`key_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_api_keys");
    } else {
        // Migrate from old schema (api_key/api_secret/permissions) to new (key_id/secret_hash/scopes)
        if ($columnExists('mod_sms_api_keys', 'api_key') && !$columnExists('mod_sms_api_keys', 'key_id')) {
            $execSql("ALTER TABLE `mod_sms_api_keys` CHANGE `api_key` `key_id` VARCHAR(64) NOT NULL", "Rename api_key to key_id in mod_sms_api_keys");
        }
        if ($columnExists('mod_sms_api_keys', 'api_secret') && !$columnExists('mod_sms_api_keys', 'secret_hash')) {
            $execSql("ALTER TABLE `mod_sms_api_keys` CHANGE `api_secret` `secret_hash` VARCHAR(255) NOT NULL", "Rename api_secret to secret_hash in mod_sms_api_keys");
        }
        if ($columnExists('mod_sms_api_keys', 'permissions') && !$columnExists('mod_sms_api_keys', 'scopes')) {
            $execSql("ALTER TABLE `mod_sms_api_keys` CHANGE `permissions` `scopes` TEXT", "Rename permissions to scopes in mod_sms_api_keys");
        }
        if (!$columnExists('mod_sms_api_keys', 'key_id')) {
            $execSql("ALTER TABLE `mod_sms_api_keys` ADD COLUMN `key_id` VARCHAR(64) NOT NULL AFTER `name`", "Add key_id to mod_sms_api_keys");
        }
        if (!$columnExists('mod_sms_api_keys', 'secret_hash')) {
            $execSql("ALTER TABLE `mod_sms_api_keys` ADD COLUMN `secret_hash` VARCHAR(255) NOT NULL AFTER `key_id`", "Add secret_hash to mod_sms_api_keys");
        }
        if (!$columnExists('mod_sms_api_keys', 'scopes')) {
            $execSql("ALTER TABLE `mod_sms_api_keys` ADD COLUMN `scopes` TEXT AFTER `secret_hash`", "Add scopes to mod_sms_api_keys");
        }
    }

    // 16. Rate limits tracking (used by ApiKeyService::checkRateLimit)
    if (!$tableExists('mod_sms_rate_limits')) {
        $execSql("
            CREATE TABLE `mod_sms_rate_limits` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key_id` INT UNSIGNED NOT NULL,
                `window` VARCHAR(20) NOT NULL,
                `requests` INT UNSIGNED DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_key_id` (`key_id`),
                INDEX `idx_window` (`window`),
                UNIQUE KEY `unique_key_window` (`key_id`, `window`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_rate_limits");
    } else {
        // Migrate from old schema ('key'+'count') to new schema ('key_id'+'requests')
        if ($columnExists('mod_sms_rate_limits', 'key') && !$columnExists('mod_sms_rate_limits', 'key_id')) {
            $execSql("ALTER TABLE `mod_sms_rate_limits` ADD COLUMN `key_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`", "Add key_id to mod_sms_rate_limits");
            $execSql("ALTER TABLE `mod_sms_rate_limits` ADD COLUMN `requests` INT UNSIGNED DEFAULT 1 AFTER `window`", "Add requests to mod_sms_rate_limits");
        }
        if (!$columnExists('mod_sms_rate_limits', 'key_id')) {
            $execSql("ALTER TABLE `mod_sms_rate_limits` ADD COLUMN `key_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`", "Add key_id to mod_sms_rate_limits");
        }
        if (!$columnExists('mod_sms_rate_limits', 'requests')) {
            $execSql("ALTER TABLE `mod_sms_rate_limits` ADD COLUMN `requests` INT UNSIGNED DEFAULT 1 AFTER `window`", "Add requests to mod_sms_rate_limits");
        }
    }

    // 17. Webhooks inbox
    if (!$tableExists('mod_sms_webhooks_inbox')) {
        $execSql("
            CREATE TABLE `mod_sms_webhooks_inbox` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `gateway_id` INT UNSIGNED,
                `gateway_type` VARCHAR(50) NOT NULL,
                `payload` TEXT NOT NULL,
                `raw_payload` MEDIUMTEXT,
                `ip_address` VARCHAR(45),
                `processed` TINYINT(1) DEFAULT 0,
                `processed_at` TIMESTAMP NULL,
                `error` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_processed_created` (`processed`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_webhooks_inbox");
    } else {
        // Add missing columns
        if (!$columnExists('mod_sms_webhooks_inbox', 'raw_payload')) {
            $execSql("ALTER TABLE `mod_sms_webhooks_inbox` ADD COLUMN `raw_payload` MEDIUMTEXT AFTER `payload`", "Add raw_payload to mod_sms_webhooks_inbox");
        }
        if (!$columnExists('mod_sms_webhooks_inbox', 'ip_address')) {
            $execSql("ALTER TABLE `mod_sms_webhooks_inbox` ADD COLUMN `ip_address` VARCHAR(45) AFTER `raw_payload`", "Add ip_address to mod_sms_webhooks_inbox");
        }
        if (!$columnExists('mod_sms_webhooks_inbox', 'processed_at')) {
            $execSql("ALTER TABLE `mod_sms_webhooks_inbox` ADD COLUMN `processed_at` TIMESTAMP NULL AFTER `processed`", "Add processed_at to mod_sms_webhooks_inbox");
        }
    }

    // 18. Automation triggers
    if (!$tableExists('mod_sms_automations')) {
        $execSql("
            CREATE TABLE `mod_sms_automations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `trigger_type` VARCHAR(50) NOT NULL,
                `trigger_config` TEXT,
                `message_template` TEXT,
                `sender_id` VARCHAR(50),
                `gateway_id` INT UNSIGNED,
                `status` VARCHAR(20) DEFAULT 'active',
                `run_count` INT UNSIGNED DEFAULT 0,
                `last_run` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_trigger_type` (`trigger_type`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_automations");
    } else {
        // Fix schema if table was created by old Schema Builder with wrong columns
        if (!$columnExists('mod_sms_automations', 'trigger_type')) {
            if ($columnExists('mod_sms_automations', 'hook')) {
                $execSql("ALTER TABLE `mod_sms_automations` CHANGE `hook` `trigger_type` VARCHAR(50) NOT NULL", "Rename hook to trigger_type in mod_sms_automations");
            } else {
                $execSql("ALTER TABLE `mod_sms_automations` ADD COLUMN `trigger_type` VARCHAR(50) NOT NULL DEFAULT 'whmcs_hook' AFTER `name`", "Add trigger_type to mod_sms_automations");
            }
        }
        if (!$columnExists('mod_sms_automations', 'trigger_config')) {
            if ($columnExists('mod_sms_automations', 'conditions')) {
                $execSql("ALTER TABLE `mod_sms_automations` CHANGE `conditions` `trigger_config` TEXT", "Rename conditions to trigger_config in mod_sms_automations");
            } else {
                $execSql("ALTER TABLE `mod_sms_automations` ADD COLUMN `trigger_config` TEXT AFTER `trigger_type`", "Add trigger_config to mod_sms_automations");
            }
        }
        if (!$columnExists('mod_sms_automations', 'message_template')) {
            if ($columnExists('mod_sms_automations', 'message')) {
                $execSql("ALTER TABLE `mod_sms_automations` CHANGE `message` `message_template` TEXT", "Rename message to message_template in mod_sms_automations");
            } else {
                $execSql("ALTER TABLE `mod_sms_automations` ADD COLUMN `message_template` TEXT AFTER `trigger_config`", "Add message_template to mod_sms_automations");
            }
        }
        if (!$columnExists('mod_sms_automations', 'run_count')) {
            $execSql("ALTER TABLE `mod_sms_automations` ADD COLUMN `run_count` INT UNSIGNED DEFAULT 0 AFTER `status`", "Add run_count to mod_sms_automations");
        }
        if (!$columnExists('mod_sms_automations', 'last_run')) {
            $execSql("ALTER TABLE `mod_sms_automations` ADD COLUMN `last_run` TIMESTAMP NULL AFTER `run_count`", "Add last_run to mod_sms_automations");
        }
    }

    // 19. Verification tokens (for 2FA)
    if (!$tableExists('mod_sms_verification_tokens')) {
        $execSql("
            CREATE TABLE `mod_sms_verification_tokens` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `token` VARCHAR(10) NOT NULL,
                `phone` VARCHAR(30) NOT NULL,
                `purpose` VARCHAR(30) DEFAULT 'login',
                `attempts` TINYINT UNSIGNED DEFAULT 0,
                `verified` TINYINT(1) DEFAULT 0,
                `expires_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_token` (`token`),
                INDEX `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_verification_tokens");
    }

    // 20. Sender ID plans
    if (!$tableExists('mod_sms_sender_id_plans')) {
        $execSql("
            CREATE TABLE `mod_sms_sender_id_plans` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `price` DECIMAL(10,2) NOT NULL,
                `currency_id` INT UNSIGNED,
                `billing_cycle` VARCHAR(20) DEFAULT 'monthly',
                `validity_days` INT DEFAULT 30,
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_sender_id_plans");
    }

    // 21. Plan credits (for credit packages)
    if (!$tableExists('mod_sms_plan_credits')) {
        $execSql("
            CREATE TABLE `mod_sms_plan_credits` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `total` INT UNSIGNED NOT NULL,
                `remaining` INT UNSIGNED NOT NULL,
                `service_id` INT UNSIGNED,
                `expires_at` DATE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_plan_credits");
    }

    // 22. SMS packages (for billing page)
    if (!$tableExists('mod_sms_credit_packages')) {
        $execSql("
            CREATE TABLE `mod_sms_credit_packages` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `credits` INT UNSIGNED NOT NULL,
                `bonus_credits` INT UNSIGNED DEFAULT 0,
                `price` DECIMAL(10,2) NOT NULL,
                `currency` VARCHAR(10) DEFAULT 'USD',
                `currency_id` INT UNSIGNED,
                `validity_days` INT DEFAULT 0,
                `popular` TINYINT(1) DEFAULT 0,
                `status` TINYINT(1) DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_credit_packages");
    } else {
        if (!$columnExists('mod_sms_credit_packages', 'currency')) {
            $execSql("ALTER TABLE `mod_sms_credit_packages` ADD COLUMN `currency` VARCHAR(10) DEFAULT 'USD' AFTER `price`", "Add currency to mod_sms_credit_packages");
        }
        if (!$columnExists('mod_sms_credit_packages', 'description')) {
            $execSql("ALTER TABLE `mod_sms_credit_packages` ADD COLUMN `description` TEXT AFTER `name`", "Add description to mod_sms_credit_packages");
        }
    }

    // 23. Optouts
    if (!$tableExists('mod_sms_optouts')) {
        $execSql("
            CREATE TABLE `mod_sms_optouts` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `phone` VARCHAR(30) NOT NULL,
                `client_id` INT UNSIGNED,
                `reason` VARCHAR(255),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_phone` (`phone`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_optouts");
    }

    // 24. Blacklist (global and per-client blocked numbers)
    if (!$tableExists('mod_sms_blacklist')) {
        $execSql("
            CREATE TABLE `mod_sms_blacklist` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `phone` VARCHAR(30) NOT NULL,
                `client_id` INT UNSIGNED DEFAULT NULL,
                `reason` VARCHAR(255),
                `blocked_by` VARCHAR(50) DEFAULT 'system',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_phone` (`phone`),
                INDEX `idx_client_id` (`client_id`),
                UNIQUE KEY `unique_phone_client` (`phone`, `client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_blacklist");
    }

    // 25. Scheduled messages
    if (!$tableExists('mod_sms_scheduled')) {
        $execSql("
            CREATE TABLE `mod_sms_scheduled` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `gateway_id` INT UNSIGNED,
                `sender_id` VARCHAR(50),
                `to_number` VARCHAR(30) NOT NULL,
                `message` TEXT NOT NULL,
                `channel` VARCHAR(20) DEFAULT 'sms',
                `timezone` VARCHAR(50),
                `status` VARCHAR(20) DEFAULT 'pending',
                `scheduled_at` TIMESTAMP NOT NULL,
                `sent_at` TIMESTAMP NULL,
                `message_id` INT UNSIGNED,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_scheduled");
    } else {
        if (!$columnExists('mod_sms_scheduled', 'channel')) {
            $execSql("ALTER TABLE `mod_sms_scheduled` ADD COLUMN `channel` VARCHAR(20) DEFAULT 'sms' AFTER `message`", "Add channel to mod_sms_scheduled");
        }
        if (!$columnExists('mod_sms_scheduled', 'timezone')) {
            $execSql("ALTER TABLE `mod_sms_scheduled` ADD COLUMN `timezone` VARCHAR(50) AFTER `channel`", "Add timezone to mod_sms_scheduled");
        }
    }

    // 26. Notification templates
    if (!$tableExists('mod_sms_notification_templates')) {
        $execSql("
            CREATE TABLE `mod_sms_notification_templates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `notification_type` VARCHAR(50),
                `name` VARCHAR(100) NOT NULL,
                `category` VARCHAR(50) DEFAULT 'general',
                `type` VARCHAR(20) DEFAULT 'sms',
                `trigger_hook` VARCHAR(100),
                `subject` VARCHAR(255),
                `message` TEXT,
                `content` TEXT,
                `variables` TEXT,
                `status` VARCHAR(20) DEFAULT 'active',
                `wa_enabled` TINYINT(1) DEFAULT 0,
                `send_to_client` TINYINT(1) DEFAULT 1,
                `send_to_admin` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_category` (`category`),
                INDEX `idx_trigger` (`trigger_hook`),
                INDEX `idx_notification_type` (`notification_type`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_notification_templates");
    } else {
        if (!$columnExists('mod_sms_notification_templates', 'notification_type')) {
            $execSql("ALTER TABLE `mod_sms_notification_templates` ADD COLUMN `notification_type` VARCHAR(50) AFTER `id`", "Add notification_type to mod_sms_notification_templates");
            $execSql("ALTER TABLE `mod_sms_notification_templates` ADD INDEX `idx_notification_type` (`notification_type`)", "Add notification_type index");
        }
        if (!$columnExists('mod_sms_notification_templates', 'message')) {
            $execSql("ALTER TABLE `mod_sms_notification_templates` ADD COLUMN `message` TEXT AFTER `subject`", "Add message to mod_sms_notification_templates");
        }
        // Migrate status from TINYINT to VARCHAR if needed (1→active, 0→inactive)
    }

    // 27. Admin notifications log
    if (!$tableExists('mod_sms_admin_notifications')) {
        $execSql("
            CREATE TABLE `mod_sms_admin_notifications` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `admin_id` INT UNSIGNED,
                `event` VARCHAR(50) NOT NULL,
                `phone` VARCHAR(30),
                `enabled` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_admin_id` (`admin_id`),
                INDEX `idx_event` (`event`),
                UNIQUE KEY `unique_admin_event` (`admin_id`, `event`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_admin_notifications");
    } else {
        // Add missing columns for notification subscriptions
        if (!$columnExists('mod_sms_admin_notifications', 'event')) {
            $execSql("ALTER TABLE `mod_sms_admin_notifications` ADD COLUMN `event` VARCHAR(50) AFTER `admin_id`", "Add event to mod_sms_admin_notifications");
        }
        if (!$columnExists('mod_sms_admin_notifications', 'phone')) {
            $execSql("ALTER TABLE `mod_sms_admin_notifications` ADD COLUMN `phone` VARCHAR(30) AFTER `event`", "Add phone to mod_sms_admin_notifications");
        }
        if (!$columnExists('mod_sms_admin_notifications', 'enabled')) {
            $execSql("ALTER TABLE `mod_sms_admin_notifications` ADD COLUMN `enabled` TINYINT(1) DEFAULT 1 AFTER `phone`", "Add enabled to mod_sms_admin_notifications");
        }
    }

    // 27b. Client verification status
    if (!$tableExists('mod_sms_client_verification')) {
        $execSql("
            CREATE TABLE `mod_sms_client_verification` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `phone_verified` TINYINT(1) DEFAULT 0,
                `verified_at` TIMESTAMP NULL,
                `verified_phone` VARCHAR(30),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_client` (`client_id`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_client_verification");
    }

    // 27c. Order verification status
    if (!$tableExists('mod_sms_order_verification')) {
        $execSql("
            CREATE TABLE `mod_sms_order_verification` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT UNSIGNED NOT NULL,
                `verified` TINYINT(1) DEFAULT 0,
                `verified_at` TIMESTAMP NULL,
                `verification_type` VARCHAR(20) DEFAULT 'sms',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_order` (`order_id`),
                INDEX `idx_order_id` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_order_verification");
    }

    // 28. Cron status tracking
    if (!$tableExists('mod_sms_cron_status')) {
        $execSql("
            CREATE TABLE `mod_sms_cron_status` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `task` VARCHAR(50) NOT NULL,
                `last_run` TIMESTAMP NULL,
                `is_running` TINYINT(1) DEFAULT 0,
                `pid` INT UNSIGNED NULL,
                `started_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_task` (`task`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_cron_status");
    } else {
        if (!$columnExists('mod_sms_cron_status', 'pid')) {
            $execSql("ALTER TABLE `mod_sms_cron_status` ADD COLUMN `pid` INT UNSIGNED NULL AFTER `is_running`", "Add pid to mod_sms_cron_status");
        }
        if (!$columnExists('mod_sms_cron_status', 'updated_at')) {
            $execSql("ALTER TABLE `mod_sms_cron_status` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`", "Add updated_at to mod_sms_cron_status");
        }
    }

    // 29. Countries reference table
    if (!$tableExists('mod_sms_countries')) {
        $execSql("
            CREATE TABLE `mod_sms_countries` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `iso_code` VARCHAR(3) NOT NULL,
                `phone_code` VARCHAR(10) NOT NULL,
                `status` TINYINT(1) DEFAULT 1,
                INDEX `idx_iso_code` (`iso_code`),
                INDEX `idx_phone_code` (`phone_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_countries");
    }

    // 30. Automation triggers
    if (!$tableExists('mod_sms_automation_triggers')) {
        $execSql("
            CREATE TABLE `mod_sms_automation_triggers` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `automation_id` INT UNSIGNED NOT NULL,
                `trigger_type` VARCHAR(50) NOT NULL,
                `trigger_config` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_automation_id` (`automation_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_automation_triggers");
    }

    // 31. Automation logs
    if (!$tableExists('mod_sms_automation_logs')) {
        $execSql("
            CREATE TABLE `mod_sms_automation_logs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `automation_id` INT UNSIGNED NOT NULL,
                `trigger_data` TEXT,
                `message_id` INT UNSIGNED,
                `status` VARCHAR(20) DEFAULT 'sent',
                `error` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_automation_id` (`automation_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_automation_logs");
    } else {
        // Fix schema if table was created by old Schema Builder with wrong columns
        if (!$columnExists('mod_sms_automation_logs', 'trigger_data')) {
            if ($columnExists('mod_sms_automation_logs', 'hook')) {
                // Old schema had hook + recipient columns; migrate to trigger_data JSON
                $execSql("ALTER TABLE `mod_sms_automation_logs` ADD COLUMN `trigger_data` TEXT AFTER `automation_id`", "Add trigger_data to mod_sms_automation_logs");
                // Migrate existing data: combine hook and recipient into trigger_data JSON
                try {
                    $pdo->exec("UPDATE `mod_sms_automation_logs` SET `trigger_data` = CONCAT('{\"hook\":\"', IFNULL(`hook`,''), '\",\"recipient\":\"', IFNULL(`recipient`,''), '\"}') WHERE `trigger_data` IS NULL");
                } catch (Exception $e) {}
            } else {
                $execSql("ALTER TABLE `mod_sms_automation_logs` ADD COLUMN `trigger_data` TEXT AFTER `automation_id`", "Add trigger_data to mod_sms_automation_logs");
            }
        }
        if (!$columnExists('mod_sms_automation_logs', 'status')) {
            if ($columnExists('mod_sms_automation_logs', 'success')) {
                // Old schema had boolean success; add status column and migrate
                $execSql("ALTER TABLE `mod_sms_automation_logs` ADD COLUMN `status` VARCHAR(20) DEFAULT 'sent' AFTER `message_id`", "Add status to mod_sms_automation_logs");
                try {
                    $pdo->exec("UPDATE `mod_sms_automation_logs` SET `status` = CASE WHEN `success` = 1 THEN 'sent' ELSE 'failed' END WHERE `status` = 'sent'");
                } catch (Exception $e) {}
            } else {
                $execSql("ALTER TABLE `mod_sms_automation_logs` ADD COLUMN `status` VARCHAR(20) DEFAULT 'sent' AFTER `message_id`", "Add status to mod_sms_automation_logs");
            }
        }
    }

    // 32. Pending wallet topups
    if (!$tableExists('mod_sms_pending_topups')) {
        $execSql("
            CREATE TABLE `mod_sms_pending_topups` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(16,4) NOT NULL,
                `invoice_id` INT UNSIGNED,
                `status` VARCHAR(20) DEFAULT 'pending',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_invoice_id` (`invoice_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_pending_topups");
    }

    // 33. API audit log
    if (!$tableExists('mod_sms_api_audit')) {
        $execSql("
            CREATE TABLE `mod_sms_api_audit` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `api_key_id` INT UNSIGNED,
                `client_id` INT UNSIGNED,
                `endpoint` VARCHAR(100),
                `method` VARCHAR(10),
                `request_data` TEXT,
                `response_code` INT,
                `response_data` TEXT,
                `ip_address` VARCHAR(45),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_api_key_id` (`api_key_id`),
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_api_audit");
    }

    // 34. API rate limits per key
    if (!$tableExists('mod_sms_api_rate_limits')) {
        $execSql("
            CREATE TABLE `mod_sms_api_rate_limits` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `api_key_id` INT UNSIGNED NOT NULL,
                `endpoint` VARCHAR(100),
                `requests` INT UNSIGNED DEFAULT 0,
                `window_start` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_api_key_window` (`api_key_id`, `window_start`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_api_rate_limits");
    }

    // 35. WhatsApp message templates
    if (!$tableExists('mod_sms_whatsapp_templates')) {
        $execSql("
            CREATE TABLE `mod_sms_whatsapp_templates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `gateway_id` INT UNSIGNED,
                `template_name` VARCHAR(100) NOT NULL,
                `template_id` VARCHAR(100),
                `language` VARCHAR(10) DEFAULT 'en',
                `category` VARCHAR(50),
                `content` TEXT,
                `variables` TEXT,
                `status` VARCHAR(20) DEFAULT 'pending',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_whatsapp_templates");
    }

    // 36. Chatbox conversations
    if (!$tableExists('mod_sms_chatbox')) {
        $execSql("
            CREATE TABLE `mod_sms_chatbox` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED,
                `gateway_id` INT UNSIGNED,
                `phone` VARCHAR(30) NOT NULL,
                `contact_name` VARCHAR(100),
                `channel` VARCHAR(20) DEFAULT 'sms',
                `last_message` TEXT,
                `last_message_at` TIMESTAMP NULL,
                `unread_count` INT UNSIGNED DEFAULT 0,
                `status` VARCHAR(20) DEFAULT 'open',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_phone` (`phone`),
                INDEX `idx_status` (`status`),
                INDEX `idx_last_message_at` (`last_message_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_chatbox");
    } else {
        if (!$columnExists('mod_sms_chatbox', 'gateway_id')) {
            $execSql("ALTER TABLE `mod_sms_chatbox` ADD COLUMN `gateway_id` INT UNSIGNED AFTER `client_id`", "Add gateway_id to mod_sms_chatbox");
        }
        if (!$columnExists('mod_sms_chatbox', 'last_message')) {
            $execSql("ALTER TABLE `mod_sms_chatbox` ADD COLUMN `last_message` TEXT AFTER `channel`", "Add last_message to mod_sms_chatbox");
        }
    }

    // 37. Chatbox messages
    if (!$tableExists('mod_sms_chatbox_messages')) {
        $execSql("
            CREATE TABLE `mod_sms_chatbox_messages` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `chatbox_id` INT UNSIGNED NOT NULL,
                `direction` VARCHAR(10) NOT NULL,
                `message` TEXT,
                `media_url` TEXT,
                `message_id` INT UNSIGNED,
                `status` VARCHAR(20) DEFAULT 'sent',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_chatbox_id` (`chatbox_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_chatbox_messages");
    }

    // Add channel index to chatbox if missing
    $hasChannelIdx = false;
    try {
        $idxRows = Capsule::select("SHOW INDEX FROM `mod_sms_chatbox` WHERE Key_name = 'idx_channel'");
        $hasChannelIdx = !empty($idxRows);
    } catch (\Exception $e) {}
    if (!$hasChannelIdx && $tableExists('mod_sms_chatbox')) {
        $execSql("ALTER TABLE `mod_sms_chatbox` ADD INDEX `idx_channel` (`channel`)", "Add idx_channel to mod_sms_chatbox");
    }

    // One-time backfill: populate mod_sms_chatbox from existing mod_sms_messages
    if ($tableExists('mod_sms_chatbox') && $tableExists('mod_sms_messages')) {
        $chatboxCount = (int)Capsule::table('mod_sms_chatbox')->count();
        if ($chatboxCount === 0) {
            // Backfill from messages grouped by client_id + to_number + channel
            try {
                $grouped = Capsule::table('mod_sms_messages')
                    ->selectRaw('client_id, to_number, channel, gateway_id, MAX(message) as last_msg, MAX(created_at) as last_time, COUNT(*) as msg_count')
                    ->whereNotNull('to_number')
                    ->where('to_number', '!=', '')
                    ->groupBy('client_id', 'to_number', 'channel', 'gateway_id')
                    ->orderBy('last_time', 'desc')
                    ->limit(500)
                    ->get();

                foreach ($grouped as $row) {
                    // Try to find contact name
                    $contactName = null;
                    $contact = Capsule::table('mod_sms_contacts')
                        ->where('phone', $row->to_number)
                        ->first();
                    if ($contact) {
                        $contactName = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
                    }

                    $chatboxId = Capsule::table('mod_sms_chatbox')->insertGetId([
                        'client_id' => $row->client_id ?: null,
                        'gateway_id' => $row->gateway_id ?: null,
                        'phone' => $row->to_number,
                        'contact_name' => $contactName ?: null,
                        'channel' => $row->channel ?: 'sms',
                        'last_message' => substr($row->last_msg ?? '', 0, 255),
                        'last_message_at' => $row->last_time,
                        'unread_count' => 0,
                        'status' => 'open',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    // Link messages to chatbox
                    $msgs = Capsule::table('mod_sms_messages')
                        ->where('to_number', $row->to_number)
                        ->where('client_id', $row->client_id ?? 0)
                        ->where('channel', $row->channel ?: 'sms')
                        ->orderBy('created_at', 'asc')
                        ->limit(100)
                        ->get();

                    foreach ($msgs as $msg) {
                        Capsule::table('mod_sms_chatbox_messages')->insert([
                            'chatbox_id' => $chatboxId,
                            'message_id' => $msg->id,
                            'direction' => $msg->direction ?? 'outbound',
                            'created_at' => $msg->created_at,
                        ]);
                    }
                }

                if (count($grouped) > 0) {
                    logActivity("SMS Suite: Backfilled " . count($grouped) . " chatbox conversations from existing messages");
                }
            } catch (\Exception $e) {
                logActivity("SMS Suite: Chatbox backfill error - " . $e->getMessage());
            }
        }
    }

    // 38. Auto-replies
    if (!$tableExists('mod_sms_auto_replies')) {
        $execSql("
            CREATE TABLE `mod_sms_auto_replies` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED,
                `keyword` VARCHAR(100),
                `match_type` VARCHAR(20) DEFAULT 'exact',
                `reply_message` TEXT NOT NULL,
                `sender_id` VARCHAR(50),
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_keyword` (`keyword`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_auto_replies");
    }

    // 39. Sender ID requests (with document uploads)
    if (!$tableExists('mod_sms_sender_id_requests')) {
        $execSql("
            CREATE TABLE `mod_sms_sender_id_requests` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `sender_id` VARCHAR(50) NOT NULL,
                `type` VARCHAR(20) DEFAULT 'alphanumeric',
                `pool_id` INT UNSIGNED,
                `gateway_id` INT UNSIGNED,
                `business_name` VARCHAR(255),
                `use_case` TEXT,
                `company_name` VARCHAR(255),
                `registration_number` VARCHAR(100),
                `documents` TEXT,
                `doc_certificate` VARCHAR(500),
                `doc_vat_cert` VARCHAR(500),
                `doc_authorization` VARCHAR(500),
                `doc_other` VARCHAR(500),
                `billing_cycle` VARCHAR(20) DEFAULT 'monthly',
                `setup_fee` DECIMAL(10,2) DEFAULT 0,
                `recurring_fee` DECIMAL(10,2) DEFAULT 0,
                `invoice_id` INT UNSIGNED,
                `status` VARCHAR(20) DEFAULT 'pending',
                `admin_notes` TEXT,
                `approved_by` INT UNSIGNED,
                `approved_at` TIMESTAMP NULL,
                `expires_at` TIMESTAMP NULL,
                `reviewed_by` INT UNSIGNED,
                `reviewed_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_sender_id` (`sender_id`),
                INDEX `idx_invoice_id` (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_sender_id_requests");
    } else {
        // Add missing columns for billing integration
        $senderReqCols = [
            'pool_id' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `pool_id` INT UNSIGNED AFTER `type`",
            'business_name' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `business_name` VARCHAR(255) AFTER `gateway_id`",
            'documents' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `documents` TEXT AFTER `registration_number`",
            'billing_cycle' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `billing_cycle` VARCHAR(20) DEFAULT 'monthly' AFTER `doc_other`",
            'setup_fee' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `setup_fee` DECIMAL(10,2) DEFAULT 0 AFTER `billing_cycle`",
            'recurring_fee' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `recurring_fee` DECIMAL(10,2) DEFAULT 0 AFTER `setup_fee`",
            'invoice_id' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `invoice_id` INT UNSIGNED AFTER `recurring_fee`",
            'approved_by' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `approved_by` INT UNSIGNED AFTER `admin_notes`",
            'approved_at' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `approved_at` TIMESTAMP NULL AFTER `approved_by`",
            'expires_at' => "ALTER TABLE `mod_sms_sender_id_requests` ADD COLUMN `expires_at` TIMESTAMP NULL AFTER `approved_at`",
        ];
        foreach ($senderReqCols as $col => $sql) {
            if (!$columnExists('mod_sms_sender_id_requests', $col)) {
                $execSql($sql, "Add {$col} to mod_sms_sender_id_requests");
            }
        }
    }

    // 40. Sender ID pool (admin-managed pool of sender IDs)
    if (!$tableExists('mod_sms_sender_id_pool')) {
        $execSql("
            CREATE TABLE `mod_sms_sender_id_pool` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `sender_id` VARCHAR(50) NOT NULL,
                `type` VARCHAR(20) DEFAULT 'alphanumeric',
                `network` VARCHAR(20) DEFAULT 'all',
                `gateway_id` INT UNSIGNED,
                `country_codes` TEXT,
                `description` TEXT,
                `price_setup` DECIMAL(10,2) DEFAULT 0,
                `price_monthly` DECIMAL(10,2) DEFAULT 0,
                `price_yearly` DECIMAL(10,2) DEFAULT 0,
                `requires_approval` TINYINT(1) DEFAULT 1,
                `is_shared` TINYINT(1) DEFAULT 0,
                `telco_status` VARCHAR(20) DEFAULT 'approved',
                `telco_approved_date` DATE,
                `telco_reference` VARCHAR(100),
                `status` VARCHAR(20) DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_sender_id` (`sender_id`),
                INDEX `idx_network` (`network`),
                INDEX `idx_status` (`status`),
                INDEX `idx_gateway_id` (`gateway_id`),
                INDEX `idx_telco_status` (`telco_status`),
                UNIQUE KEY `unique_sender_network` (`sender_id`, `network`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_sender_id_pool");
    } else {
        // Add missing columns to existing table (no AFTER clauses — target columns may not exist)
        if (!$columnExists('mod_sms_sender_id_pool', 'network')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `network` VARCHAR(20) DEFAULT 'all'", "Add network to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'country_codes')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `country_codes` TEXT", "Add country_codes to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'description')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `description` TEXT", "Add description to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'price_setup')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `price_setup` DECIMAL(10,2) DEFAULT 0", "Add price_setup to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'price_monthly')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `price_monthly` DECIMAL(10,2) DEFAULT 0", "Add price_monthly to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'price_yearly')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `price_yearly` DECIMAL(10,2) DEFAULT 0", "Add price_yearly to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'requires_approval')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `requires_approval` TINYINT(1) DEFAULT 1", "Add requires_approval to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'is_shared')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `is_shared` TINYINT(1) DEFAULT 0", "Add is_shared to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'telco_status')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `telco_status` VARCHAR(20) DEFAULT 'approved'", "Add telco_status to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'telco_approved_date')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `telco_approved_date` DATE", "Add telco_approved_date to mod_sms_sender_id_pool");
        }
        if (!$columnExists('mod_sms_sender_id_pool', 'telco_reference')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_pool` ADD COLUMN `telco_reference` VARCHAR(100)", "Add telco_reference to mod_sms_sender_id_pool");
        }
    }

    // 41. Client-specific rates (per client, gateway, destination)
    if (!$tableExists('mod_sms_client_rates')) {
        $execSql("
            CREATE TABLE `mod_sms_client_rates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `gateway_id` INT UNSIGNED,
                `country_code` VARCHAR(5),
                `network_prefix` VARCHAR(10),
                `sms_rate` DECIMAL(10,6) NOT NULL,
                `whatsapp_rate` DECIMAL(10,6),
                `effective_from` DATE,
                `effective_to` DATE,
                `priority` INT DEFAULT 0,
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_gateway_id` (`gateway_id`),
                INDEX `idx_country_code` (`country_code`),
                INDEX `idx_lookup` (`client_id`, `gateway_id`, `country_code`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_client_rates");
    }

    // 42. Credit packages - already created in block #22 above

    // 43. Credit purchases history
    if (!$tableExists('mod_sms_credit_purchases')) {
        $execSql("
            CREATE TABLE `mod_sms_credit_purchases` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `package_id` INT UNSIGNED,
                `credits_purchased` INT UNSIGNED NOT NULL,
                `bonus_credits` INT UNSIGNED DEFAULT 0,
                `amount` DECIMAL(10,2) NOT NULL,
                `invoice_id` INT UNSIGNED,
                `status` VARCHAR(20) DEFAULT 'pending',
                `credited_at` TIMESTAMP NULL,
                `expires_at` DATE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_invoice_id` (`invoice_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_credit_purchases");
    } else {
        // Fix column name if old schema used 'credits' instead of 'credits_purchased'
        if ($columnExists('mod_sms_credit_purchases', 'credits') && !$columnExists('mod_sms_credit_purchases', 'credits_purchased')) {
            $execSql("ALTER TABLE `mod_sms_credit_purchases` CHANGE `credits` `credits_purchased` INT UNSIGNED NOT NULL", "Rename credits to credits_purchased in mod_sms_credit_purchases");
        }
        if (!$columnExists('mod_sms_credit_purchases', 'credited_at')) {
            $execSql("ALTER TABLE `mod_sms_credit_purchases` ADD COLUMN `credited_at` TIMESTAMP NULL AFTER `status`", "Add credited_at to mod_sms_credit_purchases");
        }
        if (!$columnExists('mod_sms_credit_purchases', 'updated_at')) {
            $execSql("ALTER TABLE `mod_sms_credit_purchases` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`", "Add updated_at to mod_sms_credit_purchases");
        }
    }

    // 44. Credit balance tracking
    if (!$tableExists('mod_sms_credit_balance')) {
        $execSql("
            CREATE TABLE `mod_sms_credit_balance` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `balance` INT UNSIGNED DEFAULT 0,
                `total_purchased` INT UNSIGNED DEFAULT 0,
                `total_used` INT UNSIGNED DEFAULT 0,
                `total_expired` INT UNSIGNED DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_client` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_credit_balance");
    } else {
        // Migrate old column names to BillingService convention
        if ($columnExists('mod_sms_credit_balance', 'total_credits') && !$columnExists('mod_sms_credit_balance', 'balance')) {
            $execSql("ALTER TABLE `mod_sms_credit_balance` CHANGE `total_credits` `balance` INT UNSIGNED DEFAULT 0", "Rename total_credits to balance");
        }
        if ($columnExists('mod_sms_credit_balance', 'used_credits') && !$columnExists('mod_sms_credit_balance', 'total_used')) {
            $execSql("ALTER TABLE `mod_sms_credit_balance` CHANGE `used_credits` `total_used` INT UNSIGNED DEFAULT 0", "Rename used_credits to total_used");
        }
        if ($columnExists('mod_sms_credit_balance', 'reserved_credits') && !$columnExists('mod_sms_credit_balance', 'total_expired')) {
            $execSql("ALTER TABLE `mod_sms_credit_balance` CHANGE `reserved_credits` `total_expired` INT UNSIGNED DEFAULT 0", "Rename reserved_credits to total_expired");
        }
        if (!$columnExists('mod_sms_credit_balance', 'total_purchased')) {
            $execSql("ALTER TABLE `mod_sms_credit_balance` ADD COLUMN `total_purchased` INT UNSIGNED DEFAULT 0 AFTER `balance`", "Add total_purchased to mod_sms_credit_balance");
        }
    }

    // 45. Credit transactions
    if (!$tableExists('mod_sms_credit_transactions')) {
        $execSql("
            CREATE TABLE `mod_sms_credit_transactions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `type` VARCHAR(30) NOT NULL,
                `credits` INT NOT NULL,
                `balance_before` INT UNSIGNED,
                `balance_after` INT UNSIGNED,
                `description` VARCHAR(255),
                `reference_type` VARCHAR(50),
                `reference_id` INT UNSIGNED,
                `admin_id` INT UNSIGNED,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_type` (`type`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_credit_transactions");
    } else {
        if (!$columnExists('mod_sms_credit_transactions', 'balance_before')) {
            $execSql("ALTER TABLE `mod_sms_credit_transactions` ADD COLUMN `balance_before` INT UNSIGNED AFTER `credits`", "Add balance_before to mod_sms_credit_transactions");
        }
        if (!$columnExists('mod_sms_credit_transactions', 'admin_id')) {
            $execSql("ALTER TABLE `mod_sms_credit_transactions` ADD COLUMN `admin_id` INT UNSIGNED AFTER `reference_id`", "Add admin_id to mod_sms_credit_transactions");
        }
        if ($columnExists('mod_sms_credit_transactions', 'amount') && !$columnExists('mod_sms_credit_transactions', 'credits')) {
            $execSql("ALTER TABLE `mod_sms_credit_transactions` CHANGE `amount` `credits` INT NOT NULL", "Rename amount to credits");
        }
    }

    // 46. Client sender IDs (assigned to clients)
    if (!$tableExists('mod_sms_client_sender_ids')) {
        $execSql("
            CREATE TABLE `mod_sms_client_sender_ids` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `sender_id` VARCHAR(50) NOT NULL,
                `pool_id` INT UNSIGNED,
                `request_id` INT UNSIGNED,
                `gateway_id` INT UNSIGNED,
                `type` VARCHAR(20) DEFAULT 'alphanumeric',
                `network` VARCHAR(20) DEFAULT 'all',
                `status` VARCHAR(20) DEFAULT 'active',
                `is_default` TINYINT(1) DEFAULT 0,
                `service_id` INT UNSIGNED,
                `monthly_fee` DECIMAL(10,2) DEFAULT 0,
                `next_billing` DATE,
                `expires_at` DATE,
                `last_invoice_id` INT UNSIGNED,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_network` (`network`),
                UNIQUE KEY `unique_client_sender_network` (`client_id`, `sender_id`, `network`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_client_sender_ids");
    } else {
        if (!$columnExists('mod_sms_client_sender_ids', 'is_default')) {
            $execSql("ALTER TABLE `mod_sms_client_sender_ids` ADD COLUMN `is_default` TINYINT(1) DEFAULT 0 AFTER `status`", "Add is_default to mod_sms_client_sender_ids");
        }
        if (!$columnExists('mod_sms_client_sender_ids', 'network')) {
            $execSql("ALTER TABLE `mod_sms_client_sender_ids` ADD COLUMN `network` VARCHAR(20) DEFAULT 'all' AFTER `type`", "Add network to mod_sms_client_sender_ids");
        }
        if (!$columnExists('mod_sms_client_sender_ids', 'last_invoice_id')) {
            $execSql("ALTER TABLE `mod_sms_client_sender_ids` ADD COLUMN `last_invoice_id` INT UNSIGNED AFTER `expires_at`", "Add last_invoice_id to mod_sms_client_sender_ids");
        }
    }

    // 47b. Credit Allocations (track credits per package/service)
    if (!$tableExists('mod_sms_credit_allocations')) {
        $execSql("
            CREATE TABLE `mod_sms_credit_allocations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `service_id` INT UNSIGNED,
                `sender_id_ref` INT UNSIGNED,
                `total_credits` INT NOT NULL,
                `remaining_credits` INT NOT NULL,
                `used_credits` INT DEFAULT 0,
                `expires_at` DATETIME,
                `status` VARCHAR(20) DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_service_id` (`service_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_credit_allocations");
    }

    // 47c. Credit Usage Log (track per-message credit usage linked to sender ID)
    if (!$tableExists('mod_sms_credit_usage')) {
        $execSql("
            CREATE TABLE `mod_sms_credit_usage` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `allocation_id` INT UNSIGNED,
                `sender_id_ref` INT UNSIGNED,
                `message_id` INT UNSIGNED,
                `credits_used` INT DEFAULT 1,
                `destination` VARCHAR(30),
                `network` VARCHAR(20),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_allocation_id` (`allocation_id`),
                INDEX `idx_sender_id_ref` (`sender_id_ref`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_credit_usage");
    }

    // 47d. Network Prefixes (for detecting carrier/operator from phone numbers)
    if (!$tableExists('mod_sms_network_prefixes')) {
        $execSql("
            CREATE TABLE `mod_sms_network_prefixes` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `country_code` VARCHAR(5) NOT NULL,
                `country_name` VARCHAR(100),
                `prefix` VARCHAR(10) NOT NULL,
                `operator` VARCHAR(100) NOT NULL,
                `operator_code` VARCHAR(20),
                `network_type` VARCHAR(20) DEFAULT 'mobile',
                `mcc` VARCHAR(5),
                `mnc` VARCHAR(5),
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_country_code` (`country_code`),
                INDEX `idx_prefix` (`prefix`),
                INDEX `idx_operator` (`operator`),
                UNIQUE KEY `unique_country_prefix` (`country_code`, `prefix`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_network_prefixes");
    }

    // 47a. Destination Rates (global rate card by country + network)
    if (!$tableExists('mod_sms_destination_rates')) {
        $execSql("
            CREATE TABLE `mod_sms_destination_rates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `country_code` VARCHAR(5) NOT NULL,
                `network` VARCHAR(50) DEFAULT NULL,
                `sms_rate` DECIMAL(10,6) DEFAULT 0,
                `whatsapp_rate` DECIMAL(10,6) DEFAULT 0,
                `credit_cost` INT UNSIGNED DEFAULT 1,
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_destination` (`country_code`, `network`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_destination_rates");
    }

    // 47. Sender ID billing records
    if (!$tableExists('mod_sms_sender_id_billing')) {
        $execSql("
            CREATE TABLE `mod_sms_sender_id_billing` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_sender_id` INT UNSIGNED NOT NULL,
                `client_id` INT UNSIGNED NOT NULL,
                `billing_type` VARCHAR(20) DEFAULT 'setup',
                `amount` DECIMAL(10,2) NOT NULL,
                `invoice_id` INT UNSIGNED,
                `period_start` DATE,
                `period_end` DATE,
                `status` VARCHAR(20) DEFAULT 'pending',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_invoice_id` (`invoice_id`),
                INDEX `idx_billing_type` (`billing_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_sender_id_billing");
    } else {
        if (!$columnExists('mod_sms_sender_id_billing', 'billing_type')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_billing` ADD COLUMN `billing_type` VARCHAR(20) DEFAULT 'setup' AFTER `client_id`", "Add billing_type to mod_sms_sender_id_billing");
        }
        if (!$columnExists('mod_sms_sender_id_billing', 'updated_at')) {
            $execSql("ALTER TABLE `mod_sms_sender_id_billing` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`", "Add updated_at to mod_sms_sender_id_billing");
        }
    }

    // 48. Verification logs
    if (!$tableExists('mod_sms_verification_logs')) {
        $execSql("
            CREATE TABLE `mod_sms_verification_logs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED,
                `phone` VARCHAR(30) NOT NULL,
                `type` VARCHAR(30) NOT NULL,
                `token_hash` VARCHAR(255),
                `attempts` TINYINT UNSIGNED DEFAULT 0,
                `verified` TINYINT(1) DEFAULT 0,
                `verified_at` TIMESTAMP NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `ip_address` VARCHAR(45),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_phone` (`phone`),
                INDEX `idx_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_verification_logs");
    }

    // 48b. Verification message templates
    if (!$tableExists('mod_sms_verification_templates')) {
        $execSql("
            CREATE TABLE `mod_sms_verification_templates` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `type` VARCHAR(50) NOT NULL,
                `message` TEXT,
                `status` VARCHAR(20) DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_verification_templates");
    }

    // 49. Segments for targeting
    if (!$tableExists('mod_sms_segments')) {
        $execSql("
            CREATE TABLE `mod_sms_segments` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `type` VARCHAR(20) DEFAULT 'dynamic',
                `conditions` TEXT,
                `match_type` VARCHAR(10) DEFAULT 'all',
                `contact_count` INT UNSIGNED DEFAULT 0,
                `last_calculated_at` TIMESTAMP NULL,
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_client_segment` (`client_id`, `name`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_segments");
    } else {
        if (!$columnExists('mod_sms_segments', 'conditions')) {
            $execSql("ALTER TABLE `mod_sms_segments` ADD COLUMN `conditions` TEXT AFTER `type`", "Add conditions to mod_sms_segments");
        }
        if (!$columnExists('mod_sms_segments', 'match_type')) {
            $execSql("ALTER TABLE `mod_sms_segments` ADD COLUMN `match_type` VARCHAR(10) DEFAULT 'all' AFTER `conditions`", "Add match_type to mod_sms_segments");
        }
        // Add unique constraint for client isolation
        try {
            $pdo->query("ALTER TABLE `mod_sms_segments` ADD UNIQUE KEY `unique_client_segment` (`client_id`, `name`)");
        } catch (Exception $e) {
            // Already exists or duplicate entries prevent it
        }
    }

    // 50. Segment conditions
    if (!$tableExists('mod_sms_segment_conditions')) {
        $execSql("
            CREATE TABLE `mod_sms_segment_conditions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `segment_id` INT UNSIGNED NOT NULL,
                `field` VARCHAR(50) NOT NULL,
                `operator` VARCHAR(20) NOT NULL,
                `value` VARCHAR(255),
                `logic` VARCHAR(5) DEFAULT 'AND',
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_segment_id` (`segment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_segment_conditions");
    }

    // Migrate last_calculated → last_calculated_at
    if ($tableExists('mod_sms_segments') && $columnExists('mod_sms_segments', 'last_calculated') && !$columnExists('mod_sms_segments', 'last_calculated_at')) {
        $execSql("ALTER TABLE `mod_sms_segments` CHANGE `last_calculated` `last_calculated_at` TIMESTAMP NULL", "Rename last_calculated to last_calculated_at in mod_sms_segments");
    }

    // 52. Tags
    if (!$tableExists('mod_sms_tags')) {
        $execSql("
            CREATE TABLE `mod_sms_tags` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(50) NOT NULL,
                `color` VARCHAR(7) DEFAULT '#667eea',
                `description` VARCHAR(255),
                `contact_count` INT UNSIGNED DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_client_tag` (`client_id`, `name`),
                INDEX `idx_client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_tags");
    }

    // 53. Contact-Tag junction
    if (!$tableExists('mod_sms_contact_tags')) {
        $execSql("
            CREATE TABLE `mod_sms_contact_tags` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `contact_id` INT UNSIGNED NOT NULL,
                `tag_id` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_contact_tag` (`contact_id`, `tag_id`),
                INDEX `idx_tag_id` (`tag_id`),
                INDEX `idx_contact_id` (`contact_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_contact_tags");
    }

    // 54. Link tracking
    if (!$tableExists('mod_sms_tracking_links')) {
        $execSql("
            CREATE TABLE `mod_sms_tracking_links` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `campaign_id` INT UNSIGNED,
                `message_id` INT UNSIGNED,
                `original_url` TEXT NOT NULL,
                `short_code` VARCHAR(20) NOT NULL,
                `click_count` INT UNSIGNED DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_campaign_id` (`campaign_id`),
                UNIQUE KEY `unique_short_code` (`short_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_tracking_links");
    } else {
        if (!$columnExists('mod_sms_tracking_links', 'message_id')) {
            $execSql("ALTER TABLE `mod_sms_tracking_links` ADD COLUMN `message_id` INT UNSIGNED AFTER `campaign_id`", "Add message_id to mod_sms_tracking_links");
        }
    }

    // 52. Link clicks
    if (!$tableExists('mod_sms_link_clicks')) {
        $execSql("
            CREATE TABLE `mod_sms_link_clicks` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `link_id` INT UNSIGNED NOT NULL,
                `contact_id` INT UNSIGNED,
                `phone` VARCHAR(30),
                `message_id` INT UNSIGNED,
                `ip_address` VARCHAR(45),
                `user_agent` TEXT,
                `country` VARCHAR(5),
                `device` VARCHAR(50),
                `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_link_id` (`link_id`),
                INDEX `idx_contact_id` (`contact_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_link_clicks");
    } else {
        if (!$columnExists('mod_sms_link_clicks', 'phone')) {
            $execSql("ALTER TABLE `mod_sms_link_clicks` ADD COLUMN `phone` VARCHAR(30) AFTER `contact_id`", "Add phone to mod_sms_link_clicks");
        }
        if (!$columnExists('mod_sms_link_clicks', 'country')) {
            $execSql("ALTER TABLE `mod_sms_link_clicks` ADD COLUMN `country` VARCHAR(5) AFTER `user_agent`", "Add country to mod_sms_link_clicks");
        }
        if (!$columnExists('mod_sms_link_clicks', 'device')) {
            $execSql("ALTER TABLE `mod_sms_link_clicks` ADD COLUMN `device` VARCHAR(50) AFTER `country`", "Add device to mod_sms_link_clicks");
        }
        if (!$columnExists('mod_sms_link_clicks', 'clicked_at')) {
            $execSql("ALTER TABLE `mod_sms_link_clicks` ADD COLUMN `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `device`", "Add clicked_at to mod_sms_link_clicks");
        }
    }

    // 53. Drip campaigns
    if (!$tableExists('mod_sms_drip_campaigns')) {
        $execSql("
            CREATE TABLE `mod_sms_drip_campaigns` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `channel` VARCHAR(20) DEFAULT 'sms',
                `trigger_type` VARCHAR(50),
                `trigger_config` TEXT,
                `trigger_group_id` INT UNSIGNED,
                `status` VARCHAR(20) DEFAULT 'draft',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_drip_campaigns");
    } else {
        if (!$columnExists('mod_sms_drip_campaigns', 'channel')) {
            $execSql("ALTER TABLE `mod_sms_drip_campaigns` ADD COLUMN `channel` VARCHAR(20) DEFAULT 'sms' AFTER `description`", "Add channel to mod_sms_drip_campaigns");
        }
        if (!$columnExists('mod_sms_drip_campaigns', 'trigger_group_id')) {
            $execSql("ALTER TABLE `mod_sms_drip_campaigns` ADD COLUMN `trigger_group_id` INT UNSIGNED AFTER `trigger_config`", "Add trigger_group_id to mod_sms_drip_campaigns");
        }
    }

    // 54. Drip campaign steps
    if (!$tableExists('mod_sms_drip_steps')) {
        $execSql("
            CREATE TABLE `mod_sms_drip_steps` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `drip_campaign_id` INT UNSIGNED NOT NULL,
                `step_order` INT NOT NULL,
                `delay_value` INT DEFAULT 0,
                `delay_unit` VARCHAR(10) DEFAULT 'days',
                `message` TEXT NOT NULL,
                `sender_id` VARCHAR(50),
                `gateway_id` INT UNSIGNED,
                `condition_type` VARCHAR(50),
                `condition_config` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_drip_campaign_id` (`drip_campaign_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_drip_steps");
    } else {
        if ($columnExists('mod_sms_drip_steps', 'drip_id') && !$columnExists('mod_sms_drip_steps', 'drip_campaign_id')) {
            $execSql("ALTER TABLE `mod_sms_drip_steps` CHANGE COLUMN `drip_id` `drip_campaign_id` INT UNSIGNED NOT NULL", "Rename drip_id to drip_campaign_id in mod_sms_drip_steps");
        }
        if (!$columnExists('mod_sms_drip_steps', 'gateway_id')) {
            $execSql("ALTER TABLE `mod_sms_drip_steps` ADD COLUMN `gateway_id` INT UNSIGNED AFTER `sender_id`", "Add gateway_id to mod_sms_drip_steps");
        }
    }

    // 55. Drip subscribers
    if (!$tableExists('mod_sms_drip_subscribers')) {
        $execSql("
            CREATE TABLE `mod_sms_drip_subscribers` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `drip_campaign_id` INT UNSIGNED NOT NULL,
                `contact_id` INT UNSIGNED,
                `phone` VARCHAR(30) NOT NULL,
                `current_step` INT DEFAULT 0,
                `status` VARCHAR(20) DEFAULT 'active',
                `next_send_at` TIMESTAMP NULL,
                `completed_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_drip_campaign_id` (`drip_campaign_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_next_send` (`status`, `next_send_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_drip_subscribers");
    } else {
        if ($columnExists('mod_sms_drip_subscribers', 'drip_id') && !$columnExists('mod_sms_drip_subscribers', 'drip_campaign_id')) {
            $execSql("ALTER TABLE `mod_sms_drip_subscribers` CHANGE COLUMN `drip_id` `drip_campaign_id` INT UNSIGNED NOT NULL", "Rename drip_id to drip_campaign_id in mod_sms_drip_subscribers");
        }
        if ($columnExists('mod_sms_drip_subscribers', 'next_step_at') && !$columnExists('mod_sms_drip_subscribers', 'next_send_at')) {
            $execSql("ALTER TABLE `mod_sms_drip_subscribers` CHANGE COLUMN `next_step_at` `next_send_at` TIMESTAMP NULL", "Rename next_step_at to next_send_at in mod_sms_drip_subscribers");
        }
    }

    // 56. Campaign A/B tests
    if (!$tableExists('mod_sms_campaign_ab_tests')) {
        $execSql("
            CREATE TABLE `mod_sms_campaign_ab_tests` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `campaign_id` INT UNSIGNED NOT NULL,
                `variant` CHAR(1) NOT NULL,
                `message` TEXT NOT NULL,
                `sender_id` VARCHAR(50),
                `percentage` INT DEFAULT 50,
                `sent_count` INT UNSIGNED DEFAULT 0,
                `delivered_count` INT UNSIGNED DEFAULT 0,
                `clicked_count` INT UNSIGNED DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_campaign_id` (`campaign_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_campaign_ab_tests");
    } else {
        if (!$columnExists('mod_sms_campaign_ab_tests', 'updated_at')) {
            $execSql("ALTER TABLE `mod_sms_campaign_ab_tests` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`", "Add updated_at to mod_sms_campaign_ab_tests");
        }
    }

    // 57. Recurring campaign log
    if (!$tableExists('mod_sms_recurring_log')) {
        $execSql("
            CREATE TABLE `mod_sms_recurring_log` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `campaign_id` INT UNSIGNED NOT NULL,
                `run_number` INT UNSIGNED DEFAULT 1,
                `run_at` TIMESTAMP NOT NULL,
                `started_at` TIMESTAMP NULL,
                `completed_at` TIMESTAMP NULL,
                `recipients` INT UNSIGNED DEFAULT 0,
                `sent` INT UNSIGNED DEFAULT 0,
                `failed` INT UNSIGNED DEFAULT 0,
                `status` VARCHAR(20) DEFAULT 'running',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_campaign_id` (`campaign_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_recurring_log");
    } else {
        if (!$columnExists('mod_sms_recurring_log', 'run_number')) {
            $execSql("ALTER TABLE `mod_sms_recurring_log` ADD COLUMN `run_number` INT UNSIGNED DEFAULT 1 AFTER `campaign_id`", "Add run_number to mod_sms_recurring_log");
        }
        if (!$columnExists('mod_sms_recurring_log', 'started_at')) {
            $execSql("ALTER TABLE `mod_sms_recurring_log` ADD COLUMN `started_at` TIMESTAMP NULL AFTER `run_at`", "Add started_at to mod_sms_recurring_log");
        }
        if (!$columnExists('mod_sms_recurring_log', 'completed_at')) {
            $execSql("ALTER TABLE `mod_sms_recurring_log` ADD COLUMN `completed_at` TIMESTAMP NULL AFTER `started_at`", "Add completed_at to mod_sms_recurring_log");
        }
        if (!$columnExists('mod_sms_recurring_log', 'status')) {
            $execSql("ALTER TABLE `mod_sms_recurring_log` ADD COLUMN `status` VARCHAR(20) DEFAULT 'running' AFTER `failed`", "Add status to mod_sms_recurring_log");
        }
    }

    // 50. WhatsApp notification mapping table
    if (!$tableExists('mod_sms_wa_notification_map')) {
        $execSql("
            CREATE TABLE `mod_sms_wa_notification_map` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `notification_type` VARCHAR(50) NOT NULL,
                `wa_template_name` VARCHAR(100) NOT NULL,
                `gateway_id` INT UNSIGNED,
                `param_mapping` TEXT NOT NULL,
                `status` VARCHAR(20) DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_notification_type` (`notification_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_wa_notification_map");
    }

    // Add WhatsApp opt-in columns to mod_sms_settings
    if ($tableExists('mod_sms_settings')) {
        if (!$columnExists('mod_sms_settings', 'accept_whatsapp')) {
            $execSql("ALTER TABLE `mod_sms_settings` ADD COLUMN `accept_whatsapp` TINYINT(1) DEFAULT 1 AFTER `accept_marketing_sms`", "Add accept_whatsapp to mod_sms_settings");
        }
        if (!$columnExists('mod_sms_settings', 'whatsapp_number')) {
            $execSql("ALTER TABLE `mod_sms_settings` ADD COLUMN `whatsapp_number` VARCHAR(30) AFTER `accept_whatsapp`", "Add whatsapp_number to mod_sms_settings");
        }
    }

    // Add wa_enabled flag to mod_sms_notification_templates
    if ($tableExists('mod_sms_notification_templates')) {
        if (!$columnExists('mod_sms_notification_templates', 'wa_enabled')) {
            $execSql("ALTER TABLE `mod_sms_notification_templates` ADD COLUMN `wa_enabled` TINYINT(1) DEFAULT 0 AFTER `status`", "Add wa_enabled to mod_sms_notification_templates");
        }
    }

    // 45. Telegram sessions (chat_id → client mapping)
    if (!$tableExists('mod_sms_telegram_sessions')) {
        $execSql("
            CREATE TABLE `mod_sms_telegram_sessions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `chat_id` BIGINT NOT NULL,
                `client_id` INT UNSIGNED DEFAULT NULL,
                `gateway_id` INT UNSIGNED DEFAULT NULL,
                `username` VARCHAR(100) DEFAULT NULL,
                `first_name` VARCHAR(100) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_chat_gateway` (`chat_id`, `gateway_id`),
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_gateway_id` (`gateway_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_telegram_sessions");
    }

    // 46b. Messenger sessions (PSID → profile mapping)
    if (!$tableExists('mod_sms_messenger_sessions')) {
        $execSql("
            CREATE TABLE `mod_sms_messenger_sessions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `psid` VARCHAR(50) NOT NULL,
                `client_id` INT UNSIGNED DEFAULT NULL,
                `gateway_id` INT UNSIGNED DEFAULT NULL,
                `name` VARCHAR(200) DEFAULT NULL,
                `profile_pic` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_psid_gateway` (`psid`, `gateway_id`),
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_gateway_id` (`gateway_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_messenger_sessions");
    }

    // 46. AI Chatbot configuration
    if (!$tableExists('mod_sms_chatbot_config')) {
        $execSql("
            CREATE TABLE `mod_sms_chatbot_config` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT UNSIGNED DEFAULT NULL,
                `gateway_id` INT UNSIGNED DEFAULT NULL,
                `enabled` TINYINT(1) DEFAULT 0,
                `provider` VARCHAR(20) DEFAULT 'claude',
                `model` VARCHAR(50) DEFAULT NULL,
                `system_prompt` TEXT,
                `max_tokens` INT DEFAULT 300,
                `temperature` DECIMAL(3,2) DEFAULT 0.70,
                `channels` VARCHAR(100) DEFAULT 'whatsapp,telegram',
                `business_hours` VARCHAR(500) DEFAULT NULL,
                `fallback_message` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_client_gateway` (`client_id`, `gateway_id`),
                INDEX `idx_client_id` (`client_id`),
                INDEX `idx_enabled` (`enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", "Create mod_sms_chatbot_config");
    }

    // Log creation result
    if (empty($errors)) {
        logActivity('SMS Suite: All database tables created/verified successfully');
    } else {
        logActivity('SMS Suite: Table creation completed with ' . count($errors) . ' warnings');
    }

    return $errors;
}

/**
 * Create all database tables
 */
function sms_suite_create_tables()
{
    $schema = Capsule::schema();

    // Module settings per client
    if (!$schema->hasTable('mod_sms_settings')) {
        $schema->create('mod_sms_settings', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->unique();
            $table->string('billing_mode', 20)->default('per_segment');
            $table->unsignedInteger('default_gateway_id')->nullable();
            $table->string('default_sender_id', 50)->nullable();
            $table->string('webhook_url', 500)->nullable();
            $table->boolean('api_enabled')->default(true);
            $table->boolean('accept_sms')->default(true); // Client opted in for SMS notifications
            $table->boolean('accept_marketing_sms')->default(false); // Marketing SMS opt-in
            $table->text('enabled_notifications')->nullable(); // JSON array of enabled notification types
            $table->timestamps();
            $table->index('client_id');
        });
    }

    // Add SMS notification columns if table exists but columns don't
    if ($schema->hasTable('mod_sms_settings')) {
        if (!$schema->hasColumn('mod_sms_settings', 'accept_sms')) {
            $schema->table('mod_sms_settings', function ($table) {
                $table->boolean('accept_sms')->default(true)->after('api_enabled');
            });
        }
        if (!$schema->hasColumn('mod_sms_settings', 'accept_marketing_sms')) {
            $schema->table('mod_sms_settings', function ($table) {
                $table->boolean('accept_marketing_sms')->default(false)->after('accept_sms');
            });
        }
        if (!$schema->hasColumn('mod_sms_settings', 'enabled_notifications')) {
            $schema->table('mod_sms_settings', function ($table) {
                $table->text('enabled_notifications')->nullable()->after('accept_marketing_sms');
            });
        }
        // Client-specific sender ID and gateway assignment
        if (!$schema->hasColumn('mod_sms_settings', 'assigned_sender_id')) {
            $schema->table('mod_sms_settings', function ($table) {
                $table->string('assigned_sender_id', 50)->nullable()->after('default_sender_id');
            });
        }
        if (!$schema->hasColumn('mod_sms_settings', 'assigned_gateway_id')) {
            $schema->table('mod_sms_settings', function ($table) {
                $table->unsignedInteger('assigned_gateway_id')->nullable()->after('default_gateway_id');
            });
        }
        if (!$schema->hasColumn('mod_sms_settings', 'monthly_limit')) {
            $schema->table('mod_sms_settings', function ($table) {
                $table->unsignedInteger('monthly_limit')->nullable()->after('assigned_gateway_id');
            });
        }
        if (!$schema->hasColumn('mod_sms_settings', 'monthly_used')) {
            $schema->table('mod_sms_settings', function ($table) {
                $table->unsignedInteger('monthly_used')->default(0)->after('monthly_limit');
            });
        }
        // Two-factor authentication
        if (!$schema->hasColumn('mod_sms_settings', 'two_factor_enabled')) {
            $schema->table('mod_sms_settings', function ($table) {
                $table->boolean('two_factor_enabled')->default(false)->after('accept_marketing_sms');
            });
        }
    }

    // Gateways
    if (!$schema->hasTable('mod_sms_gateways')) {
        $schema->create('mod_sms_gateways', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('type', 50);
            $table->string('channel', 20)->default('sms'); // sms, whatsapp, both
            $table->boolean('status')->default(true);
            $table->text('credentials')->nullable(); // Encrypted JSON
            $table->text('settings')->nullable(); // JSON config
            $table->integer('quota_value')->default(0);
            $table->string('quota_unit', 20)->default('minute');
            $table->string('success_keyword', 100)->nullable();
            $table->decimal('balance', 16, 4)->nullable();
            $table->string('webhook_token', 64)->nullable();
            $table->timestamps();
            $table->index('type');
            $table->index('status');
        });
    }

    // Gateway country pricing
    if (!$schema->hasTable('mod_sms_gateway_countries')) {
        $schema->create('mod_sms_gateway_countries', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('gateway_id');
            $table->string('country_code', 5);
            $table->string('country_name', 100);
            $table->decimal('sms_rate', 10, 4)->default(0);
            $table->decimal('whatsapp_rate', 10, 4)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index('gateway_id');
            $table->index('country_code');
            $table->unique(['gateway_id', 'country_code']);
        });
    }

    // Sender IDs
    if (!$schema->hasTable('mod_sms_sender_ids')) {
        $schema->create('mod_sms_sender_ids', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('sender_id', 50);
            $table->string('type', 20)->default('alphanumeric'); // alphanumeric, numeric
            $table->string('status', 20)->default('pending'); // pending, active, rejected, expired
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('currency_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->text('gateway_ids')->nullable(); // JSON array
            $table->date('validity_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('client_id');
            $table->index('status');
            $table->unique(['client_id', 'sender_id']);
        });
    }

    // Sender ID Plans
    if (!$schema->hasTable('mod_sms_sender_id_plans')) {
        $schema->create('mod_sms_sender_id_plans', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('currency_id');
            $table->string('billing_cycle', 20); // monthly, yearly, onetime
            $table->integer('validity_days')->default(30);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    // Contact Groups
    if (!$schema->hasTable('mod_sms_contact_groups')) {
        $schema->create('mod_sms_contact_groups', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('default_sender_id', 50)->nullable();
            $table->text('welcome_sms')->nullable();
            $table->text('unsubscribe_sms')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('contact_count')->default(0);
            $table->timestamps();
            $table->index('client_id');
        });
    }

    // Contact Group Fields
    if (!$schema->hasTable('mod_sms_contact_group_fields')) {
        $schema->create('mod_sms_contact_group_fields', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('group_id');
            $table->string('label', 100);
            $table->string('tag', 50);
            $table->string('type', 20)->default('text'); // text, email, date, select
            $table->string('default_value', 255)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('group_id');
        });
    }

    // Contacts
    if (!$schema->hasTable('mod_sms_contacts')) {
        $schema->create('mod_sms_contacts', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('group_id')->nullable();
            $table->string('phone', 30);
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('status', 20)->default('active'); // active, unsubscribed
            $table->text('custom_data')->nullable(); // JSON
            $table->timestamps();
            $table->index(['client_id', 'phone']);
            $table->index(['group_id', 'status']);
        });
    }

    // Campaigns
    if (!$schema->hasTable('mod_sms_campaigns')) {
        $schema->create('mod_sms_campaigns', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('name', 200);
            $table->string('channel', 20)->default('sms'); // sms, whatsapp
            $table->unsignedInteger('gateway_id')->nullable();
            $table->string('sender_id', 50)->nullable();
            $table->text('message');
            $table->text('media_url')->nullable();
            $table->string('recipient_type', 30)->default('group'); // group, list, all
            $table->unsignedInteger('recipient_group_id')->nullable();
            $table->text('recipient_list')->nullable();
            $table->string('status', 20)->default('draft'); // draft, scheduled, queued, sending, paused, completed, failed, cancelled
            $table->dateTime('schedule_time')->nullable();
            $table->string('schedule_type', 20)->default('onetime'); // onetime, recurring
            $table->integer('frequency_amount')->nullable();
            $table->string('frequency_unit', 10)->nullable(); // minute, hour, day, week, month
            $table->dateTime('recurring_end')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('cost_total', 16, 4)->default(0);
            $table->string('batch_id', 50)->nullable();
            $table->unsignedInteger('batch_size')->default(100);
            $table->unsignedInteger('batch_delay')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'schedule_time']);
            $table->index(['client_id', 'status']);
        });
    }

    // Campaign Lists (junction table)
    if (!$schema->hasTable('mod_sms_campaign_lists')) {
        $schema->create('mod_sms_campaign_lists', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('campaign_id');
            $table->unsignedInteger('group_id');
            $table->timestamps();
            $table->index('campaign_id');
            $table->unique(['campaign_id', 'group_id']);
        });
    }

    // Campaign Recipients
    if (!$schema->hasTable('mod_sms_campaign_recipients')) {
        $schema->create('mod_sms_campaign_recipients', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('campaign_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->string('phone', 30);
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->unsignedInteger('message_id')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status']);
        });
    }

    // Messages (main log)
    if (!$schema->hasTable('mod_sms_messages')) {
        $schema->create('mod_sms_messages', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('campaign_id')->nullable();
            $table->unsignedInteger('automation_id')->nullable();
            $table->unsignedInteger('gateway_id')->nullable();
            $table->string('channel', 20)->default('sms'); // sms, whatsapp
            $table->string('direction', 10)->default('outbound'); // outbound, inbound
            $table->string('sender_id', 50)->nullable();
            $table->string('to_number', 30);
            $table->text('message');
            $table->text('media_url')->nullable();
            $table->string('encoding', 10)->default('gsm7'); // gsm7, gsm7ex, ucs2
            $table->unsignedTinyInteger('segments')->default(1);
            $table->unsignedTinyInteger('units')->default(1);
            $table->decimal('cost', 10, 4)->default(0);
            $table->string('status', 20)->default('queued'); // queued, sending, sent, delivered, failed, rejected, undelivered, expired
            $table->string('provider_message_id', 100)->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('api_key_id')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
            $table->index('status');
            $table->index('provider_message_id');
        });
    }

    // Webhook Inbox
    if (!$schema->hasTable('mod_sms_webhooks_inbox')) {
        $schema->create('mod_sms_webhooks_inbox', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('gateway_id')->nullable();
            $table->string('gateway_type', 50);
            $table->text('payload');
            $table->mediumText('raw_payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['processed', 'created_at']);
        });
    }

    // Templates
    if (!$schema->hasTable('mod_sms_templates')) {
        $schema->create('mod_sms_templates', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->nullable(); // NULL = system template
            $table->string('name', 100);
            $table->string('channel', 20)->default('sms');
            $table->text('message');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index('client_id');
        });
    }

    // API Keys
    if (!$schema->hasTable('mod_sms_api_keys')) {
        $schema->create('mod_sms_api_keys', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('name', 100);
            $table->string('key_prefix', 16);
            $table->string('key_hash', 255);
            $table->text('scopes')->nullable(); // JSON
            $table->integer('rate_limit')->default(100);
            $table->integer('rate_window')->default(60); // seconds
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index('client_id');
            $table->index('key_prefix');
        });
    }

    // API Rate Limits
    if (!$schema->hasTable('mod_sms_api_rate_limits')) {
        $schema->create('mod_sms_api_rate_limits', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('api_key_id');
            $table->dateTime('window_start');
            $table->integer('request_count')->default(0);
            $table->index(['api_key_id', 'window_start']);
        });
    }

    // API Audit Log
    if (!$schema->hasTable('mod_sms_api_audit')) {
        $schema->create('mod_sms_api_audit', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('api_key_id')->nullable();
            $table->unsignedInteger('client_id');
            $table->string('endpoint', 100);
            $table->string('method', 10);
            $table->text('request_data')->nullable(); // Redacted JSON
            $table->integer('response_code');
            $table->string('ip_address', 45);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['client_id', 'created_at']);
        });
    }

    // Wallet
    if (!$schema->hasTable('mod_sms_wallet')) {
        $schema->create('mod_sms_wallet', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->unique();
            $table->decimal('balance', 16, 4)->default(0);
            $table->unsignedInteger('currency_id')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->index('client_id');
        });
    }

    // Wallet Transactions
    if (!$schema->hasTable('mod_sms_wallet_transactions')) {
        $schema->create('mod_sms_wallet_transactions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('type', 20); // topup, deduction, refund, adjustment
            $table->decimal('amount', 16, 4);
            $table->decimal('balance_before', 16, 4);
            $table->decimal('balance_after', 16, 4);
            $table->string('reference_type', 50)->nullable(); // invoice, message, campaign
            $table->unsignedInteger('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['client_id', 'created_at']);
        });
    }

    // Plan Credits
    if (!$schema->hasTable('mod_sms_plan_credits')) {
        $schema->create('mod_sms_plan_credits', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('service_id')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('remaining')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('client_id');
            $table->index('expires_at');
        });
    }

    // Blacklist
    if (!$schema->hasTable('mod_sms_blacklist')) {
        $schema->create('mod_sms_blacklist', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->nullable(); // NULL = global
            $table->string('phone', 30);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['client_id', 'phone']);
        });
    }

    // Opt-outs (global)
    if (!$schema->hasTable('mod_sms_optouts')) {
        $schema->create('mod_sms_optouts', function ($table) {
            $table->increments('id');
            $table->string('phone', 30)->unique();
            $table->string('channel', 20)->default('all'); // sms, whatsapp, all
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    // Automation Triggers
    if (!$schema->hasTable('mod_sms_automation_triggers')) {
        $schema->create('mod_sms_automation_triggers', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('hook_name', 100);
            $table->string('event_type', 50);
            $table->unsignedInteger('template_id')->nullable();
            $table->unsignedInteger('gateway_id')->nullable();
            $table->string('sender_id', 50)->nullable();
            $table->text('conditions')->nullable(); // JSON
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index('hook_name');
            $table->index('status');
        });
    }

    // Countries (reference)
    if (!$schema->hasTable('mod_sms_countries')) {
        $schema->create('mod_sms_countries', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('iso_code', 2);
            $table->string('phone_code', 10);
            $table->boolean('status')->default(true);
        });
    }

    // Cron status tracking
    if (!$schema->hasTable('mod_sms_cron_status')) {
        $schema->create('mod_sms_cron_status', function ($table) {
            $table->increments('id');
            $table->string('task', 50)->unique();
            $table->dateTime('last_run')->nullable();
            $table->dateTime('next_run')->nullable();
            $table->boolean('is_running')->default(false);
            $table->integer('pid')->nullable();
            $table->timestamps();
        });
    }

    // Automations (for AutomationService)
    if (!$schema->hasTable('mod_sms_automations')) {
        $schema->create('mod_sms_automations', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('trigger_type', 50);
            $table->text('trigger_config')->nullable();
            $table->text('message_template')->nullable();
            $table->string('sender_id', 50)->nullable();
            $table->unsignedInteger('gateway_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamp('last_run')->nullable();
            $table->timestamps();
            $table->index('trigger_type');
            $table->index('status');
        });
    }

    // Automation Logs
    if (!$schema->hasTable('mod_sms_automation_logs')) {
        $schema->create('mod_sms_automation_logs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('automation_id');
            $table->text('trigger_data')->nullable();
            $table->unsignedInteger('message_id')->nullable();
            $table->string('status', 20)->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('automation_id');
            $table->index('created_at');
        });
    }

    // Pending Top-ups
    if (!$schema->hasTable('mod_sms_pending_topups')) {
        $schema->create('mod_sms_pending_topups', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('invoice_id');
            $table->decimal('amount', 16, 4);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });
    }

    // Rate Limits (for API)
    if (!$schema->hasTable('mod_sms_rate_limits')) {
        $schema->create('mod_sms_rate_limits', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('key_id');
            $table->string('window', 20);
            $table->integer('requests')->default(0);
            $table->index(['key_id', 'window']);
        });
    }

    // ============ WhatsApp Business API Tables ============

    // WhatsApp Templates
    if (!$schema->hasTable('mod_sms_whatsapp_templates')) {
        $schema->create('mod_sms_whatsapp_templates', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('language', 10)->default('en');
            $table->string('category', 50)->default('UTILITY'); // UTILITY, MARKETING, AUTHENTICATION
            $table->text('content');
            $table->string('header_type', 20)->nullable(); // text, image, video, document
            $table->text('header_content')->nullable();
            $table->string('footer', 60)->nullable();
            $table->text('buttons')->nullable(); // JSON array
            $table->text('example_params')->nullable(); // JSON
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->string('provider_template_id', 100)->nullable();
            $table->timestamps();
            $table->index('name');
            $table->index('status');
        });
    }

    // WhatsApp Chatbox (Conversations)
    if (!$schema->hasTable('mod_sms_chatbox')) {
        $schema->create('mod_sms_chatbox', function ($table) {
            $table->increments('id');
            $table->string('phone', 30);
            $table->string('contact_name', 100)->nullable();
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('gateway_id')->nullable();
            $table->string('status', 20)->default('open'); // open, closed, expired
            $table->text('last_message')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->unsignedInteger('assigned_admin_id')->nullable();
            $table->text('tags')->nullable(); // JSON
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('phone');
            $table->index('client_id');
            $table->index('status');
            $table->index('last_message_at');
        });
    }

    // Chatbox Messages (junction)
    if (!$schema->hasTable('mod_sms_chatbox_messages')) {
        $schema->create('mod_sms_chatbox_messages', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('chatbox_id');
            $table->unsignedInteger('message_id');
            $table->string('direction', 10)->default('outbound');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['chatbox_id', 'created_at']);
        });
    }

    // Auto-replies
    if (!$schema->hasTable('mod_sms_auto_replies')) {
        $schema->create('mod_sms_auto_replies', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('channel', 20)->default('whatsapp');
            $table->text('keywords')->nullable(); // JSON array
            $table->string('match_type', 20)->default('contains'); // exact, contains, starts_with
            $table->text('reply_message');
            $table->unsignedInteger('template_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index('status');
        });
    }

    // ============ Advanced Campaign Tables ============

    // Campaign A/B Testing
    if (!$schema->hasTable('mod_sms_campaign_ab_tests')) {
        $schema->create('mod_sms_campaign_ab_tests', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('campaign_id');
            $table->char('variant', 1); // A, B, C, D
            $table->text('message');
            $table->string('sender_id', 50)->nullable();
            $table->unsignedInteger('percentage')->default(50);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->timestamps();
            $table->index('campaign_id');
        });
    }

    // Drip Campaigns / Sequences
    if (!$schema->hasTable('mod_sms_drip_campaigns')) {
        $schema->create('mod_sms_drip_campaigns', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('name', 200);
            $table->string('channel', 20)->default('sms');
            $table->unsignedInteger('trigger_group_id')->nullable(); // Trigger when contact added to group
            $table->string('trigger_type', 50)->default('group_join'); // group_join, tag_added, date_field
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index('client_id');
            $table->index('status');
        });
    }

    // Drip Campaign Steps
    if (!$schema->hasTable('mod_sms_drip_steps')) {
        $schema->create('mod_sms_drip_steps', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('drip_campaign_id');
            $table->unsignedInteger('step_order')->default(1);
            $table->text('message');
            $table->unsignedInteger('delay_value')->default(1);
            $table->string('delay_unit', 10)->default('day'); // minute, hour, day, week
            $table->string('sender_id', 50)->nullable();
            $table->unsignedInteger('gateway_id')->nullable();
            $table->timestamps();
            $table->index(['drip_campaign_id', 'step_order']);
        });
    }

    // Drip Campaign Subscribers
    if (!$schema->hasTable('mod_sms_drip_subscribers')) {
        $schema->create('mod_sms_drip_subscribers', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('drip_campaign_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('current_step')->default(0);
            $table->dateTime('next_send_at')->nullable();
            $table->string('status', 20)->default('active'); // active, completed, unsubscribed, paused
            $table->timestamps();
            $table->index(['drip_campaign_id', 'status', 'next_send_at'], 'idx_drip_sub_campaign_status');
        });
    }

    // Link Tracking
    if (!$schema->hasTable('mod_sms_tracking_links')) {
        $schema->create('mod_sms_tracking_links', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('campaign_id')->nullable();
            $table->unsignedInteger('message_id')->nullable();
            $table->string('short_code', 20)->unique();
            $table->text('original_url');
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();
            $table->index('short_code');
            $table->index('campaign_id');
        });
    }

    // Link Click Log
    if (!$schema->hasTable('mod_sms_link_clicks')) {
        $schema->create('mod_sms_link_clicks', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('link_id');
            $table->string('phone', 30)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('device', 50)->nullable();
            $table->timestamp('clicked_at')->useCurrent();
            $table->index(['link_id', 'clicked_at']);
        });
    }

    // Contact Segments
    if (!$schema->hasTable('mod_sms_segments')) {
        $schema->create('mod_sms_segments', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->text('conditions')->nullable(); // JSON - filter conditions
            $table->string('match_type', 10)->default('all'); // all, any
            $table->unsignedInteger('contact_count')->default(0);
            $table->dateTime('last_calculated_at')->nullable();
            $table->timestamps();
            $table->index('client_id');
        });
    }

    // Segment Conditions
    if (!$schema->hasTable('mod_sms_segment_conditions')) {
        $schema->create('mod_sms_segment_conditions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('segment_id');
            $table->string('field', 50);
            $table->string('operator', 20); // equals, not_equals, contains, starts_with, ends_with, greater_than, less_than, between, is_empty, is_not_empty
            $table->text('value')->nullable();
            $table->string('logic', 5)->default('AND'); // AND, OR
            $table->timestamps();
            $table->index('segment_id');
        });
    }

    // Tags
    if (!$schema->hasTable('mod_sms_tags')) {
        $schema->create('mod_sms_tags', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('name', 50);
            $table->string('color', 7)->default('#667eea');
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('contact_count')->default(0);
            $table->timestamps();
            $table->unique(['client_id', 'name']);
            $table->index('client_id');
        });
    }

    // Contact Tags
    if (!$schema->hasTable('mod_sms_contact_tags')) {
        $schema->create('mod_sms_contact_tags', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('tag_id');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['contact_id', 'tag_id']);
            $table->index('tag_id');
            $table->index('contact_id');
        });
    }

    // Recurring Campaign Log
    if (!$schema->hasTable('mod_sms_recurring_log')) {
        $schema->create('mod_sms_recurring_log', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('campaign_id');
            $table->unsignedInteger('run_number')->default(1);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20)->default('completed');
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->index('campaign_id');
        });
    }

    // Scheduled Messages (individual)
    if (!$schema->hasTable('mod_sms_scheduled')) {
        $schema->create('mod_sms_scheduled', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('to_number', 30);
            $table->text('message');
            $table->string('channel', 20)->default('sms');
            $table->string('sender_id', 50)->nullable();
            $table->unsignedInteger('gateway_id')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('timezone', 50)->default('UTC');
            $table->string('status', 20)->default('pending'); // pending, sent, failed, cancelled
            $table->unsignedInteger('message_id')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });
    }

    // ============ WHMCS Notification Tables ============

    // SMS Notification Templates (linked to WHMCS email templates)
    if (!$schema->hasTable('mod_sms_notification_templates')) {
        $schema->create('mod_sms_notification_templates', function ($table) {
            $table->increments('id');
            $table->string('notification_type', 50)->unique(); // invoice_created, order_confirmation, etc
            $table->string('name', 100);
            $table->text('message');
            $table->string('category', 50)->default('other'); // client, order, invoice, domain, service, ticket
            $table->string('status', 20)->default('inactive'); // active, inactive
            $table->timestamps();
            $table->index('notification_type');
            $table->index('status');
        });
    }

    // Admin SMS Notifications
    if (!$schema->hasTable('mod_sms_admin_notifications')) {
        $schema->create('mod_sms_admin_notifications', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('admin_id');
            $table->string('event', 50); // new_order, new_ticket, client_login, etc
            $table->string('phone', 30);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['admin_id', 'event']);
            $table->unique(['admin_id', 'event']);
        });
    }

    // ============ Verification Tables ============

    // Verification Tokens
    if (!$schema->hasTable('mod_sms_verification_tokens')) {
        $schema->create('mod_sms_verification_tokens', function ($table) {
            $table->increments('id');
            $table->string('phone', 30);
            $table->string('token', 255); // Hashed token
            $table->string('type', 50); // client_verification, order_verification, two_factor, phone_verification
            $table->unsignedInteger('related_id')->nullable(); // client_id, order_id, etc
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['phone', 'type']);
            $table->index('expires_at');
        });
    }

    // Client Verification Status
    if (!$schema->hasTable('mod_sms_client_verification')) {
        $schema->create('mod_sms_client_verification', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->unique();
            $table->boolean('phone_verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->string('verified_phone', 30)->nullable();
            $table->timestamps();
            $table->index('client_id');
        });
    }

    // Order Verification Status
    if (!$schema->hasTable('mod_sms_order_verification')) {
        $schema->create('mod_sms_order_verification', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->unique();
            $table->boolean('verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->string('verification_type', 20)->default('sms'); // sms, admin_override
            $table->timestamps();
            $table->index('order_id');
        });
    }

    // Custom Verification Message Templates
    if (!$schema->hasTable('mod_sms_verification_templates')) {
        $schema->create('mod_sms_verification_templates', function ($table) {
            $table->increments('id');
            $table->string('type', 50)->unique(); // client_verification, order_verification, two_factor
            $table->text('message');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    // Verification Logs
    if (!$schema->hasTable('mod_sms_verification_logs')) {
        $schema->create('mod_sms_verification_logs', function ($table) {
            $table->increments('id');
            $table->string('phone', 30); // Masked phone
            $table->string('type', 50);
            $table->unsignedInteger('related_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['type', 'created_at']);
        });
    }

    // ============ SMS Credit Packages and Billing Tables ============

    // Admin-managed Sender IDs (global pool available for assignment)
    if (!$schema->hasTable('mod_sms_sender_id_pool')) {
        $schema->create('mod_sms_sender_id_pool', function ($table) {
            $table->increments('id');
            $table->string('sender_id', 50);
            $table->string('type', 20)->default('alphanumeric'); // alphanumeric, numeric, shortcode
            $table->string('network', 20)->default('all'); // all, safaricom, airtel, telkom
            $table->text('description')->nullable();
            $table->unsignedInteger('gateway_id')->nullable(); // Mapped gateway
            $table->text('country_codes')->nullable(); // JSON array of allowed country codes
            $table->decimal('price_setup', 10, 2)->default(0); // One-time setup fee
            $table->decimal('price_monthly', 10, 2)->default(0); // Monthly recurring
            $table->decimal('price_yearly', 10, 2)->default(0); // Yearly recurring
            $table->boolean('requires_approval')->default(true); // Needs telco approval
            $table->boolean('is_shared')->default(false); // Can be used by multiple clients
            $table->string('telco_status', 20)->default('approved'); // approved, pending, rejected
            $table->date('telco_approved_date')->nullable();
            $table->string('telco_reference', 100)->nullable(); // Telco reference number
            $table->string('status', 20)->default('active'); // active, inactive, reserved
            $table->timestamps();
            $table->index('gateway_id');
            $table->index('network');
            $table->index('status');
            $table->index('telco_status');
            $table->unique(['sender_id', 'network']);
        });
    }

    // Add missing columns to existing mod_sms_sender_id_pool (no after() — target columns may not exist)
    if ($schema->hasTable('mod_sms_sender_id_pool')) {
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'network')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->string('network', 20)->default('all');
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'country_codes')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->text('country_codes')->nullable();
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'description')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->text('description')->nullable();
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'price_setup')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->decimal('price_setup', 10, 2)->default(0);
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'price_monthly')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->decimal('price_monthly', 10, 2)->default(0);
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'price_yearly')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->decimal('price_yearly', 10, 2)->default(0);
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'requires_approval')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->boolean('requires_approval')->default(true);
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'is_shared')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->boolean('is_shared')->default(false);
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'telco_status')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->string('telco_status', 20)->default('approved');
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'telco_approved_date')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->date('telco_approved_date')->nullable();
            });
        }
        if (!$schema->hasColumn('mod_sms_sender_id_pool', 'telco_reference')) {
            $schema->table('mod_sms_sender_id_pool', function ($table) {
                $table->string('telco_reference', 100)->nullable();
            });
        }
    }

    // SMS Credit Packages (products for sale)
    if (!$schema->hasTable('mod_sms_credit_packages')) {
        $schema->create('mod_sms_credit_packages', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->integer('credits'); // Number of SMS credits
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('currency_id')->nullable();
            $table->decimal('bonus_credits', 10, 0)->default(0); // Bonus credits included
            $table->integer('validity_days')->default(0); // 0 = never expires
            $table->boolean('popular')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index('status');
        });
    }

    // Client SMS Credit Purchases (linked to WHMCS invoices)
    if (!$schema->hasTable('mod_sms_credit_purchases')) {
        $schema->create('mod_sms_credit_purchases', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('package_id')->nullable();
            $table->unsignedInteger('invoice_id');
            $table->integer('credits_purchased');
            $table->integer('bonus_credits')->default(0);
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending'); // pending, paid, cancelled, refunded
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('credited_at')->nullable(); // When credits were added to balance
            $table->timestamps();
            $table->index(['client_id', 'status']);
            $table->index('invoice_id');
        });
    }

    // Client Sender ID Requests
    if (!$schema->hasTable('mod_sms_sender_id_requests')) {
        $schema->create('mod_sms_sender_id_requests', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('sender_id', 50); // Requested sender ID
            $table->string('type', 20)->default('alphanumeric');
            $table->unsignedInteger('pool_id')->nullable(); // If selecting from pool
            $table->unsignedInteger('gateway_id')->nullable(); // Preferred gateway
            $table->text('business_name')->nullable();
            $table->text('use_case')->nullable(); // How they'll use it
            $table->text('documents')->nullable(); // JSON array of uploaded document paths
            $table->string('billing_cycle', 20)->default('monthly'); // monthly, yearly, onetime
            $table->decimal('setup_fee', 10, 2)->default(0);
            $table->decimal('recurring_fee', 10, 2)->default(0);
            $table->unsignedInteger('invoice_id')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, active, expired
            $table->text('admin_notes')->nullable();
            $table->unsignedInteger('approved_by')->nullable(); // Admin ID
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
            $table->index('invoice_id');
        });
    }

    // Client Sender ID Allocations (active sender IDs assigned to clients)
    if (!$schema->hasTable('mod_sms_client_sender_ids')) {
        $schema->create('mod_sms_client_sender_ids', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('pool_id')->nullable(); // Reference to pool
            $table->unsignedInteger('request_id')->nullable(); // Reference to request
            $table->unsignedInteger('service_id')->nullable(); // WHMCS product service ID
            $table->string('sender_id', 50);
            $table->string('type', 20)->default('alphanumeric'); // alphanumeric, numeric
            $table->unsignedInteger('gateway_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active'); // active, suspended, expired, terminated
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('last_invoice_id')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
            $table->index('service_id');
        });
    }

    // Add columns to existing mod_sms_client_sender_ids table
    if ($schema->hasTable('mod_sms_client_sender_ids')) {
        if (!$schema->hasColumn('mod_sms_client_sender_ids', 'service_id')) {
            $schema->table('mod_sms_client_sender_ids', function ($table) {
                $table->unsignedInteger('service_id')->nullable()->after('request_id');
            });
        }
        if (!$schema->hasColumn('mod_sms_client_sender_ids', 'type')) {
            $schema->table('mod_sms_client_sender_ids', function ($table) {
                $table->string('type', 20)->default('alphanumeric')->after('sender_id');
            });
        }
        if (!$schema->hasColumn('mod_sms_client_sender_ids', 'network')) {
            $schema->table('mod_sms_client_sender_ids', function ($table) {
                $table->string('network', 20)->default('all')->after('type');
            });
        }
    }

    // Credit Allocations - Track credits per package/service (for linking to sender IDs)
    if (!$schema->hasTable('mod_sms_credit_allocations')) {
        $schema->create('mod_sms_credit_allocations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('service_id')->nullable(); // WHMCS hosting service ID
            $table->unsignedInteger('sender_id_ref')->nullable(); // Link to specific sender ID
            $table->integer('total_credits');
            $table->integer('remaining_credits');
            $table->integer('used_credits')->default(0);
            $table->dateTime('expires_at')->nullable();
            $table->string('status', 20)->default('active'); // active, exhausted, expired
            $table->timestamps();
            $table->index(['client_id', 'status']);
            $table->index('service_id');
            $table->index('sender_id_ref');
        });
    }

    // Credit Usage Log - Track per-message credit usage linked to sender ID
    if (!$schema->hasTable('mod_sms_credit_usage')) {
        $schema->create('mod_sms_credit_usage', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('allocation_id')->nullable(); // FK to credit_allocations
            $table->unsignedInteger('sender_id_ref')->nullable(); // Which sender ID was used
            $table->unsignedInteger('message_id')->nullable(); // FK to messages table
            $table->integer('credits_used')->default(1);
            $table->string('destination', 30)->nullable(); // Phone number
            $table->string('network', 20)->nullable(); // safaricom, airtel, telkom
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
            $table->index('allocation_id');
            $table->index('sender_id_ref');
        });
    }

    // Client SMS Credit Balance (separate from wallet for credit-based billing)
    if (!$schema->hasTable('mod_sms_credit_balance')) {
        $schema->create('mod_sms_credit_balance', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->unique();
            $table->integer('balance')->default(0); // Current credit balance
            $table->integer('total_purchased')->default(0);
            $table->integer('total_used')->default(0);
            $table->integer('total_expired')->default(0);
            $table->timestamps();
            $table->index('client_id');
        });
    }

    // Credit Transaction Log
    if (!$schema->hasTable('mod_sms_credit_transactions')) {
        $schema->create('mod_sms_credit_transactions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('type', 20); // purchase, usage, refund, expired, adjustment, bonus
            $table->integer('credits'); // Positive for additions, negative for deductions
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('reference_type', 50)->nullable(); // invoice, message, campaign, admin
            $table->unsignedInteger('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('admin_id')->nullable(); // For admin adjustments
            $table->timestamp('created_at')->useCurrent();
            $table->index(['client_id', 'created_at']);
            $table->index('type');
        });
    }

    // Sender ID Billing History
    if (!$schema->hasTable('mod_sms_sender_id_billing')) {
        $schema->create('mod_sms_sender_id_billing', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('client_sender_id'); // FK to mod_sms_client_sender_ids
            $table->unsignedInteger('invoice_id');
            $table->string('billing_type', 20); // setup, renewal
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending'); // pending, paid, cancelled
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
            $table->index('invoice_id');
        });
    }

    // Add message_type and from_number columns to messages if not exists
    if ($schema->hasTable('mod_sms_messages')) {
        if (!$schema->hasColumn('mod_sms_messages', 'message_type')) {
            $schema->table('mod_sms_messages', function ($table) {
                $table->string('message_type', 20)->default('text')->after('message'); // text, template, image, video, audio, document, interactive
            });
        }
        if (!$schema->hasColumn('mod_sms_messages', 'from_number')) {
            $schema->table('mod_sms_messages', function ($table) {
                $table->string('from_number', 30)->nullable()->after('to_number');
            });
        }
        if (!$schema->hasColumn('mod_sms_messages', 'template_name')) {
            $schema->table('mod_sms_messages', function ($table) {
                $table->string('template_name', 100)->nullable()->after('message_type');
            });
        }
        if (!$schema->hasColumn('mod_sms_messages', 'template_params')) {
            $schema->table('mod_sms_messages', function ($table) {
                $table->text('template_params')->nullable()->after('template_name');
            });
        }
        if (!$schema->hasColumn('mod_sms_messages', 'media_type')) {
            $schema->table('mod_sms_messages', function ($table) {
                $table->string('media_type', 20)->nullable()->after('media_url');
            });
        }
        // Gateway response for debugging
        if (!$schema->hasColumn('mod_sms_messages', 'gateway_response')) {
            $schema->table('mod_sms_messages', function ($table) {
                $table->text('gateway_response')->nullable()->after('error');
            });
        }
        // Sent timestamp
        if (!$schema->hasColumn('mod_sms_messages', 'sent_at')) {
            $schema->table('mod_sms_messages', function ($table) {
                $table->dateTime('sent_at')->nullable()->after('provider_message_id');
            });
        }
    }

    // Add advanced columns to campaigns if not exists
    if ($schema->hasTable('mod_sms_campaigns')) {
        if (!$schema->hasColumn('mod_sms_campaigns', 'recipient_type')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->string('recipient_type', 20)->default('manual')->after('message'); // manual, group, segment, all
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'recipient_group_id')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->unsignedInteger('recipient_group_id')->nullable()->after('recipient_type');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'segment_id')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->unsignedInteger('segment_id')->nullable()->after('recipient_group_id');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'recipient_tag_id')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->unsignedInteger('recipient_tag_id')->nullable()->after('segment_id');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'recipient_list')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->text('recipient_list')->nullable()->after('recipient_tag_id');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'batch_size')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->unsignedInteger('batch_size')->default(100)->after('recipient_list');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'batch_delay')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->unsignedInteger('batch_delay')->default(1)->after('batch_size');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'timezone')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->string('timezone', 50)->default('UTC')->after('schedule_time');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'ab_testing')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->boolean('ab_testing')->default(false)->after('status');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'track_links')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->boolean('track_links')->default(false)->after('ab_testing');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'started_at')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->dateTime('started_at')->nullable()->after('cost_total');
            });
        }
        if (!$schema->hasColumn('mod_sms_campaigns', 'completed_at')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->dateTime('completed_at')->nullable()->after('started_at');
            });
        }
    }

    // Add template columns if not exists
    if ($schema->hasTable('mod_sms_templates')) {
        if (!$schema->hasColumn('mod_sms_templates', 'category')) {
            $schema->table('mod_sms_templates', function ($table) {
                $table->string('category', 50)->default('general')->after('name');
            });
        }
        if (!$schema->hasColumn('mod_sms_templates', 'content')) {
            $schema->table('mod_sms_templates', function ($table) {
                $table->text('content')->nullable()->after('message');
            });
        }
        if (!$schema->hasColumn('mod_sms_templates', 'dlt_template_id')) {
            $schema->table('mod_sms_templates', function ($table) {
                $table->string('dlt_template_id', 50)->nullable()->after('content');
            });
        }
        if (!$schema->hasColumn('mod_sms_templates', 'is_default')) {
            $schema->table('mod_sms_templates', function ($table) {
                $table->boolean('is_default')->default(false)->after('dlt_template_id');
            });
        }
    }

    // Destination Rates (global rate card by country + network)
    if (!$schema->hasTable('mod_sms_destination_rates')) {
        $schema->create('mod_sms_destination_rates', function ($table) {
            $table->increments('id');
            $table->string('country_code', 5);
            $table->string('network', 50)->nullable();
            $table->decimal('sms_rate', 10, 6)->default(0);
            $table->decimal('whatsapp_rate', 10, 6)->default(0);
            $table->unsignedInteger('credit_cost')->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['country_code', 'network'], 'unique_destination');
            $table->index('status', 'idx_status');
        });
    }
}

/**
 * Drop all module tables (legacy wrapper)
 */
function sms_suite_drop_tables()
{
    // Call the new SQL-based drop function
    sms_suite_drop_tables_sql();
}

/**
 * Insert default data
 */
function sms_suite_insert_defaults()
{
    // Insert default countries
    $countries = [
        ['name' => 'United States', 'iso_code' => 'US', 'phone_code' => '1'],
        ['name' => 'United Kingdom', 'iso_code' => 'GB', 'phone_code' => '44'],
        ['name' => 'Canada', 'iso_code' => 'CA', 'phone_code' => '1'],
        ['name' => 'Australia', 'iso_code' => 'AU', 'phone_code' => '61'],
        ['name' => 'Germany', 'iso_code' => 'DE', 'phone_code' => '49'],
        ['name' => 'France', 'iso_code' => 'FR', 'phone_code' => '33'],
        ['name' => 'India', 'iso_code' => 'IN', 'phone_code' => '91'],
        ['name' => 'Brazil', 'iso_code' => 'BR', 'phone_code' => '55'],
        ['name' => 'South Africa', 'iso_code' => 'ZA', 'phone_code' => '27'],
        ['name' => 'Nigeria', 'iso_code' => 'NG', 'phone_code' => '234'],
        ['name' => 'Kenya', 'iso_code' => 'KE', 'phone_code' => '254'],
        ['name' => 'Mexico', 'iso_code' => 'MX', 'phone_code' => '52'],
        ['name' => 'Spain', 'iso_code' => 'ES', 'phone_code' => '34'],
        ['name' => 'Italy', 'iso_code' => 'IT', 'phone_code' => '39'],
        ['name' => 'Netherlands', 'iso_code' => 'NL', 'phone_code' => '31'],
    ];

    foreach ($countries as $country) {
        try {
            Capsule::table('mod_sms_countries')->insertOrIgnore($country);
        } catch (Exception $e) {
            // Ignore duplicates
        }
    }

    // Insert cron task records
    $tasks = ['campaign_processor', 'message_queue', 'dlr_processor', 'rate_limit_reset', 'sender_expiry', 'report_aggregation', 'log_cleanup'];
    foreach ($tasks as $task) {
        try {
            Capsule::table('mod_sms_cron_status')->insertOrIgnore([
                'task' => $task,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // Ignore duplicates
        }
    }
}

/**
 * Load language file
 */
function sms_suite_load_language()
{
    $language = isset($_SESSION['Language']) ? $_SESSION['Language'] : 'english';
    $langFile = __DIR__ . '/lang/' . $language . '.php';

    if (!file_exists($langFile)) {
        $langFile = __DIR__ . '/lang/english.php';
    }

    if (file_exists($langFile)) {
        include $langFile;
        return isset($_LANG) ? $_LANG : [];
    }

    return [];
}

/**
 * Diagnose database tables - checks if all required tables exist
 * Returns array of missing/problematic tables
 */
function sms_suite_diagnose_tables()
{
    $pdo = Capsule::connection()->getPdo();
    $results = [
        'missing' => [],
        'existing' => [],
        'total_checked' => 0,
    ];

    $requiredTables = [
        // Core tables
        'mod_sms_gateways',
        'mod_sms_gateway_countries',
        'mod_sms_settings',
        'mod_sms_messages',
        // Contacts & Campaigns
        'mod_sms_contact_groups',
        'mod_sms_contact_group_fields',
        'mod_sms_contacts',
        'mod_sms_campaigns',
        'mod_sms_campaign_lists',
        'mod_sms_campaign_recipients',
        'mod_sms_campaign_ab_tests',
        'mod_sms_recurring_log',
        'mod_sms_templates',
        // Billing & Credits
        'mod_sms_wallet',
        'mod_sms_wallet_transactions',
        'mod_sms_credit_balance',
        'mod_sms_credit_transactions',
        'mod_sms_credit_packages',
        'mod_sms_credit_purchases',
        'mod_sms_plan_credits',
        'mod_sms_client_rates',
        'mod_sms_destination_rates',
        'mod_sms_credit_allocations',
        'mod_sms_credit_usage',
        'mod_sms_pending_topups',
        // Network Prefixes
        'mod_sms_network_prefixes',
        // Sender IDs
        'mod_sms_sender_ids',
        'mod_sms_sender_id_pool',
        'mod_sms_sender_id_requests',
        'mod_sms_sender_id_plans',
        'mod_sms_client_sender_ids',
        'mod_sms_sender_id_billing',
        // API & Security
        'mod_sms_api_keys',
        'mod_sms_api_audit',
        'mod_sms_api_rate_limits',
        'mod_sms_rate_limits',
        'mod_sms_verification_tokens',
        'mod_sms_verification_templates',
        'mod_sms_verification_logs',
        'mod_sms_blacklist',
        'mod_sms_optouts',
        // Notifications & Automation
        'mod_sms_notification_templates',
        'mod_sms_admin_notifications',
        'mod_sms_client_verification',
        'mod_sms_order_verification',
        'mod_sms_automations',
        'mod_sms_automation_triggers',
        'mod_sms_automation_logs',
        // WhatsApp & Chat
        'mod_sms_whatsapp_templates',
        'mod_sms_chatbox',
        'mod_sms_chatbox_messages',
        'mod_sms_auto_replies',
        // Scheduling & Drip
        'mod_sms_scheduled',
        'mod_sms_drip_campaigns',
        'mod_sms_drip_steps',
        'mod_sms_drip_subscribers',
        // Tags, Segments & Tracking
        'mod_sms_tags',
        'mod_sms_contact_tags',
        'mod_sms_segments',
        'mod_sms_segment_conditions',
        'mod_sms_tracking_links',
        'mod_sms_link_clicks',
        // System
        'mod_sms_cron_status',
        'mod_sms_countries',
        'mod_sms_webhooks_inbox',
    ];

    foreach ($requiredTables as $table) {
        $results['total_checked']++;
        try {
            $result = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
            if (!empty($result)) {
                $results['existing'][] = $table;
            } else {
                $results['missing'][] = $table;
            }
        } catch (Exception $e) {
            $results['missing'][] = $table . ' (error: ' . $e->getMessage() . ')';
        }
    }

    return $results;
}

/**
 * Diagnose database columns - checks critical columns exist in tables
 * Returns array of missing columns per table
 */
function sms_suite_diagnose_columns()
{
    $pdo = Capsule::connection()->getPdo();
    $results = [
        'missing' => [],
        'ok' => [],
        'total_checked' => 0,
    ];

    // Define critical columns that must exist in each table
    $requiredColumns = [
        'mod_sms_automations' => ['trigger_type', 'trigger_config', 'message_template', 'run_count', 'last_run', 'sender_id', 'gateway_id', 'status'],
        'mod_sms_automation_logs' => ['automation_id', 'trigger_data', 'message_id', 'status', 'error', 'created_at'],
        'mod_sms_client_sender_ids' => ['client_id', 'sender_id', 'pool_id', 'request_id', 'gateway_id', 'type', 'network', 'status', 'is_default', 'service_id', 'monthly_fee', 'next_billing', 'expires_at', 'last_invoice_id', 'created_at', 'updated_at'],
        'mod_sms_messages' => ['client_id', 'gateway_id', 'channel', 'direction', 'sender_id', 'to_number', 'message', 'encoding', 'segments', 'units', 'cost', 'status', 'gateway_response', 'sent_at', 'created_at'],
        'mod_sms_settings' => ['client_id', 'billing_mode', 'default_gateway_id', 'default_sender_id', 'api_enabled', 'accept_sms', 'accept_marketing_sms'],
        'mod_sms_webhooks_inbox' => ['gateway_id', 'gateway_type', 'payload', 'raw_payload', 'ip_address', 'processed', 'processed_at'],
        'mod_sms_sender_ids' => ['sender_id', 'type', 'network', 'status', 'documents', 'gateway_bindings', 'approved_at', 'approved_by', 'rejection_reason'],
        'mod_sms_gateways' => ['client_id', 'name', 'type', 'status', 'created_at'],
        'mod_sms_campaigns' => ['client_id', 'name', 'message', 'status', 'created_at'],
        'mod_sms_credit_balance' => ['client_id', 'balance', 'total_purchased', 'total_used', 'total_expired'],
        'mod_sms_credit_transactions' => ['client_id', 'type', 'credits', 'balance_before', 'balance_after', 'description', 'admin_id'],
        'mod_sms_credit_purchases' => ['client_id', 'package_id', 'credits_purchased', 'bonus_credits', 'amount', 'invoice_id', 'status', 'credited_at'],
        'mod_sms_credit_packages' => ['name', 'credits', 'bonus_credits', 'price', 'currency', 'validity_days', 'status'],
        'mod_sms_credit_allocations' => ['client_id', 'service_id', 'sender_id_ref', 'total_credits', 'remaining_credits', 'used_credits', 'status'],
        'mod_sms_wallet' => ['client_id', 'balance'],
        'mod_sms_wallet_transactions' => ['client_id', 'type', 'amount', 'balance_after', 'description'],
        'mod_sms_sender_id_requests' => ['client_id', 'sender_id', 'pool_id', 'business_name', 'billing_cycle', 'setup_fee', 'recurring_fee', 'invoice_id', 'status', 'approved_by', 'approved_at', 'expires_at'],
        'mod_sms_sender_id_billing' => ['client_sender_id', 'client_id', 'billing_type', 'amount', 'invoice_id', 'period_start', 'period_end', 'status'],
        'mod_sms_sender_id_pool' => ['sender_id', 'type', 'network', 'gateway_id', 'country_codes', 'description', 'price_setup', 'price_monthly', 'price_yearly', 'requires_approval', 'is_shared', 'telco_status', 'status'],
        'mod_sms_destination_rates' => ['country_code', 'network', 'sms_rate', 'whatsapp_rate', 'credit_cost', 'status'],
        'mod_sms_client_rates' => ['client_id', 'gateway_id', 'country_code', 'network_prefix', 'sms_rate', 'whatsapp_rate', 'status', 'priority'],
        'mod_sms_api_keys' => ['client_id', 'name', 'key_id', 'secret_hash', 'scopes', 'rate_limit', 'status', 'last_used_at', 'expires_at'],
        'mod_sms_rate_limits' => ['key_id', 'window', 'requests'],
    ];

    foreach ($requiredColumns as $table => $columns) {
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
            if (empty($tableCheck)) {
                continue; // Skip - table itself is missing (handled by table diagnosis)
            }

            $existingColumns = [];
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $existingColumns[] = $row['Field'];
            }

            foreach ($columns as $col) {
                $results['total_checked']++;
                if (in_array($col, $existingColumns)) {
                    $results['ok'][] = "{$table}.{$col}";
                } else {
                    $results['missing'][] = "{$table}.{$col}";
                }
            }
        } catch (Exception $e) {
            // Table might not exist, skip
        }
    }

    return $results;
}

/**
 * Repair database tables - creates missing tables and adds missing columns
 */
function sms_suite_repair_tables()
{
    $diagnosis = sms_suite_diagnose_tables();
    $colDiagBefore = sms_suite_diagnose_columns();

    // Always run table creation/migration - it handles both creating missing tables
    // AND adding missing columns to existing tables
    logActivity('SMS Suite: Running database repair/migration');

    $errors = sms_suite_create_tables_sql();

    $newDiagnosis = sms_suite_diagnose_tables();
    $colDiagAfter = sms_suite_diagnose_columns();

    $tablesRepaired = count($diagnosis['missing']) - count($newDiagnosis['missing']);
    $columnsRepaired = count($colDiagBefore['missing']) - count($colDiagAfter['missing']);

    return [
        'success' => empty($newDiagnosis['missing']) && empty($colDiagAfter['missing']),
        'repaired' => $tablesRepaired,
        'columns_repaired' => $columnsRepaired,
        'still_missing' => $newDiagnosis['missing'],
        'columns_still_missing' => $colDiagAfter['missing'],
        'errors' => $errors,
        'message' => "Repaired {$tablesRepaired} tables, {$columnsRepaired} columns.",
    ];
}

/**
 * Get basic statistics
 */
function sms_suite_get_stats()
{
    try {
        $stats = [
            'total_messages' => 0,
            'total_gateways' => 0,
            'total_campaigns' => 0,
            'total_clients' => 0,
        ];

        $schema = Capsule::schema();

        if ($schema->hasTable('mod_sms_messages')) {
            $stats['total_messages'] = Capsule::table('mod_sms_messages')->count();
        }
        if ($schema->hasTable('mod_sms_gateways')) {
            $stats['total_gateways'] = Capsule::table('mod_sms_gateways')->where('status', 1)->count();
        }
        if ($schema->hasTable('mod_sms_campaigns')) {
            $stats['total_campaigns'] = Capsule::table('mod_sms_campaigns')->count();
        }
        if ($schema->hasTable('mod_sms_settings')) {
            $stats['total_clients'] = Capsule::table('mod_sms_settings')->count();
        }

        return $stats;
    } catch (Exception $e) {
        logActivity('SMS Suite: Error getting stats - ' . $e->getMessage());
        return [
            'total_messages' => 0,
            'total_gateways' => 0,
            'total_campaigns' => 0,
            'total_clients' => 0,
        ];
    }
}

/**
 * Encrypt sensitive data using WHMCS encryption
 */
function sms_suite_encrypt($data)
{
    if (empty($data)) {
        return '';
    }

    // Use WHMCS localAPI for encryption
    $result = localAPI('EncryptPassword', ['password2' => $data]);

    if (isset($result['password']) && !empty($result['password'])) {
        return $result['password'];
    }

    // Log if WHMCS API failed
    if (isset($result['result']) && $result['result'] === 'error') {
        logActivity('SMS Suite: WHMCS EncryptPassword API failed: ' . ($result['message'] ?? 'Unknown error'));
    }

    // Fallback: Use OpenSSL with WHMCS hash
    global $cc_encryption_hash;
    if (!empty($cc_encryption_hash)) {
        $key = hash('sha256', $cc_encryption_hash, true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        if ($encrypted !== false) {
            return base64_encode($iv . $encrypted);
        }
    }

    // No insecure fallback — fail explicitly
    logActivity('SMS Suite: Encryption failed — no working encryption method available');
    throw new \RuntimeException('SMS Suite: Unable to encrypt data. Check WHMCS encryption configuration.');
}

/**
 * Decrypt sensitive data using WHMCS decryption
 */
function sms_suite_decrypt($data)
{
    if (empty($data)) {
        return '';
    }

    // Check for legacy base64 fallback prefix (insecure — re-encrypt this data)
    if (strpos($data, 'b64:') === 0) {
        logActivity('SMS Suite: WARNING — Decrypting data with insecure b64: encoding. Re-save this record to upgrade encryption.');
        return base64_decode(substr($data, 4));
    }

    // Use WHMCS localAPI for decryption
    $result = localAPI('DecryptPassword', ['password2' => $data]);

    if (isset($result['password']) && !empty($result['password'])) {
        $decrypted = $result['password'];

        // WHMCS DecryptPassword returns HTML-encoded data, decode it
        $decrypted = html_entity_decode($decrypted, ENT_QUOTES, 'UTF-8');

        return $decrypted;
    }

    // Fallback: Use OpenSSL with WHMCS hash
    global $cc_encryption_hash;
    if (!empty($cc_encryption_hash)) {
        $decoded = base64_decode($data);
        if ($decoded !== false && strlen($decoded) > 16) {
            $iv = substr($decoded, 0, 16);
            $encrypted = substr($decoded, 16);
            $key = hash('sha256', $cc_encryption_hash, true);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            if ($decrypted !== false) {
                return $decrypted;
            }
        }
    }

    // If all else fails, return as-is (might already be plaintext)
    return $data;
}
