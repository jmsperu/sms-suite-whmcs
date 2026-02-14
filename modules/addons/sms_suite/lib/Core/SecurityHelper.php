<?php
/**
 * SMS Suite - Security Helper
 *
 * Provides security utilities including CSRF protection, input validation, and output escaping
 */

namespace SMSSuite\Core;

class SecurityHelper
{
    /**
     * CSRF token session key
     */
    const CSRF_TOKEN_KEY = 'sms_suite_csrf_token';
    const CSRF_TOKEN_TIME_KEY = 'sms_suite_csrf_time';
    const CSRF_TOKEN_LIFETIME = 3600; // 1 hour

    /**
     * Generate or retrieve CSRF token
     *
     * @return string
     */
    public static function getCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if token exists and is not expired
        if (isset($_SESSION[self::CSRF_TOKEN_KEY]) && isset($_SESSION[self::CSRF_TOKEN_TIME_KEY])) {
            if (time() - $_SESSION[self::CSRF_TOKEN_TIME_KEY] < self::CSRF_TOKEN_LIFETIME) {
                return $_SESSION[self::CSRF_TOKEN_KEY];
            }
        }

        // Generate new token
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_TOKEN_KEY] = $token;
        $_SESSION[self::CSRF_TOKEN_TIME_KEY] = time();

        return $token;
    }

    /**
     * Validate CSRF token from request
     *
     * @param string|null $token Token from form submission
     * @return bool
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::CSRF_TOKEN_KEY]) || !isset($_SESSION[self::CSRF_TOKEN_TIME_KEY])) {
            return false;
        }

        // Check expiry
        if (time() - $_SESSION[self::CSRF_TOKEN_TIME_KEY] > self::CSRF_TOKEN_LIFETIME) {
            unset($_SESSION[self::CSRF_TOKEN_KEY], $_SESSION[self::CSRF_TOKEN_TIME_KEY]);
            return false;
        }

        // Constant-time comparison to prevent timing attacks
        return hash_equals($_SESSION[self::CSRF_TOKEN_KEY], $token);
    }

    /**
     * Get CSRF token from POST request
     *
     * @return string|null
     */
    public static function getCsrfFromPost(): ?string
    {
        return $_POST['csrf_token'] ?? $_POST['_csrf'] ?? null;
    }

    /**
     * Verify CSRF token from current POST request
     *
     * @return bool
     */
    public static function verifyCsrfPost(): bool
    {
        return self::validateCsrfToken(self::getCsrfFromPost());
    }

    /**
     * Generate hidden CSRF input field for forms
     *
     * @return string HTML input element
     */
    public static function csrfField(): string
    {
        $token = self::getCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Escape string for HTML output
     *
     * @param mixed $value
     * @return string
     */
    public static function escape($value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape string for JavaScript context
     *
     * @param mixed $value
     * @return string
     */
    public static function escapeJs($value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * Escape string for URL context
     *
     * @param string $value
     * @return string
     */
    public static function escapeUrl(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * Validate phone number format
     *
     * @param string $phone
     * @return bool
     */
    public static function isValidPhone(string $phone): bool
    {
        // Remove common formatting characters
        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', $phone);
        // Must start with + or digit, contain only digits after that
        return preg_match('/^\+?[1-9]\d{6,14}$/', $cleaned) === 1;
    }

    /**
     * Sanitize a general string input (trim whitespace, strip tags)
     *
     * @param string $value
     * @return string
     */
    public static function sanitize(string $value): string
    {
        return trim(strip_tags($value));
    }

    /**
     * Sanitize phone number
     *
     * @param string $phone
     * @return string
     */
    public static function sanitizePhone(string $phone): string
    {
        // Keep only + and digits
        return preg_replace('/[^\+\d]/', '', $phone);
    }

    /**
     * Validate email format
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sanitize filename
     *
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts
        $filename = basename($filename);
        // Remove special characters
        $filename = preg_replace('/[^\w\-\.]/', '_', $filename);
        // Prevent hidden files
        $filename = ltrim($filename, '.');
        return $filename ?: 'file';
    }

    /**
     * Validate file upload for CSV
     *
     * @param array $file $_FILES array element
     * @param int $maxSize Maximum file size in bytes (default 5MB)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateCsvUpload(array $file, int $maxSize = 5242880): array
    {
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'File upload blocked by extension',
            ];
            return ['valid' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error'];
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds ' . round($maxSize / 1048576, 1) . 'MB limit'];
        }

        // Check extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) {
            return ['valid' => false, 'error' => 'Only CSV and TXT files are allowed'];
        }

        // Check MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'];
        if (!in_array($mimeType, $allowedMimes)) {
            return ['valid' => false, 'error' => 'Invalid file type detected'];
        }

        // Check for CSV injection in first 50 lines
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle) {
            for ($i = 0; $i < 50 && ($line = fgets($handle)) !== false; $i++) {
                if (preg_match('/^[\s]*[=+\-@\t\r]/', $line)) {
                    fclose($handle);
                    return ['valid' => false, 'error' => 'File contains potentially dangerous content'];
                }
            }
            fclose($handle);
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Sanitize CSV cell value to prevent formula injection
     *
     * @param string $value
     * @return string
     */
    public static function sanitizeCsvCell(string $value): string
    {
        $dangerousChars = ['=', '+', '-', '@', "\t", "\r"];
        $firstChar = substr($value, 0, 1);
        if (in_array($firstChar, $dangerousChars)) {
            return "'" . $value; // Prefix with apostrophe
        }
        return $value;
    }

    /**
     * Rate limit check using database
     *
     * @param string $key Unique identifier (e.g., client_id:action)
     * @param int $limit Maximum attempts
     * @param int $window Time window in seconds
     * @return bool True if within limit
     */
    public static function checkRateLimit(string $key, int $limit, int $window = 60): bool
    {
        // Convert string key to integer key_id via crc32
        $keyId = abs(crc32($key));
        // Use time-window bucketing (floor to nearest $window seconds)
        $windowBucket = (string)(intdiv(time(), $window) * $window);

        // Clean old windows (older than 2 periods)
        $oldWindow = (string)((intdiv(time(), $window) - 2) * $window);
        \WHMCS\Database\Capsule::table('mod_sms_rate_limits')
            ->where('window', '<', $oldWindow)
            ->delete();

        // Atomic upsert: insert or increment
        try {
            \WHMCS\Database\Capsule::statement(
                "INSERT INTO `mod_sms_rate_limits` (`key_id`, `window`, `requests`)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE `requests` = `requests` + 1",
                [$keyId, $windowBucket]
            );
        } catch (\Exception $e) {
            // Fail closed: if the statement fails, deny the request
            return false;
        }

        // Check current count
        $count = \WHMCS\Database\Capsule::table('mod_sms_rate_limits')
            ->where('key_id', $keyId)
            ->where('window', $windowBucket)
            ->value('requests');

        return ($count ?? 0) <= $limit;
    }

    /**
     * Validate webhook signature (HMAC)
     *
     * @param string $payload Raw request body
     * @param string $signature Provided signature
     * @param string $secret Secret key
     * @param string $algorithm Hash algorithm (default sha256)
     * @return bool
     */
    public static function validateWebhookSignature(string $payload, string $signature, string $secret, string $algorithm = 'sha256'): bool
    {
        $expected = hash_hmac($algorithm, $payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Log security event
     *
     * @param string $event Event type
     * @param array $context Additional context
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // Remove sensitive data from context
        unset($context['password'], $context['secret'], $context['api_secret'], $context['credentials']);

        $message = sprintf(
            'SMS Suite Security: %s | IP: %s | UA: %s | Context: %s',
            $event,
            $ip,
            substr($userAgent, 0, 100),
            json_encode($context)
        );

        logActivity($message);
    }
}
