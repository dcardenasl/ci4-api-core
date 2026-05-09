<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use CodeIgniter\Validation\ValidationInterface;
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
     * @param class-string<T>         $dtoClass
     * @param array<string, mixed>    $data
     * @param ValidationInterface|null $validation Optional; injected into DTO for testability.
     * @return T
     */
    public function make(string $dtoClass, array $data, ?ValidationInterface $validation = null): BaseRequestDTO
    {
        if (! is_subclass_of($dtoClass, BaseRequestDTO::class)) {
            throw new \InvalidArgumentException("{$dtoClass} must extend " . BaseRequestDTO::class);
        }

        $validation ??= \Config\Services::validation();

        return new $dtoClass($data, $validation);
    }
}
