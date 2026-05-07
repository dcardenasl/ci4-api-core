<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Filters;

/**
 * FilterParser
 *
 * Parses filter arrays from request parameters into normalized
 * `[field => [operator, value]]` shape that FilterOperatorApplier consumes.
 * Supports a handful of operator aliases plus implicit equals/IN.
 */
class FilterParser
{
    /**
     * Parse filters array into query conditions.
     *
     * Examples:
     *   ['role' => 'admin']                            => ['role' => ['=', 'admin']]
     *   ['age' => ['gt' => 18]]                        => ['age' => ['>', 18]]
     *   ['status' => ['in' => ['active', 'pending']]]  => ['status' => ['IN', ['active', 'pending']]]
     *
     * @param array<string,mixed> $filters
     * @return array<string,array{0:string,1:mixed}>
     */
    public static function parse(array $filters): array
    {
        $parsed = [];

        foreach ($filters as $field => $value) {
            if (!is_array($value)) {
                $parsed[$field] = ['=', $value];
                continue;
            }

            $operator = self::detectOperator($value);

            if ($operator) {
                $parsed[$field] = $operator;
            } else {
                $parsed[$field] = ['IN', $value];
            }
        }

        return $parsed;
    }

    /**
     * @param array<string,mixed> $value
     * @return array{0:string,1:mixed}|null
     */
    protected static function detectOperator(array $value): ?array
    {
        $operatorMap = [
            'eq' => '=',
            'ne' => '!=',
            'neq' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'ge' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'le' => '<=',
            'like' => 'LIKE',
            'in' => 'IN',
            'not_in' => 'NOT IN',
            'notin' => 'NOT IN',
            'between' => 'BETWEEN',
            'null' => 'IS NULL',
            'not_null' => 'IS NOT NULL',
            'notnull' => 'IS NOT NULL',
        ];

        foreach ($operatorMap as $key => $operator) {
            if (isset($value[$key])) {
                if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                    return [$operator, null];
                }

                return [$operator, $value[$key]];
            }
        }

        return null;
    }

    /**
     * @param list<string> $allowedFields
     */
    public static function isValidField(string $field, array $allowedFields): bool
    {
        return in_array($field, $allowedFields, true);
    }

    /**
     * @param array<string,mixed> $filters
     * @param list<string>        $allowedFields
     * @return array<string,mixed>
     */
    public static function filterAllowedFields(array $filters, array $allowedFields): array
    {
        return array_filter(
            $filters,
            static fn ($field) => self::isValidField((string) $field, $allowedFields),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param list<string> $allowedFields
     * @return list<array{0:string,1:string}>
     */
    public static function parseSort(string $sort, array $allowedFields = []): array
    {
        $sortFields = explode(',', $sort);
        $parsed = [];

        foreach ($sortFields as $field) {
            $field = trim($field);
            $direction = 'ASC';

            if (str_starts_with($field, '-')) {
                $direction = 'DESC';
                $field = substr($field, 1);
            }

            if ($allowedFields !== [] && !in_array($field, $allowedFields, true)) {
                continue;
            }

            $parsed[] = [$field, $direction];
        }

        return $parsed;
    }
}
