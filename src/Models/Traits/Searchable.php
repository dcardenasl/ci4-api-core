<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models\Traits;

use dcardenasl\Ci4ApiCore\Filters\SearchQueryApplier;

/**
 * Searchable
 *
 * Adds full-text / LIKE search to a CI4 Model based on a `$searchableFields`
 * whitelist. Behavior is configurable via `config('Api')` (knobs:
 * `searchEnabled`, `searchUseFulltext`, `searchMinLength`); each knob
 * defaults safely when the config key is absent so a vanilla consumer
 * still gets a working search out of the box.
 */
trait Searchable
{
    public function search(string $query): self
    {
        if (empty($this->searchableFields) || $query === '') {
            return $this;
        }

        SearchQueryApplier::apply(
            $this,
            $query,
            $this->searchableFields,
            $this->useFulltextSearch(),
        );

        return $this;
    }

    protected function useFulltextSearch(): bool
    {
        if (!self::apiConfigBool('searchUseFulltext', true)) {
            return false;
        }

        $dbDriver = $this->db->DBDriver ?? '';
        if (!in_array(strtolower((string) $dbDriver), ['mysqli', 'mysql'], true)) {
            return false;
        }

        return self::apiConfigBool('searchEnabled', true) && !empty($this->searchableFields);
    }

    /** @return list<string> */
    public function getSearchableFields(): array
    {
        return $this->searchableFields;
    }

    public function isSearchable(string $field): bool
    {
        return in_array($field, $this->searchableFields, true);
    }

    private static function apiConfigBool(string $key, bool $default): bool
    {
        if (!function_exists('config')) {
            return $default;
        }

        /** @var object|null $config */
        $config = config('Api', false);
        if (!is_object($config) || !property_exists($config, $key) || $config->{$key} === null) {
            return $default;
        }

        return (bool) $config->{$key};
    }
}
