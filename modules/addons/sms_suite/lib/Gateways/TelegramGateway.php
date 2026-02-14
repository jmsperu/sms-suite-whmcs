<?php
/**
 * SMS Suite - Telegram Bot Gateway
 *
 * Sends/receives messages via Telegram Bot API
 */

namespace SMSSuite\Gateways;

class TelegramGateway extends AbstractGateway
{
    const API_BASE = 'https://api.telegram.org/bot';

    public function getType(): string { return 'telegram'; }
    public function getName(): string { return 'Telegram Bot'; }
    public function getSupportedChannels(): array { return ['telegram']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'bot_token', 'type' => 'password', 'label' => 'Bot Token', 'required' => true, 'placeholder' => 'From @BotFather'],
            ['name' => 'bot_username', 'type' => 'text', 'label' => 'Bot Username', 'required' => true, 'placeholder' => '@YourBot (without @)'],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $botToken = $this->config['bot_token'] ?? '';
        if (empty($botToken)) {
            return SendResult::failure('Telegram bot token not configured');
        }

        $chatId = $message->to;
        if (empty($chatId)) {
            return SendResult::failure('Telegram chat_id is required');
        }

        $url = self::API_BASE . $botToken . '/sendMessage';

        $payload = [
            'chat_id' => $chatId,
            'text' => $message->message,
            'parse_mode' => 'HTML',
        ];

        $response = $this->httpPost($url, $payload, [
            'Content-Type' => 'application/json',
        ], true);

        $data = json_decode($response['body'] ?? '', true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300 && ($data['ok'] ?? false)) {
            $msgId = (string)($data['result']['message_id'] ?? '');
            return SendResult::success($msgId, $data);
        }

        $errorMsg = $data['description'] ?? ($response['error'] ?? 'Telegram API error');
        $errorCode = (string)($data['error_code'] ?? '');
        return SendResult::failure($errorMsg, $errorCode, $data ?? []);
    }

    public function parseInboundMessage(array $payload): ?InboundResult
    {
        // Telegram Update object: { message: { from, chat, text, ... } }
        $msg = $payload['message'] ?? $payload['edited_message'] ?? null;
        if (!$msg) {
            return null;
        }

        $chatId = (string)($msg['chat']['id'] ?? '');
        $text = $msg['text'] ?? $msg['caption'] ?? '';
        $botUsername = $this->config['bot_username'] ?? '';

        if (empty($chatId)) {
            return null;
        }

        $inbound = new InboundResult($chatId, $botUsername, $text);
        $inbound->messageId = (string)($msg['message_id'] ?? '');
        $inbound->channel = 'telegram';
        $inbound->rawPayload = $payload;

        return $inbound;
    }

    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        // Telegram doesn't have delivery receipts
        return null;
    }

    public function verifyWebhook(array $headers, string $body, string $secret): bool
    {
        // Telegram sends X-Telegram-Bot-Api-Secret-Token header
        $token = $headers['X-TELEGRAM-BOT-API-SECRET-TOKEN']
            ?? $headers['X-Telegram-Bot-Api-Secret-Token']
            ?? $headers['x-telegram-bot-api-secret-token']
            ?? '';

        if (empty($secret)) {
            return true;
        }

        return hash_equals($secret, $token);
    }

    /**
     * Register webhook with Telegram
     */
    public function setWebhook(string $webhookUrl, string $secretToken = ''): array
    {
        $botToken = $this->config['bot_token'] ?? '';
        if (empty($botToken)) {
            return ['success' => false, 'error' => 'Bot token not configured'];
        }

        $url = self::API_BASE . $botToken . '/setWebhook';

        $payload = [
            'url' => $webhookUrl,
            'allowed_updates' => ['message', 'edited_message', 'callback_query'],
        ];

        if (!empty($secretToken)) {
            $payload['secret_token'] = $secretToken;
        }

        $response = $this->httpPost($url, $payload, [
            'Content-Type' => 'application/json',
        ], true);

        $data = json_decode($response['body'] ?? '', true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300 && ($data['ok'] ?? false)) {
            return ['success' => true, 'description' => $data['description'] ?? 'Webhook set'];
        }

        return ['success' => false, 'error' => $data['description'] ?? 'Failed to set webhook'];
    }

    /**
     * Get bot info from Telegram
     */
    public function getMe(): ?array
    {
        $botToken = $this->config['bot_token'] ?? '';
        if (empty($botToken)) {
            return null;
        }

        $url = self::API_BASE . $botToken . '/getMe';
        $response = $this->httpGet($url);

        $data = json_decode($response['body'] ?? '', true);
        if ($response['http_code'] === 200 && ($data['ok'] ?? false)) {
            return $data['result'];
        }

        return null;
    }
}
