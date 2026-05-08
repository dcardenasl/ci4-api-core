<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Repositories;

/**
 * Pivot Repository Interface
 *
 * Extends the standard repository contract with two queries that are specific
 * to N:M pivot tables linking a parent resource to another resource (typically
 * shared assets like files): listing all rows for a given parent, and getting
 * the highest current `sort_order` so a new attachment can be appended at the
 * end of the list.
 *
 * Concrete pivot tables in consumers are expected to expose at minimum:
 * `id`, the parent foreign key, `file_id` (or equivalent), `sort_order`,
 * `is_active`. The parent FK column name is implementation-specific and
 * resolved by the concrete repository.
 */
interface PivotRepositoryInterface extends RepositoryInterface
{
    /**
     * All pivot rows for the given parent, ordered by `sort_order` ASC, `id` ASC.
     *
     * @return list<object>
     */
    public function findByParent(int $parentId): array;

    /**
     * The current maximum `sort_order` for the given parent, or 0 if the
     * parent has no rows. Callers add 1 to assign the next position.
     */
    public function maxSortOrder(int $parentId): int;
}
