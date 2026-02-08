<?php
/**
 * SMS Suite - Tag Service
 *
 * Handles contact tagging for segmentation and targeting
 */

namespace SMSSuite\Contacts;

use WHMCS\Database\Capsule;
use Exception;

class TagService
{
    /**
     * Create a new tag
     */
    public static function createTag(int $clientId, array $data): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Tag name is required'];
        }

        $name = trim($data['name']);
        if (strlen($name) > 50) {
            return ['success' => false, 'error' => 'Tag name must be 50 characters or less'];
        }

        // Check for duplicate
        $existing = Capsule::table('mod_sms_tags')
            ->where('client_id', $clientId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'A tag with this name already exists'];
        }

        try {
            $id = Capsule::table('mod_sms_tags')->insertGetId([
                'client_id' => $clientId,
                'name' => $name,
                'color' => $data['color'] ?? '#667eea',
                'description' => $data['description'] ?? null,
                'contact_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update an existing tag
     */
    public static function updateTag(int $tagId, int $clientId, array $data): array
    {
        $tag = Capsule::table('mod_sms_tags')
            ->where('id', $tagId)
            ->where('client_id', $clientId)
            ->first();

        if (!$tag) {
            return ['success' => false, 'error' => 'Tag not found'];
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (empty($name)) {
                return ['success' => false, 'error' => 'Tag name is required'];
            }
            // Check duplicate (exclude self)
            $existing = Capsule::table('mod_sms_tags')
                ->where('client_id', $clientId)
                ->where('name', $name)
                ->where('id', '!=', $tagId)
                ->first();
            if ($existing) {
                return ['success' => false, 'error' => 'A tag with this name already exists'];
            }
            $updateData['name'] = $name;
        }

        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        try {
            Capsule::table('mod_sms_tags')
                ->where('id', $tagId)
                ->update($updateData);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a tag and all its assignments
     */
    public static function deleteTag(int $tagId, int $clientId): bool
    {
        $tag = Capsule::table('mod_sms_tags')
            ->where('id', $tagId)
            ->where('client_id', $clientId)
            ->first();

        if (!$tag) {
            return false;
        }

        Capsule::table('mod_sms_contact_tags')->where('tag_id', $tagId)->delete();
        Capsule::table('mod_sms_tags')->where('id', $tagId)->delete();

        return true;
    }

    /**
     * Get all tags for a client (with contact counts)
     */
    public static function getTags(int $clientId): array
    {
        return Capsule::table('mod_sms_tags')
            ->where('client_id', $clientId)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get a single tag
     */
    public static function getTag(int $tagId, int $clientId): ?object
    {
        return Capsule::table('mod_sms_tags')
            ->where('id', $tagId)
            ->where('client_id', $clientId)
            ->first();
    }

    /**
     * Assign a tag to a contact (validates both belong to the same client)
     */
    public static function assignTag(int $contactId, int $tagId, int $clientId): bool
    {
        // Verify tag belongs to client
        $tag = Capsule::table('mod_sms_tags')->where('id', $tagId)->where('client_id', $clientId)->first();
        if (!$tag) return false;

        // Verify contact belongs to client
        $contact = Capsule::table('mod_sms_contacts')->where('id', $contactId)->where('client_id', $clientId)->first();
        if (!$contact) return false;

        try {
            Capsule::table('mod_sms_contact_tags')->insertOrIgnore([
                'contact_id' => $contactId,
                'tag_id' => $tagId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            self::recalculateCount($tagId);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Remove a tag from a contact (validates both belong to the same client)
     */
    public static function removeTag(int $contactId, int $tagId, int $clientId): bool
    {
        // Verify tag belongs to client
        $tag = Capsule::table('mod_sms_tags')->where('id', $tagId)->where('client_id', $clientId)->first();
        if (!$tag) return false;

        // Verify contact belongs to client
        $contact = Capsule::table('mod_sms_contacts')->where('id', $contactId)->where('client_id', $clientId)->first();
        if (!$contact) return false;

        Capsule::table('mod_sms_contact_tags')
            ->where('contact_id', $contactId)
            ->where('tag_id', $tagId)
            ->delete();
        self::recalculateCount($tagId);
        return true;
    }

    /**
     * Bulk assign a tag to multiple contacts (validates tag belongs to client)
     */
    public static function bulkAssignTag(array $contactIds, int $tagId, int $clientId): int
    {
        // Verify tag belongs to client
        $tag = Capsule::table('mod_sms_tags')->where('id', $tagId)->where('client_id', $clientId)->first();
        if (!$tag) return 0;

        // Filter to only contacts belonging to this client
        $validContactIds = Capsule::table('mod_sms_contacts')
            ->where('client_id', $clientId)
            ->whereIn('id', $contactIds)
            ->pluck('id')
            ->toArray();

        $count = 0;
        foreach ($validContactIds as $contactId) {
            try {
                Capsule::table('mod_sms_contact_tags')->insertOrIgnore([
                    'contact_id' => (int)$contactId,
                    'tag_id' => $tagId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $count++;
            } catch (Exception $e) {
                // skip duplicates
            }
        }
        self::recalculateCount($tagId);
        return $count;
    }

    /**
     * Get all tags assigned to a contact (scoped to client)
     */
    public static function getContactTags(int $contactId, int $clientId): array
    {
        return Capsule::table('mod_sms_contact_tags')
            ->join('mod_sms_tags', 'mod_sms_tags.id', '=', 'mod_sms_contact_tags.tag_id')
            ->where('mod_sms_contact_tags.contact_id', $contactId)
            ->where('mod_sms_tags.client_id', $clientId)
            ->select('mod_sms_tags.*')
            ->orderBy('mod_sms_tags.name')
            ->get()
            ->toArray();
    }

    /**
     * Get all phone numbers for contacts with a given tag
     */
    public static function getTagContacts(int $tagId, int $clientId): array
    {
        return Capsule::table('mod_sms_contacts')
            ->join('mod_sms_contact_tags', 'mod_sms_contacts.id', '=', 'mod_sms_contact_tags.contact_id')
            ->where('mod_sms_contact_tags.tag_id', $tagId)
            ->where('mod_sms_contacts.client_id', $clientId)
            ->whereIn('mod_sms_contacts.status', ['active', 'subscribed'])
            ->pluck('mod_sms_contacts.phone')
            ->toArray();
    }

    /**
     * Recalculate the contact_count for a tag
     */
    public static function recalculateCount(int $tagId): void
    {
        $count = Capsule::table('mod_sms_contact_tags')
            ->where('tag_id', $tagId)
            ->count();

        Capsule::table('mod_sms_tags')
            ->where('id', $tagId)
            ->update([
                'contact_count' => $count,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
