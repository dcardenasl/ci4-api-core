<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services\Audit;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;

/**
 * No-op AuditService for projects without audit infrastructure.
 *
 * Write operations (log, logCreate, logUpdate, logDelete) silently discard
 * events — no DB writes, no side effects. Use this as the initial binding
 * produced by `php spark core:install` so a blank CI4 project can boot and
 * pass `core:check` without setting up the full audit stack.
 *
 * Read operations (index, show, byEntity) throw RuntimeException: they
 * require an audit repository that does not exist in a null-audit setup.
 * Upgrade the `auditService()` factory in app/Config/ApiCoreServices.php
 * to the full AuditService when audit log querying is needed.
 */
class NullAuditService implements AuditServiceInterface
{
    /**
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
    ): void {
        // intentionally no-op
    }

    /** @param array<string, mixed> $data */
    public function logCreate(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void
    {
        // intentionally no-op
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null, ?string $action = null): void
    {
        // intentionally no-op
    }

    /** @param array<string, mixed> $data */
    public function logDelete(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void
    {
        // intentionally no-op
    }

    public function index(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        throw new \RuntimeException(
            'NullAuditService does not support audit log queries. ' .
            'Replace the auditService() factory in app/Config/ApiCoreServices.php with the full AuditService.'
        );
    }

    public function show(int $id, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        throw new \RuntimeException(
            'NullAuditService does not support audit log queries. ' .
            'Replace the auditService() factory in app/Config/ApiCoreServices.php with the full AuditService.'
        );
    }

    public function byEntity(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        throw new \RuntimeException(
            'NullAuditService does not support audit log queries. ' .
            'Replace the auditService() factory in app/Config/ApiCoreServices.php with the full AuditService.'
        );
    }
}
