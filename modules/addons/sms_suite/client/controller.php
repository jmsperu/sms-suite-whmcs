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

        case 'inbox':
            return sms_suite_client_inbox($vars, $clientId, $lang);

        case 'conversation':
            return sms_suite_client_conversation($vars, $clientId, $lang);

        case 'api_keys':
            return sms_suite_client_api_keys($vars, $clientId, $lang);

        case 'billing':
            return sms_suite_client_billing($vars, $clientId, $lang);

        case 'reports':
            return sms_suite_client_reports($vars, $clientId, $lang);

        case 'preferences':
            return sms_suite_client_preferences($vars, $clientId, $lang);

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
 * Handle Sender ID document uploads
 *
 * @param int $clientId
 * @return array ['success' => bool, 'documents' => array, 'error' => string]
 */
function sms_suite_handle_sender_id_uploads(int $clientId): array
{
    $documents = [];
    $requiredDocs = ['doc_certificate', 'doc_vat', 'doc_kyc', 'doc_authorization'];
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/sender_ids/' . $clientId . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory. Please contact support.'];
        }
    }

    // Create .htaccess to protect uploads
    $htaccessPath = __DIR__ . '/../uploads/.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "Deny from all\n");
    }

    foreach ($requiredDocs as $docField) {
        if (!isset($_FILES[$docField]) || $_FILES[$docField]['error'] === UPLOAD_ERR_NO_FILE) {
            // Check if it's a required field
            if (in_array($docField, ['doc_certificate', 'doc_vat', 'doc_kyc', 'doc_authorization'])) {
                $fieldNames = [
                    'doc_certificate' => 'Certificate of Incorporation',
                    'doc_vat' => 'VAT Certificate',
                    'doc_kyc' => 'KYC Documents',
                    'doc_authorization' => 'Letter of Authorization',
                ];
                return ['success' => false, 'error' => $fieldNames[$docField] . ' is required.'];
            }
            continue;
        }

        $file = $_FILES[$docField];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension.',
            ];
            return ['success' => false, 'error' => $errorMessages[$file['error']] ?? 'Upload error.'];
        }

        // Validate file type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type for ' . $docField . '. Allowed: PDF, JPG, PNG.'];
        }

        // Validate file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File ' . $file['name'] . ' exceeds 5MB limit.'];
        }

        // Generate secure filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeExtension = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($extension));
        $timestamp = date('Ymd_His');
        $uniqueId = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $newFilename = "{$docField}_{$timestamp}_{$uniqueId}.{$safeExtension}";

        $destination = $uploadDir . $newFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save ' . $file['name'] . '. Please try again.'];
        }

        $documents[$docField] = [
            'filename' => $newFilename,
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'path' => 'uploads/sender_ids/' . $clientId . '/' . $newFilename,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];
    }

    return ['success' => true, 'documents' => $documents];
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
    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        }
        // Create group
        elseif (isset($_POST['create_group'])) {
            $name = trim($_POST['group_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $error = 'Group name is required.';
            } else {
                try {
                    Capsule::table('mod_sms_contact_groups')->insert([
                        'client_id' => $clientId,
                        'name' => $name,
                        'description' => $description,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $success = 'Contact group created successfully.';
                } catch (\Exception $e) {
                    $error = 'Failed to create group: ' . $e->getMessage();
                }
            }
        }
        // Update group
        elseif (isset($_POST['update_group'])) {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $name = trim($_POST['group_name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $error = 'Group name is required.';
            } else {
                Capsule::table('mod_sms_contact_groups')
                    ->where('id', $groupId)
                    ->where('client_id', $clientId)
                    ->update([
                        'name' => $name,
                        'description' => $description,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                $success = 'Contact group updated successfully.';
            }
        }
        // Delete group
        elseif (isset($_POST['delete_group'])) {
            $groupId = (int)($_POST['group_id'] ?? 0);

            // Check if group has contacts
            $contactCount = Capsule::table('mod_sms_contacts')
                ->where('group_id', $groupId)
                ->where('client_id', $clientId)
                ->count();

            if ($contactCount > 0) {
                $error = "Cannot delete group with {$contactCount} contacts. Remove contacts first.";
            } else {
                Capsule::table('mod_sms_contact_groups')
                    ->where('id', $groupId)
                    ->where('client_id', $clientId)
                    ->delete();
                $success = 'Contact group deleted successfully.';
            }
        }
    }

    // Get groups with contact counts
    $groups = Capsule::table('mod_sms_contact_groups as g')
        ->leftJoin(Capsule::raw('(SELECT group_id, COUNT(*) as contact_count FROM mod_sms_contacts WHERE client_id = ' . (int)$clientId . ' GROUP BY group_id) as c'), 'g.id', '=', 'c.group_id')
        ->where('g.client_id', $clientId)
        ->select(['g.*', Capsule::raw('COALESCE(c.contact_count, 0) as contact_count')])
        ->orderBy('g.name')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['contact_groups'],
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=contact_groups' => $lang['contact_groups'],
        ],
        'templatefile' => 'contact_groups',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'groups' => $groups,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
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
            $network = $_POST['network'] ?? 'all';
            $companyName = trim($_POST['company_name'] ?? '');
            $useCase = trim($_POST['use_case'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            // Validate required fields
            if (empty($companyName)) {
                $error = 'Company name is required.';
            } elseif (empty($useCase)) {
                $error = 'Use case description is required.';
            } else {
                // Handle file uploads
                $uploadResult = sms_suite_handle_sender_id_uploads($clientId);

                if (!$uploadResult['success']) {
                    $error = $uploadResult['error'];
                } else {
                    $result = \SMSSuite\Core\SenderIdService::request($clientId, $senderId, $type, [
                        'network' => $network,
                        'company_name' => $companyName,
                        'use_case' => $useCase,
                        'notes' => $notes,
                        'documents' => $uploadResult['documents'],
                    ]);

                    if ($result['success']) {
                        if ($result['status'] === 'active') {
                            $success = $lang['sender_id_approved'];
                        } else {
                            $success = 'Your Sender ID request has been submitted successfully. We will process your documents and submit to the telco for approval. This typically takes 3-7 business days.';
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
 * Inbox - Chat/Conversations list
 */
function sms_suite_client_inbox($vars, $clientId, $lang)
{
    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    // Handle new conversation
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        } elseif (isset($_POST['start_conversation'])) {
            $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $senderId = $_POST['sender_id'] ?? null;

            if (empty($phone)) {
                $error = 'Phone number is required.';
            } elseif (empty($message)) {
                $error = 'Message is required.';
            } else {
                // Send the message
                require_once __DIR__ . '/../lib/Core/MessageService.php';
                $result = \SMSSuite\Core\MessageService::send($clientId, $phone, $message, [
                    'sender_id' => $senderId,
                    'send_now' => true,
                ]);

                if ($result['success']) {
                    $success = 'Message sent! Conversation started.';
                } else {
                    $error = $result['error'] ?? 'Failed to send message.';
                }
            }
        }
    }

    // Get unique conversations (grouped by phone number)
    $conversations = Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->select([
            'to_number',
            Capsule::raw('MAX(created_at) as last_message_at'),
            Capsule::raw('COUNT(*) as message_count'),
            Capsule::raw('SUM(CASE WHEN direction = "inbound" AND status != "read" THEN 1 ELSE 0 END) as unread_count'),
        ])
        ->groupBy('to_number')
        ->orderBy('last_message_at', 'desc')
        ->limit(50)
        ->get();

    // Get last message preview for each conversation
    foreach ($conversations as &$conv) {
        $lastMsg = Capsule::table('mod_sms_messages')
            ->where('client_id', $clientId)
            ->where('to_number', $conv->to_number)
            ->orderBy('created_at', 'desc')
            ->first();
        $conv->last_message = $lastMsg ? substr($lastMsg->message, 0, 50) . (strlen($lastMsg->message) > 50 ? '...' : '') : '';
        $conv->last_direction = $lastMsg ? ($lastMsg->direction ?? 'outbound') : 'outbound';
        $conv->last_status = $lastMsg ? ($lastMsg->status ?? '') : '';

        // Try to get contact name
        $contact = Capsule::table('mod_sms_contacts')
            ->where('client_id', $clientId)
            ->where('phone', $conv->to_number)
            ->first();
        $conv->contact_name = $contact ? trim($contact->first_name . ' ' . $contact->last_name) : null;
    }

    // Get sender IDs for new message
    $senderIds = Capsule::table('mod_sms_client_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - Inbox',
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=inbox' => 'Inbox',
        ],
        'templatefile' => 'inbox',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'conversations' => $conversations,
            'sender_ids' => $senderIds,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Single conversation/chat view
 */
function sms_suite_client_conversation($vars, $clientId, $lang)
{
    $modulelink = $vars['modulelink'];
    $phone = preg_replace('/[^0-9+]/', '', $_GET['phone'] ?? '');
    $success = null;
    $error = null;

    if (empty($phone)) {
        header('Location: ' . $modulelink . '&action=inbox');
        exit;
    }

    // Handle sending reply
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid.';
        } elseif (isset($_POST['send_reply'])) {
            $message = trim($_POST['message'] ?? '');
            $senderId = $_POST['sender_id'] ?? null;

            if (empty($message)) {
                $error = 'Message cannot be empty.';
            } else {
                require_once __DIR__ . '/../lib/Core/MessageService.php';
                $result = \SMSSuite\Core\MessageService::send($clientId, $phone, $message, [
                    'sender_id' => $senderId,
                    'send_now' => true,
                ]);

                if ($result['success']) {
                    $success = 'Message sent!';
                } else {
                    $error = $result['error'] ?? 'Failed to send message.';
                }
            }
        }
    }

    // Mark inbound messages as read
    Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->where('to_number', $phone)
        ->where('direction', 'inbound')
        ->where('status', '!=', 'read')
        ->update(['status' => 'read', 'updated_at' => date('Y-m-d H:i:s')]);

    // Get messages for this conversation
    $messages = Capsule::table('mod_sms_messages')
        ->where('client_id', $clientId)
        ->where('to_number', $phone)
        ->orderBy('created_at', 'asc')
        ->limit(100)
        ->get();

    // Get contact info
    $contact = Capsule::table('mod_sms_contacts')
        ->where('client_id', $clientId)
        ->where('phone', $phone)
        ->first();

    // Get sender IDs
    $senderIds = Capsule::table('mod_sms_client_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - Chat with ' . ($contact ? trim($contact->first_name . ' ' . $contact->last_name) : $phone),
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=inbox' => 'Inbox',
            '' => 'Conversation',
        ],
        'templatefile' => 'conversation',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'phone' => $phone,
            'contact' => $contact,
            'messages' => $messages,
            'sender_ids' => $senderIds,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Billing page - Full implementation with SMS packages
 */
function sms_suite_client_billing($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Billing/BillingService.php';

    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    // Get client's currency
    $client = Capsule::table('tblclients')->where('id', $clientId)->first();
    $clientCurrency = $client ? Capsule::table('tblcurrencies')->where('id', $client->currency)->first() : null;
    $currencyCode = $clientCurrency ? ($clientCurrency->code ?? 'USD') : 'USD';
    $currencySymbol = $clientCurrency ? ($clientCurrency->prefix ?? '$') : '$';

    // Handle wallet top-up request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_amount'])) {
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        } else {
            $amount = (float)$_POST['topup_amount'];

            if ($amount < 5) {
                $error = 'Minimum top-up amount is ' . $currencySymbol . '5.00';
            } elseif ($amount > 10000) {
                $error = 'Maximum top-up amount is ' . $currencySymbol . '10,000.00';
            } else {
                $invoiceId = \SMSSuite\Billing\BillingService::createTopUpInvoice($clientId, $amount);
                if ($invoiceId) {
                    header("Location: viewinvoice.php?id={$invoiceId}");
                    exit;
                } else {
                    $error = 'Failed to create invoice. Please try again.';
                }
            }
        }
    }

    // Handle SMS package purchase
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_package'])) {
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        } else {
            $packageId = (int)$_POST['package_id'];
            $result = \SMSSuite\Billing\BillingService::purchaseCreditPackage($clientId, $packageId);

            if ($result['success']) {
                if (!empty($result['invoice_id'])) {
                    header("Location: viewinvoice.php?id={$result['invoice_id']}");
                    exit;
                } else {
                    $success = 'Package purchased successfully! Credits have been added to your account.';
                }
            } else {
                $error = $result['error'] ?? 'Failed to purchase package.';
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

    // Get available SMS packages
    $smsPackages = Capsule::table('mod_sms_credit_packages')
        ->where('status', 1)
        ->where(function($q) use ($currencyCode) {
            $q->where('currency', $currencyCode)
              ->orWhere('currency', 'USD'); // Fallback to USD
        })
        ->orderBy('credits', 'asc')
        ->get();

    // Get client's active sender IDs count
    $activeSenderIds = Capsule::table('mod_sms_client_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->count();

    // Get pending sender ID requests
    $pendingRequests = Capsule::table('mod_sms_sender_id_requests')
        ->where('client_id', $clientId)
        ->whereIn('status', ['pending', 'approved'])
        ->count();

    // Get credit balance details for graphic
    $creditBalance = Capsule::table('mod_sms_credit_balance')
        ->where('client_id', $clientId)
        ->first();

    $creditBalanceAmount = $creditBalance ? (int)$creditBalance->balance : 0;
    $totalPurchased = $creditBalance ? (int)$creditBalance->total_purchased : 0;
    $totalUsed = $creditBalance ? (int)$creditBalance->total_used : 0;
    $totalExpired = $creditBalance ? (int)$creditBalance->total_expired : 0;

    // Get credit usage by sender ID (last 30 days)
    $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
    $senderIdUsage = \SMSSuite\Billing\BillingService::getCreditUsageBySenderId($clientId, $thirtyDaysAgo);

    // Get credit usage by network (last 30 days)
    $networkUsage = \SMSSuite\Billing\BillingService::getCreditUsageByNetwork($clientId, $thirtyDaysAgo);

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
            'sms_packages' => $smsPackages,
            'active_sender_ids' => $activeSenderIds,
            'pending_requests' => $pendingRequests,
            'sender_id_usage' => $senderIdUsage,
            'network_usage' => $networkUsage,
            'credit_balance' => $creditBalanceAmount,
            'total_purchased' => $totalPurchased,
            'total_used' => $totalUsed,
            'total_expired' => $totalExpired,
            'currency_symbol' => $currencySymbol,
            'currency_code' => $currencyCode,
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

/**
 * Notification Preferences page - Client opt-in/opt-out for SMS notifications
 */
function sms_suite_client_preferences($vars, $clientId, $lang)
{
    $modulelink = $vars['modulelink'];
    $success = '';
    $error = '';

    // Get client details
    $client = Capsule::table('tblclients')
        ->where('id', $clientId)
        ->first();

    // Get current settings
    $settings = Capsule::table('mod_sms_settings')
        ->where('client_id', $clientId)
        ->first();

    // Available notification types
    $notificationTypes = [
        'client' => [
            'label' => $lang['notification_group_client'] ?? 'Account Notifications',
            'types' => [
                'client_signup' => $lang['notif_client_signup'] ?? 'Welcome SMS after registration',
                'client_password_change' => $lang['notif_password_change'] ?? 'Password change confirmation',
                'client_password_reset' => $lang['notif_password_reset'] ?? 'Password reset notifications',
            ],
        ],
        'invoice' => [
            'label' => $lang['notification_group_invoice'] ?? 'Invoice Notifications',
            'types' => [
                'invoice_created' => $lang['notif_invoice_created'] ?? 'New invoice created',
                'invoice_paid' => $lang['notif_invoice_paid'] ?? 'Invoice payment confirmation',
                'invoice_overdue' => $lang['notif_invoice_overdue'] ?? 'Invoice overdue reminder',
                'invoice_payment_reminder' => $lang['notif_payment_reminder'] ?? 'Payment reminders',
            ],
        ],
        'order' => [
            'label' => $lang['notification_group_order'] ?? 'Order Notifications',
            'types' => [
                'order_confirmation' => $lang['notif_order_confirm'] ?? 'Order confirmation',
                'order_accepted' => $lang['notif_order_accepted'] ?? 'Order accepted/activated',
                'order_cancelled' => $lang['notif_order_cancelled'] ?? 'Order cancellation',
            ],
        ],
        'service' => [
            'label' => $lang['notification_group_service'] ?? 'Service Notifications',
            'types' => [
                'service_welcome' => $lang['notif_service_welcome'] ?? 'Service activation welcome',
                'service_suspended' => $lang['notif_service_suspended'] ?? 'Service suspended alert',
                'service_unsuspended' => $lang['notif_service_unsuspended'] ?? 'Service reactivated',
                'service_cancelled' => $lang['notif_service_cancelled'] ?? 'Service cancellation',
            ],
        ],
        'domain' => [
            'label' => $lang['notification_group_domain'] ?? 'Domain Notifications',
            'types' => [
                'domain_registered' => $lang['notif_domain_registered'] ?? 'Domain registration confirmation',
                'domain_renewal' => $lang['notif_domain_renewal'] ?? 'Domain renewal reminders',
                'domain_expiry' => $lang['notif_domain_expiry'] ?? 'Domain expiry warnings',
            ],
        ],
        'ticket' => [
            'label' => $lang['notification_group_ticket'] ?? 'Support Ticket Notifications',
            'types' => [
                'ticket_opened' => $lang['notif_ticket_opened'] ?? 'New ticket confirmation',
                'ticket_replied' => $lang['notif_ticket_replied'] ?? 'Ticket reply notification',
                'ticket_closed' => $lang['notif_ticket_closed'] ?? 'Ticket closed notification',
            ],
        ],
    ];

    // Get enabled notifications (default to all enabled)
    $enabledNotifications = [];
    if (!empty($settings->enabled_notifications)) {
        $enabledNotifications = json_decode($settings->enabled_notifications, true) ?: [];
    } else {
        // Default: all enabled
        foreach ($notificationTypes as $group) {
            foreach ($group['types'] as $type => $label) {
                $enabledNotifications[] = $type;
            }
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
        // Verify CSRF token
        if (!SecurityHelper::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = $lang['error_csrf'] ?? 'Security token invalid. Please try again.';
        } else {
            $acceptSms = isset($_POST['accept_sms']) ? 1 : 0;
            $acceptMarketing = isset($_POST['accept_marketing_sms']) ? 1 : 0;
            $twoFactorEnabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
            $selectedNotifications = $_POST['notifications'] ?? [];

            // Update phone number if provided
            $phoneNumber = trim($_POST['phone_number'] ?? '');
            if (!empty($phoneNumber)) {
                Capsule::table('tblclients')
                    ->where('id', $clientId)
                    ->update(['phonenumber' => $phoneNumber]);
                $client->phonenumber = $phoneNumber;
            }

            // Update settings
            Capsule::table('mod_sms_settings')
                ->where('client_id', $clientId)
                ->update([
                    'accept_sms' => $acceptSms,
                    'accept_marketing_sms' => $acceptMarketing,
                    'two_factor_enabled' => $twoFactorEnabled,
                    'enabled_notifications' => json_encode(array_values($selectedNotifications)),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $enabledNotifications = $selectedNotifications;
            $success = $lang['preferences_saved'] ?? 'Your notification preferences have been saved.';

            // Refresh settings
            $settings = Capsule::table('mod_sms_settings')
                ->where('client_id', $clientId)
                ->first();
        }
    }

    // Handle 2FA verification request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_phone'])) {
        if (!SecurityHelper::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = $lang['error_csrf'] ?? 'Security token invalid. Please try again.';
        } else {
            require_once __DIR__ . '/../lib/Core/VerificationService.php';
            $result = \SMSSuite\Core\VerificationService::sendClientVerification($clientId, $client->phonenumber);
            if ($result['success']) {
                $success = $lang['verification_sent'] ?? 'Verification code sent to your phone number.';
            } else {
                $error = $result['error'] ?? 'Failed to send verification code.';
            }
        }
    }

    // Handle 2FA code verification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_verification'])) {
        if (!SecurityHelper::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = $lang['error_csrf'] ?? 'Security token invalid. Please try again.';
        } else {
            require_once __DIR__ . '/../lib/Core/VerificationService.php';
            $code = trim($_POST['verification_code'] ?? '');
            $result = \SMSSuite\Core\VerificationService::verifyClient($clientId, $code);
            if ($result['success']) {
                $success = $lang['phone_verified'] ?? 'Your phone number has been verified.';
            } else {
                $error = $result['error'] ?? 'Invalid or expired verification code.';
            }
        }
    }

    // Check if phone is verified
    $phoneVerified = Capsule::table('mod_sms_client_verification')
        ->where('client_id', $clientId)
        ->where('verified', 1)
        ->exists();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . ($lang['preferences'] ?? 'Notification Preferences'),
        'breadcrumb' => [
            'index.php?m=sms_suite' => $lang['module_name'],
            'index.php?m=sms_suite&action=preferences' => $lang['preferences'] ?? 'Preferences',
        ],
        'templatefile' => 'preferences',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'client' => $client,
            'settings' => $settings,
            'notification_types' => $notificationTypes,
            'enabled_notifications' => $enabledNotifications,
            'phone_verified' => $phoneVerified,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}
