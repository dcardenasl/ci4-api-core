<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Mappers;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

/**
 * Maps an entity-like source into a Response DTO.
 *
 * The source can be either:
 *   - an object (typically a CI4 Entity) with a `toArray()` method, or
 *   - a plain associative `array<string, mixed>`.
 *
 * Accepting both lets services merge extra fields (translations,
 * relationship data) into the entity representation before mapping, without
 * needing a wrapper helper class just to pass an array through the contract.
 */
interface ResponseMapperInterface
{
    /**
     * @param object|array<string, mixed> $source
     */
    public function map(object|array $source): DataTransferObjectInterface;
}
