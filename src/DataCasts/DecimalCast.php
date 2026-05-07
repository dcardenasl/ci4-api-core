<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\DataCasts;

use CodeIgniter\DataCaster\Cast\BaseCast;

/**
 * DecimalCast
 *
 * String-backed cast for SQL DECIMAL columns. CI4's built-in casters do not
 * recognize `decimal`; using `float` would silently round monetary and
 * tax values (`19.99 + 0.10 !== 20.09`). This cast preserves precision by
 * round-tripping the value as a string — `19.99` in, `'19.99'` out.
 *
 * Register on an Entity:
 *
 *     protected $castHandlers = [
 *         'decimal' => \dcardenasl\Ci4ApiCore\DataCasts\DecimalCast::class,
 *     ];
 *
 *     protected $casts = [
 *         'price' => 'decimal',
 *     ];
 *
 * The CI4 cast-parameter syntax is supported: `'price' => 'decimal[10,2]'`
 * passes `['10', '2']` as `$params`. Currently informational only — the cast
 * does not enforce precision/scale; it trusts the database column.
 */
class DecimalCast extends BaseCast
{
    public static function get(
        mixed $value,
        array $params = [],
        ?object $helper = null,
    ): ?string {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        self::invalidTypeValueError($value);
    }

    public static function set(
        mixed $value,
        array $params = [],
        ?object $helper = null,
    ): ?string {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        self::invalidTypeValueError($value);
    }
}
