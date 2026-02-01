<?php
/**
 * SMS Suite - Billing Service
 *
 * Handles all billing operations: wallet, credits, deductions, top-ups
 */

namespace SMSSuite\Billing;

use WHMCS\Database\Capsule;
use Exception;

class BillingService
{
    /**
     * Billing modes
     */
    const MODE_PER_MESSAGE = 'per_message';
    const MODE_PER_SEGMENT = 'per_segment';
    const MODE_WALLET = 'wallet';
    const MODE_PLAN = 'plan';

    /**
     * Check if client has sufficient balance for a message
     *
     * @param int $clientId
     * @param float $cost
     * @return bool
     */
    public static function hasBalance(int $clientId, float $cost): bool
    {
        if ($cost <= 0) {
            return true;
        }

        $settings = self::getClientSettings($clientId);
        $billingMode = $settings->billing_mode ?? self::MODE_PER_SEGMENT;

        // Get balance based on billing mode
        if ($billingMode === self::MODE_PLAN) {
            // Check plan credits
            $planCredits = Capsule::table('mod_sms_plan_credits')
                ->where('client_id', $clientId)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->sum('remaining');

            return $planCredits >= 1; // At least 1 credit needed
        }

        // For all other modes, check wallet balance
        $wallet = self::getWallet($clientId);
        return $wallet->balance >= $cost;
    }

    /**
     * Calculate message cost
     *
     * @param int $clientId
     * @param int $segments
     * @param string $channel
     * @param int|null $gatewayId
     * @param string|null $countryCode
     * @return float
     */
    public static function calculateCost(int $clientId, int $segments, string $channel = 'sms', ?int $gatewayId = null, ?string $countryCode = null): float
    {
        $settings = self::getClientSettings($clientId);
        $billingMode = $settings->billing_mode ?? self::MODE_PER_SEGMENT;

        // Get base rate
        $baseRate = self::getRate($clientId, $channel, $gatewayId, $countryCode);

        // Calculate based on billing mode
        switch ($billingMode) {
            case self::MODE_PER_MESSAGE:
                return $baseRate;

            case self::MODE_PER_SEGMENT:
            case self::MODE_WALLET:
                return $baseRate * $segments;

            case self::MODE_PLAN:
                // Plan mode uses credits, not currency
                return $segments; // 1 credit per segment

            default:
                return $baseRate * $segments;
        }
    }

    /**
     * Get rate for channel/gateway/country combination
     *
     * @param int $clientId
     * @param string $channel
     * @param int|null $gatewayId
     * @param string|null $countryCode
     * @return float
     */
    public static function getRate(int $clientId, string $channel = 'sms', ?int $gatewayId = null, ?string $countryCode = null): float
    {
        // Check for client-specific rate override
        $clientRate = Capsule::table('mod_sms_client_rates')
            ->where('client_id', $clientId)
            ->where('channel', $channel)
            ->first();

        if ($clientRate) {
            return (float)$clientRate->rate;
        }

        // Check gateway country pricing
        if ($gatewayId && $countryCode) {
            $countryRate = Capsule::table('mod_sms_gateway_countries')
                ->where('gateway_id', $gatewayId)
                ->where('country_code', $countryCode)
                ->first();

            if ($countryRate) {
                return $channel === 'whatsapp'
                    ? (float)$countryRate->whatsapp_rate
                    : (float)$countryRate->sms_rate;
            }
        }

        // Get default rate from module settings
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->pluck('value', 'setting');

        $rateKey = $channel === 'whatsapp' ? 'default_whatsapp_rate' : 'default_sms_rate';

        return (float)($settings[$rateKey] ?? 0.05);
    }

    /**
     * Deduct balance for message
     *
     * @param int $clientId
     * @param int $messageId
     * @param float $cost
     * @param int $segments
     * @return array
     */
    public static function deduct(int $clientId, int $messageId, float $cost, int $segments = 1): array
    {
        $settings = self::getClientSettings($clientId);
        $billingMode = $settings->billing_mode ?? self::MODE_PER_SEGMENT;

        try {
            if ($billingMode === self::MODE_PLAN) {
                return self::deductPlanCredits($clientId, $messageId, $segments);
            }

            return self::deductWalletBalance($clientId, $messageId, $cost);

        } catch (Exception $e) {
            logActivity("SMS Suite Billing Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deduct from wallet balance
     * Uses database transaction for data integrity
     */
    private static function deductWalletBalance(int $clientId, int $messageId, float $amount): array
    {
        return Capsule::connection()->transaction(function () use ($clientId, $messageId, $amount) {
            // Lock the wallet row for update to prevent race conditions
            $wallet = Capsule::table('mod_sms_wallet')
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->first();

            if (!$wallet || $wallet->balance < $amount) {
                throw new Exception('Insufficient balance');
            }

            $newBalance = $wallet->balance - $amount;

            // Deduct balance
            Capsule::table('mod_sms_wallet')
                ->where('client_id', $clientId)
                ->update(['balance' => $newBalance, 'updated_at' => date('Y-m-d H:i:s')]);

            // Record transaction
            Capsule::table('mod_sms_wallet_transactions')->insert([
                'client_id' => $clientId,
                'type' => 'deduction',
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'description' => "Message #{$messageId}",
                'reference_type' => 'message',
                'reference_id' => $messageId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Update message cost
            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update(['cost' => $amount, 'updated_at' => date('Y-m-d H:i:s')]);

            return ['success' => true, 'amount' => $amount];
        });
    }

    /**
     * Deduct from plan credits
     * Uses database transaction for data integrity
     */
    private static function deductPlanCredits(int $clientId, int $messageId, int $segments): array
    {
        return Capsule::connection()->transaction(function () use ($clientId, $messageId, $segments) {
            // Get active plan credits with lock (oldest first)
            $credits = Capsule::table('mod_sms_plan_credits')
                ->where('client_id', $clientId)
                ->where('remaining', '>', 0)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->orderBy('expires_at', 'asc')
                ->lockForUpdate()
                ->get();

            $creditsNeeded = $segments;
            $totalDeducted = 0;

            foreach ($credits as $credit) {
                if ($creditsNeeded <= 0) break;

                $deductAmount = min($credit->remaining, $creditsNeeded);

                Capsule::table('mod_sms_plan_credits')
                    ->where('id', $credit->id)
                    ->update([
                        'remaining' => $credit->remaining - $deductAmount,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                $creditsNeeded -= $deductAmount;
                $totalDeducted += $deductAmount;
            }

            if ($creditsNeeded > 0) {
                throw new Exception('Insufficient credits');
            }

            // Record transaction
            Capsule::table('mod_sms_wallet_transactions')->insert([
                'client_id' => $clientId,
                'type' => 'credit_deduction',
                'amount' => -$totalDeducted,
                'description' => "Message #{$messageId} ({$totalDeducted} credits)",
                'reference_type' => 'message',
                'reference_id' => $messageId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Update message (cost = credits used)
            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update(['cost' => $totalDeducted, 'updated_at' => date('Y-m-d H:i:s')]);

            return ['success' => true, 'credits' => $totalDeducted];
        });
    }

    /**
     * Refund for failed message
     *
     * @param int $messageId
     * @return bool
     */
    public static function refund(int $messageId): bool
    {
        $message = Capsule::table('mod_sms_messages')->where('id', $messageId)->first();

        if (!$message || $message->cost <= 0) {
            return false;
        }

        $settings = self::getClientSettings($message->client_id);
        $billingMode = $settings->billing_mode ?? self::MODE_PER_SEGMENT;

        try {
            if ($billingMode === self::MODE_PLAN) {
                // Refund credits (add to earliest expiring plan)
                $plan = Capsule::table('mod_sms_plan_credits')
                    ->where('client_id', $message->client_id)
                    ->where('expires_at', '>', date('Y-m-d H:i:s'))
                    ->orderBy('expires_at', 'asc')
                    ->first();

                if ($plan) {
                    Capsule::table('mod_sms_plan_credits')
                        ->where('id', $plan->id)
                        ->increment('remaining', (int)$message->cost);
                }
            } else {
                // Refund to wallet
                Capsule::table('mod_sms_wallet')
                    ->where('client_id', $message->client_id)
                    ->increment('balance', $message->cost);

                $wallet = self::getWallet($message->client_id);

                // Record transaction
                Capsule::table('mod_sms_wallet_transactions')->insert([
                    'client_id' => $message->client_id,
                    'type' => 'refund',
                    'amount' => $message->cost,
                    'balance_after' => $wallet->balance,
                    'description' => "Refund for message #{$messageId}",
                    'reference_type' => 'message',
                    'reference_id' => $messageId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Clear message cost
            Capsule::table('mod_sms_messages')
                ->where('id', $messageId)
                ->update(['cost' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

            return true;

        } catch (Exception $e) {
            logActivity("SMS Suite Refund Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add funds to wallet
     *
     * @param int $clientId
     * @param float $amount
     * @param string $description
     * @param int|null $invoiceId
     * @return array
     */
    public static function topUp(int $clientId, float $amount, string $description = 'Wallet top-up', ?int $invoiceId = null): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Invalid amount'];
        }

        try {
            // Add to wallet
            Capsule::table('mod_sms_wallet')
                ->where('client_id', $clientId)
                ->increment('balance', $amount);

            $wallet = self::getWallet($clientId);

            // Record transaction
            Capsule::table('mod_sms_wallet_transactions')->insert([
                'client_id' => $clientId,
                'type' => 'topup',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'reference_type' => $invoiceId ? 'invoice' : null,
                'reference_id' => $invoiceId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            logActivity("SMS Suite: Wallet top-up of {$amount} for client {$clientId}");

            return ['success' => true, 'balance' => $wallet->balance];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add plan credits
     *
     * @param int $clientId
     * @param int $credits
     * @param string $expiresAt
     * @param int|null $planId
     * @return array
     */
    public static function addCredits(int $clientId, int $credits, string $expiresAt, ?int $planId = null): array
    {
        try {
            $id = Capsule::table('mod_sms_plan_credits')->insertGetId([
                'client_id' => $clientId,
                'plan_id' => $planId,
                'total' => $credits,
                'remaining' => $credits,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Record transaction
            Capsule::table('mod_sms_wallet_transactions')->insert([
                'client_id' => $clientId,
                'type' => 'credit_add',
                'amount' => $credits,
                'description' => "Added {$credits} credits",
                'reference_type' => 'plan_credit',
                'reference_id' => $id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            logActivity("SMS Suite: Added {$credits} credits for client {$clientId}");

            return ['success' => true, 'credit_id' => $id];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get client's wallet
     *
     * @param int $clientId
     * @return object
     */
    public static function getWallet(int $clientId): object
    {
        $wallet = Capsule::table('mod_sms_wallet')
            ->where('client_id', $clientId)
            ->first();

        if (!$wallet) {
            // Create wallet if it doesn't exist
            Capsule::table('mod_sms_wallet')->insert([
                'client_id' => $clientId,
                'balance' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return (object)['client_id' => $clientId, 'balance' => 0];
        }

        return $wallet;
    }

    /**
     * Get client's total credits
     *
     * @param int $clientId
     * @return int
     */
    public static function getTotalCredits(int $clientId): int
    {
        return (int)Capsule::table('mod_sms_plan_credits')
            ->where('client_id', $clientId)
            ->where('remaining', '>', 0)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->sum('remaining');
    }

    /**
     * Get client settings
     *
     * @param int $clientId
     * @return object
     */
    public static function getClientSettings(int $clientId): object
    {
        $settings = Capsule::table('mod_sms_settings')
            ->where('client_id', $clientId)
            ->first();

        if (!$settings) {
            return (object)[
                'billing_mode' => self::MODE_PER_SEGMENT,
                'currency' => 'USD',
            ];
        }

        return $settings;
    }

    /**
     * Create WHMCS invoice for wallet top-up
     *
     * @param int $clientId
     * @param float $amount
     * @return int|null Invoice ID
     */
    public static function createTopUpInvoice(int $clientId, float $amount): ?int
    {
        try {
            $result = localAPI('CreateInvoice', [
                'userid' => $clientId,
                'sendinvoice' => true,
                'itemdescription1' => "SMS Suite - Wallet Top-up",
                'itemamount1' => $amount,
                'itemtaxed1' => true,
            ]);

            if ($result['result'] === 'success') {
                // Track pending top-up
                Capsule::table('mod_sms_pending_topups')->insert([
                    'client_id' => $clientId,
                    'invoice_id' => $result['invoiceid'],
                    'amount' => $amount,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                return $result['invoiceid'];
            }

        } catch (Exception $e) {
            logActivity("SMS Suite: Failed to create top-up invoice - " . $e->getMessage());
        }

        return null;
    }

    /**
     * Handle invoice paid - process wallet top-up
     *
     * @param int $invoiceId
     * @return void
     */
    public static function handleInvoicePaid(int $invoiceId): void
    {
        $pending = Capsule::table('mod_sms_pending_topups')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            // Add funds to wallet
            self::topUp($pending->client_id, $pending->amount, "Invoice #{$invoiceId}", $invoiceId);

            // Mark as complete
            Capsule::table('mod_sms_pending_topups')
                ->where('id', $pending->id)
                ->update(['status' => 'completed', 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    /**
     * Get transaction history
     *
     * @param int $clientId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getTransactionHistory(int $clientId, int $limit = 50, int $offset = 0): array
    {
        $query = Capsule::table('mod_sms_wallet_transactions')
            ->where('client_id', $clientId);

        $total = $query->count();

        $transactions = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        return [
            'transactions' => $transactions,
            'total' => $total,
        ];
    }
}
