<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

/**
 * Normalizes a value decoded from a CI4 Entity `json` cast into a plain,
 * fully-array structure.
 *
 * CI4's `json` cast decodes to `stdClass` recursively at every nesting
 * level, not just the top one — a naive `(array) $value` only casts the
 * top level and silently leaves nested values as `stdClass`, which then
 * fail any `is_array()` check downstream (schema introspection, config
 * field resolution, etc.) without raising an error. Round-tripping through
 * `json_encode()`/`json_decode(..., true)` normalizes every level at once.
 */
final class JsonCastNormalizer
{
    /**
     * @return array<array-key, mixed>
     */
    public static function toArray(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_array($value) && !($value instanceof \stdClass)) {
            return [];
        }

        $decoded = json_decode((string) json_encode($value), true);

        return is_array($decoded) ? $decoded : [];
    }
}
