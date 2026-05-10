<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use CodeIgniter\I18n\Time;

/**
 * Date/time utilities that work with PHP timestamps, date strings, and CI4's `Time`
 * objects. All methods are null-safe: missing or empty values return `null` / `true` / `0`
 * rather than throwing. Outputs are always MySQL-compatible strings (`Y-m-d H:i:s`) or
 * ISO 8601 (`c`), never locale-dependent formats.
 */
final class DateHelper
{
    public static function toTimestamp(mixed $datetime): ?int
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

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function dateNow(): string
    {
        return date('Y-m-d');
    }

    public static function addMinutes(mixed $datetime = null, int $minutes = 0): string
    {
        $time = self::toTimestamp($datetime) ?? time();
        return date('Y-m-d H:i:s', $time + ($minutes * 60));
    }

    public static function addHours(mixed $datetime = null, int $hours = 0): string
    {
        return self::addMinutes($datetime, $hours * 60);
    }

    public static function addDays(mixed $datetime = null, int $days = 0): string
    {
        return self::addMinutes($datetime, $days * 24 * 60);
    }

    public static function isExpired(mixed $datetime): bool
    {
        if ($datetime === null || $datetime === '') {
            return true;
        }

        $timestamp = self::toTimestamp($datetime);
        if ($timestamp === null) {
            return true;
        }

        return $timestamp < time();
    }

    public static function diffMinutes(mixed $from, mixed $to = null): int
    {
        $fromTime = self::toTimestamp($from);
        $toTime = self::toTimestamp($to) ?? time();

        if ($fromTime === null) {
            return 0;
        }

        return (int) round(($toTime - $fromTime) / 60);
    }

    public static function format(mixed $datetime, string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        $timestamp = self::toTimestamp($datetime);
        return $timestamp !== null ? date($format, $timestamp) : null;
    }

    public static function toIso8601(mixed $datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        $timestamp = self::toTimestamp($datetime);
        return $timestamp !== null ? date('c', $timestamp) : null;
    }

    public static function humanDiff(string $datetime, ?string $compare = null): string
    {
        $from = Time::parse($datetime);

        return $from->humanize();
    }
}
