<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

/**
 * Authorization Exception
 *
 * Thrown when user lacks permission to access a resource.
 * HTTP Status: 403 Forbidden
 */
class AuthorizationException extends ApiException
{
    protected int $statusCode = 403;

    /**
     * Constructor
     *
     * @param string|null $message Error message (default: lang('Exceptions.insufficientPermissions'))
     * @param array<string, string|list<string>> $errors Additional error details
     */
    public function __construct(?string $message = null, array $errors = [])
    {
        parent::__construct($message ?? lang('Exceptions.insufficientPermissions'), $errors);
    }
}
