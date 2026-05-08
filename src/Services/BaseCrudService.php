<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\PaginatedResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;

/**
 * Enhanced Base CRUD Service
 *
 * Provides automated, generic CRUD operations for all services.
 * Implements transaction safety and automatic Response DTO mapping.
 */
abstract class BaseCrudService implements CrudServiceContract
{
    use HandlesTransactions;

    /**
     * @param RepositoryInterface $repository The primary repository for this service
     * @param ResponseMapperInterface $responseMapper Mapper responsible for turning entities into DTOs
     */
    public function __construct(
        protected RepositoryInterface $repository,
        protected ResponseMapperInterface $responseMapper
    ) {
    }

    /**
     * Get a paginated list of resources
     */
    public function index(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        $requestData = $request->toArray();
        $page = $requestData['page'] ?? 1;
        $perPage = $requestData['per_page'] ?? 20;

        // The base criteria callable allows services to inject base constraints
        $baseCriteria = function ($builder): void {
            $this->applyBaseCriteria($builder);
        };

        // Pass the request data directly to the repository as criteria
        $criteria = $this->applyQueryOptions($requestData);
        $result = $this->repository->paginateCriteria($criteria, (int) $page, (int) $perPage, $baseCriteria);

        $entities = $this->enrichEntities((array) $result['data']);

        // Auto-map entities to response DTOs
        $result['data'] = array_map(
            function ($entity) {
                /** @var object $entity */
                return $this->mapToResponse($entity);
            },
            $entities
        );

        return PaginatedResponseDTO::fromArray([
            'data'    => $result['data'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * Get a single resource by ID
     */
    public function show(int $id, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var object|null $entity */
        $entity = $this->repository->find($id);

        if (!$entity) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        $enriched = $this->enrichEntities([$entity]);

        return $this->mapToResponse($enriched[0] ?? $entity);
    }

    /**
     * Create a new resource
     */
    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($request, $context) {
            $data = $request->toArray();
            $data = $this->beforeStore($data, $context);

            $id = $this->repository->insert($data);

            if ($id === false || $id === 0 || $id === "") {
                throw new ValidationException(lang('Api.validationFailed'), $this->repository->errors());
            }

            // $id can be true if no ID is generated, but we need an ID to find the resource.
            if ($id === true) {
                throw new \RuntimeException(lang('Api.resourceNotFound'));
            }

            $entity = $this->repository->find($id);

            if (!$entity) {
                throw new NotFoundException(lang('Api.resourceNotFound'));
            }

            $this->afterStore($entity, $context);

            return $this->mapToResponse($entity);
        });
    }

    /**
     * Update an existing resource
     */
    public function update(int $id, DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($id, $request, $context) {
            $entity = $this->repository->find($id);

            if (!$entity) {
                throw new NotFoundException(lang('Api.resourceNotFound'));
            }

            // Optimization: Pass the loaded entity to the repository for audit purposes
            $this->repository->setEntityContext($id, $entity);

            $data = $request->toArray();
            $data = $this->beforeUpdate($id, $data, $context);

            if (empty($data)) {
                throw new BadRequestException(lang('Api.noFieldsToUpdate'));
            }

            if (!$this->repository->update($id, $data)) {
                throw new ValidationException(lang('Api.updateError'), $this->repository->errors());
            }

            $updatedEntity = $this->repository->find($id);

            if (!$updatedEntity) {
                throw new NotFoundException(lang('Api.resourceNotFound'));
            }

            $this->afterUpdate($updatedEntity, $context);

            return $this->mapToResponse($updatedEntity);
        });
    }

    /**
     * Delete a resource (soft delete by default)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool
    {
        $entity = $this->repository->find($id);

        if (!$entity) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        // Optimization: Pass the loaded entity to the repository for audit purposes
        $this->repository->setEntityContext($id, $entity);

        return $this->wrapInTransaction(function () use ($id, $entity, $context) {
            $this->beforeDelete($id, $context);

            if (!$this->repository->delete($id)) {
                throw new \RuntimeException(lang('Api.deleteError'));
            }

            $this->afterDelete($entity, $context);

            return true;
        });
    }

    /**
     * Hooks for domain logic (Override in child services)
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function beforeStore(array $data, ?SecurityContext $context): array
    {
        return $data;
    }
    protected function afterStore(object $entity, ?SecurityContext $context): void
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        return $data;
    }
    protected function afterUpdate(object $entity, ?SecurityContext $context): void
    {
    }
    protected function beforeDelete(int $id, ?SecurityContext $context): void
    {
    }
    protected function afterDelete(object $entity, ?SecurityContext $context): void
    {
    }

    /**
     * Optional hook for child services to apply global criteria (e.g. security filters)
     * Note: Receives the QueryBuilder instance for decoupling from the Model.
     */
    protected function applyBaseCriteria(object $builder): void
    {
        // Default: no criteria
    }

    /**
     * Optional hook for child services to apply/modify query options (filtering, sorting, etc.)
     *
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>
     */
    protected function applyQueryOptions(array $criteria): array
    {
        return $criteria; // Default: pass through unmodified
    }

    /**
     * Optional hook to attach extra attributes to entities before they are
     * mapped to Response DTOs. Used to batch-enrich rows with related labels
     * (e.g. application_name, user_email) without N+1 queries.
     *
     * @param  array<int, object>  $entities
     * @return array<int, object>
     */
    protected function enrichEntities(array $entities): array
    {
        return $entities; // Default: pass through unmodified
    }

    /**
     * Map a model entity to its corresponding Response DTO
     */
    protected function mapToResponse(object $entity): DataTransferObjectInterface
    {
        return $this->responseMapper->map($entity);
    }
}
