<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Repositories;

/**
 * Core Repository Interface
 *
 * Defines the standard contract for data persistence.
 * By typing against this interface, services become agnostic
 * to the underlying database or ORM implementation.
 */
interface RepositoryInterface
{
    /**
     * Find a record by its primary key
     */
    public function find(int|string $id): ?object;

    /**
     * Set the current entity state to avoid redundant DB lookups in auditable models.
     */
    public function setEntityContext(int|string $id, object|array $entity): void;

    /**
     * Get validation errors
     */
    public function errors(): array;

    /**
     * Find all records matching criteria
     */
    public function findAll(int $limit = 0, int $offset = 0): array;

    /**
     * Find a batch of records by their primary keys.
     *
     * Used by services that need to enrich a list of references in one query
     * (e.g. a gallery service hydrating the underlying file rows for a set of
     * pivot entries) without falling back to direct Model access.
     *
     * @param  list<int|string>  $ids
     * @return list<object>      records keyed sequentially; empty list if `$ids` is empty
     */
    public function findByIds(array $ids): array;

    /**
     * Insert a new record
     *
     * @return int|string|bool The insert ID on success, false on failure
     */
    public function insert(array|object $data, bool $returnID = true): int|string|bool;

    /**
     * Update an existing record
     */
    public function update(int|string|array $id = null, array|object|null $data = null): bool;

    /**
     * Delete a record by ID
     */
    public function delete(int|string|array $id = null, bool $purge = false): bool;

    /**
     * Restore a soft-deleted record
     */
    public function restore(int|string $id, array $data = []): bool;

    /**
     * Get the underlying model instance
     */
    public function getModel(): \CodeIgniter\Model;

    /**
     * Get a paginated result based on given request criteria (filter, sort, search)
     *
     * @param array $criteria DTO criteria as an array
     * @param int   $page     Current page
     * @param int   $perPage  Items per page
     * @param callable|null $baseCriteria Optional callback to apply security/base constraints
     * @return array Array containing 'data', 'total', 'page', 'per_page'
     */
    public function paginateCriteria(array $criteria, int $page = 1, int $perPage = 20, ?callable $baseCriteria = null): array;
}
