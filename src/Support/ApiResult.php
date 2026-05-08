<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

/**
 * Value Object representing a normalized API result.
 *
 * Encapsulates the response body and the HTTP status code.
 */
readonly class ApiResult
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public array $body,
        public int $status = 200
    ) {
    }
}
