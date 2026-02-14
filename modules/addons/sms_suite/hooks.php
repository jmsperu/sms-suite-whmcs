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
 * Add client area navigation menu items
 */
add_hook('ClientAreaPrimarySidebar', 1, function ($sidebar) {
    try {
        $module = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', 'version')
            ->first();
        if (!$module) return;

        if (!is_null($sidebar->getChild('My Account'))) {
            $sidebar->getChild('My Account')
                ->addChild('Messaging Suite', [
                    'label' => 'Messaging Suite',
                    'uri' => 'index.php?m=sms_suite',
                    'icon' => 'fa-comment',
                    'order' => 100,
                ]);
        }
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientAreaPrimarySidebar): ' . $e->getMessage());
    }
});

/**
 * Add "Messaging" to the primary client area navbar
 */
add_hook('ClientAreaPrimaryNavbar', 1, function ($navbar) {
    try {
        $module = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', 'version')
            ->first();
        if (!$module) return;

        // Only show for logged-in clients
        $client = Menu::context('client');
        if (!$client) return;

        $navbar->addChild('Messaging', [
            'label' => '<i class="fas fa-comments"></i> Messaging',
            'uri' => 'index.php?m=sms_suite',
            'order' => 50,
        ]);

        $messaging = $navbar->getChild('Messaging');
        if ($messaging) {
            $messaging->addChild('Send Message', [
                'label' => 'Send Message',
                'uri' => 'index.php?m=sms_suite&action=send',
                'order' => 10,
            ]);
            $messaging->addChild('Inbox', [
                'label' => 'Inbox',
                'uri' => 'index.php?m=sms_suite&action=inbox',
                'order' => 20,
            ]);
            $messaging->addChild('Campaigns', [
                'label' => 'Campaigns',
                'uri' => 'index.php?m=sms_suite&action=campaigns',
                'order' => 30,
            ]);
            $messaging->addChild('Contacts', [
                'label' => 'Contacts',
                'uri' => 'index.php?m=sms_suite&action=contacts',
                'order' => 40,
            ]);
            $messaging->addChild('AI Chatbot', [
                'label' => 'AI Chatbot',
                'uri' => 'index.php?m=sms_suite&action=chatbot',
                'order' => 50,
            ]);
            $messaging->addChild('Preferences', [
                'label' => 'Preferences',
                'uri' => 'index.php?m=sms_suite&action=preferences',
                'order' => 90,
            ]);
        }
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientAreaPrimaryNavbar): ' . $e->getMessage());
    }
});

/**
 * Invoice Paid Hook - Handle SMS credits, sender ID payments, and wallet top-ups
 */
add_hook('InvoicePaid', 1, function ($vars) {
    try {
        $invoiceId = $vars['invoiceid'];

        require_once __DIR__ . '/lib/Billing/BillingService.php';

        // 1. Check for SMS Credit Package purchase
        $creditPurchase = Capsule::table('mod_sms_credit_purchases')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->first();

        if ($creditPurchase) {
            $result = \SMSSuite\Billing\BillingService::processCreditPurchasePayment($invoiceId);
            if ($result['success']) {
                logActivity('SMS Suite: SMS Credits added for invoice ' . $invoiceId . ' - ' . $result['credits_added'] . ' credits');
            }
        }

        // 2. Check for Sender ID request payment (new system)
        $senderIdRequest = Capsule::table('mod_sms_sender_id_requests')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'approved')
            ->first();

        if ($senderIdRequest) {
            $result = \SMSSuite\Billing\BillingService::processSenderIdPayment($invoiceId);
            if ($result['success']) {
                logActivity('SMS Suite: Sender ID activated after payment - Request ID ' . $senderIdRequest->id);
            }
        }

        // 3. Check for Sender ID renewal payment
        $senderIdRenewal = Capsule::table('mod_sms_sender_id_billing')
            ->where('invoice_id', $invoiceId)
            ->where('billing_type', 'renewal')
            ->where('status', 'pending')
            ->first();

        if ($senderIdRenewal) {
            $result = \SMSSuite\Billing\BillingService::processSenderIdRenewalPayment($invoiceId);
            if ($result['success']) {
                logActivity('SMS Suite: Sender ID renewed - New expiry: ' . $result['new_expiry']);
            }
        }

        // 4. Legacy: Check for old sender ID payments (mod_sms_sender_ids table)
        $legacySenderIdRequest = Capsule::table('mod_sms_sender_ids')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->first();

        if ($legacySenderIdRequest) {
            require_once __DIR__ . '/lib/Core/SenderIdService.php';
            \SMSSuite\Core\SenderIdService::approve($legacySenderIdRequest->id);
            logActivity('SMS Suite: Sender ID approved after payment - ID ' . $legacySenderIdRequest->id);
        }

        // 5. Check for wallet top-up
        $pendingTopup = Capsule::table('mod_sms_pending_topups')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->first();

        if ($pendingTopup) {
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
        // Credit & billing cleanup
        Capsule::table('mod_sms_credit_balance')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_credit_transactions')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_credit_purchases')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_credit_allocations')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_credit_usage')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_pending_topups')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_client_rates')->where('client_id', $clientId)->delete();
        // Sender ID cleanup
        Capsule::table('mod_sms_client_sender_ids')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_sender_id_requests')->where('client_id', $clientId)->delete();
        Capsule::table('mod_sms_sender_id_billing')->where('client_id', $clientId)->delete();
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
 * Client Summary Page - SMS Panel Widget
 * Adds an SMS panel to the client profile in admin area
 */
add_hook('AdminAreaClientSummaryPage', 1, function ($vars) {
    try {
        require_once __DIR__ . '/lib/Core/SecurityHelper.php';
        $clientId = $vars['userid'];

        // Get client details
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) return '';

        // Get client SMS settings
        $settings = Capsule::table('mod_sms_settings')->where('client_id', $clientId)->first();

        // Get phone number
        require_once __DIR__ . '/lib/Core/NotificationService.php';
        $phone = \SMSSuite\Core\NotificationService::getClientPhone($client);

        // Get message stats
        $messageStats = Capsule::table('mod_sms_messages')
            ->where('client_id', $clientId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered')
            ->first();

        // Get wallet balance
        $wallet = Capsule::table('mod_sms_wallet')->where('client_id', $clientId)->first();
        $balance = $wallet ? $wallet->balance : 0;

        // Get client currency
        $clientCurrency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
        $currencySymbol = $clientCurrency ? ($clientCurrency->prefix ?? '$') : '$';

        // Get assigned sender ID and gateway
        $assignedSenderId = $settings ? ($settings->assigned_sender_id ?? 'Not assigned') : 'Not assigned';
        $assignedGatewayId = $settings ? ($settings->assigned_gateway_id ?? null) : null;
        $gatewayName = 'Default';
        if ($assignedGatewayId) {
            $gateway = Capsule::table('mod_sms_gateways')->where('id', $assignedGatewayId)->first();
            $gatewayName = $gateway ? $gateway->name : 'Unknown';
        }

        // Verification status
        $verification = Capsule::table('mod_sms_client_verification')->where('client_id', $clientId)->first();
        $isVerified = $verification && $verification->phone_verified;
        $verificationBadge = $isVerified
            ? '<span class="label label-success">Verified</span>'
            : '<span class="label label-warning">Not Verified</span>';

        // Build the widget HTML
        $html = '
        <div class="panel panel-default" id="sms-suite-client-panel">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fas fa-comments"></i> Messaging Suite
                    <a href="addonmodules.php?module=sms_suite&action=client_settings&client_id=' . $clientId . '" class="btn btn-xs btn-default pull-right">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-6">
                        <strong>Phone:</strong> ' . htmlspecialchars($phone ?: 'Not set') . ' ' . $verificationBadge . '<br>
                        <strong>Balance:</strong> ' . htmlspecialchars($currencySymbol) . number_format($balance, 2) . '<br>
                        <strong>Messages Sent:</strong> ' . ($messageStats->total ?? 0) . ' (' . ($messageStats->delivered ?? 0) . ' delivered)
                    </div>
                    <div class="col-sm-6">
                        <strong>Sender ID:</strong> ' . htmlspecialchars($assignedSenderId) . '<br>
                        <strong>Gateway:</strong> ' . htmlspecialchars($gatewayName) . '<br>
                        <strong>SMS Notifications:</strong> ' . ($settings && $settings->accept_sms ? '<span class="label label-success">Enabled</span>' : '<span class="label label-default">Disabled</span>') . '
                    </div>
                </div>
                <hr style="margin: 10px 0;">
                <form method="post" action="addonmodules.php?module=sms_suite&action=send_to_client" id="sms-suite-send-form">
                    ' . \SMSSuite\Core\SecurityHelper::csrfField() . '
                    <input type="hidden" name="client_id" value="' . $clientId . '">
                    <input type="hidden" name="phone" value="' . htmlspecialchars($phone) . '">
                    <input type="hidden" name="channel" id="sms-suite-channel" value="sms">

                    <!-- Channel toggle buttons -->
                    <div style="display: flex; gap: 8px; margin-bottom: 10px;">
                        <button type="button" class="sms-ch-btn active" data-channel="sms" title="Send via SMS">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>
                            SMS
                        </button>
                        <button type="button" class="sms-ch-btn" data-channel="whatsapp" title="Send via WhatsApp">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            WhatsApp
                        </button>
                        <button type="button" class="sms-ch-btn" data-channel="both" title="Send via both SMS and WhatsApp">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>
                            +
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            Both
                        </button>
                    </div>

                    <!-- Message input + actions -->
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="flex: 1;">
                            <input type="text" name="message" class="form-control" placeholder="Type your message..." required>
                        </div>
                        <button type="submit" class="btn btn-primary" ' . (empty($phone) ? 'disabled title="No phone number"' : '') . '>
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                        <a href="addonmodules.php?module=sms_suite&action=client_messages&client_id=' . $clientId . '" class="btn btn-default">
                            <i class="fas fa-history"></i> History
                        </a>
                    </div>
                </form>
                <style>
                    .sms-ch-btn {
                        display: inline-flex; align-items: center; gap: 6px;
                        padding: 7px 14px; border-radius: 6px;
                        border: 2px solid #ddd; background: #f9f9f9;
                        color: #666; font-size: 13px; font-weight: 500;
                        cursor: pointer; transition: all .2s ease;
                        line-height: 1;
                    }
                    .sms-ch-btn:hover { background: #eee; border-color: #bbb; color: #333; }
                    .sms-ch-btn:focus { outline: none; }
                    .sms-ch-btn svg { flex-shrink: 0; }
                    .sms-ch-btn.active[data-channel="sms"] {
                        background: #e8f0fe; border-color: #337ab7; color: #337ab7;
                        box-shadow: 0 0 0 1px rgba(51,122,183,.2);
                    }
                    .sms-ch-btn.active[data-channel="whatsapp"] {
                        background: #e7f9ee; border-color: #25D366; color: #128C7E;
                        box-shadow: 0 0 0 1px rgba(37,211,102,.2);
                    }
                    .sms-ch-btn.active[data-channel="both"] {
                        background: #f0e8fa; border-color: #7c3aed; color: #5b21b6;
                        box-shadow: 0 0 0 1px rgba(124,58,237,.2);
                    }
                </style>
                <script>
                (function(){
                    var btns = document.querySelectorAll(".sms-ch-btn");
                    var input = document.getElementById("sms-suite-channel");
                    btns.forEach(function(btn){
                        btn.addEventListener("click", function(e){
                            e.preventDefault();
                            btns.forEach(function(b){ b.classList.remove("active"); });
                            btn.classList.add("active");
                            input.value = btn.getAttribute("data-channel");
                        });
                    });
                })();
                </script>
            </div>
        </div>';

        return $html;

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (AdminAreaClientSummaryPage): ' . $e->getMessage());
        return '';
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
        // Check and expire legacy sender IDs
        $today = date('Y-m-d');
        Capsule::table('mod_sms_sender_ids')
            ->where('validity_date', '<', $today)
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        // Expire overdue client sender IDs and generate renewals
        require_once __DIR__ . '/lib/Billing/BillingService.php';
        \SMSSuite\Billing\BillingService::expireOverdueSenderIds();
        \SMSSuite\Billing\BillingService::generateSenderIdRenewals();

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

// ============ WHMCS Internal Notification Hooks ============
// These hooks send SMS notifications alongside WHMCS email templates

/**
 * EmailPreSend Hook - Send SMS notification for WHMCS email templates
 * This is the main hook for internal WHMCS notifications
 */
add_hook('EmailPreSend', 1, function ($vars) {
    try {
        $templateName = $vars['messagename'] ?? '';
        $relId = $vars['relid'] ?? 0;

        // Skip if no template name or related ID
        if (empty($templateName) || empty($relId)) {
            return;
        }

        // Resolve the actual client ID — relid can be invoice ID for invoice emails
        $mergeFields = $vars['mergefields'] ?? [];
        $clientId = !empty($mergeFields['client_id']) ? (int) $mergeFields['client_id'] : 0;
        if (!$clientId) {
            // Fallback: check if relid is a client
            $isClient = Capsule::table('tblclients')->where('id', $relId)->exists();
            if ($isClient) {
                $clientId = (int) $relId;
            } else {
                // Try as invoice ID
                $inv = Capsule::table('tblinvoices')->where('id', $relId)->first();
                $clientId = $inv ? (int) $inv->userid : 0;
            }
        }
        if (!$clientId) {
            return;
        }

        // Check if we have an SMS template for this email template
        require_once __DIR__ . '/lib/Core/NotificationService.php';

        $smsTemplate = \SMSSuite\Core\NotificationService::getSmsTemplateForEmail($templateName, $clientId);

        if (!$smsTemplate) {
            return; // No SMS template or client opted out
        }

        // Build merge data from email merge fields
        $mergeData = $vars['mergefields'] ?? [];

        // Add common fields
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if ($client) {
            $mergeData['client_id'] = $clientId;
            $mergeData['first_name'] = $client->firstname;
            $mergeData['last_name'] = $client->lastname;
            $mergeData['company'] = $client->companyname;
            $mergeData['email'] = $client->email;
            $mergeData['currency'] = sms_suite_get_currency_prefix($client);
        } else {
            return; // No client found, skip notification
        }

        // Map WHMCS merge field names to our template variable names
        $fieldAliases = [
            'invoice_num' => 'invoice_number',
            'invoicenum' => 'invoice_number',
            'invoice_id' => 'invoice_id',
            'invoiceid' => 'invoice_id',
            'invoice_total' => 'total',
            'invoice_balance' => 'balance',
            'amount' => 'total',
            'client_first_name' => 'first_name',
            'client_last_name' => 'last_name',
            'client_company_name' => 'company',
            'client_email' => 'email',
        ];
        foreach ($fieldAliases as $whmcsKey => $ourKey) {
            if (!empty($mergeData[$whmcsKey]) && empty($mergeData[$ourKey])) {
                $mergeData[$ourKey] = $mergeData[$whmcsKey];
            }
        }

        // For invoice-related emails, always resolve clean data from DB
        // WHMCS merge fields like invoice_total include currency prefix (e.g. "KES2102.50")
        // We need raw numbers since our templates handle currency separately
        $invoiceId = $mergeData['invoice_id'] ?? $mergeData['invoiceid'] ?? null;
        if ($invoiceId) {
            $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
            if ($invoice) {
                $mergeData['invoice_number'] = $invoice->invoicenum ?: $invoice->id;
                $mergeData['total'] = number_format($invoice->total, 2);
            }
        }

        // Provide sensible defaults for fields that may not be available
        if (empty($mergeData['error_message'])) {
            $mergeData['error_message'] = 'Please update your payment method or contact support.';
        }

        // Send SMS + WhatsApp via sendClientNotification (handles deduplication)
        $notifType = \SMSSuite\Core\NotificationService::EMAIL_TEMPLATE_MAP[$templateName] ?? null;
        if ($notifType) {
            \SMSSuite\Core\NotificationService::sendClientNotification($clientId, $notifType, $mergeData);
        }

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (EmailPreSend): ' . $e->getMessage());
    }
});

/**
 * Resolve currency prefix for a client (e.g. "KES", "$", "£")
 */
function sms_suite_get_currency_prefix($client): string
{
    try {
        $currency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
        return $currency ? ($currency->prefix ?: $currency->code ?: '') : '';
    } catch (\Exception $e) {
        return '';
    }
}

/**
 * Invoice specific notification hooks
 */
add_hook('InvoiceCreated', 5, function($vars) {
    try {
        $invoiceId = $vars['invoiceid'];
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) return;

        $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
        if (!$client) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        $currencyPrefix = sms_suite_get_currency_prefix($client);
        \SMSSuite\Core\NotificationService::sendClientNotification($invoice->userid, 'invoice_created', [
            'invoice_number' => $invoice->invoicenum ?: $invoiceId,
            'invoice_id' => $invoiceId,
            'total' => number_format($invoice->total, 2),
            'due_date' => date('M d, Y', strtotime($invoice->duedate)),
            'currency' => $currencyPrefix,
            'invoice_url' => rtrim(\App::getSystemURL(), '/') . '/viewinvoice.php?id=' . $invoiceId,
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (InvoiceCreated notification): ' . $e->getMessage());
    }
});

add_hook('InvoicePaidPreEmail', 5, function($vars) {
    try {
        $invoiceId = $vars['invoiceid'];
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) return;

        $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
        if (!$client) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        $currencyPrefix = sms_suite_get_currency_prefix($client);
        \SMSSuite\Core\NotificationService::sendClientNotification($invoice->userid, 'invoice_paid', [
            'invoice_number' => $invoice->invoicenum ?: $invoiceId,
            'invoice_id' => $invoiceId,
            'total' => number_format($invoice->total, 2),
            'currency' => $currencyPrefix,
        ]);

        // Also notify admins
        \SMSSuite\Core\NotificationService::sendAdminNotification('order_paid', [
            'order_id' => $invoice->invoicenum ?: $invoiceId,
            'client_name' => $client->firstname . ' ' . $client->lastname,
            'total' => $currencyPrefix . number_format($invoice->total, 2),
            'currency' => $currencyPrefix,
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (InvoicePaidPreEmail): ' . $e->getMessage());
    }
});

/**
 * Order notification hooks
 */
add_hook('AfterShoppingCartCheckout', 5, function($vars) {
    try {
        $orderId = $vars['OrderID'] ?? $vars['orderid'] ?? null;
        if (!$orderId) return;

        $order = Capsule::table('tblorders')->where('id', $orderId)->first();
        if (!$order) return;

        $client = Capsule::table('tblclients')->where('id', $order->userid)->first();
        if (!$client) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        // Send client notification
        $currencyPrefix = sms_suite_get_currency_prefix($client);
        \SMSSuite\Core\NotificationService::sendClientNotification($order->userid, 'order_confirmation', [
            'order_number' => $order->ordernum,
            'order_id' => $orderId,
            'total' => number_format($order->amount, 2),
            'currency' => $currencyPrefix,
        ]);

        // Notify admins of new order
        \SMSSuite\Core\NotificationService::sendAdminNotification('new_order', [
            'order_id' => $order->ordernum,
            'client_name' => $client->firstname . ' ' . $client->lastname,
            'total' => $currencyPrefix . number_format($order->amount, 2),
            'currency' => $currencyPrefix,
        ]);

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (AfterShoppingCartCheckout): ' . $e->getMessage());
    }
});

/**
 * Ticket notification hooks
 */
add_hook('TicketOpen', 5, function($vars) {
    try {
        $ticketId = $vars['ticketid'];

        $ticket = Capsule::table('tbltickets')->where('id', $ticketId)->first();
        if (!$ticket || empty($ticket->userid)) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        // Notify client
        \SMSSuite\Core\NotificationService::sendClientNotification($ticket->userid, 'ticket_opened', [
            'ticket_id' => $ticket->tid,
            'ticket_subject' => $ticket->title,
            'ticket_url' => rtrim(\App::getSystemURL(), '/') . '/viewticket.php?tid=' . $ticket->tid,
        ]);

        // Notify admins
        $dept = Capsule::table('tblticketdepartments')->where('id', $ticket->did)->first();
        \SMSSuite\Core\NotificationService::sendAdminNotification('new_ticket', [
            'ticket_id' => $ticket->tid,
            'subject' => $ticket->title,
            'priority' => $ticket->priority,
            'department' => $dept->name ?? 'General',
        ]);

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (TicketOpen notification): ' . $e->getMessage());
    }
});

add_hook('TicketAdminReply', 5, function($vars) {
    try {
        $ticketId = $vars['ticketid'];

        $ticket = Capsule::table('tbltickets')->where('id', $ticketId)->first();
        if (!$ticket || empty($ticket->userid)) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        \SMSSuite\Core\NotificationService::sendClientNotification($ticket->userid, 'ticket_reply', [
            'ticket_id' => $ticket->tid,
            'ticket_subject' => $ticket->title,
            'ticket_url' => rtrim(\App::getSystemURL(), '/') . '/viewticket.php?tid=' . $ticket->tid,
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (TicketAdminReply notification): ' . $e->getMessage());
    }
});

add_hook('TicketUserReply', 5, function($vars) {
    try {
        $ticketId = $vars['ticketid'];

        $ticket = Capsule::table('tbltickets')->where('id', $ticketId)->first();
        if (!$ticket) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        // Notify admins of client reply
        \SMSSuite\Core\NotificationService::sendAdminNotification('ticket_reply', [
            'ticket_id' => $ticket->tid,
            'subject' => $ticket->title,
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (TicketUserReply notification): ' . $e->getMessage());
    }
});

/**
 * Domain notification hooks
 */
add_hook('DomainRegister', 5, function($vars) {
    try {
        $domainId = $vars['domainid'];

        $domain = Capsule::table('tbldomains')->where('id', $domainId)->first();
        if (!$domain) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        \SMSSuite\Core\NotificationService::sendClientNotification($domain->userid, 'domain_registered', [
            'domain' => $domain->domain,
            'expiry_date' => date('M d, Y', strtotime($domain->expirydate)),
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (DomainRegister notification): ' . $e->getMessage());
    }
});

add_hook('DomainRenewal', 5, function($vars) {
    try {
        $domainId = $vars['domainid'];

        $domain = Capsule::table('tbldomains')->where('id', $domainId)->first();
        if (!$domain) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        \SMSSuite\Core\NotificationService::sendClientNotification($domain->userid, 'domain_renewed', [
            'domain' => $domain->domain,
            'expiry_date' => date('M d, Y', strtotime($domain->expirydate)),
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (DomainRenewal notification): ' . $e->getMessage());
    }
});

/**
 * Service suspension hooks
 */
add_hook('AfterModuleSuspend', 5, function($vars) {
    try {
        $serviceId = $vars['serviceid'];

        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) return;

        $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        // Notify client
        \SMSSuite\Core\NotificationService::sendClientNotification($service->userid, 'service_suspended', [
            'product_name' => $product->name ?? 'Service',
            'service_id' => $serviceId,
        ]);

        // Notify admins
        $client = Capsule::table('tblclients')->where('id', $service->userid)->first();
        \SMSSuite\Core\NotificationService::sendAdminNotification('service_suspended', [
            'product_name' => $product->name ?? 'Service',
            'client_name' => ($client->firstname ?? '') . ' ' . ($client->lastname ?? ''),
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (AfterModuleSuspend notification): ' . $e->getMessage());
    }
});

add_hook('AfterModuleUnsuspend', 5, function($vars) {
    try {
        $serviceId = $vars['serviceid'];

        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) return;

        $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        \SMSSuite\Core\NotificationService::sendClientNotification($service->userid, 'service_unsuspended', [
            'product_name' => $product->name ?? 'Service',
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (AfterModuleUnsuspend notification): ' . $e->getMessage());
    }
});

/**
 * Client login hooks (for admin notification and 2FA)
 */
add_hook('ClientLogin', 5, function($vars) {
    try {
        $userId = $vars['userid'];

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        $client = Capsule::table('tblclients')->where('id', $userId)->first();
        if (!$client) return;

        \SMSSuite\Core\NotificationService::sendAdminNotification('client_login', [
            'email' => $client->email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientLogin notification): ' . $e->getMessage());
    }
});

/**
 * Admin login notification
 */
add_hook('AdminLogin', 5, function($vars) {
    try {
        require_once __DIR__ . '/lib/Core/NotificationService.php';

        \SMSSuite\Core\NotificationService::sendAdminNotification('admin_login', [
            'username' => $vars['username'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (AdminLogin notification): ' . $e->getMessage());
    }
});

/**
 * Cancellation request notification
 */
add_hook('CancellationRequest', 5, function($vars) {
    try {
        $serviceId = $vars['relid'];

        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) return;

        $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
        $client = Capsule::table('tblclients')->where('id', $service->userid)->first();

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        \SMSSuite\Core\NotificationService::sendAdminNotification('cancellation_request', [
            'product_name' => $product->name ?? 'Service',
            'client_name' => ($client->firstname ?? '') . ' ' . ($client->lastname ?? ''),
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (CancellationRequest notification): ' . $e->getMessage());
    }
});

/**
 * Quote notification hooks
 */
add_hook('QuoteCreated', 5, function($vars) {
    try {
        $quoteId = $vars['quoteid'];

        $quote = Capsule::table('tblquotes')->where('id', $quoteId)->first();
        if (!$quote) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        $quoteClient = Capsule::table('tblclients')->where('id', $quote->userid)->first();
        $currencyPrefix = $quoteClient ? sms_suite_get_currency_prefix($quoteClient) : '';
        \SMSSuite\Core\NotificationService::sendClientNotification($quote->userid, 'quote_created', [
            'quote_number' => $quoteId,
            'total' => number_format($quote->total, 2),
            'currency' => $currencyPrefix,
            'quote_url' => rtrim(\App::getSystemURL(), '/') . '/viewquote.php?id=' . $quoteId,
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (QuoteCreated notification): ' . $e->getMessage());
    }
});

add_hook('QuoteAccepted', 5, function($vars) {
    try {
        $quoteId = $vars['quoteid'];

        $quote = Capsule::table('tblquotes')->where('id', $quoteId)->first();
        if (!$quote) return;

        require_once __DIR__ . '/lib/Core/NotificationService.php';

        \SMSSuite\Core\NotificationService::sendClientNotification($quote->userid, 'quote_accepted', [
            'quote_number' => $quoteId,
        ]);
    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (QuoteAccepted notification): ' . $e->getMessage());
    }
});

/**
 * ============================================================================
 * TWO-FACTOR AUTHENTICATION (2FA) HOOKS
 * ============================================================================
 */

/**
 * Client Login - Initiate 2FA if enabled
 * This hook sends the OTP code when a client with 2FA enabled logs in
 */
add_hook('ClientLogin', 1, function($vars) {
    try {
        $userId = $vars['userid'];

        // Check if client has 2FA enabled
        $settings = Capsule::table('mod_sms_settings')
            ->where('client_id', $userId)
            ->first();

        if (!$settings || !$settings->two_factor_enabled) {
            return; // 2FA not enabled, allow normal login
        }

        // Check if phone is verified
        $phoneVerified = Capsule::table('mod_sms_client_verification')
            ->where('client_id', $userId)
            ->where('verified', 1)
            ->exists();

        if (!$phoneVerified) {
            return; // Phone not verified, skip 2FA
        }

        // Get client phone
        $client = Capsule::table('tblclients')
            ->where('id', $userId)
            ->first();

        if (empty($client->phonenumber)) {
            return; // No phone number, skip 2FA
        }

        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Send 2FA code
        require_once __DIR__ . '/lib/Core/VerificationService.php';
        $result = \SMSSuite\Core\VerificationService::sendTwoFactorToken($userId, $client->phonenumber);

        if ($result['success']) {
            // Set session flag for 2FA pending
            $_SESSION['sms_2fa_pending'] = true;
            $_SESSION['sms_2fa_user_id'] = $userId;
            $_SESSION['sms_2fa_timestamp'] = time();
            logActivity('SMS Suite 2FA: Code sent to client #' . $userId);
        } else {
            logActivity('SMS Suite 2FA Error: Failed to send code to client #' . $userId . ' - ' . ($result['error'] ?? 'Unknown error'));
        }

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientLogin 2FA): ' . $e->getMessage());
    }
});

/**
 * Client Area Page - Intercept pages when 2FA verification is pending
 */
add_hook('ClientAreaPage', 1, function($vars) {
    try {
        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if 2FA verification is pending
        if (empty($_SESSION['sms_2fa_pending'])) {
            return $vars;
        }

        // Allow access to specific pages
        $currentPage = $vars['filename'] ?? '';
        $allowedPages = ['logout', 'dologout', 'clientarea'];

        // Check if this is the 2FA verification action
        if (isset($_GET['action']) && $_GET['action'] === 'sms_2fa_verify') {
            return $vars; // Let it through for verification
        }

        // Allow logout
        if (in_array($currentPage, $allowedPages) && isset($_GET['action']) && $_GET['action'] === 'logout') {
            unset($_SESSION['sms_2fa_pending']);
            unset($_SESSION['sms_2fa_user_id']);
            unset($_SESSION['sms_2fa_timestamp']);
            return $vars;
        }

        // Check for timeout (10 minutes)
        if (isset($_SESSION['sms_2fa_timestamp']) && (time() - $_SESSION['sms_2fa_timestamp']) > 600) {
            // Session expired, log out user
            unset($_SESSION['sms_2fa_pending']);
            unset($_SESSION['sms_2fa_user_id']);
            unset($_SESSION['sms_2fa_timestamp']);
            header('Location: index.php?action=logout&reason=2fa_timeout');
            exit;
        }

        // Redirect to 2FA verification page
        if ($currentPage !== 'clientarea' || !isset($_GET['action']) || $_GET['action'] !== 'sms_2fa') {
            header('Location: index.php?action=sms_2fa');
            exit;
        }

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientAreaPage 2FA): ' . $e->getMessage());
    }

    return $vars;
});

/**
 * Client Area Page - Handle 2FA verification page rendering
 */
add_hook('ClientAreaPageHome', 1, function($vars) {
    try {
        // Check if this is the 2FA page request
        if (!isset($_GET['action']) || $_GET['action'] !== 'sms_2fa') {
            return $vars;
        }

        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if 2FA is actually pending
        if (empty($_SESSION['sms_2fa_pending']) || empty($_SESSION['sms_2fa_user_id'])) {
            header('Location: clientarea.php');
            exit;
        }

        $error = '';
        $success = '';
        $userId = $_SESSION['sms_2fa_user_id'];

        // Handle verification submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['verify_2fa'])) {
                $code = trim($_POST['verification_code'] ?? '');

                require_once __DIR__ . '/lib/Core/VerificationService.php';
                $result = \SMSSuite\Core\VerificationService::verifyToken(
                    $userId,
                    $code,
                    \SMSSuite\Core\VerificationService::TYPE_TWO_FACTOR
                );

                if ($result['success']) {
                    // Clear 2FA session flags
                    unset($_SESSION['sms_2fa_pending']);
                    unset($_SESSION['sms_2fa_user_id']);
                    unset($_SESSION['sms_2fa_timestamp']);

                    logActivity('SMS Suite 2FA: Client #' . $userId . ' verified successfully');

                    // Redirect to client area
                    header('Location: clientarea.php');
                    exit;
                } else {
                    $error = $result['error'] ?? 'Invalid verification code. Please try again.';
                }
            }

            // Handle resend request
            if (isset($_POST['resend_code'])) {
                $client = Capsule::table('tblclients')->where('id', $userId)->first();
                if ($client && !empty($client->phonenumber)) {
                    require_once __DIR__ . '/lib/Core/VerificationService.php';
                    $result = \SMSSuite\Core\VerificationService::sendTwoFactorToken($userId, $client->phonenumber);
                    if ($result['success']) {
                        $success = 'A new verification code has been sent to your phone.';
                    } else {
                        $error = 'Failed to resend code. Please try again later.';
                    }
                }
            }
        }

        // Get client's masked phone number
        $client = Capsule::table('tblclients')->where('id', $userId)->first();
        $maskedPhone = ($client && $client->phonenumber) ? '****' . substr($client->phonenumber, -4) : '****';

        // Return custom page vars
        $vars['pagetitle'] = 'Two-Factor Authentication';
        $vars['sms_2fa_page'] = true;
        $vars['sms_2fa_error'] = $error;
        $vars['sms_2fa_success'] = $success;
        $vars['sms_2fa_masked_phone'] = $maskedPhone;

        return $vars;

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientAreaPageHome 2FA): ' . $e->getMessage());
    }

    return $vars;
});

/**
 * Client Area Header Output - Inject 2FA page HTML
 */
add_hook('ClientAreaHeaderOutput', 1, function($vars) {
    try {
        if (!isset($_GET['action']) || $_GET['action'] !== 'sms_2fa') {
            return '';
        }

        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['sms_2fa_pending'])) {
            return '';
        }

        $userId = $_SESSION['sms_2fa_user_id'] ?? 0;
        $client = Capsule::table('tblclients')->where('id', $userId)->first();
        $maskedPhone = $client ? '****' . substr($client->phonenumber, -4) : '****';

        $error = $_SESSION['sms_2fa_error'] ?? '';
        $success = $_SESSION['sms_2fa_success'] ?? '';
        unset($_SESSION['sms_2fa_error'], $_SESSION['sms_2fa_success']);

        // Output custom 2FA page
        echo '
        <style>
            .sms-2fa-container { max-width: 400px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .sms-2fa-container h2 { text-align: center; margin-bottom: 20px; color: #333; }
            .sms-2fa-container .icon { text-align: center; font-size: 48px; color: #007bff; margin-bottom: 20px; }
            .sms-2fa-container p { text-align: center; color: #666; margin-bottom: 20px; }
            .sms-2fa-container .code-input { text-align: center; font-size: 24px; letter-spacing: 8px; padding: 15px; }
            .sms-2fa-container .btn-block { margin-top: 15px; }
            .sms-2fa-container .resend-link { text-align: center; margin-top: 15px; }
        </style>
        <div class="sms-2fa-container">
            <div class="icon"><i class="fa fa-shield"></i></div>
            <h2>Two-Factor Authentication</h2>
            <p>A verification code has been sent to your phone number ending in <strong>' . htmlspecialchars($maskedPhone) . '</strong></p>
            ' . ($error ? '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>' : '') . '
            ' . ($success ? '<div class="alert alert-success">' . htmlspecialchars($success) . '</div>' : '') . '
            <form method="post">
                <div class="form-group">
                    <input type="text" name="verification_code" class="form-control code-input" placeholder="000000" maxlength="6" required autofocus pattern="[0-9]{6}">
                </div>
                <button type="submit" name="verify_2fa" class="btn btn-primary btn-lg btn-block">Verify Code</button>
            </form>
            <div class="resend-link">
                <form method="post" style="display: inline;">
                    <button type="submit" name="resend_code" class="btn btn-link">Did not receive the code? Send again</button>
                </form>
            </div>
            <hr>
            <div class="text-center">
                <a href="index.php?action=logout" class="text-muted">Cancel and log out</a>
            </div>
        </div>
        <script>
            // Hide other page content
            document.addEventListener("DOMContentLoaded", function() {
                var mainContent = document.querySelector(".main-content, #main-body, .container");
                if (mainContent) {
                    var children = mainContent.children;
                    for (var i = 0; i < children.length; i++) {
                        if (!children[i].classList.contains("sms-2fa-container")) {
                            children[i].style.display = "none";
                        }
                    }
                }
            });
        </script>';

        exit; // Stop further page rendering

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (ClientAreaHeaderOutput 2FA): ' . $e->getMessage());
        return '';
    }
});
