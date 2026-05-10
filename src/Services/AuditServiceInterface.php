<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Audit Service Interface
 */
interface AuditServiceInterface
{
    /**
     * Log an audit event (Internal)
     *
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $metadata
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
     * @param array<string, mixed> $data
     */
    public function logCreate(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void;

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null, ?string $action = null): void;

    /**
     * @param array<string, mixed> $data
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
