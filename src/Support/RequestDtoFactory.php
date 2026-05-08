<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Central factory for constructing request DTOs with proper validation.
 * Orchestrates data validation before DTO instantiation to ensure objects are always valid.
 */
class RequestDtoFactory
{
    public function __construct()
    {
    }

    /**
     * @template T of BaseRequestDTO
     * @param class-string<T>      $dtoClass
     * @param array<string, mixed> $data
     * @return T
     */
    public function make(string $dtoClass, array $data): BaseRequestDTO
    {
        if (!is_subclass_of($dtoClass, BaseRequestDTO::class)) {
            throw new \InvalidArgumentException("{$dtoClass} must extend " . BaseRequestDTO::class);
        }

        // DTO constructors are self-validating and map validated input.
        return new $dtoClass($data);
    }
}
