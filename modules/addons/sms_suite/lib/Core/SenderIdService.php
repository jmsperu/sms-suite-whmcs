<?php
/**
 * SMS Suite - Sender ID Service
 *
 * Handles sender ID requests, approval, and gateway binding
 */

namespace SMSSuite\Core;

use WHMCS\Database\Capsule;
use Exception;

class SenderIdService
{
    /**
     * Request a new sender ID
     *
     * @param int $clientId
     * @param string $senderId
     * @param string $type 'alphanumeric' or 'numeric'
     * @param array $options
     * @return array
     */
    public static function request(int $clientId, string $senderId, string $type = 'alphanumeric', array $options = []): array
    {
        // Validate sender ID format
        $validation = self::validate($senderId, $type);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Check if already exists for this client
        $exists = Capsule::table('mod_sms_sender_ids')
            ->where('client_id', $clientId)
            ->where('sender_id', $senderId)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($exists) {
            return ['success' => false, 'error' => 'This sender ID already exists or is pending approval.'];
        }

        // Get pricing
        $price = self::getPrice($type);

        // Create sender ID record
        $id = Capsule::table('mod_sms_sender_ids')->insertGetId([
            'client_id' => $clientId,
            'sender_id' => $senderId,
            'type' => $type,
            'status' => 'pending',
            'price' => $price,
            'documents' => json_encode($options['documents'] ?? []),
            'notes' => $options['notes'] ?? null,
            'gateway_bindings' => json_encode([]),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        logActivity("SMS Suite: Sender ID request - {$senderId} by client {$clientId}");

        // If auto-approve is enabled and no price, approve immediately
        $settings = self::getModuleSettings();
        if (($settings['auto_approve_senders'] ?? false) && $price == 0) {
            self::approve($id);
            return ['success' => true, 'id' => $id, 'status' => 'active'];
        }

        return ['success' => true, 'id' => $id, 'status' => 'pending', 'price' => $price];
    }

    /**
     * Approve a sender ID request
     *
     * @param int $id
     * @param array $options
     * @return bool
     */
    public static function approve(int $id, array $options = []): bool
    {
        $senderId = Capsule::table('mod_sms_sender_ids')->where('id', $id)->first();

        if (!$senderId || $senderId->status !== 'pending') {
            return false;
        }

        // Calculate validity date
        $validityDays = $options['validity_days'] ?? 365;
        $validityDate = date('Y-m-d', strtotime("+{$validityDays} days"));

        Capsule::table('mod_sms_sender_ids')
            ->where('id', $id)
            ->update([
                'status' => 'active',
                'validity_date' => $validityDate,
                'approved_at' => date('Y-m-d H:i:s'),
                'approved_by' => $options['admin_id'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        logActivity("SMS Suite: Sender ID approved - {$senderId->sender_id}");

        return true;
    }

    /**
     * Reject a sender ID request
     *
     * @param int $id
     * @param string|null $reason
     * @return bool
     */
    public static function reject(int $id, ?string $reason = null): bool
    {
        $senderId = Capsule::table('mod_sms_sender_ids')->where('id', $id)->first();

        if (!$senderId || $senderId->status !== 'pending') {
            return false;
        }

        Capsule::table('mod_sms_sender_ids')
            ->where('id', $id)
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        logActivity("SMS Suite: Sender ID rejected - {$senderId->sender_id}");

        return true;
    }

    /**
     * Bind sender ID to gateways
     *
     * @param int $id
     * @param array $gatewayIds
     * @return bool
     */
    public static function bindToGateways(int $id, array $gatewayIds): bool
    {
        $senderId = Capsule::table('mod_sms_sender_ids')->where('id', $id)->first();

        if (!$senderId) {
            return false;
        }

        Capsule::table('mod_sms_sender_ids')
            ->where('id', $id)
            ->update([
                'gateway_bindings' => json_encode($gatewayIds),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return true;
    }

    /**
     * Validate sender ID format
     *
     * @param string $senderId
     * @param string $type
     * @return array
     */
    public static function validate(string $senderId, string $type = 'alphanumeric'): array
    {
        $senderId = trim($senderId);

        if (empty($senderId)) {
            return ['valid' => false, 'error' => 'Sender ID cannot be empty.'];
        }

        if ($type === 'alphanumeric') {
            // Alphanumeric: 3-11 characters, letters and numbers only
            if (strlen($senderId) < 3 || strlen($senderId) > 11) {
                return ['valid' => false, 'error' => 'Alphanumeric sender ID must be 3-11 characters.'];
            }

            if (!preg_match('/^[A-Za-z0-9]+$/', $senderId)) {
                return ['valid' => false, 'error' => 'Alphanumeric sender ID can only contain letters and numbers.'];
            }

            // Must start with a letter
            if (!preg_match('/^[A-Za-z]/', $senderId)) {
                return ['valid' => false, 'error' => 'Alphanumeric sender ID must start with a letter.'];
            }
        } else {
            // Numeric: phone number format
            $cleaned = preg_replace('/[^0-9+]/', '', $senderId);

            if (strlen($cleaned) < 7 || strlen($cleaned) > 15) {
                return ['valid' => false, 'error' => 'Numeric sender ID must be 7-15 digits.'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Get sender ID price
     *
     * @param string $type
     * @return float
     */
    public static function getPrice(string $type): float
    {
        $settings = self::getModuleSettings();

        if ($type === 'alphanumeric') {
            return (float)($settings['sender_id_price_alpha'] ?? 0);
        }

        return (float)($settings['sender_id_price_numeric'] ?? 0);
    }

    /**
     * Create WHMCS invoice for sender ID
     *
     * @param int $senderId Sender ID record ID
     * @return int|null Invoice ID if created
     */
    public static function createInvoice(int $senderId): ?int
    {
        $record = Capsule::table('mod_sms_sender_ids')->where('id', $senderId)->first();

        if (!$record || $record->price <= 0) {
            return null;
        }

        // Check if invoice already exists
        if ($record->invoice_id) {
            return $record->invoice_id;
        }

        try {
            $result = localAPI('CreateInvoice', [
                'userid' => $record->client_id,
                'sendinvoice' => true,
                'itemdescription1' => "Sender ID Registration: {$record->sender_id}",
                'itemamount1' => $record->price,
                'itemtaxed1' => true,
            ]);

            if ($result['result'] === 'success') {
                $invoiceId = $result['invoiceid'];

                Capsule::table('mod_sms_sender_ids')
                    ->where('id', $senderId)
                    ->update([
                        'invoice_id' => $invoiceId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                return $invoiceId;
            }
        } catch (Exception $e) {
            logActivity("SMS Suite: Failed to create sender ID invoice - " . $e->getMessage());
        }

        return null;
    }

    /**
     * Handle invoice paid - activate sender ID
     *
     * @param int $invoiceId
     * @return void
     */
    public static function handleInvoicePaid(int $invoiceId): void
    {
        $senderId = Capsule::table('mod_sms_sender_ids')
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->first();

        if ($senderId) {
            self::approve($senderId->id);
        }
    }

    /**
     * Get sender IDs for client
     *
     * @param int $clientId
     * @param string|null $status Filter by status
     * @return array
     */
    public static function getClientSenderIds(int $clientId, ?string $status = null): array
    {
        $query = Capsule::table('mod_sms_sender_ids')
            ->where('client_id', $clientId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    /**
     * Get available sender IDs for sending (active and not expired)
     *
     * @param int $clientId
     * @param int|null $gatewayId Filter by gateway binding
     * @return array
     */
    public static function getAvailableForSending(int $clientId, ?int $gatewayId = null): array
    {
        $query = Capsule::table('mod_sms_sender_ids')
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('validity_date')
                  ->orWhere('validity_date', '>=', date('Y-m-d'));
            });

        $senderIds = $query->get();

        // Filter by gateway binding if specified
        if ($gatewayId) {
            $senderIds = $senderIds->filter(function ($sid) use ($gatewayId) {
                $bindings = json_decode($sid->gateway_bindings, true) ?: [];
                return empty($bindings) || in_array($gatewayId, $bindings);
            });
        }

        return $senderIds->values()->toArray();
    }

    /**
     * Check and expire old sender IDs
     *
     * @return int Number of expired
     */
    public static function expireOldSenderIds(): int
    {
        $expired = Capsule::table('mod_sms_sender_ids')
            ->where('status', 'active')
            ->whereNotNull('validity_date')
            ->where('validity_date', '<', date('Y-m-d'))
            ->update([
                'status' => 'expired',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($expired > 0) {
            logActivity("SMS Suite: Expired {$expired} sender ID(s)");
        }

        return $expired;
    }

    /**
     * Get module settings
     */
    private static function getModuleSettings(): array
    {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'sms_suite')
            ->pluck('value', 'setting')
            ->toArray();

        return $settings;
    }
}
