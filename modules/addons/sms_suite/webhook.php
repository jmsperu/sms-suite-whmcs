<?php
/**
 * SMS Suite - Webhook Handler
 *
 * Receives delivery receipts and inbound messages from gateways
 *
 * URL: /modules/addons/sms_suite/webhook.php?gateway=twilio
 */

// Bootstrap WHMCS
$whmcsPath = dirname(__DIR__, 3);
require_once $whmcsPath . '/init.php';

use WHMCS\Database\Capsule;

// Log incoming webhook
$gatewayType = $_GET['gateway'] ?? 'unknown';
$rawPayload = file_get_contents('php://input');
$payload = [];

// Parse payload
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $payload = json_decode($rawPayload, true) ?: [];
} else {
    // Form-encoded
    parse_str($rawPayload, $payload);
    $payload = array_merge($payload, $_POST, $_GET);
}

// Verify webhook signature/token if configured
$gatewayRecord = Capsule::table('mod_sms_gateways')
    ->where('type', $gatewayType)
    ->where('status', 1)
    ->first();

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
    'gateway_type' => $gatewayType,
    'payload' => json_encode($payload),
    'raw_payload' => $rawPayload,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'processed' => false,
    'created_at' => date('Y-m-d H:i:s'),
]);

// Try to process immediately
try {
    $result = processWebhook($gatewayType, $payload, $inboxId);

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
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Process webhook based on gateway type
 */
function processWebhook(string $gatewayType, array $payload, int $inboxId): array
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

    return ['processed' => true, 'type' => 'inbound', 'message_id' => $messageId];
}
