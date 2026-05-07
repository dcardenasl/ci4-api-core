<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models\Traits;

use dcardenasl\Ci4ApiCore\Filters\FilterOperatorApplier;
use dcardenasl\Ci4ApiCore\Filters\FilterParser;

/**
 * Filterable
 *
 * Adds whitelisted filtering to a CI4 Model. The model must declare
 * `protected array $filterableFields = [...]` listing the columns it allows
 * filters against — anything outside that whitelist is silently dropped to
 * keep the API surface explicit and SQL-injection-safe.
 */
trait Filterable
{
    /**
     * @param array<string,mixed> $filters
     */
    public function applyFilters(array $filters): self
    {
        if (!empty($this->filterableFields)) {
            $filters = FilterParser::filterAllowedFields($filters, $this->filterableFields);
        }

        $parsedFilters = FilterParser::parse($filters);

        foreach ($parsedFilters as $field => $condition) {
            [$operator, $value] = $condition;
            FilterOperatorApplier::apply($this, $field, $operator, $value);
        }

        return $this;
    }

    /** @return list<string> */
    public function getFilterableFields(): array
    {
        return $this->filterableFields;
    }

    public function isFilterable(string $field): bool
    {
        return in_array($field, $this->filterableFields, true);
    }
}
