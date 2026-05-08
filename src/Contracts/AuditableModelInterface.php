<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts;

interface AuditableModelInterface
{
    /** @param object|array<string, mixed> $entity */
    public function setAuditOldValues(int $id, object|array $entity): void;
}
