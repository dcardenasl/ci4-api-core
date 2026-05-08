<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

/**
 * Interface for exceptions that provide an HTTP status code.
 */
interface HasStatusCode
{
    public function getStatusCode(): int;
}
