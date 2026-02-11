<?php
/**
 * SMS Suite - Verification Service
 *
 * Handles SMS-based verification for clients, orders, and two-factor authentication
 */

namespace SMSSuite\Core;

use WHMCS\Database\Capsule;
use Exception;

class VerificationService
{
    /**
     * Default token length
     */
    const DEFAULT_TOKEN_LENGTH = 6;

    /**
     * Default token expiry (minutes)
     */
    const DEFAULT_TOKEN_EXPIRY = 10;

    /**
     * Token types
     */
    const TYPE_CLIENT_VERIFICATION = 'client_verification';
    const TYPE_ORDER_VERIFICATION = 'order_verification';
    const TYPE_TWO_FACTOR = 'two_factor';
    const TYPE_PASSWORD_RESET = 'password_reset';
    const TYPE_PHONE_VERIFICATION = 'phone_verification';

    /**
     * Generate and send verification token
     */
    public static function sendVerificationToken(
        string $phone,
        string $type,
        int $relatedId = 0,
        array $options = []
    ): array {
        $phone = self::normalizePhone($phone);

        if (empty($phone)) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        // Check rate limit
        if (!self::checkRateLimit($phone, $type)) {
            return ['success' => false, 'error' => 'Too many requests. Please wait before requesting another code.'];
        }

        // Generate token
        $tokenLength = $options['token_length'] ?? self::getSettingValue('token_length', self::DEFAULT_TOKEN_LENGTH);
        $tokenChars = $options['token_chars'] ?? self::getSettingValue('token_chars', 'numeric');
        $token = self::generateToken($tokenLength, $tokenChars);

        // Token expiry
        $expiryMinutes = $options['expiry_minutes'] ?? self::getSettingValue('token_expiry', self::DEFAULT_TOKEN_EXPIRY);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));

        // Store token
        Capsule::table('mod_sms_verification_tokens')->insert([
            'phone' => $phone,
            'token' => password_hash($token, PASSWORD_DEFAULT),
            'type' => $type,
            'related_id' => $relatedId,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'verified' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Get message template
        $message = self::getVerificationMessage($type, $token, $options);

        // Send SMS
        $result = MessageService::sendDirect($phone, $message);

        if (!$result['success']) {
            return ['success' => false, 'error' => 'Failed to send verification SMS'];
        }

        // Log the request
        self::logVerificationRequest($phone, $type, $relatedId);

        return [
            'success' => true,
            'expires_at' => $expiresAt,
            'message' => 'Verification code sent to your phone',
        ];
    }

    /**
     * Verify a token
     */
    public static function verifyToken(string $phone, string $token, string $type): array
    {
        $phone = self::normalizePhone($phone);

        if (empty($phone) || empty($token)) {
            return ['success' => false, 'error' => 'Phone and token are required'];
        }

        // Get the latest unexpired, unverified token
        $storedToken = Capsule::table('mod_sms_verification_tokens')
            ->where('phone', $phone)
            ->where('type', $type)
            ->where('verified', 0)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$storedToken) {
            return ['success' => false, 'error' => 'Token expired or not found'];
        }

        // Check max attempts
        $maxAttempts = self::getSettingValue('max_verification_attempts', 5);
        if ($storedToken->attempts >= $maxAttempts) {
            return ['success' => false, 'error' => 'Too many failed attempts. Please request a new code.'];
        }

        // Increment attempts
        Capsule::table('mod_sms_verification_tokens')
            ->where('id', $storedToken->id)
            ->increment('attempts');

        // Verify token
        if (!password_verify($token, $storedToken->token)) {
            return ['success' => false, 'error' => 'Invalid verification code'];
        }

        // Mark as verified
        Capsule::table('mod_sms_verification_tokens')
            ->where('id', $storedToken->id)
            ->update([
                'verified' => 1,
                'verified_at' => date('Y-m-d H:i:s'),
            ]);

        // Handle post-verification actions
        self::handleVerificationSuccess($storedToken->type, $storedToken->related_id, $phone);

        return [
            'success' => true,
            'type' => $storedToken->type,
            'related_id' => $storedToken->related_id,
            'message' => 'Verification successful',
        ];
    }

    /**
     * Send client verification SMS
     */
    public static function sendClientVerification(int $clientId): array
    {
        $client = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->first();

        if (!$client) {
            return ['success' => false, 'error' => 'Client not found'];
        }

        $phone = NotificationService::getClientPhone($client);
        if (empty($phone)) {
            return ['success' => false, 'error' => 'No phone number on file'];
        }

        return self::sendVerificationToken($phone, self::TYPE_CLIENT_VERIFICATION, $clientId, [
            'client_name' => $client->firstname,
        ]);
    }

    /**
     * Send order verification SMS
     */
    public static function sendOrderVerification(int $orderId): array
    {
        $order = Capsule::table('tblorders')
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $client = Capsule::table('tblclients')
            ->where('id', $order->userid)
            ->first();

        if (!$client) {
            return ['success' => false, 'error' => 'Client not found'];
        }

        $phone = NotificationService::getClientPhone($client);
        if (empty($phone)) {
            return ['success' => false, 'error' => 'No phone number on file'];
        }

        return self::sendVerificationToken($phone, self::TYPE_ORDER_VERIFICATION, $orderId, [
            'order_number' => $order->ordernum,
        ]);
    }

    /**
     * Send two-factor authentication token
     */
    public static function sendTwoFactorToken(int $userId, string $userType = 'client'): array
    {
        if ($userType === 'admin') {
            $user = Capsule::table('tbladmins')->where('id', $userId)->first();
            $phone = NotificationService::getAdminPhone($userId);
        } else {
            $user = Capsule::table('tblclients')->where('id', $userId)->first();
            $phone = NotificationService::getClientPhone($user);
        }

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if (empty($phone)) {
            return ['success' => false, 'error' => 'No phone number configured for 2FA'];
        }

        $relatedId = ($userType === 'admin' ? -1 : 1) * $userId; // Negative for admin

        return self::sendVerificationToken($phone, self::TYPE_TWO_FACTOR, $relatedId);
    }

    /**
     * Verify client account
     */
    public static function verifyClient(int $clientId, string $token): array
    {
        $client = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->first();

        if (!$client) {
            return ['success' => false, 'error' => 'Client not found'];
        }

        $phone = NotificationService::getClientPhone($client);

        $result = self::verifyToken($phone, $token, self::TYPE_CLIENT_VERIFICATION);

        if ($result['success']) {
            // Update client verification status
            Capsule::table('mod_sms_client_verification')
                ->updateOrInsert(
                    ['client_id' => $clientId],
                    [
                        'phone_verified' => 1,
                        'verified_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );
        }

        return $result;
    }

    /**
     * Verify order
     */
    public static function verifyOrder(int $orderId, string $token): array
    {
        $order = Capsule::table('tblorders')
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $client = Capsule::table('tblclients')
            ->where('id', $order->userid)
            ->first();

        if (!$client) {
            return ['success' => false, 'error' => 'Client not found'];
        }

        $phone = NotificationService::getClientPhone($client);

        $result = self::verifyToken($phone, $token, self::TYPE_ORDER_VERIFICATION);

        if ($result['success']) {
            // Update order verification status
            Capsule::table('mod_sms_order_verification')
                ->updateOrInsert(
                    ['order_id' => $orderId],
                    [
                        'verified' => 1,
                        'verified_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );

            // Optionally accept the order
            $autoAccept = self::getSettingValue('auto_accept_verified_orders', false);
            if ($autoAccept && $order->status === 'Pending') {
                Capsule::table('tblorders')
                    ->where('id', $orderId)
                    ->update(['status' => 'Active']);
            }
        }

        return $result;
    }

    /**
     * Check if client is verified
     */
    public static function isClientVerified(int $clientId): bool
    {
        return Capsule::table('mod_sms_client_verification')
            ->where('client_id', $clientId)
            ->where('phone_verified', 1)
            ->exists();
    }

    /**
     * Check if order is verified
     */
    public static function isOrderVerified(int $orderId): bool
    {
        return Capsule::table('mod_sms_order_verification')
            ->where('order_id', $orderId)
            ->where('verified', 1)
            ->exists();
    }

    /**
     * Get verification status for client
     */
    public static function getClientVerificationStatus(int $clientId): array
    {
        $status = Capsule::table('mod_sms_client_verification')
            ->where('client_id', $clientId)
            ->first();

        if (!$status) {
            return [
                'verified' => false,
                'pending' => false,
            ];
        }

        return [
            'verified' => (bool)$status->phone_verified,
            'verified_at' => $status->verified_at,
        ];
    }

    /**
     * Generate a random token
     */
    private static function generateToken(int $length, string $type = 'numeric'): string
    {
        switch ($type) {
            case 'alpha':
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excluded I, O for clarity
                break;
            case 'alphanumeric':
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                break;
            case 'numeric':
            default:
                $chars = '0123456789';
                break;
        }

        $token = '';
        $charLen = strlen($chars);

        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[random_int(0, $charLen - 1)];
        }

        return $token;
    }

    /**
     * Get verification message
     */
    private static function getVerificationMessage(string $type, string $token, array $options = []): string
    {
        $companyName = self::getSettingValue('company_name', '');
        if (empty($companyName)) {
            $companyName = Capsule::table('tblconfiguration')
                ->where('setting', 'CompanyName')
                ->value('value') ?? 'Our Service';
        }

        $templates = [
            self::TYPE_CLIENT_VERIFICATION => "Your {$companyName} verification code is: {$token}. Valid for 10 minutes.",
            self::TYPE_ORDER_VERIFICATION => "Your order verification code is: {$token}. Enter this code to confirm your order.",
            self::TYPE_TWO_FACTOR => "Your {$companyName} login code is: {$token}. Do not share this code.",
            self::TYPE_PASSWORD_RESET => "Your password reset code is: {$token}. If you didn't request this, ignore this message.",
            self::TYPE_PHONE_VERIFICATION => "Your phone verification code is: {$token}",
        ];

        // Check for custom template
        $customTemplate = Capsule::table('mod_sms_verification_templates')
            ->where('type', $type)
            ->where('status', 'active')
            ->value('message');

        if ($customTemplate) {
            $message = str_replace('{token}', $token, $customTemplate);
            $message = str_replace('{company_name}', $companyName, $message);
        } else {
            $message = $templates[$type] ?? "Your verification code is: {$token}";
        }

        return $message;
    }

    /**
     * Check rate limit for verification requests
     */
    private static function checkRateLimit(string $phone, string $type): bool
    {
        $limitPeriod = 60; // seconds
        $maxRequests = 3;

        $recentCount = Capsule::table('mod_sms_verification_tokens')
            ->where('phone', $phone)
            ->where('type', $type)
            ->where('created_at', '>', date('Y-m-d H:i:s', strtotime("-{$limitPeriod} seconds")))
            ->count();

        return $recentCount < $maxRequests;
    }

    /**
     * Log verification request
     */
    private static function logVerificationRequest(string $phone, string $type, int $relatedId): void
    {
        Capsule::table('mod_sms_verification_logs')->insert([
            'phone' => substr($phone, 0, -4) . '****', // Mask phone
            'type' => $type,
            'related_id' => $relatedId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle successful verification
     */
    private static function handleVerificationSuccess(string $type, int $relatedId, string $phone): void
    {
        // Invalidate other tokens for same phone/type
        Capsule::table('mod_sms_verification_tokens')
            ->where('phone', $phone)
            ->where('type', $type)
            ->where('verified', 0)
            ->update(['expires_at' => date('Y-m-d H:i:s')]);

        // Log successful verification
        logActivity("SMS Verification successful: {$type} for ID {$relatedId}");
    }

    /**
     * Normalize phone number — delegates to MessageService for consistent WHMCS format handling
     */
    private static function normalizePhone(string $phone): string
    {
        require_once __DIR__ . '/MessageService.php';
        return MessageService::normalizePhone($phone);
    }

    /**
     * Get setting value
     */
    private static function getSettingValue(string $key, $default = null)
    {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', $key)
            ->value('value');

        return $value ?? $default;
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpiredTokens(): int
    {
        return Capsule::table('mod_sms_verification_tokens')
            ->where('expires_at', '<', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->delete();
    }

    /**
     * Resend verification token
     */
    public static function resendToken(string $phone, string $type, int $relatedId = 0): array
    {
        return self::sendVerificationToken($phone, $type, $relatedId);
    }
}
