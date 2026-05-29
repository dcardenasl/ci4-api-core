<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto;

use OpenApi\Attributes as OA;

/**
 * Generic Error Response DTO conforming to RFC 7807
 */
#[OA\Schema(
    schema: 'ErrorResponse',
    title: 'Error Response',
    description: 'RFC 7807 compliant error payload',
    required: ['type', 'title', 'status']
)]
readonly class ErrorResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, string|list<string>>|null $errors Detail of field-specific errors
     */
    public function __construct(
        #[OA\Property(description: 'URI identifying the problem type', example: 'about:blank')]
        public string $type,
        #[OA\Property(description: 'Short, human-readable summary of the problem', example: 'Validation Failed')]
        public string $title,
        #[OA\Property(description: 'HTTP status code', example: 422)]
        public int $status,
        #[OA\Property(description: 'Detailed explanation specific to this occurrence', example: 'The email field is required.')]
        public ?string $detail = null,
        #[OA\Property(description: 'URI identifying the specific occurrence', example: '/api/v1/users')]
        public ?string $instance = null,
        #[OA\Property(description: 'Map of field-specific validation errors', type: 'object', additionalProperties: true)]
        public ?array $errors = null
    ) {
    }

    /**
     * Create from array shape
     *
     * @param array{type?: string, title?: string, status?: int, detail?: string, instance?: string, errors?: array<string, string|list<string>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'about:blank',
            title: $data['title'] ?? 'Request Failed',
            status: (int) ($data['status'] ?? 500),
            detail: $data['detail'] ?? null,
            instance: $data['instance'] ?? null,
            errors: $data['errors'] ?? null
        );
    }

    /**
     * @return array{type: string, title: string, status: int, detail: ?string, instance: ?string, errors: ?array<string, string|list<string>>}
     */
    public function toArray(): array
    {
        return [
            'type'     => $this->type,
            'title'    => $this->title,
            'status'   => $this->status,
            'detail'   => $this->detail,
            'instance' => $this->instance,
            'errors'   => $this->errors,
        ];
    }
}
