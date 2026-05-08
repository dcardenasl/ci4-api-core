<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

/**
 * Service Unavailable Exception (HTTP 503)
 *
 * Thrown when a service is temporarily unavailable.
 * Examples:
 * - Database maintenance
 * - External API unavailable
 * - System overload
 * - Scheduled downtime
 */
class ServiceUnavailableException extends ApiException
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 503;

    /**
     * Constructor
     *
     * @param string|null $message Error message (default: lang('Exceptions.serviceUnavailable'))
     * @param array<string, string|list<string>> $errors Structured error details
     */
    public function __construct(?string $message = null, array $errors = [])
    {
        parent::__construct($message ?? lang('Exceptions.serviceUnavailable'), $errors);
    }
}
