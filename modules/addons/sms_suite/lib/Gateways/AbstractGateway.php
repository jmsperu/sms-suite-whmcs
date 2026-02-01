<?php
/**
 * SMS Suite - Abstract Gateway
 *
 * Base class for gateway implementations
 */

namespace SMSSuite\Gateways;

use WHMCS\Database\Capsule;

abstract class AbstractGateway implements GatewayInterface
{
    /**
     * Gateway configuration
     * @var array
     */
    protected array $config = [];

    /**
     * Gateway ID (from database)
     * @var int|null
     */
    protected ?int $gatewayId = null;

    /**
     * {@inheritdoc}
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->gatewayId = $config['gateway_id'] ?? null;
    }

    /**
     * Get config value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsChannel(string $channel): bool
    {
        return in_array($channel, $this->getSupportedChannels());
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionalFields(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfig(array $config): ValidationResult
    {
        $errors = [];

        foreach ($this->getRequiredFields() as $field) {
            $name = $field['name'] ?? '';
            if (empty($config[$name])) {
                $label = $field['label'] ?? $name;
                $errors[$name] = "{$label} is required";
            }
        }

        if (!empty($errors)) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success();
    }

    /**
     * {@inheritdoc}
     */
    public function getBalance(): ?float
    {
        // Override in subclass if supported
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        // Override in subclass
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function parseInboundMessage(array $payload): ?InboundResult
    {
        // Override in subclass
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhook(array $headers, string $body, string $secret): bool
    {
        // Default: check token parameter or header
        $token = $_GET['token'] ?? $headers['X-Webhook-Token'] ?? $headers['x-webhook-token'] ?? '';
        return hash_equals($secret, $token);
    }

    /**
     * Make HTTP request
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return array [success, body, httpCode, error]
     */
    protected function httpRequest(string $method, string $url, array $data = [], array $headers = []): array
    {
        $ch = curl_init();

        $method = strtoupper($method);

        // Set URL and method
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Set headers
        $headerList = [];
        foreach ($headers as $key => $value) {
            $headerList[] = "{$key}: {$value}";
        }
        if (!empty($headerList)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerList);
        }

        // Set body for POST/PUT
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $contentType = $headers['Content-Type'] ?? '';

            if (stripos($contentType, 'application/json') !== false) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        // Execute
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // Log request (redact sensitive data)
        $this->logRequest($method, $url, $data, $response, $httpCode, $error);

        $success = ($httpCode >= 200 && $httpCode < 300);

        return [$success, $response, $httpCode, $error];
    }

    /**
     * Log gateway request/response
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param string $response
     * @param int $httpCode
     * @param string $error
     */
    protected function logRequest(string $method, string $url, array $data, string $response, int $httpCode, string $error = ''): void
    {
        // Redact sensitive fields
        $sensitiveFields = ['password', 'api_key', 'api_secret', 'auth_token', 'access_token', 'secret'];
        $redactedData = $this->redactSensitive($data, $sensitiveFields);

        $logEntry = [
            'gateway_id' => $this->gatewayId,
            'gateway_type' => $this->getType(),
            'method' => $method,
            'url' => $url,
            'request' => json_encode($redactedData),
            'response' => substr($response, 0, 5000), // Limit response size
            'http_code' => $httpCode,
            'error' => $error,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Log to activity log for now (could be separate table)
        if ($error || $httpCode >= 400) {
            logActivity("SMS Suite Gateway Error: {$this->getType()} - {$error} (HTTP {$httpCode})");
        }
    }

    /**
     * Redact sensitive fields from array
     *
     * @param array $data
     * @param array $fields
     * @return array
     */
    protected function redactSensitive(array $data, array $fields): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactSensitive($value, $fields);
            } elseif (in_array(strtolower($key), $fields)) {
                $data[$key] = '[REDACTED]';
            }
        }
        return $data;
    }

    /**
     * Parse JSON response
     *
     * @param string $response
     * @return array
     */
    protected function parseJsonResponse(string $response): array
    {
        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Format phone number (remove non-digits, ensure + prefix for international)
     *
     * @param string $phone
     * @param bool $addPlus
     * @return string
     */
    protected function formatPhone(string $phone, bool $addPlus = false): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Add + if required and not present
        if ($addPlus && substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Convenience method for POST requests
     *
     * @param string $url
     * @param array|string $data
     * @param array $headers
     * @param bool $json Send as JSON
     * @return array ['http_code' => int, 'body' => string]
     */
    protected function httpPost(string $url, $data = [], array $headers = [], bool $json = false): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Build headers
        $headerList = [];
        foreach ($headers as $key => $value) {
            $headerList[] = "{$key}: {$value}";
        }
        if (!empty($headerList)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerList);
        }

        // Set body
        if ($json && is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // Log errors
        if ($error || $httpCode >= 400) {
            logActivity("SMS Suite Gateway Error: {$this->getType()} - {$error} (HTTP {$httpCode})");
        }

        return [
            'http_code' => $httpCode,
            'body' => $response,
            'error' => $error,
        ];
    }

    /**
     * Convenience method for GET requests
     *
     * @param string $url
     * @param array $headers
     * @return array ['http_code' => int, 'body' => string]
     */
    protected function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $headerList = [];
        foreach ($headers as $key => $value) {
            $headerList[] = "{$key}: {$value}";
        }
        if (!empty($headerList)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerList);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'body' => $response,
            'error' => $error,
        ];
    }
}
