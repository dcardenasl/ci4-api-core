<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models\Traits;

/**
 * AssertsEntityType
 *
 * Narrows a `findAll()`-style result set to a specific entity class,
 * throwing rather than silently dropping rows that don't match.
 *
 * Intended for models with `$returnType` fixed to a single entity (e.g. an
 * append-only log model with no `asArray()`/`asObject()` callers) — a row
 * of the wrong type there is always a bug, never an expected case. Filtering
 * it out silently would hide the bug and lose data from the caller's
 * perspective; throwing surfaces it immediately.
 */
trait AssertsEntityType
{
    /**
     * @template T of object
     * @param list<mixed> $rows
     * @param class-string<T> $entityClass
     * @return list<T>
     */
    protected function asEntities(array $rows, string $entityClass): array
    {
        $entities = [];

        foreach ($rows as $row) {
            if (!$row instanceof $entityClass) {
                throw new \UnexpectedValueException(
                    static::class . ' returned a row that is not an instance of ' . $entityClass . '.'
                );
            }

            $entities[] = $row;
        }

        return $entities;
    }
}
