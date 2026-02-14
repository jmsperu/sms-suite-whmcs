<?php
/**
 * SMS Suite - AI Chatbot Service
 *
 * Manages AI-powered auto-replies on inbound messages across all channels.
 * Supports Claude, OpenAI, Google Gemini, DeepSeek, Mistral, Groq, Cohere, and xAI Grok.
 */

namespace SMSSuite\AI;

use WHMCS\Database\Capsule;
use Exception;

class ChatbotService
{
    /**
     * Get chatbot config for a given client/gateway.
     * Resolution: client+gateway → client (all gateways) → system+gateway → system (all gateways)
     */
    public static function getConfig(?int $clientId = null, ?int $gatewayId = null): ?array
    {
        // Try client+gateway specific
        if ($clientId && $gatewayId) {
            $config = Capsule::table('mod_sms_chatbot_config')
                ->where('client_id', $clientId)
                ->where('gateway_id', $gatewayId)
                ->first();
            if ($config) return (array)$config;
        }

        // Try client, all gateways
        if ($clientId) {
            $config = Capsule::table('mod_sms_chatbot_config')
                ->where('client_id', $clientId)
                ->whereNull('gateway_id')
                ->first();
            if ($config) return (array)$config;
        }

        // Try system+gateway specific
        if ($gatewayId) {
            $config = Capsule::table('mod_sms_chatbot_config')
                ->whereNull('client_id')
                ->where('gateway_id', $gatewayId)
                ->first();
            if ($config) return (array)$config;
        }

        // System-wide fallback
        $config = Capsule::table('mod_sms_chatbot_config')
            ->whereNull('client_id')
            ->whereNull('gateway_id')
            ->first();

        return $config ? (array)$config : null;
    }

    /**
     * Check if chatbot should handle this inbound message
     */
    public static function shouldAutoReply(?int $clientId = null, ?int $gatewayId = null, string $channel = 'whatsapp'): bool
    {
        try {
            $config = self::getConfig($clientId, $gatewayId);
            if (!$config || empty($config['enabled'])) {
                return false;
            }

            // Check channel is enabled
            $enabledChannels = array_map('trim', explode(',', $config['channels'] ?? 'whatsapp,telegram'));
            if (!in_array($channel, $enabledChannels)) {
                return false;
            }

            // Check business hours if configured
            if (!empty($config['business_hours'])) {
                $hours = json_decode($config['business_hours'], true);
                if ($hours && !empty($hours['start']) && !empty($hours['end'])) {
                    $tz = new \DateTimeZone($hours['timezone'] ?? 'UTC');
                    $now = new \DateTime('now', $tz);
                    $start = \DateTime::createFromFormat('H:i', $hours['start'], $tz);
                    $end = \DateTime::createFromFormat('H:i', $hours['end'], $tz);

                    if ($start && $end) {
                        $withinHours = ($now >= $start && $now <= $end);
                        $onlyOutside = !empty($hours['only_outside']);

                        // If only_outside: reply only outside hours
                        // If not only_outside: reply only during hours
                        if ($onlyOutside && $withinHours) {
                            return false;
                        }
                        if (!$onlyOutside && !$withinHours) {
                            return false;
                        }
                    }
                }
            }

            // Check AI provider is configured
            $provider = self::getProvider($config);
            $apiKey = self::resolveApiKey($config);
            if (empty($provider) || $provider === 'none' || empty($apiKey)) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            logActivity('SMS Suite Chatbot: shouldAutoReply error - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate AI response for an inbound message
     */
    public static function generateReply(string $message, ?int $chatboxId = null, ?int $clientId = null): ?string
    {
        try {
            // Determine gateway from chatbox
            $gatewayId = null;
            if ($chatboxId) {
                $chatbox = Capsule::table('mod_sms_chatbox')->where('id', $chatboxId)->first();
                if ($chatbox) {
                    $gatewayId = $chatbox->gateway_id ?? null;
                }
            }

            $config = self::getConfig($clientId, $gatewayId);
            if (!$config || empty($config['enabled'])) {
                return null;
            }

            $provider = self::getProvider($config);
            $apiKey = self::resolveApiKey($config);

            if (empty($provider) || $provider === 'none' || empty($apiKey)) {
                return null;
            }

            // Build system prompt
            $systemPrompt = $config['system_prompt'] ?? self::getDefaultSystemPrompt();

            // Build conversation context
            $conversationMessages = self::buildConversationContext($chatboxId, 10);

            // Add current message
            $conversationMessages[] = ['role' => 'user', 'content' => $message];

            // Call AI provider
            $maxTokens = (int)($config['max_tokens'] ?? 300);
            $temperature = (float)($config['temperature'] ?? 0.7);
            $model = $config['model'] ?? null;

            $reply = self::callProvider($provider, $systemPrompt, $conversationMessages, $apiKey, $model, $maxTokens, $temperature);

            return $reply;

        } catch (Exception $e) {
            logActivity('SMS Suite Chatbot: generateReply error - ' . $e->getMessage());

            // Return fallback message if configured
            $config = self::getConfig($clientId, $gatewayId ?? null);
            if ($config && !empty($config['fallback_message'])) {
                return $config['fallback_message'];
            }

            return null;
        }
    }

    /**
     * Provider registry: endpoint, auth style, response parsing
     */
    private static function getProviderConfig(string $provider): ?array
    {
        $providers = [
            'claude' => [
                'url' => 'https://api.anthropic.com/v1/messages',
                'auth' => 'anthropic', // x-api-key header
                'format' => 'anthropic', // system separate, response in content[0].text
                'default_model' => 'claude-sonnet-4-5-20250929',
            ],
            'openai' => [
                'url' => 'https://api.openai.com/v1/chat/completions',
                'auth' => 'bearer',
                'format' => 'openai', // system in messages, response in choices[0].message.content
                'default_model' => 'gpt-4o',
            ],
            'gemini' => [
                'url' => 'https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent',
                'auth' => 'query_key', // ?key= parameter
                'format' => 'gemini',
                'default_model' => 'gemini-2.0-flash',
            ],
            'deepseek' => [
                'url' => 'https://api.deepseek.com/chat/completions',
                'auth' => 'bearer',
                'format' => 'openai',
                'default_model' => 'deepseek-chat',
            ],
            'mistral' => [
                'url' => 'https://api.mistral.ai/v1/chat/completions',
                'auth' => 'bearer',
                'format' => 'openai',
                'default_model' => 'mistral-large-latest',
            ],
            'groq' => [
                'url' => 'https://api.groq.com/openai/v1/chat/completions',
                'auth' => 'bearer',
                'format' => 'openai',
                'default_model' => 'llama-3.3-70b-versatile',
            ],
            'cohere' => [
                'url' => 'https://api.cohere.com/v2/chat',
                'auth' => 'bearer',
                'format' => 'openai',
                'default_model' => 'command-r-plus',
            ],
            'xai' => [
                'url' => 'https://api.x.ai/v1/chat/completions',
                'auth' => 'bearer',
                'format' => 'openai',
                'default_model' => 'grok-3-mini',
            ],
        ];

        return $providers[$provider] ?? null;
    }

    /**
     * Route to the correct provider
     */
    private static function callProvider(string $provider, string $systemPrompt, array $messages, string $apiKey, ?string $model, int $maxTokens, float $temperature): ?string
    {
        $providerConfig = self::getProviderConfig($provider);
        if (!$providerConfig) {
            logActivity("SMS Suite Chatbot: Unknown provider '{$provider}'");
            return null;
        }

        $model = $model ?: $providerConfig['default_model'];

        switch ($providerConfig['format']) {
            case 'anthropic':
                return self::callAnthropic($providerConfig, $systemPrompt, $messages, $apiKey, $model, $maxTokens, $temperature);
            case 'gemini':
                return self::callGemini($providerConfig, $systemPrompt, $messages, $apiKey, $model, $maxTokens, $temperature);
            case 'openai':
            default:
                return self::callOpenAICompatible($providerConfig, $systemPrompt, $messages, $apiKey, $model, $maxTokens, $temperature);
        }
    }

    /**
     * Call Anthropic Claude API
     */
    private static function callAnthropic(array $providerConfig, string $systemPrompt, array $messages, string $apiKey, string $model, int $maxTokens, float $temperature): ?string
    {
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        if ($temperature > 0) {
            $payload['temperature'] = $temperature;
        }

        $ch = curl_init($providerConfig['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logActivity('SMS Suite Chatbot: Claude API curl error - ' . $error);
            return null;
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($data['content'][0]['text'])) {
            return trim($data['content'][0]['text']);
        }

        $errorMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
        logActivity('SMS Suite Chatbot: Claude API failed - ' . $errorMsg);
        return null;
    }

    /**
     * Call OpenAI-compatible API (OpenAI, DeepSeek, Mistral, Groq, Cohere, xAI)
     */
    private static function callOpenAICompatible(array $providerConfig, string $systemPrompt, array $messages, string $apiKey, string $model, int $maxTokens, float $temperature): ?string
    {
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];
        foreach ($messages as $msg) {
            $apiMessages[] = $msg;
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => $apiMessages,
        ];

        $ch = curl_init($providerConfig['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logActivity('SMS Suite Chatbot: API curl error - ' . $error);
            return null;
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }

        $errorMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
        logActivity('SMS Suite Chatbot: API failed - ' . $errorMsg);
        return null;
    }

    /**
     * Call Google Gemini API
     */
    private static function callGemini(array $providerConfig, string $systemPrompt, array $messages, string $apiKey, string $model, int $maxTokens, float $temperature): ?string
    {
        $url = str_replace('{MODEL}', $model, $providerConfig['url']) . '?key=' . $apiKey;

        // Convert messages to Gemini format
        $contents = [];
        foreach ($messages as $msg) {
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $payload = [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature' => $temperature,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logActivity('SMS Suite Chatbot: Gemini API curl error - ' . $error);
            return null;
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }

        $errorMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
        logActivity('SMS Suite Chatbot: Gemini API failed - ' . $errorMsg);
        return null;
    }

    /**
     * Build conversation context from chatbox history
     */
    private static function buildConversationContext(?int $chatboxId, int $limit = 10): array
    {
        if (!$chatboxId) {
            return [];
        }

        $messages = Capsule::table('mod_sms_chatbox_messages as cm')
            ->join('mod_sms_messages as m', 'cm.message_id', '=', 'm.id')
            ->where('cm.chatbox_id', $chatboxId)
            ->orderBy('cm.created_at', 'desc')
            ->limit($limit)
            ->select('m.message', 'm.direction', 'cm.created_at')
            ->get();

        $context = [];
        // Reverse to chronological order
        foreach (array_reverse($messages->toArray()) as $msg) {
            $role = ($msg->direction === 'inbound') ? 'user' : 'assistant';
            $text = $msg->message ?? '';
            if (!empty($text)) {
                $context[] = ['role' => $role, 'content' => $text];
            }
        }

        return $context;
    }

    /**
     * Get the AI provider from config (with module setting fallback)
     */
    private static function getProvider(array $config): string
    {
        $provider = $config['provider'] ?? '';
        if (empty($provider) || $provider === 'none') {
            // Fallback to module config
            $provider = self::getModuleSetting('ai_provider') ?? 'none';
        }
        return $provider;
    }

    /**
     * Resolve the API key: client's own key first, then system key
     */
    private static function resolveApiKey(array $config): string
    {
        // Check if client has their own encrypted API key
        if (!empty($config['api_key'])) {
            $decrypted = function_exists('sms_suite_decrypt')
                ? \sms_suite_decrypt($config['api_key'])
                : $config['api_key'];
            if (!empty($decrypted)) {
                return $decrypted;
            }
        }

        // Fall back to system API key
        return self::getModuleSetting('ai_api_key') ?? '';
    }

    /**
     * Get the system AI API key from module settings
     */
    private static function getSystemApiKey(): string
    {
        return self::getModuleSetting('ai_api_key') ?? '';
    }

    /**
     * Get a module setting
     */
    private static function getModuleSetting(string $key): ?string
    {
        return Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', $key)
            ->value('value');
    }

    /**
     * Default system prompt
     */
    public static function getDefaultSystemPrompt(): string
    {
        return "You are a helpful customer support assistant. Be concise, friendly, and professional. " .
               "Answer questions about our services and help resolve issues. " .
               "If you cannot help with something, suggest the customer contact our support team directly. " .
               "Keep responses brief and suitable for messaging (under 500 characters when possible).";
    }

    /**
     * Get available models for a provider
     */
    public static function getModels(string $provider): array
    {
        $models = [
            'claude' => [
                'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5 (Recommended)',
                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (Faster, cheaper)',
            ],
            'openai' => [
                'gpt-4o' => 'GPT-4o (Recommended)',
                'gpt-4o-mini' => 'GPT-4o Mini (Faster, cheaper)',
                'gpt-4.1' => 'GPT-4.1',
                'gpt-4.1-mini' => 'GPT-4.1 Mini',
            ],
            'gemini' => [
                'gemini-2.0-flash' => 'Gemini 2.0 Flash (Recommended)',
                'gemini-2.5-pro-preview-05-06' => 'Gemini 2.5 Pro',
                'gemini-2.5-flash-preview-04-17' => 'Gemini 2.5 Flash',
                'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            ],
            'deepseek' => [
                'deepseek-chat' => 'DeepSeek V3 (Recommended)',
                'deepseek-reasoner' => 'DeepSeek R1 (Reasoning)',
            ],
            'mistral' => [
                'mistral-large-latest' => 'Mistral Large (Recommended)',
                'mistral-small-latest' => 'Mistral Small (Faster, cheaper)',
                'codestral-latest' => 'Codestral',
            ],
            'groq' => [
                'llama-3.3-70b-versatile' => 'Llama 3.3 70B (Recommended)',
                'llama-3.1-8b-instant' => 'Llama 3.1 8B (Fastest)',
                'mixtral-8x7b-32768' => 'Mixtral 8x7B',
                'gemma2-9b-it' => 'Gemma 2 9B',
            ],
            'cohere' => [
                'command-r-plus' => 'Command R+ (Recommended)',
                'command-r' => 'Command R (Faster)',
                'command-a-03-2025' => 'Command A',
            ],
            'xai' => [
                'grok-3-mini' => 'Grok 3 Mini (Recommended)',
                'grok-3' => 'Grok 3',
                'grok-2' => 'Grok 2',
            ],
        ];

        return $models[$provider] ?? [];
    }

    /**
     * Get all supported providers with display names
     */
    public static function getProviders(): array
    {
        return [
            'claude'   => 'Claude (Anthropic)',
            'openai'   => 'OpenAI (GPT)',
            'gemini'   => 'Google Gemini',
            'deepseek' => 'DeepSeek',
            'mistral'  => 'Mistral AI',
            'groq'     => 'Groq (Fast inference)',
            'cohere'   => 'Cohere',
            'xai'      => 'xAI (Grok)',
        ];
    }
}
