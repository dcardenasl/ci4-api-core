<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

use Exception;
use Throwable;

/**
 * Base API Exception
 *
 * Abstract base class for all custom API exceptions.
 * Provides structured error information with HTTP status codes.
 */
abstract class ApiException extends Exception implements HasStatusCode
{
    /**
     * HTTP status code for this exception
     *
     * @var int
     */
    protected int $statusCode = 500;

    /**
     * Structured error details
     *
     * @var array<string, string|list<string>>
     */
    protected array $errors = [];

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param array<string, string|list<string>> $errors Structured error details
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(string $message = '', array $errors = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get structured error details
     *
     * @return array<string, string|list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Convert exception to array format for JSON response
     *
     * @return array{status: string, code: int, message: string, errors: array<string, string|list<string>>}
     */
    public function toArray(): array
    {
        return [
            'status'  => 'error',
            'code'    => $this->statusCode,
            'message' => $this->getMessage(),
            'errors'  => $this->errors,
        ];
    }
}
