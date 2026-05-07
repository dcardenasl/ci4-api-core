<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

/**
 * Not Found Exception
 *
 * Thrown when a requested resource does not exist.
 * HTTP Status: 404
 */
class NotFoundException extends ApiException
{
    protected int $statusCode = 404;

    /**
     * Constructor
     *
     * @param string|null $message Error message (default: lang('Exceptions.resourceNotFound'))
     * @param array $errors Additional error details
     */
    public function __construct(?string $message = null, array $errors = [])
    {
        parent::__construct($message ?? lang('Exceptions.resourceNotFound'), $errors);
    }
}
