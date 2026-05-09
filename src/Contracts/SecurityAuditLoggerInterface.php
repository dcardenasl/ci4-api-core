<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts;

use CodeIgniter\HTTP\RequestInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Contract for the cross-cutting security audit logger consumed by the
 * core HTTP filters (Jwt auth, permission, throttle).
 *
 * Implementations must be non-throwing — security auditing must never
 * alter primary control flow. Concrete implementations live in consumer
 * projects and may add domain-specific methods (e.g. api-key auth
 * failures) on top of this contract.
 */
interface SecurityAuditLoggerInterface
{
    public function logAuthorizationDeniedFromRequest(
        RequestInterface $request,
        string $required,
        ?string $actorContext,
        ?int $actorId,
        string $action = 'authorization_denied_permission'
    ): void;

    /**
     * @param array<string, mixed> $details
     */
    public function logAuthorizationDeniedFromContext(
        string $action,
        array $details,
        ?SecurityContext $context
    ): void;

    public function logRevokedTokenReuse(
        RequestInterface $request,
        ?int $userId,
        ?string $userContext,
        string $jti
    ): void;
}
