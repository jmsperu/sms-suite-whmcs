<?php
/**
 * SMS Suite - API Key Service
 *
 * Handles API key generation, validation, and management
 */

namespace SMSSuite\Api;

use WHMCS\Database\Capsule;
use Exception;

class ApiKeyService
{
    /**
     * Available API scopes
     */
    const SCOPES = [
        'send_sms' => 'Send SMS messages',
        'send_whatsapp' => 'Send WhatsApp messages',
        'campaigns' => 'Manage campaigns',
        'contacts' => 'Manage contacts',
        'balance' => 'View balance and transactions',
        'logs' => 'View message logs',
        'reports' => 'Access reports and usage statistics',
        'templates' => 'Manage message templates',
        'sender_ids' => 'Manage sender IDs',
    ];

    /**
     * Generate a new API key for a client
     *
     * @param int $clientId
     * @param string $name
     * @param array $scopes
     * @param int|null $rateLimit Requests per minute
     * @param string|null $expiresAt
     * @return array ['key_id' => string, 'secret' => string] - Secret shown only once
     */
    public static function generate(int $clientId, string $name, array $scopes = [], ?int $rateLimit = 60, ?string $expiresAt = null): array
    {
        // Generate unique key ID and secret
        $keyId = 'sms_' . bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(32));

        // Hash the secret for storage (using bcrypt)
        $secretHash = password_hash($secret, PASSWORD_BCRYPT, ['cost' => 10]);

        // Validate scopes
        $validScopes = array_intersect($scopes, array_keys(self::SCOPES));
        if (empty($validScopes)) {
            $validScopes = ['send_sms', 'balance', 'logs']; // Default scopes
        }

        // Insert record
        $id = Capsule::table('mod_sms_api_keys')->insertGetId([
            'client_id' => $clientId,
            'key_id' => $keyId,
            'secret_hash' => $secretHash,
            'name' => $name,
            'scopes' => json_encode(array_values($validScopes)),
            'rate_limit' => $rateLimit,
            'status' => 'active',
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        logActivity("SMS Suite: API key created - {$keyId} for client {$clientId}");

        return [
            'id' => $id,
            'key_id' => $keyId,
            'secret' => $secret, // Only returned once!
            'scopes' => $validScopes,
        ];
    }

    /**
     * Validate API key and secret
     *
     * @param string $keyId
     * @param string $secret
     * @return array|null Key data if valid, null otherwise
     */
    public static function validate(string $keyId, string $secret): ?array
    {
        $key = Capsule::table('mod_sms_api_keys')
            ->where('key_id', $keyId)
            ->where('status', 'active')
            ->first();

        if (!$key) {
            return null;
        }

        // Check expiry
        if ($key->expires_at && strtotime($key->expires_at) < time()) {
            return null;
        }

        // Verify secret
        if (!password_verify($secret, $key->secret_hash)) {
            return null;
        }

        // Check IP whitelist if configured
        if (!empty($key->allowed_ips)) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $allowedIps = array_map('trim', explode(',', $key->allowed_ips));
            $ipAllowed = false;

            foreach ($allowedIps as $allowed) {
                if (empty($allowed)) {
                    continue;
                }
                // Support CIDR notation
                if (strpos($allowed, '/') !== false) {
                    if (self::ipInCidr($clientIp, $allowed)) {
                        $ipAllowed = true;
                        break;
                    }
                } elseif ($clientIp === $allowed) {
                    $ipAllowed = true;
                    break;
                }
            }

            if (!$ipAllowed) {
                return null;
            }
        }

        // Update last used
        Capsule::table('mod_sms_api_keys')
            ->where('id', $key->id)
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);

        return [
            'id' => $key->id,
            'client_id' => $key->client_id,
            'key_id' => $key->key_id,
            'name' => $key->name,
            'scopes' => json_decode($key->scopes, true) ?: [],
            'rate_limit' => $key->rate_limit,
        ];
    }

    /**
     * Check if key has a specific scope
     *
     * @param array $keyData
     * @param string $scope
     * @return bool
     */
    public static function hasScope(array $keyData, string $scope): bool
    {
        return in_array($scope, $keyData['scopes']);
    }

    /**
     * Check rate limit for key
     *
     * @param int $keyId
     * @param int $limit Requests per minute
     * @return bool True if within limit
     */
    public static function checkRateLimit(int $keyId, int $limit): bool
    {
        $windowStart = date('Y-m-d H:i:00'); // Current minute

        // Periodically clean up stale rate limit records (older than 5 minutes)
        static $cleaned = false;
        if (!$cleaned) {
            $cleaned = true;
            try {
                Capsule::table('mod_sms_rate_limits')
                    ->where('window', '<', date('Y-m-d H:i:00', strtotime('-5 minutes')))
                    ->delete();
            } catch (\Exception $e) {
                // Non-critical — ignore cleanup failures
            }
        }

        // Atomic upsert: insert new row or increment existing (prevents race condition)
        Capsule::statement(
            "INSERT INTO `mod_sms_rate_limits` (`key_id`, `window`, `requests`)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE `requests` = `requests` + 1",
            [$keyId, $windowStart]
        );

        // Read back the count to check against limit
        $count = Capsule::table('mod_sms_rate_limits')
            ->where('key_id', $keyId)
            ->where('window', $windowStart)
            ->value('requests');

        return $count <= $limit;
    }

    /**
     * Revoke an API key
     *
     * @param int $id
     * @param int $clientId For ownership verification
     * @return bool
     */
    public static function revoke(int $id, int $clientId): bool
    {
        $affected = Capsule::table('mod_sms_api_keys')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->update([
                'status' => 'revoked',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($affected > 0) {
            logActivity("SMS Suite: API key revoked - ID {$id}");
            return true;
        }

        return false;
    }

    /**
     * Get all keys for a client
     *
     * @param int $clientId
     * @return array
     */
    public static function getClientKeys(int $clientId): array
    {
        return Capsule::table('mod_sms_api_keys')
            ->where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'key_id' => $key->key_id,
                    'name' => $key->name,
                    'scopes' => json_decode($key->scopes, true) ?: [],
                    'rate_limit' => $key->rate_limit,
                    'status' => $key->status,
                    'last_used_at' => $key->last_used_at,
                    'expires_at' => $key->expires_at,
                    'created_at' => $key->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Check if an IP address is within a CIDR range
     *
     * @param string $ip
     * @param string $cidr e.g. "192.168.1.0/24"
     * @return bool
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $bits) = explode('/', $cidr, 2);
        $bits = (int)$bits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        return false;
    }
}
