<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

/**
 * Explicit domain outcome returned by command-like service operations.
 */
readonly class OperationResult
{
    public const SUCCESS = 'success';
    public const ACCEPTED = 'accepted';
    public const ERROR = 'error';

    private function __construct(
        public string $state,
        public mixed $data = null,
        public ?string $message = null,
        public array|string $errors = [],
        public ?int $httpStatus = null
    ) {
    }

    public static function success(mixed $data = null, ?string $message = null, ?int $httpStatus = null): self
    {
        return new self(self::SUCCESS, $data, $message, [], $httpStatus);
    }

    public static function accepted(mixed $data = null, ?string $message = null, ?int $httpStatus = null): self
    {
        return new self(self::ACCEPTED, $data, $message, [], $httpStatus ?? 202);
    }

    public static function error(array|string $errors, ?string $message = null, ?int $httpStatus = null): self
    {
        return new self(self::ERROR, null, $message, $errors, $httpStatus);
    }

    public function isError(): bool
    {
        return $this->state === self::ERROR;
    }

    public function isAccepted(): bool
    {
        return $this->state === self::ACCEPTED;
    }
}
