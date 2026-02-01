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
        $balance = $wallet->balance ?? 0;

        // Get assigned sender ID and gateway
        $assignedSenderId = $settings->assigned_sender_id ?? 'Not assigned';
        $assignedGatewayId = $settings->assigned_gateway_id ?? null;
        $gatewayName = 'Default';
        if ($assignedGatewayId) {
            $gateway = Capsule::table('mod_sms_gateways')->where('id', $assignedGatewayId)->first();
            $gatewayName = $gateway->name ?? 'Unknown';
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
                    <i class="fas fa-sms"></i> SMS Suite
                    <a href="addonmodules.php?module=sms_suite&action=client_settings&client_id=' . $clientId . '" class="btn btn-xs btn-default pull-right">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-6">
                        <strong>Phone:</strong> ' . htmlspecialchars($phone ?: 'Not set') . ' ' . $verificationBadge . '<br>
                        <strong>Balance:</strong> $' . number_format($balance, 2) . '<br>
                        <strong>Messages Sent:</strong> ' . ($messageStats->total ?? 0) . ' (' . ($messageStats->delivered ?? 0) . ' delivered)
                    </div>
                    <div class="col-sm-6">
                        <strong>Sender ID:</strong> ' . htmlspecialchars($assignedSenderId) . '<br>
                        <strong>Gateway:</strong> ' . htmlspecialchars($gatewayName) . '<br>
                        <strong>SMS Notifications:</strong> ' . ($settings && $settings->accept_sms ? '<span class="label label-success">Enabled</span>' : '<span class="label label-default">Disabled</span>') . '
                    </div>
                </div>
                <hr style="margin: 10px 0;">
                <form method="post" action="addonmodules.php?module=sms_suite&action=send_to_client" class="form-inline">
                    <input type="hidden" name="client_id" value="' . $clientId . '">
                    <input type="hidden" name="phone" value="' . htmlspecialchars($phone) . '">
                    <div class="form-group" style="width: 60%;">
                        <input type="text" name="message" class="form-control" style="width: 100%;" placeholder="Type your SMS message..." required>
                    </div>
                    <button type="submit" class="btn btn-primary" ' . (empty($phone) ? 'disabled title="No phone number"' : '') . '>
                        <i class="fas fa-paper-plane"></i> Send SMS
                    </button>
                    <a href="addonmodules.php?module=sms_suite&action=client_messages&client_id=' . $clientId . '" class="btn btn-default">
                        <i class="fas fa-history"></i> History
                    </a>
                </form>
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

// ============ WHMCS Internal Notification Hooks ============
// These hooks send SMS notifications alongside WHMCS email templates

/**
 * EmailPreSend Hook - Send SMS notification for WHMCS email templates
 * This is the main hook for internal WHMCS notifications
 */
add_hook('EmailPreSend', 1, function ($vars) {
    try {
        $templateName = $vars['messagename'] ?? '';
        $clientId = $vars['relid'] ?? 0;

        // Skip if no template name or client ID
        if (empty($templateName) || empty($clientId)) {
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
        if (isset($vars['relid'])) {
            $client = Capsule::table('tblclients')->where('id', $vars['relid'])->first();
            if ($client) {
                $mergeData['first_name'] = $client->firstname;
                $mergeData['last_name'] = $client->lastname;
                $mergeData['company'] = $client->companyname;
                $mergeData['email'] = $client->email;
            }
        }

        // Send SMS notification
        require_once __DIR__ . '/lib/Core/TemplateService.php';
        require_once __DIR__ . '/lib/Core/MessageService.php';

        $phone = \SMSSuite\Core\NotificationService::getClientPhone($client);
        if (empty($phone)) {
            return;
        }

        $message = \SMSSuite\Core\TemplateService::processTemplate($smsTemplate['message'], $mergeData);
        \SMSSuite\Core\MessageService::send($clientId, $phone, $message, 'notification');

    } catch (Exception $e) {
        logActivity('SMS Suite Hook Error (EmailPreSend): ' . $e->getMessage());
    }
});

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

        \SMSSuite\Core\NotificationService::sendClientNotification($invoice->userid, 'invoice_created', [
            'invoice_number' => $invoiceId,
            'invoice_id' => $invoiceId,
            'total' => $invoice->total,
            'due_date' => date('M d, Y', strtotime($invoice->duedate)),
            'currency' => $client->currency == 1 ? '$' : '',
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

        \SMSSuite\Core\NotificationService::sendClientNotification($invoice->userid, 'invoice_paid', [
            'invoice_number' => $invoiceId,
            'total' => $invoice->total,
            'currency' => $client->currency == 1 ? '$' : '',
        ]);

        // Also notify admins
        \SMSSuite\Core\NotificationService::sendAdminNotification('order_paid', [
            'order_id' => $invoiceId,
            'client_name' => $client->firstname . ' ' . $client->lastname,
            'total' => $invoice->total,
            'currency' => '$',
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
        \SMSSuite\Core\NotificationService::sendClientNotification($order->userid, 'order_confirmation', [
            'order_number' => $order->ordernum,
            'order_id' => $orderId,
            'total' => $order->amount,
            'currency' => '$',
        ]);

        // Notify admins of new order
        \SMSSuite\Core\NotificationService::sendAdminNotification('new_order', [
            'order_id' => $order->ordernum,
            'client_name' => $client->firstname . ' ' . $client->lastname,
            'total' => $order->amount,
            'currency' => '$',
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

        \SMSSuite\Core\NotificationService::sendClientNotification($quote->userid, 'quote_created', [
            'quote_number' => $quoteId,
            'total' => $quote->total,
            'currency' => '$',
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
