<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto;

readonly class ApiResponseDTO
{
    /**
     * @param array<string, mixed>|null $errors
     * @param array<string, mixed>|null $meta
     */
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $message = null,
        public ?array $errors = null,
        public ?array $meta = null
    ) {
    }
}
