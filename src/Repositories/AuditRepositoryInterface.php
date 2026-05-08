<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Repositories;

/**
 * Audit Repository Interface
 *
 * Specialised query contract for audit logs, on top of the generic
 * RepositoryInterface. Implementations live in the consumer project
 * because they bind to the project's `audit_logs` table conventions.
 */
interface AuditRepositoryInterface extends RepositoryInterface
{
    /**
     * Get audit logs for an entity
     *
     * @return list<object>
     */
    public function getByEntity(string $entityType, int $entityId): array;

    /**
     * Get audit logs for a user
     *
     * @return list<object>
     */
    public function getByUser(int $userId, int $limit = 50): array;

    /**
     * Get recent audit logs
     *
     * @return list<object>
     */
    public function getRecent(int $limit = 100): array;

    /**
     * Get action facets for metrics
     *
     * @return list<array<string, mixed>>
     */
    public function getActionFacets(int $windowDays = 90, int $limit = 100): array;

    /**
     * Get entity type facets for metrics
     *
     * @return list<array<string, mixed>>
     */
    public function getEntityTypeFacets(int $windowDays = 90, int $limit = 100): array;
}
