<?php
/**
 * SMS Suite - Cron Worker
 *
 * This file should be called every minute via cron:
 * * * * * * php -q /path/to/whmcs/modules/addons/sms_suite/cron.php
 *
 * Or via WHMCS cron (add to configuration.php):
 * $whmcs_cron_extras[] = 'modules/addons/sms_suite/cron.php';
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('WHMCS_CRON')) {
    die('This script can only be run from command line or WHMCS cron.');
}

// Find WHMCS root
$whmcsRoot = dirname(dirname(dirname(__DIR__)));

// Load WHMCS
require_once $whmcsRoot . '/init.php';
require_once $whmcsRoot . '/includes/functions.php';

use WHMCS\Database\Capsule;

// Lock file to prevent concurrent runs
$lockFile = __DIR__ . '/cron.lock';

if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    // If lock is older than 10 minutes, assume stale and remove
    if (time() - $lockTime > 600) {
        unlink($lockFile);
    } else {
        echo "SMS Suite cron is already running.\n";
        exit(0);
    }
}

// Create lock
file_put_contents($lockFile, getmypid());

try {
    echo "SMS Suite Cron Started: " . date('Y-m-d H:i:s') . "\n";

    // Task 1: Process campaign queue
    processCampaignQueue();

    // Task 2: Process message queue
    processMessageQueue();

    // Task 3: Process scheduled messages
    processScheduledMessages();

    // Task 4: Process drip campaigns
    processDripCampaigns();

    // Task 5: Process recurring campaigns
    processRecurringCampaigns();

    // Task 6: Process pending webhooks
    processPendingWebhooks();

    // Task 7: Clean up rate limit counters (hourly)
    if (date('i') === '00') {
        cleanRateLimitCounters();
    }

    echo "SMS Suite Cron Completed: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    logActivity('SMS Suite Cron Error: ' . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Remove lock
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

/**
 * Process scheduled campaigns
 */
function processCampaignQueue()
{
    echo "Processing campaign queue...\n";

    // Update cron status
    Capsule::table('mod_sms_cron_status')
        ->where('task', 'campaign_processor')
        ->update([
            'last_run' => date('Y-m-d H:i:s'),
            'is_running' => true,
            'pid' => getmypid(),
        ]);

    try {
        require_once __DIR__ . '/lib/Campaigns/CampaignService.php';

        $result = \SMSSuite\Campaigns\CampaignService::processPending();
        echo "  Processed " . ($result['processed'] ?? 0) . " campaigns.\n";

    } catch (Exception $e) {
        echo "  Campaign processing error: " . $e->getMessage() . "\n";
    }

    // Mark not running
    Capsule::table('mod_sms_cron_status')
        ->where('task', 'campaign_processor')
        ->update([
            'is_running' => false,
            'pid' => null,
        ]);
}

/**
 * Process queued messages
 */
function processMessageQueue()
{
    echo "Processing message queue...\n";

    // Update cron status
    Capsule::table('mod_sms_cron_status')
        ->where('task', 'message_queue')
        ->update([
            'last_run' => date('Y-m-d H:i:s'),
            'is_running' => true,
            'pid' => getmypid(),
        ]);

    try {
        require_once __DIR__ . '/lib/Core/SegmentCounter.php';
        require_once __DIR__ . '/lib/Core/MessageService.php';

        // Get queued messages (not part of campaigns - those are handled separately)
        $messages = Capsule::table('mod_sms_messages')
            ->where('status', 'queued')
            ->whereNull('campaign_id')
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($messages as $message) {
            $result = \SMSSuite\Core\MessageService::processMessage($message->id);

            if (!empty($result['success'])) {
                $processed++;
            } else {
                $failed++;
            }
        }

        echo "  Processed {$processed} messages, {$failed} failed.\n";

    } catch (Exception $e) {
        echo "  Message processing error: " . $e->getMessage() . "\n";
    }

    // Mark not running
    Capsule::table('mod_sms_cron_status')
        ->where('task', 'message_queue')
        ->update([
            'is_running' => false,
            'pid' => null,
        ]);
}

/**
 * Process pending webhooks
 */
function processPendingWebhooks()
{
    echo "Processing pending webhooks...\n";

    // Update cron status
    Capsule::table('mod_sms_cron_status')
        ->where('task', 'dlr_processor')
        ->update([
            'last_run' => date('Y-m-d H:i:s'),
            'is_running' => true,
            'pid' => getmypid(),
        ]);

    try {
        // Get unprocessed webhooks
        $webhooks = Capsule::table('mod_sms_webhooks_inbox')
            ->where('processed', false)
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        $processed = 0;

        foreach ($webhooks as $webhook) {
            // Webhook processing will be implemented in Slice 9
            // For now, just mark as processed

            Capsule::table('mod_sms_webhooks_inbox')
                ->where('id', $webhook->id)
                ->update([
                    'processed' => true,
                ]);

            $processed++;
        }

        echo "  Processed {$processed} webhooks.\n";

    } catch (Exception $e) {
        echo "  Webhook processing error: " . $e->getMessage() . "\n";
    }

    // Mark not running
    Capsule::table('mod_sms_cron_status')
        ->where('task', 'dlr_processor')
        ->update([
            'is_running' => false,
            'pid' => null,
        ]);
}

/**
 * Process scheduled messages
 */
function processScheduledMessages()
{
    echo "Processing scheduled messages...\n";

    try {
        require_once __DIR__ . '/lib/Campaigns/AdvancedCampaignService.php';

        $result = \SMSSuite\Campaigns\AdvancedCampaignService::processScheduledMessages();
        echo "  Processed " . ($result['processed'] ?? 0) . " scheduled messages.\n";

    } catch (Exception $e) {
        echo "  Scheduled message processing error: " . $e->getMessage() . "\n";
    }
}

/**
 * Process drip campaigns
 */
function processDripCampaigns()
{
    echo "Processing drip campaigns...\n";

    try {
        require_once __DIR__ . '/lib/Campaigns/AdvancedCampaignService.php';

        $result = \SMSSuite\Campaigns\AdvancedCampaignService::processDripCampaigns();
        echo "  Processed " . ($result['processed'] ?? 0) . " drip campaign messages.\n";

    } catch (Exception $e) {
        echo "  Drip campaign processing error: " . $e->getMessage() . "\n";
    }
}

/**
 * Process recurring campaigns
 */
function processRecurringCampaigns()
{
    echo "Processing recurring campaigns...\n";

    try {
        require_once __DIR__ . '/lib/Campaigns/AdvancedCampaignService.php';

        $result = \SMSSuite\Campaigns\AdvancedCampaignService::processRecurringCampaigns();
        echo "  Processed " . ($result['processed'] ?? 0) . " recurring campaigns.\n";

    } catch (Exception $e) {
        echo "  Recurring campaign processing error: " . $e->getMessage() . "\n";
    }
}

/**
 * Clean up old rate limit counters
 */
function cleanRateLimitCounters()
{
    echo "Cleaning rate limit counters...\n";

    // Update cron status
    Capsule::table('mod_sms_cron_status')
        ->where('task', 'rate_limit_reset')
        ->update([
            'last_run' => date('Y-m-d H:i:s'),
        ]);

    try {
        // Delete counters older than 1 hour
        $cutoff = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $deleted = Capsule::table('mod_sms_api_rate_limits')
            ->where('window_start', '<', $cutoff)
            ->delete();

        echo "  Cleaned {$deleted} rate limit records.\n";

    } catch (Exception $e) {
        echo "  Rate limit cleanup error: " . $e->getMessage() . "\n";
    }
}
