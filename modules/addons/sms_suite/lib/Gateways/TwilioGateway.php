<?php
/**
 * SMS Suite - Twilio Gateway
 *
 * Twilio SMS and WhatsApp integration
 */

namespace SMSSuite\Gateways;

class TwilioGateway extends AbstractGateway
{
    const API_BASE = 'https://api.twilio.com/2010-04-01';

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'twilio';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Twilio';
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'mms'];
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredFields(): array
    {
        return [
            [
                'name' => 'account_sid',
                'label' => 'Account SID',
                'type' => 'text',
                'description' => 'Your Twilio Account SID',
            ],
            [
                'name' => 'auth_token',
                'label' => 'Auth Token',
                'type' => 'password',
                'description' => 'Your Twilio Auth Token',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionalFields(): array
    {
        return [
            [
                'name' => 'messaging_service_sid',
                'label' => 'Messaging Service SID',
                'type' => 'text',
                'description' => 'Optional: Use a Messaging Service instead of From number',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageDTO $message): SendResult
    {
        $accountSid = $this->getConfig('account_sid');
        $authToken = $this->getConfig('auth_token');

        if (empty($accountSid) || empty($authToken)) {
            return SendResult::failure('Twilio credentials not configured');
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Messages.json";

        // Format phone numbers
        $to = $this->formatPhone($message->to, true);
        $from = $message->from;

        // For WhatsApp, add prefix
        if ($message->channel === 'whatsapp') {
            $to = 'whatsapp:' . $to;
            $from = 'whatsapp:' . $from;
        }

        // Build request data
        $data = [
            'To' => $to,
            'Body' => $message->message,
        ];

        // Use Messaging Service or From number
        $messagingServiceSid = $this->getConfig('messaging_service_sid');
        if (!empty($messagingServiceSid)) {
            $data['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $data['From'] = $from;
        }

        // Add media URL if present
        if (!empty($message->mediaUrl)) {
            $data['MediaUrl'] = $message->mediaUrl;
        }

        // Make request
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
        ];

        list($success, $response, $httpCode, $error) = $this->httpRequest('POST', $url, $data, $headers);

        if (!empty($error)) {
            return SendResult::failure("HTTP Error: {$error}");
        }

        $responseData = $this->parseJsonResponse($response);

        // Check for error
        if (isset($responseData['code']) || isset($responseData['error_code'])) {
            $errorMsg = $responseData['message'] ?? $responseData['error_message'] ?? 'Unknown error';
            $errorCode = $responseData['code'] ?? $responseData['error_code'] ?? null;
            return SendResult::failure($errorMsg, $errorCode, $responseData);
        }

        if ($httpCode >= 400) {
            return SendResult::failure($responseData['message'] ?? 'Request failed', (string)$httpCode, $responseData);
        }

        // Success
        $messageId = $responseData['sid'] ?? null;
        return SendResult::success($messageId, $responseData);
    }

    /**
     * {@inheritdoc}
     */
    public function getBalance(): ?float
    {
        $accountSid = $this->getConfig('account_sid');
        $authToken = $this->getConfig('auth_token');

        if (empty($accountSid) || empty($authToken)) {
            return null;
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Balance.json";

        $headers = [
            'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
        ];

        list($success, $response, $httpCode, $error) = $this->httpRequest('GET', $url, [], $headers);

        if (!$success) {
            return null;
        }

        $data = $this->parseJsonResponse($response);
        return isset($data['balance']) ? (float)$data['balance'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        $messageId = $payload['MessageSid'] ?? $payload['SmsSid'] ?? null;
        $status = $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? null;

        if (!$messageId || !$status) {
            return null;
        }

        $result = new DLRResult($messageId, DLRResult::normalizeStatus($status));
        $result->errorCode = $payload['ErrorCode'] ?? null;
        $result->errorMessage = $payload['ErrorMessage'] ?? null;
        $result->rawPayload = $payload;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function parseInboundMessage(array $payload): ?InboundResult
    {
        $from = $payload['From'] ?? null;
        $to = $payload['To'] ?? null;
        $body = $payload['Body'] ?? null;

        if (!$from || !$body) {
            return null;
        }

        // Remove whatsapp: prefix
        $from = str_replace('whatsapp:', '', $from);
        $to = str_replace('whatsapp:', '', $to);

        $result = new InboundResult($from, $to, $body);
        $result->messageId = $payload['MessageSid'] ?? $payload['SmsSid'] ?? null;
        $result->mediaUrl = $payload['MediaUrl0'] ?? null;
        $result->channel = (strpos($payload['From'] ?? '', 'whatsapp:') === 0) ? 'whatsapp' : 'sms';
        $result->rawPayload = $payload;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhook(array $headers, string $body, string $secret): bool
    {
        // Twilio uses signature validation
        $signature = $headers['X-Twilio-Signature'] ?? $headers['x-twilio-signature'] ?? '';

        if (empty($signature) || empty($secret)) {
            return false;
        }

        // For full validation, need request URL + params
        // Simplified check for token-based auth
        return parent::verifyWebhook($headers, $body, $secret);
    }
}
