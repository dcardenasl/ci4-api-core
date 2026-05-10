<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts\Iam;

/**
 * Resolves the effective permission codes a user has within an application.
 *
 * Consumers may back this with any storage (relational tables, Redis, a
 * remote IAM hub) as long as the contract holds: codes are returned
 * sorted, deduplicated, and scoped to the (user, application) pair.
 */
interface PermissionResolverInterface
{
    /**
     * @return list<string> permission codes (sorted, deduplicated)
     */
    public function resolve(int $userId, int $applicationId): array;

    public function invalidateForUser(int $userId, int $applicationId): void;

    public function invalidateAll(): void;
}
