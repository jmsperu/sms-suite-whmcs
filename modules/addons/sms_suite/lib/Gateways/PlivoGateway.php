<?php
/**
 * SMS Suite - Plivo Gateway
 */

namespace SMSSuite\Gateways;

class PlivoGateway extends AbstractGateway
{
    const API_BASE = 'https://api.plivo.com/v1';

    public function getType(): string
    {
        return 'plivo';
    }

    public function getName(): string
    {
        return 'Plivo';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'mms'];
    }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'auth_id', 'label' => 'Auth ID', 'type' => 'text'],
            ['name' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password'],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $authId = $this->getConfig('auth_id');
        $authToken = $this->getConfig('auth_token');

        if (empty($authId) || empty($authToken)) {
            return SendResult::failure('Plivo credentials not configured');
        }

        $url = self::API_BASE . "/Account/{$authId}/Message/";

        $data = [
            'src' => $message->from,
            'dst' => $this->formatPhone($message->to),
            'text' => $message->message,
        ];

        if (!empty($message->mediaUrl)) {
            $data['type'] = 'mms';
            $data['media_urls'] = [$message->mediaUrl];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode("{$authId}:{$authToken}"),
        ];

        list($success, $response, $httpCode, $error) = $this->httpRequest('POST', $url, $data, $headers);

        if (!empty($error)) {
            return SendResult::failure("HTTP Error: {$error}");
        }

        $responseData = $this->parseJsonResponse($response);

        if ($httpCode >= 400 || isset($responseData['error'])) {
            return SendResult::failure($responseData['error'] ?? 'Request failed', (string)$httpCode, $responseData);
        }

        $messageId = $responseData['message_uuid'][0] ?? null;
        return SendResult::success($messageId, $responseData);
    }

    public function getBalance(): ?float
    {
        $authId = $this->getConfig('auth_id');
        $authToken = $this->getConfig('auth_token');

        $url = self::API_BASE . "/Account/{$authId}/";
        $headers = ['Authorization' => 'Basic ' . base64_encode("{$authId}:{$authToken}")];

        list($success, $response) = $this->httpRequest('GET', $url, [], $headers);

        if (!$success) return null;

        $data = $this->parseJsonResponse($response);
        return isset($data['cash_credits']) ? (float)$data['cash_credits'] : null;
    }

    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        $messageId = $payload['MessageUUID'] ?? null;
        $status = $payload['Status'] ?? null;

        if (!$messageId || !$status) return null;

        $result = new DLRResult($messageId, DLRResult::normalizeStatus($status));
        $result->errorCode = $payload['ErrorCode'] ?? null;
        $result->rawPayload = $payload;
        return $result;
    }

    public function parseInboundMessage(array $payload): ?InboundResult
    {
        $from = $payload['From'] ?? null;
        $to = $payload['To'] ?? null;
        $text = $payload['Text'] ?? null;

        if (!$from || !$text) return null;

        $result = new InboundResult($from, $to, $text);
        $result->messageId = $payload['MessageUUID'] ?? null;
        $result->rawPayload = $payload;
        return $result;
    }
}
