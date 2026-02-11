<?php
/**
 * SMS Suite - REST API Entry Point
 *
 * Full documentation: docs/API.md
 *
 * Endpoints:
 *   Messaging:
 *     POST /smsapi.php?endpoint=send              - Send single message
 *     POST /smsapi.php?endpoint=send/bulk         - Send bulk messages
 *     POST /smsapi.php?endpoint=send/schedule     - Schedule message
 *     GET  /smsapi.php?endpoint=status            - Get message status
 *     GET  /smsapi.php?endpoint=messages          - Get message history
 *     GET  /smsapi.php?endpoint=segments          - Count segments (preview)
 *
 *   WhatsApp:
 *     POST /smsapi.php?endpoint=whatsapp/send     - Send WhatsApp text
 *     POST /smsapi.php?endpoint=whatsapp/template - Send WhatsApp template
 *     POST /smsapi.php?endpoint=whatsapp/media    - Send WhatsApp media
 *
 *   Contacts:
 *     GET  /smsapi.php?endpoint=contacts          - Get contacts
 *     POST /smsapi.php?endpoint=contacts          - Create contact
 *     GET  /smsapi.php?endpoint=contacts/groups   - Get contact groups
 *     POST /smsapi.php?endpoint=contacts/import   - Import contacts
 *
 *   Campaigns:
 *     GET  /smsapi.php?endpoint=campaigns         - List campaigns
 *     POST /smsapi.php?endpoint=campaigns         - Create campaign
 *     GET  /smsapi.php?endpoint=campaigns/status  - Get campaign status
 *     POST /smsapi.php?endpoint=campaigns/pause   - Pause campaign
 *     POST /smsapi.php?endpoint=campaigns/resume  - Resume campaign
 *     POST /smsapi.php?endpoint=campaigns/cancel  - Cancel campaign
 *
 *   Sender IDs:
 *     GET  /smsapi.php?endpoint=senderids         - List sender IDs
 *     POST /smsapi.php?endpoint=senderids/request - Request sender ID
 *
 *   Billing:
 *     GET  /smsapi.php?endpoint=balance           - Get wallet balance
 *     GET  /smsapi.php?endpoint=transactions      - Get transactions
 *     GET  /smsapi.php?endpoint=usage             - Get usage statistics
 *
 *   Templates:
 *     GET  /smsapi.php?endpoint=templates         - List templates
 *     POST /smsapi.php?endpoint=templates         - Create template
 *
 * Authentication (credentials must be sent via headers, NOT in URL):
 *   Headers: X-API-KEY, X-API-SECRET (recommended)
 *   OR Basic Auth: base64(key_id:secret)
 *
 * SECURITY: Query parameter authentication is NOT supported to prevent credential exposure in logs
 */

// Bootstrap WHMCS
$whmcsPath = dirname(__DIR__, 3);
require_once $whmcsPath . '/init.php';
require_once $whmcsPath . '/includes/api.php';

use WHMCS\Database\Capsule;

// Set JSON content type and security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Handle CORS for API clients — restrict to configured origins
$corsAllowed = false;
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowedOrigins = Capsule::table('mod_sms_settings')
        ->where('setting', 'api_cors_origins')
        ->value('value');

    if (!empty($allowedOrigins)) {
        $originList = array_map('trim', explode(',', $allowedOrigins));
        if (in_array($_SERVER['HTTP_ORIGIN'], $originList, true)) {
            header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            header('Access-Control-Allow-Credentials: true');
            $corsAllowed = true;
        }
    }
    // If no origins configured or origin not in list, no CORS headers are sent
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($corsAllowed) {
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: X-API-Key, X-API-Secret, Authorization, Content-Type');
        header('Access-Control-Max-Age: 86400');
    }
    exit(0);
}

// Check if module is active
$moduleActive = Capsule::table('tbladdonmodules')
    ->where('module', 'sms_suite')
    ->where('setting', 'status')
    ->where('value', 'Active')
    ->exists();

if (!$moduleActive) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 503,
            'message' => 'SMS Suite module is not active',
        ],
    ]);
    exit;
}

// Load API classes
require_once __DIR__ . '/lib/Api/ApiKeyService.php';
require_once __DIR__ . '/lib/Api/ApiController.php';

// Get request parameters
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? preg_replace('/[^a-zA-Z0-9\/_-]/', '', $_GET['endpoint']) : '';

// Parse JSON body for POST/PUT requests
$params = [];
if (in_array($method, ['POST', 'PUT'])) {
    // Enforce request body size limit (1MB)
    $body = file_get_contents('php://input');
    if (strlen($body) > 1048576) {
        http_response_code(413);
        echo json_encode([
            'success' => false,
            'error' => ['code' => 413, 'message' => 'Request body too large (max 1MB)'],
        ]);
        exit;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $params = $json;
        }
    } else {
        $params = $_POST;
    }
}

// Only merge safe GET params (exclude any credential-like params)
$safeGetParams = $_GET;
unset($safeGetParams['endpoint']);
unset($safeGetParams['api_key']);
unset($safeGetParams['api_secret']);
unset($safeGetParams['key']);
unset($safeGetParams['secret']);
unset($safeGetParams['token']);
$params = array_merge($safeGetParams, $params);

// Handle request
$controller = new \SMSSuite\Api\ApiController();
$response = $controller->handle($method, $endpoint, $params);

// Set HTTP status code
http_response_code($controller->getHttpCode());

// Output response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
