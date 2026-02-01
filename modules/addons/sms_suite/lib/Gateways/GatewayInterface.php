<?php
/**
 * SMS Suite - Gateway Interface
 *
 * All gateway drivers must implement this interface
 */

namespace SMSSuite\Gateways;

interface GatewayInterface
{
    /**
     * Get the gateway type identifier
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get human-readable gateway name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get supported channels
     *
     * @return array ['sms', 'whatsapp', 'mms', 'voice']
     */
    public function getSupportedChannels(): array;

    /**
     * Check if gateway supports a specific channel
     *
     * @param string $channel
     * @return bool
     */
    public function supportsChannel(string $channel): bool;

    /**
     * Get required configuration fields
     *
     * @return array Field definitions
     */
    public function getRequiredFields(): array;

    /**
     * Get optional configuration fields
     *
     * @return array Field definitions
     */
    public function getOptionalFields(): array;

    /**
     * Set gateway configuration
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void;

    /**
     * Validate configuration
     *
     * @param array $config
     * @return ValidationResult
     */
    public function validateConfig(array $config): ValidationResult;

    /**
     * Send a message
     *
     * @param MessageDTO $message
     * @return SendResult
     */
    public function send(MessageDTO $message): SendResult;

    /**
     * Get account balance
     *
     * @return float|null
     */
    public function getBalance(): ?float;

    /**
     * Parse delivery receipt webhook payload
     *
     * @param array $payload
     * @return DLRResult|null
     */
    public function parseDeliveryReceipt(array $payload): ?DLRResult;

    /**
     * Parse inbound message webhook payload
     *
     * @param array $payload
     * @return InboundResult|null
     */
    public function parseInboundMessage(array $payload): ?InboundResult;

    /**
     * Verify webhook signature/token
     *
     * @param array $headers
     * @param string $body
     * @param string $secret
     * @return bool
     */
    public function verifyWebhook(array $headers, string $body, string $secret): bool;
}

/**
 * Data Transfer Object for messages
 */
class MessageDTO
{
    public int $id;
    public int $clientId;
    public string $channel = 'sms';
    public string $to;
    public string $from;
    public string $message;
    public ?string $mediaUrl = null;
    public string $encoding = 'gsm7';
    public int $segments = 1;
    public array $metadata = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * Result of send operation
 */
class SendResult
{
    public bool $success;
    public ?string $messageId = null;
    public ?string $status = null;
    public ?string $error = null;
    public ?string $errorCode = null;
    public array $rawResponse = [];

    public function __construct(bool $success, ?string $messageId = null, ?string $error = null)
    {
        $this->success = $success;
        $this->messageId = $messageId;
        $this->error = $error;
        $this->status = $success ? 'sent' : 'failed';
    }

    public static function success(string $messageId, array $response = []): self
    {
        $result = new self(true, $messageId);
        $result->rawResponse = $response;
        return $result;
    }

    public static function failure(string $error, ?string $errorCode = null, array $response = []): self
    {
        $result = new self(false, null, $error);
        $result->errorCode = $errorCode;
        $result->rawResponse = $response;
        return $result;
    }
}

/**
 * Result of configuration validation
 */
class ValidationResult
{
    public bool $valid;
    public array $errors = [];

    public function __construct(bool $valid, array $errors = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
    }

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }

    public function addError(string $field, string $message): void
    {
        $this->valid = false;
        $this->errors[$field] = $message;
    }
}

/**
 * Delivery receipt result
 */
class DLRResult
{
    public string $messageId;
    public string $status;
    public ?string $errorCode = null;
    public ?string $errorMessage = null;
    public ?string $deliveredAt = null;
    public array $rawPayload = [];

    public function __construct(string $messageId, string $status)
    {
        $this->messageId = $messageId;
        $this->status = $status;
    }

    /**
     * Normalize status to standard values
     */
    public static function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        $map = [
            'DELIVRD' => 'delivered',
            'DELIVERED' => 'delivered',
            'SENT' => 'sent',
            'ACCEPTED' => 'sent',
            'ACCEPTD' => 'sent',
            'UNDELIVERABLE' => 'undelivered',
            'UNDELIV' => 'undelivered',
            'EXPIRED' => 'expired',
            'DELETED' => 'expired',
            'REJECTED' => 'rejected',
            'REJECTD' => 'rejected',
            'FAILED' => 'failed',
            'ERROR' => 'failed',
            'UNKNOWN' => 'unknown',
            'ENROUTE' => 'sending',
            'QUEUED' => 'queued',
        ];

        return $map[$status] ?? 'unknown';
    }
}

/**
 * Inbound message result
 */
class InboundResult
{
    public string $from;
    public string $to;
    public string $message;
    public ?string $messageId = null;
    public ?string $mediaUrl = null;
    public string $channel = 'sms';
    public array $rawPayload = [];

    public function __construct(string $from, string $to, string $message)
    {
        $this->from = $from;
        $this->to = $to;
        $this->message = $message;
    }
}
