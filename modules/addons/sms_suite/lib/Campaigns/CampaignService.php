<?php
/**
 * SMS Suite - Campaign Service
 *
 * Handles bulk messaging campaigns with scheduling
 */

namespace SMSSuite\Campaigns;

use WHMCS\Database\Capsule;
use SMSSuite\Core\MessageService;
use SMSSuite\Contacts\ContactService;
use SMSSuite\Billing\BillingService;
use Exception;

class CampaignService
{
    /**
     * Campaign statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_QUEUED = 'queued';
    const STATUS_SENDING = 'sending';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Create a new campaign
     *
     * @param int $clientId
     * @param array $data
     * @return array
     */
    public static function create(int $clientId, array $data): array
    {
        // Validate required fields
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Campaign name is required'];
        }

        if (empty($data['message'])) {
            return ['success' => false, 'error' => 'Message content is required'];
        }

        try {
            $id = Capsule::table('mod_sms_campaigns')->insertGetId([
                'client_id' => $clientId,
                'name' => $data['name'],
                'channel' => $data['channel'] ?? 'sms',
                'message' => $data['message'],
                'sender_id' => $data['sender_id'] ?? null,
                'gateway_id' => $data['gateway_id'] ?? null,
                'recipient_type' => $data['recipient_type'] ?? 'manual', // manual, group, all
                'recipient_group_id' => $data['recipient_group_id'] ?? null,
                'recipient_list' => json_encode($data['recipients'] ?? []),
                'total_recipients' => 0,
                'sent_count' => 0,
                'delivered_count' => 0,
                'failed_count' => 0,
                'status' => self::STATUS_DRAFT,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'batch_size' => $data['batch_size'] ?? 100,
                'batch_delay' => $data['batch_delay'] ?? 1, // seconds between batches
                'started_at' => null,
                'completed_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update campaign
     *
     * @param int $id
     * @param int $clientId
     * @param array $data
     * @return array
     */
    public static function update(int $id, int $clientId, array $data): array
    {
        $campaign = Capsule::table('mod_sms_campaigns')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->first();

        if (!$campaign) {
            return ['success' => false, 'error' => 'Campaign not found'];
        }

        if (!in_array($campaign->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED])) {
            return ['success' => false, 'error' => 'Cannot edit campaign in current status'];
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        $allowedFields = ['name', 'message', 'channel', 'sender_id', 'gateway_id',
                          'recipient_type', 'recipient_group_id', 'scheduled_at',
                          'batch_size', 'batch_delay'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['recipients'])) {
            $updateData['recipient_list'] = json_encode($data['recipients']);
        }

        Capsule::table('mod_sms_campaigns')
            ->where('id', $id)
            ->update($updateData);

        return ['success' => true];
    }

    /**
     * Schedule a campaign
     *
     * @param int $id
     * @param int $clientId
     * @param string|null $scheduledAt
     * @return array
     */
    public static function schedule(int $id, int $clientId, ?string $scheduledAt = null): array
    {
        $campaign = Capsule::table('mod_sms_campaigns')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->first();

        if (!$campaign) {
            return ['success' => false, 'error' => 'Campaign not found'];
        }

        // Calculate recipients
        $recipients = self::resolveRecipients($campaign);

        if (empty($recipients)) {
            return ['success' => false, 'error' => 'No recipients found'];
        }

        // Check balance
        if ($campaign->client_id > 0) {
            require_once dirname(__DIR__) . '/Core/SegmentCounter.php';
            require_once dirname(__DIR__) . '/Billing/BillingService.php';

            $segmentResult = \SMSSuite\Core\SegmentCounter::count($campaign->message, $campaign->channel);
            $costPerMessage = BillingService::calculateCost(
                $campaign->client_id,
                $segmentResult->segments,
                $campaign->channel,
                $campaign->gateway_id
            );
            $totalCost = $costPerMessage * count($recipients);

            if (!BillingService::hasBalance($campaign->client_id, $totalCost)) {
                return ['success' => false, 'error' => 'Insufficient balance for campaign'];
            }
        }

        // Update campaign
        $status = $scheduledAt ? self::STATUS_SCHEDULED : self::STATUS_QUEUED;

        Capsule::table('mod_sms_campaigns')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'total_recipients' => count($recipients),
                'recipient_list' => json_encode($recipients),
                'scheduled_at' => $scheduledAt,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return ['success' => true, 'status' => $status, 'recipients' => count($recipients)];
    }

    /**
     * Process pending campaigns (called by cron)
     *
     * @return array
     */
    public static function processPending(): array
    {
        $processed = 0;

        // Find campaigns ready to process
        $campaigns = Capsule::table('mod_sms_campaigns')
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_SCHEDULED])
            ->where(function ($query) {
                $query->whereNull('scheduled_at')
                      ->orWhere('scheduled_at', '<=', date('Y-m-d H:i:s'));
            })
            ->limit(5)
            ->get();

        foreach ($campaigns as $campaign) {
            self::processCampaign($campaign->id);
            $processed++;
        }

        return ['processed' => $processed];
    }

    /**
     * Process a single campaign
     *
     * @param int $campaignId
     * @return array
     */
    public static function processCampaign(int $campaignId): array
    {
        $campaign = Capsule::table('mod_sms_campaigns')->where('id', $campaignId)->first();

        if (!$campaign) {
            return ['success' => false, 'error' => 'Campaign not found'];
        }

        // Update to sending
        Capsule::table('mod_sms_campaigns')
            ->where('id', $campaignId)
            ->update([
                'status' => self::STATUS_SENDING,
                'started_at' => $campaign->started_at ?? date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        require_once dirname(__DIR__) . '/Core/SegmentCounter.php';
        require_once dirname(__DIR__) . '/Core/MessageService.php';

        $recipients = json_decode($campaign->recipient_list, true) ?: [];
        $sentCount = $campaign->sent_count;
        $failedCount = $campaign->failed_count;

        $batchSize = $campaign->batch_size ?: 100;
        $batchDelay = $campaign->batch_delay ?: 1;

        // Get already processed message IDs
        $processedNumbers = Capsule::table('mod_sms_messages')
            ->where('campaign_id', $campaignId)
            ->pluck('to_number')
            ->toArray();

        $batch = 0;
        foreach ($recipients as $phone) {
            // Check if paused or cancelled
            $currentStatus = Capsule::table('mod_sms_campaigns')
                ->where('id', $campaignId)
                ->value('status');

            if (in_array($currentStatus, [self::STATUS_PAUSED, self::STATUS_CANCELLED])) {
                break;
            }

            // Skip already processed
            if (in_array($phone, $processedNumbers)) {
                continue;
            }

            // Get personalized message (handles A/B testing and template variables)
            $messageData = self::getCampaignMessage($campaign, $phone);

            // Send message
            $result = MessageService::send($campaign->client_id, $phone, $messageData['message'], [
                'channel' => $campaign->channel,
                'sender_id' => $messageData['sender_id'],
                'gateway_id' => $campaign->gateway_id,
                'campaign_id' => $campaignId,
                'send_now' => true,
            ]);

            if ($result['success']) {
                $sentCount++;

                // Update A/B test stats if variant was used
                if ($messageData['variant'] && $campaign->ab_testing) {
                    Capsule::table('mod_sms_campaign_ab_tests')
                        ->where('campaign_id', $campaignId)
                        ->where('variant', $messageData['variant'])
                        ->increment('sent_count');
                }
            } else {
                $failedCount++;
            }

            $batch++;

            // Update progress periodically
            if ($batch % 10 === 0) {
                Capsule::table('mod_sms_campaigns')
                    ->where('id', $campaignId)
                    ->update([
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            // Batch delay
            if ($batch >= $batchSize) {
                sleep($batchDelay);
                $batch = 0;
            }
        }

        // Final update
        $isComplete = ($sentCount + $failedCount) >= count($recipients);
        $finalStatus = $isComplete ? self::STATUS_COMPLETED : $currentStatus;

        Capsule::table('mod_sms_campaigns')
            ->where('id', $campaignId)
            ->update([
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'status' => $finalStatus,
                'completed_at' => $isComplete ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return [
            'success' => true,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'status' => $finalStatus,
        ];
    }

    /**
     * Pause a campaign
     *
     * @param int $id
     * @param int $clientId
     * @return bool
     */
    public static function pause(int $id, int $clientId): bool
    {
        return Capsule::table('mod_sms_campaigns')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->where('status', self::STATUS_SENDING)
            ->update([
                'status' => self::STATUS_PAUSED,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Resume a paused campaign
     *
     * @param int $id
     * @param int $clientId
     * @return bool
     */
    public static function resume(int $id, int $clientId): bool
    {
        return Capsule::table('mod_sms_campaigns')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->where('status', self::STATUS_PAUSED)
            ->update([
                'status' => self::STATUS_QUEUED,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Cancel a campaign
     *
     * @param int $id
     * @param int $clientId
     * @return bool
     */
    public static function cancel(int $id, int $clientId): bool
    {
        return Capsule::table('mod_sms_campaigns')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SCHEDULED, self::STATUS_QUEUED, self::STATUS_SENDING, self::STATUS_PAUSED])
            ->update([
                'status' => self::STATUS_CANCELLED,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Get campaign by ID
     *
     * @param int $id
     * @param int $clientId
     * @return object|null
     */
    public static function getCampaign(int $id, int $clientId): ?object
    {
        return Capsule::table('mod_sms_campaigns')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->first();
    }

    /**
     * Get campaigns for client
     *
     * @param int $clientId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getCampaigns(int $clientId, int $limit = 50, int $offset = 0): array
    {
        $query = Capsule::table('mod_sms_campaigns')
            ->where('client_id', $clientId);

        $total = $query->count();

        $campaigns = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        return [
            'campaigns' => $campaigns,
            'total' => $total,
        ];
    }

    /**
     * Resolve recipient list based on type
     *
     * @param object $campaign
     * @return array
     */
    private static function resolveRecipients(object $campaign): array
    {
        switch ($campaign->recipient_type) {
            case 'group':
                if ($campaign->recipient_group_id) {
                    require_once dirname(__DIR__) . '/Contacts/ContactService.php';
                    return ContactService::getGroupPhones($campaign->recipient_group_id, $campaign->client_id);
                }
                return [];

            case 'segment':
                if ($campaign->segment_id) {
                    require_once __DIR__ . '/AdvancedCampaignService.php';
                    return AdvancedCampaignService::getSegmentContacts($campaign->segment_id);
                }
                return [];

            case 'all':
                // All active contacts for client
                return Capsule::table('mod_sms_contacts')
                    ->where('client_id', $campaign->client_id)
                    ->where('status', 'subscribed')
                    ->pluck('phone')
                    ->toArray();

            case 'manual':
            default:
                return json_decode($campaign->recipient_list, true) ?: [];
        }
    }

    /**
     * Get message content for a campaign (handles A/B testing and personalization)
     *
     * @param object $campaign
     * @param string $phone
     * @return array ['message' => string, 'sender_id' => string, 'variant' => string|null]
     */
    public static function getCampaignMessage(object $campaign, string $phone): array
    {
        $message = $campaign->message;
        $senderId = $campaign->sender_id;
        $variant = null;

        // Check for A/B testing
        if ($campaign->ab_testing) {
            require_once __DIR__ . '/AdvancedCampaignService.php';
            $abVariant = AdvancedCampaignService::getABVariant($campaign->id);
            if ($abVariant) {
                $message = $abVariant->message;
                $senderId = $abVariant->sender_id ?: $senderId;
                $variant = $abVariant->variant;
            }
        }

        // Apply personalization
        require_once dirname(__DIR__) . '/Core/TemplateService.php';

        // Try to find contact for personalization
        $contact = Capsule::table('mod_sms_contacts')
            ->where('phone', $phone)
            ->where('client_id', $campaign->client_id)
            ->first();

        $templateData = [
            'client_id' => $campaign->client_id,
            'phone' => $phone,
            'campaign_id' => $campaign->id,
        ];

        if ($contact) {
            $templateData['contact_id'] = $contact->id;
            $templateData['first_name'] = $contact->first_name;
            $templateData['last_name'] = $contact->last_name;
            $templateData['email'] = $contact->email;

            // Add custom fields
            if (!empty($contact->custom_data)) {
                $customData = json_decode($contact->custom_data, true);
                if (is_array($customData)) {
                    $templateData = array_merge($templateData, $customData);
                }
            }
        }

        $message = \SMSSuite\Core\TemplateService::render($message, $templateData);

        // Apply link tracking if enabled
        if ($campaign->track_links) {
            require_once __DIR__ . '/AdvancedCampaignService.php';
            $message = AdvancedCampaignService::processMessageLinks($message, $campaign->id);
        }

        return [
            'message' => $message,
            'sender_id' => $senderId,
            'variant' => $variant,
        ];
    }

    /**
     * Update delivered count from DLR
     *
     * @param int $campaignId
     * @return void
     */
    public static function updateDeliveredCount(int $campaignId): void
    {
        $delivered = Capsule::table('mod_sms_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'delivered')
            ->count();

        Capsule::table('mod_sms_campaigns')
            ->where('id', $campaignId)
            ->update([
                'delivered_count' => $delivered,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
