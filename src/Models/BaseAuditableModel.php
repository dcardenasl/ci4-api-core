<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Contracts\AuditableModelInterface;

/**
 * Shared base model for auditable resources.
 *
 * Centralizes audit callback bootstrap to avoid repeating constructor wiring
 * across all auditable models.
 *
 * The `AuditServiceInterface` is resolved lazily on the first audit operation
 * via CI4's `service()` helper. To skip the service locator entirely, call
 * `$model->setAuditService($service)` before any write operation.
 */
abstract class BaseAuditableModel extends Model implements AuditableModelInterface
{
    use Auditable;

    protected function initialize(): void
    {
        parent::initialize();
        $this->initAuditable();
    }
}
