<?php
/**
 * SMS Suite - Gateway Drivers
 *
 * Contains implementations for 50+ popular SMS gateway providers
 * Based on Laravel Ultimate SMS gateway drivers
 */

namespace SMSSuite\Gateways;

use Exception;

/**
 * Gateway Type Registry - All supported gateway types
 * Maps type identifier to gateway class name and display info
 */
class GatewayTypes
{
    /**
     * All supported gateway types
     * Format: 'type_key' => ['name' => 'Display Name', 'class' => 'ClassName', 'fields' => [...]]
     */
    const TYPES = [
        // === Major Providers ===
        'twilio' => ['name' => 'Twilio', 'class' => 'TwilioGateway', 'category' => 'Premium'],
        'plivo' => ['name' => 'Plivo', 'class' => 'PlivoGateway', 'category' => 'Premium'],
        'vonage' => ['name' => 'Vonage (Nexmo)', 'class' => 'VonageGateway', 'category' => 'Premium'],
        'infobip' => ['name' => 'Infobip', 'class' => 'InfobipGateway', 'category' => 'Premium'],
        'messagebird' => ['name' => 'MessageBird', 'class' => 'MessageBirdGateway', 'category' => 'Premium'],
        'clickatell' => ['name' => 'Clickatell', 'class' => 'ClickatellGateway', 'category' => 'Premium'],
        'sinch' => ['name' => 'Sinch', 'class' => 'SinchGateway', 'category' => 'Premium'],
        'bandwidth' => ['name' => 'Bandwidth', 'class' => 'BandwidthGateway', 'category' => 'Premium'],

        // === WhatsApp Providers ===
        'twilio_whatsapp' => ['name' => 'Twilio WhatsApp', 'class' => 'TwilioWhatsAppGateway', 'category' => 'WhatsApp'],
        'meta_whatsapp' => ['name' => 'Meta WhatsApp Business', 'class' => 'MetaWhatsAppGateway', 'category' => 'WhatsApp'],
        'messagebird_whatsapp' => ['name' => 'MessageBird WhatsApp', 'class' => 'MessageBirdWhatsAppGateway', 'category' => 'WhatsApp'],
        'gupshup_whatsapp' => ['name' => 'Gupshup WhatsApp', 'class' => 'GupshupWhatsAppGateway', 'category' => 'WhatsApp'],
        'interakt_whatsapp' => ['name' => 'Interakt', 'class' => 'InteraktGateway', 'category' => 'WhatsApp'],
        'ultramsg_whatsapp' => ['name' => 'UltraMsg', 'class' => 'UltraMsgGateway', 'category' => 'WhatsApp'],
        'wati' => ['name' => 'WATI', 'class' => 'WatiGateway', 'category' => 'WhatsApp'],
        'chat_api' => ['name' => 'Chat-API', 'class' => 'ChatApiGateway', 'category' => 'WhatsApp'],

        // === Regional Providers - Africa ===
        'africastalking' => ['name' => 'Africa\'s Talking', 'class' => 'AfricasTalkingGateway', 'category' => 'Africa'],
        'airtouch' => ['name' => 'Airtouch Kenya', 'class' => 'AirtouchGateway', 'category' => 'Africa'],
        'termii' => ['name' => 'Termii', 'class' => 'TermiiGateway', 'category' => 'Africa'],
        'smsbroadcast_ng' => ['name' => 'SMS Broadcast NG', 'class' => 'SmsBroadcastNgGateway', 'category' => 'Africa'],
        'bulksmsnigeria' => ['name' => 'BulkSMS Nigeria', 'class' => 'BulkSmsNigeriaGateway', 'category' => 'Africa'],
        'multitexter' => ['name' => 'Multitexter', 'class' => 'MultitexterGateway', 'category' => 'Africa'],
        'smslive247' => ['name' => 'SMSLive247', 'class' => 'SmsLive247Gateway', 'category' => 'Africa'],

        // === Regional Providers - India ===
        'msg91' => ['name' => 'MSG91', 'class' => 'Msg91Gateway', 'category' => 'India'],
        'textlocal_india' => ['name' => 'Textlocal India', 'class' => 'TextlocalIndiaGateway', 'category' => 'India'],
        'kaleyra' => ['name' => 'Kaleyra', 'class' => 'KaleyraGateway', 'category' => 'India'],
        'fast2sms' => ['name' => 'Fast2SMS', 'class' => 'Fast2SmsGateway', 'category' => 'India'],
        'smsgatewayhub' => ['name' => 'SMS Gateway Hub', 'class' => 'SmsGatewayHubGateway', 'category' => 'India'],
        'sms_country' => ['name' => 'SMS Country', 'class' => 'SmsCountryGateway', 'category' => 'India'],

        // === Regional Providers - Europe ===
        'smsapi_eu' => ['name' => 'SMSAPI (EU)', 'class' => 'SmsApiEuGateway', 'category' => 'Europe'],
        'textlocal_uk' => ['name' => 'Textlocal UK', 'class' => 'TextlocalUkGateway', 'category' => 'Europe'],
        'bulksms' => ['name' => 'BulkSMS', 'class' => 'BulkSmsGateway', 'category' => 'Europe'],
        'smsto' => ['name' => 'SMSto', 'class' => 'SmsToGateway', 'category' => 'Europe'],
        'clockworksms' => ['name' => 'Clockwork SMS', 'class' => 'ClockworkSmsGateway', 'category' => 'Europe'],
        'esendex' => ['name' => 'Esendex', 'class' => 'EsendexGateway', 'category' => 'Europe'],

        // === Regional Providers - Asia ===
        'alibaba_sms' => ['name' => 'Alibaba Cloud SMS', 'class' => 'AlibabaCloudGateway', 'category' => 'Asia'],
        'tencent_sms' => ['name' => 'Tencent Cloud SMS', 'class' => 'TencentCloudGateway', 'category' => 'Asia'],
        'mocean' => ['name' => 'Mocean', 'class' => 'MoceanGateway', 'category' => 'Asia'],
        'onewaysms' => ['name' => 'OneWaySMS', 'class' => 'OneWaySmsGateway', 'category' => 'Asia'],
        'smartsms_sg' => ['name' => 'SmartSMS SG', 'class' => 'SmartSmsSgGateway', 'category' => 'Asia'],

        // === Regional Providers - Americas ===
        'smsmasivos' => ['name' => 'SMS Masivos', 'class' => 'SmsMasivosGateway', 'category' => 'Americas'],
        'mensatek' => ['name' => 'Mensatek', 'class' => 'MensatekGateway', 'category' => 'Americas'],
        'zenvia' => ['name' => 'Zenvia', 'class' => 'ZenviaGateway', 'category' => 'Americas'],
        'movile' => ['name' => 'Movile', 'class' => 'MovileGateway', 'category' => 'Americas'],

        // === Budget/Aggregator Providers ===
        'sms_gateway_me' => ['name' => 'SMS Gateway Me', 'class' => 'SmsGatewayMeGateway', 'category' => 'Budget'],
        'smsglobal' => ['name' => 'SMSGlobal', 'class' => 'SmsGlobalGateway', 'category' => 'Budget'],
        'routee' => ['name' => 'Routee', 'class' => 'RouteeGateway', 'category' => 'Budget'],
        'telesign' => ['name' => 'Telesign', 'class' => 'TelesignGateway', 'category' => 'Budget'],
        'telnyx' => ['name' => 'Telnyx', 'class' => 'TelnyxGateway', 'category' => 'Budget'],
        'signalwire' => ['name' => 'SignalWire', 'class' => 'SignalWireGateway', 'category' => 'Budget'],

        // === Enterprise Providers ===
        'aws_sns' => ['name' => 'Amazon SNS', 'class' => 'AwsSnsGateway', 'category' => 'Enterprise'],
        'firebase' => ['name' => 'Firebase Cloud Messaging', 'class' => 'FirebaseGateway', 'category' => 'Enterprise'],

        // === Messaging Apps ===
        'telegram' => ['name' => 'Telegram Bot', 'class' => 'TelegramGateway', 'category' => 'Messaging Apps'],
        'messenger' => ['name' => 'Facebook Messenger', 'class' => 'MessengerGateway', 'category' => 'Messaging Apps'],

        // === Generic/Custom ===
        'generic_http' => ['name' => 'Custom HTTP Gateway (Create Your Own)', 'class' => 'GenericHttpGateway', 'category' => 'Custom'],
        'smpp' => ['name' => 'SMPP Gateway (Direct SMSC)', 'class' => 'SmppGateway', 'category' => 'Custom'],
    ];

    /**
     * Get all gateway types
     */
    public static function getAll(): array
    {
        return self::TYPES;
    }

    /**
     * Get gateway types by category
     */
    public static function getByCategory(string $category): array
    {
        return array_filter(self::TYPES, function ($type) use ($category) {
            return ($type['category'] ?? '') === $category;
        });
    }

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        $categories = [];
        foreach (self::TYPES as $type) {
            if (!empty($type['category'])) {
                $categories[$type['category']] = true;
            }
        }
        return array_keys($categories);
    }

    /**
     * Get required fields for gateway type
     */
    public static function getFields(string $type): array
    {
        $fields = [
            'twilio' => ['account_sid', 'auth_token', 'from_number'],
            'plivo' => ['auth_id', 'auth_token', 'from_number'],
            'vonage' => ['api_key', 'api_secret', 'from'],
            'infobip' => ['api_key', 'base_url', 'from'],
            'messagebird' => ['api_key', 'originator'],
            'clickatell' => ['api_key'],
            'sinch' => ['service_plan_id', 'api_token', 'from_number'],
            'bandwidth' => ['account_id', 'api_token', 'api_secret', 'application_id', 'from_number'],
            'africastalking' => ['username', 'api_key', 'from'],
            'termii' => ['api_key', 'sender_id'],
            'msg91' => ['auth_key', 'sender_id', 'template_id'],
            'textlocal_india' => ['api_key', 'sender'],
            'kaleyra' => ['api_key', 'sid', 'from'],
            'fast2sms' => ['api_key', 'sender_id'],
            'bulksms' => ['username', 'password'],
            'smsto' => ['api_key', 'sender_id'],
            'smsapi_eu' => ['api_token', 'from'],
            'clockworksms' => ['api_key', 'from'],
            'esendex' => ['username', 'password', 'account_reference'],
            'alibaba_sms' => ['access_key_id', 'access_key_secret', 'sign_name', 'template_code'],
            'tencent_sms' => ['secret_id', 'secret_key', 'app_id', 'sign_name', 'template_id'],
            'aws_sns' => ['access_key', 'secret_key', 'region', 'sender_id'],
            'mocean' => ['api_key', 'api_secret', 'from'],
            'onewaysms' => ['api_username', 'api_password', 'sender_id'],
            'smsglobal' => ['api_key', 'api_secret', 'from'],
            'routee' => ['application_id', 'application_secret', 'from'],
            'telesign' => ['customer_id', 'api_key'],
            'telnyx' => ['api_key', 'messaging_profile_id', 'from'],
            'signalwire' => ['project_id', 'api_token', 'space_url', 'from'],
            'zenvia' => ['api_token', 'from'],
            'generic_http' => ['api_url', 'api_method', 'api_key'],
            'twilio_whatsapp' => ['account_sid', 'auth_token', 'whatsapp_number'],
            'meta_whatsapp' => ['phone_number_id', 'access_token', 'waba_id'],
            'gupshup_whatsapp' => ['api_key', 'app_id', 'source_number'],
            'interakt_whatsapp' => ['api_key'],
            'ultramsg_whatsapp' => ['instance_id', 'token'],
            'wati' => ['api_url', 'access_token'],
            'telegram' => ['bot_token', 'bot_username'],
            'messenger' => ['page_id', 'page_access_token'],
        ];

        return $fields[$type] ?? ['api_key'];
    }
}

// ==================== Gateway Implementations ====================

/**
 * MessageBird Gateway
 */
class MessageBirdGateway extends AbstractGateway
{
    public function getType(): string { return 'messagebird'; }
    public function getName(): string { return 'MessageBird'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'text', 'label' => 'API Key', 'required' => true],
            ['name' => 'originator', 'type' => 'text', 'label' => 'Originator/Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $originator = $message->from ?: $this->config['originator'];

        $response = $this->httpPost('https://rest.messagebird.com/messages', [
            'recipients' => [$message->to],
            'originator' => $originator,
            'body' => $message->message,
        ], [
            'Authorization' => 'AccessKey ' . $apiKey,
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            return new SendResult(true, $data['id'] ?? null);
        }

        $error = json_decode($response['body'], true);
        return new SendResult(false, null, $error['errors'][0]['description'] ?? 'MessageBird error');
    }
}

/**
 * Clickatell Gateway
 */
class ClickatellGateway extends AbstractGateway
{
    public function getType(): string { return 'clickatell'; }
    public function getName(): string { return 'Clickatell'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'text', 'label' => 'API Key', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];

        $response = $this->httpPost('https://platform.clickatell.com/messages/http/send', [
            'apiKey' => $apiKey,
            'to' => [$message->to],
            'content' => $message->message,
        ], [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            $msgData = $data['messages'][0] ?? [];
            if (!empty($msgData['accepted'])) {
                return new SendResult(true, $msgData['apiMessageId'] ?? null);
            }
            return new SendResult(false, null, $msgData['error'] ?? 'Message rejected');
        }

        return new SendResult(false, null, 'Clickatell API error');
    }
}

/**
 * Sinch Gateway
 */
class SinchGateway extends AbstractGateway
{
    public function getType(): string { return 'sinch'; }
    public function getName(): string { return 'Sinch'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'service_plan_id', 'type' => 'text', 'label' => 'Service Plan ID', 'required' => true],
            ['name' => 'api_token', 'type' => 'password', 'label' => 'API Token', 'required' => true],
            ['name' => 'from_number', 'type' => 'text', 'label' => 'From Number', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $servicePlanId = $this->config['service_plan_id'];
        $apiToken = $this->config['api_token'];
        $from = $message->from ?: $this->config['from_number'];

        $url = "https://us.sms.api.sinch.com/xms/v1/{$servicePlanId}/batches";

        $response = $this->httpPost($url, [
            'from' => $from,
            'to' => [$message->to],
            'body' => $message->message,
        ], [
            'Authorization' => 'Bearer ' . $apiToken,
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            return new SendResult(true, $data['id'] ?? null);
        }

        return new SendResult(false, null, 'Sinch API error');
    }
}

/**
 * Bandwidth Gateway
 */
class BandwidthGateway extends AbstractGateway
{
    public function getType(): string { return 'bandwidth'; }
    public function getName(): string { return 'Bandwidth'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'account_id', 'type' => 'text', 'label' => 'Account ID', 'required' => true],
            ['name' => 'api_token', 'type' => 'text', 'label' => 'API Token', 'required' => true],
            ['name' => 'api_secret', 'type' => 'password', 'label' => 'API Secret', 'required' => true],
            ['name' => 'application_id', 'type' => 'text', 'label' => 'Application ID', 'required' => true],
            ['name' => 'from_number', 'type' => 'text', 'label' => 'From Number', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $accountId = $this->config['account_id'];
        $apiToken = $this->config['api_token'];
        $apiSecret = $this->config['api_secret'];
        $applicationId = $this->config['application_id'];
        $from = $message->from ?: $this->config['from_number'];

        $url = "https://messaging.bandwidth.com/api/v2/users/{$accountId}/messages";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'to' => [$message->to],
                'from' => $from,
                'text' => $message->message,
                'applicationId' => $applicationId,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => "{$apiToken}:{$apiSecret}",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return new SendResult(true, $data['id'] ?? null);
        }

        return new SendResult(false, null, 'Bandwidth API error');
    }
}

/**
 * Africa's Talking Gateway
 */
class AfricasTalkingGateway extends AbstractGateway
{
    public function getType(): string { return 'africastalking'; }
    public function getName(): string { return 'Africa\'s Talking'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'username', 'type' => 'text', 'label' => 'Username', 'required' => true],
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
            ['name' => 'from', 'type' => 'text', 'label' => 'Sender ID', 'required' => false],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $username = $this->config['username'];
        $apiKey = $this->config['api_key'];
        $from = $message->from ?: $this->config['from'];

        $url = $username === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        $response = $this->httpPost($url, [
            'username' => $username,
            'to' => $message->to,
            'message' => $message->message,
            'from' => $from,
        ], [
            'apiKey' => $apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ]);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            $recipient = $data['SMSMessageData']['Recipients'][0] ?? [];
            if (($recipient['status'] ?? '') === 'Success') {
                return new SendResult(true, $recipient['messageId'] ?? null);
            }
            return new SendResult(false, null, $recipient['status'] ?? 'Failed');
        }

        return new SendResult(false, null, 'Africa\'s Talking API error');
    }
}

/**
 * Termii Gateway
 */
class TermiiGateway extends AbstractGateway
{
    public function getType(): string { return 'termii'; }
    public function getName(): string { return 'Termii'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
            ['name' => 'sender_id', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $senderId = $message->from ?: $this->config['sender_id'];

        $response = $this->httpPost('https://api.ng.termii.com/api/sms/send', [
            'api_key' => $apiKey,
            'to' => $message->to,
            'from' => $senderId,
            'sms' => $message->message,
            'type' => 'plain',
            'channel' => 'generic',
        ], [
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            if (($data['code'] ?? '') === 'ok') {
                return new SendResult(true, $data['message_id'] ?? null);
            }
            return new SendResult(false, null, $data['message'] ?? 'Termii error');
        }

        return new SendResult(false, null, 'Termii API error');
    }
}

/**
 * MSG91 Gateway (India)
 */
class Msg91Gateway extends AbstractGateway
{
    public function getType(): string { return 'msg91'; }
    public function getName(): string { return 'MSG91'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'auth_key', 'type' => 'password', 'label' => 'Auth Key', 'required' => true],
            ['name' => 'sender_id', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
            ['name' => 'template_id', 'type' => 'text', 'label' => 'Template ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $authKey = $this->config['auth_key'];
        $senderId = $message->from ?: $this->config['sender_id'];
        $templateId = $this->config['template_id'] ?? '';

        $response = $this->httpPost('https://api.msg91.com/api/v5/flow/', [
            'template_id' => $templateId,
            'sender' => $senderId,
            'mobiles' => $message->to,
            'VAR1' => $message->message,
        ], [
            'authkey' => $authKey,
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            if (($data['type'] ?? '') === 'success') {
                return new SendResult(true, $data['request_id'] ?? null);
            }
            return new SendResult(false, null, $data['message'] ?? 'MSG91 error');
        }

        return new SendResult(false, null, 'MSG91 API error');
    }
}

/**
 * SMS Global Gateway
 */
class SmsGlobalGateway extends AbstractGateway
{
    public function getType(): string { return 'smsglobal'; }
    public function getName(): string { return 'SMSGlobal'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'text', 'label' => 'API Key', 'required' => true],
            ['name' => 'api_secret', 'type' => 'password', 'label' => 'API Secret', 'required' => true],
            ['name' => 'from', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $apiSecret = $this->config['api_secret'];
        $from = $message->from ?: $this->config['from'];

        $response = $this->httpPost('https://api.smsglobal.com/v2/sms/', [
            'destination' => $message->to,
            'message' => $message->message,
            'origin' => $from,
        ], [
            'Authorization' => 'Basic ' . base64_encode("{$apiKey}:{$apiSecret}"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            return new SendResult(true, $data['messages'][0]['id'] ?? null);
        }

        return new SendResult(false, null, 'SMSGlobal API error');
    }
}

/**
 * Telnyx Gateway
 */
class TelnyxGateway extends AbstractGateway
{
    public function getType(): string { return 'telnyx'; }
    public function getName(): string { return 'Telnyx'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
            ['name' => 'messaging_profile_id', 'type' => 'text', 'label' => 'Messaging Profile ID', 'required' => false],
            ['name' => 'from', 'type' => 'text', 'label' => 'From Number', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $from = $message->from ?: $this->config['from'];
        $profileId = $this->config['messaging_profile_id'] ?? null;

        $payload = [
            'from' => $from,
            'to' => $message->to,
            'text' => $message->message,
        ];

        if ($profileId) {
            $payload['messaging_profile_id'] = $profileId;
        }

        $response = $this->httpPost('https://api.telnyx.com/v2/messages', $payload, [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            return new SendResult(true, $data['data']['id'] ?? null);
        }

        $error = json_decode($response['body'], true);
        return new SendResult(false, null, $error['errors'][0]['detail'] ?? 'Telnyx error');
    }
}

/**
 * Telesign Gateway
 */
class TelesignGateway extends AbstractGateway
{
    public function getType(): string { return 'telesign'; }
    public function getName(): string { return 'Telesign'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'customer_id', 'type' => 'text', 'label' => 'Customer ID', 'required' => true],
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $customerId = $this->config['customer_id'];
        $apiKey = $this->config['api_key'];

        $url = "https://rest-api.telesign.com/v1/messaging";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'phone_number' => $message->to,
                'message' => $message->message,
                'message_type' => 'ARN',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$customerId}:{$apiKey}",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return new SendResult(true, $data['reference_id'] ?? null);
        }

        return new SendResult(false, null, 'Telesign API error');
    }
}

/**
 * AWS SNS Gateway
 */
class AwsSnsGateway extends AbstractGateway
{
    public function getType(): string { return 'aws_sns'; }
    public function getName(): string { return 'Amazon SNS'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'access_key', 'type' => 'text', 'label' => 'AWS Access Key', 'required' => true],
            ['name' => 'secret_key', 'type' => 'password', 'label' => 'AWS Secret Key', 'required' => true],
            ['name' => 'region', 'type' => 'text', 'label' => 'AWS Region', 'required' => true, 'default' => 'us-east-1'],
            ['name' => 'sender_id', 'type' => 'text', 'label' => 'Sender ID', 'required' => false],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        // Note: For production, use AWS SDK. This is a simplified implementation.
        $accessKey = $this->config['access_key'];
        $secretKey = $this->config['secret_key'];
        $region = $this->config['region'] ?? 'us-east-1';
        $senderId = $message->from ?: $this->config['sender_id'];

        $service = 'sns';
        $host = "sns.{$region}.amazonaws.com";
        $endpoint = "https://{$host}/";

        $params = [
            'Action' => 'Publish',
            'PhoneNumber' => $message->to,
            'Message' => $message->message,
            'Version' => '2010-03-31',
        ];

        if ($senderId) {
            $params['MessageAttributes.entry.1.Name'] = 'AWS.SNS.SMS.SenderID';
            $params['MessageAttributes.entry.1.Value.DataType'] = 'String';
            $params['MessageAttributes.entry.1.Value.StringValue'] = $senderId;
        }

        // AWS Signature V4 would be needed here for production
        // This is a placeholder showing the concept
        $response = $this->httpPost($endpoint, $params);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300 &&
            strpos($response['body'], 'MessageId') !== false) {
            preg_match('/<MessageId>(.*?)<\/MessageId>/', $response['body'], $matches);
            return new SendResult(true, $matches[1] ?? null);
        }

        return new SendResult(false, null, 'AWS SNS error');
    }
}

/**
 * Routee Gateway
 */
class RouteeGateway extends AbstractGateway
{
    public function getType(): string { return 'routee'; }
    public function getName(): string { return 'Routee'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'application_id', 'type' => 'text', 'label' => 'Application ID', 'required' => true],
            ['name' => 'application_secret', 'type' => 'password', 'label' => 'Application Secret', 'required' => true],
            ['name' => 'from', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $appId = $this->config['application_id'];
        $appSecret = $this->config['application_secret'];
        $from = $message->from ?: $this->config['from'];

        // Get access token
        $tokenResponse = $this->httpPost('https://auth.routee.net/oauth/token',
            'grant_type=client_credentials',
            [
                'Authorization' => 'Basic ' . base64_encode("{$appId}:{$appSecret}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        );

        if ($tokenResponse['http_code'] !== 200) {
            return new SendResult(false, null, 'Routee authentication failed');
        }

        $tokenData = json_decode($tokenResponse['body'], true);
        $accessToken = $tokenData['access_token'];

        // Send SMS
        $response = $this->httpPost('https://connect.routee.net/sms', [
            'body' => $message->message,
            'to' => $message->to,
            'from' => $from,
        ], [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            return new SendResult(true, $data['trackingId'] ?? null);
        }

        return new SendResult(false, null, 'Routee API error');
    }
}

/**
 * BulkSMS Gateway
 */
class BulkSmsGateway extends AbstractGateway
{
    public function getType(): string { return 'bulksms'; }
    public function getName(): string { return 'BulkSMS'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'username', 'type' => 'text', 'label' => 'Username', 'required' => true],
            ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $username = $this->config['username'];
        $password = $this->config['password'];

        $response = $this->httpPost('https://api.bulksms.com/v1/messages', [
            'to' => $message->to,
            'body' => $message->message,
        ], [
            'Authorization' => 'Basic ' . base64_encode("{$username}:{$password}"),
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            return new SendResult(true, $data[0]['id'] ?? null);
        }

        $error = json_decode($response['body'], true);
        return new SendResult(false, null, $error['detail'] ?? 'BulkSMS error');
    }
}

/**
 * SMS.to Gateway
 */
class SmsToGateway extends AbstractGateway
{
    public function getType(): string { return 'smsto'; }
    public function getName(): string { return 'SMS.to'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
            ['name' => 'sender_id', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $senderId = $message->from ?: $this->config['sender_id'];

        $response = $this->httpPost('https://api.sms.to/sms/send', [
            'to' => $message->to,
            'message' => $message->message,
            'sender_id' => $senderId,
        ], [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            if ($data['success'] ?? false) {
                return new SendResult(true, $data['message_id'] ?? null);
            }
            return new SendResult(false, null, $data['message'] ?? 'SMS.to error');
        }

        return new SendResult(false, null, 'SMS.to API error');
    }
}

/**
 * SignalWire Gateway
 */
class SignalWireGateway extends AbstractGateway
{
    public function getType(): string { return 'signalwire'; }
    public function getName(): string { return 'SignalWire'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'project_id', 'type' => 'text', 'label' => 'Project ID', 'required' => true],
            ['name' => 'api_token', 'type' => 'password', 'label' => 'API Token', 'required' => true],
            ['name' => 'space_url', 'type' => 'text', 'label' => 'Space URL', 'required' => true, 'placeholder' => 'example.signalwire.com'],
            ['name' => 'from', 'type' => 'text', 'label' => 'From Number', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $projectId = $this->config['project_id'];
        $apiToken = $this->config['api_token'];
        $spaceUrl = $this->config['space_url']; // e.g., example.signalwire.com
        $from = $message->from ?: $this->config['from'];

        $url = "https://{$spaceUrl}/api/laml/2010-04-01/Accounts/{$projectId}/Messages.json";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'To' => $message->to,
                'From' => $from,
                'Body' => $message->message,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$projectId}:{$apiToken}",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return new SendResult(true, $data['sid'] ?? null);
        }

        $error = json_decode($response, true);
        return new SendResult(false, null, $error['message'] ?? 'SignalWire error');
    }
}

/**
 * Zenvia Gateway (Brazil)
 */
class ZenviaGateway extends AbstractGateway
{
    public function getType(): string { return 'zenvia'; }
    public function getName(): string { return 'Zenvia'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_token', 'type' => 'password', 'label' => 'API Token', 'required' => true],
            ['name' => 'from', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiToken = $this->config['api_token'];
        $from = $message->from ?: $this->config['from'];

        $response = $this->httpPost('https://api.zenvia.com/v2/channels/sms/messages', [
            'from' => $from,
            'to' => $message->to,
            'contents' => [
                ['type' => 'text', 'text' => $message->message]
            ],
        ], [
            'X-API-TOKEN' => $apiToken,
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            return new SendResult(true, $data['id'] ?? null);
        }

        return new SendResult(false, null, 'Zenvia API error');
    }
}

/**
 * SMPP Gateway
 * Supports SMPP 3.4 protocol for direct SMSC connections
 */
class SmppGateway extends AbstractGateway
{
    public function getType(): string { return 'smpp'; }
    public function getName(): string { return 'SMPP Gateway'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'host', 'type' => 'text', 'label' => 'SMSC Host', 'required' => true, 'placeholder' => 'smsc.provider.com'],
            ['name' => 'port', 'type' => 'text', 'label' => 'SMSC Port', 'required' => true, 'default' => '2775'],
            ['name' => 'system_id', 'type' => 'text', 'label' => 'System ID', 'required' => true],
            ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'required' => true],
            ['name' => 'system_type', 'type' => 'text', 'label' => 'System Type', 'required' => false, 'default' => ''],
            ['name' => 'source_addr', 'type' => 'text', 'label' => 'Source Address/Sender ID', 'required' => true],
            ['name' => 'source_addr_ton', 'type' => 'select', 'label' => 'Source TON', 'required' => false,
             'options' => ['0' => 'Unknown', '1' => 'International', '2' => 'National', '3' => 'Network Specific', '5' => 'Alphanumeric'],
             'default' => '5'],
            ['name' => 'source_addr_npi', 'type' => 'select', 'label' => 'Source NPI', 'required' => false,
             'options' => ['0' => 'Unknown', '1' => 'ISDN (E163/E164)', '9' => 'Private'],
             'default' => '0'],
            ['name' => 'dest_addr_ton', 'type' => 'select', 'label' => 'Destination TON', 'required' => false,
             'options' => ['0' => 'Unknown', '1' => 'International', '2' => 'National'],
             'default' => '1'],
            ['name' => 'dest_addr_npi', 'type' => 'select', 'label' => 'Destination NPI', 'required' => false,
             'options' => ['0' => 'Unknown', '1' => 'ISDN (E163/E164)'],
             'default' => '1'],
            ['name' => 'use_ssl', 'type' => 'checkbox', 'label' => 'Use SSL/TLS', 'required' => false, 'default' => '0'],
            ['name' => 'enquire_link_interval', 'type' => 'text', 'label' => 'Enquire Link Interval (seconds)', 'required' => false, 'default' => '30'],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $host = $this->config['host'];
        $port = (int)($this->config['port'] ?? 2775);
        $systemId = $this->config['system_id'];
        $password = $this->config['password'];
        $systemType = $this->config['system_type'] ?? '';
        $sourceAddr = $message->from ?: $this->config['source_addr'];
        $useSsl = !empty($this->config['use_ssl']);

        // TON/NPI settings
        $sourceTon = (int)($this->config['source_addr_ton'] ?? 5);
        $sourceNpi = (int)($this->config['source_addr_npi'] ?? 0);
        $destTon = (int)($this->config['dest_addr_ton'] ?? 1);
        $destNpi = (int)($this->config['dest_addr_npi'] ?? 1);

        try {
            // Connect to SMSC
            $transport = $useSsl
                ? "ssl://{$host}:{$port}"
                : "tcp://{$host}:{$port}";

            $socket = @stream_socket_client($transport, $errno, $errstr, 30);

            if (!$socket) {
                return new SendResult(false, null, "Connection failed: {$errstr}");
            }

            stream_set_timeout($socket, 30);

            // BIND_TRANSMITTER (command_id = 0x00000002)
            $bindPdu = $this->buildBindPdu($systemId, $password, $systemType);
            fwrite($socket, $bindPdu);

            // Read bind response
            $bindResp = $this->readPdu($socket);
            if (!$bindResp || $bindResp['command_status'] !== 0) {
                fclose($socket);
                $statusMsg = $this->getSmppError($bindResp['command_status'] ?? -1);
                return new SendResult(false, null, "Bind failed: {$statusMsg}");
            }

            // SUBMIT_SM (command_id = 0x00000004)
            $submitPdu = $this->buildSubmitPdu(
                $sourceAddr, $sourceTon, $sourceNpi,
                $message->to, $destTon, $destNpi,
                $message->message, $message->encoding
            );
            fwrite($socket, $submitPdu);

            // Read submit response
            $submitResp = $this->readPdu($socket);

            // UNBIND
            $unbindPdu = $this->buildUnbindPdu();
            fwrite($socket, $unbindPdu);
            fclose($socket);

            if (!$submitResp || $submitResp['command_status'] !== 0) {
                $statusMsg = $this->getSmppError($submitResp['command_status'] ?? -1);
                return new SendResult(false, null, "Submit failed: {$statusMsg}");
            }

            $messageId = $submitResp['message_id'] ?? ('smpp_' . time());
            return new SendResult(true, $messageId);

        } catch (\Exception $e) {
            return new SendResult(false, null, 'SMPP error: ' . $e->getMessage());
        }
    }

    private function buildBindPdu(string $systemId, string $password, string $systemType): string
    {
        $body = $systemId . "\x00" .        // system_id
                $password . "\x00" .         // password
                $systemType . "\x00" .       // system_type
                "\x34" .                     // interface_version (3.4)
                "\x00" .                     // addr_ton
                "\x00" .                     // addr_npi
                "\x00";                      // address_range

        $header = pack('N', strlen($body) + 16) .  // command_length
                  pack('N', 0x00000002) .           // command_id (bind_transmitter)
                  pack('N', 0x00000000) .           // command_status
                  pack('N', 0x00000001);            // sequence_number

        return $header . $body;
    }

    private function buildSubmitPdu(
        string $sourceAddr, int $sourceTon, int $sourceNpi,
        string $destAddr, int $destTon, int $destNpi,
        string $message, string $encoding
    ): string {
        // Determine data coding
        $dataCoding = 0; // GSM-7
        if ($encoding === 'ucs2') {
            $dataCoding = 8; // UCS2
            $message = mb_convert_encoding($message, 'UCS-2BE', 'UTF-8');
        }

        $body = "\x00" .                          // service_type
                chr($sourceTon) .                  // source_addr_ton
                chr($sourceNpi) .                  // source_addr_npi
                $sourceAddr . "\x00" .             // source_addr
                chr($destTon) .                    // dest_addr_ton
                chr($destNpi) .                    // dest_addr_npi
                $destAddr . "\x00" .               // destination_addr
                "\x00" .                          // esm_class
                "\x00" .                          // protocol_id
                "\x00" .                          // priority_flag
                "\x00" .                          // schedule_delivery_time
                "\x00" .                          // validity_period
                "\x01" .                          // registered_delivery (request DLR)
                "\x00" .                          // replace_if_present_flag
                chr($dataCoding) .                // data_coding
                "\x00" .                          // sm_default_msg_id
                chr(strlen($message)) .           // sm_length
                $message;                         // short_message

        static $seq = 1;
        $header = pack('N', strlen($body) + 16) .  // command_length
                  pack('N', 0x00000004) .           // command_id (submit_sm)
                  pack('N', 0x00000000) .           // command_status
                  pack('N', ++$seq);                // sequence_number

        return $header . $body;
    }

    private function buildUnbindPdu(): string
    {
        static $seq = 100;
        return pack('N', 16) .              // command_length
               pack('N', 0x00000006) .      // command_id (unbind)
               pack('N', 0x00000000) .      // command_status
               pack('N', ++$seq);           // sequence_number
    }

    private function readPdu($socket): ?array
    {
        $header = fread($socket, 16);
        if (!$header || strlen($header) < 16) {
            return null;
        }

        $parsed = unpack('Nlength/Ncommand_id/Ncommand_status/Nsequence', $header);
        $bodyLen = $parsed['length'] - 16;

        $body = '';
        if ($bodyLen > 0) {
            $body = fread($socket, $bodyLen);
        }

        $result = [
            'command_id' => $parsed['command_id'],
            'command_status' => $parsed['command_status'],
            'sequence' => $parsed['sequence'],
        ];

        // Extract message_id from submit_sm_resp
        if ($parsed['command_id'] === 0x80000004 && !empty($body)) {
            $nullPos = strpos($body, "\x00");
            $result['message_id'] = $nullPos !== false ? substr($body, 0, $nullPos) : $body;
        }

        return $result;
    }

    private function getSmppError(int $status): string
    {
        $errors = [
            0x00 => 'OK',
            0x01 => 'Invalid message length',
            0x02 => 'Invalid command length',
            0x03 => 'Invalid command ID',
            0x04 => 'Incorrect bind status',
            0x05 => 'Already bound',
            0x06 => 'Invalid priority flag',
            0x07 => 'Invalid registered delivery flag',
            0x08 => 'System error',
            0x0A => 'Invalid source address',
            0x0B => 'Invalid destination address',
            0x0C => 'Message ID invalid',
            0x0D => 'Bind failed',
            0x0E => 'Invalid password',
            0x0F => 'Invalid system ID',
            0x14 => 'Message queue full',
            0x45 => 'Invalid number of destinations',
            0x58 => 'Throttling error',
        ];

        return $errors[$status] ?? "Unknown error (0x" . dechex($status) . ")";
    }

    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        // SMPP DLR comes as text message with specific format
        $text = $payload['message'] ?? $payload['short_message'] ?? '';

        // Parse: id:MSGID sub:001 dlvrd:001 submit date:... done date:... stat:DELIVRD
        if (preg_match('/id:(\S+).*stat:(\S+)/', $text, $matches)) {
            $result = new DLRResult($matches[1], DLRResult::normalizeStatus($matches[2]));
            $result->rawPayload = $payload;
            return $result;
        }

        return null;
    }
}

/**
 * Textlocal India Gateway
 */
class TextlocalIndiaGateway extends AbstractGateway
{
    public function getType(): string { return 'textlocal_india'; }
    public function getName(): string { return 'Textlocal India'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
            ['name' => 'sender', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $sender = $message->from ?: $this->config['sender'];

        $response = $this->httpPost('https://api.textlocal.in/send/', [
            'apikey' => $apiKey,
            'numbers' => preg_replace('/[^0-9]/', '', $message->to),
            'message' => $message->message,
            'sender' => $sender,
        ]);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            if (($data['status'] ?? '') === 'success') {
                return new SendResult(true, $data['messages'][0]['id'] ?? null);
            }
            return new SendResult(false, null, $data['errors'][0]['message'] ?? 'Textlocal error');
        }

        return new SendResult(false, null, 'Textlocal API error');
    }
}

/**
 * Textlocal UK Gateway
 */
class TextlocalUkGateway extends AbstractGateway
{
    public function getType(): string { return 'textlocal_uk'; }
    public function getName(): string { return 'Textlocal UK'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
            ['name' => 'sender', 'type' => 'text', 'label' => 'Sender Name', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $sender = $message->from ?: $this->config['sender'];

        $response = $this->httpPost('https://api.txtlocal.com/send/', [
            'apikey' => $apiKey,
            'numbers' => preg_replace('/[^0-9]/', '', $message->to),
            'message' => $message->message,
            'sender' => $sender,
        ]);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            if (($data['status'] ?? '') === 'success') {
                return new SendResult(true, $data['messages'][0]['id'] ?? null);
            }
            return new SendResult(false, null, $data['errors'][0]['message'] ?? 'Textlocal error');
        }

        return new SendResult(false, null, 'Textlocal API error');
    }
}

/**
 * Kaleyra Gateway (India)
 */
class KaleyraGateway extends AbstractGateway
{
    public function getType(): string { return 'kaleyra'; }
    public function getName(): string { return 'Kaleyra'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
            ['name' => 'sid', 'type' => 'text', 'label' => 'SID', 'required' => true],
            ['name' => 'from', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $sid = $this->config['sid'];
        $from = $message->from ?: $this->config['from'];

        $url = "https://api.kaleyra.io/v1/{$sid}/messages";

        $response = $this->httpPost($url, [
            'to' => $message->to,
            'type' => 'OTP',
            'sender' => $from,
            'body' => $message->message,
        ], [
            'api-key' => $apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            if (!empty($data['id'])) {
                return new SendResult(true, $data['id']);
            }
            return new SendResult(false, null, $data['error']['message'] ?? 'Kaleyra error');
        }

        return new SendResult(false, null, 'Kaleyra API error');
    }
}

/**
 * Fast2SMS Gateway (India)
 */
class Fast2SmsGateway extends AbstractGateway
{
    public function getType(): string { return 'fast2sms'; }
    public function getName(): string { return 'Fast2SMS'; }
    public function getSupportedChannels(): array { return ['sms']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'type' => 'password', 'label' => 'API Key', 'required' => true],
            ['name' => 'sender_id', 'type' => 'text', 'label' => 'Sender ID', 'required' => true],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->config['api_key'];
        $senderId = $message->from ?: $this->config['sender_id'];

        $response = $this->httpPost('https://www.fast2sms.com/dev/bulkV2', [
            'route' => 'dlt',
            'sender_id' => $senderId,
            'message' => $message->message,
            'numbers' => preg_replace('/[^0-9]/', '', $message->to),
        ], [
            'authorization' => $apiKey,
            'Content-Type' => 'application/json',
        ], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $data = json_decode($response['body'], true);
            if (($data['return'] ?? false)) {
                return new SendResult(true, $data['request_id'] ?? null);
            }
            return new SendResult(false, null, $data['message'][0] ?? 'Fast2SMS error');
        }

        return new SendResult(false, null, 'Fast2SMS API error');
    }
}

/**
 * Meta WhatsApp Business Cloud API Gateway
 */
class MetaWhatsAppGateway extends AbstractGateway
{
    const API_VERSION = 'v24.0';

    public function getType(): string { return 'meta_whatsapp'; }
    public function getName(): string { return 'Meta WhatsApp Business'; }
    public function getSupportedChannels(): array { return ['whatsapp']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'phone_number_id', 'type' => 'text', 'label' => 'Phone Number ID', 'required' => true, 'placeholder' => 'From Meta Business Suite > WhatsApp > API Setup'],
            ['name' => 'access_token', 'type' => 'password', 'label' => 'Access Token', 'required' => true],
            ['name' => 'waba_id', 'type' => 'text', 'label' => 'WABA ID', 'required' => true, 'placeholder' => 'WhatsApp Business Account ID'],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $phoneNumberId = $this->config['phone_number_id'] ?? '';
        $accessToken = $this->config['access_token'] ?? '';

        if (empty($phoneNumberId) || empty($accessToken)) {
            return SendResult::failure('Meta WhatsApp credentials not configured');
        }

        $to = preg_replace('/[^0-9]/', '', $message->to);

        $url = 'https://graph.facebook.com/' . self::API_VERSION . '/' . $phoneNumberId . '/messages';

        // Check if this is a template message (from metadata)
        $templateName = $message->metadata['template_name'] ?? null;

        if ($templateName) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $message->metadata['language'] ?? 'en'],
                ],
            ];
            if (!empty($message->metadata['template_params'])) {
                $components = [];
                $params = [];
                foreach ($message->metadata['template_params'] as $val) {
                    $params[] = ['type' => 'text', 'text' => (string)$val];
                }
                if (!empty($params)) {
                    $components[] = ['type' => 'body', 'parameters' => $params];
                }
                $payload['template']['components'] = $components;
            }
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message->message,
                ],
            ];
        }

        $response = $this->httpPost($url, $payload, [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ], true);

        $data = json_decode($response['body'] ?? '', true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
            $waMessageId = $data['messages'][0]['id'] ?? null;
            if ($waMessageId) {
                return SendResult::success($waMessageId, $data);
            }
            return SendResult::failure('No message ID in response', null, $data ?? []);
        }

        $errorMsg = $data['error']['message'] ?? ($response['error'] ?? 'Meta WhatsApp API error');
        $errorCode = (string)($data['error']['code'] ?? '');
        return SendResult::failure($errorMsg, $errorCode, $data ?? []);
    }

    public function getBalance(): ?float
    {
        return null; // Meta WhatsApp uses conversation-based pricing via Meta billing
    }

    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        // Meta webhook: entry[].changes[].value.statuses[]
        $statuses = $payload['statuses'] ?? [];
        if (empty($statuses)) {
            return null;
        }

        $status = $statuses[0];
        $messageId = $status['id'] ?? '';
        $statusStr = $status['status'] ?? 'unknown';

        $statusMap = [
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'delivered',
            'failed' => 'failed',
        ];

        $dlr = new DLRResult($messageId, $statusMap[$statusStr] ?? 'unknown');
        $dlr->rawPayload = $payload;

        if ($statusStr === 'failed' && isset($status['errors'][0])) {
            $dlr->errorCode = (string)($status['errors'][0]['code'] ?? '');
            $dlr->errorMessage = $status['errors'][0]['title'] ?? '';
        }

        return $dlr;
    }

    public function parseInboundMessage(array $payload): ?InboundResult
    {
        $messages = $payload['messages'] ?? [];
        if (empty($messages)) {
            return null;
        }

        $msg = $messages[0];
        $from = $msg['from'] ?? '';
        $to = $payload['metadata']['display_phone_number'] ?? '';
        $text = $msg['text']['body'] ?? '';
        $msgId = $msg['id'] ?? '';

        $inbound = new InboundResult($from, $to, $text);
        $inbound->messageId = $msgId;
        $inbound->channel = 'whatsapp';
        $inbound->rawPayload = $payload;

        return $inbound;
    }

    public function verifyWebhook(array $headers, string $body, string $secret): bool
    {
        $signature = $headers['X-Hub-Signature-256'] ?? $headers['x-hub-signature-256'] ?? '';
        if (empty($signature) || empty($secret)) {
            return false; // Reject if signature or secret is missing
        }

        $signature = str_replace('sha256=', '', $signature);
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $signature);
    }
}
