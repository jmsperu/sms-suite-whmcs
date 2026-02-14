<?php
/**
 * SMS Suite - Facebook Messenger Gateway
 *
 * Sends/receives messages via Facebook Page Messaging (Graph API)
 */

namespace SMSSuite\Gateways;

class MessengerGateway extends AbstractGateway
{
    const API_VERSION = 'v24.0';
    const API_BASE = 'https://graph.facebook.com/';

    public function getType(): string { return 'messenger'; }
    public function getName(): string { return 'Facebook Messenger'; }
    public function getSupportedChannels(): array { return ['messenger']; }

    public function getRequiredFields(): array
    {
        return [
            ['name' => 'page_id', 'type' => 'text', 'label' => 'Page ID', 'required' => true, 'placeholder' => 'Your Facebook Page ID'],
            ['name' => 'page_access_token', 'type' => 'password', 'label' => 'Page Access Token', 'required' => true, 'placeholder' => 'Long-lived Page Access Token'],
        ];
    }

    public function send(MessageDTO $message): SendResult
    {
        $token = $this->config['page_access_token'] ?? '';
        if (empty($token)) {
            return SendResult::failure('Page access token not configured');
        }

        $psid = $message->to;
        if (empty($psid)) {
            return SendResult::failure('Recipient PSID is required');
        }

        $url = self::API_BASE . self::API_VERSION . '/me/messages?access_token=' . urlencode($token);

        $payload = [
            'recipient' => ['id' => $psid],
            'message' => ['text' => $message->message],
        ];

        $response = $this->httpPost($url, $payload, [
            'Content-Type' => 'application/json',
        ], true);

        $data = json_decode($response['body'] ?? '', true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300 && !empty($data['message_id'])) {
            return SendResult::success($data['message_id'], $data);
        }

        $errorMsg = $data['error']['message'] ?? ($response['error'] ?? 'Messenger API error');
        $errorCode = (string)($data['error']['code'] ?? '');
        return SendResult::failure($errorMsg, $errorCode, $data ?? []);
    }

    public function parseInboundMessage(array $payload): ?InboundResult
    {
        // Messenger webhook: entry[].messaging[].message
        $messaging = $payload['messaging'][0] ?? null;
        if (!$messaging || !isset($messaging['message'])) {
            return null;
        }

        $senderId = (string)($messaging['sender']['id'] ?? '');
        $text = $messaging['message']['text'] ?? '';
        $msgId = $messaging['message']['mid'] ?? '';

        if (empty($senderId)) {
            return null;
        }

        $pageId = $this->config['page_id'] ?? '';
        $inbound = new InboundResult($senderId, $pageId, $text);
        $inbound->messageId = $msgId;
        $inbound->channel = 'messenger';
        $inbound->rawPayload = $payload;

        return $inbound;
    }

    public function parseDeliveryReceipt(array $payload): ?DLRResult
    {
        // Messenger delivery/read receipts
        $messaging = $payload['messaging'][0] ?? null;
        if (!$messaging) {
            return null;
        }

        if (isset($messaging['delivery'])) {
            $mids = $messaging['delivery']['mids'] ?? [];
            if (!empty($mids)) {
                return new DLRResult($mids[0], 'delivered');
            }
        }

        if (isset($messaging['read'])) {
            // Read receipts don't include specific message IDs
            return null;
        }

        return null;
    }

    public function verifyWebhook(array $headers, string $body, string $secret): bool
    {
        // Uses X-Hub-Signature-256 (same as Meta WhatsApp)
        $signature = $headers['X-HUB-SIGNATURE-256']
            ?? $headers['X-Hub-Signature-256']
            ?? $headers['x-hub-signature-256']
            ?? '';

        if (empty($signature) || empty($secret)) {
            return false; // Reject if signature or secret is missing
        }

        $signature = str_replace('sha256=', '', $signature);
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Subscribe the app to the page's messaging events
     */
    public function subscribeWebhook(): array
    {
        $pageId = $this->config['page_id'] ?? '';
        $token = $this->config['page_access_token'] ?? '';

        if (empty($pageId) || empty($token)) {
            return ['success' => false, 'error' => 'Page ID and access token are required'];
        }

        $url = self::API_BASE . self::API_VERSION . '/' . $pageId
             . '/subscribed_apps?subscribed_fields=messages,messaging_postbacks&access_token=' . urlencode($token);

        $response = $this->httpPost($url, [], [
            'Content-Type' => 'application/json',
        ], true);

        $data = json_decode($response['body'] ?? '', true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300 && ($data['success'] ?? false)) {
            return ['success' => true, 'description' => 'App subscribed to page messages'];
        }

        return ['success' => false, 'error' => $data['error']['message'] ?? 'Failed to subscribe'];
    }

    /**
     * Get sender profile info from Facebook
     */
    public function getSenderProfile(string $psid): ?array
    {
        $token = $this->config['page_access_token'] ?? '';
        if (empty($token)) {
            return null;
        }

        $url = self::API_BASE . self::API_VERSION . '/' . $psid
             . '?fields=name,profile_pic&access_token=' . urlencode($token);

        $response = $this->httpGet($url);
        $data = json_decode($response['body'] ?? '', true);

        if ($response['http_code'] === 200 && !empty($data['name'])) {
            return $data;
        }

        return null;
    }
}
