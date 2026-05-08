<?php

declare(strict_types=1);

use dcardenasl\Ci4ApiCore\Support\DateHelper;

/**
 * Date Helper Functions
 *
 * @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper instead.
 * These procedural wrappers will be removed in v1.0.0.
 */

if (!function_exists('datetime_to_timestamp')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::toTimestamp() */
    function datetime_to_timestamp(mixed $datetime): ?int
    {
        return DateHelper::toTimestamp($datetime);
    }
}

if (!function_exists('datetime_now')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::now() */
    function datetime_now(): string
    {
        return DateHelper::now();
    }
}

if (!function_exists('date_now')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::dateNow() */
    function date_now(): string
    {
        return DateHelper::dateNow();
    }
}

if (!function_exists('add_minutes')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::addMinutes() */
    function add_minutes(mixed $datetime = null, int $minutes = 0): string
    {
        return DateHelper::addMinutes($datetime, $minutes);
    }
}

if (!function_exists('add_hours')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::addHours() */
    function add_hours(mixed $datetime = null, int $hours = 0): string
    {
        return DateHelper::addHours($datetime, $hours);
    }
}

if (!function_exists('add_days')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::addDays() */
    function add_days(mixed $datetime = null, int $days = 0): string
    {
        return DateHelper::addDays($datetime, $days);
    }
}

if (!function_exists('is_expired')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::isExpired() */
    function is_expired(mixed $datetime): bool
    {
        return DateHelper::isExpired($datetime);
    }
}

if (!function_exists('datetime_diff_minutes')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::diffMinutes() */
    function datetime_diff_minutes(mixed $from, mixed $to = null): int
    {
        return DateHelper::diffMinutes($from, $to);
    }
}

if (!function_exists('format_datetime')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::format() */
    function format_datetime(mixed $datetime, string $format = 'Y-m-d H:i:s'): ?string
    {
        return DateHelper::format($datetime, $format);
    }
}

if (!function_exists('to_iso8601')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::toIso8601() */
    function to_iso8601(mixed $datetime): ?string
    {
        return DateHelper::toIso8601($datetime);
    }
}

if (!function_exists('human_time_diff')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\DateHelper::humanDiff() */
    function human_time_diff(string $datetime, ?string $compare = null): string
    {
        return DateHelper::humanDiff($datetime, $compare);
    }
}
