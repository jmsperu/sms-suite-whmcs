<?php
/**
 * SMS Suite - Template Service
 *
 * Handles message templates with merge tags for personalization
 * Equivalent to Laravel's Tool::renderSMS() functionality
 */

namespace SMSSuite\Core;

use WHMCS\Database\Capsule;
use Exception;

class TemplateService
{
    /**
     * Available merge tags organized by category
     */
    const MERGE_TAGS = [
        'client' => [
            'first_name' => 'Client first name',
            'last_name' => 'Client last name',
            'full_name' => 'Client full name',
            'company' => 'Company name',
            'email' => 'Email address',
            'phone' => 'Phone number',
            'address1' => 'Address line 1',
            'address2' => 'Address line 2',
            'city' => 'City',
            'state' => 'State/Region',
            'postcode' => 'Postal code',
            'country' => 'Country',
            'credit' => 'Account credit',
            'currency' => 'Currency code',
        ],
        'contact' => [
            'first_name' => 'Contact first name',
            'last_name' => 'Contact last name',
            'phone' => 'Contact phone',
            'email' => 'Contact email',
            'custom_1' => 'Custom field 1',
            'custom_2' => 'Custom field 2',
            'custom_3' => 'Custom field 3',
            'custom_4' => 'Custom field 4',
            'custom_5' => 'Custom field 5',
        ],
        'invoice' => [
            'id' => 'Invoice ID',
            'num' => 'Invoice number',
            'date' => 'Invoice date',
            'duedate' => 'Due date',
            'total' => 'Total amount',
            'balance' => 'Balance due',
            'status' => 'Invoice status',
            'items' => 'Line items summary',
        ],
        'service' => [
            'id' => 'Service ID',
            'product' => 'Product name',
            'domain' => 'Domain/Hostname',
            'username' => 'Service username',
            'password' => 'Service password',
            'dedicated_ip' => 'Dedicated IP',
            'status' => 'Service status',
            'next_due_date' => 'Next due date',
            'billing_cycle' => 'Billing cycle',
            'recurring_amount' => 'Recurring amount',
        ],
        'ticket' => [
            'id' => 'Ticket ID',
            'subject' => 'Ticket subject',
            'department' => 'Department name',
            'status' => 'Ticket status',
            'priority' => 'Priority level',
            'last_reply' => 'Last reply date',
        ],
        'order' => [
            'id' => 'Order ID',
            'num' => 'Order number',
            'date' => 'Order date',
            'total' => 'Order total',
            'status' => 'Order status',
            'payment_method' => 'Payment method',
        ],
        'domain' => [
            'domain' => 'Domain name',
            'registration_date' => 'Registration date',
            'expiry_date' => 'Expiry date',
            'status' => 'Domain status',
            'registrar' => 'Registrar',
        ],
        'system' => [
            'company_name' => 'Your company name',
            'company_url' => 'Website URL',
            'support_email' => 'Support email',
            'support_phone' => 'Support phone',
            'date' => 'Current date',
            'time' => 'Current time',
        ],
    ];

    /**
     * Render a message template with merge tags
     *
     * @param string $template
     * @param array $data Contextual data with entity IDs
     * @return string
     */
    public static function render(string $template, array $data = []): string
    {
        // Support both {tag} and {{tag}} syntax
        $template = preg_replace_callback('/\{\{([^}]+)\}\}|\{([^}]+)\}/', function ($matches) use ($data) {
            // $matches[1] for {{tag}}, $matches[2] for {tag}
            $tag = trim($matches[1] ?: $matches[2]);
            $value = self::resolveTag($tag, $data);
            return $value !== null ? $value : $matches[0];
        }, $template);

        return $template;
    }

    /**
     * Resolve a single merge tag
     *
     * @param string $tag
     * @param array $data
     * @return string|null
     */
    private static function resolveTag(string $tag, array $data): ?string
    {
        // Direct data lookup first — if the caller provided the value, use it
        if (isset($data[$tag]) && $data[$tag] !== '') {
            return (string) $data[$tag];
        }

        // Handle dot notation (e.g., client.first_name)
        if (strpos($tag, '.') !== false) {
            list($category, $field) = explode('.', $tag, 2);
        } else {
            // Try to infer category from common field names
            $field = $tag;
            $category = self::inferCategory($tag);
        }

        $category = strtolower($category);
        $field = strtolower($field);

        switch ($category) {
            case 'client':
                return self::getClientValue($field, $data);
            case 'contact':
                return self::getContactValue($field, $data);
            case 'invoice':
                return self::getInvoiceValue($field, $data);
            case 'service':
            case 'hosting':
                return self::getServiceValue($field, $data);
            case 'ticket':
                return self::getTicketValue($field, $data);
            case 'order':
                return self::getOrderValue($field, $data);
            case 'domain':
                return self::getDomainValue($field, $data);
            case 'system':
            case 'company':
                return self::getSystemValue($field);
            default:
                // Try direct data lookup
                return $data[$tag] ?? $data[$field] ?? null;
        }
    }

    /**
     * Infer category from common field names
     */
    private static function inferCategory(string $field): string
    {
        $clientFields = ['first_name', 'last_name', 'full_name', 'firstname', 'lastname', 'fullname', 'company', 'email', 'phone', 'phonenumber'];
        $invoiceFields = ['invoiceid', 'invoice_id', 'duedate', 'due_date', 'total', 'balance'];
        $ticketFields = ['ticketid', 'ticket_id', 'subject', 'department', 'priority'];

        $field = strtolower($field);

        if (in_array($field, $clientFields)) return 'client';
        if (in_array($field, $invoiceFields)) return 'invoice';
        if (in_array($field, $ticketFields)) return 'ticket';

        return 'client'; // Default
    }

    /**
     * Get client field value
     */
    private static function getClientValue(string $field, array $data): ?string
    {
        $clientId = $data['client_id'] ?? $data['userid'] ?? $data['clientid'] ?? null;

        if (!$clientId) {
            return null;
        }

        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            return null;
        }

        $fieldMap = [
            'first_name' => 'firstname',
            'firstname' => 'firstname',
            'last_name' => 'lastname',
            'lastname' => 'lastname',
            'full_name' => ['firstname', 'lastname'],
            'fullname' => ['firstname', 'lastname'],
            'company' => 'companyname',
            'companyname' => 'companyname',
            'email' => 'email',
            'phone' => 'phonenumber',
            'phonenumber' => 'phonenumber',
            'address1' => 'address1',
            'address2' => 'address2',
            'city' => 'city',
            'state' => 'state',
            'postcode' => 'postcode',
            'country' => 'country',
            'credit' => 'credit',
            'currency' => 'currency',
        ];

        if (isset($fieldMap[$field])) {
            $dbField = $fieldMap[$field];
            if (is_array($dbField)) {
                // Concatenate fields (e.g., full name)
                $parts = [];
                foreach ($dbField as $f) {
                    if (!empty($client->$f)) {
                        $parts[] = $client->$f;
                    }
                }
                return implode(' ', $parts);
            }
            return $client->$dbField ?? null;
        }

        return $client->$field ?? null;
    }

    /**
     * Get contact (from mod_sms_contacts) field value
     */
    private static function getContactValue(string $field, array $data): ?string
    {
        $contactId = $data['contact_id'] ?? null;
        $phone = $data['phone'] ?? $data['to'] ?? $data['recipient'] ?? null;

        if (!$contactId && $phone) {
            // Try to find contact by phone
            $clientId = $data['client_id'] ?? null;
            $contact = Capsule::table('mod_sms_contacts')
                ->where('phone', $phone)
                ->when($clientId, function ($q) use ($clientId) {
                    return $q->where('client_id', $clientId);
                })
                ->first();
        } elseif ($contactId) {
            $contact = Capsule::table('mod_sms_contacts')->where('id', $contactId)->first();
        } else {
            return null;
        }

        if (!$contact) {
            return null;
        }

        $fieldMap = [
            'first_name' => 'first_name',
            'firstname' => 'first_name',
            'last_name' => 'last_name',
            'lastname' => 'last_name',
            'phone' => 'phone',
            'email' => 'email',
        ];

        // Handle custom fields
        if (strpos($field, 'custom_') === 0) {
            $customData = json_decode($contact->custom_data ?? '{}', true);
            $customNum = str_replace('custom_', '', $field);
            return $customData["field_{$customNum}"] ?? $customData[$field] ?? null;
        }

        $dbField = $fieldMap[$field] ?? $field;
        return $contact->$dbField ?? null;
    }

    /**
     * Get invoice field value
     */
    private static function getInvoiceValue(string $field, array $data): ?string
    {
        $invoiceId = $data['invoice_id'] ?? $data['invoiceid'] ?? null;

        if (!$invoiceId) {
            return null;
        }

        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) {
            return null;
        }

        $fieldMap = [
            'id' => 'id',
            'num' => 'invoicenum',
            'invoicenum' => 'invoicenum',
            'date' => 'date',
            'duedate' => 'duedate',
            'due_date' => 'duedate',
            'total' => 'total',
            'subtotal' => 'subtotal',
            'balance' => 'total', // Will subtract payments
            'status' => 'status',
            'paymentmethod' => 'paymentmethod',
        ];

        if ($field === 'balance') {
            $paid = Capsule::table('tblaccounts')
                ->where('invoiceid', $invoiceId)
                ->sum('amountin');
            return number_format($invoice->total - $paid, 2);
        }

        if ($field === 'items') {
            $items = Capsule::table('tblinvoiceitems')
                ->where('invoiceid', $invoiceId)
                ->pluck('description')
                ->toArray();
            return implode(', ', array_slice($items, 0, 3));
        }

        $dbField = $fieldMap[$field] ?? $field;

        if (in_array($dbField, ['total', 'subtotal'])) {
            return number_format($invoice->$dbField ?? 0, 2);
        }

        return $invoice->$dbField ?? null;
    }

    /**
     * Get service/hosting field value
     */
    private static function getServiceValue(string $field, array $data): ?string
    {
        $serviceId = $data['service_id'] ?? $data['serviceid'] ?? $data['relid'] ?? null;

        if (!$serviceId) {
            return null;
        }

        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) {
            return null;
        }

        $fieldMap = [
            'id' => 'id',
            'domain' => 'domain',
            'username' => 'username',
            'password' => 'password',
            'dedicated_ip' => 'dedicatedip',
            'dedicatedip' => 'dedicatedip',
            'status' => 'domainstatus',
            'next_due_date' => 'nextduedate',
            'nextduedate' => 'nextduedate',
            'billing_cycle' => 'billingcycle',
            'billingcycle' => 'billingcycle',
            'recurring_amount' => 'amount',
            'amount' => 'amount',
        ];

        if ($field === 'product') {
            $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
            return $product ? $product->name : null;
        }

        $dbField = $fieldMap[$field] ?? $field;

        if ($dbField === 'password') {
            // Decrypt WHMCS password
            try {
                return decrypt($service->password);
            } catch (Exception $e) {
                return '[encrypted]';
            }
        }

        if ($dbField === 'amount') {
            return number_format($service->$dbField ?? 0, 2);
        }

        return $service->$dbField ?? null;
    }

    /**
     * Get ticket field value
     */
    private static function getTicketValue(string $field, array $data): ?string
    {
        $ticketId = $data['ticket_id'] ?? $data['ticketid'] ?? $data['id'] ?? null;

        if (!$ticketId) {
            return null;
        }

        $ticket = Capsule::table('tbltickets')->where('id', $ticketId)->first();
        if (!$ticket) {
            return null;
        }

        $fieldMap = [
            'id' => 'tid',
            'tid' => 'tid',
            'subject' => 'title',
            'title' => 'title',
            'status' => 'status',
            'priority' => 'urgency',
            'urgency' => 'urgency',
            'last_reply' => 'lastreply',
            'lastreply' => 'lastreply',
        ];

        if ($field === 'department') {
            $dept = Capsule::table('tblticketdepartments')->where('id', $ticket->did)->first();
            return $dept ? $dept->name : null;
        }

        $dbField = $fieldMap[$field] ?? $field;
        return $ticket->$dbField ?? null;
    }

    /**
     * Get order field value
     */
    private static function getOrderValue(string $field, array $data): ?string
    {
        $orderId = $data['order_id'] ?? $data['orderid'] ?? null;

        if (!$orderId) {
            return null;
        }

        $order = Capsule::table('tblorders')->where('id', $orderId)->first();
        if (!$order) {
            return null;
        }

        $fieldMap = [
            'id' => 'id',
            'num' => 'ordernum',
            'ordernum' => 'ordernum',
            'date' => 'date',
            'total' => 'amount',
            'amount' => 'amount',
            'status' => 'status',
            'payment_method' => 'paymentmethod',
            'paymentmethod' => 'paymentmethod',
        ];

        $dbField = $fieldMap[$field] ?? $field;

        if ($dbField === 'amount') {
            return number_format($order->$dbField ?? 0, 2);
        }

        return $order->$dbField ?? null;
    }

    /**
     * Get domain field value
     */
    private static function getDomainValue(string $field, array $data): ?string
    {
        $domainId = $data['domain_id'] ?? $data['domainid'] ?? null;

        if (!$domainId) {
            return null;
        }

        $domain = Capsule::table('tbldomains')->where('id', $domainId)->first();
        if (!$domain) {
            return null;
        }

        $fieldMap = [
            'domain' => 'domain',
            'registration_date' => 'registrationdate',
            'registrationdate' => 'registrationdate',
            'expiry_date' => 'expirydate',
            'expirydate' => 'expirydate',
            'status' => 'status',
        ];

        if ($field === 'registrar') {
            return $domain->registrar ?? null;
        }

        $dbField = $fieldMap[$field] ?? $field;
        return $domain->$dbField ?? null;
    }

    /**
     * Get system/company field value
     */
    private static function getSystemValue(string $field): ?string
    {
        switch ($field) {
            case 'company_name':
            case 'companyname':
                return self::getWhmcsSetting('CompanyName');
            case 'company_url':
            case 'url':
            case 'website':
                return self::getWhmcsSetting('SystemURL');
            case 'support_email':
            case 'email':
                return self::getWhmcsSetting('Email');
            case 'support_phone':
            case 'phone':
                return self::getWhmcsSetting('Phone');
            case 'date':
                return date('Y-m-d');
            case 'time':
                return date('H:i:s');
            case 'datetime':
                return date('Y-m-d H:i:s');
            default:
                return null;
        }
    }

    /**
     * Get WHMCS configuration setting
     */
    private static function getWhmcsSetting(string $setting): ?string
    {
        $value = Capsule::table('tblconfiguration')
            ->where('setting', $setting)
            ->value('value');
        return $value ?: null;
    }

    /**
     * Create a new message template
     *
     * @param int $clientId
     * @param array $data
     * @return array
     */
    public static function create(int $clientId, array $data): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Template name is required'];
        }

        if (empty($data['content'])) {
            return ['success' => false, 'error' => 'Template content is required'];
        }

        try {
            $id = Capsule::table('mod_sms_templates')->insertGetId([
                'client_id' => $clientId,
                'name' => $data['name'],
                'category' => $data['category'] ?? 'general',
                'content' => $data['content'],
                'channel' => $data['channel'] ?? 'sms',
                'dlt_template_id' => $data['dlt_template_id'] ?? null,
                'is_default' => $data['is_default'] ?? 0,
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
     * Update a template
     */
    public static function update(int $id, int $clientId, array $data): array
    {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        $allowedFields = ['name', 'category', 'content', 'channel', 'dlt_template_id', 'is_default', 'status'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        Capsule::table('mod_sms_templates')
            ->where('id', $id)
            ->where(function ($q) use ($clientId) {
                $q->where('client_id', $clientId)
                  ->orWhere('client_id', 0); // System templates
            })
            ->update($updateData);

        return ['success' => true];
    }

    /**
     * Delete a template
     */
    public static function delete(int $id, int $clientId): bool
    {
        return Capsule::table('mod_sms_templates')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->delete() > 0;
    }

    /**
     * Get templates for client
     */
    public static function getTemplates(int $clientId, ?string $category = null): array
    {
        $query = Capsule::table('mod_sms_templates')
            ->where(function ($q) use ($clientId) {
                $q->where('client_id', $clientId)
                  ->orWhere('client_id', 0); // Include system templates
            })
            ->where('status', 'active');

        if ($category) {
            $query->where('category', $category);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    /**
     * Get available merge tags for documentation/UI
     */
    public static function getAvailableTags(): array
    {
        return self::MERGE_TAGS;
    }

    /**
     * Preview template with sample data
     */
    public static function preview(string $template, int $clientId = 0): string
    {
        $sampleData = [
            'client_id' => $clientId ?: 1,
            'invoice_id' => 1,
            'service_id' => 1,
            'ticket_id' => 1,
            'order_id' => 1,
        ];

        // Replace with sample values if real data not found
        $rendered = self::render($template, $sampleData);

        // Replace any remaining tags with sample values
        $sampleValues = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'company' => 'ACME Corp',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'total' => '99.99',
            'balance' => '50.00',
            'duedate' => date('Y-m-d', strtotime('+7 days')),
            'domain' => 'example.com',
            'subject' => 'Sample Ticket Subject',
            'date' => date('Y-m-d'),
        ];

        foreach ($sampleValues as $tag => $value) {
            $rendered = str_replace(['{' . $tag . '}', '{{' . $tag . '}}'], "[$value]", $rendered);
        }

        return $rendered;
    }
}
