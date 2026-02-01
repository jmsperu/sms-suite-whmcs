<?php
/**
 * SMS Suite - Infobip Gateway
 */

namespace SMSSuite\Gateways;

class InfobipGateway extends AbstractGateway
{
    public function getType(): string
    {
        return 'infobip';
    }

    public function getName(): string
    {
        return 'Infobip';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'base_url', 'label' => 'Base URL', 'type' => 'text', 'default' => 'https://api.infobip.com', 'description' => 'Your Infobip API base URL'],
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password'],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $baseUrl = rtrim($this->getConfig('base_url', 'https://api.infobip.com'), '/');
        $apiKey = $this->getConfig('api_key');

        if (empty($apiKey)) {
            return SendResult::failure('Infobip API key not configured');
        }

        $url = $baseUrl . '/sms/2/text/advanced';

        $data = [
            'messages' => [
                [
                    'from' => $message->from,
                    'destinations' => [
                        ['to' => $this->formatPhone($message->to)]
                    ],
                    'text' => $message->message,
                ]
            ]
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'App ' . $apiKey,
        ];

        list($success, $response, $httpCode, $error) = $this->httpRequest('POST', $url, $data, $headers);

        if (!empty($error)) {
            return SendResult::failure("HTTP Error: {$error}");
        }

        $responseData = $this->parseJsonResponse($response);

        if ($httpCode >= 400) {
            $errorMsg = $responseData['requestError']['serviceException']['text'] ?? 'Request failed';
            return SendResult::failure($errorMsg, (string)$httpCode, $responseData);
        }

        $msg = $responseData['messages'][0] ?? [];
        $messageId = $msg['messageId'] ?? null;
        $status = $msg['status']['groupName'] ?? '';

        if (in_array($status, ['REJECTED', 'UNDELIVERABLE'])) {
            return SendResult::failure($msg['status']['description'] ?? 'Message rejected', $status, $responseData);
        }

        return SendResult::success($messageId, $responseData);
    }

    public function getBalance(): ?float
    {
        $baseUrl = rtrim($this->getConfig('base_url', 'https://api.infobip.com'), '/');
        $apiKey = $this->getConfig('api_key');

        $url = $baseUrl . '/account/1/balance';
        $headers = ['Authorization' => 'App ' . $apiKey];

        list($success, $response) = $this->httpRequest('GET', $url, [], $headers);

        if (!$success) return null;

        $data = $this->parseJsonResponse($response);
        return isset($data['balance']) ? (float)$data['balance'] : null;
    }

    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        $results = $payload['results'] ?? [];
        if (empty($results)) return null;

        $msg = $results[0];
        $messageId = $msg['messageId'] ?? null;
        $status = $msg['status']['groupName'] ?? null;

        if (!$messageId || !$status) return null;

        $result = new DLRResult($messageId, DLRResult::normalizeStatus($status));
        $result->errorCode = $msg['status']['id'] ?? null;
        $result->errorMessage = $msg['status']['description'] ?? null;
        $result->rawPayload = $payload;
        return $result;
    }

    public function parseInboundMessage(array $payload): ?InboundResult
    {
        $results = $payload['results'] ?? [];
        if (empty($results)) return null;

        $msg = $results[0];
        $from = $msg['from'] ?? null;
        $to = $msg['to'] ?? null;
        $text = $msg['text'] ?? $msg['message']['text'] ?? null;

        if (!$from || !$text) return null;

        $result = new InboundResult($from, $to, $text);
        $result->messageId = $msg['messageId'] ?? null;
        $result->rawPayload = $payload;
        return $result;
    }
}
