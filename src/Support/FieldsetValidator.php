<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use dcardenasl\Ci4ApiCore\Contracts\FieldsetValidatorInterface;

/**
 * Fieldset Validator
 *
 * Validates sparse fieldset requests against whitelists.
 * Ensures type safety and prevents injection attacks.
 */
class FieldsetValidator implements FieldsetValidatorInterface
{
    /**
     * Validate requested fields against an allowlist.
     *
     * @param list<string> $requestedFields Fields requested by client
     * @param list<string> $allowedFields Whitelist of valid fields
     * @return list<string> Validated, unique field names
     *
     * @throws \InvalidArgumentException If a requested field is not allowed
     */
    public function validate(array $requestedFields, array $allowedFields): array
    {
        if ($requestedFields === []) {
            return [];
        }

        $allowed = array_flip($allowedFields);
        $validated = [];

        foreach ($requestedFields as $field) {
            if (! is_string($field)) {
                throw new \InvalidArgumentException(
                    'Field names must be strings. Got: ' . gettype($field)
                );
            }

            $clean = trim($field);
            if ($clean === '') {
                continue;
            }

            if (! isset($allowed[$clean])) {
                throw new \InvalidArgumentException(
                    "Field '{$clean}' is not allowed. Allowed fields: " .
                    implode(', ', $allowedFields)
                );
            }

            $validated[$clean] = true;
        }

        return array_keys($validated);
    }

    /**
     * Check if a specific field is allowed.
     *
     * @param string $field Field name to check
     * @param list<string> $allowedFields Whitelist of valid fields
     * @return bool true if field is allowed, false otherwise
     */
    public function isAllowed(string $field, array $allowedFields): bool
    {
        return in_array($field, $allowedFields, true);
    }

    /**
     * Parse comma-separated string of field names.
     *
     * @param string $fieldsString Comma-separated field names
     * @return list<string> Parsed and trimmed field names
     */
    public function parse(string $fieldsString): array
    {
        if (trim($fieldsString) === '') {
            return [];
        }

        $parsed = array_map(
            static fn (string $field): string => trim($field),
            explode(',', $fieldsString)
        );

        return array_values(array_filter($parsed, static fn (string $field): bool => $field !== ''));
    }
}
