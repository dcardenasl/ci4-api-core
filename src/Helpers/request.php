<?php

declare(strict_types=1);

use dcardenasl\Ci4ApiCore\Request\RequestHelper;

/**
 * Request Helper Functions
 *
 * @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper instead.
 * These procedural wrappers will be removed in v1.0.0.
 */

if (!function_exists('require_id')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::requireId() */
    function require_id(array $data, string $field = 'id', string $langKey = 'Api.invalidRequest'): int
    {
        return RequestHelper::requireId($data, $field, $langKey);
    }
}

if (!function_exists('require_fields')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::requireFields() */
    function require_fields(array $data, array $fields, string $langKey = 'Api.invalidRequest'): void
    {
        RequestHelper::requireFields($data, array_values($fields), $langKey);
    }
}

if (!function_exists('get_int')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::getInt() */
    function get_int(array $data, string $key, int $default = 0): int
    {
        return RequestHelper::getInt($data, $key, $default);
    }
}

if (!function_exists('get_bool')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::getBool() */
    function get_bool(array $data, string $key, bool $default = false): bool
    {
        return RequestHelper::getBool($data, $key, $default);
    }
}

if (!function_exists('get_string')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::getString() */
    function get_string(array $data, string $key, ?string $default = null): ?string
    {
        return RequestHelper::getString($data, $key, $default);
    }
}

if (!function_exists('get_array')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::getArray() */
    function get_array(array $data, string $key, array $default = []): array
    {
        return RequestHelper::getArray($data, $key, $default);
    }
}

if (!function_exists('pick_fields')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::pickFields() */
    function pick_fields(array $data, array $fields): array
    {
        return RequestHelper::pickFields($data, array_values($fields));
    }
}

if (!function_exists('filter_null')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::filterNull() */
    function filter_null(array $data): array
    {
        return RequestHelper::filterNull($data);
    }
}

if (!function_exists('filter_empty')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::filterEmpty() */
    function filter_empty(array $data): array
    {
        return RequestHelper::filterEmpty($data);
    }
}

if (!function_exists('get_pagination_params')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Request\RequestHelper::getPaginationParams() */
    function get_pagination_params(array $data, int $defaultLimit = 20, int $maxLimit = 100): array
    {
        return RequestHelper::getPaginationParams($data, $defaultLimit, $maxLimit);
    }
}
