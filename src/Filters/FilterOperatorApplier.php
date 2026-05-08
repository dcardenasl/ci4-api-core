<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Filters;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * FilterOperatorApplier
 *
 * Applies the parsed `[operator, value]` tuples produced by FilterParser
 * to a CI4 Model or BaseBuilder. Centralizes the operator → builder-method
 * dispatch so callers (Filterable trait, QueryBuilder) stay declarative.
 */
class FilterOperatorApplier
{
    /**
     * @param Model|BaseBuilder $builder
     */
    public static function apply(Model|BaseBuilder $builder, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            '=' => $builder->where($field, $value),
            '!=' => $builder->where($field . ' !=', $value),
            '>' => $builder->where($field . ' >', $value),
            '<' => $builder->where($field . ' <', $value),
            '>=' => $builder->where($field . ' >=', $value),
            '<=' => $builder->where($field . ' <=', $value),
            'LIKE' => $builder->like($field, (string) $value),
            'IN' => $builder->whereIn($field, (array) $value),
            'NOT IN' => $builder->whereNotIn($field, (array) $value),
            'BETWEEN' => self::applyBetween($builder, $field, $value),
            'IS NULL' => $builder->where($field, null),
            'IS NOT NULL' => $builder->where($field . ' !=', null),
            default => null,
        };
    }

    /**
     * @param Model|BaseBuilder $builder
     */
    private static function applyBetween(Model|BaseBuilder $builder, string $field, mixed $value): void
    {
        if (is_array($value) && count($value) === 2) {
            $builder->where($field . ' >=', $value[0]);
            $builder->where($field . ' <=', $value[1]);
        }
    }
}
