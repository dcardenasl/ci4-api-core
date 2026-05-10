<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts\Iam;

/**
 * Resolves the full set of permission codes belonging to an application.
 *
 * Used by service (M2M) tokens, where the calling application — not a
 * user — drives the JWT scope.
 */
interface ApplicationPermissionResolverInterface
{
    /**
     * @return list<string> permission codes (sorted, deduplicated)
     */
    public function resolve(int $applicationId): array;

    public function invalidate(int $applicationId): void;
}
