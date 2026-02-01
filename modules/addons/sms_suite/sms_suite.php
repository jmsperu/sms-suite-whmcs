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
        'name' => 'SMS Suite',
        'description' => 'Comprehensive SMS and WhatsApp messaging platform with campaigns, contacts, billing, and API access.',
        'version' => SMS_SUITE_VERSION,
        'author' => 'SMS Suite',
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
        ],
    ];
}

/**
 * Module activation
 */
function sms_suite_activate()
{
    try {
        // Create all database tables
        sms_suite_create_tables();

        // Insert default data
        sms_suite_insert_defaults();

        return [
            'status' => 'success',
            'description' => 'SMS Suite has been activated successfully. Please configure your gateways.',
        ];
    } catch (Exception $e) {
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
            sms_suite_drop_tables();
            return [
                'status' => 'success',
                'description' => 'SMS Suite has been deactivated and all data has been purged.',
            ];
        }

        return [
            'status' => 'success',
            'description' => 'SMS Suite has been deactivated. Data has been preserved.',
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Deactivation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module upgrade handler
 */
function sms_suite_upgrade($vars)
{
    $currentVersion = $vars['version'];
    $schema = Capsule::schema();

    try {
        // Version-specific upgrades
        if (version_compare($currentVersion, '1.0.0', '<')) {
            // Initial installation or upgrade from pre-1.0
            sms_suite_create_tables();
        }

        // Add performance indexes (1.0.1)
        sms_suite_add_performance_indexes();

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
    $addIndex('mod_sms_drip_subscribers', 'idx_drip_sub_status', ['status', 'next_step_at']);
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
            'pagetitle' => 'SMS Suite',
            'breadcrumb' => ['index.php?m=sms_suite' => 'SMS Suite'],
            'templatefile' => 'error',
            'vars' => [
                'error' => 'You must be logged in to access this page.',
            ],
        ];
    }

    // Load language
    $lang = sms_suite_load_language();

    // Include client controller
    $clientController = __DIR__ . '/client/controller.php';
    if (file_exists($clientController)) {
        require_once $clientController;
        if (function_exists('sms_suite_client_dispatch')) {
            return sms_suite_client_dispatch($vars, $action, $clientId, $lang);
        }
    }

    // Fallback basic output
    return [
        'pagetitle' => 'SMS Suite',
        'breadcrumb' => ['index.php?m=sms_suite' => 'SMS Suite'],
        'templatefile' => 'dashboard',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
        ],
    ];
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
            $table->timestamps();
            $table->index('client_id');
        });
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
            $table->string('status', 20)->default('subscribed'); // subscribed, unsubscribed
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
            $table->boolean('processed')->default(false);
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
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('service_id');
            $table->integer('credits_total')->default(0);
            $table->integer('credits_used')->default(0);
            $table->date('reset_date')->nullable();
            $table->timestamps();
            $table->index('client_id');
            $table->unique(['client_id', 'service_id']);
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
            $table->string('hook', 100);
            $table->string('channel', 20)->default('sms');
            $table->unsignedInteger('template_id')->nullable();
            $table->text('message')->nullable();
            $table->string('sender_id', 50)->nullable();
            $table->unsignedInteger('gateway_id')->nullable();
            $table->text('conditions')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index('hook');
            $table->index('status');
        });
    }

    // Automation Logs
    if (!$schema->hasTable('mod_sms_automation_logs')) {
        $schema->create('mod_sms_automation_logs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('automation_id');
            $table->string('hook', 100);
            $table->string('recipient', 50);
            $table->boolean('success')->default(false);
            $table->unsignedInteger('message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['automation_id', 'created_at']);
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
            $table->index(['drip_campaign_id', 'status', 'next_send_at']);
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
        if (!$schema->hasColumn('mod_sms_campaigns', 'recipient_list')) {
            $schema->table('mod_sms_campaigns', function ($table) {
                $table->text('recipient_list')->nullable()->after('segment_id');
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
}

/**
 * Drop all module tables
 */
function sms_suite_drop_tables()
{
    $tables = [
        // New advanced tables
        'mod_sms_scheduled',
        'mod_sms_recurring_log',
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

    $schema = Capsule::schema();
    foreach ($tables as $table) {
        if ($schema->hasTable($table)) {
            $schema->drop($table);
        }
    }
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
 * Get basic statistics
 */
function sms_suite_get_stats()
{
    try {
        return [
            'total_messages' => Capsule::table('mod_sms_messages')->count(),
            'total_gateways' => Capsule::table('mod_sms_gateways')->where('status', 1)->count(),
            'total_campaigns' => Capsule::table('mod_sms_campaigns')->count(),
            'total_clients' => Capsule::table('mod_sms_settings')->count(),
        ];
    } catch (Exception $e) {
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

    if (isset($result['password'])) {
        return $result['password'];
    }

    // Fallback: Use OpenSSL with WHMCS hash
    global $cc_encryption_hash;
    if (!empty($cc_encryption_hash)) {
        $key = hash('sha256', $cc_encryption_hash, true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    return $data;
}

/**
 * Decrypt sensitive data using WHMCS decryption
 */
function sms_suite_decrypt($data)
{
    if (empty($data)) {
        return '';
    }

    // Use WHMCS localAPI for decryption
    $result = localAPI('DecryptPassword', ['password2' => $data]);

    if (isset($result['password'])) {
        return $result['password'];
    }

    // Fallback: Use OpenSSL with WHMCS hash
    global $cc_encryption_hash;
    if (!empty($cc_encryption_hash)) {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $key = hash('sha256', $cc_encryption_hash, true);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    return $data;
}
