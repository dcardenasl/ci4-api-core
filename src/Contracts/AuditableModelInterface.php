<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts;

interface AuditableModelInterface
{
    /** @param object|array<string, mixed> $entity */
    public function setAuditOldValues(int $id, object|array $entity): void;

    /**
     * Override the audit action name for the next CUD call.
     * Resets automatically after the hook fires — safe to call once per operation.
     */
    public function withAuditAction(string $action): static;
}
