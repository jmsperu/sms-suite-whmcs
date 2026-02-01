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

        // === Generic/Custom ===
        'generic_http' => ['name' => 'Generic HTTP', 'class' => 'GenericHttpGateway', 'category' => 'Custom'],
        'smpp' => ['name' => 'SMPP Gateway', 'class' => 'SmppGateway', 'category' => 'Custom'],
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
            'meta_whatsapp' => ['phone_number_id', 'access_token'],
            'gupshup_whatsapp' => ['api_key', 'app_id', 'source_number'],
            'interakt_whatsapp' => ['api_key'],
            'ultramsg_whatsapp' => ['instance_id', 'token'],
            'wati' => ['api_url', 'access_token'],
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
