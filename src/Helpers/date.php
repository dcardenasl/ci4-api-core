<?php

declare(strict_types=1);

/**
 * Date Helper Functions
 *
 * Provides consistent date/time formatting across the application.
 */

use CodeIgniter\I18n\Time;

if (!function_exists('datetime_to_timestamp')) {
    /**
     * Normalize a datetime value into a unix timestamp
     *
     * @param mixed $datetime Time|string|int|null
     * @return int|null Unix timestamp or null if invalid
     */
    function datetime_to_timestamp($datetime): ?int
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        if ($datetime instanceof Time) {
            return $datetime->getTimestamp();
        }

        if (is_int($datetime)) {
            return $datetime;
        }

        if (is_string($datetime)) {
            $timestamp = strtotime($datetime);
            return $timestamp !== false ? $timestamp : null;
        }

        return null;
    }
}

if (!function_exists('datetime_now')) {
    /**
     * Get current datetime string in MySQL format
     *
     * @return string Y-m-d H:i:s format
     */
    function datetime_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('date_now')) {
    /**
     * Get current date string
     *
     * @return string Y-m-d format
     */
    function date_now(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('add_minutes')) {
    /**
     * Add minutes to a datetime
     *
     * @param string|null $datetime Base datetime (default: now)
     * @param int         $minutes  Minutes to add
     * @return string Resulting datetime string
     */
    function add_minutes($datetime = null, int $minutes = 0): string
    {
        $time = datetime_to_timestamp($datetime) ?? time();
        return date('Y-m-d H:i:s', $time + ($minutes * 60));
    }
}

if (!function_exists('add_hours')) {
    /**
     * Add hours to a datetime
     *
     * @param string|null $datetime Base datetime (default: now)
     * @param int         $hours    Hours to add
     * @return string Resulting datetime string
     */
    function add_hours($datetime = null, int $hours = 0): string
    {
        return add_minutes($datetime, $hours * 60);
    }
}

if (!function_exists('add_days')) {
    /**
     * Add days to a datetime
     *
     * @param string|null $datetime Base datetime (default: now)
     * @param int         $days     Days to add
     * @return string Resulting datetime string
     */
    function add_days($datetime = null, int $days = 0): string
    {
        return add_minutes($datetime, $days * 24 * 60);
    }
}

if (!function_exists('is_expired')) {
    /**
     * Check if a datetime has passed
     *
     * @param string|null $datetime Datetime to check
     * @return bool True if expired (datetime is in the past)
     */
    function is_expired($datetime): bool
    {
        if ($datetime === null || $datetime === '') {
            return true;
        }

        $timestamp = datetime_to_timestamp($datetime);
        if ($timestamp === null) {
            return true;
        }

        return $timestamp < time();
    }
}

if (!function_exists('datetime_diff_minutes')) {
    /**
     * Get difference in minutes between two datetimes
     *
     * @param string      $from Start datetime
     * @param string|null $to   End datetime (default: now)
     * @return int Minutes difference
     */
    function datetime_diff_minutes($from, $to = null): int
    {
        $fromTime = datetime_to_timestamp($from);
        $toTime = datetime_to_timestamp($to) ?? time();

        if ($fromTime === null) {
            return 0;
        }

        return (int) round(($toTime - $fromTime) / 60);
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Format a datetime string to a specific format
     *
     * @param string|null $datetime Datetime to format
     * @param string      $format   PHP date format
     * @return string|null Formatted datetime or null if input is null
     */
    function format_datetime($datetime, string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        $timestamp = datetime_to_timestamp($datetime);
        return $timestamp !== null ? date($format, $timestamp) : null;
    }
}

if (!function_exists('to_iso8601')) {
    /**
     * Convert datetime to ISO 8601 format
     *
     * @param string|null $datetime Datetime to convert
     * @return string|null ISO 8601 formatted datetime
     */
    function to_iso8601($datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        $timestamp = datetime_to_timestamp($datetime);
        return $timestamp !== null ? date('c', $timestamp) : null;
    }
}

if (!function_exists('human_time_diff')) {
    /**
     * Get human-readable time difference (e.g., "2 hours ago")
     *
     * @param string      $datetime Datetime to compare
     * @param string|null $compare  Compare against (default: now)
     * @return string Human-readable difference
     */
    function human_time_diff(string $datetime, ?string $compare = null): string
    {
        $from = Time::parse($datetime);
        $to = $compare ? Time::parse($compare) : Time::now();

        return $from->humanize();
    }
}
