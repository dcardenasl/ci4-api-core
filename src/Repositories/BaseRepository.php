<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Repositories;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Contracts\AuditableModelInterface;
use dcardenasl\Ci4ApiCore\Filters\QueryBuilder;

/**
 * Base Repository
 *
 * Implements the Repository pattern by wrapping a CodeIgniter Model.
 * Encapsulates the QueryBuilder logic, keeping Services completely
 * decoupled from database-specific mechanisms.
 *
 * @template T of object
 * @implements RepositoryInterface<T>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @var Model */
    protected Model $model;

    /**
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function errors(): array
    {
        return $this->model->errors();
    }

    /**
     * @return T|null
     */
    public function find(int|string $id): ?object
    {
        /** @var T|null $result */
        $result = $this->model->find($id);

        return $result;
    }

    /**
     * Set the current entity state to avoid redundant DB lookups in auditable models.
     *
     * @param T|array<string, mixed> $entity
     */
    final public function setEntityContext(int|string $id, object|array $entity): void
    {
        if ($this->model instanceof AuditableModelInterface) {
            $this->model->setAuditOldValues((int) $id, $entity);
        }
    }

    /**
     * Override the audit action name for the next CUD operation.
     * No-op when the underlying model is not auditable.
     */
    public function withAuditAction(string $action): static
    {
        if ($this->model instanceof AuditableModelInterface) {
            $this->model->withAuditAction($action);
        }

        return $this;
    }

    /**
     * @return list<T>
     */
    public function findAll(int $limit = 0, int $offset = 0): array
    {
        /** @var list<T> $result */
        $result = $this->model->findAll($limit, $offset);

        return $result;
    }

    /**
     * @param  list<int|string> $ids
     * @return list<T>
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // CI4's Model::find() accepts an array and returns the matching rows
        // honoring the model's configured returnType.
        /** @var list<T>|T|null $result */
        $result = $this->model->find($ids);

        return is_array($result) ? $result : [];
    }

    /**
     * @return T|null
     */
    public function findBy(string $column, mixed $value): ?object
    {
        /** @var T|null $result */
        $result = $this->model->where($column, $value)->first();

        return $result;
    }

    /**
     * @param array<string, mixed>|T $data
     */
    public function insert(array|object $data, bool $returnID = true): int|string|bool
    {
        return $this->model->insert($data, $returnID);
    }

    /**
     * @param int|string|list<int|string>|null $id
     * @param array<string, mixed>|T|null $data
     */
    public function update(int|string|array|null $id = null, array|object|null $data = null): bool
    {
        // Guard against empty datasets to avoid CodeIgniter DataException
        if ($data === null || $data === []) {
            return true;
        }

        return $this->model->update($id, $data);
    }

    /**
     * @param int|string|list<int|string>|null $id
     */
    public function delete(int|string|array|null $id = null, bool $purge = false): bool
    {
        $result = $this->model->delete($id, $purge);

        return (bool) $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function restore(int|string $id, array $data = []): bool
    {
        // Clear the soft-delete timestamp
        $data['deleted_at'] = null;

        // Use the query builder directly to avoid model-level soft-delete filters during the update.
        // This is the most reliable way to restore a record in CodeIgniter 4.
        return (bool) $this->model->builder()
            ->where('id', $id)
            ->update($data);
    }

    /**
     * @param  array<string, mixed> $criteria DTO criteria as an array
     * @param  callable|null        $baseCriteria Optional callback to apply security/base constraints
     * @return array{data: list<T>, total: int, page: int, per_page: int, last_page: int, from: int, to: int}
     */
    final public function paginateCriteria(array $criteria, int $page = 1, int $perPage = 20, ?callable $baseCriteria = null): array
    {
        // Force reset the underlying model builder to ensure no previous state (filters, sorts) leaks
        // into this pagination request. This is critical for persistent execution contexts.
        $this->model->builder()->resetQuery();

        $builder = new QueryBuilder($this->model);

        if ($baseCriteria !== null) {
            $baseCriteria($builder);
        }

        // Apply filters
        if (isset($criteria['filter']) && is_array($criteria['filter']) && $criteria['filter'] !== []) {
            $builder->filter($criteria['filter']);
        }

        // Apply sort
        if (isset($criteria['sort']) && is_string($criteria['sort']) && $criteria['sort'] !== '') {
            $builder->sort($criteria['sort']);
        }

        // Apply search
        if (isset($criteria['search']) && is_string($criteria['search']) && $criteria['search'] !== '') {
            $builder->search($criteria['search']);
        }

        return $builder->paginate($page, $perPage);
    }

    /**
     * Get the underlying model instance (use with caution)
     */
    final public function getModel(): Model
    {
        return $this->model;
    }
}
