<?php
/**
 * SMS Suite - Message Service
 *
 * Handles message creation, sending, and status updates
 */

namespace SMSSuite\Core;

use WHMCS\Database\Capsule;
use SMSSuite\Gateways\GatewayRegistry;
use SMSSuite\Gateways\MessageDTO;
use SMSSuite\Billing\BillingService;
use Exception;

class MessageService
{
    /**
     * Send a single message
     *
     * @param int $clientId
     * @param string $to
     * @param string $message
     * @param array $options
     * @return array ['success' => bool, 'message_id' => int, 'error' => string]
     */
    public static function send(int $clientId, string $to, string $message, array $options = []): array
    {
        try {
            // Validate inputs
            $to = self::normalizePhone($to);
            if (empty($to)) {
                return ['success' => false, 'error' => 'Invalid phone number'];
            }

            if (empty($message)) {
                return ['success' => false, 'error' => 'Message cannot be empty'];
            }

            // Apply template personalization if context provided
            if (!empty($options['personalize']) || !empty($options['contact_id']) || !empty($options['template_data'])) {
                require_once __DIR__ . '/TemplateService.php';
                $templateData = $options['template_data'] ?? [];
                $templateData['client_id'] = $clientId;
                $templateData['phone'] = $to;
                $templateData['recipient'] = $to;
                if (!empty($options['contact_id'])) {
                    $templateData['contact_id'] = $options['contact_id'];
                }
                $message = TemplateService::render($message, $templateData);
            }

            // Apply link tracking if enabled
            if (!empty($options['track_links'])) {
                require_once dirname(__DIR__) . '/Campaigns/AdvancedCampaignService.php';
                $message = \SMSSuite\Campaigns\AdvancedCampaignService::processMessageLinks(
                    $message,
                    $options['campaign_id'] ?? null,
                    null // message_id not yet available
                );
            }

            // Check opt-out/blacklist
            if (self::isBlocked($clientId, $to)) {
                return ['success' => false, 'error' => 'Number is blocked or opted out'];
            }

            // Get channel and gateway
            $channel = $options['channel'] ?? 'sms';
            $gatewayId = $options['gateway_id'] ?? self::getDefaultGateway($clientId, $channel);
            $senderId = $options['sender_id'] ?? self::getDefaultSenderId($clientId);

            if (!$gatewayId) {
                return ['success' => false, 'error' => 'No gateway configured'];
            }

            // Calculate segments
            $segmentResult = SegmentCounter::count($message, $channel);

            // Calculate cost and check balance (skip for admin broadcasts with clientId = 0)
            $cost = 0;
            if ($clientId > 0) {
                require_once dirname(__DIR__) . '/Billing/BillingService.php';

                // Extract country code from phone for rate lookup
                $countryCode = self::extractCountryCode($to);
                $cost = BillingService::calculateCost($clientId, $segmentResult->segments, $channel, $gatewayId, $countryCode);

                // Check balance
                if (!BillingService::hasBalance($clientId, $cost)) {
                    return ['success' => false, 'error' => 'Insufficient balance'];
                }
            }

            // Create message record
            $messageId = Capsule::table('mod_sms_messages')->insertGetId([
                'client_id' => $clientId,
                'campaign_id' => $options['campaign_id'] ?? null,
                'automation_id' => $options['automation_id'] ?? null,
                'gateway_id' => $gatewayId,
                'channel' => $channel,
                'direction' => 'outbound',
                'sender_id' => $senderId,
                'to_number' => $to,
                'message' => $message,
                'media_url' => $options['media_url'] ?? null,
                'encoding' => $segmentResult->encoding,
                'segments' => $segmentResult->segments,
                'units' => $segmentResult->units,
                'cost' => 0, // Will be calculated by billing
                'status' => 'queued',
                'api_key_id' => $options['api_key_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // If immediate send is requested (not queued)
            if (!empty($options['send_now'])) {
                return self::processMessage($messageId);
            }

            return [
                'success' => true,
                'message_id' => $messageId,
                'segments' => $segmentResult->segments,
                'encoding' => $segmentResult->encoding,
            ];

        } catch (Exception $e) {
            logActivity('SMS Suite: Message send error - ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process a queued message (actually send it)
     *
     * @param int $messageId
     * @return array
     */
    public static function processMessage(int $messageId): array
    {
        try {
            // Load message
            $message = Capsule::table('mod_sms_messages')->where('id', $messageId)->first();

            if (!$message) {
                return ['success' => false, 'error' => 'Message not found'];
            }

            if ($message->status !== 'queued') {
                return ['success' => false, 'error' => 'Message is not in queued status'];
            }

            // Update status to sending
            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update(['status' => 'sending', 'updated_at' => date('Y-m-d H:i:s')]);

            // Load gateway
            self::loadGatewayClasses();
            $gateway = GatewayRegistry::getById($message->gateway_id);

            // Create DTO
            $dto = new MessageDTO([
                'id' => $messageId,
                'clientId' => $message->client_id,
                'channel' => $message->channel,
                'to' => $message->to_number,
                'from' => $message->sender_id,
                'message' => $message->message,
                'mediaUrl' => $message->media_url,
                'encoding' => $message->encoding,
                'segments' => $message->segments,
            ]);

            // Send
            $result = $gateway->send($dto);

            // Update message status
            $updateData = [
                'status' => $result->success ? 'sent' : 'failed',
                'provider_message_id' => $result->messageId,
                'error' => $result->error,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update($updateData);

            if ($result->success) {
                // Deduct billing (only for client messages)
                if ($message->client_id > 0) {
                    require_once dirname(__DIR__) . '/Billing/BillingService.php';
                    $countryCode = self::extractCountryCode($message->to_number);
                    $cost = BillingService::calculateCost(
                        $message->client_id,
                        $message->segments,
                        $message->channel,
                        $message->gateway_id,
                        $countryCode
                    );
                    BillingService::deduct($message->client_id, $messageId, $cost, $message->segments);
                }

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'provider_message_id' => $result->messageId,
                    'segments' => $message->segments,
                    'encoding' => $message->encoding,
                ];
            } else {
                return [
                    'success' => false,
                    'message_id' => $messageId,
                    'error' => $result->error,
                ];
            }

        } catch (Exception $e) {
            // Update to failed
            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            logActivity('SMS Suite: Message processing error - ' . $e->getMessage());
            return ['success' => false, 'message_id' => $messageId, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update message status from delivery receipt
     *
     * @param string $providerMessageId
     * @param string $status
     * @param string|null $error
     * @return bool
     */
    public static function updateStatus(string $providerMessageId, string $status, ?string $error = null): bool
    {
        try {
            $message = Capsule::table('mod_sms_messages')
                ->where('provider_message_id', $providerMessageId)
                ->first();

            if (!$message) {
                logActivity("SMS Suite: Message not found for provider ID: {$providerMessageId}");
                return false;
            }

            $updateData = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($status === 'delivered') {
                $updateData['delivered_at'] = date('Y-m-d H:i:s');
            }

            if ($error) {
                $updateData['error'] = $error;
            }

            Capsule::table('mod_sms_messages')
                ->where('id', $message->id)
                ->update($updateData);

            // If failed, consider refunding credits (will be implemented in billing slice)

            return true;

        } catch (Exception $e) {
            logActivity('SMS Suite: Status update error - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if phone number is blocked
     *
     * @param int $clientId
     * @param string $phone
     * @return bool
     */
    public static function isBlocked(int $clientId, string $phone): bool
    {
        // Check global opt-out
        $optout = Capsule::table('mod_sms_optouts')
            ->where('phone', $phone)
            ->first();

        if ($optout) {
            return true;
        }

        // Check client blacklist
        $blacklisted = Capsule::table('mod_sms_blacklist')
            ->where(function ($query) use ($clientId, $phone) {
                $query->where('client_id', $clientId)
                      ->orWhereNull('client_id');
            })
            ->where('phone', $phone)
            ->exists();

        return $blacklisted;
    }

    /**
     * Get default gateway for client
     *
     * @param int $clientId
     * @param string $channel
     * @return int|null
     */
    public static function getDefaultGateway(int $clientId, string $channel = 'sms'): ?int
    {
        // Check client settings
        $settings = Capsule::table('mod_sms_settings')
            ->where('client_id', $clientId)
            ->first();

        if ($settings && $settings->default_gateway_id) {
            return $settings->default_gateway_id;
        }

        // Get first active gateway that supports the channel
        $gateway = Capsule::table('mod_sms_gateways')
            ->where('status', 1)
            ->where(function ($query) use ($channel) {
                $query->where('channel', $channel)
                      ->orWhere('channel', 'both');
            })
            ->orderBy('id')
            ->first();

        return $gateway ? $gateway->id : null;
    }

    /**
     * Get default sender ID for client
     *
     * @param int $clientId
     * @return string|null
     */
    public static function getDefaultSenderId(int $clientId): ?string
    {
        // Check client settings
        $settings = Capsule::table('mod_sms_settings')
            ->where('client_id', $clientId)
            ->first();

        if ($settings && $settings->default_sender_id) {
            return $settings->default_sender_id;
        }

        // Get first active sender ID for client
        $senderId = Capsule::table('mod_sms_sender_ids')
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        return $senderId ? $senderId->sender_id : null;
    }

    /**
     * Normalize phone number
     *
     * @param string $phone
     * @return string
     */
    public static function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure it has at least some digits
        if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
            return '';
        }

        return $phone;
    }

    /**
     * Load gateway class files
     */
    private static function loadGatewayClasses(): void
    {
        $baseDir = dirname(__DIR__) . '/Gateways/';

        require_once $baseDir . 'GatewayInterface.php';
        require_once $baseDir . 'AbstractGateway.php';
        require_once $baseDir . 'GenericHttpGateway.php';
        require_once $baseDir . 'TwilioGateway.php';
        require_once $baseDir . 'PlivoGateway.php';
        require_once $baseDir . 'VonageGateway.php';
        require_once $baseDir . 'InfobipGateway.php';
        require_once $baseDir . 'GatewayRegistry.php';
    }

    /**
     * Get message by ID
     *
     * @param int $messageId
     * @return object|null
     */
    public static function getMessage(int $messageId): ?object
    {
        return Capsule::table('mod_sms_messages')
            ->where('id', $messageId)
            ->first();
    }

    /**
     * Get messages for client
     *
     * @param int $clientId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getMessages(int $clientId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = Capsule::table('mod_sms_messages')
            ->where('client_id', $clientId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('to_number', 'like', $search)
                  ->orWhere('message', 'like', $search);
            });
        }

        $total = $query->count();

        $messages = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'messages' => $messages,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Extract country code from phone number
     *
     * @param string $phone
     * @return string|null
     */
    public static function extractCountryCode(string $phone): ?string
    {
        // Remove non-digits except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Common country code patterns (simplified)
        $countryCodes = [
            '1' => 'US', // Also Canada, Caribbean
            '7' => 'RU',
            '20' => 'EG',
            '27' => 'ZA',
            '30' => 'GR',
            '31' => 'NL',
            '32' => 'BE',
            '33' => 'FR',
            '34' => 'ES',
            '36' => 'HU',
            '39' => 'IT',
            '40' => 'RO',
            '41' => 'CH',
            '43' => 'AT',
            '44' => 'GB',
            '45' => 'DK',
            '46' => 'SE',
            '47' => 'NO',
            '48' => 'PL',
            '49' => 'DE',
            '52' => 'MX',
            '54' => 'AR',
            '55' => 'BR',
            '56' => 'CL',
            '57' => 'CO',
            '60' => 'MY',
            '61' => 'AU',
            '62' => 'ID',
            '63' => 'PH',
            '64' => 'NZ',
            '65' => 'SG',
            '66' => 'TH',
            '81' => 'JP',
            '82' => 'KR',
            '84' => 'VN',
            '86' => 'CN',
            '90' => 'TR',
            '91' => 'IN',
            '92' => 'PK',
            '93' => 'AF',
            '94' => 'LK',
            '95' => 'MM',
            '98' => 'IR',
            '212' => 'MA',
            '213' => 'DZ',
            '216' => 'TN',
            '218' => 'LY',
            '220' => 'GM',
            '221' => 'SN',
            '234' => 'NG',
            '254' => 'KE',
            '255' => 'TZ',
            '256' => 'UG',
            '260' => 'ZM',
            '263' => 'ZW',
            '351' => 'PT',
            '352' => 'LU',
            '353' => 'IE',
            '354' => 'IS',
            '355' => 'AL',
            '358' => 'FI',
            '359' => 'BG',
            '370' => 'LT',
            '371' => 'LV',
            '372' => 'EE',
            '380' => 'UA',
            '381' => 'RS',
            '385' => 'HR',
            '386' => 'SI',
            '420' => 'CZ',
            '421' => 'SK',
            '852' => 'HK',
            '853' => 'MO',
            '880' => 'BD',
            '886' => 'TW',
            '960' => 'MV',
            '961' => 'LB',
            '962' => 'JO',
            '963' => 'SY',
            '964' => 'IQ',
            '965' => 'KW',
            '966' => 'SA',
            '967' => 'YE',
            '968' => 'OM',
            '970' => 'PS',
            '971' => 'AE',
            '972' => 'IL',
            '973' => 'BH',
            '974' => 'QA',
        ];

        // Remove leading + or 00
        if (substr($phone, 0, 1) === '+') {
            $phone = substr($phone, 1);
        } elseif (substr($phone, 0, 2) === '00') {
            $phone = substr($phone, 2);
        }

        // Try to match country codes (longest first)
        foreach ([3, 2, 1] as $len) {
            $code = substr($phone, 0, $len);
            if (isset($countryCodes[$code])) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Send a message directly without client context (for admin/system notifications)
     *
     * @param string $to Phone number
     * @param string $message Message content
     * @param array $options Additional options
     * @return array
     */
    public static function sendDirect(string $to, string $message, array $options = []): array
    {
        try {
            $to = self::normalizePhone($to);
            if (empty($to)) {
                return ['success' => false, 'error' => 'Invalid phone number'];
            }

            if (empty($message)) {
                return ['success' => false, 'error' => 'Message cannot be empty'];
            }

            // Get system default gateway
            $gatewayId = $options['gateway_id'] ?? self::getSystemDefaultGateway();
            if (!$gatewayId) {
                return ['success' => false, 'error' => 'No gateway configured'];
            }

            $channel = $options['channel'] ?? 'sms';
            $senderId = $options['sender_id'] ?? self::getSystemDefaultSenderId();

            // Calculate segments
            $segmentResult = SegmentCounter::count($message, $channel);

            // Create message record (client_id = 0 for system messages)
            $messageId = Capsule::table('mod_sms_messages')->insertGetId([
                'client_id' => 0,
                'campaign_id' => null,
                'automation_id' => null,
                'gateway_id' => $gatewayId,
                'channel' => $channel,
                'direction' => 'outbound',
                'sender_id' => $senderId,
                'to_number' => $to,
                'message' => $message,
                'encoding' => $segmentResult->encoding,
                'segments' => $segmentResult->segments,
                'units' => $segmentResult->units,
                'cost' => 0,
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Send immediately
            return self::processMessage($messageId);

        } catch (Exception $e) {
            logActivity('SMS Suite: Direct message send error - ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get system default gateway (first active gateway)
     *
     * @return int|null
     */
    private static function getSystemDefaultGateway(): ?int
    {
        $gateway = Capsule::table('mod_sms_gateways')
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        return $gateway->id ?? null;
    }

    /**
     * Get system default sender ID
     *
     * @return string|null
     */
    private static function getSystemDefaultSenderId(): ?string
    {
        $setting = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', 'default_sender_id')
            ->first();

        return $setting->value ?? null;
    }
}
