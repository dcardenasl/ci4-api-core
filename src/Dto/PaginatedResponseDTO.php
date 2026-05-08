<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto;

use OpenApi\Attributes as OA;

/**
 * Generic Paginated Response DTO
 */
#[OA\Schema(
    schema: 'PaginatedResponse',
    title: 'Paginated Response',
    description: 'Generic pagination payload',
    required: ['data', 'total', 'page', 'per_page']
)]
readonly class PaginatedResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<int, mixed> $data
     */
    public function __construct(
        #[OA\Property(description: 'Result set', type: 'array', items: new OA\Items(type: 'object'))]
        public array $data,
        #[OA\Property(description: 'Total number of records', example: 120)]
        public int $total,
        #[OA\Property(description: 'Current page', example: 1)]
        public int $page,
        #[OA\Property(property: 'per_page', description: 'Items per page', example: 20)]
        public int $per_page
    ) {
    }

    /**
     * @param array{data?: array<int, mixed>, total?: int, page?: int, per_page?: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            data: $data['data'] ?? [],
            total: (int) ($data['total'] ?? 0),
            page: (int) ($data['page'] ?? 1),
            per_page: (int) ($data['per_page'] ?? 10)
        );
    }

    /**
     * @return array{data: array<int, mixed>, total: int, page: int, per_page: int}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'total' => $this->total,
            'page' => $this->page,
            'per_page' => $this->per_page,
        ];
    }
}
