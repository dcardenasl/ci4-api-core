<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models;

use CodeIgniter\Model;
use Config\Services;

/**
 * Shared base model for auditable resources.
 *
 * Centralizes audit callback bootstrap to avoid repeating constructor wiring
 * across all auditable models.
 *
 * Consumer requirement: the application's `Config\Services` class MUST expose
 * an `auditService()` factory method that returns an implementation of
 * `dcardenasl\Ci4ApiCore\Services\AuditServiceInterface`. The model resolves
 * the service lazily at initialize() time via that factory.
 */
abstract class BaseAuditableModel extends Model
{
    use Auditable;

    protected function initialize(): void
    {
        parent::initialize();
        $this->setAuditService(Services::auditService());
        $this->initAuditable();
    }
}
