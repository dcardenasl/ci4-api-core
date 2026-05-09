<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services\Audit;

use Config\Audit as AuditConfig;
use dcardenasl\Ci4ApiCore\Dto\Audit\AuditEventDTO;
use dcardenasl\Ci4ApiCore\Dto\Common\PayloadResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Queue\Jobs\WriteAuditLogJob;
use dcardenasl\Ci4ApiCore\Queue\QueueManager;
use dcardenasl\Ci4ApiCore\Repositories\AuditRepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;
use dcardenasl\Ci4ApiCore\Support\RelationLabelLoader;

/**
 * Modernized Audit Service
 *
 * Handles automated trail logging and provides queryable access to logs.
 * Inherits BaseCrudService for automated index and show operations.
 */
class AuditService extends BaseCrudService implements \dcardenasl\Ci4ApiCore\Services\AuditServiceInterface
{
    /**
     * @var bool Allow enabling audit logging during tests
     */
    public static bool $forceEnabledInTests = false;

    /**
     * Promoted-then-narrowed dependencies. Each is nullable in the
     * constructor signature (so callers can omit them) but is guaranteed
     * non-null once the constructor finishes via the `??=` defaults below.
     * The narrowed properties below shadow the promoted ones with non-nullable
     * types so PHPStan trusts dereferences in the rest of the class.
     */
    protected AuditPayloadSanitizer $payloadSanitizer;

    protected AuditWriter $auditWriter;

    protected AuditConfig $auditConfig;

    protected RelationLabelLoader $labels;

    public function __construct(
        protected AuditRepositoryInterface $auditRepository,
        ResponseMapperInterface $responseMapper,
        ?AuditWriter $auditWriter = null,
        protected ?QueueManager $queueManager = null,
        ?AuditConfig $auditConfig = null,
        protected bool $enabled = true,
        protected string $defaultIpAddress = '127.0.0.1',
        protected string $defaultUserAgent = 'system',
        ?AuditPayloadSanitizer $payloadSanitizer = null,
        ?RelationLabelLoader $labels = null
    ) {
        parent::__construct($auditRepository, $responseMapper);
        $this->payloadSanitizer = $payloadSanitizer ?? new AuditPayloadSanitizer();
        $this->auditWriter      = $auditWriter      ?? new AuditWriter($auditRepository);
        $this->auditConfig      = $auditConfig      ?? config('Audit');
        $this->labels           = $labels           ?? new RelationLabelLoader();
    }

    protected function enrichEntities(array $entities): array
    {
        return $this->labels->attachUserLabels($entities, 'user_id');
    }

    /**
     * Log an audit event
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
        if (!$this->enabled && !self::$forceEnabledInTests) {
            return;
        }

        $event = $this->buildEvent(
            $action,
            $entityType,
            $entityId,
            $oldValues,
            $newValues,
            $context,
            $result,
            $severity,
            $metadata,
            $requestId
        );

        $userId = $event->context?->user_id;
        $ipAddress = trim((string) ($event->context?->metadata['ip_address'] ?? ''));
        $userAgent = trim((string) ($event->context?->metadata['user_agent'] ?? ''));

        $ipAddress = $ipAddress !== '' ? $ipAddress : $this->defaultIpAddress;
        $userAgent = $userAgent !== '' ? $userAgent : $this->defaultUserAgent;

        $data = [
            'user_id'     => $userId,
            'action'      => $event->action,
            'entity_type' => $event->entity_type,
            'entity_id'   => $event->entity_id,
            'old_values'  => $event->old_values !== [] ? json_encode($event->old_values) : null,
            'new_values'  => $event->new_values !== [] ? json_encode($event->new_values) : null,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'result'      => $event->result,
            'severity'    => $event->severity,
            'request_id'  => $event->request_id,
            'metadata'    => $event->metadata !== [] ? json_encode($event->metadata) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        if ($this->shouldPersistSynchronously($event->action, $event->severity)) {
            $this->persistSynchronously($data);
            return;
        }

        if ($this->enqueueAudit($data)) {
            return;
        }

        $this->persistSynchronously($data);
    }

    /**
     * Log a create action
     */
    public function logCreate(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void
    {
        $this->log($action ?? 'create', $entityType, $entityId, [], $data, $context);
    }

    /**
     * Log an update action
     */
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null, ?string $action = null): void
    {
        $sanitizedOld = $this->payloadSanitizer->sanitize($oldValues);
        $sanitizedNew = $this->payloadSanitizer->sanitize($newValues);

        if (json_encode($sanitizedOld) !== json_encode($sanitizedNew)) {
            $this->log($action ?? 'update', $entityType, $entityId, $sanitizedOld, $sanitizedNew, $context);
        }
    }

    /**
     * Log a delete action
     */
    public function logDelete(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void
    {
        $this->log($action ?? 'delete', $entityType, $entityId, $data, [], $context);
    }

    private function normalizeEntityType(string $entityType): string
    {
        $normalized = strtolower(trim($entityType));
        $aliases = [
            'user' => 'users',
            'api-key' => 'api_keys',
            'file' => 'files',
        ];
        return $aliases[$normalized] ?? $normalized;
    }

    private function normalizeResult(string $result): string
    {
        $normalized = strtolower(trim($result));
        return in_array($normalized, ['success', 'failure', 'denied'], true) ? $normalized : 'success';
    }

    private function normalizeSeverity(string $severity): string
    {
        $normalized = strtolower(trim($severity));
        return in_array($normalized, ['info', 'warning', 'critical'], true) ? $normalized : 'info';
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $metadata
     */
    private function buildEvent(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?SecurityContext $context,
        string $result,
        string $severity,
        array $metadata,
        ?string $requestId
    ): AuditEventDTO {
        return new AuditEventDTO(
            action: $action,
            entity_type: $this->normalizeEntityType($entityType),
            entity_id: $entityId,
            old_values: $this->payloadSanitizer->sanitize($oldValues),
            new_values: $this->payloadSanitizer->sanitize($newValues),
            context: $context,
            result: $this->normalizeResult($result),
            severity: $this->normalizeSeverity($severity),
            metadata: $this->payloadSanitizer->sanitize($metadata),
            request_id: $requestId ?: ($context?->metadata['request_id'] ?? null)
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistSynchronously(array $data): void
    {
        try {
            $this->auditWriter->write($data);
        } catch (\Throwable $e) {
            // Audit logging must be non-blocking for primary flows.
            log_message('error', '[Audit] Synchronous write failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function enqueueAudit(array $data): bool
    {
        if (!$this->auditConfig->asyncEnabled || $this->queueManager === null) {
            return false;
        }

        try {
            $payload = $this->shrinkForQueue($data);
            $jobId = $this->queueManager->push(
                WriteAuditLogJob::class,
                ['audit' => $payload],
                $this->auditConfig->queueName
            );

            return $jobId > 0;
        } catch (\Throwable $e) {
            log_message('error', '[Audit] Failed to enqueue audit log: ' . $e->getMessage());
            return false;
        }
    }

    private function shouldPersistSynchronously(string $action, string $severity): bool
    {
        if ($severity === 'critical') {
            return true;
        }

        $criticalActions = $this->auditConfig->criticalActions;
        return in_array($action, $criticalActions, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function shrinkForQueue(array $data): array
    {
        $maxBytes = max(1024, $this->auditConfig->maxPayloadBytes);

        if ($this->jsonByteLength($data) <= $maxBytes) {
            return $data;
        }

        $reduced = $data;
        $reduced['metadata'] = $this->truncateString((string) ($reduced['metadata'] ?? ''), 4096);
        $reduced['old_values'] = $this->truncateString((string) ($reduced['old_values'] ?? ''), 8192);
        $reduced['new_values'] = $this->truncateString((string) ($reduced['new_values'] ?? ''), 8192);

        if ($this->jsonByteLength($reduced) <= $maxBytes) {
            return $reduced;
        }

        $reduced['metadata'] = null;
        if ($this->jsonByteLength($reduced) <= $maxBytes) {
            return $reduced;
        }

        $reduced['old_values'] = null;
        $reduced['new_values'] = null;
        return $reduced;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonByteLength(array $data): int
    {
        $encoded = json_encode($data);
        if (!is_string($encoded)) {
            return PHP_INT_MAX;
        }

        return strlen($encoded);
    }

    private function truncateString(string $value, int $maxLength): ?string
    {
        if ($value === '') {
            return null;
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        $suffix = '...[truncated]';
        return substr($value, 0, max(0, $maxLength - strlen($suffix))) . $suffix;
    }

    /**
     * Get audit logs for a specific entity
     */
    public function byEntity(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {

        $entityId = (int) ($request->{'entity_id'} ?? 0);
        $entityType = $this->normalizeEntityType((string) ($request->{'entity_type'} ?? ''));

        $logs    = $this->auditRepository->getByEntity($entityType, $entityId);
        $logs    = $this->enrichEntities($logs);
        $payload = array_map(
            fn ($log) => $this->mapToResponse($log)->toArray(),
            $logs
        );

        return PayloadResponseDTO::fromArray($payload);
    }

    /**
     * Audit logs are immutable via API
     */
    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        throw new \BadMethodCallException(lang('Audit.cannotCreateManual'));
    }

    /**
     * Audit logs are immutable via API
     */
    public function update(int $id, DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        throw new \BadMethodCallException(lang('Audit.immutable'));
    }
}
