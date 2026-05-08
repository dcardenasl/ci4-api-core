<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http;

/**
 * Request ID Holder
 *
 * Static registry for the current request's correlation ID. Mirrors the
 * `ContextHolder` pattern — lets deeper layers (logger processors,
 * exception handlers, audit writers) tag their output with the same
 * `request_id` that the client sees in the response header, without
 * threading it through every method signature.
 *
 * Audit B10.1 (2026-05-07): introduced alongside `CorrelationIdFilter`.
 *
 * Safe to use as static state because PHP-FPM serves one request per
 * worker process. The filter's `before()` sets it, `after()` does NOT
 * flush it (the framework reaps the process). Tests must call
 * `flush()` between assertions.
 */
final class RequestIdHolder
{
    private static ?string $id = null;

    public static function set(string $id): void
    {
        self::$id = $id;
    }

    public static function get(): ?string
    {
        return self::$id;
    }

    public static function flush(): void
    {
        self::$id = null;
    }
}
