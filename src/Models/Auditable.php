<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models;

use dcardenasl\Ci4ApiCore\Http\ContextHolder;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;

/**
 * Auditable Trait
 *
 * Automatically logs create, update, and delete actions for models
 *
 * SECURITY NOTE: This trait uses Entity::toArray() to ensure sensitive
 * fields (passwords, tokens) are filtered before logging.
 *
 * Make sure your Entity classes override toArray() to exclude sensitive data:
 *
 * class UserEntity extends Entity {
 *     public function toArray(...): array {
 *         $data = parent::toArray(...);
 *         unset($data['password']);
 *         return $data;
 *     }
 * }
 */
trait Auditable
{
    /**
     * Resolved once by model constructor to keep trait framework-agnostic.
     */
    protected ?AuditServiceInterface $auditService = null;

    public function setAuditService(AuditServiceInterface $auditService): void
    {
        $this->auditService = $auditService;
    }

    protected function initAuditable(): void
    {
        if (property_exists($this, 'beforeUpdate')) {
            $this->beforeUpdate = array_values(array_unique(array_merge($this->beforeUpdate, ['auditBeforeUpdate'])));
        }

        if (property_exists($this, 'beforeDelete')) {
            $this->beforeDelete = array_values(array_unique(array_merge($this->beforeDelete, ['auditBeforeDelete'])));
        }

        if (property_exists($this, 'afterInsert')) {
            $this->afterInsert = array_values(array_unique(array_merge($this->afterInsert, ['auditInsert'])));
        }

        if (property_exists($this, 'afterUpdate')) {
            $this->afterUpdate = array_values(array_unique(array_merge($this->afterUpdate, ['auditUpdate'])));
        }

        if (property_exists($this, 'afterDelete')) {
            $this->afterDelete = array_values(array_unique(array_merge($this->afterDelete, ['auditDelete'])));
        }
    }

    /**
     * Temporary storage for old values before update/delete
     */
    protected array $auditOldValues = [];

    /**
     * Inject old values from service layer to avoid redundant DB queries.
     */
    public function setAuditOldValues(int $id, array|object $values): void
    {
        $this->auditOldValues[$id] = is_object($values)
            ? (method_exists($values, 'toArray') ? $values->toArray() : (array) $values)
            : $values;
    }

    /**
     * Capture old values before update (fallback if not injected)
     */
    protected function auditBeforeUpdate(array $data): array
    {
        if (isset($data['id'])) {
            $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];

            // Only query if not already injected by service layer
            if (!isset($this->auditOldValues[$id])) {
                $old = $this->find($id);
                if ($old) {
                    $this->setAuditOldValues($id, $old);
                }
            }
        }

        return $data;
    }

    /**
     * Capture entity data before delete (fallback if not injected)
     */
    protected function auditBeforeDelete(array $data): array
    {
        if (isset($data['id'])) {
            $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];

            // Only query if not already injected by service layer
            if (!isset($this->auditOldValues[$id])) {
                $entity = $this->find($id);
                if ($entity) {
                    $this->setAuditOldValues($id, $entity);
                }
            }
        }

        return $data;
    }

    /**
     * Audit insert operations
     *
     * @param array $data
     * @return void
     */
    protected function auditInsert(array $data): void
    {
        if (!isset($data['id'])) {
            return;
        }

        $auditService = $this->getAuditService();
        $context = ContextHolder::get();

        $auditService->logCreate(
            $this->getEntityType(),
            is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'],
            $data['data'] ?? [],
            $context
        );
    }

    /**
     * Audit update operations
     *
     * @param array $data
     * @return void
     */
    protected function auditUpdate(array $data): void
    {
        if (!isset($data['id'])) {
            return;
        }

        $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];

        // Get old values from before update (captured in auditBeforeUpdate)
        if (!isset($this->auditOldValues[$id])) {
            return;
        }

        $oldValues = $this->auditOldValues[$id];
        $newValues = array_merge($oldValues, $data['data'] ?? []);

        // Clear stored old values
        unset($this->auditOldValues[$id]);

        $auditService = $this->getAuditService();
        $context = ContextHolder::get();

        $auditService->logUpdate(
            $this->getEntityType(),
            $id,
            $oldValues,
            $newValues,
            $context
        );
    }

    /**
     * Audit delete operations
     *
     * @param array $data
     * @return void
     */
    protected function auditDelete(array $data): void
    {
        if (!isset($data['id'])) {
            return;
        }

        $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];

        // Get entity data from before delete (captured in auditBeforeDelete)
        if (!isset($this->auditOldValues[$id])) {
            return;
        }

        $deletedData = $this->auditOldValues[$id];

        // Clear stored old values
        unset($this->auditOldValues[$id]);

        $auditService = $this->getAuditService();
        $context = ContextHolder::get();

        $auditService->logDelete(
            $this->getEntityType(),
            $id,
            $deletedData,
            $context
        );
    }

    protected function getAuditService(): AuditServiceInterface
    {
        if ($this->auditService === null) {
            throw new \RuntimeException(lang('Audit.serviceNotConfigured'));
        }

        return $this->auditService;
    }

    /**
     * Get entity type for audit logging
     *
     * Override this in your model if needed
     *
     * @return string
     */
    protected function getEntityType(): string
    {
        // Default: use table name without prefix
        return str_replace($this->DBPrefix ?? '', '', $this->table);
    }
}
