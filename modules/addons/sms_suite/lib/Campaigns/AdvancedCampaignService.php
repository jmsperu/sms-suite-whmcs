<?php
/**
 * SMS Suite - Advanced Campaign Service
 *
 * Handles A/B testing, drip campaigns, recurring campaigns, link tracking, and segmentation
 */

namespace SMSSuite\Campaigns;

use WHMCS\Database\Capsule;
use SMSSuite\Core\MessageService;
use SMSSuite\Core\TemplateService;
use Exception;

class AdvancedCampaignService
{
    // ==================== A/B Testing ====================

    /**
     * Create A/B test variants for a campaign
     *
     * @param int $campaignId
     * @param array $variants Array of variant data with message, sender_id, percentage
     * @return array
     */
    public static function createABTest(int $campaignId, array $variants): array
    {
        try {
            // Validate percentages sum to 100
            $totalPercentage = array_sum(array_column($variants, 'percentage'));
            if ($totalPercentage !== 100) {
                return ['success' => false, 'error' => 'Variant percentages must sum to 100'];
            }

            // Enable A/B testing on campaign
            Capsule::table('mod_sms_campaigns')
                ->where('id', $campaignId)
                ->update(['ab_testing' => true, 'updated_at' => date('Y-m-d H:i:s')]);

            // Create variants
            $variantLetters = ['A', 'B', 'C', 'D'];
            $createdVariants = [];

            foreach ($variants as $i => $variant) {
                if ($i >= 4) break; // Max 4 variants

                $id = Capsule::table('mod_sms_campaign_ab_tests')->insertGetId([
                    'campaign_id' => $campaignId,
                    'variant' => $variantLetters[$i],
                    'message' => $variant['message'],
                    'sender_id' => $variant['sender_id'] ?? null,
                    'percentage' => $variant['percentage'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $createdVariants[] = ['id' => $id, 'variant' => $variantLetters[$i]];
            }

            return ['success' => true, 'variants' => $createdVariants];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get A/B test variant for sending (random based on percentages)
     */
    public static function getABVariant(int $campaignId): ?object
    {
        $variants = Capsule::table('mod_sms_campaign_ab_tests')
            ->where('campaign_id', $campaignId)
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        // Random selection based on percentages
        $rand = mt_rand(1, 100);
        $cumulative = 0;

        foreach ($variants as $variant) {
            $cumulative += $variant->percentage;
            if ($rand <= $cumulative) {
                return $variant;
            }
        }

        return $variants->last();
    }

    /**
     * Get A/B test results
     */
    public static function getABTestResults(int $campaignId): array
    {
        $variants = Capsule::table('mod_sms_campaign_ab_tests')
            ->where('campaign_id', $campaignId)
            ->get();

        $results = [];
        foreach ($variants as $variant) {
            $deliveryRate = $variant->sent_count > 0
                ? round(($variant->delivered_count / $variant->sent_count) * 100, 2)
                : 0;

            $clickRate = $variant->sent_count > 0
                ? round(($variant->clicked_count / $variant->sent_count) * 100, 2)
                : 0;

            $results[] = [
                'variant' => $variant->variant,
                'message' => substr($variant->message, 0, 50) . '...',
                'sent' => $variant->sent_count,
                'delivered' => $variant->delivered_count,
                'clicked' => $variant->clicked_count,
                'delivery_rate' => $deliveryRate,
                'click_rate' => $clickRate,
            ];
        }

        // Determine winner
        $winner = null;
        $highestScore = 0;
        foreach ($results as $result) {
            // Score = delivery rate * click rate weight
            $score = $result['delivery_rate'] + ($result['click_rate'] * 2);
            if ($score > $highestScore) {
                $highestScore = $score;
                $winner = $result['variant'];
            }
        }

        return [
            'variants' => $results,
            'winner' => $winner,
        ];
    }

    // ==================== Drip Campaigns / Sequences ====================

    /**
     * Create a drip campaign
     */
    public static function createDripCampaign(int $clientId, array $data): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Name is required'];
        }

        try {
            $id = Capsule::table('mod_sms_drip_campaigns')->insertGetId([
                'client_id' => $clientId,
                'name' => $data['name'],
                'channel' => $data['channel'] ?? 'sms',
                'trigger_group_id' => $data['trigger_group_id'] ?? null,
                'trigger_type' => $data['trigger_type'] ?? 'group_join',
                'status' => 'draft',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add step to drip campaign
     */
    public static function addDripStep(int $dripCampaignId, array $data): array
    {
        if (empty($data['message'])) {
            return ['success' => false, 'error' => 'Message is required'];
        }

        try {
            // Get next order
            $maxOrder = Capsule::table('mod_sms_drip_steps')
                ->where('drip_campaign_id', $dripCampaignId)
                ->max('step_order') ?? 0;

            $id = Capsule::table('mod_sms_drip_steps')->insertGetId([
                'drip_campaign_id' => $dripCampaignId,
                'step_order' => $maxOrder + 1,
                'message' => $data['message'],
                'delay_value' => $data['delay_value'] ?? 1,
                'delay_unit' => $data['delay_unit'] ?? 'day',
                'sender_id' => $data['sender_id'] ?? null,
                'gateway_id' => $data['gateway_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id, 'step_order' => $maxOrder + 1];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Subscribe contact to drip campaign
     */
    public static function subscribeToDrip(int $dripCampaignId, int $contactId): array
    {
        try {
            // Check if already subscribed
            $exists = Capsule::table('mod_sms_drip_subscribers')
                ->where('drip_campaign_id', $dripCampaignId)
                ->where('contact_id', $contactId)
                ->exists();

            if ($exists) {
                return ['success' => false, 'error' => 'Contact already subscribed'];
            }

            // Get contact phone (required for drip subscribers)
            $contact = Capsule::table('mod_sms_contacts')->where('id', $contactId)->first();
            if (!$contact || empty($contact->phone)) {
                return ['success' => false, 'error' => 'Contact has no phone number'];
            }

            // Get first step delay
            $firstStep = Capsule::table('mod_sms_drip_steps')
                ->where('drip_campaign_id', $dripCampaignId)
                ->orderBy('step_order')
                ->first();

            $nextSendAt = self::calculateNextSendTime($firstStep);

            $id = Capsule::table('mod_sms_drip_subscribers')->insertGetId([
                'drip_campaign_id' => $dripCampaignId,
                'contact_id' => $contactId,
                'phone' => $contact->phone,
                'current_step' => 0,
                'next_send_at' => $nextSendAt,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process drip campaign messages (called by cron)
     */
    public static function processDripCampaigns(): array
    {
        $processed = 0;
        $now = date('Y-m-d H:i:s');

        // Get subscribers ready for next message
        $subscribers = Capsule::table('mod_sms_drip_subscribers as ds')
            ->join('mod_sms_drip_campaigns as dc', 'ds.drip_campaign_id', '=', 'dc.id')
            ->where('ds.status', 'active')
            ->where('ds.next_send_at', '<=', $now)
            ->where('dc.status', 'active')
            ->select('ds.*', 'dc.channel', 'dc.client_id')
            ->limit(100)
            ->get();

        foreach ($subscribers as $subscriber) {
            // Get next step
            $step = Capsule::table('mod_sms_drip_steps')
                ->where('drip_campaign_id', $subscriber->drip_campaign_id)
                ->where('step_order', $subscriber->current_step + 1)
                ->first();

            if (!$step) {
                // Campaign complete for this subscriber
                Capsule::table('mod_sms_drip_subscribers')
                    ->where('id', $subscriber->id)
                    ->update([
                        'status' => 'completed',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                continue;
            }

            // Get contact
            $contact = Capsule::table('mod_sms_contacts')
                ->where('id', $subscriber->contact_id)
                ->first();

            if (!$contact || $contact->status !== 'subscribed') {
                Capsule::table('mod_sms_drip_subscribers')
                    ->where('id', $subscriber->id)
                    ->update(['status' => 'unsubscribed', 'updated_at' => date('Y-m-d H:i:s')]);
                continue;
            }

            // Render message with personalization
            require_once dirname(__DIR__) . '/Core/TemplateService.php';
            $message = TemplateService::render($step->message, [
                'contact_id' => $contact->id,
                'client_id' => $subscriber->client_id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'phone' => $contact->phone,
            ]);

            // Send message
            require_once dirname(__DIR__) . '/Core/MessageService.php';
            $result = MessageService::send($subscriber->client_id, $contact->phone, $message, [
                'channel' => $subscriber->channel,
                'sender_id' => $step->sender_id,
                'gateway_id' => $step->gateway_id,
                'send_now' => true,
            ]);

            // Calculate next step time
            $nextStep = Capsule::table('mod_sms_drip_steps')
                ->where('drip_campaign_id', $subscriber->drip_campaign_id)
                ->where('step_order', $step->step_order + 1)
                ->first();

            $nextSendAt = $nextStep ? self::calculateNextSendTime($nextStep) : null;

            // Update subscriber
            Capsule::table('mod_sms_drip_subscribers')
                ->where('id', $subscriber->id)
                ->update([
                    'current_step' => $step->step_order,
                    'next_send_at' => $nextSendAt,
                    'status' => $nextStep ? 'active' : 'completed',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $processed++;
        }

        return ['processed' => $processed];
    }

    /**
     * Calculate next send time based on delay
     */
    private static function calculateNextSendTime(?object $step): ?string
    {
        if (!$step) return null;

        $value = $step->delay_value;
        $unit = $step->delay_unit;

        $multiplier = [
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            'week' => 604800,
        ][$unit] ?? 86400;

        return date('Y-m-d H:i:s', time() + ($value * $multiplier));
    }

    // ==================== Recurring Campaigns ====================

    /**
     * Process recurring campaigns (called by cron)
     */
    public static function processRecurringCampaigns(): array
    {
        $processed = 0;
        $now = date('Y-m-d H:i:s');

        // Find recurring campaigns due for execution
        $campaigns = Capsule::table('mod_sms_campaigns')
            ->where('schedule_type', 'recurring')
            ->where('status', 'scheduled')
            ->where('schedule_time', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('recurring_end')
                  ->orWhere('recurring_end', '>=', $now);
            })
            ->limit(10)
            ->get();

        foreach ($campaigns as $campaign) {
            // Log this run
            $runNumber = Capsule::table('mod_sms_recurring_log')
                ->where('campaign_id', $campaign->id)
                ->max('run_number') ?? 0;

            $logId = Capsule::table('mod_sms_recurring_log')->insertGetId([
                'campaign_id' => $campaign->id,
                'run_number' => $runNumber + 1,
                'status' => 'running',
                'started_at' => date('Y-m-d H:i:s'),
            ]);

            // Process the campaign
            $result = CampaignService::processCampaign($campaign->id);

            // Update log
            Capsule::table('mod_sms_recurring_log')
                ->where('id', $logId)
                ->update([
                    'sent_count' => $result['sent'] ?? 0,
                    'failed_count' => $result['failed'] ?? 0,
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);

            // Calculate next run time
            $nextRun = self::calculateNextRecurringRun($campaign);

            if ($nextRun && (!$campaign->recurring_end || $nextRun <= $campaign->recurring_end)) {
                // Reset for next run
                Capsule::table('mod_sms_campaigns')
                    ->where('id', $campaign->id)
                    ->update([
                        'schedule_time' => $nextRun,
                        'status' => 'scheduled',
                        'sent_count' => 0,
                        'failed_count' => 0,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                // Recurring complete
                Capsule::table('mod_sms_campaigns')
                    ->where('id', $campaign->id)
                    ->update([
                        'status' => 'completed',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            $processed++;
        }

        return ['processed' => $processed];
    }

    /**
     * Calculate next recurring run time
     */
    private static function calculateNextRecurringRun(object $campaign): ?string
    {
        if (!$campaign->frequency_amount || !$campaign->frequency_unit) {
            return null;
        }

        $amount = $campaign->frequency_amount;
        $unit = $campaign->frequency_unit;

        $intervalMap = [
            'minute' => 'minutes',
            'hour' => 'hours',
            'day' => 'days',
            'week' => 'weeks',
            'month' => 'months',
        ];

        $interval = $intervalMap[$unit] ?? 'days';

        return date('Y-m-d H:i:s', strtotime("+{$amount} {$interval}"));
    }

    // ==================== Link Tracking ====================

    /**
     * Create a trackable short link
     */
    public static function createTrackingLink(string $originalUrl, ?int $campaignId = null, ?int $messageId = null): string
    {
        // Generate unique short code
        $shortCode = self::generateShortCode();

        Capsule::table('mod_sms_tracking_links')->insert([
            'campaign_id' => $campaignId,
            'message_id' => $messageId,
            'short_code' => $shortCode,
            'original_url' => $originalUrl,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Get system URL from WHMCS config
        $systemUrl = Capsule::table('tblconfiguration')
            ->where('setting', 'SystemURL')
            ->value('value');

        return rtrim($systemUrl, '/') . '/modules/addons/sms_suite/track.php?c=' . $shortCode;
    }

    /**
     * Generate unique short code
     */
    private static function generateShortCode(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = Capsule::table('mod_sms_tracking_links')
                ->where('short_code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    /**
     * Process link with tracking (replace URLs in message)
     */
    public static function processMessageLinks(string $message, ?int $campaignId = null, ?int $messageId = null): string
    {
        // Find URLs in message
        $urlPattern = '/(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/i';

        return preg_replace_callback($urlPattern, function ($matches) use ($campaignId, $messageId) {
            return self::createTrackingLink($matches[1], $campaignId, $messageId);
        }, $message);
    }

    /**
     * Record link click
     */
    public static function recordClick(string $shortCode, array $data = []): ?string
    {
        $link = Capsule::table('mod_sms_tracking_links')
            ->where('short_code', $shortCode)
            ->first();

        if (!$link) {
            return null;
        }

        // Increment click count
        Capsule::table('mod_sms_tracking_links')
            ->where('id', $link->id)
            ->increment('click_count');

        // Log click details
        Capsule::table('mod_sms_link_clicks')->insert([
            'link_id' => $link->id,
            'phone' => $data['phone'] ?? null,
            'ip_address' => $data['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            'country' => $data['country'] ?? null,
            'device' => self::detectDevice($data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? ''),
            'clicked_at' => date('Y-m-d H:i:s'),
        ]);

        // Update A/B test if applicable
        if ($link->campaign_id) {
            $message = Capsule::table('mod_sms_messages')
                ->where('id', $link->message_id)
                ->first();

            if ($message && $message->ab_variant) {
                Capsule::table('mod_sms_campaign_ab_tests')
                    ->where('campaign_id', $link->campaign_id)
                    ->where('variant', $message->ab_variant)
                    ->increment('clicked_count');
            }
        }

        return $link->original_url;
    }

    /**
     * Detect device type from user agent
     */
    private static function detectDevice(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'mobile') !== false) return 'mobile';
        if (strpos($userAgent, 'android') !== false) return 'mobile';
        if (strpos($userAgent, 'iphone') !== false) return 'mobile';
        if (strpos($userAgent, 'ipad') !== false) return 'tablet';
        if (strpos($userAgent, 'tablet') !== false) return 'tablet';

        return 'desktop';
    }

    /**
     * Get link tracking stats
     */
    public static function getLinkStats(?int $campaignId = null): array
    {
        $query = Capsule::table('mod_sms_tracking_links');

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $links = $query->get();

        $totalClicks = 0;
        $uniqueClicks = 0;
        $linkStats = [];

        foreach ($links as $link) {
            $totalClicks += $link->click_count;

            $unique = Capsule::table('mod_sms_link_clicks')
                ->where('link_id', $link->id)
                ->distinct('ip_address')
                ->count('ip_address');

            $uniqueClicks += $unique;

            $linkStats[] = [
                'url' => $link->original_url,
                'short_code' => $link->short_code,
                'total_clicks' => $link->click_count,
                'unique_clicks' => $unique,
            ];
        }

        return [
            'total_clicks' => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'links' => $linkStats,
        ];
    }

    // ==================== Contact Segmentation ====================

    /**
     * Create a segment
     */
    public static function createSegment(int $clientId, array $data): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Name is required'];
        }

        try {
            $id = Capsule::table('mod_sms_segments')->insertGetId([
                'client_id' => $clientId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'conditions' => json_encode($data['conditions'] ?? []),
                'match_type' => $data['match_type'] ?? 'all',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Add conditions
            if (!empty($data['conditions'])) {
                foreach ($data['conditions'] as $condition) {
                    Capsule::table('mod_sms_segment_conditions')->insert([
                        'segment_id' => $id,
                        'field' => $condition['field'],
                        'operator' => $condition['operator'],
                        'value' => $condition['value'] ?? null,
                        'logic' => $condition['logic'] ?? 'AND',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Calculate initial count
            self::calculateSegmentCount($id, $clientId);

            return ['success' => true, 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get contacts matching segment criteria
     */
    public static function getSegmentContacts(int $segmentId, ?int $limit = null): array
    {
        $segment = Capsule::table('mod_sms_segments')->where('id', $segmentId)->first();
        if (!$segment) {
            return [];
        }

        $conditions = Capsule::table('mod_sms_segment_conditions')
            ->where('segment_id', $segmentId)
            ->get();

        $query = Capsule::table('mod_sms_contacts')
            ->where('client_id', $segment->client_id)
            ->whereIn('status', ['active', 'subscribed']);

        if ($segment->match_type === 'all') {
            // All conditions must match (AND)
            foreach ($conditions as $condition) {
                $query = self::applyCondition($query, $condition);
            }
        } else {
            // Any condition matches (OR)
            $query->where(function ($q) use ($conditions) {
                foreach ($conditions as $i => $condition) {
                    if ($i === 0) {
                        $q = self::applyCondition($q, $condition);
                    } else {
                        $q->orWhere(function ($subQ) use ($condition) {
                            self::applyCondition($subQ, $condition);
                        });
                    }
                }
            });
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('phone')->toArray();
    }

    /**
     * Apply a single condition to query
     */
    private static function applyCondition($query, object $condition)
    {
        $field = $condition->field;
        $operator = $condition->operator;
        $value = $condition->value;

        // Handle tag field — route to has_tag/not_has_tag
        if ($field === 'tag') {
            $tagOperator = ($operator === 'not_equals' || $operator === 'not_has_tag') ? 'not_has_tag' : 'has_tag';
            switch ($tagOperator) {
                case 'has_tag':
                    return $query->whereIn('id', function ($sub) use ($value) {
                        $sub->select('contact_id')->from('mod_sms_contact_tags')->where('tag_id', (int)$value);
                    });
                case 'not_has_tag':
                    return $query->whereNotIn('id', function ($sub) use ($value) {
                        $sub->select('contact_id')->from('mod_sms_contact_tags')->where('tag_id', (int)$value);
                    });
            }
        }

        // Handle custom fields
        if (strpos($field, 'custom_') === 0) {
            $field = "JSON_EXTRACT(custom_data, '$.{$field}')";
        }

        switch ($operator) {
            case 'equals':
                return $query->where($field, '=', $value);
            case 'not_equals':
                return $query->where($field, '!=', $value);
            case 'contains':
                return $query->where($field, 'like', '%' . $value . '%');
            case 'starts_with':
                return $query->where($field, 'like', $value . '%');
            case 'ends_with':
                return $query->where($field, 'like', '%' . $value);
            case 'greater_than':
                return $query->where($field, '>', $value);
            case 'less_than':
                return $query->where($field, '<', $value);
            case 'is_empty':
                return $query->where(function ($q) use ($field) {
                    $q->whereNull($field)->orWhere($field, '');
                });
            case 'is_not_empty':
                return $query->whereNotNull($field)->where($field, '!=', '');
            case 'between':
                $values = explode(',', $value);
                if (count($values) === 2) {
                    return $query->whereBetween($field, [trim($values[0]), trim($values[1])]);
                }
                return $query;
            case 'has_tag':
                return $query->whereIn('id', function ($sub) use ($value) {
                    $sub->select('contact_id')->from('mod_sms_contact_tags')->where('tag_id', (int)$value);
                });
            case 'not_has_tag':
                return $query->whereNotIn('id', function ($sub) use ($value) {
                    $sub->select('contact_id')->from('mod_sms_contact_tags')->where('tag_id', (int)$value);
                });
            default:
                return $query;
        }
    }

    /**
     * Calculate and update segment contact count
     */
    public static function calculateSegmentCount(int $segmentId, int $clientId = 0): int
    {
        // Verify segment belongs to client if client_id provided
        if ($clientId > 0) {
            $segment = Capsule::table('mod_sms_segments')
                ->where('id', $segmentId)
                ->where('client_id', $clientId)
                ->first();
            if (!$segment) return 0;
        }

        $contacts = self::getSegmentContacts($segmentId);
        $count = count($contacts);

        $update = ['contact_count' => $count, 'last_calculated_at' => date('Y-m-d H:i:s')];
        $query = Capsule::table('mod_sms_segments')->where('id', $segmentId);
        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }
        $query->update($update);

        return $count;
    }

    /**
     * Get segments for client
     */
    public static function getSegments(int $clientId): array
    {
        return Capsule::table('mod_sms_segments')
            ->where('client_id', $clientId)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    // ==================== Scheduled Messages ====================

    /**
     * Schedule a single message
     */
    public static function scheduleMessage(int $clientId, string $to, string $message, string $scheduledAt, array $options = []): array
    {
        try {
            $id = Capsule::table('mod_sms_scheduled')->insertGetId([
                'client_id' => $clientId,
                'to_number' => $to,
                'message' => $message,
                'channel' => $options['channel'] ?? 'sms',
                'sender_id' => $options['sender_id'] ?? null,
                'gateway_id' => $options['gateway_id'] ?? null,
                'scheduled_at' => $scheduledAt,
                'timezone' => $options['timezone'] ?? 'UTC',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process scheduled messages (called by cron)
     */
    public static function processScheduledMessages(): array
    {
        $processed = 0;
        $now = date('Y-m-d H:i:s');

        $messages = Capsule::table('mod_sms_scheduled')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->limit(100)
            ->get();

        foreach ($messages as $scheduled) {
            require_once dirname(__DIR__) . '/Core/MessageService.php';

            $result = MessageService::send($scheduled->client_id, $scheduled->to_number, $scheduled->message, [
                'channel' => $scheduled->channel,
                'sender_id' => $scheduled->sender_id,
                'gateway_id' => $scheduled->gateway_id,
                'send_now' => true,
            ]);

            Capsule::table('mod_sms_scheduled')
                ->where('id', $scheduled->id)
                ->update([
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'message_id' => $result['message_id'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $processed++;
        }

        return ['processed' => $processed];
    }
}
