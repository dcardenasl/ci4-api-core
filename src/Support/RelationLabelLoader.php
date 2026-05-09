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
     * Attach `{prefix}_email`, `{prefix}_full_name`, and `{prefix}_label`
     * onto each entity that has a FK ($sourceField) into a related actor
     * table. Generic alternative to the now-deprecated `attachUserLabels()`
     * — the calling code provides the table and column names explicitly.
     *
     * @param  array<int, object> $entities
     * @param  list<string>       $nameColumns columns concatenated to form the
     *                                          full name (in order)
     * @return array<int, object>
     */
    public function attachActorLabels(
        array $entities,
        string $sourceField,
        string $relatedTable,
        string $emailColumn = 'email',
        array $nameColumns = ['first_name', 'last_name'],
        string $targetPrefix = 'user',
        string $relatedKey = 'id'
    ): array {
        $ids = $this->collectIds($entities, $sourceField);

        if ($ids === []) {
            return $entities;
        }

        $select  = array_unique(array_merge([$relatedKey, $emailColumn], $nameColumns));
        $db      = Database::connect();
        $query   = $db->table($relatedTable)
            ->select(implode(', ', $select))
            ->whereIn($relatedKey, $ids)
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();
        $map  = [];

        foreach ($rows as $row) {
            $id    = (int) ($row[$relatedKey] ?? 0);
            $email = (string) ($row[$emailColumn] ?? '');

            $nameParts = [];
            foreach ($nameColumns as $column) {
                $part = trim((string) ($row[$column] ?? ''));
                if ($part !== '') {
                    $nameParts[] = $part;
                }
            }

            $name  = implode(' ', $nameParts);
            $label = $name === '' ? $email : sprintf('%s <%s>', $name, $email);

            $map[$id] = [
                'email'     => $email,
                'full_name' => $name === '' ? null : $name,
                'label'     => $label,
            ];
        }

        $emailField = $targetPrefix . '_email';
        $nameField  = $targetPrefix . '_full_name';
        $labelField = $targetPrefix . '_label';

        foreach ($entities as $entity) {
            $id = $this->readInt($entity, $sourceField);

            if ($id === null || ! isset($map[$id])) {
                continue;
            }

            $entity->{$emailField} = $map[$id]['email'];
            $entity->{$nameField}  = $map[$id]['full_name'];
            $entity->{$labelField} = $map[$id]['label'];
        }

        return $entities;
    }

    /**
     * Attach user_email, user_full_name, and user_label onto each entity
     * that has a `$sourceField` FK to the `users` table.
     *
     * @deprecated since 0.4 — use {@see self::attachActorLabels()} with
     *                          explicit table/column arguments. This wrapper
     *                          assumes the `users` table layout and the
     *                          (`first_name`, `last_name`) name columns,
     *                          which is consumer-specific. Will be removed
     *                          in v1.0.
     *
     * @param  array<int, object> $entities
     * @return array<int, object>
     */
    public function attachUserLabels(array $entities, string $sourceField = 'user_id'): array
    {
        return $this->attachActorLabels(
            $entities,
            $sourceField,
            'users',
            'email',
            ['first_name', 'last_name'],
            'user'
        );
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
