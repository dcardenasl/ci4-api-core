<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Filters;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Support\ApiConfigFacade;

/**
 * SearchQueryApplier
 *
 * Applies FULLTEXT or LIKE search queries to a CI4 Model or BaseBuilder.
 *
 * Reads three knobs from `config('Api')` when available:
 *   - searchEnabled       (bool, default true)
 *   - searchMinLength     (int, default 0)
 *   - searchUseFulltext   (bool, default true)
 *
 * Each lookup falls back to a safe default when `config('Api')` does not
 * exist or the property is missing — so a consumer that hasn't shipped a
 * Config\Api still gets a working search out of the box.
 */
class SearchQueryApplier
{
    /**
     * @param Model|BaseBuilder $builder
     * @param list<string>      $searchableFields
     */
    public static function apply(
        Model|BaseBuilder $builder,
        string $query,
        array $searchableFields,
        bool $useFulltext = true,
    ): void {
        if ($searchableFields === [] || $query === '') {
            return;
        }

        $minLength = ApiConfigFacade::int('searchMinLength', 0);
        if (strlen($query) < $minLength) {
            return;
        }

        if (! ApiConfigFacade::bool('searchEnabled', true)) {
            return;
        }

        if ($useFulltext) {
            self::applyFulltext($builder, $query, $searchableFields);
        } else {
            self::applyLike($builder, $query, $searchableFields);
        }
    }

    /**
     * @param Model|BaseBuilder $builder
     * @param list<string>      $searchableFields
     */
    public static function applyFulltext(
        Model|BaseBuilder $builder,
        string $query,
        array $searchableFields,
    ): void {
        $actualBuilder = $builder instanceof Model ? $builder->builder() : $builder;

        $fields = implode(', ', $searchableFields);
        $db = $builder instanceof Model ? $builder->db : $builder->db();
        $escapedQuery = $db->escape(self::sanitizeFulltextQuery($query));
        $actualBuilder->where("MATCH({$fields}) AGAINST({$escapedQuery} IN BOOLEAN MODE)", null, false);
    }

    /**
     * Strip MySQL Boolean Mode operators so user input does not alter query semantics.
     * Operators +  -  *  "  (  )  ~  <  > are replaced with a space.
     * Exposed publicly so consumers can pre-sanitize queries before other uses.
     */
    public static function sanitizeFulltextQuery(string $query): string
    {
        return preg_replace('/[+\-*"()~<>]/', ' ', $query) ?? $query;
    }

    /**
     * @param Model|BaseBuilder $builder
     * @param list<string>      $searchableFields
     */
    public static function applyLike(
        Model|BaseBuilder $builder,
        string $query,
        array $searchableFields,
    ): void {
        $actualBuilder = $builder instanceof Model ? $builder->builder() : $builder;

        $actualBuilder->groupStart();

        foreach ($searchableFields as $index => $field) {
            if ($index === 0) {
                $actualBuilder->like($field, $query);
            } else {
                $actualBuilder->orLike($field, $query);
            }
        }

        $actualBuilder->groupEnd();
    }

}
