<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

/**
 * Too Many Requests Exception (HTTP 429)
 *
 * Thrown when the rate limit has been exceeded.
 * Examples:
 * - API rate limit exceeded
 * - Login attempts exceeded
 * - Email sending limit reached
 */
class TooManyRequestsException extends ApiException
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 429;

    /**
     * Constructor
     *
     * @param string|null $message Error message (default: lang('Exceptions.tooManyRequests'))
     * @param array<string, string|list<string>> $errors Structured error details
     */
    public function __construct(?string $message = null, array $errors = [])
    {
        parent::__construct($message ?? lang('Exceptions.tooManyRequests'), $errors);
    }
}
