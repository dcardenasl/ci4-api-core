<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models\Traits;

use dcardenasl\Ci4ApiCore\Filters\QueryBuilder;

/**
 * Shared query option handling for service index/list methods.
 */
trait AppliesQueryOptions
{
    /**
     * @param array<string, mixed> $data
     */
    protected function applyQueryOptions(
        QueryBuilder $builder,
        array $data,
        ?callable $onMissingSort = null
    ): void {
        if (! empty($data['filter']) && is_array($data['filter'])) {
            $builder->filter($data['filter']);
        }

        if (! empty($data['search'])) {
            $builder->search((string) $data['search']);
        }

        if (! empty($data['sort'])) {
            $builder->sort((string) $data['sort']);
        } elseif ($onMissingSort !== null) {
            $onMissingSort();
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0:int,1:int} [page, limit]
     */
    protected function resolvePagination(
        array $data,
        int $defaultLimit,
        ?int $maxLimit = null
    ): array {
        $page = isset($data['page']) ? max((int) $data['page'], 1) : 1;
        $limit = isset($data['limit']) ? (int) $data['limit'] : $defaultLimit;

        if ($maxLimit !== null) {
            $limit = min(max($limit, 1), $maxLimit);
        }

        return [$page, $limit];
    }
}
