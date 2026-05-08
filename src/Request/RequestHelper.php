<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Request;

use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;

final class RequestHelper
{
    /** @param array<string, mixed> $data */
    public static function requireId(array $data, string $field = 'id', string $langKey = 'Api.invalidRequest'): int
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

    /**
     * @param array<string, mixed> $data
     * @param list<string> $fields
     */
    public static function requireFields(array $data, array $fields, string $langKey = 'Api.invalidRequest'): void
    {
        $errors = [];

        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        if (! empty($errors)) {
            throw new BadRequestException(lang($langKey), $errors);
        }
    }

    /** @param array<string, mixed> $data */
    public static function getInt(array $data, string $key, int $default = 0): int
    {
        if (! isset($data[$key])) {
            return $default;
        }

        $value = filter_var($data[$key], FILTER_VALIDATE_INT);
        return $value !== false ? $value : $default;
    }

    /** @param array<string, mixed> $data */
    public static function getBool(array $data, string $key, bool $default = false): bool
    {
        if (! isset($data[$key])) {
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

    /** @param array<string, mixed> $data */
    public static function getString(array $data, string $key, ?string $default = null): ?string
    {
        if (! isset($data[$key])) {
            return $default;
        }

        $value = $data[$key];

        if (! is_string($value) && ! is_numeric($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public static function getArray(array $data, string $key, array $default = []): array
    {
        if (! isset($data[$key]) || ! is_array($data[$key])) {
            return $default;
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    public static function pickFields(array $data, array $fields): array
    {
        return array_intersect_key($data, array_flip($fields));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function filterNull(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function filterEmpty(array $data): array
    {
        return array_filter($data, fn ($value) => ! empty($value) || $value === 0 || $value === '0');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{page: int, limit: int}
     */
    public static function getPaginationParams(array $data, int $defaultLimit = 20, int $maxLimit = 100): array
    {
        $page = max(self::getInt($data, 'page', 1), 1);
        $limit = min(self::getInt($data, 'limit', $defaultLimit), $maxLimit);
        $limit = min(self::getInt($data, 'per_page', $limit), $maxLimit);

        return [
            'page'  => $page,
            'limit' => max($limit, 1),
        ];
    }
}
