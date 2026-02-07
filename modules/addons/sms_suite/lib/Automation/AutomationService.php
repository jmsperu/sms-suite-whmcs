<?php
/**
 * SMS Suite - Automation Service
 *
 * Handles automated messaging based on WHMCS events
 */

namespace SMSSuite\Automation;

use WHMCS\Database\Capsule;
use SMSSuite\Core\MessageService;
use Exception;

class AutomationService
{
    /**
     * Available WHMCS hooks for automation
     */
    const AVAILABLE_HOOKS = [
        'ClientAdd' => 'New Client Registration',
        'ClientLogin' => 'Client Login',
        'InvoiceCreated' => 'Invoice Created',
        'InvoicePaid' => 'Invoice Paid',
        'InvoicePaymentReminder' => 'Payment Reminder',
        'OrderPaid' => 'Order Paid',
        'AfterModuleCreate' => 'Service Activated',
        'AfterModuleSuspend' => 'Service Suspended',
        'AfterModuleUnsuspend' => 'Service Unsuspended',
        'AfterModuleTerminate' => 'Service Terminated',
        'TicketOpen' => 'Ticket Opened',
        'TicketUserReply' => 'Ticket Reply (User)',
        'TicketAdminReply' => 'Ticket Reply (Admin)',
        'TicketStatusChange' => 'Ticket Status Changed',
        'DomainExpiryNotice' => 'Domain Expiry Notice',
    ];

    /**
     * Available template variables
     */
    const TEMPLATE_VARS = [
        'client' => ['firstname', 'lastname', 'companyname', 'email', 'phonenumber'],
        'invoice' => ['id', 'total', 'duedate', 'status'],
        'service' => ['domain', 'dedicatedip', 'username'],
        'ticket' => ['id', 'subject', 'status', 'department'],
        'order' => ['id', 'ordernum', 'amount'],
    ];

    /**
     * Create automation trigger
     *
     * @param array $data
     * @return array
     */
    public static function create(array $data): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Name is required'];
        }

        if (empty($data['trigger_type'])) {
            return ['success' => false, 'error' => 'Trigger type is required'];
        }

        try {
            $id = Capsule::table('mod_sms_automations')->insertGetId([
                'name' => $data['name'],
                'trigger_type' => $data['trigger_type'],
                'trigger_config' => is_string($data['trigger_config'] ?? '') ? ($data['trigger_config'] ?? '{}') : json_encode($data['trigger_config'] ?? []),
                'message_template' => $data['message_template'] ?? '',
                'sender_id' => $data['sender_id'] ?? null,
                'gateway_id' => $data['gateway_id'] ?? null,
                'status' => $data['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update automation trigger
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public static function update(int $id, array $data): array
    {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        $allowedFields = ['name', 'trigger_type', 'message_template',
                          'sender_id', 'gateway_id', 'status'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['trigger_config'])) {
            $updateData['trigger_config'] = is_string($data['trigger_config'])
                ? $data['trigger_config']
                : json_encode($data['trigger_config']);
        }

        Capsule::table('mod_sms_automations')
            ->where('id', $id)
            ->update($updateData);

        return ['success' => true];
    }

    /**
     * Delete automation trigger
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        return Capsule::table('mod_sms_automations')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Get all automations
     *
     * @return array
     */
    public static function getAll(): array
    {
        return Capsule::table('mod_sms_automations')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Execute automation for a hook event
     *
     * @param string $hook
     * @param array $vars Event variables from WHMCS
     * @return array Results
     */
    public static function execute(string $hook, array $vars): array
    {
        $results = [];

        // Find active automations for this hook
        $automations = Capsule::table('mod_sms_automations')
            ->where('trigger_type', 'whmcs_hook')
            ->where('status', 'active')
            ->get();

        foreach ($automations as $automation) {
            try {
                // Check if this automation matches the specific hook
                $config = json_decode($automation->trigger_config, true) ?: [];
                if (($config['hook'] ?? '') !== $hook) {
                    continue;
                }

                // Get recipient phone number
                $phone = self::getRecipientPhone($hook, $vars);
                if (empty($phone)) {
                    $results[] = [
                        'automation_id' => $automation->id,
                        'success' => false,
                        'error' => 'No phone number found',
                    ];
                    continue;
                }

                // Get client ID
                $clientId = self::getClientId($hook, $vars);

                // Parse message template
                $message = self::parseTemplate($automation->message_template, $vars);

                // Send message
                require_once dirname(__DIR__) . '/Core/SegmentCounter.php';
                require_once dirname(__DIR__) . '/Core/MessageService.php';

                $result = MessageService::send($clientId, $phone, $message, [
                    'channel' => 'sms',
                    'sender_id' => $automation->sender_id,
                    'gateway_id' => $automation->gateway_id,
                    'automation_id' => $automation->id,
                    'send_now' => true,
                ]);

                $results[] = [
                    'automation_id' => $automation->id,
                    'success' => $result['success'],
                    'message_id' => $result['message_id'] ?? null,
                    'error' => $result['error'] ?? null,
                ];

                // Log execution
                Capsule::table('mod_sms_automation_logs')->insert([
                    'automation_id' => $automation->id,
                    'trigger_data' => json_encode(['hook' => $hook, 'recipient' => $phone]),
                    'message_id' => $result['message_id'] ?? null,
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'error' => $result['error'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

            } catch (Exception $e) {
                $results[] = [
                    'automation_id' => $automation->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                logActivity("SMS Suite Automation Error ({$hook}): " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Check if conditions are met
     */
    private static function checkConditions(object $automation, array $vars): bool
    {
        $config = json_decode($automation->trigger_config, true) ?: [];
        $conditions = $config['conditions'] ?? [];

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? '';

            $actualValue = self::getNestedValue($vars, $field);

            switch ($operator) {
                case 'equals':
                    if ($actualValue != $value) return false;
                    break;
                case 'not_equals':
                    if ($actualValue == $value) return false;
                    break;
                case 'contains':
                    if (strpos($actualValue, $value) === false) return false;
                    break;
                case 'greater_than':
                    if ($actualValue <= $value) return false;
                    break;
                case 'less_than':
                    if ($actualValue >= $value) return false;
                    break;
            }
        }

        return true;
    }

    /**
     * Get recipient phone number from hook variables
     */
    private static function getRecipientPhone(string $hook, array $vars): string
    {
        // Try to get phone from various sources
        $clientId = self::getClientId($hook, $vars);

        if ($clientId) {
            $client = Capsule::table('tblclients')
                ->where('id', $clientId)
                ->first();

            if ($client && !empty($client->phonenumber)) {
                return $client->phonenumber;
            }
        }

        // Check direct phone in vars
        return $vars['phonenumber'] ?? $vars['phone'] ?? '';
    }

    /**
     * Get client ID from hook variables
     */
    private static function getClientId(string $hook, array $vars): int
    {
        return (int)($vars['userid'] ?? $vars['clientid'] ?? $vars['client_id'] ?? 0);
    }

    /**
     * Parse template with variables
     */
    private static function parseTemplate(string $template, array $vars): string
    {
        // Replace {var} style placeholders
        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($vars) {
            $key = $matches[1];
            $value = self::getNestedValue($vars, $key);
            return $value !== null ? $value : $matches[0];
        }, $template);
    }

    /**
     * Get nested value from array using dot notation
     */
    private static function getNestedValue(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } elseif (is_object($value) && isset($value->$k)) {
                $value = $value->$k;
            } else {
                return null;
            }
        }

        return $value;
    }
}
