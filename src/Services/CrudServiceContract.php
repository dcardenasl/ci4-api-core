<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Modernized CRUD Service Contract
 *
 * Enforces strict typing using Data Transfer Objects (DTOs)
 * for all input data.
 */
interface CrudServiceContract
{
    /**
     * Get a paginated list of resources
     */
    public function index(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface;

    /**
     * Get a single resource by ID
     */
    public function show(int $id, ?SecurityContext $context = null): DataTransferObjectInterface;

    /**
     * Create a new resource
     */
    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface;

    /**
     * Update an existing resource
     */
    public function update(int $id, DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface;

    /**
     * Remove a resource (soft or hard delete)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;
}
