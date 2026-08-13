<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts;

/**
 * Fieldset Validator Interface
 *
 * Defines contract for validating sparse fieldset requests.
 * Separates validation logic from filtering for clarity and testability.
 */
interface FieldsetValidatorInterface
{
    /**
     * Validate requested fields against an allowlist.
     *
     * @param array<mixed> $requestedFields Fields requested by client (e.g., from ?fields=)
     * @param list<string> $allowedFields Whitelist of valid fields for this operation
     * @return list<string> Cleaned, validated list of requested fields
     *
     * @throws \dcardenasl\Ci4ApiCore\Exceptions\ValidationException If a requested field is not allowed or not a string
     */
    public function validate(array $requestedFields, array $allowedFields): array;

    /**
     * Check if a specific field is allowed.
     *
     * @param string $field Field name to check
     * @param list<string> $allowedFields Whitelist of valid fields
     * @return bool true if field is allowed, false otherwise
     */
    public function isAllowed(string $field, array $allowedFields): bool;

    /**
     * Parse comma-separated string of field names.
     *
     * @param string $fieldsString Comma-separated field names (e.g., "id,name,slug")
     * @return list<string> Parsed and trimmed field names
     */
    public function parse(string $fieldsString): array;
}
