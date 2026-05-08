<?php

declare(strict_types=1);

/**
 * Request Helper Functions
 *
 * Provides common request validation and data extraction patterns.
 */

use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;

if (!function_exists('require_id')) {
    /**
     * Require an ID field in the data array
     *
     * @param array  $data      Request data
     * @param string $field     Field name (default: 'id')
     * @param string $langKey   Language key for error message
     * @return int The validated ID
     * @throws BadRequestException If ID is missing or invalid
     */
    function require_id(array $data, string $field = 'id', string $langKey = 'Api.invalidRequest'): int
    {
        if (empty($data[$field])) {
            throw new BadRequestException(
                lang($langKey),
                [$field => lang('InputValidation.common.idRequired', [ucfirst($field)])]
            );
        }

        $id = filter_var($data[$field], FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            throw new BadRequestException(
                lang($langKey),
                [$field => lang('InputValidation.common.idMustBePositive', [ucfirst($field)])]
            );
        }

        return $id;
    }
}

if (!function_exists('require_fields')) {
    /**
     * Require multiple fields in the data array
     *
     * @param array         $data   Request data
     * @param array<string> $fields Required field names
     * @param string        $langKey Language key for error message
     * @return void
     * @throws BadRequestException If any field is missing
     */
    function require_fields(array $data, array $fields, string $langKey = 'Api.invalidRequest'): void
    {
        $errors = [];

        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        if (!empty($errors)) {
            throw new BadRequestException(lang($langKey), $errors);
        }
    }
}

if (!function_exists('get_int')) {
    /**
     * Get an integer value from data with optional default
     *
     * @param array  $data    Request data
     * @param string $key     Key to retrieve
     * @param int    $default Default value if not found or invalid
     * @return int
     */
    function get_int(array $data, string $key, int $default = 0): int
    {
        if (!isset($data[$key])) {
            return $default;
        }

        $value = filter_var($data[$key], FILTER_VALIDATE_INT);
        return $value !== false ? $value : $default;
    }
}

if (!function_exists('get_bool')) {
    /**
     * Get a boolean value from data
     *
     * Handles: true, false, 1, 0, "1", "0", "true", "false", "yes", "no"
     *
     * @param array  $data    Request data
     * @param string $key     Key to retrieve
     * @param bool   $default Default value if not found
     * @return bool
     */
    function get_bool(array $data, string $key, bool $default = false): bool
    {
        if (!isset($data[$key])) {
            return $default;
        }

        $value = $data[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', 'yes', '1'], true);
        }

        return $default;
    }
}

if (!function_exists('get_string')) {
    /**
     * Get a trimmed string value from data
     *
     * @param array       $data    Request data
     * @param string      $key     Key to retrieve
     * @param string|null $default Default value if not found
     * @return string|null
     */
    function get_string(array $data, string $key, ?string $default = null): ?string
    {
        if (!isset($data[$key])) {
            return $default;
        }

        $value = $data[$key];

        if (!is_string($value) && !is_numeric($value)) {
            return $default;
        }

        return trim((string) $value);
    }
}

if (!function_exists('get_array')) {
    /**
     * Get an array value from data
     *
     * @param array  $data    Request data
     * @param string $key     Key to retrieve
     * @param array  $default Default value if not found
     * @return array
     */
    function get_array(array $data, string $key, array $default = []): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            return $default;
        }

        return $data[$key];
    }
}

if (!function_exists('pick_fields')) {
    /**
     * Extract specific fields from data array
     *
     * @param array         $data   Source data
     * @param array<string> $fields Fields to extract
     * @return array Only the specified fields
     */
    function pick_fields(array $data, array $fields): array
    {
        return array_intersect_key($data, array_flip($fields));
    }
}

if (!function_exists('filter_null')) {
    /**
     * Remove null values from array
     *
     * @param array $data Source data
     * @return array Filtered data without null values
     */
    function filter_null(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null);
    }
}

if (!function_exists('filter_empty')) {
    /**
     * Remove empty values from array (null, '', [])
     *
     * @param array $data Source data
     * @return array Filtered data without empty values
     */
    function filter_empty(array $data): array
    {
        return array_filter($data, fn ($value) => !empty($value) || $value === 0 || $value === '0');
    }
}

if (!function_exists('get_pagination_params')) {
    /**
     * Extract pagination parameters from request data
     *
     * @param array $data    Request data
     * @param int   $defaultLimit Default items per page
     * @param int   $maxLimit Maximum items per page
     * @return array{page: int, limit: int}
     */
    function get_pagination_params(array $data, int $defaultLimit = 20, int $maxLimit = 100): array
    {
        $page = max(get_int($data, 'page', 1), 1);
        $limit = min(get_int($data, 'limit', $defaultLimit), $maxLimit);
        $limit = min(get_int($data, 'per_page', $limit), $maxLimit);

        return [
            'page'  => $page,
            'limit' => max($limit, 1),
        ];
    }
}
