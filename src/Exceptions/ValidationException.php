<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

/**
 * Validation Exception
 *
 * Thrown when input validation fails.
 * HTTP Status: 422 Unprocessable Entity
 */
class ValidationException extends ApiException
{
    protected int $statusCode = 422;

    /**
     * Constructor
     *
     * @param string|null $message Error message (default: lang('Exceptions.validationFailed'))
     * @param array $errors Validation error details
     */
    public function __construct(?string $message = null, array $errors = [])
    {
        parent::__construct($message ?? lang('Exceptions.validationFailed'), $errors);
    }
}
