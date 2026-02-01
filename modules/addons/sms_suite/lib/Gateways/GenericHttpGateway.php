<?php
/**
 * SMS Suite - Generic HTTP Gateway
 *
 * A fully configurable HTTP gateway for any SMS provider
 */

namespace SMSSuite\Gateways;

class GenericHttpGateway extends AbstractGateway
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'generic_http';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Generic HTTP Gateway';
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
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
                'description' => 'Full URL to the SMS API endpoint',
                'placeholder' => 'https://api.provider.com/sms/send',
            ],
            [
                'name' => 'http_method',
                'label' => 'HTTP Method',
                'type' => 'select',
                'options' => ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT'],
                'default' => 'POST',
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
                'name' => 'auth_type',
                'label' => 'Authentication Type',
                'type' => 'select',
                'options' => [
                    'none' => 'None',
                    'basic' => 'Basic Auth',
                    'bearer' => 'Bearer Token',
                    'api_key_header' => 'API Key (Header)',
                    'api_key_query' => 'API Key (Query Param)',
                ],
                'default' => 'none',
            ],
            [
                'name' => 'auth_username',
                'label' => 'Username / API Key',
                'type' => 'text',
                'description' => 'For Basic Auth or API Key authentication',
            ],
            [
                'name' => 'auth_password',
                'label' => 'Password / Secret',
                'type' => 'password',
                'description' => 'For Basic Auth',
            ],
            [
                'name' => 'auth_header_name',
                'label' => 'API Key Header Name',
                'type' => 'text',
                'default' => 'X-API-Key',
                'description' => 'Header name for API key authentication',
            ],
            [
                'name' => 'auth_query_param',
                'label' => 'API Key Query Parameter',
                'type' => 'text',
                'default' => 'api_key',
                'description' => 'Query parameter name for API key',
            ],
            [
                'name' => 'content_type',
                'label' => 'Content Type',
                'type' => 'select',
                'options' => [
                    'application/x-www-form-urlencoded' => 'Form Encoded',
                    'application/json' => 'JSON',
                ],
                'default' => 'application/json',
            ],
            [
                'name' => 'custom_headers',
                'label' => 'Custom Headers',
                'type' => 'textarea',
                'description' => 'One header per line: Header-Name: Value',
            ],
            [
                'name' => 'param_to',
                'label' => 'Recipient Parameter Name',
                'type' => 'text',
                'default' => 'to',
            ],
            [
                'name' => 'param_from',
                'label' => 'Sender Parameter Name',
                'type' => 'text',
                'default' => 'from',
            ],
            [
                'name' => 'param_message',
                'label' => 'Message Parameter Name',
                'type' => 'text',
                'default' => 'message',
            ],
            [
                'name' => 'extra_params',
                'label' => 'Extra Parameters',
                'type' => 'textarea',
                'description' => 'One parameter per line: param_name=value',
            ],
            [
                'name' => 'body_template',
                'label' => 'Custom Body Template (JSON)',
                'type' => 'textarea',
                'description' => 'JSON template with {to}, {from}, {message} placeholders',
            ],
            [
                'name' => 'response_message_id_path',
                'label' => 'Message ID Path in Response',
                'type' => 'text',
                'default' => 'message_id',
                'description' => 'JSON path to message ID (e.g., data.id or messages.0.id)',
            ],
            [
                'name' => 'success_codes',
                'label' => 'Success HTTP Codes',
                'type' => 'text',
                'default' => '200,201,202',
                'description' => 'Comma-separated HTTP status codes indicating success',
            ],
            [
                'name' => 'success_keyword',
                'label' => 'Success Keyword',
                'type' => 'text',
                'description' => 'Text that must appear in response for success (optional)',
            ],
            [
                'name' => 'phone_format',
                'label' => 'Phone Number Format',
                'type' => 'select',
                'options' => [
                    'as_is' => 'As Is',
                    'plus_prefix' => 'With + Prefix',
                    'no_plus' => 'Without + Prefix',
                    'digits_only' => 'Digits Only',
                ],
                'default' => 'as_is',
            ],
            [
                'name' => 'balance_endpoint',
                'label' => 'Balance Check Endpoint',
                'type' => 'text',
                'description' => 'URL to check account balance (optional)',
            ],
            [
                'name' => 'balance_path',
                'label' => 'Balance Path in Response',
                'type' => 'text',
                'default' => 'balance',
                'description' => 'JSON path to balance value',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageDTO $message): SendResult
    {
        $endpoint = $this->getConfig('api_endpoint');
        $method = $this->getConfig('http_method', 'POST');

        if (empty($endpoint)) {
            return SendResult::failure('API endpoint not configured');
        }

        // Format phone number
        $to = $this->formatPhoneByConfig($message->to);

        // Build request data
        $data = $this->buildRequestData($message, $to);

        // Build headers
        $headers = $this->buildHeaders();

        // Add auth to URL if needed
        $endpoint = $this->addUrlAuth($endpoint);

        // Make request
        list($success, $response, $httpCode, $error) = $this->httpRequest($method, $endpoint, $data, $headers);

        // Check HTTP error
        if (!empty($error)) {
            return SendResult::failure("HTTP Error: {$error}");
        }

        // Parse response
        $responseData = $this->parseJsonResponse($response);

        // Check success
        if (!$this->isSuccessResponse($httpCode, $response)) {
            $errorMsg = $this->extractError($responseData, $response);
            return SendResult::failure($errorMsg, (string)$httpCode, $responseData);
        }

        // Extract message ID
        $messageId = $this->extractMessageId($responseData);

        return SendResult::success($messageId ?: 'sent_' . time(), $responseData);
    }

    /**
     * Build request data array
     */
    protected function buildRequestData(MessageDTO $message, string $formattedTo): array
    {
        // Check for custom body template
        $bodyTemplate = $this->getConfig('body_template');
        if (!empty($bodyTemplate)) {
            return $this->parseBodyTemplate($bodyTemplate, $message, $formattedTo);
        }

        // Build from parameters
        $paramTo = $this->getConfig('param_to', 'to');
        $paramFrom = $this->getConfig('param_from', 'from');
        $paramMessage = $this->getConfig('param_message', 'message');

        $data = [
            $paramTo => $formattedTo,
            $paramFrom => $message->from,
            $paramMessage => $message->message,
        ];

        // Add extra parameters
        $extraParams = $this->getConfig('extra_params', '');
        if (!empty($extraParams)) {
            $lines = explode("\n", $extraParams);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $data[trim($key)] = trim($value);
                }
            }
        }

        return $data;
    }

    /**
     * Parse custom body template
     */
    protected function parseBodyTemplate(string $template, MessageDTO $message, string $formattedTo): array
    {
        // Replace placeholders
        $replacements = [
            '{to}' => $formattedTo,
            '{from}' => $message->from,
            '{message}' => $message->message,
            '{media_url}' => $message->mediaUrl ?? '',
        ];

        $body = str_replace(array_keys($replacements), array_values($replacements), $template);

        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Build request headers
     */
    protected function buildHeaders(): array
    {
        $headers = [];

        // Content type
        $contentType = $this->getConfig('content_type', 'application/json');
        $headers['Content-Type'] = $contentType;

        // Authentication headers
        $authType = $this->getConfig('auth_type', 'none');

        switch ($authType) {
            case 'basic':
                $username = $this->getConfig('auth_username', '');
                $password = $this->getConfig('auth_password', '');
                $headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
                break;

            case 'bearer':
                $token = $this->getConfig('auth_username', '');
                $headers['Authorization'] = 'Bearer ' . $token;
                break;

            case 'api_key_header':
                $headerName = $this->getConfig('auth_header_name', 'X-API-Key');
                $apiKey = $this->getConfig('auth_username', '');
                $headers[$headerName] = $apiKey;
                break;
        }

        // Custom headers
        $customHeaders = $this->getConfig('custom_headers', '');
        if (!empty($customHeaders)) {
            $lines = explode("\n", $customHeaders);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, ':') !== false) {
                    list($name, $value) = explode(':', $line, 2);
                    $headers[trim($name)] = trim($value);
                }
            }
        }

        return $headers;
    }

    /**
     * Add authentication to URL if using query param auth
     */
    protected function addUrlAuth(string $url): string
    {
        $authType = $this->getConfig('auth_type', 'none');

        if ($authType === 'api_key_query') {
            $paramName = $this->getConfig('auth_query_param', 'api_key');
            $apiKey = $this->getConfig('auth_username', '');

            $separator = (strpos($url, '?') === false) ? '?' : '&';
            $url .= $separator . urlencode($paramName) . '=' . urlencode($apiKey);
        }

        return $url;
    }

    /**
     * Format phone number based on configuration
     */
    protected function formatPhoneByConfig(string $phone): string
    {
        $format = $this->getConfig('phone_format', 'as_is');

        switch ($format) {
            case 'plus_prefix':
                $phone = preg_replace('/[^0-9]/', '', $phone);
                return '+' . $phone;

            case 'no_plus':
                return ltrim(preg_replace('/[^0-9+]/', '', $phone), '+');

            case 'digits_only':
                return preg_replace('/[^0-9]/', '', $phone);

            default:
                return $phone;
        }
    }

    /**
     * Check if response indicates success
     */
    protected function isSuccessResponse(int $httpCode, string $response): bool
    {
        // Check HTTP codes
        $successCodes = $this->getConfig('success_codes', '200,201,202');
        $codes = array_map('intval', explode(',', $successCodes));

        if (!in_array($httpCode, $codes)) {
            return false;
        }

        // Check success keyword if configured
        $keyword = $this->getConfig('success_keyword', '');
        if (!empty($keyword)) {
            return stripos($response, $keyword) !== false;
        }

        return true;
    }

    /**
     * Extract message ID from response
     */
    protected function extractMessageId(array $response): ?string
    {
        $path = $this->getConfig('response_message_id_path', 'message_id');

        return $this->getNestedValue($response, $path);
    }

    /**
     * Extract error message from response
     */
    protected function extractError(array $response, string $rawResponse): string
    {
        // Try common error paths
        $errorPaths = ['error', 'error.message', 'errors.0', 'message', 'errorMessage', 'error_message'];

        foreach ($errorPaths as $path) {
            $error = $this->getNestedValue($response, $path);
            if (!empty($error)) {
                return is_string($error) ? $error : json_encode($error);
            }
        }

        // Return truncated raw response
        return substr($rawResponse, 0, 200);
    }

    /**
     * Get nested value from array using dot notation
     */
    protected function getNestedValue(array $array, string $path)
    {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
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

        $headers = $this->buildHeaders();
        $endpoint = $this->addUrlAuth($endpoint);

        list($success, $response, $httpCode, $error) = $this->httpRequest('GET', $endpoint, [], $headers);

        if (!$success || !empty($error)) {
            return null;
        }

        $data = $this->parseJsonResponse($response);
        $path = $this->getConfig('balance_path', 'balance');
        $balance = $this->getNestedValue($data, $path);

        return is_numeric($balance) ? (float)$balance : null;
    }

    /**
     * {@inheritdoc}
     */
    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        // Generic parsing - try common field names
        $messageId = $payload['message_id'] ?? $payload['messageId'] ?? $payload['id'] ?? $payload['smsId'] ?? null;
        $status = $payload['status'] ?? $payload['delivery_status'] ?? $payload['deliveryStatus'] ?? null;

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
        // Generic parsing - try common field names
        $from = $payload['from'] ?? $payload['sender'] ?? $payload['msisdn'] ?? null;
        $to = $payload['to'] ?? $payload['recipient'] ?? $payload['destination'] ?? null;
        $message = $payload['message'] ?? $payload['text'] ?? $payload['body'] ?? $payload['content'] ?? null;

        if ($from && $message) {
            $result = new InboundResult($from, $to ?? '', $message);
            $result->messageId = $payload['message_id'] ?? $payload['messageId'] ?? null;
            $result->rawPayload = $payload;
            return $result;
        }

        return null;
    }
}
