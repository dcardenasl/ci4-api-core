<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Audit Service Interface
 *
 * @phpstan-type AuditValues array<string, mixed>
 * @phpstan-type AuditMetadata array<string, mixed>
 */
interface AuditServiceInterface
{
    /**
     * Log an audit event (Internal)
     *
     * @param AuditValues   $oldValues
     * @param AuditValues   $newValues
     * @param AuditMetadata $metadata
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?SecurityContext $context = null,
        string $result = 'success',
        string $severity = 'info',
        array $metadata = [],
        ?string $requestId = null
    ): void;

    /**
     * Log structured events (Internal)
     *
     * @param AuditValues $data
     */
    public function logCreate(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void;

    /**
     * @param AuditValues $oldValues
     * @param AuditValues $newValues
     */
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null, ?string $action = null): void;

    /**
     * @param AuditValues $data
     */
    public function logDelete(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void;

    /**
     * List audit logs (API)
     */
    public function index(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface;

    /**
     * Get single log (API)
     */
    public function show(int $id, ?SecurityContext $context = null): DataTransferObjectInterface;

    /**
     * Get logs by entity (Internal/API)
     */
    public function byEntity(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface;
}
