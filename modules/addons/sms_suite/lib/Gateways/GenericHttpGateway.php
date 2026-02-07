<?php
/**
 * SMS Suite - Generic/Custom HTTP Gateway
 *
 * A fully configurable HTTP gateway for any SMS provider
 * Supports custom parameter mapping, authentication, rate limiting, and more
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
        return 'Custom HTTP Gateway';
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
            // Basic Configuration
            [
                'name' => 'api_endpoint',
                'label' => 'Base URL / API Endpoint',
                'type' => 'text',
                'required' => true,
                'description' => 'Full URL to the SMS API endpoint',
                'placeholder' => 'https://api.provider.com/sms/send',
            ],
            [
                'name' => 'http_method',
                'label' => 'HTTP Request Method',
                'type' => 'select',
                'options' => ['POST' => 'POST', 'GET' => 'GET', 'PUT' => 'PUT'],
                'default' => 'POST',
            ],
            [
                'name' => 'success_keyword',
                'label' => 'Success Keyword',
                'type' => 'text',
                'description' => 'Text/code that appears in response to indicate success (e.g., 200, success, OK)',
                'placeholder' => '200',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionalFields(): array
    {
        return [
            // === Request Configuration ===
            [
                'name' => '_section_request',
                'label' => 'Request Configuration',
                'type' => 'section',
            ],
            [
                'name' => 'json_encoded',
                'label' => 'Enable JSON Encoded POST',
                'type' => 'select',
                'options' => ['no' => 'No (Form Data)', 'yes' => 'Yes (JSON Body)'],
                'default' => 'no',
            ],
            [
                'name' => 'content_type',
                'label' => 'Content Type',
                'type' => 'select',
                'options' => [
                    'application/x-www-form-urlencoded' => 'application/x-www-form-urlencoded',
                    'application/json' => 'application/json',
                    'multipart/form-data' => 'multipart/form-data',
                    'text/plain' => 'text/plain',
                ],
                'default' => 'application/x-www-form-urlencoded',
            ],
            [
                'name' => 'accept_header',
                'label' => 'Content Type Accept',
                'type' => 'select',
                'options' => [
                    'application/json' => 'application/json',
                    'text/plain' => 'text/plain',
                    'text/xml' => 'text/xml',
                    '*/*' => '*/* (Any)',
                ],
                'default' => 'application/json',
            ],
            [
                'name' => 'character_encoding',
                'label' => 'Character Encoding',
                'type' => 'select',
                'options' => [
                    'none' => 'None',
                    'utf-8' => 'UTF-8',
                    'iso-8859-1' => 'ISO-8859-1',
                ],
                'default' => 'none',
            ],
            [
                'name' => 'ignore_ssl',
                'label' => 'Ignore SSL Certificate Verification',
                'type' => 'select',
                'options' => ['no' => 'No', 'yes' => 'Yes'],
                'default' => 'no',
                'description' => 'Enable only for testing or if provider has self-signed cert',
            ],

            // === Authentication ===
            [
                'name' => '_section_auth',
                'label' => 'Authentication',
                'type' => 'section',
            ],
            [
                'name' => 'auth_type',
                'label' => 'Authorization Type',
                'type' => 'select',
                'options' => [
                    'params' => 'Authentication via Parameters',
                    'basic' => 'Basic Auth (Header)',
                    'bearer' => 'Bearer Token (Header)',
                    'api_key_header' => 'API Key (Custom Header)',
                    'none' => 'None',
                ],
                'default' => 'params',
            ],
            [
                'name' => 'auth_header_name',
                'label' => 'API Key Header Name',
                'type' => 'text',
                'default' => 'Authorization',
                'description' => 'For API Key header auth',
            ],

            // === Rate Limiting ===
            [
                'name' => '_section_rate',
                'label' => 'Rate Limiting',
                'type' => 'section',
            ],
            [
                'name' => 'rate_limit',
                'label' => 'Sending Credit (max messages)',
                'type' => 'text',
                'default' => '60',
                'description' => 'Maximum number of SMS per time period',
            ],
            [
                'name' => 'rate_time_value',
                'label' => 'Time Base',
                'type' => 'text',
                'default' => '1',
            ],
            [
                'name' => 'rate_time_unit',
                'label' => 'Time Unit',
                'type' => 'select',
                'options' => [
                    'second' => 'Second',
                    'minute' => 'Minute',
                    'hour' => 'Hour',
                ],
                'default' => 'minute',
            ],
            [
                'name' => 'sms_per_request',
                'label' => 'SMS Per Single Request',
                'type' => 'text',
                'default' => '1',
                'description' => 'Number of SMS in single API request (for bulk)',
            ],
            [
                'name' => 'bulk_delimiter',
                'label' => 'Delimiter (for bulk)',
                'type' => 'select',
                'options' => [
                    ',' => 'Comma (,)',
                    ';' => 'Semicolon (;)',
                    '|' => 'Pipe (|)',
                    '\n' => 'New Line',
                ],
                'default' => ',',
            ],

            // === Features ===
            [
                'name' => '_section_features',
                'label' => 'Features',
                'type' => 'section',
            ],
            [
                'name' => 'support_plain',
                'label' => 'Plain Text Messages',
                'type' => 'select',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
            ],
            [
                'name' => 'support_unicode',
                'label' => 'Unicode Messages',
                'type' => 'select',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
            ],
            [
                'name' => 'support_schedule',
                'label' => 'Scheduled Messages',
                'type' => 'select',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'no',
            ],

            // === Parameter Mapping ===
            [
                'name' => '_section_params',
                'label' => 'Parameter Mapping',
                'type' => 'section',
            ],
            // Username/API Key
            [
                'name' => 'param_username_key',
                'label' => 'Username/API Key - Parameter Name',
                'type' => 'text',
                'placeholder' => 'username',
            ],
            [
                'name' => 'param_username_value',
                'label' => 'Username/API Key - Value',
                'type' => 'text',
                'placeholder' => 'your_username',
            ],
            [
                'name' => 'param_username_location',
                'label' => 'Username/API Key - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL'],
                'default' => 'body',
            ],
            // Password
            [
                'name' => 'param_password_key',
                'label' => 'Password - Parameter Name',
                'type' => 'text',
                'placeholder' => 'password',
            ],
            [
                'name' => 'param_password_value',
                'label' => 'Password - Value',
                'type' => 'password',
            ],
            [
                'name' => 'param_password_location',
                'label' => 'Password - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'],
                'default' => 'body',
            ],
            // Action
            [
                'name' => 'param_action_key',
                'label' => 'Action - Parameter Name',
                'type' => 'text',
                'placeholder' => 'action',
            ],
            [
                'name' => 'param_action_value',
                'label' => 'Action - Value',
                'type' => 'text',
                'placeholder' => 'send',
            ],
            [
                'name' => 'param_action_location',
                'label' => 'Action - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'],
                'default' => 'blank',
            ],
            // Source (Sender ID)
            [
                'name' => 'param_source_key',
                'label' => 'Source/Sender ID - Parameter Name',
                'type' => 'text',
                'default' => 'from',
                'placeholder' => 'from, sender, source',
            ],
            [
                'name' => 'param_source_value',
                'label' => 'Source/Sender ID - Default Value',
                'type' => 'text',
                'description' => 'Default sender ID (can be overridden per message)',
            ],
            [
                'name' => 'param_source_location',
                'label' => 'Source - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL'],
                'default' => 'body',
            ],
            // Destination (Phone Number)
            [
                'name' => 'param_destination_key',
                'label' => 'Destination - Parameter Name',
                'type' => 'text',
                'default' => 'to',
                'placeholder' => 'to, msisdn, destination, phone',
            ],
            // Message
            [
                'name' => 'param_message_key',
                'label' => 'Message - Parameter Name',
                'type' => 'text',
                'default' => 'message',
                'placeholder' => 'message, text, body, content',
            ],
            // Unicode
            [
                'name' => 'param_unicode_key',
                'label' => 'Unicode - Parameter Name',
                'type' => 'text',
                'placeholder' => 'unicode, encoding, type',
            ],
            [
                'name' => 'param_unicode_value',
                'label' => 'Unicode - Value (when unicode)',
                'type' => 'text',
                'placeholder' => '1, true, unicode',
            ],
            [
                'name' => 'param_unicode_location',
                'label' => 'Unicode - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'],
                'default' => 'blank',
            ],
            // Type/Route
            [
                'name' => 'param_type_key',
                'label' => 'Type/Route - Parameter Name',
                'type' => 'text',
                'placeholder' => 'type, route',
            ],
            [
                'name' => 'param_type_value',
                'label' => 'Type/Route - Value',
                'type' => 'text',
            ],
            [
                'name' => 'param_type_location',
                'label' => 'Type/Route - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'],
                'default' => 'blank',
            ],
            // Language
            [
                'name' => 'param_language_key',
                'label' => 'Language - Parameter Name',
                'type' => 'text',
                'placeholder' => 'lang, language',
            ],
            [
                'name' => 'param_language_value',
                'label' => 'Language - Value',
                'type' => 'text',
            ],
            [
                'name' => 'param_language_location',
                'label' => 'Language - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'],
                'default' => 'blank',
            ],
            // Schedule
            [
                'name' => 'param_schedule_key',
                'label' => 'Schedule - Parameter Name',
                'type' => 'text',
                'placeholder' => 'schedule, send_at, datetime',
            ],
            [
                'name' => 'param_schedule_format',
                'label' => 'Schedule - Date Format',
                'type' => 'text',
                'default' => 'Y-m-d H:i:s',
                'placeholder' => 'Y-m-d H:i:s',
            ],
            // Custom Values 1-3
            [
                'name' => 'param_custom1_key',
                'label' => 'Custom Value 1 - Parameter Name',
                'type' => 'text',
            ],
            [
                'name' => 'param_custom1_value',
                'label' => 'Custom Value 1 - Value',
                'type' => 'text',
            ],
            [
                'name' => 'param_custom1_location',
                'label' => 'Custom Value 1 - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'],
                'default' => 'blank',
            ],
            [
                'name' => 'param_custom2_key',
                'label' => 'Custom Value 2 - Parameter Name',
                'type' => 'text',
            ],
            [
                'name' => 'param_custom2_value',
                'label' => 'Custom Value 2 - Value',
                'type' => 'text',
            ],
            [
                'name' => 'param_custom2_location',
                'label' => 'Custom Value 2 - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'],
                'default' => 'blank',
            ],
            [
                'name' => 'param_custom3_key',
                'label' => 'Custom Value 3 - Parameter Name',
                'type' => 'text',
            ],
            [
                'name' => 'param_custom3_value',
                'label' => 'Custom Value 3 - Value',
                'type' => 'text',
            ],
            [
                'name' => 'param_custom3_location',
                'label' => 'Custom Value 3 - Location',
                'type' => 'select',
                'options' => ['body' => 'Request Body', 'url' => 'Add to URL', 'blank' => 'Not Used'],
                'default' => 'blank',
            ],

            // === Response Handling ===
            [
                'name' => '_section_response',
                'label' => 'Response Handling',
                'type' => 'section',
            ],
            [
                'name' => 'success_codes',
                'label' => 'Success HTTP Codes',
                'type' => 'text',
                'default' => '200,201,202',
                'description' => 'Comma-separated HTTP status codes indicating success',
            ],
            [
                'name' => 'response_message_id_path',
                'label' => 'Message ID Path in Response',
                'type' => 'text',
                'default' => 'message_id',
                'description' => 'JSON path to message ID (e.g., data.id or messages.0.id)',
            ],
            [
                'name' => 'phone_format',
                'label' => 'Phone Number Format',
                'type' => 'select',
                'options' => [
                    'as_is' => 'As Is (no modification)',
                    'plus_prefix' => 'With + Prefix',
                    'no_plus' => 'Without + Prefix',
                    'digits_only' => 'Digits Only',
                ],
                'default' => 'as_is',
            ],

            // === Balance Check ===
            [
                'name' => '_section_balance',
                'label' => 'Balance Check (Optional)',
                'type' => 'section',
            ],
            [
                'name' => 'balance_endpoint',
                'label' => 'Balance Check Endpoint',
                'type' => 'text',
                'description' => 'URL to check account balance',
            ],
            [
                'name' => 'balance_path',
                'label' => 'Balance Path in Response',
                'type' => 'text',
                'default' => 'balance',
                'description' => 'JSON path to balance value',
            ],

            // === Custom Headers ===
            [
                'name' => '_section_headers',
                'label' => 'Custom Headers',
                'type' => 'section',
            ],
            [
                'name' => 'custom_headers',
                'label' => 'Additional Headers',
                'type' => 'textarea',
                'description' => 'One header per line: Header-Name: Value',
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

        // Detect if unicode
        $isUnicode = $this->detectUnicode($message->message);

        // Build URL parameters
        $urlParams = $this->buildUrlParams($message, $to, $isUnicode);
        if (!empty($urlParams)) {
            $separator = (strpos($endpoint, '?') === false) ? '?' : '&';
            $endpoint .= $separator . http_build_query($urlParams);
        }

        // Build body parameters
        $bodyData = $this->buildBodyParams($message, $to, $isUnicode);

        // Build headers
        $headers = $this->buildHeaders();

        // Check JSON encoding
        $jsonEncoded = $this->getConfig('json_encoded', 'no') === 'yes';
        if ($jsonEncoded) {
            $headers['Content-Type'] = 'application/json';
        }

        // SSL verification
        $verifySSL = $this->getConfig('ignore_ssl', 'no') !== 'yes';

        // Make request
        list($success, $response, $httpCode, $error) = $this->httpRequestCustom(
            $method,
            $endpoint,
            $bodyData,
            $headers,
            $jsonEncoded,
            $verifySSL
        );

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
     * Build URL query parameters
     */
    protected function buildUrlParams(MessageDTO $message, string $formattedTo, bool $isUnicode): array
    {
        $params = [];

        // Parameter mappings that go to URL
        $mappings = [
            'username' => ['key' => 'param_username_key', 'value' => 'param_username_value', 'location' => 'param_username_location'],
            'password' => ['key' => 'param_password_key', 'value' => 'param_password_value', 'location' => 'param_password_location'],
            'action' => ['key' => 'param_action_key', 'value' => 'param_action_value', 'location' => 'param_action_location'],
            'source' => ['key' => 'param_source_key', 'value' => 'param_source_value', 'location' => 'param_source_location'],
            'type' => ['key' => 'param_type_key', 'value' => 'param_type_value', 'location' => 'param_type_location'],
            'language' => ['key' => 'param_language_key', 'value' => 'param_language_value', 'location' => 'param_language_location'],
            'unicode' => ['key' => 'param_unicode_key', 'value' => 'param_unicode_value', 'location' => 'param_unicode_location'],
            'custom1' => ['key' => 'param_custom1_key', 'value' => 'param_custom1_value', 'location' => 'param_custom1_location'],
            'custom2' => ['key' => 'param_custom2_key', 'value' => 'param_custom2_value', 'location' => 'param_custom2_location'],
            'custom3' => ['key' => 'param_custom3_key', 'value' => 'param_custom3_value', 'location' => 'param_custom3_location'],
        ];

        foreach ($mappings as $name => $config) {
            $paramKey = $this->getConfig($config['key'], '');
            $paramValue = $this->getConfig($config['value'], '');
            $location = $this->getConfig($config['location'], 'blank');

            if ($location === 'url' && !empty($paramKey)) {
                // Special handling for source (sender ID) - use message sender if available
                if ($name === 'source' && !empty($message->from)) {
                    $paramValue = $message->from;
                }
                // Special handling for unicode - only add if unicode message
                if ($name === 'unicode' && !$isUnicode) {
                    continue;
                }

                $params[$paramKey] = $paramValue;
            }
        }

        return $params;
    }

    /**
     * Build request body parameters
     */
    protected function buildBodyParams(MessageDTO $message, string $formattedTo, bool $isUnicode): array
    {
        $params = [];

        // Destination (always in body)
        $destKey = $this->getConfig('param_destination_key', 'to');
        if (!empty($destKey)) {
            $params[$destKey] = $formattedTo;
        }

        // Message (always in body)
        $msgKey = $this->getConfig('param_message_key', 'message');
        if (!empty($msgKey)) {
            $params[$msgKey] = $message->message;
        }

        // Parameter mappings that go to body
        $mappings = [
            'username' => ['key' => 'param_username_key', 'value' => 'param_username_value', 'location' => 'param_username_location'],
            'password' => ['key' => 'param_password_key', 'value' => 'param_password_value', 'location' => 'param_password_location'],
            'action' => ['key' => 'param_action_key', 'value' => 'param_action_value', 'location' => 'param_action_location'],
            'source' => ['key' => 'param_source_key', 'value' => 'param_source_value', 'location' => 'param_source_location'],
            'type' => ['key' => 'param_type_key', 'value' => 'param_type_value', 'location' => 'param_type_location'],
            'language' => ['key' => 'param_language_key', 'value' => 'param_language_value', 'location' => 'param_language_location'],
            'unicode' => ['key' => 'param_unicode_key', 'value' => 'param_unicode_value', 'location' => 'param_unicode_location'],
            'custom1' => ['key' => 'param_custom1_key', 'value' => 'param_custom1_value', 'location' => 'param_custom1_location'],
            'custom2' => ['key' => 'param_custom2_key', 'value' => 'param_custom2_value', 'location' => 'param_custom2_location'],
            'custom3' => ['key' => 'param_custom3_key', 'value' => 'param_custom3_value', 'location' => 'param_custom3_location'],
        ];

        foreach ($mappings as $name => $config) {
            $paramKey = $this->getConfig($config['key'], '');
            $paramValue = $this->getConfig($config['value'], '');
            $location = $this->getConfig($config['location'], 'blank');

            if ($location === 'body' && !empty($paramKey)) {
                // Special handling for source (sender ID) - use message sender if available
                if ($name === 'source' && !empty($message->from)) {
                    $paramValue = $message->from;
                }
                // Special handling for unicode - only add if unicode message
                if ($name === 'unicode' && !$isUnicode) {
                    continue;
                }

                $params[$paramKey] = $paramValue;
            }
        }

        // Schedule parameter
        if (!empty($message->scheduleTime)) {
            $scheduleKey = $this->getConfig('param_schedule_key', '');
            if (!empty($scheduleKey)) {
                $format = $this->getConfig('param_schedule_format', 'Y-m-d H:i:s');
                $params[$scheduleKey] = date($format, strtotime($message->scheduleTime));
            }
        }

        return $params;
    }

    /**
     * Build request headers
     */
    protected function buildHeaders(): array
    {
        $headers = [];

        // Content type
        $contentType = $this->getConfig('content_type', 'application/x-www-form-urlencoded');
        $headers['Content-Type'] = $contentType;

        // Accept header
        $acceptHeader = $this->getConfig('accept_header', 'application/json');
        $headers['Accept'] = $acceptHeader;

        // Character encoding
        $encoding = $this->getConfig('character_encoding', 'none');
        if ($encoding !== 'none') {
            $headers['Content-Type'] .= '; charset=' . $encoding;
        }

        // Authentication
        $authType = $this->getConfig('auth_type', 'params');

        switch ($authType) {
            case 'basic':
                $username = $this->getConfig('param_username_value', '');
                $password = $this->getConfig('param_password_value', '');
                $headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
                break;

            case 'bearer':
                $token = $this->getConfig('param_username_value', '');
                $headers['Authorization'] = 'Bearer ' . $token;
                break;

            case 'api_key_header':
                $headerName = $this->getConfig('auth_header_name', 'Authorization');
                $apiKey = $this->getConfig('param_username_value', '');
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
     * Custom HTTP request with full options
     */
    protected function httpRequestCustom(
        string $method,
        string $url,
        array $data,
        array $headers,
        bool $jsonEncode = false,
        bool $verifySSL = true
    ): array {
        $ch = curl_init();

        // Set URL
        if (strtoupper($method) === 'GET' && !empty($data)) {
            $separator = (strpos($url, '?') === false) ? '?' : '&';
            $url .= $separator . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // SSL verification
        if (!$verifySSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        // Set method
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($jsonEncode) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($jsonEncode) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
        }

        // Set headers
        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = "{$key}: {$value}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

        // Execute
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [empty($error), $response, $httpCode, $error];
    }

    /**
     * Detect if message contains unicode characters
     */
    protected function detectUnicode(string $message): bool
    {
        return strlen($message) !== strlen(utf8_decode($message));
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
        $codes = array_map('intval', array_map('trim', explode(',', $successCodes)));

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
        $errorPaths = ['error', 'error.message', 'errors.0', 'message', 'errorMessage', 'error_message', 'description'];

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
        $verifySSL = $this->getConfig('ignore_ssl', 'no') !== 'yes';

        // Add auth params to URL if needed
        $urlParams = $this->buildUrlParams(new MessageDTO([]), '', false);
        if (!empty($urlParams)) {
            $separator = (strpos($endpoint, '?') === false) ? '?' : '&';
            $endpoint .= $separator . http_build_query($urlParams);
        }

        list($success, $response, $httpCode, $error) = $this->httpRequestCustom('GET', $endpoint, [], $headers, false, $verifySSL);

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

    /**
     * Get rate limit configuration
     */
    public function getRateLimit(): array
    {
        return [
            'limit' => (int)$this->getConfig('rate_limit', 60),
            'time_value' => (int)$this->getConfig('rate_time_value', 1),
            'time_unit' => $this->getConfig('rate_time_unit', 'minute'),
            'sms_per_request' => (int)$this->getConfig('sms_per_request', 1),
        ];
    }
}
