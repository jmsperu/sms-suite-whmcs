<?php
/**
 * SMS Suite - API Controller
 *
 * Handles REST API requests
 */

namespace SMSSuite\Api;

use WHMCS\Database\Capsule;
use SMSSuite\Core\MessageService;
use SMSSuite\Core\SegmentCounter;
use Exception;

class ApiController
{
    private ?array $apiKey = null;
    private ?string $error = null;
    private int $httpCode = 200;

    /**
     * Handle incoming API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array Response data
     */
    public function handle(string $method, string $endpoint, array $params = []): array
    {
        try {
            // Authenticate
            if (!$this->authenticate()) {
                return $this->error('Invalid API credentials', 401);
            }

            // Check rate limit
            if (!ApiKeyService::checkRateLimit($this->apiKey['id'], $this->apiKey['rate_limit'])) {
                return $this->error('Rate limit exceeded', 429);
            }

            // Route to endpoint
            return match ($endpoint) {
                // Messaging
                'send' => $this->sendMessage($params),
                'send/bulk' => $this->sendBulk($params),
                'send/schedule' => $this->scheduleMessage($params),
                'status' => $this->getMessageStatus($params),
                'messages' => $this->getMessages($params),
                'segments' => $this->countSegments($params),

                // WhatsApp
                'whatsapp/send' => $this->sendWhatsApp($params),
                'whatsapp/template' => $this->sendWhatsAppTemplate($params),
                'whatsapp/media' => $this->sendWhatsAppMedia($params),

                // Contacts
                'contacts' => $method === 'GET' ? $this->getContacts($params) : $this->createContact($params),
                'contacts/groups' => $this->getContactGroups($params),
                'contacts/import' => $this->importContacts($params),

                // Campaigns
                'campaigns' => $method === 'GET' ? $this->getCampaigns($params) : $this->createCampaign($params),
                'campaigns/status' => $this->getCampaignStatus($params),
                'campaigns/pause' => $this->pauseCampaign($params),
                'campaigns/resume' => $this->resumeCampaign($params),
                'campaigns/cancel' => $this->cancelCampaign($params),

                // Sender IDs
                'senderids' => $this->getSenderIds($params),
                'senderids/request' => $this->requestSenderId($params),

                // Billing & Account
                'balance' => $this->getBalance(),
                'transactions' => $this->getTransactions($params),
                'usage' => $this->getUsageStats($params),

                // Templates
                'templates' => $method === 'GET' ? $this->getTemplates($params) : $this->createTemplate($params),

                default => $this->error('Unknown endpoint', 404),
            };

        } catch (Exception $e) {
            logActivity('SMS Suite API Error: ' . $e->getMessage());
            return $this->error('Internal server error', 500);
        }
    }

    /**
     * Authenticate API request
     *
     * Supported methods (in order of preference):
     * 1. HTTP Headers: X-API-KEY and X-API-SECRET
     * 2. Basic Authentication: base64(key_id:secret)
     *
     * SECURITY: Query parameters are NOT supported to prevent credential leakage in logs
     */
    private function authenticate(): bool
    {
        $keyId = null;
        $secret = null;

        // Method 1: Check for API key in headers (recommended)
        if (isset($_SERVER['HTTP_X_API_KEY']) && isset($_SERVER['HTTP_X_API_SECRET'])) {
            $keyId = $_SERVER['HTTP_X_API_KEY'];
            $secret = $_SERVER['HTTP_X_API_SECRET'];
        }

        // Method 2: Basic auth
        if (!$keyId && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($auth, 'Basic ') === 0) {
                $decoded = base64_decode(substr($auth, 6));
                if ($decoded !== false && strpos($decoded, ':') !== false) {
                    list($keyId, $secret) = explode(':', $decoded, 2);
                }
            }
        }

        // Validate credentials are present
        if (empty($keyId) || empty($secret)) {
            return false;
        }

        // Validate key format (must start with sms_)
        if (strpos($keyId, 'sms_') !== 0) {
            return false;
        }

        $this->apiKey = ApiKeyService::validate($keyId, $secret);
        return $this->apiKey !== null;
    }

    /**
     * Send single message
     */
    private function sendMessage(array $params): array
    {
        $channel = $params['channel'] ?? 'sms';
        $scope = $channel === 'whatsapp' ? 'send_whatsapp' : 'send_sms';

        if (!ApiKeyService::hasScope($this->apiKey, $scope)) {
            return $this->error('Insufficient permissions', 403);
        }

        $to = $params['to'] ?? null;
        $message = $params['message'] ?? null;

        if (empty($to)) {
            return $this->error('Missing required parameter: to', 400);
        }

        if (empty($message)) {
            return $this->error('Missing required parameter: message', 400);
        }

        // Load message service
        require_once dirname(__DIR__) . '/Core/SegmentCounter.php';
        require_once dirname(__DIR__) . '/Core/MessageService.php';

        $result = MessageService::send($this->apiKey['client_id'], $to, $message, [
            'channel' => $channel,
            'sender_id' => $params['sender_id'] ?? null,
            'gateway_id' => $params['gateway_id'] ?? null,
            'api_key_id' => $this->apiKey['id'],
            'send_now' => true,
        ]);

        if ($result['success']) {
            return $this->success([
                'message_id' => $result['message_id'],
                'segments' => $result['segments'] ?? 1,
                'encoding' => $result['encoding'] ?? 'gsm7',
                'status' => 'sent',
            ], 201);
        }

        return $this->error($result['error'] ?? 'Failed to send message', 400);
    }

    /**
     * Send bulk messages
     */
    private function sendBulk(array $params): array
    {
        $channel = $params['channel'] ?? 'sms';
        $scope = $channel === 'whatsapp' ? 'send_whatsapp' : 'send_sms';

        if (!ApiKeyService::hasScope($this->apiKey, $scope)) {
            return $this->error('Insufficient permissions', 403);
        }

        $recipients = $params['recipients'] ?? [];
        $message = $params['message'] ?? null;

        if (empty($recipients) || !is_array($recipients)) {
            return $this->error('Missing or invalid parameter: recipients (array expected)', 400);
        }

        if (empty($message)) {
            return $this->error('Missing required parameter: message', 400);
        }

        if (count($recipients) > 1000) {
            return $this->error('Maximum 1000 recipients per request', 400);
        }

        require_once dirname(__DIR__) . '/Core/SegmentCounter.php';
        require_once dirname(__DIR__) . '/Core/MessageService.php';

        $results = [];
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $to) {
            $result = MessageService::send($this->apiKey['client_id'], $to, $message, [
                'channel' => $channel,
                'sender_id' => $params['sender_id'] ?? null,
                'gateway_id' => $params['gateway_id'] ?? null,
                'api_key_id' => $this->apiKey['id'],
                'send_now' => false, // Queue for batch processing
            ]);

            $results[] = [
                'to' => $to,
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null,
            ];

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return $this->success([
            'total' => count($recipients),
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
        ], 201);
    }

    /**
     * Get wallet balance
     */
    private function getBalance(): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'balance')) {
            return $this->error('Insufficient permissions', 403);
        }

        $wallet = Capsule::table('mod_sms_wallet')
            ->where('client_id', $this->apiKey['client_id'])
            ->first();

        $settings = Capsule::table('mod_sms_settings')
            ->where('client_id', $this->apiKey['client_id'])
            ->first();

        return $this->success([
            'balance' => $wallet ? (float)$wallet->balance : 0,
            'currency' => $settings->currency ?? 'USD',
            'billing_mode' => $settings->billing_mode ?? 'per_segment',
        ]);
    }

    /**
     * Get message status
     */
    private function getMessageStatus(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'logs')) {
            return $this->error('Insufficient permissions', 403);
        }

        $messageId = $params['message_id'] ?? null;

        if (empty($messageId)) {
            return $this->error('Missing required parameter: message_id', 400);
        }

        $message = Capsule::table('mod_sms_messages')
            ->where('id', $messageId)
            ->where('client_id', $this->apiKey['client_id'])
            ->first();

        if (!$message) {
            return $this->error('Message not found', 404);
        }

        return $this->success([
            'message_id' => $message->id,
            'to' => $message->to_number,
            'status' => $message->status,
            'segments' => $message->segments,
            'cost' => (float)$message->cost,
            'created_at' => $message->created_at,
            'delivered_at' => $message->delivered_at,
            'error' => $message->error,
        ]);
    }

    /**
     * Get message history
     */
    private function getMessages(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'logs')) {
            return $this->error('Insufficient permissions', 403);
        }

        $limit = min((int)($params['limit'] ?? 50), 100);
        $offset = (int)($params['offset'] ?? 0);
        $status = $params['status'] ?? null;

        $query = Capsule::table('mod_sms_messages')
            ->where('client_id', $this->apiKey['client_id']);

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();

        $messages = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'to' => $msg->to_number,
                    'from' => $msg->sender_id,
                    'message' => $msg->message,
                    'channel' => $msg->channel,
                    'status' => $msg->status,
                    'segments' => $msg->segments,
                    'cost' => (float)$msg->cost,
                    'created_at' => $msg->created_at,
                    'delivered_at' => $msg->delivered_at,
                ];
            })
            ->toArray();

        return $this->success([
            'messages' => $messages,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * Count segments for a message (preview)
     */
    private function countSegments(array $params): array
    {
        $message = $params['message'] ?? '';
        $channel = $params['channel'] ?? 'sms';

        require_once dirname(__DIR__) . '/Core/SegmentCounter.php';

        $result = SegmentCounter::count($message, $channel);

        return $this->success($result->toArray());
    }

    /**
     * Get contacts
     */
    private function getContacts(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'contacts')) {
            return $this->error('Insufficient permissions', 403);
        }

        $limit = min((int)($params['limit'] ?? 50), 100);
        $offset = (int)($params['offset'] ?? 0);
        $groupId = $params['group_id'] ?? null;

        $query = Capsule::table('mod_sms_contacts')
            ->where('client_id', $this->apiKey['client_id']);

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        $total = $query->count();

        $contacts = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        return $this->success([
            'contacts' => $contacts,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * Create contact
     */
    private function createContact(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'contacts')) {
            return $this->error('Insufficient permissions', 403);
        }

        $phone = $params['phone'] ?? null;

        if (empty($phone)) {
            return $this->error('Missing required parameter: phone', 400);
        }

        $id = Capsule::table('mod_sms_contacts')->insertGetId([
            'client_id' => $this->apiKey['client_id'],
            'group_id' => $params['group_id'] ?? null,
            'phone' => $phone,
            'first_name' => $params['first_name'] ?? null,
            'last_name' => $params['last_name'] ?? null,
            'email' => $params['email'] ?? null,
            'custom_data' => json_encode($params['custom_fields'] ?? []),
            'status' => 'subscribed',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->success(['id' => $id], 201);
    }

    /**
     * Get contact groups
     */
    private function getContactGroups(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'contacts')) {
            return $this->error('Insufficient permissions', 403);
        }

        $groups = Capsule::table('mod_sms_contact_groups')
            ->where('client_id', $this->apiKey['client_id'])
            ->orderBy('name')
            ->get()
            ->toArray();

        return $this->success(['groups' => $groups]);
    }

    /**
     * Schedule a message for later delivery
     */
    private function scheduleMessage(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'send_sms')) {
            return $this->error('Insufficient permissions', 403);
        }

        $to = $params['to'] ?? null;
        $message = $params['message'] ?? null;
        $scheduledAt = $params['scheduled_at'] ?? null;

        if (empty($to) || empty($message) || empty($scheduledAt)) {
            return $this->error('Missing required parameters: to, message, scheduled_at', 400);
        }

        require_once dirname(__DIR__) . '/Campaigns/AdvancedCampaignService.php';

        $result = \SMSSuite\Campaigns\AdvancedCampaignService::scheduleMessage(
            $this->apiKey['client_id'],
            $to,
            $message,
            $scheduledAt,
            [
                'channel' => $params['channel'] ?? 'sms',
                'sender_id' => $params['sender_id'] ?? null,
                'gateway_id' => $params['gateway_id'] ?? null,
                'timezone' => $params['timezone'] ?? 'UTC',
            ]
        );

        if ($result['success']) {
            return $this->success(['scheduled_id' => $result['id']], 201);
        }

        return $this->error($result['error'] ?? 'Failed to schedule message', 400);
    }

    /**
     * Send WhatsApp text message
     */
    private function sendWhatsApp(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'send_whatsapp')) {
            return $this->error('Insufficient permissions', 403);
        }

        $to = $params['to'] ?? null;
        $message = $params['message'] ?? null;

        if (empty($to) || empty($message)) {
            return $this->error('Missing required parameters: to, message', 400);
        }

        require_once dirname(__DIR__) . '/Core/SegmentCounter.php';
        require_once dirname(__DIR__) . '/Core/MessageService.php';

        $result = MessageService::send($this->apiKey['client_id'], $to, $message, [
            'channel' => 'whatsapp',
            'sender_id' => $params['sender_id'] ?? null,
            'gateway_id' => $params['gateway_id'] ?? null,
            'api_key_id' => $this->apiKey['id'],
            'send_now' => true,
        ]);

        if ($result['success']) {
            return $this->success([
                'message_id' => $result['message_id'],
                'status' => 'sent',
            ], 201);
        }

        return $this->error($result['error'] ?? 'Failed to send message', 400);
    }

    /**
     * Send WhatsApp template message
     */
    private function sendWhatsAppTemplate(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'send_whatsapp')) {
            return $this->error('Insufficient permissions', 403);
        }

        $to = $params['to'] ?? null;
        $templateName = $params['template_name'] ?? null;

        if (empty($to) || empty($templateName)) {
            return $this->error('Missing required parameters: to, template_name', 400);
        }

        require_once dirname(__DIR__) . '/WhatsApp/WhatsAppService.php';

        $result = \SMSSuite\WhatsApp\WhatsAppService::sendTemplate(
            $this->apiKey['client_id'],
            $to,
            $templateName,
            $params['template_params'] ?? [],
            [
                'language' => $params['language'] ?? 'en',
                'gateway_id' => $params['gateway_id'] ?? null,
            ]
        );

        if ($result['success']) {
            return $this->success([
                'message_id' => $result['message_id'],
                'provider_message_id' => $result['provider_message_id'] ?? null,
            ], 201);
        }

        return $this->error($result['error'] ?? 'Failed to send template', 400);
    }

    /**
     * Send WhatsApp media message
     */
    private function sendWhatsAppMedia(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'send_whatsapp')) {
            return $this->error('Insufficient permissions', 403);
        }

        $to = $params['to'] ?? null;
        $mediaUrl = $params['media_url'] ?? null;
        $mediaType = $params['media_type'] ?? 'image';

        if (empty($to) || empty($mediaUrl)) {
            return $this->error('Missing required parameters: to, media_url', 400);
        }

        require_once dirname(__DIR__) . '/WhatsApp/WhatsAppService.php';

        $result = \SMSSuite\WhatsApp\WhatsAppService::sendMedia(
            $this->apiKey['client_id'],
            $to,
            $mediaType,
            $mediaUrl,
            [
                'caption' => $params['caption'] ?? null,
                'filename' => $params['filename'] ?? null,
                'gateway_id' => $params['gateway_id'] ?? null,
            ]
        );

        if ($result['success']) {
            return $this->success(['message_id' => $result['message_id']], 201);
        }

        return $this->error($result['error'] ?? 'Failed to send media', 400);
    }

    /**
     * Import contacts from array
     */
    private function importContacts(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'contacts')) {
            return $this->error('Insufficient permissions', 403);
        }

        $contacts = $params['contacts'] ?? [];
        $groupId = $params['group_id'] ?? null;

        if (empty($contacts) || !is_array($contacts)) {
            return $this->error('Missing or invalid parameter: contacts (array expected)', 400);
        }

        if (count($contacts) > 10000) {
            return $this->error('Maximum 10000 contacts per request', 400);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($contacts as $contact) {
            $phone = $contact['phone'] ?? null;
            if (empty($phone)) {
                $skipped++;
                continue;
            }

            try {
                Capsule::table('mod_sms_contacts')->insert([
                    'client_id' => $this->apiKey['client_id'],
                    'group_id' => $groupId,
                    'phone' => $phone,
                    'first_name' => $contact['first_name'] ?? null,
                    'last_name' => $contact['last_name'] ?? null,
                    'email' => $contact['email'] ?? null,
                    'custom_data' => json_encode($contact['custom_fields'] ?? []),
                    'status' => 'subscribed',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $imported++;
            } catch (Exception $e) {
                $skipped++;
            }
        }

        return $this->success([
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => count($contacts),
        ], 201);
    }

    /**
     * Get campaigns
     */
    private function getCampaigns(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'campaigns')) {
            return $this->error('Insufficient permissions', 403);
        }

        $limit = min((int)($params['limit'] ?? 50), 100);
        $offset = (int)($params['offset'] ?? 0);

        $query = Capsule::table('mod_sms_campaigns')
            ->where('client_id', $this->apiKey['client_id']);

        $total = $query->count();

        $campaigns = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'channel' => $c->channel,
                    'status' => $c->status,
                    'total_recipients' => $c->total_recipients,
                    'sent_count' => $c->sent_count,
                    'delivered_count' => $c->delivered_count,
                    'failed_count' => $c->failed_count,
                    'scheduled_at' => $c->schedule_time,
                    'created_at' => $c->created_at,
                ];
            })
            ->toArray();

        return $this->success([
            'campaigns' => $campaigns,
            'pagination' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
        ]);
    }

    /**
     * Create campaign
     */
    private function createCampaign(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'campaigns')) {
            return $this->error('Insufficient permissions', 403);
        }

        $name = $params['name'] ?? null;
        $message = $params['message'] ?? null;
        $recipients = $params['recipients'] ?? [];

        if (empty($name) || empty($message)) {
            return $this->error('Missing required parameters: name, message', 400);
        }

        require_once dirname(__DIR__) . '/Campaigns/CampaignService.php';

        $result = \SMSSuite\Campaigns\CampaignService::create($this->apiKey['client_id'], [
            'name' => $name,
            'message' => $message,
            'channel' => $params['channel'] ?? 'sms',
            'sender_id' => $params['sender_id'] ?? null,
            'gateway_id' => $params['gateway_id'] ?? null,
            'recipient_type' => !empty($params['group_id']) ? 'group' : 'manual',
            'recipient_group_id' => $params['group_id'] ?? null,
            'recipients' => $recipients,
            'scheduled_at' => $params['scheduled_at'] ?? null,
        ]);

        if ($result['success']) {
            // Schedule if requested
            if (!empty($params['send_now']) || !empty($params['scheduled_at'])) {
                \SMSSuite\Campaigns\CampaignService::schedule(
                    $result['id'],
                    $this->apiKey['client_id'],
                    $params['scheduled_at'] ?? null
                );
            }

            return $this->success(['campaign_id' => $result['id']], 201);
        }

        return $this->error($result['error'] ?? 'Failed to create campaign', 400);
    }

    /**
     * Get campaign status
     */
    private function getCampaignStatus(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'campaigns')) {
            return $this->error('Insufficient permissions', 403);
        }

        $campaignId = $params['campaign_id'] ?? null;
        if (empty($campaignId)) {
            return $this->error('Missing required parameter: campaign_id', 400);
        }

        $campaign = Capsule::table('mod_sms_campaigns')
            ->where('id', $campaignId)
            ->where('client_id', $this->apiKey['client_id'])
            ->first();

        if (!$campaign) {
            return $this->error('Campaign not found', 404);
        }

        $progress = $campaign->total_recipients > 0
            ? round((($campaign->sent_count + $campaign->failed_count) / $campaign->total_recipients) * 100, 2)
            : 0;

        return $this->success([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'progress' => $progress,
            'total_recipients' => $campaign->total_recipients,
            'sent_count' => $campaign->sent_count,
            'delivered_count' => $campaign->delivered_count,
            'failed_count' => $campaign->failed_count,
            'started_at' => $campaign->started_at,
            'completed_at' => $campaign->completed_at,
        ]);
    }

    /**
     * Pause campaign
     */
    private function pauseCampaign(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'campaigns')) {
            return $this->error('Insufficient permissions', 403);
        }

        $campaignId = $params['campaign_id'] ?? null;
        if (empty($campaignId)) {
            return $this->error('Missing required parameter: campaign_id', 400);
        }

        require_once dirname(__DIR__) . '/Campaigns/CampaignService.php';

        if (\SMSSuite\Campaigns\CampaignService::pause($campaignId, $this->apiKey['client_id'])) {
            return $this->success(['status' => 'paused']);
        }

        return $this->error('Failed to pause campaign', 400);
    }

    /**
     * Resume campaign
     */
    private function resumeCampaign(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'campaigns')) {
            return $this->error('Insufficient permissions', 403);
        }

        $campaignId = $params['campaign_id'] ?? null;
        if (empty($campaignId)) {
            return $this->error('Missing required parameter: campaign_id', 400);
        }

        require_once dirname(__DIR__) . '/Campaigns/CampaignService.php';

        if (\SMSSuite\Campaigns\CampaignService::resume($campaignId, $this->apiKey['client_id'])) {
            return $this->success(['status' => 'queued']);
        }

        return $this->error('Failed to resume campaign', 400);
    }

    /**
     * Cancel campaign
     */
    private function cancelCampaign(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'campaigns')) {
            return $this->error('Insufficient permissions', 403);
        }

        $campaignId = $params['campaign_id'] ?? null;
        if (empty($campaignId)) {
            return $this->error('Missing required parameter: campaign_id', 400);
        }

        require_once dirname(__DIR__) . '/Campaigns/CampaignService.php';

        if (\SMSSuite\Campaigns\CampaignService::cancel($campaignId, $this->apiKey['client_id'])) {
            return $this->success(['status' => 'cancelled']);
        }

        return $this->error('Failed to cancel campaign', 400);
    }

    /**
     * Get sender IDs
     */
    private function getSenderIds(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'sender_ids')) {
            return $this->error('Insufficient permissions', 403);
        }

        $senderIds = Capsule::table('mod_sms_sender_ids')
            ->where('client_id', $this->apiKey['client_id'])
            ->orderBy('sender_id')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'sender_id' => $s->sender_id,
                    'type' => $s->type,
                    'status' => $s->status,
                    'validity_date' => $s->validity_date,
                ];
            })
            ->toArray();

        return $this->success(['sender_ids' => $senderIds]);
    }

    /**
     * Request new sender ID
     */
    private function requestSenderId(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'sender_ids')) {
            return $this->error('Insufficient permissions', 403);
        }

        $senderId = $params['sender_id'] ?? null;
        if (empty($senderId)) {
            return $this->error('Missing required parameter: sender_id', 400);
        }

        require_once dirname(__DIR__) . '/Core/SenderIdService.php';

        $result = \SMSSuite\Core\SenderIdService::request(
            $this->apiKey['client_id'],
            $senderId,
            $params['type'] ?? 'alphanumeric',
            ['notes' => $params['notes'] ?? '']
        );

        if ($result['success']) {
            return $this->success([
                'request_id' => $result['id'],
                'status' => $result['status'],
            ], 201);
        }

        return $this->error($result['error'] ?? 'Failed to request sender ID', 400);
    }

    /**
     * Get transaction history
     */
    private function getTransactions(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'balance')) {
            return $this->error('Insufficient permissions', 403);
        }

        $limit = min((int)($params['limit'] ?? 50), 100);
        $offset = (int)($params['offset'] ?? 0);

        $query = Capsule::table('mod_sms_wallet_transactions')
            ->where('client_id', $this->apiKey['client_id']);

        $total = $query->count();

        $transactions = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        return $this->success([
            'transactions' => $transactions,
            'pagination' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
        ]);
    }

    /**
     * Get usage statistics
     */
    private function getUsageStats(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'reports')) {
            return $this->error('Insufficient permissions', 403);
        }

        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');

        require_once dirname(__DIR__) . '/Reports/ReportService.php';

        $summary = \SMSSuite\Reports\ReportService::getUsageSummary(
            $this->apiKey['client_id'],
            $startDate,
            $endDate
        );

        return $this->success($summary);
    }

    /**
     * Get templates
     */
    private function getTemplates(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'templates')) {
            return $this->error('Insufficient permissions', 403);
        }

        $templates = Capsule::table('mod_sms_templates')
            ->where(function ($q) {
                $q->where('client_id', $this->apiKey['client_id'])
                  ->orWhere('client_id', 0);
            })
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->toArray();

        return $this->success(['templates' => $templates]);
    }

    /**
     * Create template
     */
    private function createTemplate(array $params): array
    {
        if (!ApiKeyService::hasScope($this->apiKey, 'templates')) {
            return $this->error('Insufficient permissions', 403);
        }

        $name = $params['name'] ?? null;
        $content = $params['content'] ?? null;

        if (empty($name) || empty($content)) {
            return $this->error('Missing required parameters: name, content', 400);
        }

        require_once dirname(__DIR__) . '/Core/TemplateService.php';

        $result = \SMSSuite\Core\TemplateService::create($this->apiKey['client_id'], [
            'name' => $name,
            'content' => $content,
            'category' => $params['category'] ?? 'general',
            'channel' => $params['channel'] ?? 'sms',
        ]);

        if ($result['success']) {
            return $this->success(['template_id' => $result['id']], 201);
        }

        return $this->error($result['error'] ?? 'Failed to create template', 400);
    }

    /**
     * Format success response
     */
    private function success(array $data, int $code = 200): array
    {
        $this->httpCode = $code;
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Format error response
     */
    private function error(string $message, int $code = 400): array
    {
        $this->httpCode = $code;
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * Get HTTP response code
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
