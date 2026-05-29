<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto;

use OpenApi\Attributes as OA;

/**
 * Generic Collection Response DTO
 */
#[OA\Schema(
    schema: 'CollectionResponse',
    title: 'Collection Response',
    description: 'Generic collection payload wrapper',
    required: ['data']
)]
readonly class CollectionResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<int, mixed> $data List of items in the collection
     * @param array<string, mixed>|null $meta Optional metadata
     */
    public function __construct(
        #[OA\Property(description: 'Result list', type: 'array', items: new OA\Items(type: 'object'))]
        public array $data,
        #[OA\Property(description: 'Optional metadata', type: 'object', additionalProperties: true)]
        public ?array $meta = null
    ) {
    }

    /**
     * Create from array shape
     *
     * @param array{data?: array<int, mixed>, meta?: array<string, mixed>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            data: $data['data'] ?? [],
            meta: $data['meta'] ?? null
        );
    }

    /**
     * @return array{data: array<int, mixed>, meta: ?array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta,
        ];
    }
}
