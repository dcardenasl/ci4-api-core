<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

final class ApiConfigFacade
{
    public static function get(): ?object
    {
        if (! function_exists('config')) {
            return null;
        }

        /** @var object|null $config */
        $config = config('Api', false);

        return is_object($config) ? $config : null;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $config = self::get();
        if ($config === null || ! property_exists($config, $key) || $config->{$key} === null) {
            return $default;
        }

        return (bool) $config->{$key};
    }

    public static function int(string $key, int $default = 0): int
    {
        $config = self::get();
        if ($config === null || ! property_exists($config, $key) || $config->{$key} === null) {
            return $default;
        }

        return (int) $config->{$key};
    }
}
