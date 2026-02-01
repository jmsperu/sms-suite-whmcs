<?php
/**
 * SMS Suite - Client Controller
 *
 * Handles client area routing and dispatch
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

// Load security helper
require_once __DIR__ . '/../lib/Core/SecurityHelper.php';
use SMSSuite\Core\SecurityHelper;

/**
 * Client dispatch handler
 */
function sms_suite_client_dispatch($vars, $action, $clientId, $lang)
{
    $modulelink = $vars['modulelink'];

    // Ensure client has settings record
    sms_suite_ensure_client_settings($clientId);

    // Route to appropriate action
    switch ($action) {
        case 'send':
            return sms_suite_client_send($vars, $clientId, $lang);

        case 'campaigns':
            return sms_suite_client_campaigns($vars, $clientId, $lang);

        case 'contacts':
            return sms_suite_client_contacts($vars, $clientId, $lang);

        case 'contact_groups':
            return sms_suite_client_contact_groups($vars, $clientId, $lang);

        case 'sender_ids':
            return sms_suite_client_sender_ids($vars, $clientId, $lang);

        case 'templates':
            return sms_suite_client_templates($vars, $clientId, $lang);

        case 'logs':
            return sms_suite_client_logs($vars, $clientId, $lang);

        case 'api_keys':
            return sms_suite_client_api_keys($vars, $clientId, $lang);

        case 'billing':
            return sms_suite_client_billing($vars, $clientId, $lang);

        case 'reports':
            return sms_suite_client_reports($vars, $clientId, $lang);

        case 'dashboard':
        default:
            return sms_suite_client_dashboard($vars, $clientId, $lang);
    }
}

/**
 * Ensure client has settings record
 */
function sms_suite_ensure_client_settings($clientId)
{
    $exists = Capsule::table('mod_sms_settings')
        ->where('client_id', $clientId)
        ->exists();

    if (!$exists) {
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
}

/**
 * Dashboard page
 */
function sms_suite_client_dashboard($vars, $clientId, $lang)
{
    $modulelink = $vars['modulelink'];

    // Get client stats
    $totalMessages = Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->count();

    $deliveredMessages = Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->where('status', 'delivered')
        ->count();

    $todayMessages = Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->where('created_at', '>=', date('Y-m-d 00:00:00'))
        ->count();

    // Get wallet balance
    $wallet = Capsule::table('mod_sms_wallet')
        ->where('client_id', $clientId)
        ->first();

    $balance = $wallet ? $wallet->balance : 0;

    // Get recent messages
    $recentMessages = Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    // Get active sender IDs
    $senderIds = Capsule::table('mod_sms_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['client_dashboard'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
        ],
        'templatefile' => 'dashboard',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'total_messages' => $totalMessages,
            'delivered_messages' => $deliveredMessages,
            'today_messages' => $todayMessages,
            'balance' => $balance,
            'recent_messages' => $recentMessages,
            'sender_ids' => $senderIds,
        ],
    ];
}

/**
 * Send message page
 */
function sms_suite_client_send($vars, $clientId, $lang)
{
    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;
    $segmentInfo = null;

    // Load core classes
    require_once __DIR__ . '/../lib/Core/SegmentCounter.php';
    require_once __DIR__ . '/../lib/Core/MessageService.php';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
        // Verify CSRF token
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        } else {
            $to = trim($_POST['to'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $channel = $_POST['channel'] ?? 'sms';
            $senderId = $_POST['sender_id'] ?? null;
            $gatewayId = !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null;

            // Validate
            if (empty($to)) {
                $error = $lang['error_recipient_required'];
            } elseif (empty($message)) {
                $error = $lang['error_message_required'];
            } else {
                // Send message
                $result = \SMSSuite\Core\MessageService::send($clientId, $to, $message, [
                    'channel' => $channel,
                    'sender_id' => $senderId,
                    'gateway_id' => $gatewayId,
                    'send_now' => true,
                ]);

                if ($result['success']) {
                    $success = $lang['message_sent'];
                    $segmentInfo = [
                        'segments' => $result['segments'] ?? 1,
                        'encoding' => $result['encoding'] ?? 'gsm7',
                    ];
                } else {
                    $error = $result['error'] ?? $lang['message_failed'];
                }
            }
        }
    }

    // Get available gateways
    $gateways = Capsule::table('mod_sms_gateways')
        ->where('status', 1)
        ->orderBy('name')
        ->get();

    // Get client's sender IDs
    $senderIds = Capsule::table('mod_sms_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->orderBy('sender_id')
        ->get();

    // Get client settings
    $settings = Capsule::table('mod_sms_settings')
        ->where('client_id', $clientId)
        ->first();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['menu_send_sms'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=send' => $lang['menu_send_sms'],
        ],
        'templatefile' => 'send',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'gateways' => $gateways,
            'sender_ids' => $senderIds,
            'settings' => $settings,
            'success' => $success,
            'error' => $error,
            'segment_info' => $segmentInfo,
            'posted' => $_POST,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Campaigns page - Full implementation
 */
function sms_suite_client_campaigns($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Campaigns/CampaignService.php';
    require_once __DIR__ . '/../lib/Contacts/ContactService.php';

    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        }
        // Create campaign
        elseif (isset($_POST['create_campaign'])) {
            $recipients = [];
            if (!empty($_POST['recipients'])) {
                $recipients = preg_split('/[\n,]+/', $_POST['recipients']);
                $recipients = array_map('trim', $recipients);
                $recipients = array_filter($recipients);
            }

            $result = \SMSSuite\Campaigns\CampaignService::create($clientId, [
                'name' => $_POST['name'] ?? '',
                'message' => $_POST['message'] ?? '',
                'channel' => $_POST['channel'] ?? 'sms',
                'sender_id' => $_POST['sender_id'] ?? null,
                'gateway_id' => !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null,
                'recipient_type' => $_POST['recipient_type'] ?? 'manual',
                'recipient_group_id' => !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null,
                'recipients' => $recipients,
                'scheduled_at' => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null,
            ]);

            if ($result['success']) {
                // If send now is checked, schedule immediately
                if (!empty($_POST['send_now'])) {
                    \SMSSuite\Campaigns\CampaignService::schedule($result['id'], $clientId);
                }
                $success = $lang['campaign_saved'];
            } else {
                $error = $result['error'];
            }
        }

        // Pause campaign
        elseif (isset($_POST['pause_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            if (\SMSSuite\Campaigns\CampaignService::pause($campaignId, $clientId)) {
                $success = $lang['campaign_paused_msg'];
            }
        }

        // Resume campaign
        elseif (isset($_POST['resume_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            if (\SMSSuite\Campaigns\CampaignService::resume($campaignId, $clientId)) {
                $success = $lang['campaign_started'];
            }
        }

        // Cancel campaign
        elseif (isset($_POST['cancel_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            if (\SMSSuite\Campaigns\CampaignService::cancel($campaignId, $clientId)) {
                $success = $lang['campaign_cancelled_msg'];
            }
        }
    }

    // Get campaigns
    $campaignData = \SMSSuite\Campaigns\CampaignService::getCampaigns($clientId);

    // Get groups for recipient selection
    $groups = \SMSSuite\Contacts\ContactService::getGroups($clientId);

    // Get gateways
    $gateways = Capsule::table('mod_sms_gateways')
        ->where('status', 1)
        ->orderBy('name')
        ->get();

    // Get sender IDs
    $senderIds = Capsule::table('mod_sms_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->orderBy('sender_id')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['campaigns'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=campaigns' => $lang['campaigns'],
        ],
        'templatefile' => 'campaigns',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'campaigns' => $campaignData['campaigns'],
            'groups' => $groups,
            'gateways' => $gateways,
            'sender_ids' => $senderIds,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Contacts page - Full implementation
 */
function sms_suite_client_contacts($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Contacts/ContactService.php';

    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        }
        // Add contact
        elseif (isset($_POST['add_contact'])) {
            $result = \SMSSuite\Contacts\ContactService::createContact($clientId, [
                'phone' => $_POST['phone'] ?? '',
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'group_id' => !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null,
            ]);

            if ($result['success']) {
                $success = $lang['contact_saved'];
            } else {
                $error = $result['error'];
            }
        }

        // Delete contact
        elseif (isset($_POST['delete_contact'])) {
            $contactId = (int)$_POST['contact_id'];
            if (\SMSSuite\Contacts\ContactService::deleteContact($contactId, $clientId)) {
                $success = $lang['contact_deleted'];
            } else {
                $error = 'Failed to delete contact.';
            }
        }

        // Import CSV with security validation
        elseif (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file'];
            $validation = SecurityHelper::validateCsvUpload($file);

            if (!$validation['valid']) {
                $error = $validation['error'];
            } else {
                $csvData = file_get_contents($file['tmp_name']);
                $groupId = !empty($_POST['import_group_id']) ? (int)$_POST['import_group_id'] : null;
                $result = \SMSSuite\Contacts\ContactService::importCsv($clientId, $csvData, $groupId);
                $success = sprintf('%d contacts imported, %d skipped.', $result['imported'], $result['skipped']);
            }
        }

        // Export CSV
        elseif (isset($_POST['export_csv'])) {
            $groupId = !empty($_POST['export_group_id']) ? (int)$_POST['export_group_id'] : null;
            $csv = \SMSSuite\Contacts\ContactService::exportCsv($clientId, $groupId);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="contacts_' . date('Y-m-d') . '.csv"');
            echo $csv;
            exit;
        }
    }

    // Get contacts with pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $filters = [
        'group_id' => $_GET['group_id'] ?? null,
        'search' => $_GET['search'] ?? null,
    ];

    $contactData = \SMSSuite\Contacts\ContactService::getContacts($clientId, $filters, $limit, $offset);
    $groups = \SMSSuite\Contacts\ContactService::getGroups($clientId);

    $totalPages = ceil($contactData['total'] / $limit);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['contacts'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=contacts' => $lang['contacts'],
        ],
        'templatefile' => 'contacts',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'contacts' => $contactData['contacts'],
            'groups' => $groups,
            'total' => $contactData['total'],
            'page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Contact groups page (stub)
 */
function sms_suite_client_contact_groups($vars, $clientId, $lang)
{
    $groups = Capsule::table('mod_sms_contact_groups')
        ->where('client_id', $clientId)
        ->orderBy('name')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['contact_groups'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=contact_groups' => $lang['contact_groups'],
        ],
        'templatefile' => 'contact_groups',
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'lang' => $lang,
            'client_id' => $clientId,
            'groups' => $groups,
        ],
    ];
}

/**
 * Sender IDs page - Full implementation
 */
function sms_suite_client_sender_ids($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Core/SenderIdService.php';

    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        }
        // Request new sender ID
        elseif (isset($_POST['request_sender'])) {
            $senderId = trim($_POST['sender_id'] ?? '');
            $type = $_POST['sender_type'] ?? 'alphanumeric';
            $notes = trim($_POST['notes'] ?? '');

            $result = \SMSSuite\Core\SenderIdService::request($clientId, $senderId, $type, [
                'notes' => $notes,
            ]);

            if ($result['success']) {
                if ($result['status'] === 'active') {
                    $success = $lang['sender_id_approved'];
                } else {
                    $success = $lang['sender_id_pending'];
                    // Create invoice if there's a price
                    if (!empty($result['price']) && $result['price'] > 0) {
                        $invoiceId = \SMSSuite\Core\SenderIdService::createInvoice($result['id']);
                        if ($invoiceId) {
                            $success .= ' Invoice #' . $invoiceId . ' has been generated.';
                        }
                    }
                }
            } else {
                $error = $result['error'];
            }
        }
    }

    // Get sender IDs
    $senderIds = \SMSSuite\Core\SenderIdService::getClientSenderIds($clientId);

    // Get pricing
    $alphaPrice = \SMSSuite\Core\SenderIdService::getPrice('alphanumeric');
    $numericPrice = \SMSSuite\Core\SenderIdService::getPrice('numeric');

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['sender_ids'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=sender_ids' => $lang['sender_ids'],
        ],
        'templatefile' => 'sender_ids',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'sender_ids' => $senderIds,
            'alpha_price' => $alphaPrice,
            'numeric_price' => $numericPrice,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Templates page (stub)
 */
function sms_suite_client_templates($vars, $clientId, $lang)
{
    $templates = Capsule::table('mod_sms_templates')
        ->where(function ($query) use ($clientId) {
            $query->where('client_id', $clientId)
                  ->orWhereNull('client_id');
        })
        ->orderBy('name')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['templates'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=templates' => $lang['templates'],
        ],
        'templatefile' => 'templates',
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'lang' => $lang,
            'client_id' => $clientId,
            'templates' => $templates,
        ],
    ];
}

/**
 * Message logs page (stub)
 */
function sms_suite_client_logs($vars, $clientId, $lang)
{
    $messages = Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->orderBy('created_at', 'desc')
        ->limit(100)
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['message_log'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=logs' => $lang['message_log'],
        ],
        'templatefile' => 'logs',
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'lang' => $lang,
            'client_id' => $clientId,
            'messages' => $messages,
        ],
    ];
}

/**
 * API keys page - Full implementation
 */
function sms_suite_client_api_keys($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Api/ApiKeyService.php';

    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;
    $newKey = null;

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        }
        // Create new API key
        elseif (isset($_POST['create_key'])) {
            // Rate limit API key creation (5 per hour)
            if (!SecurityHelper::checkRateLimit("api_key_create:{$clientId}", 5, 3600)) {
                $error = 'Too many API key creation attempts. Please try again later.';
            } else {
                $name = trim($_POST['key_name'] ?? '');
                $scopes = $_POST['scopes'] ?? [];
                $rateLimit = (int)($_POST['rate_limit'] ?? 60);

                if (empty($name)) {
                    $error = 'Please enter a name for this API key.';
                } else {
                    try {
                        $result = \SMSSuite\Api\ApiKeyService::generate($clientId, $name, $scopes, $rateLimit);
                        $newKey = $result;
                        $success = $lang['api_key_created'];
                    } catch (\Exception $e) {
                        $error = 'Failed to create API key.';
                        SecurityHelper::logSecurityEvent('api_key_creation_failed', ['client_id' => $clientId]);
                    }
                }
            }
        }

        // Revoke API key
        elseif (isset($_POST['revoke_key'])) {
            $keyId = (int)$_POST['key_id'];
            if (\SMSSuite\Api\ApiKeyService::revoke($keyId, $clientId)) {
                $success = $lang['api_key_revoked'];
            } else {
                $error = 'Failed to revoke API key.';
            }
        }
    }

    // Get existing keys
    $apiKeys = \SMSSuite\Api\ApiKeyService::getClientKeys($clientId);
    $availableScopes = \SMSSuite\Api\ApiKeyService::SCOPES;

    // Build API base URL
    $systemUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL'), '/');
    $apiBaseUrl = $systemUrl . '/modules/addons/sms_suite/api.php';

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['api_keys'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=api_keys' => $lang['api_keys'],
        ],
        'templatefile' => 'api_keys',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'api_keys' => $apiKeys,
            'available_scopes' => $availableScopes,
            'api_base_url' => $apiBaseUrl,
            'new_key' => $newKey,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Billing page - Full implementation
 */
function sms_suite_client_billing($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Billing/BillingService.php';

    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    // Handle top-up request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_amount'])) {
        // Verify CSRF token
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        } else {
            $amount = (float)$_POST['topup_amount'];

            if ($amount < 5) {
                $error = 'Minimum top-up amount is $5.00';
            } elseif ($amount > 10000) {
                $error = 'Maximum top-up amount is $10,000.00';
            } else {
                $invoiceId = \SMSSuite\Billing\BillingService::createTopUpInvoice($clientId, $amount);
                if ($invoiceId) {
                    // Redirect to invoice
                    header("Location: viewinvoice.php?id={$invoiceId}");
                    exit;
                } else {
                    $error = 'Failed to create invoice. Please try again.';
                }
            }
        }
    }

    // Get wallet and settings
    $wallet = \SMSSuite\Billing\BillingService::getWallet($clientId);
    $settings = \SMSSuite\Billing\BillingService::getClientSettings($clientId);
    $credits = \SMSSuite\Billing\BillingService::getTotalCredits($clientId);

    // Get transactions
    $transactionData = \SMSSuite\Billing\BillingService::getTransactionHistory($clientId, 50);

    // Get plan credits if applicable
    $planCredits = Capsule::table('mod_sms_plan_credits')
        ->where('client_id', $clientId)
        ->where('remaining', '>', 0)
        ->where('expires_at', '>', date('Y-m-d H:i:s'))
        ->orderBy('expires_at', 'asc')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['billing'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=billing' => $lang['billing'],
        ],
        'templatefile' => 'billing',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'wallet' => $wallet,
            'settings' => $settings,
            'credits' => $credits,
            'plan_credits' => $planCredits,
            'transactions' => $transactionData['transactions'],
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Reports page - Full implementation
 */
function sms_suite_client_reports($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Reports/ReportService.php';

    $modulelink = $vars['modulelink'];

    // Default date range: last 30 days
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-30 days'));

    if (!empty($_GET['start_date'])) {
        $startDate = $_GET['start_date'];
    }
    if (!empty($_GET['end_date'])) {
        $endDate = $_GET['end_date'];
    }

    // Handle export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $csv = \SMSSuite\Reports\ReportService::exportToCsv($clientId, $startDate, $endDate);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sms_report_' . date('Y-m-d') . '.csv"');
        echo $csv;
        exit;
    }

    // Get report data
    $summary = \SMSSuite\Reports\ReportService::getUsageSummary($clientId, $startDate, $endDate);
    $dailyStats = \SMSSuite\Reports\ReportService::getDailyStats($clientId, $startDate, $endDate);
    $topDestinations = \SMSSuite\Reports\ReportService::getTopDestinations($clientId, $startDate, $endDate);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['reports'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=reports' => $lang['reports'],
        ],
        'templatefile' => 'reports',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'summary' => $summary,
            'daily_stats' => $dailyStats,
            'top_destinations' => $topDestinations,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ],
    ];
}
