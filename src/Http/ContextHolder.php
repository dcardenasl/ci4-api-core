<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Context Holder
 *
 * Provides a static registry for the current request's SecurityContext.
 * This allows deeper parts of the application (like Model traits) to access
 * the actor's identity without polluting method signatures.
 */
class ContextHolder
{
    private static ?SecurityContext $context = null;

    public static function set(?SecurityContext $context): void
    {
        self::$context = $context;
    }

    public static function get(): ?SecurityContext
    {
        return self::$context;
    }

    public static function getUserId(): ?int
    {
        return self::$context?->user_id;
    }

    public static function flush(): void
    {
        self::$context = null;
    }
}
