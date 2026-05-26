<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Repositories;

/**
 * Core Repository Interface
 *
 * Defines the standard contract for data persistence.
 * By typing against this interface, services become agnostic
 * to the underlying database or ORM implementation.
 *
 * @template TEntity of object
 */
interface RepositoryInterface
{
    /**
     * Find a record by its primary key
     *
     * @return TEntity|null
     */
    public function find(int|string $id): ?object;

    /**
     * Set the current entity state to avoid redundant DB lookups in auditable models.
     *
     * @param TEntity|array<string, mixed> $entity
     */
    public function setEntityContext(int|string $id, object|array $entity): void;

    /**
     * Override the audit action name for the next CUD operation on this repository.
     * No-op when the underlying model is not auditable. Returns $this for fluent chaining.
     */
    public function withAuditAction(string $action): static;

    /**
     * Get validation errors
     *
     * @return array<string, string|list<string>>
     */
    public function errors(): array;

    /**
     * Find all records matching criteria
     *
     * @return list<TEntity>
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
     * @return list<TEntity>      records keyed sequentially; empty list if `$ids` is empty
     */
    public function findByIds(array $ids): array;

    /**
     * Find a single record by a column value.
     *
     * Prefer this over `getModel()->where($col, $val)->first()` to get a narrow
     * `?object` return type that satisfies PHPStan level 8 without `@var` casts.
     *
     * @return TEntity|null
     */
    public function findBy(string $column, mixed $value): ?object;

    /**
     * Insert a new record
     *
     * @param array<string, mixed>|TEntity $data
     * @return int|string|bool The insert ID on success, false on failure
     */
    public function insert(array|object $data, bool $returnID = true): int|string|bool;

    /**
     * Update an existing record
     *
     * @param int|string|list<int|string>|null $id
     * @param array<string, mixed>|TEntity|null $data
     */
    public function update(int|string|array|null $id = null, array|object|null $data = null): bool;

    /**
     * Delete a record by ID
     *
     * @param int|string|list<int|string>|null $id
     */
    public function delete(int|string|array|null $id = null, bool $purge = false): bool;

    /**
     * Restore a soft-deleted record
     *
     * @param array<string, mixed> $data
     */
    public function restore(int|string $id, array $data = []): bool;

    /**
     * Get the underlying model instance
     */
    public function getModel(): \CodeIgniter\Model;

    /**
     * Get a paginated result based on given request criteria (filter, sort, search)
     *
     * @param array<string, mixed> $criteria DTO criteria as an array
     * @param int   $page     Current page
     * @param int   $perPage  Items per page
     * @param callable|null $baseCriteria Optional callback to apply security/base constraints
     * @return array{data: list<TEntity>, total: int, page: int, per_page: int, last_page: int, from: int, to: int}
     */
    public function paginateCriteria(array $criteria, int $page = 1, int $perPage = 20, ?callable $baseCriteria = null): array;
}
