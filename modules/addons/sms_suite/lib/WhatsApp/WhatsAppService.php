<?php
/**
 * SMS Suite - WhatsApp Business API Service
 *
 * Handles WhatsApp template messages, media messages, conversations, and chatbox
 */

namespace SMSSuite\WhatsApp;

use WHMCS\Database\Capsule;
use SMSSuite\Gateways\GatewayRegistry;
use SMSSuite\Gateways\MessageDTO;
use SMSSuite\Core\TemplateService;
use Exception;

class WhatsAppService
{
    /**
     * Message types
     */
    const TYPE_TEXT = 'text';
    const TYPE_TEMPLATE = 'template';
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_DOCUMENT = 'document';
    const TYPE_LOCATION = 'location';
    const TYPE_CONTACT = 'contact';
    const TYPE_INTERACTIVE = 'interactive';

    /**
     * Interactive message types
     */
    const INTERACTIVE_BUTTON = 'button';
    const INTERACTIVE_LIST = 'list';
    const INTERACTIVE_PRODUCT = 'product';

    /**
     * Conversation statuses
     */
    const CONVERSATION_OPEN = 'open';
    const CONVERSATION_CLOSED = 'closed';
    const CONVERSATION_EXPIRED = 'expired';

    /**
     * Send a WhatsApp template message
     *
     * @param int $clientId
     * @param string $to
     * @param string $templateName
     * @param array $params Template parameters
     * @param array $options Additional options
     * @return array
     */
    public static function sendTemplate(int $clientId, string $to, string $templateName, array $params = [], array $options = []): array
    {
        try {
            $to = self::normalizePhone($to);
            if (empty($to)) {
                return ['success' => false, 'error' => 'Invalid phone number'];
            }

            // Load WhatsApp template
            $template = self::getWhatsAppTemplate($templateName, $options['language'] ?? 'en');
            if (!$template) {
                return ['success' => false, 'error' => 'Template not found: ' . $templateName];
            }

            // Get gateway
            $gatewayId = $options['gateway_id'] ?? self::getWhatsAppGateway($clientId);
            if (!$gatewayId) {
                return ['success' => false, 'error' => 'No WhatsApp gateway configured'];
            }

            // Build template message
            $messagePayload = [
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $options['language'] ?? 'en',
                    ],
                    'components' => self::buildTemplateComponents($template, $params),
                ],
            ];

            // Create message record
            $messageId = Capsule::table('mod_sms_messages')->insertGetId([
                'client_id' => $clientId,
                'gateway_id' => $gatewayId,
                'channel' => 'whatsapp',
                'direction' => 'outbound',
                'to_number' => $to,
                'message' => $template->content ?? $templateName,
                'message_type' => self::TYPE_TEMPLATE,
                'template_name' => $templateName,
                'template_params' => json_encode($params),
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Send via gateway
            $result = self::sendViaGateway($gatewayId, $to, $messagePayload, $messageId);

            // Update message status
            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update([
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'provider_message_id' => $result['provider_message_id'] ?? null,
                    'error' => $result['error'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            // Update or create conversation
            if ($result['success']) {
                self::updateConversation($clientId, $to, $messageId, 'outbound');
            }

            return [
                'success' => $result['success'],
                'message_id' => $messageId,
                'provider_message_id' => $result['provider_message_id'] ?? null,
                'error' => $result['error'] ?? null,
            ];

        } catch (Exception $e) {
            logActivity('SMS Suite WhatsApp: Template send error - ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a WhatsApp media message
     *
     * @param int $clientId
     * @param string $to
     * @param string $mediaType image|video|audio|document
     * @param string $mediaUrl
     * @param array $options caption, filename, etc.
     * @return array
     */
    public static function sendMedia(int $clientId, string $to, string $mediaType, string $mediaUrl, array $options = []): array
    {
        try {
            $to = self::normalizePhone($to);
            if (empty($to)) {
                return ['success' => false, 'error' => 'Invalid phone number'];
            }

            if (!in_array($mediaType, [self::TYPE_IMAGE, self::TYPE_VIDEO, self::TYPE_AUDIO, self::TYPE_DOCUMENT])) {
                return ['success' => false, 'error' => 'Invalid media type'];
            }

            $gatewayId = $options['gateway_id'] ?? self::getWhatsAppGateway($clientId);
            if (!$gatewayId) {
                return ['success' => false, 'error' => 'No WhatsApp gateway configured'];
            }

            // Build media message
            $messagePayload = [
                'type' => $mediaType,
                $mediaType => [
                    'link' => $mediaUrl,
                ],
            ];

            // Add caption for image/video/document
            if (!empty($options['caption']) && in_array($mediaType, ['image', 'video', 'document'])) {
                $messagePayload[$mediaType]['caption'] = $options['caption'];
            }

            // Add filename for document
            if (!empty($options['filename']) && $mediaType === 'document') {
                $messagePayload[$mediaType]['filename'] = $options['filename'];
            }

            // Create message record
            $messageId = Capsule::table('mod_sms_messages')->insertGetId([
                'client_id' => $clientId,
                'gateway_id' => $gatewayId,
                'channel' => 'whatsapp',
                'direction' => 'outbound',
                'to_number' => $to,
                'message' => $options['caption'] ?? '',
                'message_type' => $mediaType,
                'media_url' => $mediaUrl,
                'media_type' => $mediaType,
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Send via gateway
            $result = self::sendViaGateway($gatewayId, $to, $messagePayload, $messageId);

            // Update message status
            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update([
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'provider_message_id' => $result['provider_message_id'] ?? null,
                    'error' => $result['error'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($result['success']) {
                self::updateConversation($clientId, $to, $messageId, 'outbound');
            }

            return [
                'success' => $result['success'],
                'message_id' => $messageId,
                'error' => $result['error'] ?? null,
            ];

        } catch (Exception $e) {
            logActivity('SMS Suite WhatsApp: Media send error - ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send an interactive message (buttons/list)
     *
     * @param int $clientId
     * @param string $to
     * @param string $interactiveType button|list
     * @param array $content Header, body, footer, buttons/sections
     * @param array $options
     * @return array
     */
    public static function sendInteractive(int $clientId, string $to, string $interactiveType, array $content, array $options = []): array
    {
        try {
            $to = self::normalizePhone($to);
            if (empty($to)) {
                return ['success' => false, 'error' => 'Invalid phone number'];
            }

            $gatewayId = $options['gateway_id'] ?? self::getWhatsAppGateway($clientId);
            if (!$gatewayId) {
                return ['success' => false, 'error' => 'No WhatsApp gateway configured'];
            }

            // Build interactive message
            $interactive = [
                'type' => $interactiveType,
            ];

            // Header (optional)
            if (!empty($content['header'])) {
                $interactive['header'] = $content['header'];
            }

            // Body (required)
            $interactive['body'] = [
                'text' => $content['body'] ?? '',
            ];

            // Footer (optional)
            if (!empty($content['footer'])) {
                $interactive['footer'] = [
                    'text' => $content['footer'],
                ];
            }

            // Action
            if ($interactiveType === 'button') {
                $buttons = [];
                foreach (($content['buttons'] ?? []) as $i => $button) {
                    $buttons[] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'] ?? 'btn_' . $i,
                            'title' => $button['title'] ?? $button,
                        ],
                    ];
                }
                $interactive['action'] = ['buttons' => array_slice($buttons, 0, 3)]; // Max 3 buttons
            } elseif ($interactiveType === 'list') {
                $interactive['action'] = [
                    'button' => $content['button_text'] ?? 'Select',
                    'sections' => $content['sections'] ?? [],
                ];
            }

            $messagePayload = [
                'type' => 'interactive',
                'interactive' => $interactive,
            ];

            // Create message record
            $messageId = Capsule::table('mod_sms_messages')->insertGetId([
                'client_id' => $clientId,
                'gateway_id' => $gatewayId,
                'channel' => 'whatsapp',
                'direction' => 'outbound',
                'to_number' => $to,
                'message' => $content['body'] ?? '',
                'message_type' => 'interactive',
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Send via gateway
            $result = self::sendViaGateway($gatewayId, $to, $messagePayload, $messageId);

            // Update message status
            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update([
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'provider_message_id' => $result['provider_message_id'] ?? null,
                    'error' => $result['error'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($result['success']) {
                self::updateConversation($clientId, $to, $messageId, 'outbound');
            }

            return [
                'success' => $result['success'],
                'message_id' => $messageId,
                'error' => $result['error'] ?? null,
            ];

        } catch (Exception $e) {
            logActivity('SMS Suite WhatsApp: Interactive send error - ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a location message
     */
    public static function sendLocation(int $clientId, string $to, float $latitude, float $longitude, array $options = []): array
    {
        try {
            $to = self::normalizePhone($to);
            $gatewayId = $options['gateway_id'] ?? self::getWhatsAppGateway($clientId);

            $messagePayload = [
                'type' => 'location',
                'location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'name' => $options['name'] ?? null,
                    'address' => $options['address'] ?? null,
                ],
            ];

            $messageId = Capsule::table('mod_sms_messages')->insertGetId([
                'client_id' => $clientId,
                'gateway_id' => $gatewayId,
                'channel' => 'whatsapp',
                'direction' => 'outbound',
                'to_number' => $to,
                'message' => $options['name'] ?? "Location: {$latitude}, {$longitude}",
                'message_type' => 'location',
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $result = self::sendViaGateway($gatewayId, $to, $messagePayload, $messageId);

            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update([
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'provider_message_id' => $result['provider_message_id'] ?? null,
                    'error' => $result['error'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return [
                'success' => $result['success'],
                'message_id' => $messageId,
                'error' => $result['error'] ?? null,
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle incoming WhatsApp message (webhook)
     *
     * @param array $data Webhook payload
     * @return array
     */
    public static function handleInbound(array $data): array
    {
        try {
            $from = $data['from'] ?? $data['sender'] ?? null;
            $message = $data['text'] ?? $data['message'] ?? $data['body'] ?? '';
            $messageId = $data['id'] ?? $data['message_id'] ?? null;
            $timestamp = $data['timestamp'] ?? time();

            if (!$from) {
                return ['success' => false, 'error' => 'Missing sender'];
            }

            // Determine message type
            $messageType = self::TYPE_TEXT;
            $mediaUrl = null;

            if (!empty($data['type'])) {
                $messageType = $data['type'];
            }

            if (isset($data['image'])) {
                $messageType = self::TYPE_IMAGE;
                $mediaUrl = $data['image']['link'] ?? $data['image']['url'] ?? null;
                $message = $data['image']['caption'] ?? '[Image]';
            } elseif (isset($data['video'])) {
                $messageType = self::TYPE_VIDEO;
                $mediaUrl = $data['video']['link'] ?? $data['video']['url'] ?? null;
                $message = $data['video']['caption'] ?? '[Video]';
            } elseif (isset($data['audio'])) {
                $messageType = self::TYPE_AUDIO;
                $mediaUrl = $data['audio']['link'] ?? $data['audio']['url'] ?? null;
                $message = '[Audio]';
            } elseif (isset($data['document'])) {
                $messageType = self::TYPE_DOCUMENT;
                $mediaUrl = $data['document']['link'] ?? $data['document']['url'] ?? null;
                $message = $data['document']['filename'] ?? '[Document]';
            }

            // Find or create chatbox entry
            $chatboxId = self::findOrCreateChatbox($from);

            // Store inbound message
            $inboundId = Capsule::table('mod_sms_messages')->insertGetId([
                'client_id' => 0, // Will be linked via chatbox
                'gateway_id' => $data['gateway_id'] ?? null,
                'channel' => 'whatsapp',
                'direction' => 'inbound',
                'from_number' => $from,
                'message' => $message,
                'message_type' => $messageType,
                'media_url' => $mediaUrl,
                'provider_message_id' => $messageId,
                'status' => 'received',
                'created_at' => date('Y-m-d H:i:s', $timestamp),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Add to chatbox messages
            Capsule::table('mod_sms_chatbox_messages')->insert([
                'chatbox_id' => $chatboxId,
                'message_id' => $inboundId,
                'direction' => 'inbound',
                'created_at' => date('Y-m-d H:i:s', $timestamp),
            ]);

            // Update chatbox last message
            Capsule::table('mod_sms_chatbox')
                ->where('id', $chatboxId)
                ->update([
                    'last_message' => substr($message, 0, 255),
                    'last_message_at' => date('Y-m-d H:i:s', $timestamp),
                    'unread_count' => Capsule::raw('unread_count + 1'),
                    'status' => self::CONVERSATION_OPEN,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            // Check for auto-reply triggers
            self::checkAutoReply($chatboxId, $message, $from);

            return [
                'success' => true,
                'message_id' => $inboundId,
                'chatbox_id' => $chatboxId,
            ];

        } catch (Exception $e) {
            logActivity('SMS Suite WhatsApp: Inbound error - ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get conversations/chatbox list
     *
     * @param int $clientId 0 for admin view
     * @param array $filters
     * @return array
     */
    public static function getConversations(int $clientId = 0, array $filters = []): array
    {
        $query = Capsule::table('mod_sms_chatbox')
            ->select('mod_sms_chatbox.*');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', $search)
                  ->orWhere('contact_name', 'like', $search)
                  ->orWhere('last_message', 'like', $search);
            });
        }

        $total = $query->count();
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;

        $conversations = $query
            ->orderBy('last_message_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'conversations' => $conversations,
            'total' => $total,
        ];
    }

    /**
     * Get conversation messages
     *
     * @param int $chatboxId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getConversationMessages(int $chatboxId, int $limit = 50, int $offset = 0): array
    {
        $messages = Capsule::table('mod_sms_chatbox_messages as cm')
            ->join('mod_sms_messages as m', 'cm.message_id', '=', 'm.id')
            ->where('cm.chatbox_id', $chatboxId)
            ->select('m.*', 'cm.direction')
            ->orderBy('m.created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        // Mark as read
        Capsule::table('mod_sms_chatbox')
            ->where('id', $chatboxId)
            ->update([
                'unread_count' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return array_reverse($messages->toArray());
    }

    /**
     * Reply to conversation
     */
    public static function replyToConversation(int $chatboxId, string $message, array $options = []): array
    {
        $chatbox = Capsule::table('mod_sms_chatbox')->where('id', $chatboxId)->first();
        if (!$chatbox) {
            return ['success' => false, 'error' => 'Conversation not found'];
        }

        $clientId = $chatbox->client_id ?: 0;
        $to = $chatbox->phone;

        // Check if within 24-hour window
        if ($chatbox->last_message_at) {
            $lastMessage = strtotime($chatbox->last_message_at);
            $windowEnd = $lastMessage + (24 * 60 * 60);

            if (time() > $windowEnd && empty($options['template_name'])) {
                return [
                    'success' => false,
                    'error' => '24-hour messaging window expired. Use a template message.',
                    'window_expired' => true,
                ];
            }
        }

        // If template specified, send template
        if (!empty($options['template_name'])) {
            return self::sendTemplate($clientId, $to, $options['template_name'], $options['template_params'] ?? []);
        }

        // Send regular text message
        require_once dirname(__DIR__) . '/Core/MessageService.php';

        $result = \SMSSuite\Core\MessageService::send($clientId, $to, $message, [
            'channel' => 'whatsapp',
            'gateway_id' => $chatbox->gateway_id ?? $options['gateway_id'] ?? null,
            'send_now' => true,
        ]);

        if ($result['success']) {
            // Link message to chatbox
            Capsule::table('mod_sms_chatbox_messages')->insert([
                'chatbox_id' => $chatboxId,
                'message_id' => $result['message_id'],
                'direction' => 'outbound',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Update chatbox
            Capsule::table('mod_sms_chatbox')
                ->where('id', $chatboxId)
                ->update([
                    'last_message' => substr($message, 0, 255),
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        return $result;
    }

    /**
     * Create or get WhatsApp template
     */
    public static function createWhatsAppTemplate(array $data): array
    {
        if (empty($data['name']) || empty($data['content'])) {
            return ['success' => false, 'error' => 'Name and content are required'];
        }

        try {
            $id = Capsule::table('mod_sms_whatsapp_templates')->insertGetId([
                'name' => $data['name'],
                'language' => $data['language'] ?? 'en',
                'category' => $data['category'] ?? 'UTILITY',
                'content' => $data['content'],
                'header_type' => $data['header_type'] ?? null,
                'header_content' => $data['header_content'] ?? null,
                'footer' => $data['footer'] ?? null,
                'buttons' => json_encode($data['buttons'] ?? []),
                'example_params' => json_encode($data['example_params'] ?? []),
                'status' => 'pending', // Needs provider approval
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get WhatsApp templates
     */
    public static function getWhatsAppTemplates(?string $status = null): array
    {
        $query = Capsule::table('mod_sms_whatsapp_templates');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    // ==================== Meta Business Management API ====================

    /**
     * Get Meta API credentials for a gateway
     *
     * @param int $gatewayId
     * @return array ['phone_number_id', 'access_token', 'waba_id']
     */
    public static function getMetaCredentials(int $gatewayId): array
    {
        $gateway = Capsule::table('mod_sms_gateways')->where('id', $gatewayId)->first();
        if (!$gateway) {
            throw new Exception('Gateway not found');
        }

        if ($gateway->type !== 'meta_whatsapp') {
            throw new Exception('Gateway is not Meta WhatsApp type');
        }

        // Decrypt credentials
        require_once dirname(__DIR__, 2) . '/sms_suite.php';
        $decrypted = sms_suite_decrypt($gateway->credentials);
        $credentials = json_decode($decrypted, true);

        if (!is_array($credentials) || empty($credentials['access_token']) || empty($credentials['waba_id'])) {
            throw new Exception('Missing Meta WhatsApp credentials (access_token and waba_id required)');
        }

        return $credentials;
    }

    /**
     * Create a message template on Meta's API
     *
     * @param int $gatewayId
     * @param array $data ['name', 'language', 'category', 'components']
     * @return array
     */
    public static function createMetaTemplate(int $gatewayId, array $data): array
    {
        try {
            $credentials = self::getMetaCredentials($gatewayId);
            $wabaId = $credentials['waba_id'];
            $accessToken = $credentials['access_token'];

            $url = "https://graph.facebook.com/v21.0/{$wabaId}/message_templates";

            $payload = [
                'name' => $data['name'],
                'language' => $data['language'] ?? 'en',
                'category' => strtoupper($data['category'] ?? 'UTILITY'),
                'components' => $data['components'] ?? [
                    ['type' => 'BODY', 'text' => $data['content'] ?? ''],
                ],
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'id' => $result['id'] ?? null,
                    'status' => $result['status'] ?? 'PENDING',
                    'category' => $result['category'] ?? $payload['category'],
                ];
            }

            return [
                'success' => false,
                'error' => $result['error']['message'] ?? 'Meta API error',
                'error_code' => $result['error']['code'] ?? null,
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all message templates from Meta's API
     *
     * @param int $gatewayId
     * @return array
     */
    public static function getMetaTemplates(int $gatewayId): array
    {
        try {
            $credentials = self::getMetaCredentials($gatewayId);
            $wabaId = $credentials['waba_id'];
            $accessToken = $credentials['access_token'];

            $url = "https://graph.facebook.com/v21.0/{$wabaId}/message_templates?"
                 . http_build_query(['limit' => 250, 'fields' => 'name,status,category,language,components,id']);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'templates' => $result['data'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $result['error']['message'] ?? 'Meta API error',
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get a single message template from Meta's API
     *
     * @param int $gatewayId
     * @param string $templateName
     * @return array
     */
    public static function getMetaTemplate(int $gatewayId, string $templateName): array
    {
        try {
            $credentials = self::getMetaCredentials($gatewayId);
            $wabaId = $credentials['waba_id'];
            $accessToken = $credentials['access_token'];

            $url = "https://graph.facebook.com/v21.0/{$wabaId}/message_templates?"
                 . http_build_query(['name' => $templateName]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                $templates = $result['data'] ?? [];
                return [
                    'success' => true,
                    'template' => !empty($templates) ? $templates[0] : null,
                ];
            }

            return [
                'success' => false,
                'error' => $result['error']['message'] ?? 'Meta API error',
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a message template from Meta's API
     *
     * @param int $gatewayId
     * @param string $templateName
     * @return array
     */
    public static function deleteMetaTemplate(int $gatewayId, string $templateName): array
    {
        try {
            $credentials = self::getMetaCredentials($gatewayId);
            $wabaId = $credentials['waba_id'];
            $accessToken = $credentials['access_token'];

            // First get the template ID
            $templateData = self::getMetaTemplate($gatewayId, $templateName);
            $templateId = $templateData['template']['id'] ?? null;

            // Try deleting by template ID (preferred) or by name on WABA
            if ($templateId) {
                $url = "https://graph.facebook.com/v21.0/{$wabaId}/message_templates?"
                     . http_build_query(['hsm_id' => $templateId, 'name' => $templateName]);
            } else {
                $url = "https://graph.facebook.com/v21.0/{$wabaId}/message_templates?"
                     . http_build_query(['name' => $templateName]);
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => $result['success'] ?? true,
                ];
            }

            // If Meta delete fails (permission issue), still allow local removal
            $errorMsg = $result['error']['message'] ?? 'Meta API error';
            return [
                'success' => false,
                'error' => $errorMsg . ' (template removed locally — delete from Meta Business Manager manually)',
                'local_only' => true,
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ==================== Private Helper Methods ====================

    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
            return '';
        }
        return $phone;
    }

    private static function getWhatsAppGateway(int $clientId): ?int
    {
        $gateway = Capsule::table('mod_sms_gateways')
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('channel', 'whatsapp')
                  ->orWhere('channel', 'both');
            })
            ->orderBy('id')
            ->first();

        return $gateway ? $gateway->id : null;
    }

    private static function getWhatsAppTemplate(string $name, string $language = 'en'): ?object
    {
        return Capsule::table('mod_sms_whatsapp_templates')
            ->where('name', $name)
            ->where(function ($q) use ($language) {
                $q->where('language', $language)
                  ->orWhere('language', 'en');
            })
            ->where('status', 'approved')
            ->first();
    }

    private static function buildTemplateComponents(object $template, array $params): array
    {
        $components = [];

        // Header component
        if ($template->header_type) {
            $header = ['type' => 'header'];
            if ($template->header_type === 'text' && !empty($params['header'])) {
                $header['parameters'] = [['type' => 'text', 'text' => $params['header']]];
            } elseif (in_array($template->header_type, ['image', 'video', 'document']) && !empty($params['header_media'])) {
                $header['parameters'] = [['type' => $template->header_type, $template->header_type => ['link' => $params['header_media']]]];
            }
            $components[] = $header;
        }

        // Body component
        $bodyParams = [];
        $paramIndex = 1;
        while (isset($params["body_$paramIndex"]) || isset($params[$paramIndex])) {
            $value = $params["body_$paramIndex"] ?? $params[$paramIndex];
            $bodyParams[] = ['type' => 'text', 'text' => $value];
            $paramIndex++;
        }
        if (!empty($bodyParams)) {
            $components[] = ['type' => 'body', 'parameters' => $bodyParams];
        }

        // Button components
        $buttons = json_decode($template->buttons ?? '[]', true);
        foreach ($buttons as $i => $button) {
            if ($button['type'] === 'url' && isset($params["button_$i"])) {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => $i,
                    'parameters' => [['type' => 'text', 'text' => $params["button_$i"]]],
                ];
            }
        }

        return $components;
    }

    private static function sendViaGateway(int $gatewayId, string $to, array $payload, int $messageId): array
    {
        try {
            // Load gateway
            $gatewayConfig = Capsule::table('mod_sms_gateways')->where('id', $gatewayId)->first();
            if (!$gatewayConfig) {
                return ['success' => false, 'error' => 'Gateway not found'];
            }

            $credentials = json_decode($gatewayConfig->credentials, true);

            // Use cURL to send to WhatsApp Business API
            $ch = curl_init();

            // Different providers have different endpoints
            switch ($gatewayConfig->type) {
                case 'twilio_whatsapp':
                    return self::sendViaTwilio($credentials, $to, $payload);
                case 'meta_whatsapp':
                case 'facebook_whatsapp':
                    return self::sendViaMeta($credentials, $to, $payload);
                case 'messagebird_whatsapp':
                    return self::sendViaMessageBird($credentials, $to, $payload);
                case 'gupshup_whatsapp':
                    return self::sendViaGupshup($credentials, $to, $payload);
                case 'interakt_whatsapp':
                    return self::sendViaInterakt($credentials, $to, $payload);
                case 'ultramsg_whatsapp':
                    return self::sendViaUltraMsg($credentials, $to, $payload);
                default:
                    return self::sendViaGenericWhatsApp($gatewayConfig, $credentials, $to, $payload);
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private static function sendViaTwilio(array $credentials, string $to, array $payload): array
    {
        $accountSid = $credentials['account_sid'];
        $authToken = $credentials['auth_token'];
        $from = $credentials['whatsapp_number']; // whatsapp:+14155238886

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $postData = [
            'To' => 'whatsapp:' . $to,
            'From' => 'whatsapp:' . $from,
        ];

        if ($payload['type'] === 'template') {
            $postData['ContentSid'] = $payload['template']['name'] ?? null;
            $postData['ContentVariables'] = json_encode($payload['template']['components'] ?? []);
        } else {
            $postData['Body'] = $payload['text']['body'] ?? $payload['message'] ?? '';
            if (!empty($payload['image']['link'])) {
                $postData['MediaUrl'] = $payload['image']['link'];
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'provider_message_id' => $result['sid'] ?? null];
        }

        return ['success' => false, 'error' => $result['message'] ?? 'Twilio error'];
    }

    private static function sendViaMeta(array $credentials, string $to, array $payload): array
    {
        $phoneNumberId = $credentials['phone_number_id'];
        $accessToken = $credentials['access_token'];

        $url = "https://graph.facebook.com/v21.0/{$phoneNumberId}/messages";

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $payload['type'],
        ];

        // Add type-specific content
        if (isset($payload[$payload['type']])) {
            $body[$payload['type']] = $payload[$payload['type']];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'provider_message_id' => $result['messages'][0]['id'] ?? null,
            ];
        }

        return [
            'success' => false,
            'error' => $result['error']['message'] ?? 'Meta WhatsApp API error',
        ];
    }

    private static function sendViaMessageBird(array $credentials, string $to, array $payload): array
    {
        $accessKey = $credentials['access_key'];
        $channelId = $credentials['channel_id'];

        $url = 'https://conversations.messagebird.com/v1/send';

        $body = [
            'to' => $to,
            'from' => $channelId,
            'type' => 'text',
            'content' => ['text' => $payload['text']['body'] ?? ''],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: AccessKey ' . $accessKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'provider_message_id' => $result['id'] ?? null,
            'error' => $result['errors'][0]['description'] ?? null,
        ];
    }

    private static function sendViaGupshup(array $credentials, string $to, array $payload): array
    {
        $apiKey = $credentials['api_key'];
        $appId = $credentials['app_id'];
        $source = $credentials['source_number'];

        $url = 'https://api.gupshup.io/sm/api/v1/msg';

        $postData = [
            'channel' => 'whatsapp',
            'source' => $source,
            'destination' => $to,
            'src.name' => $appId,
        ];

        if ($payload['type'] === 'template') {
            $postData['message'] = json_encode([
                'type' => 'template',
                'template' => $payload['template'],
            ]);
        } else {
            $postData['message'] = json_encode([
                'type' => 'text',
                'text' => $payload['text']['body'] ?? '',
            ]);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success' => ($result['status'] ?? '') === 'submitted',
            'provider_message_id' => $result['messageId'] ?? null,
            'error' => $result['message'] ?? null,
        ];
    }

    private static function sendViaInterakt(array $credentials, string $to, array $payload): array
    {
        $apiKey = $credentials['api_key'];

        $url = 'https://api.interakt.ai/v1/public/message/';

        $body = [
            'countryCode' => '+' . substr($to, 0, 2),
            'phoneNumber' => substr($to, 2),
            'type' => 'Template',
            'template' => [
                'name' => $payload['template']['name'] ?? '',
                'languageCode' => $payload['template']['language']['code'] ?? 'en',
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'provider_message_id' => $result['id'] ?? null,
            'error' => $result['message'] ?? null,
        ];
    }

    private static function sendViaUltraMsg(array $credentials, string $to, array $payload): array
    {
        $instanceId = $credentials['instance_id'];
        $token = $credentials['token'];

        $endpoint = 'messages/chat';
        $body = ['to' => $to];

        if ($payload['type'] === 'image') {
            $endpoint = 'messages/image';
            $body['image'] = $payload['image']['link'];
            $body['caption'] = $payload['image']['caption'] ?? '';
        } elseif ($payload['type'] === 'document') {
            $endpoint = 'messages/document';
            $body['document'] = $payload['document']['link'];
            $body['filename'] = $payload['document']['filename'] ?? 'document';
        } elseif ($payload['type'] === 'audio') {
            $endpoint = 'messages/audio';
            $body['audio'] = $payload['audio']['link'];
        } elseif ($payload['type'] === 'video') {
            $endpoint = 'messages/video';
            $body['video'] = $payload['video']['link'];
        } else {
            $body['body'] = $payload['text']['body'] ?? '';
        }

        $url = "https://api.ultramsg.com/{$instanceId}/{$endpoint}";
        $body['token'] = $token;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success' => isset($result['sent']) && $result['sent'] === 'true',
            'provider_message_id' => $result['id'] ?? null,
            'error' => $result['error'] ?? null,
        ];
    }

    private static function sendViaGenericWhatsApp($gateway, array $credentials, string $to, array $payload): array
    {
        // Generic HTTP gateway for WhatsApp
        $url = $gateway->api_url;
        $method = strtoupper($gateway->api_method ?? 'POST');

        $body = [
            'to' => $to,
            'message' => $payload['text']['body'] ?? '',
        ];

        // Merge credentials as parameters
        $body = array_merge($body, $credentials);

        $ch = curl_init($url);

        if ($method === 'GET') {
            $url .= '?' . http_build_query($body);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'error' => $httpCode >= 400 ? $response : null,
        ];
    }

    private static function findOrCreateChatbox(string $phone): int
    {
        $chatbox = Capsule::table('mod_sms_chatbox')
            ->where('phone', $phone)
            ->first();

        if ($chatbox) {
            return $chatbox->id;
        }

        return Capsule::table('mod_sms_chatbox')->insertGetId([
            'phone' => $phone,
            'contact_name' => null,
            'client_id' => null,
            'status' => self::CONVERSATION_OPEN,
            'unread_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function updateConversation(int $clientId, string $to, int $messageId, string $direction): void
    {
        $chatboxId = self::findOrCreateChatbox($to);

        Capsule::table('mod_sms_chatbox_messages')->insert([
            'chatbox_id' => $chatboxId,
            'message_id' => $messageId,
            'direction' => $direction,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $message = Capsule::table('mod_sms_messages')->where('id', $messageId)->first();

        Capsule::table('mod_sms_chatbox')
            ->where('id', $chatboxId)
            ->update([
                'client_id' => $clientId ?: null,
                'last_message' => $message ? substr($message->message ?? '', 0, 255) : '',
                'last_message_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private static function checkAutoReply(int $chatboxId, string $message, string $from): void
    {
        // Check for keyword-based auto-replies
        $message = strtolower(trim($message));

        $autoReplies = Capsule::table('mod_sms_auto_replies')
            ->where('status', 'active')
            ->where('channel', 'whatsapp')
            ->get();

        foreach ($autoReplies as $rule) {
            $keywords = json_decode($rule->keywords ?? '[]', true);
            $matchType = $rule->match_type ?? 'contains';

            $matched = false;
            foreach ($keywords as $keyword) {
                $keyword = strtolower($keyword);
                if ($matchType === 'exact' && $message === $keyword) {
                    $matched = true;
                } elseif ($matchType === 'contains' && strpos($message, $keyword) !== false) {
                    $matched = true;
                } elseif ($matchType === 'starts_with' && strpos($message, $keyword) === 0) {
                    $matched = true;
                }

                if ($matched) break;
            }

            if ($matched) {
                // Send auto-reply
                self::replyToConversation($chatboxId, $rule->reply_message);
                break;
            }
        }
    }
}
