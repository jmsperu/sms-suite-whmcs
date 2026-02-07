<?php
/**
 * SMS Suite - WHMCS Provisioning Module
 *
 * This module allows you to sell SMS credits and Sender IDs as WHMCS products.
 * Clients order through the standard WHMCS cart and credits are provisioned automatically.
 *
 * Product Types:
 * - SMS Credit Package: One-time or recurring SMS credits
 * - Sender ID Subscription: Monthly/yearly sender ID rental
 *
 * @package    SMSSuite
 * @author     SMS Suite
 * @copyright  2024
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Module metadata
 */
function sms_suite_MetaData()
{
    return [
        'DisplayName' => 'SMS Suite',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
        'DefaultNonSSLPort' => '',
        'DefaultSSLPort' => '',
    ];
}

/**
 * Module configuration options
 * These appear when creating a product
 *
 * IMPORTANT: WHMCS requires:
 * - Numeric array keys (1-24 for configoption1-configoption24)
 * - 'Options' must be comma-separated string, not array
 */
function sms_suite_ConfigOptions()
{
    return [
        // configoption1
        'Product Type' => [
            'Type' => 'dropdown',
            'Options' => 'sms_credits,sender_id',
            'Description' => 'sms_credits = SMS Credit Package, sender_id = Sender ID Subscription',
            'Default' => 'sms_credits',
        ],
        // configoption2
        'SMS Credits' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '100',
            'Description' => 'Number of SMS credits to provision (for SMS packages)',
        ],
        // configoption3
        'Bonus Credits' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '0',
            'Description' => 'Bonus credits to add (for SMS packages)',
        ],
        // configoption4
        'Credit Validity (Days)' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '365',
            'Description' => 'Days until credits expire (0 = never)',
        ],
        // configoption5
        'Sender ID' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Pre-assigned Sender ID (leave blank for client to choose)',
        ],
        // configoption6
        'Sender ID Type' => [
            'Type' => 'dropdown',
            'Options' => 'alphanumeric,numeric,shortcode',
            'Description' => 'Type of Sender ID',
            'Default' => 'alphanumeric',
        ],
        // configoption7
        'Network' => [
            'Type' => 'dropdown',
            'Options' => 'all,safaricom,airtel,telkom',
            'Description' => 'Network for Sender ID (Safaricom, Airtel, Telkom, or All)',
            'Default' => 'all',
        ],
        // configoption8
        'Gateway ID' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Assign specific gateway ID (optional)',
        ],
        // configoption9
        'Monthly SMS Limit' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '0',
            'Description' => 'Monthly SMS sending limit (0 = unlimited)',
        ],
    ];
}

/**
 * Custom fields for the product
 * These are shown to the client during order and stored per service
 */
function sms_suite_CustomFields()
{
    return [
        'Sender ID' => [
            'Type' => 'text',
            'Size' => '15',
            'Default' => '',
            'Required' => false,
            'Description' => 'Your preferred Sender ID (3-11 alphanumeric characters)',
        ],
        'Network' => [
            'Type' => 'dropdown',
            'Options' => 'all,safaricom,airtel,telkom',
            'Default' => 'all',
            'Required' => false,
            'Description' => 'Select network for this Sender ID',
        ],
    ];
}

/**
 * Provision the service - Called when order is paid
 */
function sms_suite_CreateAccount(array $params)
{
    try {
        $clientId = $params['clientsdetails']['userid'];
        $serviceId = $params['serviceid'];
        $productType = $params['configoption1'] ?? 'sms_credits';
        $smsCredits = (int)($params['configoption2'] ?? 100);
        $bonusCredits = (int)($params['configoption3'] ?? 0);
        $validityDays = (int)($params['configoption4'] ?? 365);
        $senderId = $params['configoption5'] ?? '';
        $senderIdType = $params['configoption6'] ?? 'alphanumeric';
        $network = $params['configoption7'] ?? 'all';
        $gatewayId = $params['configoption8'] ?? '';
        $monthlyLimit = (int)($params['configoption9'] ?? 0);

        // Get custom field values
        $customSenderId = '';
        $customNetwork = '';
        if (isset($params['customfields']) && is_array($params['customfields'])) {
            foreach ($params['customfields'] as $field) {
                if ($field['name'] === 'Sender ID' && !empty($field['value'])) {
                    $customSenderId = $field['value'];
                }
                if ($field['name'] === 'Network' && !empty($field['value'])) {
                    $customNetwork = $field['value'];
                }
            }
        }

        // Ensure client settings exist
        sms_suite_ensure_client_settings($clientId);

        if ($productType === 'sms_credits') {
            // Provision SMS Credits
            $totalCredits = $smsCredits + $bonusCredits;
            $expiresAt = $validityDays > 0
                ? date('Y-m-d H:i:s', strtotime("+{$validityDays} days"))
                : null;

            // Add credits to client's balance using INSERT ON DUPLICATE KEY UPDATE
            Capsule::statement("INSERT INTO mod_sms_credit_balance (client_id, balance, total_purchased, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE balance = balance + ?, total_purchased = total_purchased + ?, updated_at = NOW()",
                [$clientId, $totalCredits, $totalCredits, $totalCredits, $totalCredits]
            );

            // Record transaction
            $currentBalance = Capsule::table('mod_sms_credit_balance')->where('client_id', $clientId)->value('balance') ?? 0;
            Capsule::table('mod_sms_credit_transactions')->insert([
                'client_id' => $clientId,
                'type' => 'package_purchase',
                'credits' => $totalCredits,
                'balance_before' => $currentBalance - $totalCredits,
                'balance_after' => $currentBalance,
                'description' => "SMS Package: {$smsCredits} credits" . ($bonusCredits > 0 ? " + {$bonusCredits} bonus" : ''),
                'reference_type' => 'service',
                'reference_id' => $serviceId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create credit allocation record (for tracking credits per package/sender ID)
            Capsule::table('mod_sms_credit_allocations')->insert([
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'total_credits' => $totalCredits,
                'remaining_credits' => $totalCredits,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Store service data
            Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->update([
                    'username' => $totalCredits . ' SMS Credits',
                    'dedicatedip' => $expiresAt ? "Expires: " . date('Y-m-d', strtotime($expiresAt)) : 'Never expires',
                ]);

            // Update monthly limit if set
            if ($monthlyLimit > 0) {
                Capsule::table('mod_sms_settings')
                    ->where('client_id', $clientId)
                    ->update(['monthly_limit' => $monthlyLimit]);
            }

            logActivity("SMS Suite: Provisioned {$totalCredits} SMS credits for client #{$clientId} (Service #{$serviceId})");

        } elseif ($productType === 'sender_id') {
            // Provision Sender ID
            $senderIdValue = $customSenderId ?: $senderId;
            $networkValue = $customNetwork ?: $network;

            if (empty($senderIdValue)) {
                return 'Sender ID not specified. Please configure a Sender ID.';
            }

            // Check if sender ID already exists for another client on the same network
            $existing = Capsule::table('mod_sms_client_sender_ids')
                ->where('sender_id', $senderIdValue)
                ->where('network', $networkValue)
                ->where('client_id', '!=', $clientId)
                ->where('status', 'active')
                ->first();

            if ($existing) {
                return "This Sender ID is already in use by another client on {$networkValue} network.";
            }

            // Create or update sender ID assignment
            $existingAssignment = Capsule::table('mod_sms_client_sender_ids')
                ->where('client_id', $clientId)
                ->where('sender_id', $senderIdValue)
                ->where('network', $networkValue)
                ->first();

            $billingCycle = $params['model']['billingcycle'] ?? 'annually';
            $expiryInterval = $billingCycle === 'monthly' ? '+1 month' : '+1 year';

            if ($existingAssignment) {
                Capsule::table('mod_sms_client_sender_ids')
                    ->where('id', $existingAssignment->id)
                    ->update([
                        'status' => 'active',
                        'service_id' => $serviceId,
                        'expires_at' => date('Y-m-d H:i:s', strtotime($expiryInterval)),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                Capsule::table('mod_sms_client_sender_ids')->insert([
                    'client_id' => $clientId,
                    'sender_id' => $senderIdValue,
                    'type' => $senderIdType,
                    'network' => $networkValue,
                    'status' => 'active',
                    'service_id' => $serviceId,
                    'gateway_id' => $gatewayId ?: null,
                    'expires_at' => date('Y-m-d H:i:s', strtotime($expiryInterval)),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Store service data
            Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->update([
                    'username' => $senderIdValue,
                    'dedicatedip' => ucfirst($networkValue) . ' - ' . ucfirst($senderIdType),
                ]);

            logActivity("SMS Suite: Provisioned Sender ID '{$senderIdValue}' for {$networkValue} network for client #{$clientId} (Service #{$serviceId})");
        }

        return 'success';

    } catch (Exception $e) {
        logActivity("SMS Suite Provisioning Error: " . $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Suspend the service
 */
function sms_suite_SuspendAccount(array $params)
{
    try {
        $clientId = $params['clientsdetails']['userid'];
        $serviceId = $params['serviceid'];
        $productType = $params['configoption1'] ?? 'sms_credits';

        if ($productType === 'sender_id') {
            // Suspend sender ID
            Capsule::table('mod_sms_client_sender_ids')
                ->where('service_id', $serviceId)
                ->update([
                    'status' => 'suspended',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            logActivity("SMS Suite: Suspended Sender ID for service #{$serviceId}");
        }

        // Disable SMS sending for client
        Capsule::table('mod_sms_settings')
            ->where('client_id', $clientId)
            ->update(['api_enabled' => false]);

        return 'success';

    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Unsuspend the service
 */
function sms_suite_UnsuspendAccount(array $params)
{
    try {
        $clientId = $params['clientsdetails']['userid'];
        $serviceId = $params['serviceid'];
        $productType = $params['configoption1'] ?? 'sms_credits';

        if ($productType === 'sender_id') {
            // Reactivate sender ID
            Capsule::table('mod_sms_client_sender_ids')
                ->where('service_id', $serviceId)
                ->update([
                    'status' => 'active',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            logActivity("SMS Suite: Unsuspended Sender ID for service #{$serviceId}");
        }

        // Re-enable SMS sending
        Capsule::table('mod_sms_settings')
            ->where('client_id', $clientId)
            ->update(['api_enabled' => true]);

        return 'success';

    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Terminate the service
 */
function sms_suite_TerminateAccount(array $params)
{
    try {
        $clientId = $params['clientsdetails']['userid'];
        $serviceId = $params['serviceid'];
        $productType = $params['configoption1'] ?? 'sms_credits';

        if ($productType === 'sender_id') {
            // Remove sender ID
            Capsule::table('mod_sms_client_sender_ids')
                ->where('service_id', $serviceId)
                ->update([
                    'status' => 'terminated',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            logActivity("SMS Suite: Terminated Sender ID for service #{$serviceId}");
        }

        // Note: We don't remove credits on termination - they were already paid for

        return 'success';

    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Renew the service - Called on recurring payment
 */
function sms_suite_Renew(array $params)
{
    try {
        $clientId = $params['clientsdetails']['userid'];
        $serviceId = $params['serviceid'];
        $productType = $params['configoption1'] ?? 'sms_credits';
        $smsCredits = (int)($params['configoption2'] ?? 100);
        $bonusCredits = (int)($params['configoption3'] ?? 0);
        $validityDays = (int)($params['configoption4'] ?? 365);

        if ($productType === 'sms_credits') {
            // Add more credits on renewal
            $totalCredits = $smsCredits + $bonusCredits;

            $balanceBefore = Capsule::table('mod_sms_credit_balance')->where('client_id', $clientId)->value('balance') ?? 0;

            Capsule::statement("INSERT INTO mod_sms_credit_balance (client_id, balance, total_purchased, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE balance = balance + ?, total_purchased = total_purchased + ?, updated_at = NOW()",
                [$clientId, $totalCredits, $totalCredits, $totalCredits, $totalCredits]
            );

            $balanceAfter = $balanceBefore + $totalCredits;

            // Record transaction
            Capsule::table('mod_sms_credit_transactions')->insert([
                'client_id' => $clientId,
                'type' => 'package_renewal',
                'credits' => $totalCredits,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "SMS Package Renewal: {$smsCredits} credits" . ($bonusCredits > 0 ? " + {$bonusCredits} bonus" : ''),
                'reference_type' => 'service',
                'reference_id' => $serviceId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create credit allocation record for FIFO tracking
            $expiresAt = $validityDays > 0
                ? date('Y-m-d H:i:s', strtotime("+{$validityDays} days"))
                : null;
            Capsule::table('mod_sms_credit_allocations')->insert([
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'total_credits' => $totalCredits,
                'remaining_credits' => $totalCredits,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Reset monthly usage counter
            Capsule::table('mod_sms_settings')
                ->where('client_id', $clientId)
                ->update(['monthly_used' => 0]);

            logActivity("SMS Suite: Renewed {$totalCredits} SMS credits for client #{$clientId} (Service #{$serviceId})");

        } elseif ($productType === 'sender_id') {
            // Extend sender ID validity
            Capsule::table('mod_sms_client_sender_ids')
                ->where('service_id', $serviceId)
                ->update([
                    'status' => 'active',
                    'expires_at' => Capsule::raw("DATE_ADD(COALESCE(expires_at, NOW()), INTERVAL 1 YEAR)"),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            logActivity("SMS Suite: Renewed Sender ID for service #{$serviceId}");
        }

        return 'success';

    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Change package/upgrade/downgrade
 */
function sms_suite_ChangePackage(array $params)
{
    // Get the difference in credits and add/remove
    $oldCredits = (int)($params['configoption2'] ?? 0);
    $newCredits = (int)($params['configoption2'] ?? 0);

    // For now, just log the change - actual credit adjustment happens on renewal
    logActivity("SMS Suite: Package change requested for service #{$params['serviceid']}");

    return 'success';
}

/**
 * Admin area service page output
 */
function sms_suite_AdminServicesTabFields(array $params)
{
    $clientId = $params['clientsdetails']['userid'];
    $serviceId = $params['serviceid'];
    $productType = $params['configoption1'] ?? 'sms_credits';

    // Get client's current credit balance
    $balance = Capsule::table('mod_sms_credit_balance')
        ->where('client_id', $clientId)
        ->value('balance') ?? 0;

    // Get sender IDs for this service
    $senderIds = Capsule::table('mod_sms_client_sender_ids')
        ->where('service_id', $serviceId)
        ->get();

    $fields = [
        'Current Credit Balance' => number_format($balance) . ' SMS',
    ];

    if ($productType === 'sender_id' && count($senderIds) > 0) {
        foreach ($senderIds as $sid) {
            $fields['Sender ID'] = $sid->sender_id . ' (' . ucfirst($sid->status) . ')';
            $fields['Sender ID Expires'] = $sid->expires_at ?? 'Never';
        }
    }

    return $fields;
}

/**
 * Client area service output
 */
function sms_suite_ClientArea(array $params)
{
    $clientId = $params['clientsdetails']['userid'];
    $serviceId = $params['serviceid'];
    $productType = $params['configoption1'] ?? 'sms_credits';

    // Get credit balance
    $balance = Capsule::table('mod_sms_credit_balance')
        ->where('client_id', $clientId)
        ->value('balance') ?? 0;

    // Get recent transactions
    $transactions = Capsule::table('mod_sms_credit_transactions')
        ->where('client_id', $clientId)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    // Get sender IDs
    $senderIds = Capsule::table('mod_sms_client_sender_ids')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->get();

    return [
        'templatefile' => 'clientarea',
        'vars' => [
            'product_type' => $productType,
            'credit_balance' => $balance,
            'transactions' => $transactions,
            'sender_ids' => $senderIds,
            'sms_suite_link' => 'index.php?m=sms_suite',
        ],
    ];
}

/**
 * Admin custom buttons
 */
function sms_suite_AdminCustomButtonArray()
{
    return [
        'Add Credits' => 'addCredits',
        'View Usage' => 'viewUsage',
    ];
}

/**
 * Add credits manually
 */
function sms_suite_addCredits(array $params)
{
    $clientId = $params['clientsdetails']['userid'];
    $credits = (int)($_POST['credits'] ?? 100);

    if ($credits <= 0) {
        return 'Please specify a valid number of credits';
    }

    $balanceBefore = Capsule::table('mod_sms_credit_balance')->where('client_id', $clientId)->value('balance') ?? 0;

    Capsule::statement("INSERT INTO mod_sms_credit_balance (client_id, balance, total_purchased, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE balance = balance + ?, total_purchased = total_purchased + ?, updated_at = NOW()",
        [$clientId, $credits, $credits, $credits, $credits]
    );

    Capsule::table('mod_sms_credit_transactions')->insert([
        'client_id' => $clientId,
        'type' => 'admin_add',
        'credits' => $credits,
        'balance_before' => $balanceBefore,
        'balance_after' => $balanceBefore + $credits,
        'description' => 'Credits added by admin',
        'reference_type' => 'manual',
        'reference_id' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    logActivity("SMS Suite: Admin added {$credits} credits for client #{$clientId}");

    return 'success';
}

/**
 * View usage - redirects to addon module
 */
function sms_suite_viewUsage(array $params)
{
    $clientId = $params['clientsdetails']['userid'];
    header("Location: addonmodules.php?module=sms_suite&action=client_messages&client_id={$clientId}");
    exit;
}

/**
 * Ensure client settings exist
 */
function sms_suite_ensure_client_settings($clientId)
{
    $exists = Capsule::table('mod_sms_settings')
        ->where('client_id', $clientId)
        ->exists();

    if (!$exists) {
        Capsule::table('mod_sms_settings')->insert([
            'client_id' => $clientId,
            'billing_mode' => 'plan',
            'api_enabled' => true,
            'accept_sms' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
