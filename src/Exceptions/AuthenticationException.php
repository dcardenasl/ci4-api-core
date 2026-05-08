<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

/**
 * Authentication Exception
 *
 * Thrown when authentication fails or credentials are invalid.
 * HTTP Status: 401 Unauthorized
 */
class AuthenticationException extends ApiException
{
    protected int $statusCode = 401;

    /**
     * Constructor
     *
     * @param string|null $message Error message (default: lang('Exceptions.authenticationFailed'))
     * @param array<string, string|list<string>> $errors Additional error details
     */
    public function __construct(?string $message = null, array $errors = [])
    {
        parent::__construct($message ?? lang('Exceptions.authenticationFailed'), $errors);
    }
}
