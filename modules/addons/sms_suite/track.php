<?php
/**
 * SMS Suite - Link Tracking Endpoint
 *
 * Tracks clicks and redirects to original URL
 */

// Initialize WHMCS
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/lib/Campaigns/AdvancedCampaignService.php';

use SMSSuite\Campaigns\AdvancedCampaignService;

// Get short code
$shortCode = isset($_GET['c']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['c']) : null;

if (!$shortCode) {
    http_response_code(404);
    die('Link not found');
}

// Record click and get original URL
$originalUrl = AdvancedCampaignService::recordClick($shortCode, [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'phone' => $_GET['p'] ?? null, // Optional phone tracking
]);

if (!$originalUrl) {
    http_response_code(404);
    die('Link not found');
}

// Redirect to original URL
header('HTTP/1.1 302 Found');
header('Location: ' . $originalUrl);
exit;
