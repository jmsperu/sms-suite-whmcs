<?php
/**
 * SMS Suite - Airtouch Kenya Gateway
 *
 * Pre-configured gateway for Airtouch SMS API
 * API Format: https://client.airtouch.co.ke:9012/sms/api/?issn=SENDER&msisdn=PHONE&text=MESSAGE&username=USER&password=PASS
 */

namespace SMSSuite\Gateways;

class AirtouchGateway extends AbstractGateway
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'airtouch';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Airtouch Kenya';
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredFields(): array
    {
        return [
            [
                'name' => 'api_endpoint',
                'label' => 'API Endpoint URL',
                'type' => 'text',
                'required' => true,
                'default' => 'https://client.airtouch.co.ke:9012/sms/api/',
                'description' => 'Airtouch API endpoint (default provided)',
            ],
            [
                'name' => 'username',
                'label' => 'Username',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Your Airtouch username',
            ],
            [
                'name' => 'password',
                'label' => 'Password / API Key',
                'type' => 'password',
                'required' => true,
                'description' => 'Your Airtouch password or API key hash',
            ],
            [
                'name' => 'sender_id',
                'label' => 'Default Sender ID (ISSN)',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'e.g., XCOBEAN',
                'description' => 'Your registered sender ID',
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
                'name' => 'ignore_ssl',
                'label' => 'Ignore SSL Certificate Errors',
                'type' => 'select',
                'options' => ['no' => 'No', 'yes' => 'Yes'],
                'default' => 'no',
                'description' => 'Enable if you get SSL certificate errors',
            ],
            [
                'name' => 'success_keyword',
                'label' => 'Success Keyword',
                'type' => 'text',
                'default' => '',
                'description' => 'Text that indicates success in API response (leave blank to use HTTP code)',
            ],
            [
                'name' => 'balance_endpoint',
                'label' => 'Balance Check URL',
                'type' => 'text',
                'description' => 'URL to check account balance (optional)',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageDTO $message): SendResult
    {
        $endpoint = $this->getConfig('api_endpoint', 'https://client.airtouch.co.ke:9012/sms/api/');
        $username = $this->getConfig('username');
        $password = $this->getConfig('password');
        $senderId = !empty($message->from) ? $message->from : $this->getConfig('sender_id');

        if (empty($username) || empty($password)) {
            return SendResult::failure('Username and password are required');
        }

        // Build query parameters (Airtouch uses GET with URL params)
        $params = [
            'username' => $username,
            'password' => $password,
            'issn' => $senderId,
            'msisdn' => $this->formatKenyaPhone($message->to),
            'text' => $message->message,
        ];

        // Build full URL
        $url = $endpoint;
        if (strpos($url, '?') === false) {
            $url .= '?';
        } else {
            $url .= '&';
        }
        $url .= http_build_query($params);

        // Make GET request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // SSL verification
        if ($this->getConfig('ignore_ssl', 'no') === 'yes') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Check for cURL errors
        if (!empty($error)) {
            return SendResult::failure("Connection error: {$error}");
        }

        // Log the response for debugging
        $responseData = [
            'http_code' => $httpCode,
            'response' => $response,
        ];

        // Check success
        if (!$this->isSuccessResponse($httpCode, $response)) {
            $errorMsg = $this->extractError($response, $httpCode);
            return SendResult::failure($errorMsg, (string)$httpCode, $responseData);
        }

        // Try to extract message ID from response
        $messageId = $this->extractMessageId($response);

        return SendResult::success($messageId ?: 'airtouch_' . time() . '_' . rand(1000, 9999), $responseData);
    }

    /**
     * Format phone number for Airtouch (Kenya format)
     */
    protected function formatKenyaPhone(string $phone): string
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove + prefix if present
        $phone = ltrim($phone, '+');

        // If starts with 0, replace with 254 (Kenya code)
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }

        // If doesn't start with 254, add it
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }

        return $phone;
    }

    /**
     * Check if response indicates success
     */
    protected function isSuccessResponse(int $httpCode, string $response): bool
    {
        // Check HTTP code first
        if ($httpCode < 200 || $httpCode >= 300) {
            return false;
        }

        // Check for success keyword if configured
        $keyword = $this->getConfig('success_keyword', '');
        if (!empty($keyword)) {
            return stripos($response, $keyword) !== false;
        }

        // Check for common error patterns
        $errorPatterns = ['error', 'failed', 'invalid', 'denied', 'insufficient'];
        $responseLower = strtolower($response);
        foreach ($errorPatterns as $pattern) {
            if (strpos($responseLower, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract message ID from response
     */
    protected function extractMessageId(string $response): ?string
    {
        // Try JSON response
        $json = json_decode($response, true);
        if (is_array($json)) {
            // Common message ID field names
            $idFields = ['message_id', 'messageid', 'msgid', 'id', 'smsid', 'reference'];
            foreach ($idFields as $field) {
                if (!empty($json[$field])) {
                    return (string)$json[$field];
                }
            }
        }

        // Try to find ID in text response (e.g., "OK:12345" or "ID=12345")
        if (preg_match('/(?:id|msgid|message_id)[=:]\s*([a-zA-Z0-9-]+)/i', $response, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract error message from response
     */
    protected function extractError(string $response, int $httpCode): string
    {
        // Try JSON error
        $json = json_decode($response, true);
        if (is_array($json)) {
            $errorFields = ['error', 'message', 'error_message', 'description', 'msg'];
            foreach ($errorFields as $field) {
                if (!empty($json[$field])) {
                    return is_string($json[$field]) ? $json[$field] : json_encode($json[$field]);
                }
            }
        }

        // Return truncated raw response
        if (!empty($response)) {
            return substr($response, 0, 200);
        }

        return "HTTP Error: {$httpCode}";
    }

    /**
     * {@inheritdoc}
     */
    public function getBalance(): ?float
    {
        $endpoint = $this->getConfig('balance_endpoint');
        if (empty($endpoint)) {
            return null;
        }

        $username = $this->getConfig('username');
        $password = $this->getConfig('password');

        // Build balance check URL
        $url = $endpoint;
        if (strpos($url, '?') === false) {
            $url .= '?';
        } else {
            $url .= '&';
        }
        $url .= http_build_query([
            'username' => $username,
            'password' => $password,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if ($this->getConfig('ignore_ssl', 'no') === 'yes') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        // Try to parse balance from response
        $json = json_decode($response, true);
        if (is_array($json)) {
            $balanceFields = ['balance', 'credits', 'units', 'sms_balance'];
            foreach ($balanceFields as $field) {
                if (isset($json[$field]) && is_numeric($json[$field])) {
                    return (float)$json[$field];
                }
            }
        }

        // Try to find numeric balance in text
        if (preg_match('/(?:balance|credits)[=:]\s*([\d.]+)/i', $response, $matches)) {
            return (float)$matches[1];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        // Common DLR field names
        $messageId = $payload['message_id'] ?? $payload['msgid'] ?? $payload['id'] ?? null;
        $status = $payload['status'] ?? $payload['dlr_status'] ?? $payload['delivery_status'] ?? null;

        if ($messageId && $status) {
            $result = new DLRResult($messageId, DLRResult::normalizeStatus($status));
            $result->rawPayload = $payload;
            return $result;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function parseInboundMessage(array $payload): ?InboundResult
    {
        $from = $payload['from'] ?? $payload['sender'] ?? $payload['msisdn'] ?? null;
        $to = $payload['to'] ?? $payload['shortcode'] ?? $payload['issn'] ?? null;
        $message = $payload['message'] ?? $payload['text'] ?? $payload['msg'] ?? null;

        if ($from && $message) {
            $result = new InboundResult($from, $to ?? '', $message);
            $result->messageId = $payload['message_id'] ?? $payload['msgid'] ?? null;
            $result->rawPayload = $payload;
            return $result;
        }

        return null;
    }
}
