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

            return $planCredits >= $cost; // cost = segments * credit_cost in plan mode
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
    public static function calculateCost(int $clientId, int $segments, string $channel = 'sms', ?int $gatewayId = null, ?string $countryCode = null, ?string $network = null): float
    {
        $settings = self::getClientSettings($clientId);
        $billingMode = $settings->billing_mode ?? self::MODE_PER_SEGMENT;

        // Get base rate
        $baseRate = self::getRate($clientId, $channel, $gatewayId, $countryCode, $network);

        // Calculate based on billing mode
        switch ($billingMode) {
            case self::MODE_PER_MESSAGE:
                return $baseRate;

            case self::MODE_PER_SEGMENT:
            case self::MODE_WALLET:
                return $baseRate * $segments;

            case self::MODE_PLAN:
                // Plan mode uses credits per segment based on destination
                return $segments * self::getCreditCost($countryCode, $network);

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
    public static function getRate(int $clientId, string $channel = 'sms', ?int $gatewayId = null, ?string $countryCode = null, ?string $network = null): float
    {
        $rateColumn = $channel === 'whatsapp' ? 'whatsapp_rate' : 'sms_rate';

        // 1. Client rate: client + country + network (most specific)
        if ($countryCode && $network) {
            $clientRate = Capsule::table('mod_sms_client_rates')
                ->where('client_id', $clientId)
                ->where('country_code', $countryCode)
                ->where('network_prefix', $network)
                ->where('status', 1)
                ->orderBy('priority', 'desc')
                ->first();

            if ($clientRate) {
                return (float)$clientRate->$rateColumn;
            }
        }

        // 2. Client rate: client + country only
        if ($countryCode) {
            $clientRate = Capsule::table('mod_sms_client_rates')
                ->where('client_id', $clientId)
                ->where('country_code', $countryCode)
                ->where(function ($q) {
                    $q->whereNull('network_prefix')
                      ->orWhere('network_prefix', '');
                })
                ->where('status', 1)
                ->orderBy('priority', 'desc')
                ->first();

            if ($clientRate) {
                return (float)$clientRate->$rateColumn;
            }
        }

        // 3. Client rate: client flat override (no country/network)
        $clientFlat = Capsule::table('mod_sms_client_rates')
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->whereNull('country_code')
                  ->orWhere('country_code', '');
            })
            ->where('status', 1)
            ->orderBy('priority', 'desc')
            ->first();

        if ($clientFlat) {
            return (float)$clientFlat->$rateColumn;
        }

        // 4. Destination rate: country + network
        if ($countryCode && $network) {
            $destRate = Capsule::table('mod_sms_destination_rates')
                ->where('country_code', $countryCode)
                ->where('network', $network)
                ->where('status', 1)
                ->first();

            if ($destRate) {
                return (float)$destRate->$rateColumn;
            }
        }

        // 5. Destination rate: country only
        if ($countryCode) {
            $destRate = Capsule::table('mod_sms_destination_rates')
                ->where('country_code', $countryCode)
                ->where(function ($q) {
                    $q->whereNull('network')
                      ->orWhere('network', '');
                })
                ->where('status', 1)
                ->first();

            if ($destRate) {
                return (float)$destRate->$rateColumn;
            }
        }

        // 6. Gateway-country rate
        if ($gatewayId && $countryCode) {
            $countryRate = Capsule::table('mod_sms_gateway_countries')
                ->where('gateway_id', $gatewayId)
                ->where('country_code', $countryCode)
                ->first();

            if ($countryRate) {
                return (float)$countryRate->$rateColumn;
            }
        }

        // 7. Default module setting
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->pluck('value', 'setting');

        $rateKey = $channel === 'whatsapp' ? 'default_whatsapp_rate' : 'default_sms_rate';

        return (float)($settings[$rateKey] ?? 0.05);
    }

    /**
     * Get credit cost per segment for a destination (used in plan/credit mode)
     *
     * Lookup order: destination_rates (country+network) -> destination_rates (country) -> module setting -> default 1
     *
     * @param string|null $countryCode
     * @param string|null $network
     * @return int Credits per segment
     */
    public static function getCreditCost(?string $countryCode, ?string $network = null): int
    {
        // 1. Destination rate: country + network
        if ($countryCode && $network) {
            $destRate = Capsule::table('mod_sms_destination_rates')
                ->where('country_code', $countryCode)
                ->where('network', $network)
                ->where('status', 1)
                ->first();

            if ($destRate) {
                return max(1, (int)$destRate->credit_cost);
            }
        }

        // 2. Destination rate: country only
        if ($countryCode) {
            $destRate = Capsule::table('mod_sms_destination_rates')
                ->where('country_code', $countryCode)
                ->where(function ($q) {
                    $q->whereNull('network')
                      ->orWhere('network', '');
                })
                ->where('status', 1)
                ->first();

            if ($destRate) {
                return max(1, (int)$destRate->credit_cost);
            }
        }

        // 3. Module setting
        $setting = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->where('setting', 'credit_per_segment')
            ->first();

        if ($setting && (int)$setting->value > 0) {
            return (int)$setting->value;
        }

        // 4. Default
        return 1;
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

    // ============================================================
    // SMS Credit Package Purchase Methods
    // ============================================================

    /**
     * Create invoice for SMS credit package purchase
     *
     * @param int $clientId
     * @param int $packageId
     * @return array
     */
    public static function purchaseCreditPackage(int $clientId, int $packageId): array
    {
        try {
            $package = Capsule::table('mod_sms_credit_packages')
                ->where('id', $packageId)
                ->where('status', 1)
                ->first();

            if (!$package) {
                return ['success' => false, 'error' => 'Package not found or inactive'];
            }

            $client = Capsule::table('tblclients')->where('id', $clientId)->first();
            if (!$client) {
                return ['success' => false, 'error' => 'Client not found'];
            }

            $currencyId = $client->currency;
            $price = $package->price;

            if ($package->currency_id && $package->currency_id != $currencyId) {
                $price = self::convertCurrency($price, $package->currency_id, $currencyId);
            }

            $invoiceResult = localAPI('CreateInvoice', [
                'userid' => $clientId,
                'sendinvoice' => true,
                'paymentmethod' => '',
                'itemdescription1' => 'SMS Credits: ' . $package->name . ' (' . $package->credits . ' credits)',
                'itemamount1' => $price,
                'itemtaxed1' => true,
            ]);

            if ($invoiceResult['result'] !== 'success') {
                return ['success' => false, 'error' => 'Failed to create invoice: ' . ($invoiceResult['message'] ?? 'Unknown error')];
            }

            $invoiceId = $invoiceResult['invoiceid'];

            $expiresAt = null;
            if ($package->validity_days > 0) {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$package->validity_days} days"));
            }

            Capsule::table('mod_sms_credit_purchases')->insert([
                'client_id' => $clientId,
                'package_id' => $packageId,
                'invoice_id' => $invoiceId,
                'credits_purchased' => $package->credits,
                'bonus_credits' => $package->bonus_credits,
                'amount' => $price,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'credits' => $package->credits,
                'bonus_credits' => $package->bonus_credits,
                'amount' => $price,
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process paid invoice for credit purchase
     *
     * @param int $invoiceId
     * @return array
     */
    public static function processCreditPurchasePayment(int $invoiceId): array
    {
        try {
            $purchase = Capsule::table('mod_sms_credit_purchases')
                ->where('invoice_id', $invoiceId)
                ->where('status', 'pending')
                ->first();

            if (!$purchase) {
                return ['success' => false, 'error' => 'No pending purchase found'];
            }

            $balance = Capsule::table('mod_sms_credit_balance')
                ->where('client_id', $purchase->client_id)
                ->first();

            $currentBalance = $balance ? $balance->balance : 0;
            $totalCredits = $purchase->credits_purchased + $purchase->bonus_credits;
            $newBalance = $currentBalance + $totalCredits;

            if ($balance) {
                Capsule::table('mod_sms_credit_balance')
                    ->where('client_id', $purchase->client_id)
                    ->update([
                        'balance' => $newBalance,
                        'total_purchased' => $balance->total_purchased + $purchase->credits_purchased,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                Capsule::table('mod_sms_credit_balance')->insert([
                    'client_id' => $purchase->client_id,
                    'balance' => $newBalance,
                    'total_purchased' => $purchase->credits_purchased,
                    'total_used' => 0,
                    'total_expired' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Capsule::table('mod_sms_credit_transactions')->insert([
                'client_id' => $purchase->client_id,
                'type' => 'purchase',
                'credits' => $purchase->credits_purchased,
                'balance_before' => $currentBalance,
                'balance_after' => $currentBalance + $purchase->credits_purchased,
                'reference_type' => 'invoice',
                'reference_id' => $invoiceId,
                'description' => 'SMS Credit Purchase',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($purchase->bonus_credits > 0) {
                Capsule::table('mod_sms_credit_transactions')->insert([
                    'client_id' => $purchase->client_id,
                    'type' => 'bonus',
                    'credits' => $purchase->bonus_credits,
                    'balance_before' => $currentBalance + $purchase->credits_purchased,
                    'balance_after' => $newBalance,
                    'reference_type' => 'invoice',
                    'reference_id' => $invoiceId,
                    'description' => 'Bonus Credits',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Capsule::table('mod_sms_credit_purchases')
                ->where('id', $purchase->id)
                ->update([
                    'status' => 'paid',
                    'credited_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return [
                'success' => true,
                'credits_added' => $totalCredits,
                'new_balance' => $newBalance,
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get client SMS credit balance
     *
     * @param int $clientId
     * @return int
     */
    public static function getClientCreditBalance(int $clientId): int
    {
        $balance = Capsule::table('mod_sms_credit_balance')
            ->where('client_id', $clientId)
            ->first();

        return $balance ? (int)$balance->balance : 0;
    }

    /**
     * Deduct SMS credits for usage with sender ID tracking
     *
     * @param int $clientId
     * @param int $credits
     * @param string $description
     * @param int|null $messageId
     * @param int|null $senderIdRef  Client sender ID reference
     * @param string|null $destination Phone number
     * @param string|null $network Network (safaricom, airtel, telkom)
     * @return array
     */
    public static function deductSmsCredits(int $clientId, int $credits, string $description = '', ?int $messageId = null, ?int $senderIdRef = null, ?string $destination = null, ?string $network = null): array
    {
        try {
            return Capsule::connection()->transaction(function () use ($clientId, $credits, $description, $messageId, $senderIdRef, $destination, $network) {
                $balance = Capsule::table('mod_sms_credit_balance')
                    ->where('client_id', $clientId)
                    ->lockForUpdate()
                    ->first();

                if (!$balance || $balance->balance < $credits) {
                    throw new Exception('Insufficient credits');
                }

                $newBalance = $balance->balance - $credits;

                // Update main balance
                Capsule::table('mod_sms_credit_balance')
                    ->where('client_id', $clientId)
                    ->update([
                        'balance' => $newBalance,
                        'total_used' => $balance->total_used + $credits,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                // Deduct from allocations (FIFO - oldest first)
                $allocationId = self::deductFromAllocations($clientId, $credits, $senderIdRef);

                // Record transaction
                Capsule::table('mod_sms_credit_transactions')->insert([
                    'client_id' => $clientId,
                    'type' => 'usage',
                    'credits' => -$credits,
                    'balance_before' => $balance->balance,
                    'balance_after' => $newBalance,
                    'reference_type' => $messageId ? 'message' : null,
                    'reference_id' => $messageId,
                    'description' => $description ?: 'SMS Usage',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                // Record detailed usage for sender ID tracking
                Capsule::table('mod_sms_credit_usage')->insert([
                    'client_id' => $clientId,
                    'allocation_id' => $allocationId,
                    'sender_id_ref' => $senderIdRef,
                    'message_id' => $messageId,
                    'credits_used' => $credits,
                    'destination' => $destination,
                    'network' => $network,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                return ['success' => true, 'new_balance' => $newBalance, 'allocation_id' => $allocationId];
            });

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deduct credits from allocations (FIFO - oldest non-expired first)
     *
     * @param int $clientId
     * @param int $credits
     * @param int|null $senderIdRef
     * @return int|null Allocation ID used
     */
    private static function deductFromAllocations(int $clientId, int $credits, ?int $senderIdRef = null): ?int
    {
        // First try to find allocations linked to this specific sender ID
        $query = Capsule::table('mod_sms_credit_allocations')
            ->where('client_id', $clientId)
            ->where('remaining_credits', '>', 0)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            });

        if ($senderIdRef) {
            // Prefer allocations linked to this sender ID
            $senderSpecific = (clone $query)->where('sender_id_ref', $senderIdRef)
                ->orderBy('created_at', 'asc')
                ->first();

            if ($senderSpecific && $senderSpecific->remaining_credits >= $credits) {
                self::updateAllocation($senderSpecific->id, $credits);
                return $senderSpecific->id;
            }
        }

        // Fall back to general allocations (no sender ID link or any available)
        $allocations = $query->orderBy('expires_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $remainingToDeduct = $credits;
        $primaryAllocationId = null;

        foreach ($allocations as $allocation) {
            if ($remainingToDeduct <= 0) break;

            $deductAmount = min($allocation->remaining_credits, $remainingToDeduct);
            self::updateAllocation($allocation->id, $deductAmount);

            if ($primaryAllocationId === null) {
                $primaryAllocationId = $allocation->id;
            }

            $remainingToDeduct -= $deductAmount;
        }

        return $primaryAllocationId;
    }

    /**
     * Update allocation after deduction
     *
     * @param int $allocationId
     * @param int $deductAmount
     */
    private static function updateAllocation(int $allocationId, int $deductAmount): void
    {
        $allocation = Capsule::table('mod_sms_credit_allocations')
            ->where('id', $allocationId)
            ->first();

        if (!$allocation) return;

        $newRemaining = $allocation->remaining_credits - $deductAmount;
        $newUsed = $allocation->used_credits + $deductAmount;

        Capsule::table('mod_sms_credit_allocations')
            ->where('id', $allocationId)
            ->update([
                'remaining_credits' => $newRemaining,
                'used_credits' => $newUsed,
                'status' => $newRemaining <= 0 ? 'exhausted' : 'active',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Get credit usage report per sender ID
     *
     * @param int $clientId
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public static function getCreditUsageBySenderId(int $clientId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Capsule::table('mod_sms_credit_usage as u')
            ->leftJoin('mod_sms_client_sender_ids as s', 'u.sender_id_ref', '=', 's.id')
            ->where('u.client_id', $clientId)
            ->select([
                's.sender_id',
                's.network',
                Capsule::raw('SUM(u.credits_used) as total_credits'),
                Capsule::raw('COUNT(*) as message_count'),
            ])
            ->groupBy('s.sender_id', 's.network');

        if ($startDate) {
            $query->where('u.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('u.created_at', '<=', $endDate);
        }

        return $query->get()->toArray();
    }

    /**
     * Get credit usage report per network
     *
     * @param int $clientId
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public static function getCreditUsageByNetwork(int $clientId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Capsule::table('mod_sms_credit_usage')
            ->where('client_id', $clientId)
            ->select([
                'network',
                Capsule::raw('SUM(credits_used) as total_credits'),
                Capsule::raw('COUNT(*) as message_count'),
            ])
            ->groupBy('network');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get()->toArray();
    }

    /**
     * Get client's credit allocations
     *
     * @param int $clientId
     * @param bool $activeOnly
     * @return array
     */
    public static function getClientAllocations(int $clientId, bool $activeOnly = true): array
    {
        $query = Capsule::table('mod_sms_credit_allocations as a')
            ->leftJoin('mod_sms_client_sender_ids as s', 'a.sender_id_ref', '=', 's.id')
            ->leftJoin('tblhosting as h', 'a.service_id', '=', 'h.id')
            ->where('a.client_id', $clientId)
            ->select([
                'a.*',
                's.sender_id',
                's.network',
                'h.domain as service_name',
            ]);

        if ($activeOnly) {
            $query->where('a.status', 'active')
                  ->where(function ($q) {
                      $q->whereNull('a.expires_at')
                        ->orWhere('a.expires_at', '>', date('Y-m-d H:i:s'));
                  });
        }

        return $query->orderBy('a.created_at', 'desc')->get()->toArray();
    }

    /**
     * Link credit allocation to a sender ID
     *
     * @param int $allocationId
     * @param int $senderIdRef
     * @return array
     */
    public static function linkAllocationToSenderId(int $allocationId, int $senderIdRef): array
    {
        try {
            Capsule::table('mod_sms_credit_allocations')
                ->where('id', $allocationId)
                ->update([
                    'sender_id_ref' => $senderIdRef,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all credit packages (for client purchase page)
     *
     * @param bool $activeOnly
     * @return array
     */
    public static function getCreditPackages(bool $activeOnly = true): array
    {
        $query = Capsule::table('mod_sms_credit_packages');

        if ($activeOnly) {
            $query->where('status', 1);
        }

        return $query->orderBy('sort_order')->orderBy('price')->get()->toArray();
    }

    /**
     * Create credit package (admin)
     *
     * @param array $data
     * @return array
     */
    public static function createCreditPackage(array $data): array
    {
        try {
            $packageId = Capsule::table('mod_sms_credit_packages')->insertGetId([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'credits' => $data['credits'],
                'price' => $data['price'],
                'currency_id' => $data['currency_id'] ?? null,
                'bonus_credits' => $data['bonus_credits'] ?? 0,
                'validity_days' => $data['validity_days'] ?? 0,
                'is_featured' => $data['is_featured'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0,
                'status' => $data['status'] ?? true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'package_id' => $packageId];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update credit package (admin)
     *
     * @param int $packageId
     * @param array $data
     * @return array
     */
    public static function updateCreditPackage(int $packageId, array $data): array
    {
        try {
            Capsule::table('mod_sms_credit_packages')
                ->where('id', $packageId)
                ->update(array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]));

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete credit package (admin)
     *
     * @param int $packageId
     * @return array
     */
    public static function deleteCreditPackage(int $packageId): array
    {
        try {
            Capsule::table('mod_sms_credit_packages')
                ->where('id', $packageId)
                ->delete();

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Admin: Add credits to client account
     *
     * @param int $clientId
     * @param int $credits
     * @param int $adminId
     * @param string $reason
     * @return array
     */
    public static function addCreditsToClient(int $clientId, int $credits, int $adminId, string $reason = ''): array
    {
        try {
            $balance = Capsule::table('mod_sms_credit_balance')
                ->where('client_id', $clientId)
                ->first();

            $currentBalance = $balance ? $balance->balance : 0;
            $newBalance = $currentBalance + $credits;

            if ($balance) {
                Capsule::table('mod_sms_credit_balance')
                    ->where('client_id', $clientId)
                    ->update([
                        'balance' => $newBalance,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                Capsule::table('mod_sms_credit_balance')->insert([
                    'client_id' => $clientId,
                    'balance' => $newBalance,
                    'total_purchased' => 0,
                    'total_used' => 0,
                    'total_expired' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Capsule::table('mod_sms_credit_transactions')->insert([
                'client_id' => $clientId,
                'type' => 'adjustment',
                'credits' => $credits,
                'balance_before' => $currentBalance,
                'balance_after' => $newBalance,
                'reference_type' => 'admin',
                'reference_id' => $adminId,
                'description' => $reason ?: 'Admin adjustment',
                'admin_id' => $adminId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'new_balance' => $newBalance];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================
    // Sender ID Pool and Assignment Methods
    // ============================================================

    /**
     * Admin: Add Sender ID to pool
     *
     * @param array $data
     * @return array
     */
    public static function addSenderIdToPool(array $data): array
    {
        try {
            $gateway = Capsule::table('mod_sms_gateways')
                ->where('id', $data['gateway_id'])
                ->first();

            if (!$gateway) {
                return ['success' => false, 'error' => 'Gateway not found'];
            }

            $poolId = Capsule::table('mod_sms_sender_id_pool')->insertGetId([
                'sender_id' => $data['sender_id'],
                'type' => $data['type'] ?? 'alphanumeric',
                'description' => $data['description'] ?? null,
                'gateway_id' => $data['gateway_id'],
                'country_codes' => isset($data['country_codes']) ? json_encode($data['country_codes']) : null,
                'price_setup' => $data['price_setup'] ?? 0,
                'price_monthly' => $data['price_monthly'] ?? 0,
                'price_yearly' => $data['price_yearly'] ?? 0,
                'requires_approval' => $data['requires_approval'] ?? true,
                'is_shared' => $data['is_shared'] ?? false,
                'status' => $data['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'pool_id' => $poolId];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Admin: Update Sender ID in pool
     *
     * @param int $poolId
     * @param array $data
     * @return array
     */
    public static function updateSenderIdPool(int $poolId, array $data): array
    {
        try {
            if (isset($data['country_codes']) && is_array($data['country_codes'])) {
                $data['country_codes'] = json_encode($data['country_codes']);
            }

            Capsule::table('mod_sms_sender_id_pool')
                ->where('id', $poolId)
                ->update(array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]));

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Admin: Delete Sender ID from pool
     *
     * @param int $poolId
     * @return array
     */
    public static function deleteSenderIdFromPool(int $poolId): array
    {
        try {
            // Check if assigned to any active clients
            $activeAssignments = Capsule::table('mod_sms_client_sender_ids')
                ->where('pool_id', $poolId)
                ->where('status', 'active')
                ->count();

            if ($activeAssignments > 0) {
                return ['success' => false, 'error' => 'Cannot delete: Sender ID is assigned to ' . $activeAssignments . ' active client(s)'];
            }

            Capsule::table('mod_sms_sender_id_pool')
                ->where('id', $poolId)
                ->delete();

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all sender IDs in pool
     *
     * @param array $filters
     * @return array
     */
    public static function getSenderIdPool(array $filters = []): array
    {
        $query = Capsule::table('mod_sms_sender_id_pool as p')
            ->leftJoin('mod_sms_gateways as g', 'p.gateway_id', '=', 'g.id')
            ->select([
                'p.*',
                'g.name as gateway_name',
            ]);

        if (!empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        if (!empty($filters['gateway_id'])) {
            $query->where('p.gateway_id', $filters['gateway_id']);
        }

        return $query->orderBy('p.sender_id')->get()->toArray();
    }

    /**
     * Get single sender ID from pool
     *
     * @param int $poolId
     * @return object|null
     */
    public static function getSenderIdPoolItem(int $poolId): ?object
    {
        return Capsule::table('mod_sms_sender_id_pool as p')
            ->leftJoin('mod_sms_gateways as g', 'p.gateway_id', '=', 'g.id')
            ->where('p.id', $poolId)
            ->select(['p.*', 'g.name as gateway_name'])
            ->first();
    }

    /**
     * Client: Request a new Sender ID
     *
     * @param int $clientId
     * @param array $data
     * @return array
     */
    public static function requestSenderId(int $clientId, array $data): array
    {
        try {
            // Check if already exists
            $existing = Capsule::table('mod_sms_client_sender_ids')
                ->where('client_id', $clientId)
                ->where('sender_id', $data['sender_id'])
                ->where('status', '!=', 'expired')
                ->first();

            if ($existing) {
                return ['success' => false, 'error' => 'You already have this Sender ID'];
            }

            // Check pending request
            $pendingRequest = Capsule::table('mod_sms_sender_id_requests')
                ->where('client_id', $clientId)
                ->where('sender_id', $data['sender_id'])
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($pendingRequest) {
                return ['success' => false, 'error' => 'You already have a pending request for this Sender ID'];
            }

            $setupFee = 0;
            $recurringFee = 0;
            $gatewayId = $data['gateway_id'] ?? null;

            if (!empty($data['pool_id'])) {
                $poolItem = Capsule::table('mod_sms_sender_id_pool')
                    ->where('id', $data['pool_id'])
                    ->where('status', 'active')
                    ->first();

                if ($poolItem) {
                    $setupFee = $poolItem->price_setup;
                    $gatewayId = $poolItem->gateway_id;

                    switch ($data['billing_cycle'] ?? 'monthly') {
                        case 'yearly':
                            $recurringFee = $poolItem->price_yearly;
                            break;
                        case 'onetime':
                            $recurringFee = 0;
                            break;
                        default:
                            $recurringFee = $poolItem->price_monthly;
                    }
                }
            }

            $requestId = Capsule::table('mod_sms_sender_id_requests')->insertGetId([
                'client_id' => $clientId,
                'sender_id' => $data['sender_id'],
                'type' => $data['type'] ?? 'alphanumeric',
                'pool_id' => $data['pool_id'] ?? null,
                'gateway_id' => $gatewayId,
                'business_name' => $data['business_name'] ?? null,
                'use_case' => $data['use_case'] ?? null,
                'documents' => isset($data['documents']) ? json_encode($data['documents']) : null,
                'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
                'setup_fee' => $setupFee,
                'recurring_fee' => $recurringFee,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            logActivity("SMS Suite: New Sender ID request from client #{$clientId}: {$data['sender_id']}");

            return [
                'success' => true,
                'request_id' => $requestId,
                'message' => 'Your Sender ID request has been submitted for approval.',
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Admin: Approve Sender ID request
     *
     * @param int $requestId
     * @param int $adminId
     * @param array $options
     * @return array
     */
    public static function approveSenderIdRequest(int $requestId, int $adminId, array $options = []): array
    {
        try {
            $request = Capsule::table('mod_sms_sender_id_requests')
                ->where('id', $requestId)
                ->where('status', 'pending')
                ->first();

            if (!$request) {
                return ['success' => false, 'error' => 'Request not found or already processed'];
            }

            $setupFee = $options['setup_fee'] ?? $request->setup_fee;
            $recurringFee = $options['recurring_fee'] ?? $request->recurring_fee;
            $gatewayId = $options['gateway_id'] ?? $request->gateway_id;

            if (!$gatewayId) {
                return ['success' => false, 'error' => 'Gateway must be specified'];
            }

            $expiresAt = null;
            switch ($request->billing_cycle) {
                case 'monthly':
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
                    break;
                case 'yearly':
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 year'));
                    break;
            }

            $invoiceId = null;
            $totalFee = $setupFee + $recurringFee;

            if ($totalFee > 0) {
                $invoiceItems = [];
                $itemIndex = 1;

                if ($setupFee > 0) {
                    $invoiceItems["itemdescription{$itemIndex}"] = "Sender ID Setup Fee: {$request->sender_id}";
                    $invoiceItems["itemamount{$itemIndex}"] = $setupFee;
                    $invoiceItems["itemtaxed{$itemIndex}"] = true;
                    $itemIndex++;
                }

                if ($recurringFee > 0) {
                    $cycleLabel = ucfirst($request->billing_cycle);
                    $invoiceItems["itemdescription{$itemIndex}"] = "Sender ID {$cycleLabel} Fee: {$request->sender_id}";
                    $invoiceItems["itemamount{$itemIndex}"] = $recurringFee;
                    $invoiceItems["itemtaxed{$itemIndex}"] = true;
                }

                $invoiceResult = localAPI('CreateInvoice', array_merge([
                    'userid' => $request->client_id,
                    'sendinvoice' => true,
                    'paymentmethod' => '',
                ], $invoiceItems));

                if ($invoiceResult['result'] !== 'success') {
                    return ['success' => false, 'error' => 'Failed to create invoice'];
                }

                $invoiceId = $invoiceResult['invoiceid'];
            }

            Capsule::table('mod_sms_sender_id_requests')
                ->where('id', $requestId)
                ->update([
                    'status' => $invoiceId ? 'approved' : 'active',
                    'gateway_id' => $gatewayId,
                    'setup_fee' => $setupFee,
                    'recurring_fee' => $recurringFee,
                    'invoice_id' => $invoiceId,
                    'admin_notes' => $options['admin_notes'] ?? null,
                    'approved_by' => $adminId,
                    'approved_at' => date('Y-m-d H:i:s'),
                    'expires_at' => $expiresAt,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$invoiceId) {
                self::activateSenderId($requestId);
            }

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'message' => $invoiceId ? 'Request approved. Invoice created for client.' : 'Sender ID activated.',
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Admin: Reject Sender ID request
     *
     * @param int $requestId
     * @param int $adminId
     * @param string $reason
     * @return array
     */
    public static function rejectSenderIdRequest(int $requestId, int $adminId, string $reason = ''): array
    {
        try {
            $request = Capsule::table('mod_sms_sender_id_requests')
                ->where('id', $requestId)
                ->where('status', 'pending')
                ->first();

            if (!$request) {
                return ['success' => false, 'error' => 'Request not found or already processed'];
            }

            Capsule::table('mod_sms_sender_id_requests')
                ->where('id', $requestId)
                ->update([
                    'status' => 'rejected',
                    'admin_notes' => $reason,
                    'approved_by' => $adminId,
                    'approved_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return ['success' => true, 'message' => 'Request rejected.'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Activate Sender ID after payment or free approval
     *
     * @param int $requestId
     * @return array
     */
    public static function activateSenderId(int $requestId): array
    {
        try {
            $request = Capsule::table('mod_sms_sender_id_requests')
                ->where('id', $requestId)
                ->first();

            if (!$request) {
                return ['success' => false, 'error' => 'Request not found'];
            }

            $allocationId = Capsule::table('mod_sms_client_sender_ids')->insertGetId([
                'client_id' => $request->client_id,
                'pool_id' => $request->pool_id,
                'request_id' => $requestId,
                'sender_id' => $request->sender_id,
                'gateway_id' => $request->gateway_id,
                'is_default' => false,
                'status' => 'active',
                'expires_at' => $request->expires_at,
                'last_invoice_id' => $request->invoice_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Capsule::table('mod_sms_sender_id_requests')
                ->where('id', $requestId)
                ->update([
                    'status' => 'active',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            // Make default if client has no default
            $hasDefault = Capsule::table('mod_sms_client_sender_ids')
                ->where('client_id', $request->client_id)
                ->where('is_default', true)
                ->where('status', 'active')
                ->where('id', '!=', $allocationId)
                ->exists();

            if (!$hasDefault) {
                Capsule::table('mod_sms_client_sender_ids')
                    ->where('id', $allocationId)
                    ->update(['is_default' => true]);

                // Update client settings
                Capsule::table('mod_sms_settings')
                    ->updateOrInsert(
                        ['client_id' => $request->client_id],
                        [
                            'assigned_sender_id' => $request->sender_id,
                            'assigned_gateway_id' => $request->gateway_id,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
            }

            return [
                'success' => true,
                'allocation_id' => $allocationId,
                'message' => 'Sender ID activated successfully.',
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process Sender ID payment
     *
     * @param int $invoiceId
     * @return array
     */
    public static function processSenderIdPayment(int $invoiceId): array
    {
        try {
            $request = Capsule::table('mod_sms_sender_id_requests')
                ->where('invoice_id', $invoiceId)
                ->where('status', 'approved')
                ->first();

            if (!$request) {
                return ['success' => false, 'error' => 'No pending sender ID request found'];
            }

            return self::activateSenderId($request->id);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Admin: Manually assign sender ID to client
     *
     * @param int $clientId
     * @param int $poolId
     * @param array $options
     * @return array
     */
    public static function assignSenderIdToClient(int $clientId, int $poolId, array $options = []): array
    {
        try {
            $poolItem = Capsule::table('mod_sms_sender_id_pool')
                ->where('id', $poolId)
                ->where('status', 'active')
                ->first();

            if (!$poolItem) {
                return ['success' => false, 'error' => 'Sender ID not found in pool'];
            }

            $existing = Capsule::table('mod_sms_client_sender_ids')
                ->where('client_id', $clientId)
                ->where('sender_id', $poolItem->sender_id)
                ->where('gateway_id', $poolItem->gateway_id)
                ->where('status', 'active')
                ->first();

            if ($existing) {
                return ['success' => false, 'error' => 'Sender ID already assigned to this client'];
            }

            $expiresAt = null;
            if (!empty($options['expires_at'])) {
                $expiresAt = $options['expires_at'];
            } elseif (!empty($options['validity_days'])) {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$options['validity_days']} days"));
            }

            $allocationId = Capsule::table('mod_sms_client_sender_ids')->insertGetId([
                'client_id' => $clientId,
                'pool_id' => $poolId,
                'request_id' => null,
                'sender_id' => $poolItem->sender_id,
                'gateway_id' => $poolItem->gateway_id,
                'is_default' => $options['is_default'] ?? false,
                'status' => 'active',
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($options['is_default'] ?? false) {
                self::setClientDefaultSenderId($clientId, $allocationId);
            }

            return [
                'success' => true,
                'allocation_id' => $allocationId,
                'message' => 'Sender ID assigned successfully.',
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Set client's default sender ID
     *
     * @param int $clientId
     * @param int $allocationId
     * @return array
     */
    public static function setClientDefaultSenderId(int $clientId, int $allocationId): array
    {
        try {
            Capsule::table('mod_sms_client_sender_ids')
                ->where('client_id', $clientId)
                ->update(['is_default' => false]);

            $allocation = Capsule::table('mod_sms_client_sender_ids')
                ->where('id', $allocationId)
                ->where('client_id', $clientId)
                ->first();

            if (!$allocation) {
                return ['success' => false, 'error' => 'Sender ID allocation not found'];
            }

            Capsule::table('mod_sms_client_sender_ids')
                ->where('id', $allocationId)
                ->update(['is_default' => true]);

            Capsule::table('mod_sms_settings')
                ->updateOrInsert(
                    ['client_id' => $clientId],
                    [
                        'assigned_sender_id' => $allocation->sender_id,
                        'assigned_gateway_id' => $allocation->gateway_id,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get client's active sender IDs
     *
     * @param int $clientId
     * @return array
     */
    public static function getClientSenderIds(int $clientId): array
    {
        return Capsule::table('mod_sms_client_sender_ids as csi')
            ->leftJoin('mod_sms_gateways as g', 'csi.gateway_id', '=', 'g.id')
            ->where('csi.client_id', $clientId)
            ->where('csi.status', 'active')
            ->select([
                'csi.id',
                'csi.sender_id',
                'csi.is_default',
                'csi.expires_at',
                'csi.status',
                // Note: gateway_name is NOT exposed to clients for security
            ])
            ->get()
            ->toArray();
    }

    /**
     * Get client's active sender IDs with gateway info (admin only)
     *
     * @param int $clientId
     * @return array
     */
    public static function getClientSenderIdsAdmin(int $clientId): array
    {
        return Capsule::table('mod_sms_client_sender_ids as csi')
            ->leftJoin('mod_sms_gateways as g', 'csi.gateway_id', '=', 'g.id')
            ->where('csi.client_id', $clientId)
            ->select([
                'csi.*',
                'g.name as gateway_name',
            ])
            ->orderBy('csi.is_default', 'desc')
            ->orderBy('csi.created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get pending sender ID requests (admin)
     *
     * @return array
     */
    public static function getPendingSenderIdRequests(): array
    {
        return Capsule::table('mod_sms_sender_id_requests as r')
            ->leftJoin('tblclients as c', 'r.client_id', '=', 'c.id')
            ->where('r.status', 'pending')
            ->select([
                'r.*',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email',
            ])
            ->orderBy('r.created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get all sender ID requests (admin)
     *
     * @param array $filters
     * @return array
     */
    public static function getSenderIdRequests(array $filters = []): array
    {
        $query = Capsule::table('mod_sms_sender_id_requests as r')
            ->leftJoin('tblclients as c', 'r.client_id', '=', 'c.id')
            ->leftJoin('mod_sms_gateways as g', 'r.gateway_id', '=', 'g.id')
            ->select([
                'r.*',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email',
                'g.name as gateway_name',
            ]);

        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        if (!empty($filters['client_id'])) {
            $query->where('r.client_id', $filters['client_id']);
        }

        return $query->orderBy('r.created_at', 'desc')->get()->toArray();
    }

    /**
     * Convert currency
     *
     * @param float $amount
     * @param int $fromCurrencyId
     * @param int $toCurrencyId
     * @return float
     */
    private static function convertCurrency(float $amount, int $fromCurrencyId, int $toCurrencyId): float
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return $amount;
        }

        $fromCurrency = Capsule::table('tblcurrencies')->where('id', $fromCurrencyId)->first();
        $toCurrency = Capsule::table('tblcurrencies')->where('id', $toCurrencyId)->first();

        if (!$fromCurrency || !$toCurrency) {
            return $amount;
        }

        $baseAmount = $amount / $fromCurrency->rate;
        return round($baseAmount * $toCurrency->rate, 2);
    }

    /**
     * Generate renewal invoices for expiring sender IDs
     *
     * @param int $daysBeforeExpiry
     * @return array
     */
    public static function generateSenderIdRenewals(int $daysBeforeExpiry = 7): array
    {
        try {
            $expiryDate = date('Y-m-d H:i:s', strtotime("+{$daysBeforeExpiry} days"));

            $expiringIds = Capsule::table('mod_sms_client_sender_ids as csi')
                ->leftJoin('mod_sms_sender_id_requests as r', 'csi.request_id', '=', 'r.id')
                ->where('csi.status', 'active')
                ->whereNotNull('csi.expires_at')
                ->where('csi.expires_at', '<=', $expiryDate)
                ->where('csi.expires_at', '>', date('Y-m-d H:i:s'))
                ->select(['csi.*', 'r.billing_cycle', 'r.recurring_fee'])
                ->get();

            $invoicesCreated = 0;

            foreach ($expiringIds as $senderId) {
                if (!$senderId->recurring_fee || $senderId->recurring_fee <= 0) {
                    continue;
                }

                // Check if renewal already exists
                $existingRenewal = Capsule::table('mod_sms_sender_id_billing')
                    ->where('client_sender_id', $senderId->id)
                    ->where('billing_type', 'renewal')
                    ->where('status', 'pending')
                    ->exists();

                if ($existingRenewal) {
                    continue;
                }

                $newExpiry = null;
                switch ($senderId->billing_cycle) {
                    case 'monthly':
                        $newExpiry = date('Y-m-d H:i:s', strtotime($senderId->expires_at . ' +1 month'));
                        break;
                    case 'yearly':
                        $newExpiry = date('Y-m-d H:i:s', strtotime($senderId->expires_at . ' +1 year'));
                        break;
                }

                if (!$newExpiry) {
                    continue;
                }

                $invoiceResult = localAPI('CreateInvoice', [
                    'userid' => $senderId->client_id,
                    'sendinvoice' => true,
                    'itemdescription1' => "Sender ID Renewal: {$senderId->sender_id}",
                    'itemamount1' => $senderId->recurring_fee,
                    'itemtaxed1' => true,
                ]);

                if ($invoiceResult['result'] === 'success') {
                    Capsule::table('mod_sms_sender_id_billing')->insert([
                        'client_id' => $senderId->client_id,
                        'client_sender_id' => $senderId->id,
                        'invoice_id' => $invoiceResult['invoiceid'],
                        'billing_type' => 'renewal',
                        'amount' => $senderId->recurring_fee,
                        'status' => 'pending',
                        'period_start' => $senderId->expires_at,
                        'period_end' => $newExpiry,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    $invoicesCreated++;
                }
            }

            return ['success' => true, 'invoices_created' => $invoicesCreated];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process sender ID renewal payment
     *
     * @param int $invoiceId
     * @return array
     */
    public static function processSenderIdRenewalPayment(int $invoiceId): array
    {
        try {
            $billing = Capsule::table('mod_sms_sender_id_billing')
                ->where('invoice_id', $invoiceId)
                ->where('billing_type', 'renewal')
                ->where('status', 'pending')
                ->first();

            if (!$billing) {
                return ['success' => false, 'error' => 'No pending renewal found'];
            }

            Capsule::table('mod_sms_sender_id_billing')
                ->where('id', $billing->id)
                ->update([
                    'status' => 'paid',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            Capsule::table('mod_sms_client_sender_ids')
                ->where('id', $billing->client_sender_id)
                ->update([
                    'expires_at' => $billing->period_end,
                    'last_invoice_id' => $invoiceId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return ['success' => true, 'new_expiry' => $billing->period_end];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Expire sender IDs that are past their expiry date
     *
     * @return array
     */
    public static function expireOverdueSenderIds(): array
    {
        try {
            $expired = Capsule::table('mod_sms_client_sender_ids')
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', date('Y-m-d H:i:s'))
                ->update([
                    'status' => 'expired',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return ['success' => true, 'expired_count' => $expired];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
