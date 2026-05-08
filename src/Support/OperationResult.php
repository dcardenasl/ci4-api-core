<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

/**
 * Explicit domain outcome returned by command-like service operations.
 */
readonly class OperationResult
{
    /**
     * @param array<string, string|list<string>>|string $errors
     */
    private function __construct(
        public OperationState $state,
        public mixed $data = null,
        public ?string $message = null,
        public array|string $errors = [],
        public ?int $httpStatus = null
    ) {
    }

    public static function success(mixed $data = null, ?string $message = null, ?int $httpStatus = null): self
    {
        return new self(OperationState::SUCCESS, $data, $message, [], $httpStatus);
    }

    public static function accepted(mixed $data = null, ?string $message = null, ?int $httpStatus = null): self
    {
        return new self(OperationState::ACCEPTED, $data, $message, [], $httpStatus ?? 202);
    }

    /**
     * @param array<string, string|list<string>>|string $errors
     */
    public static function error(array|string $errors, ?string $message = null, ?int $httpStatus = null): self
    {
        return new self(OperationState::ERROR, null, $message, $errors, $httpStatus);
    }

    public function isError(): bool
    {
        return $this->state === OperationState::ERROR;
    }

    public function isAccepted(): bool
    {
        return $this->state === OperationState::ACCEPTED;
    }
}
