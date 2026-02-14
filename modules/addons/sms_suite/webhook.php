<?php
/**
 * SMS Suite - Webhook Handler
 *
 * Receives delivery receipts and inbound messages from gateways
 *
 * URL: /modules/addons/sms_suite/webhook.php?gateway=twilio
 */

// Capture raw input BEFORE WHMCS init (php://input can only be read once)
$_SMS_RAW_INPUT = file_get_contents('php://input');
define('SMS_RAW_BODY', $_SMS_RAW_INPUT);

// Bootstrap WHMCS
$whmcsPath = dirname(__DIR__, 3);
require_once $whmcsPath . '/init.php';

use WHMCS\Database\Capsule;

// ---- API Routing: if 'route' param is present, handle as API request ----
if (isset($_GET['route'])) {
    // Parse body from pre-captured raw input
    $_API_METHOD = $_SERVER['REQUEST_METHOD'];
    $_API_PARAMS = [];
    if (in_array($_API_METHOD, ['POST', 'PUT'])) {
        // Use the raw body captured at top of file (before WHMCS init consumed php://input)
        $body = $GLOBALS['_SMS_RAW_INPUT'] ?? '';
        if (empty($body)) $body = file_get_contents('php://input');


        if (!empty($body)) {
            $json = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $_API_PARAMS = $json;
            }
        }
        if (empty($_API_PARAMS) && !empty($_POST)) {
            $_API_PARAMS = $_POST;
        }
    }
    $safeGetParams = $_GET;
    unset($safeGetParams['route'], $safeGetParams['api_key'], $safeGetParams['api_secret'], $safeGetParams['key'], $safeGetParams['secret'], $safeGetParams['token']);
    $_API_PARAMS = array_merge($safeGetParams, $_API_PARAMS);

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    // CORS
    $corsAllowed = false;
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $allowedOrigins = Capsule::table('mod_sms_settings')
            ->where('setting', 'api_cors_origins')
            ->value('value');
        if (!empty($allowedOrigins)) {
            $originList = array_map('trim', explode(',', $allowedOrigins));
            if (in_array($_SERVER['HTTP_ORIGIN'], $originList, true)) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
                header('Access-Control-Allow-Credentials: true');
                $corsAllowed = true;
            }
        }
    }
    if ($_API_METHOD === 'OPTIONS') {
        if ($corsAllowed) {
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: X-API-Key, X-API-Secret, Authorization, Content-Type');
            header('Access-Control-Max-Age: 86400');
        }
        exit(0);
    }

    // Load module helpers (encrypt/decrypt) and API classes
    require_once __DIR__ . '/sms_suite.php';
    require_once __DIR__ . '/lib/Api/ApiKeyService.php';
    require_once __DIR__ . '/lib/Api/ApiController.php';

    $endpoint = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $_GET['route']);

    $controller = new \SMSSuite\Api\ApiController();
    $response = $controller->handle($_API_METHOD, $endpoint, $_API_PARAMS);
    http_response_code($controller->getHttpCode());
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
// ---- End API Routing ----

// Log incoming webhook
$gatewayType = $_GET['gateway'] ?? 'unknown';
$gatewayIdParam = isset($_GET['gw_id']) ? (int)$_GET['gw_id'] : null;
$rawPayload = $_SMS_RAW_INPUT;
$payload = [];

// Meta WhatsApp webhook verification (GET challenge)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_verify_token'], $_GET['hub_challenge'])) {
    // Look up gateway to verify token
    $verifyGw = null;
    if ($gatewayIdParam) {
        $verifyGw = Capsule::table('mod_sms_gateways')->where('id', $gatewayIdParam)->first();
    } else {
        $verifyGw = Capsule::table('mod_sms_gateways')
            ->where('type', $gatewayType)->where('status', 1)->first();
    }

    if ($verifyGw && !empty($verifyGw->webhook_token) && $_GET['hub_verify_token'] === $verifyGw->webhook_token) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $_GET['hub_challenge'];
        exit;
    }
    http_response_code(403);
    echo 'Verification failed';
    exit;
}

// Parse payload
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $payload = json_decode($rawPayload, true) ?: [];
} else {
    // Form-encoded
    parse_str($rawPayload, $payload);
    $payload = array_merge($payload, $_POST, $_GET);
}

// Resolve gateway record: by gw_id (unique per-client) or by type (legacy)
if ($gatewayIdParam) {
    $gatewayRecord = Capsule::table('mod_sms_gateways')
        ->where('id', $gatewayIdParam)
        ->where('status', 1)
        ->first();
    // Override gatewayType from the resolved record
    if ($gatewayRecord) {
        $gatewayType = $gatewayRecord->type;
    }
} else {
    $gatewayRecord = Capsule::table('mod_sms_gateways')
        ->where('type', $gatewayType)
        ->where('status', 1)
        ->first();
}

if ($gatewayRecord && empty($gatewayRecord->webhook_token)) {
    logActivity('SMS Suite: WARNING — Gateway "' . $gatewayRecord->type . '" (ID: ' . $gatewayRecord->id . ') has no webhook_token configured. Webhook signature verification is skipped.');
}

if ($gatewayRecord && !empty($gatewayRecord->webhook_token)) {
    // Build headers array from $_SERVER
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $headerName = str_replace('_', '-', substr($key, 5));
            $headers[$headerName] = $value;
        }
    }

    // Load gateway class for verification
    $baseDir = __DIR__ . '/lib/Gateways/';
    require_once $baseDir . 'GatewayInterface.php';
    require_once $baseDir . 'AbstractGateway.php';

    $verifyClass = null;
    switch ($gatewayType) {
        case 'twilio':
            require_once $baseDir . 'TwilioGateway.php';
            $verifyClass = new \SMSSuite\Gateways\TwilioGateway(0, []);
            break;
        case 'plivo':
            require_once $baseDir . 'PlivoGateway.php';
            $verifyClass = new \SMSSuite\Gateways\PlivoGateway(0, []);
            break;
        case 'vonage':
        case 'nexmo':
            require_once $baseDir . 'VonageGateway.php';
            $verifyClass = new \SMSSuite\Gateways\VonageGateway(0, []);
            break;
        case 'infobip':
            require_once $baseDir . 'InfobipGateway.php';
            $verifyClass = new \SMSSuite\Gateways\InfobipGateway(0, []);
            break;
        case 'airtouch':
        case 'airtouch_kenya':
            require_once $baseDir . 'AirtouchGateway.php';
            $verifyClass = new \SMSSuite\Gateways\AirtouchGateway(0, []);
            break;
        case 'generic':
            require_once $baseDir . 'GenericHttpGateway.php';
            $verifyClass = new \SMSSuite\Gateways\GenericHttpGateway(0, []);
            break;
        case 'telegram':
            require_once $baseDir . 'TelegramGateway.php';
            $verifyClass = new \SMSSuite\Gateways\TelegramGateway(0, []);
            break;
        case 'messenger':
            require_once $baseDir . 'MessengerGateway.php';
            $verifyClass = new \SMSSuite\Gateways\MessengerGateway(0, []);
            break;
        default:
            // Use base class for token check
            $verifyClass = new class(0, []) extends \SMSSuite\Gateways\AbstractGateway {
                public function send(string $to, string $message, array $options = []): array { return []; }
                public function getBalance(): ?float { return null; }
            };
            break;
    }

    if (!$verifyClass->verifyWebhook($headers, $rawPayload, $gatewayRecord->webhook_token)) {
        // Log the failed attempt
        Capsule::table('mod_sms_webhooks_inbox')->insertGetId([
            'gateway_type' => $gatewayType,
            'payload' => json_encode($payload),
            'raw_payload' => $rawPayload,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'processed' => false,
            'error' => 'Webhook signature verification failed',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        http_response_code(403);
        echo json_encode(['error' => 'Webhook verification failed']);
        exit;
    }
}

// Store in inbox for processing
$inboxId = Capsule::table('mod_sms_webhooks_inbox')->insertGetId([
    'gateway_id' => $gatewayRecord ? $gatewayRecord->id : null,
    'gateway_type' => $gatewayType,
    'payload' => json_encode($payload),
    'raw_payload' => $rawPayload,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'processed' => false,
    'created_at' => date('Y-m-d H:i:s'),
]);

// Make gateway ID available to processing functions
$resolvedGatewayId = $gatewayRecord ? $gatewayRecord->id : null;

// Try to process immediately
try {
    $result = processWebhook($gatewayType, $payload, $inboxId, $resolvedGatewayId);

    if ($result['processed']) {
        Capsule::table('mod_sms_webhooks_inbox')
            ->where('id', $inboxId)
            ->update([
                'processed' => true,
                'processed_at' => date('Y-m-d H:i:s'),
            ]);
    }

    // Return appropriate response for gateway
    http_response_code(200);
    if ($gatewayType === 'twilio') {
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }

} catch (Exception $e) {
    Capsule::table('mod_sms_webhooks_inbox')
        ->where('id', $inboxId)
        ->update(['error' => $e->getMessage()]);

    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}

/**
 * Process webhook based on gateway type
 */
function processWebhook(string $gatewayType, array $payload, int $inboxId, ?int $resolvedGatewayId = null): array
{
    // Load gateway classes
    $baseDir = __DIR__ . '/lib/Gateways/';
    require_once $baseDir . 'GatewayInterface.php';
    require_once $baseDir . 'AbstractGateway.php';

    $gatewayClass = null;

    switch ($gatewayType) {
        case 'twilio':
            require_once $baseDir . 'TwilioGateway.php';
            $gatewayClass = \SMSSuite\Gateways\TwilioGateway::class;
            break;

        case 'plivo':
            require_once $baseDir . 'PlivoGateway.php';
            $gatewayClass = \SMSSuite\Gateways\PlivoGateway::class;
            break;

        case 'vonage':
        case 'nexmo':
            require_once $baseDir . 'VonageGateway.php';
            $gatewayClass = \SMSSuite\Gateways\VonageGateway::class;
            break;

        case 'infobip':
            require_once $baseDir . 'InfobipGateway.php';
            $gatewayClass = \SMSSuite\Gateways\InfobipGateway::class;
            break;

        case 'airtouch':
        case 'airtouch_kenya':
            require_once $baseDir . 'AirtouchGateway.php';
            $gatewayClass = \SMSSuite\Gateways\AirtouchGateway::class;
            break;

        case 'generic':
            require_once $baseDir . 'GenericHttpGateway.php';
            $gatewayClass = \SMSSuite\Gateways\GenericHttpGateway::class;
            break;

        case 'meta_whatsapp':
        case 'facebook_whatsapp':
            // Meta Cloud API webhook — process directly
            return processMetaWhatsAppWebhook($payload, $inboxId, $resolvedGatewayId);

        case 'telegram':
            return processTelegramWebhook($payload, $inboxId, $resolvedGatewayId);

        case 'messenger':
            return processMessengerWebhook($payload, $inboxId, $resolvedGatewayId);

        default:
            // Try generic processing
            return processGenericWebhook($payload, $inboxId);
    }

    // Create gateway instance (without config - just for parsing)
    $gateway = new $gatewayClass(0, []);

    // Try DLR first
    $dlr = $gateway->parseDeliveryReceipt($payload);
    if ($dlr) {
        return handleDLR($dlr, $inboxId);
    }

    // Try inbound message
    $inbound = $gateway->parseInboundMessage($payload);
    if ($inbound) {
        return handleInbound($inbound, $gatewayType, $inboxId);
    }

    return ['processed' => false, 'reason' => 'Unknown webhook type'];
}

/**
 * Process generic webhook (try common patterns)
 */
function processGenericWebhook(array $payload, int $inboxId): array
{
    // Common DLR patterns
    $messageIdKeys = ['messageId', 'message_id', 'MessageSid', 'msgid', 'smsid', 'id'];
    $statusKeys = ['status', 'Status', 'MessageStatus', 'dlr_status', 'state'];

    $messageId = null;
    $status = null;

    foreach ($messageIdKeys as $key) {
        if (isset($payload[$key])) {
            $messageId = $payload[$key];
            break;
        }
    }

    foreach ($statusKeys as $key) {
        if (isset($payload[$key])) {
            $status = $payload[$key];
            break;
        }
    }

    if ($messageId && $status) {
        require_once __DIR__ . '/lib/Gateways/GatewayInterface.php';

        $dlr = new \SMSSuite\Gateways\DLRResult($messageId, \SMSSuite\Gateways\DLRResult::normalizeStatus($status));
        $dlr->rawPayload = $payload;

        return handleDLR($dlr, $inboxId);
    }

    return ['processed' => false, 'reason' => 'Could not parse webhook'];
}

/**
 * Handle delivery receipt
 */
function handleDLR(\SMSSuite\Gateways\DLRResult $dlr, int $inboxId): array
{
    require_once __DIR__ . '/lib/Core/SegmentCounter.php';
    require_once __DIR__ . '/lib/Core/MessageService.php';

    $updated = \SMSSuite\Core\MessageService::updateStatus(
        $dlr->messageId,
        $dlr->status,
        $dlr->errorMessage
    );

    if ($updated) {
        // Check if part of campaign and update stats
        $message = Capsule::table('mod_sms_messages')
            ->where('provider_message_id', $dlr->messageId)
            ->first();

        if ($message && $message->campaign_id) {
            require_once __DIR__ . '/lib/Campaigns/CampaignService.php';
            \SMSSuite\Campaigns\CampaignService::updateDeliveredCount($message->campaign_id);
        }

        // If failed, consider refund
        if ($message && ($dlr->status === 'failed' || $dlr->status === 'undelivered')) {
            require_once __DIR__ . '/lib/Billing/BillingService.php';
            \SMSSuite\Billing\BillingService::refund($message->id);
        }

        return ['processed' => true, 'type' => 'dlr', 'message_id' => $dlr->messageId];
    }

    return ['processed' => false, 'reason' => 'Message not found'];
}

/**
 * Handle inbound message
 */
function handleInbound(\SMSSuite\Gateways\InboundResult $inbound, string $gatewayType, int $inboxId): array
{
    // Find the gateway to get client context
    $gateway = Capsule::table('mod_sms_gateways')
        ->where('type', $gatewayType)
        ->where('status', 1)
        ->first();

    $clientId = 0; // Default to admin/system

    // Try to find client by phone number (from their contacts or recent messages)
    if ($inbound->to) {
        $senderIdMatch = Capsule::table('mod_sms_sender_ids')
            ->where('sender_id', $inbound->to)
            ->where('status', 'active')
            ->first();

        if ($senderIdMatch) {
            $clientId = $senderIdMatch->client_id;
        }
    }

    // Store inbound message
    // For conversation tracking: to_number always stores the customer/remote phone
    // For inbound: from = customer, to = our sender ID
    // So we store: to_number = customer phone (from), sender_id = our number (to)
    $messageId = Capsule::table('mod_sms_messages')->insertGetId([
        'client_id' => $clientId,
        'gateway_id' => $gateway ? $gateway->id : null,
        'channel' => 'sms',
        'direction' => 'inbound',
        'sender_id' => $inbound->to,      // Our sender ID that received the message
        'to_number' => $inbound->from,    // Customer phone (for conversation grouping)
        'message' => $inbound->message,
        'media_url' => $inbound->mediaUrl,
        'provider_message_id' => $inbound->messageId,
        'status' => 'received',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Track in chatbox for unified inbox
    require_once __DIR__ . '/lib/WhatsApp/WhatsAppService.php';
    \SMSSuite\WhatsApp\WhatsAppService::trackMessageInChatbox(
        $clientId,
        $inbound->from,
        $messageId,
        'inbound',
        'sms',
        $gateway ? $gateway->id : null
    );

    // Check for opt-out keywords
    $optOutKeywords = ['STOP', 'UNSUBSCRIBE', 'OPTOUT', 'OPT OUT', 'CANCEL', 'END', 'QUIT'];
    $messageUpper = strtoupper(trim($inbound->message));
    $customerPhone = $inbound->from; // Customer's phone number

    if (in_array($messageUpper, $optOutKeywords)) {
        // Add to opt-out list
        $exists = Capsule::table('mod_sms_optouts')
            ->where('phone', $customerPhone)
            ->exists();

        if (!$exists) {
            Capsule::table('mod_sms_optouts')->insert([
                'phone' => $customerPhone,
                'reason' => 'Keyword: ' . $messageUpper,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            logActivity("SMS Suite: Opt-out received from {$customerPhone}");
        }
    }

    // AI Chatbot auto-reply for SMS
    $chatbotFile = __DIR__ . '/lib/AI/ChatbotService.php';
    if (file_exists($chatbotFile) && !empty($inbound->message) && !in_array($messageUpper, $optOutKeywords)) {
        require_once $chatbotFile;
        $gwClientId = $gateway ? ($gateway->client_id ?? null) : null;
        $gwId = $gateway ? $gateway->id : null;
        if (\SMSSuite\AI\ChatbotService::shouldAutoReply($gwClientId ? (int)$gwClientId : null, $gwId ? (int)$gwId : null, 'sms')) {
            $reply = \SMSSuite\AI\ChatbotService::generateReply($inbound->message, null, $gwClientId ? (int)$gwClientId : null);
            if ($reply && $gateway) {
                // Send SMS reply back
                require_once __DIR__ . '/lib/Core/SegmentCounter.php';
                require_once __DIR__ . '/lib/Core/MessageService.php';
                \SMSSuite\Core\MessageService::send($clientId, $inbound->from, $reply, [
                    'channel' => 'sms',
                    'gateway_id' => $gateway->id,
                    'sender_id' => $inbound->to,
                    'send_now' => true,
                ]);
            }
        }
    }

    return ['processed' => true, 'type' => 'inbound', 'message_id' => $messageId];
}

/**
 * Process Meta WhatsApp Cloud API webhook
 *
 * Meta sends: { object: "whatsapp_business_account", entry: [{ changes: [{ value: { ... } }] }] }
 */
function processMetaWhatsAppWebhook(array $payload, int $inboxId, ?int $resolvedGatewayId = null): array
{
    $entries = $payload['entry'] ?? [];
    if (empty($entries)) {
        return ['processed' => false, 'reason' => 'No entries in Meta webhook'];
    }

    $processed = false;

    foreach ($entries as $entry) {
        $changes = $entry['changes'] ?? [];
        foreach ($changes as $change) {
            $value = $change['value'] ?? [];
            $field = $change['field'] ?? '';

            if ($field !== 'messages') {
                continue;
            }

            // Handle delivery status updates
            $statuses = $value['statuses'] ?? [];
            foreach ($statuses as $statusUpdate) {
                $providerMsgId = $statusUpdate['id'] ?? null;
                $status = $statusUpdate['status'] ?? null; // sent, delivered, read, failed
                if ($providerMsgId && $status) {
                    $statusMap = [
                        'sent' => 'sent',
                        'delivered' => 'delivered',
                        'read' => 'delivered',
                        'failed' => 'failed',
                    ];
                    $mapped = $statusMap[$status] ?? $status;

                    // Update message by provider_message_id
                    Capsule::table('mod_sms_messages')
                        ->where('provider_message_id', $providerMsgId)
                        ->update([
                            'status' => $mapped,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    $processed = true;
                }
            }

            // Handle inbound messages
            $messages = $value['messages'] ?? [];
            $contacts = $value['contacts'] ?? [];
            foreach ($messages as $msg) {
                $from = $msg['from'] ?? null;
                $msgType = $msg['type'] ?? 'text';
                $text = '';
                $mediaUrl = null;

                if ($msgType === 'text') {
                    $text = $msg['text']['body'] ?? '';
                } elseif (in_array($msgType, ['image', 'video', 'audio', 'document'])) {
                    $text = $msg[$msgType]['caption'] ?? "[$msgType]";
                    $mediaUrl = $msg[$msgType]['id'] ?? null; // Media ID, needs download
                }

                if ($from) {
                    // Route to WhatsApp inbound handler
                    require_once __DIR__ . '/lib/WhatsApp/WhatsAppService.php';
                    \SMSSuite\WhatsApp\WhatsAppService::handleInbound([
                        'from' => $from,
                        'text' => $text,
                        'type' => $msgType,
                        'message_id' => $msg['id'] ?? null,
                        'timestamp' => $msg['timestamp'] ?? time(),
                        'gateway_id' => $resolvedGatewayId,
                        $msgType => $msg[$msgType] ?? null,
                    ]);
                    $processed = true;
                }
            }
        }
    }

    return ['processed' => $processed, 'type' => 'meta_whatsapp'];
}

/**
 * Process Telegram Bot API webhook
 *
 * Telegram sends Update objects: { update_id, message: { message_id, from, chat, text, ... } }
 */
function processTelegramWebhook(array $payload, int $inboxId, ?int $resolvedGatewayId = null): array
{
    $msg = $payload['message'] ?? $payload['edited_message'] ?? null;

    // Handle callback queries (button clicks)
    if (!$msg && isset($payload['callback_query'])) {
        $msg = $payload['callback_query']['message'] ?? null;
        $callbackText = $payload['callback_query']['data'] ?? '';
        if ($msg) {
            $msg['text'] = $callbackText;
        }
    }

    if (!$msg) {
        return ['processed' => false, 'reason' => 'No message in Telegram update'];
    }

    $chatId = (string)($msg['chat']['id'] ?? '');
    $text = $msg['text'] ?? $msg['caption'] ?? '';
    $msgType = 'text';
    $mediaUrl = null;

    if (!empty($chatId) && empty($text)) {
        // Check for media messages
        if (isset($msg['photo'])) {
            $msgType = 'image';
            $text = $msg['caption'] ?? '[Photo]';
            // Get highest resolution photo
            $photos = $msg['photo'];
            $mediaUrl = end($photos)['file_id'] ?? null;
        } elseif (isset($msg['video'])) {
            $msgType = 'video';
            $text = $msg['caption'] ?? '[Video]';
            $mediaUrl = $msg['video']['file_id'] ?? null;
        } elseif (isset($msg['document'])) {
            $msgType = 'document';
            $text = $msg['document']['file_name'] ?? '[Document]';
            $mediaUrl = $msg['document']['file_id'] ?? null;
        } elseif (isset($msg['voice'])) {
            $msgType = 'audio';
            $text = '[Voice message]';
            $mediaUrl = $msg['voice']['file_id'] ?? null;
        } elseif (isset($msg['audio'])) {
            $msgType = 'audio';
            $text = $msg['audio']['title'] ?? '[Audio]';
            $mediaUrl = $msg['audio']['file_id'] ?? null;
        } elseif (isset($msg['sticker'])) {
            $msgType = 'text';
            $text = $msg['sticker']['emoji'] ?? '[Sticker]';
        }
    }

    if (empty($chatId)) {
        return ['processed' => false, 'reason' => 'No chat_id in Telegram message'];
    }

    // Build contact name from Telegram user info
    $from = $msg['from'] ?? [];
    $contactName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    $username = $from['username'] ?? null;

    // Store/update Telegram session
    if ($resolvedGatewayId) {
        $existing = Capsule::table('mod_sms_telegram_sessions')
            ->where('chat_id', $chatId)
            ->where('gateway_id', $resolvedGatewayId)
            ->first();

        if ($existing) {
            Capsule::table('mod_sms_telegram_sessions')
                ->where('id', $existing->id)
                ->update([
                    'username' => $username,
                    'first_name' => $from['first_name'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            Capsule::table('mod_sms_telegram_sessions')->insert([
                'chat_id' => $chatId,
                'gateway_id' => $resolvedGatewayId,
                'username' => $username,
                'first_name' => $from['first_name'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // Determine gateway owner (client_id)
    $gateway = $resolvedGatewayId
        ? Capsule::table('mod_sms_gateways')->where('id', $resolvedGatewayId)->first()
        : null;
    $gatewayClientId = $gateway ? ($gateway->client_id ?? 0) : 0;

    // Use WhatsApp-style chatbox for Telegram conversations
    require_once __DIR__ . '/lib/WhatsApp/WhatsAppService.php';

    $result = \SMSSuite\WhatsApp\WhatsAppService::handleInbound([
        'from' => $chatId,
        'text' => $text,
        'type' => $msgType,
        'message_id' => (string)($msg['message_id'] ?? ''),
        'timestamp' => $msg['date'] ?? time(),
        'gateway_id' => $resolvedGatewayId,
        'channel' => 'telegram',
        'contact_name' => $contactName ?: ($username ? '@' . $username : 'Telegram User'),
    ]);

    // AI Chatbot auto-reply
    $chatbotFile = __DIR__ . '/lib/AI/ChatbotService.php';
    if (file_exists($chatbotFile) && !empty($text)) {
        require_once $chatbotFile;
        if (\SMSSuite\AI\ChatbotService::shouldAutoReply($gatewayClientId ?: null, $resolvedGatewayId, 'telegram')) {
            $chatboxId = $result['chatbox_id'] ?? null;
            $reply = \SMSSuite\AI\ChatbotService::generateReply($text, $chatboxId, $gatewayClientId ?: null);
            if ($reply && $chatboxId) {
                // Send reply back via Telegram
                $baseDir = __DIR__ . '/lib/Gateways/';
                require_once $baseDir . 'GatewayInterface.php';
                require_once $baseDir . 'AbstractGateway.php';
                require_once $baseDir . 'TelegramGateway.php';

                if ($gateway) {
                    require_once __DIR__ . '/sms_suite.php';
                    $creds = json_decode(sms_suite_decrypt($gateway->credentials), true) ?: [];
                    $tg = new \SMSSuite\Gateways\TelegramGateway(0, []);
                    $tg->setConfig(array_merge($creds, ['gateway_id' => $resolvedGatewayId]));

                    $dto = new \SMSSuite\Gateways\MessageDTO([
                        'to' => $chatId,
                        'message' => $reply,
                        'channel' => 'telegram',
                    ]);
                    $sendResult = $tg->send($dto);

                    if ($sendResult->success) {
                        // Store the AI reply in messages + chatbox
                        $replyMsgId = Capsule::table('mod_sms_messages')->insertGetId([
                            'client_id' => $gatewayClientId,
                            'gateway_id' => $resolvedGatewayId,
                            'channel' => 'telegram',
                            'direction' => 'outbound',
                            'to_number' => $chatId,
                            'message' => $reply,
                            'provider_message_id' => $sendResult->messageId,
                            'status' => 'sent',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);

                        Capsule::table('mod_sms_chatbox_messages')->insert([
                            'chatbox_id' => $chatboxId,
                            'message_id' => $replyMsgId,
                            'direction' => 'outbound',
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);

                        Capsule::table('mod_sms_chatbox')
                            ->where('id', $chatboxId)
                            ->update([
                                'last_message' => substr($reply, 0, 255),
                                'last_message_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                    }
                }
            }
        }
    }

    return ['processed' => true, 'type' => 'telegram', 'message_id' => $result['message_id'] ?? null];
}

/**
 * Process Facebook Messenger webhook
 *
 * Meta sends: { object: "page", entry: [{ messaging: [{ sender, recipient, message, ... }] }] }
 */
function processMessengerWebhook(array $payload, int $inboxId, ?int $resolvedGatewayId = null): array
{
    $entries = $payload['entry'] ?? [];
    if (empty($entries)) {
        return ['processed' => false, 'reason' => 'No entries in Messenger webhook'];
    }

    $processed = false;

    foreach ($entries as $entry) {
        $messagingEvents = $entry['messaging'] ?? [];
        foreach ($messagingEvents as $event) {
            $senderId = (string)($event['sender']['id'] ?? '');

            if (empty($senderId)) {
                continue;
            }

            // Delivery receipts
            if (isset($event['delivery'])) {
                $mids = $event['delivery']['mids'] ?? [];
                foreach ($mids as $mid) {
                    Capsule::table('mod_sms_messages')
                        ->where('provider_message_id', $mid)
                        ->update([
                            'status' => 'delivered',
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                }
                $processed = true;
                continue;
            }

            // Read receipts
            if (isset($event['read'])) {
                $processed = true;
                continue;
            }

            // Inbound messages
            if (isset($event['message'])) {
                $text = $event['message']['text'] ?? '';
                $msgId = $event['message']['mid'] ?? '';
                $msgType = 'text';

                // Handle attachments
                if (empty($text) && !empty($event['message']['attachments'])) {
                    $att = $event['message']['attachments'][0];
                    $attType = $att['type'] ?? 'fallback';
                    if ($attType === 'image') {
                        $msgType = 'image';
                        $text = '[Image]';
                    } elseif ($attType === 'video') {
                        $msgType = 'video';
                        $text = '[Video]';
                    } elseif ($attType === 'audio') {
                        $msgType = 'audio';
                        $text = '[Audio]';
                    } elseif ($attType === 'file') {
                        $msgType = 'document';
                        $text = '[File]';
                    } else {
                        $text = '[Attachment]';
                    }
                }

                // Get sender profile name (with session caching)
                $contactName = 'Messenger User';
                if ($resolvedGatewayId) {
                    $session = Capsule::table('mod_sms_messenger_sessions')
                        ->where('psid', $senderId)
                        ->where('gateway_id', $resolvedGatewayId)
                        ->first();

                    if ($session && !empty($session->name)) {
                        $contactName = $session->name;
                    } else {
                        // Fetch profile from Graph API
                        $gateway = Capsule::table('mod_sms_gateways')->where('id', $resolvedGatewayId)->first();
                        if ($gateway) {
                            require_once __DIR__ . '/sms_suite.php';
                            $creds = json_decode(sms_suite_decrypt($gateway->credentials), true) ?: [];
                            $token = $creds['page_access_token'] ?? '';

                            if (!empty($token)) {
                                $profileUrl = 'https://graph.facebook.com/v24.0/' . $senderId . '?fields=name,profile_pic&access_token=' . urlencode($token);
                                $ch = curl_init($profileUrl);
                                curl_setopt_array($ch, [
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_TIMEOUT => 10,
                                ]);
                                $profileResp = curl_exec($ch);
                                curl_close($ch);
                                $profileData = json_decode($profileResp, true);

                                if (!empty($profileData['name'])) {
                                    $contactName = $profileData['name'];
                                }

                                // Store/update session
                                if ($session) {
                                    Capsule::table('mod_sms_messenger_sessions')
                                        ->where('id', $session->id)
                                        ->update([
                                            'name' => $contactName,
                                            'profile_pic' => $profileData['profile_pic'] ?? null,
                                            'updated_at' => date('Y-m-d H:i:s'),
                                        ]);
                                } else {
                                    Capsule::table('mod_sms_messenger_sessions')->insert([
                                        'psid' => $senderId,
                                        'gateway_id' => $resolvedGatewayId,
                                        'name' => $contactName,
                                        'profile_pic' => $profileData['profile_pic'] ?? null,
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'updated_at' => date('Y-m-d H:i:s'),
                                    ]);
                                }
                            }
                        }
                    }
                }

                // Route to WhatsApp-style chatbox handler
                require_once __DIR__ . '/lib/WhatsApp/WhatsAppService.php';

                $result = \SMSSuite\WhatsApp\WhatsAppService::handleInbound([
                    'from' => $senderId,
                    'text' => $text,
                    'type' => $msgType,
                    'message_id' => $msgId,
                    'timestamp' => $event['timestamp'] ?? time(),
                    'gateway_id' => $resolvedGatewayId,
                    'channel' => 'messenger',
                    'contact_name' => $contactName,
                ]);

                // AI Chatbot auto-reply
                $chatbotFile = __DIR__ . '/lib/AI/ChatbotService.php';
                if (file_exists($chatbotFile) && !empty($text)) {
                    require_once $chatbotFile;
                    $gateway = $resolvedGatewayId
                        ? Capsule::table('mod_sms_gateways')->where('id', $resolvedGatewayId)->first()
                        : null;
                    $gatewayClientId = $gateway ? ($gateway->client_id ?? 0) : 0;

                    if (\SMSSuite\AI\ChatbotService::shouldAutoReply($gatewayClientId ?: null, $resolvedGatewayId, 'messenger')) {
                        $chatboxId = $result['chatbox_id'] ?? null;
                        $reply = \SMSSuite\AI\ChatbotService::generateReply($text, $chatboxId, $gatewayClientId ?: null);
                        if ($reply && $chatboxId) {
                            // Send reply via Messenger
                            $baseDir = __DIR__ . '/lib/Gateways/';
                            require_once $baseDir . 'GatewayInterface.php';
                            require_once $baseDir . 'AbstractGateway.php';
                            require_once $baseDir . 'MessengerGateway.php';

                            if ($gateway) {
                                require_once __DIR__ . '/sms_suite.php';
                                $creds = json_decode(sms_suite_decrypt($gateway->credentials), true) ?: [];
                                $mg = new \SMSSuite\Gateways\MessengerGateway(0, []);
                                $mg->setConfig(array_merge($creds, ['gateway_id' => $resolvedGatewayId]));

                                $dto = new \SMSSuite\Gateways\MessageDTO([
                                    'to' => $senderId,
                                    'message' => $reply,
                                    'channel' => 'messenger',
                                ]);
                                $sendResult = $mg->send($dto);

                                if ($sendResult->success) {
                                    $replyMsgId = Capsule::table('mod_sms_messages')->insertGetId([
                                        'client_id' => $gatewayClientId,
                                        'gateway_id' => $resolvedGatewayId,
                                        'channel' => 'messenger',
                                        'direction' => 'outbound',
                                        'to_number' => $senderId,
                                        'message' => $reply,
                                        'provider_message_id' => $sendResult->messageId,
                                        'status' => 'sent',
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'updated_at' => date('Y-m-d H:i:s'),
                                    ]);

                                    Capsule::table('mod_sms_chatbox_messages')->insert([
                                        'chatbox_id' => $chatboxId,
                                        'message_id' => $replyMsgId,
                                        'direction' => 'outbound',
                                        'created_at' => date('Y-m-d H:i:s'),
                                    ]);

                                    Capsule::table('mod_sms_chatbox')
                                        ->where('id', $chatboxId)
                                        ->update([
                                            'last_message' => substr($reply, 0, 255),
                                            'last_message_at' => date('Y-m-d H:i:s'),
                                            'updated_at' => date('Y-m-d H:i:s'),
                                        ]);
                                }
                            }
                        }
                    }
                }

                $processed = true;
            }

            // Postback handling (button clicks)
            if (isset($event['postback'])) {
                $postbackText = $event['postback']['payload'] ?? '';
                if (!empty($postbackText)) {
                    require_once __DIR__ . '/lib/WhatsApp/WhatsAppService.php';
                    \SMSSuite\WhatsApp\WhatsAppService::handleInbound([
                        'from' => $senderId,
                        'text' => $postbackText,
                        'type' => 'text',
                        'message_id' => 'postback_' . time(),
                        'timestamp' => $event['timestamp'] ?? time(),
                        'gateway_id' => $resolvedGatewayId,
                        'channel' => 'messenger',
                        'contact_name' => 'Messenger User',
                    ]);
                    $processed = true;
                }
            }
        }
    }

    return ['processed' => $processed, 'type' => 'messenger'];
}
