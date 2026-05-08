<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto\Common;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Generic payload response DTO
 */
#[OA\Schema(
    schema: 'PayloadResponse',
    title: 'Payload Response',
    description: 'Generic payload wrapper'
)]
readonly class PayloadResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        #[OA\Property(
            description: 'Payload data',
            type: 'object',
            additionalProperties: true
        )]
        public array $payload
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(payload: $payload);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }
}
