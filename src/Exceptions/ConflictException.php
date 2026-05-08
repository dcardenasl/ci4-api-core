<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

use dcardenasl\Ci4ApiCore\Exceptions\ApiException;

/**
 * Conflict Exception (HTTP 409)
 *
 * Thrown when a request conflicts with the current state of the resource.
 * Examples:
 * - Email already verified
 * - Resource already exists
 * - Version conflict
 * - State transition not allowed
 */
class ConflictException extends ApiException
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 409;

    /**
     * Constructor
     *
     * @param string|null $message Error message (default: lang('Exceptions.conflictState'))
     * @param array<string, string|list<string>> $errors Structured error details
     */
    public function __construct(?string $message = null, array $errors = [])
    {
        parent::__construct($message ?? lang('Exceptions.conflictState'), $errors);
    }
}
