<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services\Iam;

use dcardenasl\Ci4ApiCore\Contracts\Iam\PermissionResolverInterface;
use dcardenasl\Ci4ApiCore\Contracts\SecurityAuditLoggerInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;

/**
 * Hierarchical authorization rules for IAM operations (abstract base).
 *
 * SuperAdmin = actor whose effective permissions include
 * `superAdminPermission()`. SuperAdmin bypasses every assert except
 * `assertNotSelf` (which intentionally applies to everyone to prevent
 * accidental lock-out).
 *
 * Non-SuperAdmin actors:
 *   - cannot grant a permission they do not own (`assertCanGrantPermissions`)
 *   - cannot grant a role whose permissions exceed their own
 *     (`assertCanGrantRoles`)
 *   - cannot modify roles flagged as system (`assertCanModifyRole`)
 *   - cannot operate on subjects who are SuperAdmin (`assertCanActOnSubject`)
 *   - cannot operate on themselves (`assertNotSelf`)
 *
 * Every denial is audited via the injected `SecurityAuditLoggerInterface`
 * and surfaced as `AuthorizationException` (HTTP 403). Concrete subclasses
 * provide the storage-bound hooks (`loadRoleSystemFlag`,
 * `resolvePermissionCodes`, `resolveRolePermissionCodes`).
 */
abstract class AbstractIamAuthorizationService
{
    public function __construct(
        protected readonly PermissionResolverInterface $resolver,
        protected readonly SecurityAuditLoggerInterface $audit,
    ) {
    }

    public function isSuperAdmin(?SecurityContext $context, int $applicationId = null): bool
    {
        $applicationId ??= $this->defaultApplicationId();

        if ($context === null || $context->user_id === null) {
            return false;
        }

        if ($context->permissions !== []) {
            return in_array($this->superAdminPermission(), $context->permissions, true);
        }

        return in_array(
            $this->superAdminPermission(),
            $this->resolver->resolve($context->user_id, $applicationId),
            true
        );
    }

    /**
     * @return list<string>
     */
    public function actorPermissions(?SecurityContext $context, int $applicationId = null): array
    {
        $applicationId ??= $this->defaultApplicationId();

        if ($context === null || $context->user_id === null) {
            return [];
        }

        if ($context->permissions !== []) {
            return $context->permissions;
        }

        return $this->resolver->resolve($context->user_id, $applicationId);
    }

    /**
     * @return list<string>
     */
    public function subjectPermissions(int $subjectUserId, int $applicationId = null): array
    {
        $applicationId ??= $this->defaultApplicationId();

        return $this->resolver->resolve($subjectUserId, $applicationId);
    }

    /**
     * Block grants of permissions the actor does not already hold.
     *
     * @param array<int, int> $permissionIds
     */
    public function assertCanGrantPermissions(?SecurityContext $context, array $permissionIds, int $applicationId = null): void
    {
        $applicationId ??= $this->defaultApplicationId();

        if ($permissionIds === [] || $this->isSuperAdmin($context, $applicationId)) {
            return;
        }

        $actorPerms = $this->actorPermissions($context, $applicationId);
        $codes      = $this->resolvePermissionCodes($permissionIds);
        $unowned    = array_values(array_diff($codes, $actorPerms));

        if ($unowned !== []) {
            $this->deny($context, 'cannotGrantUnownedPermission', [
                'unowned'        => $unowned,
                'permission_ids' => array_values($permissionIds),
            ]);
        }
    }

    /**
     * Block grants of roles whose permission set exceeds the actor's own.
     *
     * @param array<int, int> $roleIds
     */
    public function assertCanGrantRoles(?SecurityContext $context, array $roleIds, int $applicationId = null): void
    {
        $applicationId ??= $this->defaultApplicationId();

        if ($roleIds === [] || $this->isSuperAdmin($context, $applicationId)) {
            return;
        }

        $actorPerms = $this->actorPermissions($context, $applicationId);
        $codes      = $this->resolveRolePermissionCodes($roleIds);
        $unowned    = array_values(array_diff($codes, $actorPerms));

        if ($unowned !== []) {
            $this->deny($context, 'cannotGrantUnownedPermission', [
                'unowned'  => $unowned,
                'role_ids' => array_values($roleIds),
            ]);
        }
    }

    public function assertCanModifyRole(?SecurityContext $context, int $roleId, int $applicationId = null): void
    {
        $applicationId ??= $this->defaultApplicationId();

        if ($this->isSuperAdmin($context, $applicationId)) {
            return;
        }

        if ($this->loadRoleSystemFlag($roleId)) {
            $this->deny($context, 'cannotModifySystemRole', ['role_id' => $roleId]);
        }
    }

    /**
     * Self-protection. Applies to every actor, including SuperAdmin, to
     * prevent accidental lock-out (e.g. removing one's own role).
     */
    public function assertNotSelf(?SecurityContext $context, int $subjectUserId): void
    {
        if ($context !== null && $context->user_id === $subjectUserId) {
            $this->deny($context, 'cannotModifySelf', ['subject_id' => $subjectUserId]);
        }
    }

    public function assertCanActOnSubject(?SecurityContext $context, int $subjectUserId, int $applicationId = null): void
    {
        $applicationId ??= $this->defaultApplicationId();

        if ($this->isSuperAdmin($context, $applicationId)) {
            return;
        }

        $subjectPerms = $this->subjectPermissions($subjectUserId, $applicationId);
        if (in_array($this->superAdminPermission(), $subjectPerms, true)) {
            $this->deny($context, 'cannotActOnSuperAdmin', ['subject_id' => $subjectUserId]);
        }
    }

    /**
     * Convenience for the common "modifying user/membership X" flow.
     */
    public function assertCanModifySubject(?SecurityContext $context, int $subjectUserId, int $applicationId = null): void
    {
        $applicationId ??= $this->defaultApplicationId();
        $this->assertNotSelf($context, $subjectUserId);
        $this->assertCanActOnSubject($context, $subjectUserId, $applicationId);
    }

    public function assertSuperAdmin(?SecurityContext $context, int $applicationId = null): void
    {
        $applicationId ??= $this->defaultApplicationId();

        if (! $this->isSuperAdmin($context, $applicationId)) {
            $this->deny($context, 'cannotPerformSuperAdminOperation', []);
        }
    }

    // ------------------------------------------------------------------
    // Configuration hooks (override to customise codes / defaults)
    // ------------------------------------------------------------------

    protected function superAdminPermission(): string
    {
        return 'iam.superadmin-access';
    }

    protected function defaultApplicationId(): int
    {
        return 1;
    }

    /**
     * i18n key prefix used by `deny()`. Override to namespace messages
     * differently (e.g. `'CustomIam.'`).
     */
    protected function denyLanguagePrefix(): string
    {
        return 'Iam.';
    }

    protected function denyAction(): string
    {
        return 'iam.authorization.denied';
    }

    // ------------------------------------------------------------------
    // Storage hooks (subclasses must implement)
    // ------------------------------------------------------------------

    /**
     * Whether the given role is a "system" role (cannot be modified by
     * non-superadmins). Concrete subclass typically queries a `roles`
     * table for an `is_system` column.
     */
    abstract protected function loadRoleSystemFlag(int $roleId): bool;

    /**
     * Resolve the permission codes corresponding to the given permission IDs.
     *
     * @param  array<int, int> $permissionIds
     * @return list<string>
     */
    abstract protected function resolvePermissionCodes(array $permissionIds): array;

    /**
     * Resolve the union of permission codes belonging to the given roles.
     *
     * @param  array<int, int> $roleIds
     * @return list<string>
     */
    abstract protected function resolveRolePermissionCodes(array $roleIds): array;

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $details
     */
    protected function deny(?SecurityContext $context, string $messageKey, array $details): never
    {
        $this->audit->logAuthorizationDeniedFromContext(
            $this->denyAction(),
            array_merge(['rule' => $messageKey], $details),
            $context
        );

        $fullKey = $this->denyLanguagePrefix() . $messageKey;
        $message = function_exists('lang') ? (string) lang($fullKey) : $fullKey;

        throw new AuthorizationException($message);
    }
}
