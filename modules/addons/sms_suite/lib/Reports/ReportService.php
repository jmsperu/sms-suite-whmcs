<?php
/**
 * SMS Suite - Report Service
 *
 * Generates various reports for messaging activity
 */

namespace SMSSuite\Reports;

use WHMCS\Database\Capsule;

class ReportService
{
    /**
     * Get usage summary for client
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getUsageSummary(int $clientId, string $startDate, string $endDate): array
    {
        $query = Capsule::table('mod_sms_messages')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        $totalMessages = $query->count();
        $totalSegments = $query->sum('segments');
        $totalCost = $query->sum('cost');

        $byStatus = Capsule::table('mod_sms_messages')
            ->select('status', Capsule::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->when($clientId > 0, function ($q) use ($clientId) {
                return $q->where('client_id', $clientId);
            })
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byChannel = Capsule::table('mod_sms_messages')
            ->select('channel', Capsule::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->when($clientId > 0, function ($q) use ($clientId) {
                return $q->where('client_id', $clientId);
            })
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        return [
            'total_messages' => $totalMessages,
            'total_segments' => $totalSegments,
            'total_cost' => $totalCost,
            'by_status' => $byStatus,
            'by_channel' => $byChannel,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }

    /**
     * Get daily message counts
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getDailyStats(int $clientId, string $startDate, string $endDate): array
    {
        $query = Capsule::table('mod_sms_messages')
            ->select(
                Capsule::raw('DATE(created_at) as date'),
                Capsule::raw('COUNT(*) as total'),
                Capsule::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                Capsule::raw('SUM(CASE WHEN status IN ("failed", "undelivered", "rejected") THEN 1 ELSE 0 END) as failed'),
                Capsule::raw('SUM(segments) as segments'),
                Capsule::raw('SUM(cost) as cost')
            )
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->groupBy(Capsule::raw('DATE(created_at)'))
            ->orderBy('date');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get top destinations (countries/numbers)
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     */
    public static function getTopDestinations(int $clientId, string $startDate, string $endDate, int $limit = 10): array
    {
        $query = Capsule::table('mod_sms_messages')
            ->select(
                Capsule::raw('LEFT(to_number, 3) as prefix'),
                Capsule::raw('COUNT(*) as count'),
                Capsule::raw('SUM(cost) as total_cost')
            )
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->groupBy('prefix')
            ->orderBy('count', 'desc')
            ->limit($limit);

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get gateway performance stats
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getGatewayStats(string $startDate, string $endDate): array
    {
        return Capsule::table('mod_sms_messages')
            ->join('mod_sms_gateways', 'mod_sms_messages.gateway_id', '=', 'mod_sms_gateways.id')
            ->select(
                'mod_sms_gateways.name as gateway_name',
                Capsule::raw('COUNT(*) as total'),
                Capsule::raw('SUM(CASE WHEN mod_sms_messages.status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                Capsule::raw('SUM(CASE WHEN mod_sms_messages.status IN ("failed", "undelivered") THEN 1 ELSE 0 END) as failed'),
                Capsule::raw('AVG(CASE WHEN mod_sms_messages.delivered_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, mod_sms_messages.created_at, mod_sms_messages.delivered_at) END) as avg_delivery_time')
            )
            ->where('mod_sms_messages.created_at', '>=', $startDate)
            ->where('mod_sms_messages.created_at', '<=', $endDate . ' 23:59:59')
            ->groupBy('mod_sms_messages.gateway_id', 'mod_sms_gateways.name')
            ->get()
            ->toArray();
    }

    /**
     * Export report to CSV
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @return string
     */
    public static function exportToCsv(int $clientId, string $startDate, string $endDate): string
    {
        $query = Capsule::table('mod_sms_messages')
            ->select('id', 'to_number', 'sender_id', 'message', 'channel', 'status', 'segments', 'cost', 'created_at', 'delivered_at')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->orderBy('created_at', 'desc');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        $messages = $query->get();

        $output = "ID,To,From,Message,Channel,Status,Segments,Cost,Created,Delivered\n";

        foreach ($messages as $msg) {
            $output .= sprintf(
                '%d,"%s","%s","%s","%s","%s",%d,%.4f,"%s","%s"' . "\n",
                $msg->id,
                $msg->to_number,
                $msg->sender_id ?? '',
                str_replace('"', '""', substr($msg->message, 0, 100)),
                $msg->channel,
                $msg->status,
                $msg->segments,
                $msg->cost,
                $msg->created_at,
                $msg->delivered_at ?? ''
            );
        }

        return $output;
    }

    /**
     * Get campaign report
     *
     * @param int $campaignId
     * @return array
     */
    public static function getCampaignReport(int $campaignId): array
    {
        $campaign = Capsule::table('mod_sms_campaigns')
            ->where('id', $campaignId)
            ->first();

        if (!$campaign) {
            return [];
        }

        $messages = Capsule::table('mod_sms_messages')
            ->where('campaign_id', $campaignId)
            ->get();

        $byStatus = [];
        $totalCost = 0;

        foreach ($messages as $msg) {
            if (!isset($byStatus[$msg->status])) {
                $byStatus[$msg->status] = 0;
            }
            $byStatus[$msg->status]++;
            $totalCost += $msg->cost;
        }

        $deliveryRate = $campaign->sent_count > 0
            ? round(($campaign->delivered_count / $campaign->sent_count) * 100, 2)
            : 0;

        return [
            'campaign' => $campaign,
            'by_status' => $byStatus,
            'total_cost' => $totalCost,
            'delivery_rate' => $deliveryRate,
        ];
    }

    // ==================== Chart Data Methods ====================

    /**
     * Get hourly message distribution for charts
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getHourlyDistribution(int $clientId, string $startDate, string $endDate): array
    {
        $query = Capsule::table('mod_sms_messages')
            ->select(
                Capsule::raw('HOUR(created_at) as hour'),
                Capsule::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->groupBy(Capsule::raw('HOUR(created_at)'))
            ->orderBy('hour');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        $data = $query->pluck('count', 'hour')->toArray();

        // Fill in missing hours with 0
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $result[$i] = $data[$i] ?? 0;
        }

        return [
            'labels' => array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)),
            'data' => array_values($result),
        ];
    }

    /**
     * Get day of week distribution for charts
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getDayOfWeekDistribution(int $clientId, string $startDate, string $endDate): array
    {
        $query = Capsule::table('mod_sms_messages')
            ->select(
                Capsule::raw('DAYOFWEEK(created_at) as dow'),
                Capsule::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->groupBy(Capsule::raw('DAYOFWEEK(created_at)'))
            ->orderBy('dow');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        $data = $query->pluck('count', 'dow')->toArray();

        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $result = [];
        for ($i = 1; $i <= 7; $i++) {
            $result[] = $data[$i] ?? 0;
        }

        return [
            'labels' => $days,
            'data' => $result,
        ];
    }

    /**
     * Get message trend data for line charts (last 30 days)
     *
     * @param int $clientId
     * @return array
     */
    public static function getMessageTrend(int $clientId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate = date('Y-m-d');

        $query = Capsule::table('mod_sms_messages')
            ->select(
                Capsule::raw('DATE(created_at) as date'),
                Capsule::raw('COUNT(*) as total'),
                Capsule::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered')
            )
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->groupBy(Capsule::raw('DATE(created_at)'))
            ->orderBy('date');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        $results = $query->get()->keyBy('date');

        // Fill in missing days
        $labels = [];
        $totalData = [];
        $deliveredData = [];

        for ($i = $days; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M d', strtotime($date));
            $totalData[] = $results[$date]->total ?? 0;
            $deliveredData[] = $results[$date]->delivered ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Total', 'data' => $totalData, 'borderColor' => '#3498db'],
                ['label' => 'Delivered', 'data' => $deliveredData, 'borderColor' => '#2ecc71'],
            ],
        ];
    }

    /**
     * Get status distribution for pie/donut charts
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getStatusDistribution(int $clientId, string $startDate, string $endDate): array
    {
        $query = Capsule::table('mod_sms_messages')
            ->select('status', Capsule::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->groupBy('status');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        $data = $query->pluck('count', 'status')->toArray();

        $statusColors = [
            'delivered' => '#2ecc71',
            'sent' => '#3498db',
            'queued' => '#f39c12',
            'sending' => '#9b59b6',
            'failed' => '#e74c3c',
            'undelivered' => '#e67e22',
            'rejected' => '#c0392b',
            'expired' => '#95a5a6',
        ];

        $labels = array_keys($data);
        $values = array_values($data);
        $colors = array_map(fn($s) => $statusColors[$s] ?? '#bdc3c7', $labels);

        return [
            'labels' => array_map('ucfirst', $labels),
            'data' => $values,
            'backgroundColor' => $colors,
        ];
    }

    /**
     * Get delivery performance metrics
     *
     * @param int $clientId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getDeliveryMetrics(int $clientId, string $startDate, string $endDate): array
    {
        $query = Capsule::table('mod_sms_messages')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        $total = $query->count();
        $delivered = (clone $query)->where('status', 'delivered')->count();
        $failed = (clone $query)->whereIn('status', ['failed', 'undelivered', 'rejected'])->count();

        // Calculate average delivery time
        $avgDeliveryTime = Capsule::table('mod_sms_messages')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->whereNotNull('delivered_at')
            ->when($clientId > 0, fn($q) => $q->where('client_id', $clientId))
            ->avg(Capsule::raw('TIMESTAMPDIFF(SECOND, created_at, delivered_at)'));

        return [
            'total_sent' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'avg_delivery_time_seconds' => round($avgDeliveryTime ?? 0),
            'avg_delivery_time_formatted' => self::formatSeconds($avgDeliveryTime ?? 0),
        ];
    }

    /**
     * Get link click stats for campaigns
     *
     * @param int $campaignId
     * @return array
     */
    public static function getCampaignLinkStats(int $campaignId): array
    {
        $links = Capsule::table('mod_sms_tracking_links')
            ->where('campaign_id', $campaignId)
            ->get();

        $totalClicks = 0;
        $uniqueClicks = 0;
        $clicksByDevice = ['mobile' => 0, 'desktop' => 0, 'tablet' => 0];

        foreach ($links as $link) {
            $totalClicks += $link->click_count;

            $unique = Capsule::table('mod_sms_link_clicks')
                ->where('link_id', $link->id)
                ->distinct('ip_address')
                ->count('ip_address');

            $uniqueClicks += $unique;

            $devices = Capsule::table('mod_sms_link_clicks')
                ->where('link_id', $link->id)
                ->select('device', Capsule::raw('COUNT(*) as count'))
                ->groupBy('device')
                ->pluck('count', 'device');

            foreach ($devices as $device => $count) {
                if (isset($clicksByDevice[$device])) {
                    $clicksByDevice[$device] += $count;
                }
            }
        }

        // Get campaign for CTR calculation
        $campaign = Capsule::table('mod_sms_campaigns')
            ->where('id', $campaignId)
            ->first();

        $ctr = ($campaign && $campaign->sent_count > 0)
            ? round(($uniqueClicks / $campaign->sent_count) * 100, 2)
            : 0;

        return [
            'total_links' => count($links),
            'total_clicks' => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'ctr' => $ctr,
            'by_device' => $clicksByDevice,
        ];
    }

    /**
     * Get A/B test report
     *
     * @param int $campaignId
     * @return array
     */
    public static function getABTestReport(int $campaignId): array
    {
        $variants = Capsule::table('mod_sms_campaign_ab_tests')
            ->where('campaign_id', $campaignId)
            ->get();

        if ($variants->isEmpty()) {
            return [];
        }

        $results = [];
        $winner = null;
        $bestScore = 0;

        foreach ($variants as $variant) {
            $deliveryRate = $variant->sent_count > 0
                ? round(($variant->delivered_count / $variant->sent_count) * 100, 2)
                : 0;

            $clickRate = $variant->sent_count > 0
                ? round(($variant->clicked_count / $variant->sent_count) * 100, 2)
                : 0;

            $score = $deliveryRate + ($clickRate * 2);

            $results[] = [
                'variant' => $variant->variant,
                'message_preview' => substr($variant->message, 0, 50) . '...',
                'sent' => $variant->sent_count,
                'delivered' => $variant->delivered_count,
                'clicked' => $variant->clicked_count,
                'delivery_rate' => $deliveryRate,
                'click_rate' => $clickRate,
                'score' => $score,
            ];

            if ($score > $bestScore) {
                $bestScore = $score;
                $winner = $variant->variant;
            }
        }

        return [
            'variants' => $results,
            'winner' => $winner,
            'statistical_significance' => self::calculateSignificance($results),
        ];
    }

    /**
     * Calculate statistical significance of A/B test
     */
    private static function calculateSignificance(array $variants): string
    {
        if (count($variants) < 2) return 'insufficient_data';

        $totalSamples = array_sum(array_column($variants, 'sent'));
        if ($totalSamples < 100) return 'low_sample_size';

        // Simple significance check based on conversion difference
        $rates = array_column($variants, 'delivery_rate');
        $maxDiff = max($rates) - min($rates);

        if ($maxDiff > 5) return 'significant';
        if ($maxDiff > 2) return 'marginal';
        return 'not_significant';
    }

    /**
     * Format seconds to human readable
     */
    private static function formatSeconds(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds) . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . 'm';
        } else {
            return round($seconds / 3600, 1) . 'h';
        }
    }

    /**
     * Get real-time dashboard stats
     *
     * @param int $clientId
     * @return array
     */
    public static function getDashboardStats(int $clientId): array
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m-01');

        $query = Capsule::table('mod_sms_messages');
        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        // Today's stats
        $todayTotal = (clone $query)
            ->where('created_at', '>=', $today)
            ->count();

        $todayDelivered = (clone $query)
            ->where('created_at', '>=', $today)
            ->where('status', 'delivered')
            ->count();

        // This month's stats
        $monthTotal = (clone $query)
            ->where('created_at', '>=', $thisMonth)
            ->count();

        $monthCost = (clone $query)
            ->where('created_at', '>=', $thisMonth)
            ->sum('cost');

        // Active campaigns
        $activeCampaigns = Capsule::table('mod_sms_campaigns')
            ->whereIn('status', ['sending', 'scheduled', 'queued'])
            ->when($clientId > 0, fn($q) => $q->where('client_id', $clientId))
            ->count();

        // Pending messages
        $pendingMessages = (clone $query)
            ->where('status', 'queued')
            ->count();

        // Balance (for clients)
        $balance = null;
        if ($clientId > 0) {
            $wallet = Capsule::table('mod_sms_wallet')
                ->where('client_id', $clientId)
                ->first();
            $balance = $wallet ? $wallet->balance : 0;
        }

        return [
            'today' => [
                'total' => $todayTotal,
                'delivered' => $todayDelivered,
                'delivery_rate' => $todayTotal > 0 ? round(($todayDelivered / $todayTotal) * 100, 1) : 0,
            ],
            'month' => [
                'total' => $monthTotal,
                'cost' => $monthCost,
            ],
            'active_campaigns' => $activeCampaigns,
            'pending_messages' => $pendingMessages,
            'balance' => $balance,
        ];
    }
}
