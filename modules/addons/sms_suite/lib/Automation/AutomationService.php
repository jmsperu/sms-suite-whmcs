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

        if (empty($data['hook'])) {
            return ['success' => false, 'error' => 'Hook is required'];
        }

        if (!isset(self::AVAILABLE_HOOKS[$data['hook']])) {
            return ['success' => false, 'error' => 'Invalid hook'];
        }

        try {
            $id = Capsule::table('mod_sms_automations')->insertGetId([
                'name' => $data['name'],
                'hook' => $data['hook'],
                'channel' => $data['channel'] ?? 'sms',
                'template_id' => $data['template_id'] ?? null,
                'message' => $data['message'] ?? '',
                'sender_id' => $data['sender_id'] ?? null,
                'gateway_id' => $data['gateway_id'] ?? null,
                'conditions' => json_encode($data['conditions'] ?? []),
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

        $allowedFields = ['name', 'hook', 'channel', 'template_id', 'message',
                          'sender_id', 'gateway_id', 'status'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['conditions'])) {
            $updateData['conditions'] = json_encode($data['conditions']);
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
            ->where('hook', $hook)
            ->where('status', 'active')
            ->get();

        foreach ($automations as $automation) {
            try {
                // Check conditions
                if (!self::checkConditions($automation, $vars)) {
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
                $message = self::parseTemplate($automation->message, $vars);

                // If template_id is set, use template content
                if ($automation->template_id) {
                    $template = Capsule::table('mod_sms_templates')
                        ->where('id', $automation->template_id)
                        ->first();

                    if ($template) {
                        $message = self::parseTemplate($template->content, $vars);
                    }
                }

                // Send message
                require_once dirname(__DIR__) . '/Core/SegmentCounter.php';
                require_once dirname(__DIR__) . '/Core/MessageService.php';

                $result = MessageService::send($clientId, $phone, $message, [
                    'channel' => $automation->channel,
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
                    'hook' => $hook,
                    'recipient' => $phone,
                    'success' => $result['success'] ? 1 : 0,
                    'message_id' => $result['message_id'] ?? null,
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
        $conditions = json_decode($automation->conditions, true) ?: [];

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
