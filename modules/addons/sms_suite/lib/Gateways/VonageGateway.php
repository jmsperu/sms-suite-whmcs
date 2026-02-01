<?php
/**
 * SMS Suite - Vonage (Nexmo) Gateway
 */

namespace SMSSuite\Gateways;

class VonageGateway extends AbstractGateway
{
    const API_BASE = 'https://rest.nexmo.com';

    public function getType(): string
    {
        return 'vonage';
    }

    public function getName(): string
    {
        return 'Vonage (Nexmo)';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'text'],
            ['name' => 'api_secret', 'label' => 'API Secret', 'type' => 'password'],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $apiKey = $this->getConfig('api_key');
        $apiSecret = $this->getConfig('api_secret');

        if (empty($apiKey) || empty($apiSecret)) {
            return SendResult::failure('Vonage credentials not configured');
        }

        $url = self::API_BASE . '/sms/json';

        $data = [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'from' => $message->from,
            'to' => $this->formatPhone($message->to),
            'text' => $message->message,
        ];

        // Set encoding type if Unicode
        if ($message->encoding === 'ucs2') {
            $data['type'] = 'unicode';
        }

        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

        list($success, $response, $httpCode, $error) = $this->httpRequest('POST', $url, $data, $headers);

        if (!empty($error)) {
            return SendResult::failure("HTTP Error: {$error}");
        }

        $responseData = $this->parseJsonResponse($response);

        // Vonage returns messages array
        $msg = $responseData['messages'][0] ?? [];
        $status = $msg['status'] ?? '99';

        if ($status !== '0') {
            $errorText = $msg['error-text'] ?? 'Unknown error';
            return SendResult::failure($errorText, $status, $responseData);
        }

        return SendResult::success($msg['message-id'] ?? null, $responseData);
    }

    public function getBalance(): ?float
    {
        $apiKey = $this->getConfig('api_key');
        $apiSecret = $this->getConfig('api_secret');

        $url = self::API_BASE . "/account/get-balance?api_key={$apiKey}&api_secret={$apiSecret}";

        list($success, $response) = $this->httpRequest('GET', $url);

        if (!$success) return null;

        $data = $this->parseJsonResponse($response);
        return isset($data['value']) ? (float)$data['value'] : null;
    }

    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        $messageId = $payload['messageId'] ?? $payload['message-id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$messageId || !$status) return null;

        $result = new DLRResult($messageId, DLRResult::normalizeStatus($status));
        $result->errorCode = $payload['err-code'] ?? null;
        $result->rawPayload = $payload;
        return $result;
    }

    public function parseInboundMessage(array $payload): ?InboundResult
    {
        $from = $payload['msisdn'] ?? null;
        $to = $payload['to'] ?? null;
        $text = $payload['text'] ?? null;

        if (!$from || !$text) return null;

        $result = new InboundResult($from, $to, $text);
        $result->messageId = $payload['messageId'] ?? null;
        $result->rawPayload = $payload;
        return $result;
    }
}
