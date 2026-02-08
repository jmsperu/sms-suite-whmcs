<?php
/**
 * SMS Suite - Contact Service
 *
 * Handles contact and contact group management
 */

namespace SMSSuite\Contacts;

use WHMCS\Database\Capsule;
use Exception;

class ContactService
{
    /**
     * Create a new contact
     *
     * @param int $clientId
     * @param array $data
     * @return array
     */
    public static function createContact(int $clientId, array $data): array
    {
        $phone = self::normalizePhone($data['phone'] ?? '');

        if (empty($phone)) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        // Check for duplicate
        $exists = Capsule::table('mod_sms_contacts')
            ->where('client_id', $clientId)
            ->where('phone', $phone)
            ->exists();

        if ($exists) {
            return ['success' => false, 'error' => 'Contact with this phone number already exists'];
        }

        try {
            $id = Capsule::table('mod_sms_contacts')->insertGetId([
                'client_id' => $clientId,
                'group_id' => $data['group_id'] ?? null,
                'phone' => $phone,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'custom_data' => json_encode($data['custom_data'] ?? []),
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
     * Update a contact
     *
     * @param int $id
     * @param int $clientId
     * @param array $data
     * @return array
     */
    public static function updateContact(int $id, int $clientId, array $data): array
    {
        $contact = Capsule::table('mod_sms_contacts')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->first();

        if (!$contact) {
            return ['success' => false, 'error' => 'Contact not found'];
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['phone'])) {
            $phone = self::normalizePhone($data['phone']);
            if (!empty($phone)) {
                $updateData['phone'] = $phone;
            }
        }

        if (isset($data['first_name'])) $updateData['first_name'] = $data['first_name'];
        if (isset($data['last_name'])) $updateData['last_name'] = $data['last_name'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['group_id'])) $updateData['group_id'] = $data['group_id'];
        if (isset($data['custom_data'])) $updateData['custom_data'] = json_encode($data['custom_data']);
        if (isset($data['status'])) $updateData['status'] = $data['status'];

        Capsule::table('mod_sms_contacts')
            ->where('id', $id)
            ->update($updateData);

        return ['success' => true];
    }

    /**
     * Delete a contact
     *
     * @param int $id
     * @param int $clientId
     * @return bool
     */
    public static function deleteContact(int $id, int $clientId): bool
    {
        return Capsule::table('mod_sms_contacts')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->delete() > 0;
    }

    /**
     * Get contacts for client
     *
     * @param int $clientId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getContacts(int $clientId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = Capsule::table('mod_sms_contacts')
            ->where('client_id', $clientId);

        if (!empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', $search)
                  ->orWhere('first_name', 'like', $search)
                  ->orWhere('last_name', 'like', $search)
                  ->orWhere('email', 'like', $search);
            });
        }

        $total = $query->count();

        $contacts = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        return [
            'contacts' => $contacts,
            'total' => $total,
        ];
    }

    /**
     * Create a contact group
     *
     * @param int $clientId
     * @param string $name
     * @param string|null $description
     * @return array
     */
    public static function createGroup(int $clientId, string $name, ?string $description = null): array
    {
        if (empty($name)) {
            return ['success' => false, 'error' => 'Group name is required'];
        }

        try {
            $id = Capsule::table('mod_sms_contact_groups')->insertGetId([
                'client_id' => $clientId,
                'name' => $name,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get contact groups for client
     * Uses subquery to get contact counts in single query (avoids N+1)
     *
     * @param int $clientId
     * @return array
     */
    public static function getGroups(int $clientId): array
    {
        // Get groups with contact count in a single query using subquery
        $groups = Capsule::table('mod_sms_contact_groups as g')
            ->select([
                'g.*',
                Capsule::raw('(SELECT COUNT(*) FROM mod_sms_contacts WHERE group_id = g.id) as contact_count')
            ])
            ->where('g.client_id', $clientId)
            ->orderBy('g.name')
            ->get();

        return $groups->toArray();
    }

    /**
     * Delete a contact group
     *
     * @param int $id
     * @param int $clientId
     * @param bool $deleteContacts Also delete contacts in group
     * @return bool
     */
    public static function deleteGroup(int $id, int $clientId, bool $deleteContacts = false): bool
    {
        if ($deleteContacts) {
            Capsule::table('mod_sms_contacts')
                ->where('group_id', $id)
                ->where('client_id', $clientId)
                ->delete();
        } else {
            // Set contacts' group_id to null
            Capsule::table('mod_sms_contacts')
                ->where('group_id', $id)
                ->where('client_id', $clientId)
                ->update(['group_id' => null]);
        }

        return Capsule::table('mod_sms_contact_groups')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->delete() > 0;
    }

    /**
     * Import contacts from CSV
     *
     * @param int $clientId
     * @param string $csvData
     * @param int|null $groupId
     * @param array $mapping Column mapping ['phone' => 0, 'first_name' => 1, ...]
     * @return array
     */
    public static function importCsv(int $clientId, string $csvData, ?int $groupId = null, array $mapping = []): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $csvData);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Default mapping if not provided
        if (empty($mapping)) {
            $mapping = [
                'phone' => 0,
                'first_name' => 1,
                'last_name' => 2,
                'email' => 3,
            ];
        }

        foreach ($lines as $lineNum => $line) {
            if ($lineNum === 0 && self::isHeaderRow($line)) {
                continue; // Skip header
            }

            $line = trim($line);
            if (empty($line)) continue;

            $columns = str_getcsv($line);

            $phone = isset($mapping['phone']) && isset($columns[$mapping['phone']])
                ? self::normalizePhone($columns[$mapping['phone']])
                : '';

            if (empty($phone)) {
                $skipped++;
                continue;
            }

            // Check for duplicate
            $exists = Capsule::table('mod_sms_contacts')
                ->where('client_id', $clientId)
                ->where('phone', $phone)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            try {
                Capsule::table('mod_sms_contacts')->insert([
                    'client_id' => $clientId,
                    'group_id' => $groupId,
                    'phone' => $phone,
                    'first_name' => isset($mapping['first_name']) && isset($columns[$mapping['first_name']])
                        ? trim($columns[$mapping['first_name']]) : null,
                    'last_name' => isset($mapping['last_name']) && isset($columns[$mapping['last_name']])
                        ? trim($columns[$mapping['last_name']]) : null,
                    'email' => isset($mapping['email']) && isset($columns[$mapping['email']])
                        ? trim($columns[$mapping['email']]) : null,
                    'custom_data' => '[]',
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $imported++;

            } catch (Exception $e) {
                $errors[] = "Line {$lineNum}: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Export contacts to CSV
     *
     * @param int $clientId
     * @param int|null $groupId
     * @return string CSV content
     */
    public static function exportCsv(int $clientId, ?int $groupId = null): string
    {
        $query = Capsule::table('mod_sms_contacts')
            ->where('client_id', $clientId);

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        $contacts = $query->get();

        $output = "Phone,First Name,Last Name,Email,Status,Created\n";

        foreach ($contacts as $contact) {
            $output .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                $contact->phone,
                str_replace('"', '""', $contact->first_name ?? ''),
                str_replace('"', '""', $contact->last_name ?? ''),
                str_replace('"', '""', $contact->email ?? ''),
                $contact->status,
                $contact->created_at
            );
        }

        return $output;
    }

    /**
     * Check if CSV row is a header
     */
    private static function isHeaderRow(string $line): bool
    {
        $lower = strtolower($line);
        return strpos($lower, 'phone') !== false ||
               strpos($lower, 'mobile') !== false ||
               strpos($lower, 'name') !== false ||
               strpos($lower, 'email') !== false;
    }

    /**
     * Normalize phone number
     */
    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
            return '';
        }

        return $phone;
    }

    /**
     * Get contact phone numbers for a group (for campaigns)
     *
     * @param int $groupId
     * @param int $clientId
     * @return array
     */
    public static function getGroupPhones(int $groupId, int $clientId): array
    {
        return Capsule::table('mod_sms_contacts')
            ->where('group_id', $groupId)
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->pluck('phone')
            ->toArray();
    }
}
