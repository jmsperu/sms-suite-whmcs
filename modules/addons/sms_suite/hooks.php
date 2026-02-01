<?php
/**
 * SMS Suite - WHMCS Hooks
 *
 * All hooks are wrapped in try/catch to prevent fatal errors in WHMCS
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Add client area navigation menu item
 */
add_hook('ClientAreaPrimarySidebar', 1, function ($sidebar) {
    try {
        // Check if module is active
        $module = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', 'version')
            ->first();

        if (!$module) {
            return;
        }

        // Add menu item under "Your Account" or as standalone
        $primarySidebar = $sidebar;

        if (!is_null($primarySidebar->getChild('My Account'))) {
            $primarySidebar->getChild('My Account')
                ->addChild('SMS Suite', [
                    'label' => 'SMS Suite',
                    'uri' => 'index.php?m=sms_suite',
                    'icon' => 'fa-comment',
                    'order' => 100,
                ]);
        }
    } catch (Exception $e) {
        // Silently fail - don't break WHMCS
        logActivity('SMS Suite Hook Error (ClientAreaPrimarySidebar): ' . $e->getMessage());
    }
});

/**
 * Invoice Paid Hook - Handle sender ID payments and wallet top-ups
 */
add_hook('InvoicePaid', 1, function ($vars) {
    try {
        $invoiceId = $vars['invoiceid'];

        // Check for sender ID payments
        $senderIdRequest = Capsule::table('mod_sms_sender_ids')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->first();

        if ($senderIdRequest) {
            // Load sender ID service and approve
            require_once __DIR__ . '/lib/Core/SenderIdService.php';
            \SMSSuite\Core\SenderIdService::approve($senderIdRequest->id);
            logActivity('SMS Suite: Sender ID approved after payment - ID ' . $senderIdRequest->id);
        }

        // Check for wallet top-up
        $pendingTopup = Capsule::table('mod_sms_pending_topups')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->first();

        if ($pendingTopup) {
            require_once __DIR__ . '/lib/Billing/BillingService.php';
            \SMSSuite\Billing\BillingService::handleInvoicePaid($invoiceId);
            logActivity('SMS Suite: Wallet top-up processed for invoice ' . $invoiceId);
        }

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (InvoicePaid): ' . $e->getMessage());
    }
});

/**
 * Client Add Hook - Initialize client SMS settings
 */
add_hook('ClientAdd', 1, function ($vars) {
    try {
        $clientId = $vars['userid'];

        // Check if already exists
        $exists = Capsule::table('mod_sms_settings')
            ->where('client_id', $clientId)
            ->exists();

        if (!$exists) {
            // Create settings
            Capsule::table('mod_sms_settings')->insert([
                'client_id' => $clientId,
                'billing_mode' => 'per_segment',
                'api_enabled' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Create wallet
            Capsule::table('mod_sms_wallet')->insert([
                'client_id' => $clientId,
                'balance' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientAdd): ' . $e->getMessage());
    }
});

/**
 * Client Delete Hook - Clean up client data
 */
add_hook('ClientDelete', 1, function ($vars) {
    try {
        $clientId = $vars['userid'];

        // Delete client-specific data
        Capsule::table('mod_sms_settings')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_wallet')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_wallet_transactions')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_contacts')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_contact_groups')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_sender_ids')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_templates')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_api_keys')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_campaigns')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_blacklist')->where('client_id', $clientId)->delete();
        // Keep messages for audit trail, but could optionally delete
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientDelete): ' . $e->getMessage());
    }
});

/**
 * Admin Area Footer Output - Add any required JS/CSS
 */
add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    try {
        // Only on SMS Suite pages
        if (isset($_GET['module']) && $_GET['module'] === 'sms_suite') {
            return <<<HTML
<style>
.sms-suite-admin .panel-heading h4 { margin: 0; }
.sms-suite-admin .huge { font-size: 40px; }
</style>
HTML;
        }
    } catch (Exception $e) {
        // Silent fail
    }
});

/**
 * Client Area Footer Output - Add any required JS/CSS
 */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    try {
        // Only on SMS Suite pages
        if (isset($_GET['m']) && $_GET['m'] === 'sms_suite') {
            return <<<HTML
<style>
.sms-suite-dashboard .panel-heading h4 { margin: 0; font-size: 24px; }
.sms-suite-dashboard .panel-heading p { margin: 5px 0 0 0; }
</style>
HTML;
        }
    } catch (Exception $e) {
        // Silent fail
    }
});

/**
 * Daily Cron Job Hook - Run scheduled tasks
 */
add_hook('DailyCronJob', 1, function ($vars) {
    try {
        // Check and expire sender IDs
        $today = date('Y-m-d');
        Capsule::table('mod_sms_sender_ids')
            ->where('validity_date', '<', $today)
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        // Log cleanup (if retention is set)
        $retentionDays = 90; // Default, could be from config
        $module = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', 'log_retention_days')
            ->first();

        if ($module && is_numeric($module->value)) {
            $retentionDays = (int)$module->value;
        }

        if ($retentionDays > 0) {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            // Delete old webhook inbox entries
            Capsule::table('mod_sms_webhooks_inbox')
                ->where('created_at', '<', $cutoffDate)
                ->where('processed', true)
                ->delete();

            // Delete old API audit logs
            Capsule::table('mod_sms_api_audit')
                ->where('created_at', '<', $cutoffDate)
                ->delete();

            // Delete old rate limit records
            Capsule::table('mod_sms_api_rate_limits')
                ->where('window_start', '<', $cutoffDate)
                ->delete();
        }

        // Update cron status
        Capsule::table('mod_sms_cron_status')
            ->where('task', 'log_cleanup')
            ->update([
                'last_run' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (DailyCronJob): ' . $e->getMessage());
    }
});

/**
 * Automation hooks - Execute configured triggers
 */

// Helper function to execute automation
function sms_suite_execute_automation($hook, $vars) {
    try {
        require_once __DIR__ . '/lib/Automation/AutomationService.php';
        \SMSSuite\Automation\AutomationService::execute($hook, $vars);
    } catch (Exception $e) {
        logActivity("SMS Suite Automation Hook Error ({$hook}): " . $e->getMessage());
    }
}

// Client hooks
add_hook('ClientAdd', 10, function($vars) {
    sms_suite_execute_automation('ClientAdd', $vars);
});

add_hook('ClientLogin', 10, function($vars) {
    sms_suite_execute_automation('ClientLogin', $vars);
});

// Invoice hooks
add_hook('InvoiceCreated', 10, function($vars) {
    sms_suite_execute_automation('InvoiceCreated', $vars);
});

add_hook('InvoicePaid', 10, function($vars) {
    sms_suite_execute_automation('InvoicePaid', $vars);
});

add_hook('InvoicePaymentReminder', 10, function($vars) {
    sms_suite_execute_automation('InvoicePaymentReminder', $vars);
});

// Order hooks
add_hook('OrderPaid', 10, function($vars) {
    sms_suite_execute_automation('OrderPaid', $vars);
});

// Service/Product hooks
add_hook('AfterModuleCreate', 10, function($vars) {
    sms_suite_execute_automation('AfterModuleCreate', $vars);
});

add_hook('AfterModuleSuspend', 10, function($vars) {
    sms_suite_execute_automation('AfterModuleSuspend', $vars);
});

add_hook('AfterModuleUnsuspend', 10, function($vars) {
    sms_suite_execute_automation('AfterModuleUnsuspend', $vars);
});

add_hook('AfterModuleTerminate', 10, function($vars) {
    sms_suite_execute_automation('AfterModuleTerminate', $vars);
});

// Ticket hooks
add_hook('TicketOpen', 10, function($vars) {
    sms_suite_execute_automation('TicketOpen', $vars);
});

add_hook('TicketUserReply', 10, function($vars) {
    sms_suite_execute_automation('TicketUserReply', $vars);
});

add_hook('TicketAdminReply', 10, function($vars) {
    sms_suite_execute_automation('TicketAdminReply', $vars);
});

add_hook('TicketStatusChange', 10, function($vars) {
    sms_suite_execute_automation('TicketStatusChange', $vars);
});

// Domain hooks
add_hook('DomainExpiryNotice', 10, function($vars) {
    sms_suite_execute_automation('DomainExpiryNotice', $vars);
});
