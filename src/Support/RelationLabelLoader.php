<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use Config\Database;

/**
 * Batch-loads display labels from related tables and attaches them to entities
 * before they are mapped to Response DTOs. One SQL query per relation,
 * regardless of page size — replaces N+1 lookups and full-catalog pagination.
 *
 * Used by IAM and Audit services to expose `*_name` / `*_email` siblings of
 * the FK columns they already return.
 */
final class RelationLabelLoader
{
    /**
     * Attach a single label column from a related table onto each entity.
     *
     * @param  array<int, object> $entities
     * @return array<int, object>
     */
    public function attachLabel(
        array $entities,
        string $sourceField,
        string $targetField,
        string $relatedTable,
        string $relatedLabel,
        string $relatedKey = 'id'
    ): array {
        $ids = $this->collectIds($entities, $sourceField);

        if ($ids === []) {
            return $entities;
        }

        $db    = Database::connect();
        $query = $db->table($relatedTable)
            ->select($relatedKey . ', ' . $relatedLabel)
            ->whereIn($relatedKey, $ids)
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();
        $map  = [];

        foreach ($rows as $row) {
            $map[(int) ($row[$relatedKey] ?? 0)] = (string) ($row[$relatedLabel] ?? '');
        }

        foreach ($entities as $entity) {
            $id = $this->readInt($entity, $sourceField);
            if ($id !== null && isset($map[$id])) {
                $entity->{$targetField} = $map[$id];
            }
        }

        return $entities;
    }

    /**
     * Attach user_email, user_label and (optional) user_full_name onto each
     * entity that has a `$sourceField` FK to the users table.
     *
     * @param  array<int, object> $entities
     * @return array<int, object>
     */
    public function attachUserLabels(array $entities, string $sourceField = 'user_id'): array
    {
        $ids = $this->collectIds($entities, $sourceField);

        if ($ids === []) {
            return $entities;
        }

        $db    = Database::connect();
        $query = $db->table('users')
            ->select('id, email, first_name, last_name')
            ->whereIn('id', $ids)
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();
        $map  = [];

        foreach ($rows as $row) {
            $id    = (int) ($row['id'] ?? 0);
            $email = (string) ($row['email'] ?? '');
            $first = trim((string) ($row['first_name'] ?? ''));
            $last  = trim((string) ($row['last_name'] ?? ''));
            $name  = trim($first . ' ' . $last);
            $label = $name === '' ? $email : sprintf('%s <%s>', $name, $email);

            $map[$id] = [
                'email'     => $email,
                'full_name' => $name === '' ? null : $name,
                'label'     => $label,
            ];
        }

        foreach ($entities as $entity) {
            $id = $this->readInt($entity, $sourceField);

            if ($id === null || !isset($map[$id])) {
                continue;
            }

            $entity->user_email     = $map[$id]['email'];
            $entity->user_full_name = $map[$id]['full_name'];
            $entity->user_label     = $map[$id]['label'];
        }

        return $entities;
    }

    /**
     * @param  array<int, object>  $entities
     * @return list<int>
     */
    private function collectIds(array $entities, string $field): array
    {
        $ids = [];

        foreach ($entities as $entity) {
            $value = $this->readInt($entity, $field);
            if ($value !== null && $value > 0) {
                $ids[$value] = true;
            }
        }

        return array_keys($ids);
    }

    private function readInt(object $entity, string $field): ?int
    {
        $value = $entity->{$field} ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
