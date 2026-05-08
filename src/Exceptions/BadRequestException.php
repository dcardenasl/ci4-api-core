<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

/**
 * Bad Request Exception
 *
 * Thrown when the request is malformed or contains invalid data.
 * HTTP Status: 400 Bad Request
 */
class BadRequestException extends ApiException
{
    protected int $statusCode = 400;

    /**
     * Constructor
     *
     * @param string|null $message Error message (default: lang('Exceptions.badRequest'))
     * @param array<string, string|list<string>> $errors Additional error details
     */
    public function __construct(?string $message = null, array $errors = [])
    {
        parent::__construct($message ?? lang('Exceptions.badRequest'), $errors);
    }
}
