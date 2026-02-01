<?php
/**
 * SMS Suite - Notification Service
 *
 * Handles SMS notifications linked to WHMCS email templates
 * Provides SMS counterparts to standard WHMCS notifications
 */

namespace SMSSuite\Core;

use WHMCS\Database\Capsule;
use Exception;

class NotificationService
{
    /**
     * WHMCS Email Template to SMS Template mapping
     * Maps standard WHMCS email template names to SMS notification types
     */
    const EMAIL_TEMPLATE_MAP = [
        // Client notifications
        'Client Signup Email' => 'client_signup',
        'Client Change Password' => 'client_password_change',
        'Password Reset Confirmation' => 'password_reset',
        'Password Reset Validation' => 'password_reset_validation',

        // Order notifications
        'Order Confirmation' => 'order_confirmation',
        'Order Pending' => 'order_pending',
        'Order Accepted' => 'order_accepted',
        'Order Cancellation' => 'order_cancelled',
        'Order Refund' => 'order_refund',

        // Invoice notifications
        'Invoice Created' => 'invoice_created',
        'Invoice Payment Confirmation' => 'invoice_paid',
        'Invoice Overdue' => 'invoice_overdue',
        'Invoice Reminder' => 'invoice_reminder',
        'First Invoice Overdue Notice' => 'invoice_first_overdue',
        'Second Invoice Overdue Notice' => 'invoice_second_overdue',
        'Third Invoice Overdue Notice' => 'invoice_third_overdue',
        'Credit Card Payment Confirmation' => 'payment_confirmation',
        'Credit Card Payment Failed' => 'payment_failed',

        // Quote notifications
        'Quote Delivery with PDF' => 'quote_created',
        'Quote Accepted' => 'quote_accepted',
        'Quote Subject' => 'quote_reminder',

        // Domain notifications
        'Domain Registration Confirmation' => 'domain_registered',
        'Domain Transfer Initiated' => 'domain_transfer_initiated',
        'Domain Transfer Completed' => 'domain_transfer_completed',
        'Domain Transfer Failed' => 'domain_transfer_failed',
        'Domain Renewal Confirmation' => 'domain_renewed',
        'Domain Expiry Notice' => 'domain_expiry',
        'Upcoming Domain Renewal Notice' => 'domain_renewal_notice',

        // Service/Hosting notifications
        'Product/Service Welcome Email' => 'service_welcome',
        'Service Suspension' => 'service_suspended',
        'Service Unsuspension' => 'service_unsuspended',
        'Service Cancellation' => 'service_cancelled',
        'Upgrade/Downgrade Confirmation' => 'service_upgrade',

        // Ticket notifications
        'Support Ticket Opened' => 'ticket_opened',
        'Support Ticket Reply' => 'ticket_reply',
        'Support Ticket Closed' => 'ticket_closed',

        // Affiliate notifications
        'Affiliate Activation Email' => 'affiliate_activated',
        'Affiliate Commission Withdrawal Request' => 'affiliate_withdrawal',
    ];

    /**
     * Admin notification events
     */
    const ADMIN_EVENTS = [
        'new_order' => 'New order submitted',
        'order_paid' => 'Order payment received',
        'new_ticket' => 'New support ticket opened',
        'ticket_reply' => 'Client replied to ticket',
        'ticket_escalated' => 'Ticket escalated',
        'client_login' => 'Client login',
        'admin_login' => 'Admin login',
        'cancellation_request' => 'Cancellation request submitted',
        'service_suspended' => 'Service suspended',
        'service_failed' => 'Service automation failed',
        'domain_renewal_failed' => 'Domain renewal failed',
        'low_credit' => 'Low credit balance',
        'fraud_order' => 'Potential fraud detected',
    ];

    /**
     * Get SMS template for email template name
     */
    public static function getSmsTemplateForEmail(string $emailTemplateName, int $clientId = 0): ?array
    {
        // Get the SMS notification type from mapping
        $notificationType = self::EMAIL_TEMPLATE_MAP[$emailTemplateName] ?? null;

        if (!$notificationType) {
            return null;
        }

        // Check if SMS notifications are enabled for this type
        $template = Capsule::table('mod_sms_notification_templates')
            ->where('notification_type', $notificationType)
            ->where('status', 'active')
            ->first();

        if (!$template) {
            return null;
        }

        // Check if client has opted in for SMS notifications
        if ($clientId > 0) {
            $clientSetting = Capsule::table('mod_sms_settings')
                ->where('client_id', $clientId)
                ->first();

            if ($clientSetting) {
                $enabledNotifications = json_decode($clientSetting->enabled_notifications ?? '[]', true);
                $acceptSms = !empty($clientSetting->accept_sms);

                if (!$acceptSms || (!empty($enabledNotifications) && !in_array($notificationType, $enabledNotifications))) {
                    return null;
                }
            }
        }

        return (array)$template;
    }

    /**
     * Get all available notification types
     */
    public static function getNotificationTypes(): array
    {
        $types = [];

        foreach (self::EMAIL_TEMPLATE_MAP as $emailTemplate => $type) {
            $types[$type] = [
                'type' => $type,
                'email_template' => $emailTemplate,
                'category' => self::getCategoryForType($type),
            ];
        }

        return $types;
    }

    /**
     * Get category for notification type
     */
    public static function getCategoryForType(string $type): string
    {
        $categories = [
            'client' => ['client_signup', 'client_password_change', 'password_reset', 'password_reset_validation'],
            'order' => ['order_confirmation', 'order_pending', 'order_accepted', 'order_cancelled', 'order_refund'],
            'invoice' => ['invoice_created', 'invoice_paid', 'invoice_overdue', 'invoice_reminder',
                         'invoice_first_overdue', 'invoice_second_overdue', 'invoice_third_overdue',
                         'payment_confirmation', 'payment_failed'],
            'quote' => ['quote_created', 'quote_accepted', 'quote_reminder'],
            'domain' => ['domain_registered', 'domain_transfer_initiated', 'domain_transfer_completed',
                        'domain_transfer_failed', 'domain_renewed', 'domain_expiry', 'domain_renewal_notice'],
            'service' => ['service_welcome', 'service_suspended', 'service_unsuspended', 'service_cancelled', 'service_upgrade'],
            'ticket' => ['ticket_opened', 'ticket_reply', 'ticket_closed'],
            'affiliate' => ['affiliate_activated', 'affiliate_withdrawal'],
        ];

        foreach ($categories as $category => $types) {
            if (in_array($type, $types)) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * Create default SMS templates for WHMCS email templates
     */
    public static function createDefaultTemplates(): int
    {
        $created = 0;
        $defaults = self::getDefaultTemplates();

        foreach ($defaults as $type => $template) {
            $exists = Capsule::table('mod_sms_notification_templates')
                ->where('notification_type', $type)
                ->exists();

            if (!$exists) {
                Capsule::table('mod_sms_notification_templates')->insert([
                    'notification_type' => $type,
                    'name' => $template['name'],
                    'message' => $template['message'],
                    'category' => self::getCategoryForType($type),
                    'status' => 'inactive', // Inactive by default, admin enables
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Get default SMS templates
     */
    public static function getDefaultTemplates(): array
    {
        return [
            // Client
            'client_signup' => [
                'name' => 'Client Registration',
                'message' => 'Welcome to {company_name}! Your account has been created. Login at {whmcs_url}',
            ],
            'client_password_change' => [
                'name' => 'Password Changed',
                'message' => 'Your password has been changed. If this was not you, contact support immediately.',
            ],
            'password_reset' => [
                'name' => 'Password Reset',
                'message' => 'Password reset requested. Your reset code is: {reset_code}',
            ],
            'password_reset_validation' => [
                'name' => 'Password Reset Validation',
                'message' => 'Click to reset password: {reset_url}',
            ],

            // Orders
            'order_confirmation' => [
                'name' => 'Order Confirmation',
                'message' => 'Order #{order_number} received. Total: {currency}{total}. Thank you for your order!',
            ],
            'order_pending' => [
                'name' => 'Order Pending',
                'message' => 'Order #{order_number} is pending review. We\'ll notify you when processed.',
            ],
            'order_accepted' => [
                'name' => 'Order Accepted',
                'message' => 'Order #{order_number} has been accepted and is being processed.',
            ],
            'order_cancelled' => [
                'name' => 'Order Cancelled',
                'message' => 'Order #{order_number} has been cancelled.',
            ],

            // Invoices
            'invoice_created' => [
                'name' => 'Invoice Created',
                'message' => 'Invoice #{invoice_number} for {currency}{total} is now due. Pay at: {invoice_url}',
            ],
            'invoice_paid' => [
                'name' => 'Invoice Paid',
                'message' => 'Payment of {currency}{total} received for invoice #{invoice_number}. Thank you!',
            ],
            'invoice_overdue' => [
                'name' => 'Invoice Overdue',
                'message' => 'Invoice #{invoice_number} ({currency}{total}) is overdue. Please pay immediately.',
            ],
            'invoice_reminder' => [
                'name' => 'Invoice Reminder',
                'message' => 'Reminder: Invoice #{invoice_number} ({currency}{total}) due {due_date}.',
            ],
            'invoice_first_overdue' => [
                'name' => 'First Overdue Notice',
                'message' => 'OVERDUE: Invoice #{invoice_number} ({currency}{total}). Service may be suspended.',
            ],
            'invoice_second_overdue' => [
                'name' => 'Second Overdue Notice',
                'message' => 'URGENT: Invoice #{invoice_number} ({currency}{total}) overdue. Suspension imminent.',
            ],
            'invoice_third_overdue' => [
                'name' => 'Third Overdue Notice',
                'message' => 'FINAL NOTICE: Invoice #{invoice_number}. Service will be terminated.',
            ],
            'payment_confirmation' => [
                'name' => 'Payment Confirmation',
                'message' => 'Payment of {currency}{amount} processed successfully. Transaction: {transaction_id}',
            ],
            'payment_failed' => [
                'name' => 'Payment Failed',
                'message' => 'Payment failed for invoice #{invoice_number}. Please update payment method.',
            ],

            // Quotes
            'quote_created' => [
                'name' => 'Quote Created',
                'message' => 'Quote #{quote_number} for {currency}{total} has been sent. View: {quote_url}',
            ],
            'quote_accepted' => [
                'name' => 'Quote Accepted',
                'message' => 'Quote #{quote_number} has been accepted. Thank you!',
            ],

            // Domains
            'domain_registered' => [
                'name' => 'Domain Registered',
                'message' => 'Domain {domain} registered successfully! Expires: {expiry_date}',
            ],
            'domain_transfer_initiated' => [
                'name' => 'Domain Transfer Started',
                'message' => 'Transfer of {domain} has been initiated. EPP code may be required.',
            ],
            'domain_transfer_completed' => [
                'name' => 'Domain Transfer Complete',
                'message' => 'Domain {domain} transfer completed successfully!',
            ],
            'domain_transfer_failed' => [
                'name' => 'Domain Transfer Failed',
                'message' => 'Domain {domain} transfer failed. Contact support for assistance.',
            ],
            'domain_renewed' => [
                'name' => 'Domain Renewed',
                'message' => 'Domain {domain} renewed. New expiry: {expiry_date}',
            ],
            'domain_expiry' => [
                'name' => 'Domain Expiring',
                'message' => 'URGENT: Domain {domain} expires on {expiry_date}. Renew now to avoid loss!',
            ],
            'domain_renewal_notice' => [
                'name' => 'Domain Renewal Notice',
                'message' => 'Domain {domain} expires {expiry_date}. Renew: {renewal_url}',
            ],

            // Services
            'service_welcome' => [
                'name' => 'Service Welcome',
                'message' => 'Your {product_name} is now active! Access at: {service_url}',
            ],
            'service_suspended' => [
                'name' => 'Service Suspended',
                'message' => '{product_name} has been suspended. Pay outstanding invoices to restore.',
            ],
            'service_unsuspended' => [
                'name' => 'Service Restored',
                'message' => '{product_name} has been restored. Thank you for your payment!',
            ],
            'service_cancelled' => [
                'name' => 'Service Cancelled',
                'message' => '{product_name} has been cancelled. We\'re sorry to see you go.',
            ],
            'service_upgrade' => [
                'name' => 'Service Upgrade',
                'message' => '{product_name} upgraded successfully. New features now available.',
            ],

            // Tickets
            'ticket_opened' => [
                'name' => 'Ticket Opened',
                'message' => 'Ticket #{ticket_id} opened: {ticket_subject}. We\'ll respond shortly.',
            ],
            'ticket_reply' => [
                'name' => 'Ticket Reply',
                'message' => 'New reply on ticket #{ticket_id}. View: {ticket_url}',
            ],
            'ticket_closed' => [
                'name' => 'Ticket Closed',
                'message' => 'Ticket #{ticket_id} has been closed. Rate our support: {rating_url}',
            ],

            // Affiliate
            'affiliate_activated' => [
                'name' => 'Affiliate Activated',
                'message' => 'Your affiliate account is now active! Your referral link: {affiliate_url}',
            ],
            'affiliate_withdrawal' => [
                'name' => 'Affiliate Withdrawal',
                'message' => 'Withdrawal of {currency}{amount} requested. Processing...',
            ],
        ];
    }

    /**
     * Send notification to client
     */
    public static function sendClientNotification(
        int $clientId,
        string $notificationType,
        array $mergeData = []
    ): array {
        // Get client phone number
        $client = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->first();

        if (!$client) {
            return ['success' => false, 'error' => 'Client not found'];
        }

        // Get phone from client or custom field
        $phone = self::getClientPhone($client);
        if (empty($phone)) {
            return ['success' => false, 'error' => 'No phone number'];
        }

        // Get template
        $template = Capsule::table('mod_sms_notification_templates')
            ->where('notification_type', $notificationType)
            ->where('status', 'active')
            ->first();

        if (!$template) {
            return ['success' => false, 'error' => 'Template not found or disabled'];
        }

        // Check opt-in
        $settings = Capsule::table('mod_sms_settings')
            ->where('client_id', $clientId)
            ->first();

        if ($settings) {
            if (empty($settings->accept_sms)) {
                return ['success' => false, 'error' => 'Client opted out'];
            }
            $enabledTypes = json_decode($settings->enabled_notifications ?? '[]', true);
            if (!empty($enabledTypes) && !in_array($notificationType, $enabledTypes)) {
                return ['success' => false, 'error' => 'Notification type not enabled'];
            }
        }

        // Merge client data
        $mergeData = array_merge([
            'first_name' => $client->firstname,
            'last_name' => $client->lastname,
            'company' => $client->companyname,
            'email' => $client->email,
        ], $mergeData);

        // Process template
        $message = TemplateService::processTemplate($template->message, $mergeData);

        // Send via MessageService
        return MessageService::send($clientId, $phone, $message, 'notification');
    }

    /**
     * Send admin notification
     */
    public static function sendAdminNotification(string $event, array $data = []): array
    {
        // Get admins who want this notification
        $admins = Capsule::table('mod_sms_admin_notifications')
            ->where('event', $event)
            ->where('enabled', 1)
            ->get();

        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($admins as $adminNotif) {
            $admin = Capsule::table('tbladmins')
                ->where('id', $adminNotif->admin_id)
                ->first();

            if (!$admin) continue;

            // Get admin phone from settings
            $phone = self::getAdminPhone($adminNotif->admin_id);
            if (empty($phone)) continue;

            // Build message
            $message = self::buildAdminMessage($event, $data);

            // Send SMS
            $result = MessageService::sendDirect($phone, $message);

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }

            $results[] = [
                'admin_id' => $adminNotif->admin_id,
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
            ];
        }

        return [
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Build admin notification message
     */
    private static function buildAdminMessage(string $event, array $data): string
    {
        $templates = [
            'new_order' => 'New order #{order_id} from {client_name} - {currency}{total}',
            'order_paid' => 'Order #{order_id} paid - {currency}{total}',
            'new_ticket' => 'New ticket #{ticket_id}: {subject} - Priority: {priority}',
            'ticket_reply' => 'Reply on #{ticket_id}: {subject}',
            'ticket_escalated' => 'ESCALATED: Ticket #{ticket_id} - {subject}',
            'client_login' => 'Client login: {email} from {ip}',
            'admin_login' => 'Admin login: {username} from {ip}',
            'cancellation_request' => 'Cancellation: {product_name} (Client: {client_name})',
            'service_suspended' => 'Suspended: {product_name} - Client: {client_name}',
            'service_failed' => 'FAILED: {product_name} setup - {error}',
            'domain_renewal_failed' => 'Domain renewal failed: {domain} - {error}',
            'low_credit' => 'Low credit alert: {client_name} - Balance: {currency}{balance}',
            'fraud_order' => 'FRAUD ALERT: Order #{order_id} - Risk: {risk_score}',
        ];

        $template = $templates[$event] ?? "Admin notification: {$event}";

        // Replace placeholders
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Get client phone number
     */
    public static function getClientPhone($client): string
    {
        // First check module settings for custom field
        $customFieldId = self::getModuleSetting('phone_custom_field');

        if ($customFieldId) {
            $customValue = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $customFieldId)
                ->where('relid', $client->id)
                ->value('value');

            if (!empty($customValue)) {
                return self::normalizePhone($customValue);
            }
        }

        // Fall back to default phone field
        $phone = $client->phonenumber ?? '';

        // Combine country code if needed
        if (!empty($phone)) {
            // Try to get country code
            $countryCode = self::getCountryPhoneCode($client->country ?? '');
            if ($countryCode && strpos($phone, '+') !== 0 && strpos($phone, $countryCode) !== 0) {
                $phone = $countryCode . ltrim($phone, '0');
            }
        }

        return self::normalizePhone($phone);
    }

    /**
     * Get admin phone number
     */
    public static function getAdminPhone(int $adminId): string
    {
        $phone = Capsule::table('mod_sms_admin_notifications')
            ->where('admin_id', $adminId)
            ->value('phone');

        return self::normalizePhone($phone ?? '');
    }

    /**
     * Normalize phone number
     */
    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
            return '';
        }

        return $phone;
    }

    /**
     * Get country phone code
     */
    private static function getCountryPhoneCode(string $countryCode): string
    {
        $codes = [
            'US' => '+1', 'CA' => '+1', 'GB' => '+44', 'UK' => '+44',
            'AU' => '+61', 'DE' => '+49', 'FR' => '+33', 'IN' => '+91',
            'NG' => '+234', 'KE' => '+254', 'ZA' => '+27', 'GH' => '+233',
            'BR' => '+55', 'MX' => '+52', 'ES' => '+34', 'IT' => '+39',
            'NL' => '+31', 'BE' => '+32', 'CH' => '+41', 'AT' => '+43',
            'SE' => '+46', 'NO' => '+47', 'DK' => '+45', 'FI' => '+358',
            'PL' => '+48', 'PT' => '+351', 'IE' => '+353', 'SG' => '+65',
            'MY' => '+60', 'PH' => '+63', 'ID' => '+62', 'TH' => '+66',
            'JP' => '+81', 'KR' => '+82', 'CN' => '+86', 'HK' => '+852',
            'TW' => '+886', 'NZ' => '+64', 'AE' => '+971', 'SA' => '+966',
            'EG' => '+20', 'PK' => '+92', 'BD' => '+880', 'RU' => '+7',
        ];

        return $codes[strtoupper($countryCode)] ?? '';
    }

    /**
     * Get module setting
     */
    private static function getModuleSetting(string $key)
    {
        return Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', $key)
            ->value('value');
    }

    /**
     * Save admin notification preferences
     */
    public static function saveAdminNotifications(int $adminId, array $events, string $phone): bool
    {
        // Delete existing
        Capsule::table('mod_sms_admin_notifications')
            ->where('admin_id', $adminId)
            ->delete();

        // Insert new
        foreach ($events as $event) {
            Capsule::table('mod_sms_admin_notifications')->insert([
                'admin_id' => $adminId,
                'event' => $event,
                'phone' => $phone,
                'enabled' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    /**
     * Get admin notification preferences
     */
    public static function getAdminNotifications(int $adminId): array
    {
        return Capsule::table('mod_sms_admin_notifications')
            ->where('admin_id', $adminId)
            ->where('enabled', 1)
            ->pluck('event')
            ->toArray();
    }
}
