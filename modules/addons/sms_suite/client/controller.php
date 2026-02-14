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

    // Handle AJAX routes (return JSON and exit)
    if ($action === 'ajax_meta_token_exchange') {
        sms_suite_client_ajax_meta_token_exchange($clientId);
        return [];
    }

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

        case 'tags':
            return sms_suite_client_tags($vars, $clientId, $lang);

        case 'segments':
            return sms_suite_client_segments($vars, $clientId, $lang);

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

        case 'wa_rates':
            return sms_suite_client_wa_rates($vars, $clientId, $lang);

        case 'reports':
            return sms_suite_client_reports($vars, $clientId, $lang);

        case 'preferences':
            return sms_suite_client_preferences($vars, $clientId, $lang);

        case 'chatbot':
            return sms_suite_client_chatbot($vars, $clientId, $lang);

        case 'dashboard':
        default:
            return sms_suite_client_dashboard($vars, $clientId, $lang);
    }
}

/**
 * Get client currency info from WHMCS
 */
function sms_suite_get_client_currency($clientId)
{
    $client = Capsule::table('tblclients')->where('id', $clientId)->first();
    $currency = $client ? Capsule::table('tblcurrencies')->where('id', $client->currency)->first() : null;
    return [
        'code' => $currency ? ($currency->code ?? 'USD') : 'USD',
        'symbol' => $currency ? ($currency->prefix ?? '$') : '$',
    ];
}

/**
 * Get all active sender IDs for a client (merged from all sources)
 * Sources: mod_sms_sender_ids (client-requested), mod_sms_client_sender_ids (admin-assigned),
 *          mod_sms_settings.assigned_sender_id (legacy assignment)
 */
function sms_suite_get_client_sender_ids_merged($clientId)
{
    $seen = [];
    $result = [];

    // 1. Client-requested sender IDs (approved)
    $requested = Capsule::table('mod_sms_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->orderBy('sender_id')
        ->get();

    foreach ($requested as $sid) {
        $key = strtolower($sid->sender_id);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $result[] = $sid;
        }
    }

    // 2. Admin-assigned sender IDs (from mod_sms_client_sender_ids)
    $assigned = Capsule::table('mod_sms_client_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->orderBy('sender_id')
        ->get();

    foreach ($assigned as $sid) {
        $key = strtolower($sid->sender_id);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $sid->source = 'assigned';
            $result[] = $sid;
        }
    }

    // 3. Legacy: assigned_sender_id from mod_sms_settings
    $settings = Capsule::table('mod_sms_settings')
        ->where('client_id', $clientId)
        ->first();

    if ($settings && !empty($settings->assigned_sender_id)) {
        $key = strtolower($settings->assigned_sender_id);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $result[] = (object)[
                'id' => 0,
                'sender_id' => $settings->assigned_sender_id,
                'type' => 'alphanumeric',
                'status' => 'active',
                'source' => 'admin',
            ];
        }
    }

    return $result;
}

/**
 * Parse phone numbers from a string (supports comma, newline, tab, space, semicolon separators)
 */
function sms_suite_parse_phone_numbers($input)
{
    if (empty($input)) {
        return [];
    }

    // Split by common delimiters: comma, newline, tab, semicolon, pipe, space
    $parts = preg_split('/[,\n\r\t;|]+/', $input);
    $numbers = [];

    foreach ($parts as $part) {
        $cleaned = trim($part);
        if (empty($cleaned)) {
            continue;
        }

        // Handle WHMCS phone format: +CC.localNumber (e.g. +254.254702324532)
        if (strpos($cleaned, '.') !== false) {
            $dotParts = explode('.', $cleaned, 2);
            $cc = preg_replace('/[^0-9]/', '', $dotParts[0]);
            $local = preg_replace('/[^0-9]/', '', $dotParts[1]);
            if (!empty($cc) && !empty($local)) {
                if (strpos($local, $cc) === 0) {
                    $phone = '+' . $local;
                } else {
                    $phone = '+' . $cc . ltrim($local, '0');
                }
            } else {
                $phone = preg_replace('/[^\d+]/', '', $cleaned);
            }
        } else {
            $phone = preg_replace('/[^\d+]/', '', $cleaned);
        }

        // Basic validation: at least 7 digits
        if (preg_match('/^\+?\d{7,15}$/', $phone)) {
            $numbers[] = $phone;
        }
    }

    return $numbers;
}

/**
 * Parse phone numbers from an uploaded file (CSV, TXT, XLSX)
 */
function sms_suite_parse_recipients_file($file)
{
    $numbers = [];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['size'] > $maxSize) {
        return [];
    }

    if (in_array($ext, ['csv', 'txt'])) {
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            return [];
        }
        // Parse CSV/TXT — each line may have one number or multiple comma-separated
        $lines = preg_split('/[\r\n]+/', $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Try to parse as CSV row
            $fields = str_getcsv($line);
            foreach ($fields as $field) {
                $parsed = sms_suite_parse_phone_numbers($field);
                $numbers = array_merge($numbers, $parsed);
            }
        }
    } elseif ($ext === 'xlsx') {
        // Basic XLSX parsing (XLSX = ZIP with XML inside)
        $numbers = sms_suite_parse_xlsx_phones($file['tmp_name']);
    } elseif ($ext === 'xls') {
        // Old Excel format — not supported natively, try as CSV
        $content = file_get_contents($file['tmp_name']);
        if ($content) {
            $numbers = sms_suite_parse_phone_numbers($content);
        }
    }

    return $numbers;
}

/**
 * Extract phone numbers from XLSX file
 */
function sms_suite_parse_xlsx_phones($filepath)
{
    $numbers = [];

    $zip = new \ZipArchive();
    if ($zip->open($filepath) !== true) {
        return [];
    }

    // Read shared strings (text values are stored here)
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $xml = @simplexml_load_string($ssXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                $sharedStrings[] = (string)$si->t;
            }
        }
    }

    // Read first sheet
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml) {
        $xml = @simplexml_load_string($sheetXml);
        if ($xml && isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                foreach ($row->c as $cell) {
                    $value = '';
                    $type = (string)($cell['t'] ?? '');

                    if ($type === 's') {
                        // Shared string reference
                        $idx = (int)(string)$cell->v;
                        $value = $sharedStrings[$idx] ?? '';
                    } else {
                        $value = (string)($cell->v ?? '');
                    }

                    if (!empty($value)) {
                        $parsed = sms_suite_parse_phone_numbers($value);
                        $numbers = array_merge($numbers, $parsed);
                    }
                }
            }
        }
    }

    $zip->close();
    return $numbers;
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

    // Get all active sender IDs (merged from all sources)
    $senderIds = sms_suite_get_client_sender_ids_merged($clientId);

    // Get client currency
    $currency = sms_suite_get_client_currency($clientId);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['client_dashboard'],
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
        ],
        'templatefile' => 'templates/client/dashboard',
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
            'currency_symbol' => $currency['symbol'],
            'currency_code' => $currency['code'],
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
            $toRaw = trim($_POST['to'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $channel = $_POST['channel'] ?? 'sms';
            $senderId = $_POST['sender_id'] ?? null;
            $gatewayId = !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null;

            // Collect numbers from textarea input
            $numbers = sms_suite_parse_phone_numbers($toRaw);

            // Collect numbers from file upload (CSV, TXT, XLSX)
            if (!empty($_FILES['recipients_file']) && $_FILES['recipients_file']['error'] === UPLOAD_ERR_OK) {
                $fileNumbers = sms_suite_parse_recipients_file($_FILES['recipients_file']);
                $numbers = array_merge($numbers, $fileNumbers);
            }

            // Deduplicate
            $numbers = array_unique($numbers);

            // Validate
            if (empty($numbers)) {
                $error = $lang['error_recipient_required'];
            } elseif (empty($message)) {
                $error = $lang['error_message_required'];
            } elseif (count($numbers) > 10000) {
                $error = 'Maximum 10,000 recipients per send. Use Campaigns for larger lists.';
            } else {
                $sent = 0;
                $failed = 0;
                $errors = [];

                foreach ($numbers as $to) {
                    $result = \SMSSuite\Core\MessageService::send($clientId, $to, $message, [
                        'channel' => $channel,
                        'sender_id' => $senderId,
                        'gateway_id' => $gatewayId,
                        'send_now' => true,
                    ]);

                    if ($result['success']) {
                        $sent++;
                        if (!$segmentInfo) {
                            $segmentInfo = [
                                'segments' => $result['segments'] ?? 1,
                                'encoding' => $result['encoding'] ?? 'gsm7',
                            ];
                        }
                    } else {
                        $failed++;
                        $errMsg = $result['error'] ?? 'Unknown error';
                        if (!isset($errors[$errMsg])) {
                            $errors[$errMsg] = 0;
                        }
                        $errors[$errMsg]++;
                    }
                }

                if ($sent > 0) {
                    $success = $sent . ' message' . ($sent > 1 ? 's' : '') . ' sent successfully.';
                    if ($failed > 0) {
                        $success .= ' ' . $failed . ' failed.';
                    }
                } else {
                    $error = 'All ' . $failed . ' message(s) failed.';
                    $firstError = array_key_first($errors);
                    if ($firstError) {
                        $error .= ' Error: ' . $firstError;
                    }
                }
            }
        }
    }

    // Get available gateways (global + client-owned)
    try {
        $gateways = Capsule::table('mod_sms_gateways')
            ->where('status', 1)
            ->where(function ($query) use ($clientId) {
                $query->whereNull('client_id')
                      ->orWhere('client_id', $clientId);
            })
            ->orderBy('name')
            ->get();
    } catch (\Exception $e) {
        // Fallback if client_id column not yet added
        $gateways = Capsule::table('mod_sms_gateways')
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    // Get all active sender IDs (merged from all sources)
    $senderIds = sms_suite_get_client_sender_ids_merged($clientId);

    // Get client settings
    $settings = Capsule::table('mod_sms_settings')
        ->where('client_id', $clientId)
        ->first();

    // Get wallet balance
    $wallet = Capsule::table('mod_sms_wallet')
        ->where('client_id', $clientId)
        ->first();
    $balance = $wallet ? $wallet->balance : 0;

    // Get client currency
    $currency = sms_suite_get_client_currency($clientId);

    // Check if client has Telegram/Messenger gateways available
    $hasTelegram = false;
    $hasMessenger = false;
    foreach ($gateways as $gw) {
        if ($gw->type === 'telegram') {
            $hasTelegram = true;
        }
        if ($gw->type === 'messenger') {
            $hasMessenger = true;
        }
    }

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['menu_send_sms'],
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=send' => $lang['menu_send_sms'],
        ],
        'templatefile' => 'templates/client/send',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'gateways' => $gateways,
            'sender_ids' => $senderIds,
            'settings' => $settings,
            'balance' => $balance,
            'success' => $success,
            'error' => $error,
            'segment_info' => $segmentInfo,
            'posted' => $_POST,
            'currency_symbol' => $currency['symbol'],
            'currency_code' => $currency['code'],
            'csrf_token' => SecurityHelper::getCsrfToken(),
            'has_telegram' => $hasTelegram,
            'has_messenger' => $hasMessenger,
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
                $recipients = array_values($recipients);
            }

            // Validate segment_id belongs to this client
            $segmentId = !empty($_POST['segment_id']) ? (int)$_POST['segment_id'] : null;
            if ($segmentId) {
                $seg = Capsule::table('mod_sms_segments')->where('id', $segmentId)->where('client_id', $clientId)->first();
                if (!$seg) $segmentId = null;
            }
            // Validate recipient_tag_id belongs to this client
            $recipientTagId = !empty($_POST['recipient_tag_id']) ? (int)$_POST['recipient_tag_id'] : null;
            if ($recipientTagId) {
                $tg = Capsule::table('mod_sms_tags')->where('id', $recipientTagId)->where('client_id', $clientId)->first();
                if (!$tg) $recipientTagId = null;
            }
            // Validate group_id belongs to this client
            $groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
            if ($groupId) {
                $grp = Capsule::table('mod_sms_contact_groups')->where('id', $groupId)->where('client_id', $clientId)->first();
                if (!$grp) $groupId = null;
            }

            $result = \SMSSuite\Campaigns\CampaignService::create($clientId, [
                'name' => $_POST['name'] ?? '',
                'message' => $_POST['message'] ?? '',
                'channel' => $_POST['channel'] ?? 'sms',
                'sender_id' => $_POST['sender_id'] ?? null,
                'gateway_id' => !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null,
                'recipient_type' => $_POST['recipient_type'] ?? 'manual',
                'recipient_group_id' => $groupId,
                'segment_id' => $segmentId,
                'recipient_tag_id' => $recipientTagId,
                'recipients' => $recipients,
                'scheduled_at' => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null,
            ]);

            if ($result['success']) {
                // If send now is checked, schedule immediately
                if (!empty($_POST['send_now'])) {
                    $schedResult = \SMSSuite\Campaigns\CampaignService::schedule($result['id'], $clientId);
                    if (!empty($schedResult['success'])) {
                        $success = 'Campaign created and queued for sending (' . $schedResult['recipients'] . ' recipients).';
                    } else {
                        $error = 'Campaign created but could not be sent: ' . ($schedResult['error'] ?? 'Unknown error');
                    }
                } else {
                    $success = $lang['campaign_saved'] ?? 'Campaign saved as draft.';
                }
            } else {
                $error = $result['error'];
            }
        }

        // Update campaign
        elseif (isset($_POST['update_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            $recipients = [];
            if (!empty($_POST['recipients'])) {
                $recipients = preg_split('/[\n,]+/', $_POST['recipients']);
                $recipients = array_map('trim', $recipients);
                $recipients = array_filter($recipients);
                $recipients = array_values($recipients);
            }

            // Validate segment_id belongs to this client
            $segmentId = !empty($_POST['segment_id']) ? (int)$_POST['segment_id'] : null;
            if ($segmentId) {
                $seg = Capsule::table('mod_sms_segments')->where('id', $segmentId)->where('client_id', $clientId)->first();
                if (!$seg) $segmentId = null;
            }
            // Validate recipient_tag_id belongs to this client
            $recipientTagId = !empty($_POST['recipient_tag_id']) ? (int)$_POST['recipient_tag_id'] : null;
            if ($recipientTagId) {
                $tg = Capsule::table('mod_sms_tags')->where('id', $recipientTagId)->where('client_id', $clientId)->first();
                if (!$tg) $recipientTagId = null;
            }
            // Validate group_id belongs to this client
            $groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
            if ($groupId) {
                $grp = Capsule::table('mod_sms_contact_groups')->where('id', $groupId)->where('client_id', $clientId)->first();
                if (!$grp) $groupId = null;
            }

            $updateData = [
                'name' => $_POST['name'] ?? '',
                'message' => $_POST['message'] ?? '',
                'channel' => $_POST['channel'] ?? 'sms',
                'sender_id' => $_POST['sender_id'] ?? null,
                'gateway_id' => !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null,
                'recipient_type' => $_POST['recipient_type'] ?? 'manual',
                'recipient_group_id' => $groupId,
                'segment_id' => $segmentId,
                'recipient_tag_id' => $recipientTagId,
                'recipients' => $recipients,
            ];

            $result = \SMSSuite\Campaigns\CampaignService::update($campaignId, $clientId, $updateData);

            if ($result['success']) {
                $success = 'Campaign updated successfully.';
            } else {
                $error = $result['error'];
            }
        }

        // Send campaign (schedule a draft)
        elseif (isset($_POST['send_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            $scheduledAt = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
            $schedResult = \SMSSuite\Campaigns\CampaignService::schedule($campaignId, $clientId, $scheduledAt);
            if (!empty($schedResult['success'])) {
                $success = 'Campaign queued for sending (' . $schedResult['recipients'] . ' recipients).';
            } else {
                $error = $schedResult['error'] ?? 'Failed to send campaign.';
            }
        }

        // Pause campaign
        elseif (isset($_POST['pause_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            if (\SMSSuite\Campaigns\CampaignService::pause($campaignId, $clientId)) {
                $success = $lang['campaign_paused_msg'] ?? 'Campaign paused.';
            }
        }

        // Resume campaign
        elseif (isset($_POST['resume_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            if (\SMSSuite\Campaigns\CampaignService::resume($campaignId, $clientId)) {
                $success = $lang['campaign_started'] ?? 'Campaign resumed.';
            }
        }

        // Cancel campaign
        elseif (isset($_POST['cancel_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            if (\SMSSuite\Campaigns\CampaignService::cancel($campaignId, $clientId)) {
                $success = $lang['campaign_cancelled_msg'] ?? 'Campaign cancelled.';
            }
        }

        // Delete campaign (draft only)
        elseif (isset($_POST['delete_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            $campaign = Capsule::table('mod_sms_campaigns')
                ->where('id', $campaignId)
                ->where('client_id', $clientId)
                ->first();
            if ($campaign && in_array($campaign->status, ['draft', 'cancelled'])) {
                Capsule::table('mod_sms_campaigns')->where('id', $campaignId)->delete();
                $success = 'Campaign deleted.';
            } else {
                $error = 'Only draft or cancelled campaigns can be deleted.';
            }
        }
    }

    // Check if viewing a specific campaign
    if (!empty($_GET['campaign_id'])) {
        return sms_suite_client_campaign_detail($vars, $clientId, $lang, (int)$_GET['campaign_id'], $success, $error);
    }

    // Get campaigns
    $campaignData = \SMSSuite\Campaigns\CampaignService::getCampaigns($clientId);

    // Get groups for recipient selection
    $groups = \SMSSuite\Contacts\ContactService::getGroups($clientId);

    // Get segments and tags for recipient selection
    require_once __DIR__ . '/../lib/Campaigns/AdvancedCampaignService.php';
    require_once __DIR__ . '/../lib/Contacts/TagService.php';
    $segments = \SMSSuite\Campaigns\AdvancedCampaignService::getSegments($clientId);
    $tags = \SMSSuite\Contacts\TagService::getTags($clientId);

    // Get gateways
    $gateways = Capsule::table('mod_sms_gateways')
        ->where('status', 1)
        ->orderBy('name')
        ->get();

    // Get all active sender IDs (merged from all sources)
    $senderIds = sms_suite_get_client_sender_ids_merged($clientId);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . ($lang['campaigns'] ?? 'Campaigns'),
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=campaigns' => $lang['campaigns'] ?? 'Campaigns',
        ],
        'templatefile' => 'templates/client/campaigns',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'campaigns' => $campaignData['campaigns'],
            'groups' => $groups,
            'segments' => $segments,
            'tags' => $tags,
            'gateways' => $gateways,
            'sender_ids' => $senderIds,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Campaign detail/edit view
 */
function sms_suite_client_campaign_detail($vars, $clientId, $lang, $campaignId, $success = null, $error = null)
{
    require_once __DIR__ . '/../lib/Campaigns/CampaignService.php';
    require_once __DIR__ . '/../lib/Contacts/ContactService.php';

    $modulelink = $vars['modulelink'];

    $campaign = \SMSSuite\Campaigns\CampaignService::getCampaign($campaignId, $clientId);
    if (!$campaign) {
        // Redirect to campaigns list
        return sms_suite_client_campaigns($vars, $clientId, $lang);
    }

    // Decode recipient list
    $recipientList = json_decode($campaign->recipient_list ?? '[]', true) ?: [];

    // Get groups for recipient selection
    $groups = \SMSSuite\Contacts\ContactService::getGroups($clientId);

    // Get gateways
    $gateways = Capsule::table('mod_sms_gateways')
        ->where('status', 1)
        ->orderBy('name')
        ->get();

    // Get sender IDs
    $senderIds = sms_suite_get_client_sender_ids_merged($clientId);

    // Get campaign messages (sent log)
    $messages = Capsule::table('mod_sms_messages')
        ->where('campaign_id', $campaignId)
        ->orderBy('created_at', 'desc')
        ->limit(100)
        ->get()
        ->toArray();

    $isEditable = in_array($campaign->status, ['draft', 'scheduled']);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $campaign->name,
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=campaigns' => $lang['campaigns'] ?? 'Campaigns',
            '' => $campaign->name,
        ],
        'templatefile' => 'templates/client/campaign_detail',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'campaign' => $campaign,
            'recipient_list' => $recipientList,
            'recipients_text' => implode("\n", $recipientList),
            'is_editable' => $isEditable,
            'groups' => $groups,
            'gateways' => $gateways,
            'sender_ids' => $senderIds,
            'messages' => $messages,
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

        // Import contacts (file upload or paste)
        elseif (isset($_POST['import_contacts'])) {
            $groupId = !empty($_POST['import_group_id']) ? (int)$_POST['import_group_id'] : null;
            $imported = 0;
            $skipped = 0;

            // Handle file upload
            if (!empty($_FILES['import_file']['tmp_name'])) {
                $file = $_FILES['import_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($ext === 'xlsx') {
                    // Parse Excel
                    $phones = sms_suite_parse_recipients_file($file);
                    foreach ($phones as $phone) {
                        $res = \SMSSuite\Contacts\ContactService::createContact($clientId, [
                            'phone' => $phone, 'group_id' => $groupId,
                        ]);
                        if (!empty($res['success'])) { $imported++; } else { $skipped++; }
                    }
                } else {
                    // CSV/TXT
                    $csvData = file_get_contents($file['tmp_name']);
                    $result = \SMSSuite\Contacts\ContactService::importCsv($clientId, $csvData, $groupId);
                    $imported = $result['imported'];
                    $skipped = $result['skipped'];
                }
            }

            // Handle pasted numbers
            if (!empty($_POST['paste_numbers'])) {
                $phones = sms_suite_parse_phone_numbers($_POST['paste_numbers']);
                foreach ($phones as $phone) {
                    $res = \SMSSuite\Contacts\ContactService::createContact($clientId, [
                        'phone' => $phone, 'group_id' => $groupId,
                    ]);
                    if (!empty($res['success'])) { $imported++; } else { $skipped++; }
                }
            }

            if ($imported > 0 || $skipped > 0) {
                $success = sprintf('%d contacts imported, %d skipped (duplicates/invalid).', $imported, $skipped);
            } else {
                $error = 'No contacts to import. Please upload a file or paste phone numbers.';
            }
        }

        // Legacy import CSV support
        elseif (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file'];
            $csvData = file_get_contents($file['tmp_name']);
            $groupId = !empty($_POST['import_group_id']) ? (int)$_POST['import_group_id'] : null;
            $result = \SMSSuite\Contacts\ContactService::importCsv($clientId, $csvData, $groupId);
            $success = sprintf('%d contacts imported, %d skipped.', $result['imported'], $result['skipped']);
        }

        // Assign tag to contact
        elseif (isset($_POST['assign_tag'])) {
            require_once __DIR__ . '/../lib/Contacts/TagService.php';
            $contactId = (int)$_POST['contact_id'];
            $tagId = (int)$_POST['tag_id'];
            if (\SMSSuite\Contacts\TagService::assignTag($contactId, $tagId, $clientId)) {
                $success = $lang['tag_assigned'] ?? 'Tag assigned successfully.';
            } else {
                $error = 'Failed to assign tag.';
            }
        }

        // Remove tag from contact
        elseif (isset($_POST['remove_tag'])) {
            require_once __DIR__ . '/../lib/Contacts/TagService.php';
            $contactId = (int)$_POST['contact_id'];
            $tagId = (int)$_POST['tag_id'];
            if (\SMSSuite\Contacts\TagService::removeTag($contactId, $tagId, $clientId)) {
                $success = $lang['tag_removed'] ?? 'Tag removed successfully.';
            } else {
                $error = 'Failed to remove tag.';
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

    // Load tags for contact tag display
    require_once __DIR__ . '/../lib/Contacts/TagService.php';
    $allTags = \SMSSuite\Contacts\TagService::getTags($clientId);

    // Build contact_tags map: contact_id => [tag objects]
    $contactTags = [];
    if (!empty($contactData['contacts'])) {
        foreach ($contactData['contacts'] as $contact) {
            $contactTags[$contact->id] = \SMSSuite\Contacts\TagService::getContactTags($contact->id, $clientId);
        }
    }

    $totalPages = ceil($contactData['total'] / $limit);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['contacts'],
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=contacts' => $lang['contacts'],
        ],
        'templatefile' => 'templates/client/contacts',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'contacts' => $contactData['contacts'],
            'groups' => $groups,
            'all_tags' => $allTags,
            'contact_tags' => $contactTags,
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
 * Contact groups page
 */
function sms_suite_client_contact_groups($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Contacts/ContactService.php';

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
        // Import contacts to group
        elseif (isset($_POST['import_to_group'])) {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $imported = 0;
            $skipped = 0;

            // File upload
            if (!empty($_FILES['import_file']['tmp_name'])) {
                $file = $_FILES['import_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($ext === 'xlsx') {
                    $phones = sms_suite_parse_recipients_file($file);
                    foreach ($phones as $phone) {
                        $res = \SMSSuite\Contacts\ContactService::createContact($clientId, [
                            'phone' => $phone, 'group_id' => $groupId,
                        ]);
                        if (!empty($res['success'])) { $imported++; } else { $skipped++; }
                    }
                } else {
                    $csvData = file_get_contents($file['tmp_name']);
                    $result = \SMSSuite\Contacts\ContactService::importCsv($clientId, $csvData, $groupId);
                    $imported = $result['imported'];
                    $skipped = $result['skipped'];
                }
            }

            // Pasted numbers
            if (!empty($_POST['paste_numbers'])) {
                $phones = sms_suite_parse_phone_numbers($_POST['paste_numbers']);
                foreach ($phones as $phone) {
                    $res = \SMSSuite\Contacts\ContactService::createContact($clientId, [
                        'phone' => $phone, 'group_id' => $groupId,
                    ]);
                    if (!empty($res['success'])) { $imported++; } else { $skipped++; }
                }
            }

            if ($imported > 0 || $skipped > 0) {
                $success = sprintf('%d contacts imported to group, %d skipped.', $imported, $skipped);
            } else {
                $error = 'No contacts to import.';
            }
        }
        // Add existing contact to group
        elseif (isset($_POST['add_contact_to_group'])) {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $contactId = (int)($_POST['contact_id'] ?? 0);
            Capsule::table('mod_sms_contacts')
                ->where('id', $contactId)
                ->where('client_id', $clientId)
                ->update(['group_id' => $groupId, 'updated_at' => date('Y-m-d H:i:s')]);
            $success = 'Contact added to group.';
        }
        // Remove contact from group
        elseif (isset($_POST['remove_from_group'])) {
            $contactId = (int)($_POST['contact_id'] ?? 0);
            Capsule::table('mod_sms_contacts')
                ->where('id', $contactId)
                ->where('client_id', $clientId)
                ->update(['group_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);
            $success = 'Contact removed from group.';
        }
    }

    // Check if viewing a specific group
    if (!empty($_GET['group_id'])) {
        return sms_suite_client_group_detail($vars, $clientId, $lang, (int)$_GET['group_id'], $success, $error);
    }

    // Get groups with contact counts
    $groups = Capsule::table('mod_sms_contact_groups as g')
        ->leftJoin(Capsule::raw('(SELECT group_id, COUNT(*) as contact_count FROM mod_sms_contacts WHERE client_id = ' . (int)$clientId . ' GROUP BY group_id) as c'), 'g.id', '=', 'c.group_id')
        ->where('g.client_id', $clientId)
        ->select(['g.*', Capsule::raw('COALESCE(c.contact_count, 0) as contact_count')])
        ->orderBy('g.name')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . ($lang['contact_groups'] ?? 'Groups'),
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=contact_groups' => $lang['contact_groups'] ?? 'Groups',
        ],
        'templatefile' => 'templates/client/contact_groups',
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
 * Tags management page
 */
function sms_suite_client_tags($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Contacts/TagService.php';

    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        }
        // Create tag
        elseif (isset($_POST['create_tag'])) {
            $result = \SMSSuite\Contacts\TagService::createTag($clientId, [
                'name' => $_POST['tag_name'] ?? '',
                'color' => $_POST['tag_color'] ?? '#667eea',
                'description' => $_POST['description'] ?? '',
            ]);
            if ($result['success']) {
                $success = $lang['tag_saved'] ?? 'Tag created successfully.';
            } else {
                $error = $result['error'];
            }
        }
        // Update tag
        elseif (isset($_POST['update_tag'])) {
            $tagId = (int)($_POST['tag_id'] ?? 0);
            $result = \SMSSuite\Contacts\TagService::updateTag($tagId, $clientId, [
                'name' => $_POST['tag_name'] ?? '',
                'color' => $_POST['tag_color'] ?? '#667eea',
                'description' => $_POST['description'] ?? '',
            ]);
            if ($result['success']) {
                $success = $lang['tag_saved'] ?? 'Tag updated successfully.';
            } else {
                $error = $result['error'];
            }
        }
        // Delete tag
        elseif (isset($_POST['delete_tag'])) {
            $tagId = (int)($_POST['tag_id'] ?? 0);
            if (\SMSSuite\Contacts\TagService::deleteTag($tagId, $clientId)) {
                $success = $lang['tag_deleted'] ?? 'Tag deleted successfully.';
            } else {
                $error = 'Failed to delete tag.';
            }
        }
    }

    $tags = \SMSSuite\Contacts\TagService::getTags($clientId);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . ($lang['tags'] ?? 'Tags'),
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=tags' => $lang['tags'] ?? 'Tags',
        ],
        'templatefile' => 'templates/client/tags',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'tags' => $tags,
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Segments management page
 */
function sms_suite_client_segments($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Campaigns/AdvancedCampaignService.php';
    require_once __DIR__ . '/../lib/Contacts/TagService.php';
    require_once __DIR__ . '/../lib/Contacts/ContactService.php';

    $modulelink = $vars['modulelink'];
    $success = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = 'Security token invalid. Please refresh and try again.';
        }
        // Create segment
        elseif (isset($_POST['create_segment'])) {
            $conditions = [];
            if (!empty($_POST['conditions']) && is_array($_POST['conditions'])) {
                foreach ($_POST['conditions'] as $cond) {
                    if (!empty($cond['field']) && !empty($cond['operator'])) {
                        // Validate tag/group ownership in conditions
                        $value = $cond['value'] ?? '';
                        if ($cond['field'] === 'tag' && !empty($value)) {
                            $tag = Capsule::table('mod_sms_tags')->where('id', (int)$value)->where('client_id', $clientId)->first();
                            if (!$tag) continue;
                        }
                        if ($cond['field'] === 'group_id' && !empty($value)) {
                            $grp = Capsule::table('mod_sms_contact_groups')->where('id', (int)$value)->where('client_id', $clientId)->first();
                            if (!$grp) continue;
                        }
                        $conditions[] = [
                            'field' => $cond['field'],
                            'operator' => $cond['operator'],
                            'value' => $value,
                            'logic' => 'AND',
                        ];
                    }
                }
            }

            $result = \SMSSuite\Campaigns\AdvancedCampaignService::createSegment($clientId, [
                'name' => $_POST['segment_name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'match_type' => $_POST['match_type'] ?? 'all',
                'conditions' => $conditions,
            ]);
            if ($result['success']) {
                $success = $lang['segment_saved'] ?? 'Segment created successfully.';
            } else {
                $error = $result['error'];
            }
        }
        // Delete segment
        elseif (isset($_POST['delete_segment'])) {
            $segmentId = (int)($_POST['segment_id'] ?? 0);
            $segment = Capsule::table('mod_sms_segments')
                ->where('id', $segmentId)
                ->where('client_id', $clientId)
                ->first();
            if ($segment) {
                Capsule::table('mod_sms_segment_conditions')->where('segment_id', $segmentId)->delete();
                Capsule::table('mod_sms_segments')->where('id', $segmentId)->delete();
                $success = $lang['segment_deleted'] ?? 'Segment deleted successfully.';
            } else {
                $error = 'Segment not found.';
            }
        }
        // Recalculate segment count
        elseif (isset($_POST['recalculate_segment'])) {
            $segmentId = (int)($_POST['segment_id'] ?? 0);
            $segment = Capsule::table('mod_sms_segments')
                ->where('id', $segmentId)
                ->where('client_id', $clientId)
                ->first();
            if ($segment) {
                \SMSSuite\Campaigns\AdvancedCampaignService::calculateSegmentCount($segmentId, $clientId);
                $success = $lang['segment_recalculated'] ?? 'Segment count recalculated.';
            }
        }
    }

    $segments = \SMSSuite\Campaigns\AdvancedCampaignService::getSegments($clientId);
    $tags = \SMSSuite\Contacts\TagService::getTags($clientId);
    $groups = \SMSSuite\Contacts\ContactService::getGroups($clientId);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . ($lang['segments'] ?? 'Segments'),
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=segments' => $lang['segments'] ?? 'Segments',
        ],
        'templatefile' => 'templates/client/segments',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'segments' => $segments,
            'tags' => $tags,
            'tags_json' => json_encode(array_values($tags)),
            'groups' => $groups,
            'groups_json' => json_encode(array_values($groups)),
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * Group detail view with contacts, import, and management
 */
function sms_suite_client_group_detail($vars, $clientId, $lang, $groupId, $success = null, $error = null)
{
    $modulelink = $vars['modulelink'];

    $group = Capsule::table('mod_sms_contact_groups')
        ->where('id', $groupId)
        ->where('client_id', $clientId)
        ->first();

    if (!$group) {
        return sms_suite_client_contact_groups($vars, $clientId, $lang);
    }

    // Get contacts in this group
    $contacts = Capsule::table('mod_sms_contacts')
        ->where('group_id', $groupId)
        ->where('client_id', $clientId)
        ->orderBy('created_at', 'desc')
        ->get()
        ->toArray();

    // Get contacts NOT in this group (for "add existing" feature)
    $ungroupedContacts = Capsule::table('mod_sms_contacts')
        ->where('client_id', $clientId)
        ->where(function ($q) use ($groupId) {
            $q->whereNull('group_id')->orWhere('group_id', '!=', $groupId);
        })
        ->orderBy('phone')
        ->limit(200)
        ->get()
        ->toArray();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $group->name,
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=contact_groups' => $lang['contact_groups'] ?? 'Groups',
            '' => $group->name,
        ],
        'templatefile' => 'templates/client/group_detail',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'group' => $group,
            'contacts' => $contacts,
            'ungrouped_contacts' => $ungroupedContacts,
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

    // Get client-requested sender IDs (all statuses for display)
    $senderIds = \SMSSuite\Core\SenderIdService::getClientSenderIds($clientId);

    // Get admin-assigned sender IDs from mod_sms_client_sender_ids
    $assignedIds = Capsule::table('mod_sms_client_sender_ids')
        ->where('client_id', $clientId)
        ->orderBy('sender_id')
        ->get();

    // Get legacy assigned_sender_id from settings
    $settings = Capsule::table('mod_sms_settings')
        ->where('client_id', $clientId)
        ->first();

    // Build list of admin-assigned IDs (deduplicated)
    $adminSenderIds = [];
    $seenAdmin = [];

    foreach ($assignedIds as $a) {
        $key = strtolower($a->sender_id);
        if (!isset($seenAdmin[$key])) {
            $seenAdmin[$key] = true;
            $adminSenderIds[] = $a;
        }
    }

    if ($settings && !empty($settings->assigned_sender_id)) {
        $key = strtolower($settings->assigned_sender_id);
        if (!isset($seenAdmin[$key])) {
            $adminSenderIds[] = (object)[
                'id' => 0,
                'sender_id' => $settings->assigned_sender_id,
                'type' => 'alphanumeric',
                'status' => 'active',
                'created_at' => $settings->created_at ?? null,
            ];
        }
    }

    // Get pricing
    $alphaPrice = \SMSSuite\Core\SenderIdService::getPrice('alphanumeric');
    $numericPrice = \SMSSuite\Core\SenderIdService::getPrice('numeric');

    // Get client currency
    $currency = sms_suite_get_client_currency($clientId);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['sender_ids'],
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=sender_ids' => $lang['sender_ids'],
        ],
        'templatefile' => 'templates/client/sender_ids',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'sender_ids' => $senderIds,
            'admin_sender_ids' => $adminSenderIds,
            'alpha_price' => $alphaPrice,
            'numeric_price' => $numericPrice,
            'currency_symbol' => $currency['symbol'],
            'currency_code' => $currency['code'],
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
            $modulelink => $lang['module_name'],
            $modulelink . '&action=templates' => $lang['templates'],
        ],
        'templatefile' => 'templates/client/templates',
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
            $modulelink => $lang['module_name'],
            $modulelink . '&action=logs' => $lang['message_log'],
        ],
        'templatefile' => 'templates/client/logs',
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
    $apiBaseUrl = $systemUrl . '/modules/addons/sms_suite/webhook.php';

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['api_keys'],
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=api_keys' => $lang['api_keys'],
        ],
        'templatefile' => 'templates/client/api_keys',
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

    // Channel filter
    $channelFilter = $_GET['channel'] ?? 'all';
    $search = trim($_GET['search'] ?? '');

    // Get conversations from chatbox table
    $query = Capsule::table('mod_sms_chatbox')->where('client_id', $clientId);

    if ($channelFilter !== 'all') {
        $query->where('channel', $channelFilter);
    }

    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->where('phone', 'like', "%{$search}%")
              ->orWhere('contact_name', 'like', "%{$search}%");
        });
    }

    $conversations = $query->orderBy('last_message_at', 'desc')->limit(50)->get();

    // Also load legacy conversations from mod_sms_messages that may not be in chatbox yet
    // (backwards compatibility — will be covered by backfill migration)
    if ($conversations->isEmpty() && $channelFilter === 'all' && empty($search)) {
        $legacyConvs = Capsule::table('mod_sms_messages')
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

        foreach ($legacyConvs as &$conv) {
            $lastMsg = Capsule::table('mod_sms_messages')
                ->where('client_id', $clientId)
                ->where('to_number', $conv->to_number)
                ->orderBy('created_at', 'desc')
                ->first();
            $conv->last_message = $lastMsg ? substr($lastMsg->message, 0, 60) : '';
            $conv->last_direction = $lastMsg ? ($lastMsg->direction ?? 'outbound') : 'outbound';
            $conv->channel = $lastMsg ? ($lastMsg->channel ?? 'sms') : 'sms';
            $conv->id = null; // No chatbox ID — use legacy phone link
            $conv->phone = $conv->to_number;
            $contact = Capsule::table('mod_sms_contacts')
                ->where('client_id', $clientId)
                ->where('phone', $conv->to_number)
                ->first();
            $conv->contact_name = $contact ? trim($contact->first_name . ' ' . $contact->last_name) : null;
        }
        $conversations = $legacyConvs;
    }

    // Channel counts for filter tabs
    $channelCounts = [
        'all' => Capsule::table('mod_sms_chatbox')->where('client_id', $clientId)->count(),
        'sms' => Capsule::table('mod_sms_chatbox')->where('client_id', $clientId)->where('channel', 'sms')->count(),
        'whatsapp' => Capsule::table('mod_sms_chatbox')->where('client_id', $clientId)->where('channel', 'whatsapp')->count(),
        'telegram' => Capsule::table('mod_sms_chatbox')->where('client_id', $clientId)->where('channel', 'telegram')->count(),
        'messenger' => Capsule::table('mod_sms_chatbox')->where('client_id', $clientId)->where('channel', 'messenger')->count(),
    ];

    // Get sender IDs for new message
    $senderIds = Capsule::table('mod_sms_client_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->get();

    return [
        'pagetitle' => $lang['module_name'] . ' - Inbox',
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=inbox' => 'Inbox',
        ],
        'templatefile' => 'templates/client/inbox',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'conversations' => $conversations,
            'sender_ids' => $senderIds,
            'channel_filter' => $channelFilter,
            'channel_counts' => $channelCounts,
            'search' => $search,
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
    $chatboxId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $phone = preg_replace('/[^0-9+]/', '', $_GET['phone'] ?? '');
    $success = null;
    $error = null;

    // Resolve chatbox
    $chatbox = null;
    if ($chatboxId) {
        $chatbox = Capsule::table('mod_sms_chatbox')
            ->where('id', $chatboxId)
            ->where('client_id', $clientId)
            ->first();
        if ($chatbox) {
            $phone = $chatbox->phone;
        }
    }

    // Legacy fallback: look up by phone
    if (!$chatbox && !empty($phone)) {
        $chatbox = Capsule::table('mod_sms_chatbox')
            ->where('phone', $phone)
            ->where('client_id', $clientId)
            ->first();
        if ($chatbox) {
            $chatboxId = $chatbox->id;
        }
    }

    if (empty($phone)) {
        header('Location: ' . $modulelink . '&action=inbox');
        exit;
    }

    $channel = $chatbox ? ($chatbox->channel ?? 'sms') : 'sms';

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
                // Use chatbox reply for WA/TG/Messenger channels, direct send for SMS
                if ($chatboxId && in_array($channel, ['whatsapp', 'telegram', 'messenger'])) {
                    require_once __DIR__ . '/../lib/WhatsApp/WhatsAppService.php';
                    $result = \SMSSuite\WhatsApp\WhatsAppService::replyToConversation($chatboxId, $message);
                } else {
                    require_once __DIR__ . '/../lib/Core/MessageService.php';
                    $result = \SMSSuite\Core\MessageService::send($clientId, $phone, $message, [
                        'channel' => $channel,
                        'sender_id' => $senderId,
                        'send_now' => true,
                    ]);
                }

                if ($result['success']) {
                    $success = 'Message sent!';
                } else {
                    $error = $result['error'] ?? 'Failed to send message.';
                }
            }
        }
    }

    // Get messages via chatbox if available
    if ($chatboxId) {
        $messages = Capsule::table('mod_sms_chatbox_messages as cm')
            ->join('mod_sms_messages as m', 'cm.message_id', '=', 'm.id')
            ->where('cm.chatbox_id', $chatboxId)
            ->select('m.*', 'cm.direction')
            ->orderBy('m.created_at', 'asc')
            ->limit(100)
            ->get();

        // Mark chatbox as read
        Capsule::table('mod_sms_chatbox')
            ->where('id', $chatboxId)
            ->update(['unread_count' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    } else {
        // Fallback: direct query
        $messages = Capsule::table('mod_sms_messages')
            ->where('client_id', $clientId)
            ->where('to_number', $phone)
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        // Mark inbound as read
        Capsule::table('mod_sms_messages')
            ->where('client_id', $clientId)
            ->where('to_number', $phone)
            ->where('direction', 'inbound')
            ->where('status', '!=', 'read')
            ->update(['status' => 'read', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    // Check WhatsApp 24h window
    $windowExpired = false;
    if ($channel === 'whatsapp' && $chatbox && $chatbox->last_message_at) {
        $lastMessage = strtotime($chatbox->last_message_at);
        $windowExpired = (time() > $lastMessage + (24 * 60 * 60));
    }

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
        'pagetitle' => $lang['module_name'] . ' - Chat with ' . ($contact ? trim($contact->first_name . ' ' . $contact->last_name) : ($chatbox->contact_name ?? $phone)),
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=inbox' => 'Inbox',
            '' => 'Conversation',
        ],
        'templatefile' => 'templates/client/conversation',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'phone' => $phone,
            'contact' => $contact,
            'chatbox' => $chatbox,
            'chatbox_id' => $chatboxId,
            'channel' => $channel,
            'messages' => $messages,
            'sender_ids' => $senderIds,
            'window_expired' => $windowExpired,
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
            $modulelink => $lang['module_name'],
            $modulelink . '&action=billing' => $lang['billing'],
        ],
        'templatefile' => 'templates/client/billing',
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

    // Get client currency
    $currency = sms_suite_get_client_currency($clientId);

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . $lang['reports'],
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=reports' => $lang['reports'],
        ],
        'templatefile' => 'templates/client/reports',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'summary' => $summary,
            'daily_stats' => $dailyStats,
            'top_destinations' => $topDestinations,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'currency_symbol' => $currency['symbol'],
            'currency_code' => $currency['code'],
        ],
    ];
}

/**
 * Notification Preferences page - Client opt-in/opt-out for SMS notifications
 */
function sms_suite_client_preferences($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../sms_suite.php';
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
        if (!SecurityHelper::verifyCsrfPost()) {
            $error = $lang['error_csrf'] ?? 'Security token invalid. Please try again.';
        } else {
            $acceptSms = isset($_POST['accept_sms']) ? 1 : 0;
            $acceptMarketing = isset($_POST['accept_marketing_sms']) ? 1 : 0;
            $acceptWhatsapp = isset($_POST['accept_whatsapp']) ? 1 : 0;
            $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');
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
                    'accept_whatsapp' => $acceptWhatsapp,
                    'whatsapp_number' => $whatsappNumber ?: null,
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
        if (!SecurityHelper::verifyCsrfPost()) {
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
        if (!SecurityHelper::verifyCsrfPost()) {
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

    // WhatsApp Business gateway (requires client_id column)
    $waGateway = null;
    $waConfig = [];
    $waColumnExists = true;

    try {
        // Test if client_id column exists by running a lightweight query
        Capsule::table('mod_sms_gateways')->whereNull('client_id')->limit(1)->first();
    } catch (\Exception $e) {
        $waColumnExists = false;
    }

    if ($waColumnExists) {
        // Handle WhatsApp Business gateway save
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_whatsapp_gateway'])) {
            if (!SecurityHelper::verifyCsrfPost()) {
                $error = $lang['error_csrf'] ?? 'Security token invalid. Please try again.';
            } else {
                $phoneNumberId = trim($_POST['wa_phone_number_id'] ?? '');
                $accessToken = trim($_POST['wa_access_token'] ?? '');
                $wabaId = trim($_POST['wa_waba_id'] ?? '');

                if (empty($phoneNumberId) || empty($accessToken) || empty($wabaId)) {
                    $error = $lang['wa_fields_required'] ?? 'All WhatsApp Business fields are required.';
                } else {
                    $credentials = sms_suite_encrypt(json_encode([
                        'phone_number_id' => $phoneNumberId,
                        'access_token' => $accessToken,
                        'waba_id' => $wabaId,
                    ]));

                    // Check if client already has a WhatsApp gateway
                    $existingGw = Capsule::table('mod_sms_gateways')
                        ->where('client_id', $clientId)
                        ->where('type', 'meta_whatsapp')
                        ->first();

                    if ($existingGw) {
                        Capsule::table('mod_sms_gateways')
                            ->where('id', $existingGw->id)
                            ->update([
                                'credentials' => $credentials,
                                'status' => 0, // Reset to pending approval
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                    } else {
                        // Build gateway name from client info
                        $gwClient = Capsule::table('tblclients')->where('id', $clientId)->first();
                        $gwName = $gwClient
                            ? trim(($gwClient->companyname ?: $gwClient->firstname . ' ' . $gwClient->lastname)) . ' WhatsApp'
                            : 'Client #' . $clientId . ' WhatsApp';

                        Capsule::table('mod_sms_gateways')->insert([
                            'client_id' => $clientId,
                            'name' => $gwName,
                            'type' => 'meta_whatsapp',
                            'channel' => 'whatsapp',
                            'status' => 0, // Pending admin approval
                            'credentials' => $credentials,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }

                    $success = $lang['wa_gateway_saved'] ?? 'WhatsApp Business credentials saved. Pending admin approval.';
                }
            }
        }

        // Handle WhatsApp gateway test connection
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_whatsapp_gateway'])) {
            if (!SecurityHelper::verifyCsrfPost()) {
                $error = $lang['error_csrf'] ?? 'Security token invalid. Please try again.';
            } else {
                // Load credentials from saved gateway
                $testGw = Capsule::table('mod_sms_gateways')
                    ->where('client_id', $clientId)
                    ->where('type', 'meta_whatsapp')
                    ->first();

                if (!$testGw || empty($testGw->credentials)) {
                    $error = $lang['wa_save_first'] ?? 'Please save your WhatsApp credentials first.';
                } else {
                    $decrypted = sms_suite_decrypt($testGw->credentials);
                    $creds = json_decode($decrypted, true);

                    if (!$creds || empty($creds['phone_number_id']) || empty($creds['access_token'])) {
                        $error = $lang['wa_invalid_creds'] ?? 'Saved credentials are invalid. Please re-enter them.';
                    } else {
                        // Call Meta Graph API to verify phone_number_id + access_token
                        $testUrl = 'https://graph.facebook.com/v24.0/' . urlencode($creds['phone_number_id'])
                            . '?fields=verified_name,display_phone_number,quality_rating&access_token=' . urlencode($creds['access_token']);

                        $ch = curl_init($testUrl);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 15,
                            CURLOPT_SSL_VERIFYPEER => true,
                        ]);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curlErr = curl_error($ch);
                        curl_close($ch);

                        if ($curlErr) {
                            $error = 'Connection failed: ' . $curlErr;
                        } elseif ($httpCode === 200) {
                            $data = json_decode($response, true);
                            $verifiedName = $data['verified_name'] ?? 'Unknown';
                            $displayPhone = $data['display_phone_number'] ?? 'Unknown';
                            $quality = $data['quality_rating'] ?? 'N/A';
                            $success = "Connection successful! Business: {$verifiedName} | Phone: {$displayPhone} | Quality: {$quality}";
                        } else {
                            $data = json_decode($response, true);
                            $metaError = $data['error']['message'] ?? 'Unknown error';
                            $error = "Meta API error (HTTP {$httpCode}): {$metaError}";
                        }
                    }
                }
            }
        }

        // Handle WhatsApp phone registration
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_whatsapp_phone'])) {
            if (!SecurityHelper::verifyCsrfPost()) {
                $error = $lang['error_csrf'] ?? 'Security token invalid. Please try again.';
            } else {
                $regGw = Capsule::table('mod_sms_gateways')
                    ->where('client_id', $clientId)
                    ->where('type', 'meta_whatsapp')
                    ->first();

                if (!$regGw || empty($regGw->credentials)) {
                    $error = 'Please save your WhatsApp credentials first.';
                } else {
                    $decrypted = sms_suite_decrypt($regGw->credentials);
                    $creds = json_decode($decrypted, true);

                    if (empty($creds['phone_number_id']) || empty($creds['access_token'])) {
                        $error = 'Saved credentials are invalid. Please re-enter them.';
                    } else {
                        $regUrl = 'https://graph.facebook.com/v24.0/' . urlencode($creds['phone_number_id']) . '/register';
                        $ch = curl_init($regUrl);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode(['messaging_product' => 'whatsapp', 'pin' => '123456']),
                            CURLOPT_HTTPHEADER => [
                                'Authorization: Bearer ' . $creds['access_token'],
                                'Content-Type: application/json',
                            ],
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_SSL_VERIFYPEER => true,
                        ]);
                        $regResp = curl_exec($ch);
                        $regCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $regErr = curl_error($ch);
                        curl_close($ch);

                        if ($regErr) {
                            $error = 'Connection error: ' . $regErr;
                        } elseif ($regCode === 200) {
                            $success = 'Phone number registered with WhatsApp Cloud API successfully! You can now send messages.';
                        } else {
                            $data = json_decode($regResp, true);
                            $metaError = $data['error']['message'] ?? 'Unknown error';
                            $error = "Registration failed (HTTP {$regCode}): {$metaError}";
                        }
                    }
                }
            }
        }

        // Handle WhatsApp gateway delete
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_whatsapp_gateway'])) {
            if (!SecurityHelper::verifyCsrfPost()) {
                $error = $lang['error_csrf'] ?? 'Security token invalid. Please try again.';
            } else {
                Capsule::table('mod_sms_gateways')
                    ->where('client_id', $clientId)
                    ->where('type', 'meta_whatsapp')
                    ->delete();
                $success = $lang['wa_gateway_deleted'] ?? 'WhatsApp Business configuration removed.';
            }
        }

        // Load client's WhatsApp gateway
        $waGateway = Capsule::table('mod_sms_gateways')
            ->where('client_id', $clientId)
            ->where('type', 'meta_whatsapp')
            ->first();

        if ($waGateway && !empty($waGateway->credentials)) {
            $decrypted = sms_suite_decrypt($waGateway->credentials);
            $decoded = json_decode($decrypted, true);
            if ($decoded) {
                $waConfig = [
                    'phone_number_id' => $decoded['phone_number_id'] ?? '',
                    'access_token_masked' => !empty($decoded['access_token'])
                        ? str_repeat('*', max(0, strlen($decoded['access_token']) - 6)) . substr($decoded['access_token'], -6)
                        : '',
                    'waba_id' => $decoded['waba_id'] ?? '',
                ];
            }
        }
    }

    // Check if phone is verified
    $phoneVerified = Capsule::table('mod_sms_client_verification')
        ->where('client_id', $clientId)
        ->where('phone_verified', 1)
        ->exists();

    return [
        'pagetitle' => $lang['module_name'] . ' - ' . ($lang['preferences'] ?? 'Notification Preferences'),
        'breadcrumb' => [
            $modulelink => $lang['module_name'],
            $modulelink . '&action=preferences' => $lang['preferences'] ?? 'Preferences',
        ],
        'templatefile' => 'templates/client/preferences',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'client' => $client,
            'settings' => $settings,
            'notification_types' => $notificationTypes,
            'enabled_notifications' => $enabledNotifications,
            'phone_verified' => $phoneVerified,
            'wa_gateway' => $waGateway,
            'wa_config' => $waConfig,
            'wa_webhook_url' => $waGateway
                ? rtrim($GLOBALS['CONFIG']['SystemURL'] ?? '', '/') . '/modules/addons/sms_suite/webhook.php?gateway=meta_whatsapp&gw_id=' . $waGateway->id
                : '',
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
            'meta_app_id' => sms_suite_get_module_setting('meta_app_id'),
            'meta_config_id' => sms_suite_get_module_setting('meta_config_id'),
            'meta_configured' => !empty(sms_suite_get_module_setting('meta_app_id')) && !empty(sms_suite_get_module_setting('meta_app_secret')) && !empty(sms_suite_get_module_setting('meta_config_id')),
        ],
    ];
}

/**
 * AJAX: Exchange Meta OAuth code for access token (client area)
 */
function sms_suite_client_ajax_meta_token_exchange($clientId)
{
    header('Content-Type: application/json; charset=utf-8');

    if (!SecurityHelper::verifyCsrfPost()) {
        echo json_encode(['success' => false, 'error' => 'Invalid security token']);
        exit;
    }

    $code = trim($_POST['code'] ?? '');
    if (empty($code)) {
        echo json_encode(['success' => false, 'error' => 'No authorization code provided']);
        exit;
    }

    require_once __DIR__ . '/../sms_suite.php';
    $appId = sms_suite_get_module_setting('meta_app_id');
    $appSecret = sms_suite_get_module_setting('meta_app_secret');

    if (empty($appId) || empty($appSecret)) {
        echo json_encode(['success' => false, 'error' => 'Meta App not configured']);
        exit;
    }

    $url = 'https://graph.facebook.com/v24.0/oauth/access_token?' . http_build_query([
        'client_id' => $appId,
        'client_secret' => $appSecret,
        'code' => $code,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'error' => 'Connection error: ' . $curlError]);
        exit;
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || empty($data['access_token'])) {
        $errorMsg = $data['error']['message'] ?? 'Unknown error (HTTP ' . $httpCode . ')';
        echo json_encode(['success' => false, 'error' => 'Token exchange failed: ' . $errorMsg]);
        exit;
    }

    echo json_encode(['success' => true, 'access_token' => $data['access_token']]);
    exit;
}

/**
 * Client chatbot configuration page
 */
function sms_suite_client_chatbot($vars, $clientId, $lang)
{
    $modulelink = $vars['modulelink'];

    require_once __DIR__ . '/../lib/AI/ChatbotService.php';

    $success = null;
    $error = null;

    // Get client's gateways
    $gateways = Capsule::table('mod_sms_gateways')
        ->where('client_id', $clientId)
        ->where('status', 1)
        ->get();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_chatbot'])) {
        $gatewayId = !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null;

        // Verify gateway belongs to this client
        if ($gatewayId) {
            $gwOwned = Capsule::table('mod_sms_gateways')
                ->where('id', $gatewayId)
                ->where('client_id', $clientId)
                ->exists();
            if (!$gwOwned) {
                $error = 'Invalid gateway selected.';
            }
        }

        if (!$error) {
            $data = [
                'enabled' => !empty($_POST['enabled']) ? 1 : 0,
                'provider' => $_POST['provider'] ?? 'claude',
                'model' => $_POST['model'] ?? null,
                'system_prompt' => trim($_POST['system_prompt'] ?? ''),
                'channels' => implode(',', $_POST['channels'] ?? ['whatsapp', 'telegram']),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Handle client's own API key
            $clientApiKey = trim($_POST['api_key'] ?? '');
            if (!empty($clientApiKey)) {
                require_once __DIR__ . '/../sms_suite.php';
                $data['api_key'] = sms_suite_encrypt($clientApiKey);
            } elseif (isset($_POST['clear_api_key'])) {
                $data['api_key'] = null;
            }

            $existing = Capsule::table('mod_sms_chatbot_config')
                ->where('client_id', $clientId)
                ->where('gateway_id', $gatewayId)
                ->first();

            if ($existing) {
                Capsule::table('mod_sms_chatbot_config')
                    ->where('id', $existing->id)
                    ->update($data);
            } else {
                $data['client_id'] = $clientId;
                $data['gateway_id'] = $gatewayId;
                $data['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('mod_sms_chatbot_config')->insert($data);
            }

            $success = 'Chatbot settings saved.';
        }
    }

    // Build gateway configs list
    $gatewayConfigs = [];
    foreach ($gateways as $gw) {
        $config = Capsule::table('mod_sms_chatbot_config')
            ->where('client_id', $clientId)
            ->where('gateway_id', $gw->id)
            ->first();
        $gatewayConfigs[] = [
            'gateway' => $gw,
            'config' => $config,
        ];
    }

    // Check if system AI is configured
    $systemProvider = Capsule::table('tbladdonmodules')
        ->where('module', 'sms_suite')
        ->where('setting', 'ai_provider')
        ->value('value');
    $systemKeySet = !empty(Capsule::table('tbladdonmodules')
        ->where('module', 'sms_suite')
        ->where('setting', 'ai_api_key')
        ->value('value'));
    // AI is available if system has a provider set OR client can use their own key
    $aiAvailable = (!empty($systemProvider) && $systemProvider !== 'none') || true; // always show — clients can bring their own key

    // Build providers & models JSON for JS
    $providers = \SMSSuite\AI\ChatbotService::getProviders();
    $allModels = [];
    foreach (array_keys($providers) as $p) {
        $allModels[$p] = \SMSSuite\AI\ChatbotService::getModels($p);
    }

    return [
        'pagetitle' => ($lang['module_name'] ?? 'Messaging Suite') . ' - AI Chatbot',
        'breadcrumb' => [
            $modulelink => $lang['module_name'] ?? 'Messaging Suite',
            $modulelink . '&action=chatbot' => 'AI Chatbot',
        ],
        'templatefile' => 'templates/client/chatbot',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'gateways' => $gateways,
            'gateway_configs' => $gatewayConfigs,
            'ai_available' => $aiAvailable,
            'system_key_set' => $systemKeySet,
            'providers' => $providers,
            'all_models_json' => json_encode($allModels),
            'success' => $success,
            'error' => $error,
            'csrf_token' => SecurityHelper::getCsrfToken(),
        ],
    ];
}

/**
 * WhatsApp Platform Rates - Client-facing rate card
 */
function sms_suite_client_wa_rates($vars, $clientId, $lang)
{
    require_once __DIR__ . '/../lib/Billing/MetaPricingService.php';

    $modulelink = $vars['modulelink'];

    // Get all rates (latest effective date)
    $allRates = \SMSSuite\Billing\MetaPricingService::getAllRates();
    $mappings = \SMSSuite\Billing\MetaPricingService::getAllMappings();
    $effectiveDates = \SMSSuite\Billing\MetaPricingService::getEffectiveDates();
    $latestDate = $effectiveDates[0] ?? null;

    // Build country lookup for easy searching
    $countryLookup = [];
    foreach ($mappings as $m) {
        $countryLookup[$m->country_code] = [
            'name' => $m->country_name,
            'market' => $m->market_name,
        ];
    }

    return [
        'pagetitle' => ($lang['module_name'] ?? 'Messaging Suite') . ' - WhatsApp Rates',
        'breadcrumb' => [
            $modulelink => $lang['module_name'] ?? 'Messaging Suite',
            $modulelink . '&action=wa_rates' => 'WhatsApp Rates',
        ],
        'templatefile' => 'templates/client/wa_rates',
        'vars' => [
            'modulelink' => $modulelink,
            'lang' => $lang,
            'client_id' => $clientId,
            'all_rates' => $allRates,
            'country_lookup' => $countryLookup,
            'effective_date' => $latestDate,
        ],
    ];
}
