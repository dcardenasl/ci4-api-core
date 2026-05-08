<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;

/**
 * Response DTO Factory
 *
 * Centralizes the transformation of entities/models into Response DTOs
 * using the provided mappers.
 */
class ResponseDtoFactory
{
    /**
     * Map a single entity to a Response DTO
     */
    public function make(ResponseMapperInterface $mapper, object $entity): DataTransferObjectInterface
    {
        return $mapper->map($entity);
    }

    /**
     * Map a collection of entities to an array of Response DTOs
     *
     * @param  array<int|string, object>                 $entities
     * @return array<int|string, DataTransferObjectInterface>
     */
    public function makeCollection(ResponseMapperInterface $mapper, array $entities): array
    {
        return array_map(
            static fn ($entity) => $mapper->map($entity),
            $entities
        );
    }
}
