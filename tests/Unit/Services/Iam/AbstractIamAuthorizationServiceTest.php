<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use CodeIgniter\HTTP\RequestInterface;
use dcardenasl\Ci4ApiCore\Contracts\Iam\PermissionResolverInterface;
use dcardenasl\Ci4ApiCore\Contracts\SecurityAuditLoggerInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Services\Iam\AbstractIamAuthorizationService;
use PHPUnit\Framework\TestCase;

final class AbstractIamAuthorizationServiceTest extends TestCase
{
    public function testIsSuperAdminUsesContextPermissionsWhenPresent(): void
    {
        $service = $this->makeService(
            resolver: new RecordingPermissionResolver([]),
        );

        $context = new SecurityContext(1, [], ['iam.superadmin-access']);

        $this->assertTrue($service->isSuperAdmin($context));
    }

    public function testIsSuperAdminFallsBackToResolverWhenContextHasNoScope(): void
    {
        $resolver = new RecordingPermissionResolver(['iam.superadmin-access']);
        $service  = $this->makeService($resolver);

        $context = new SecurityContext(7, []);

        $this->assertTrue($service->isSuperAdmin($context));
        $this->assertSame([[7, 1]], $resolver->calls);
    }

    public function testIsSuperAdminReturnsFalseWhenContextIsNull(): void
    {
        $service = $this->makeService();

        $this->assertFalse($service->isSuperAdmin(null));
    }

    public function testAssertNotSelfBlocksSelfEvenForSuperadmin(): void
    {
        $service = $this->makeService();
        $context = new SecurityContext(42, [], ['iam.superadmin-access']);

        $this->expectException(AuthorizationException::class);

        $service->assertNotSelf($context, 42);
    }

    public function testAssertCanGrantPermissionsBlocksUnownedCodes(): void
    {
        $service = new TestableIamAuthorizationService(
            new RecordingPermissionResolver([]),
            new RecordingSecurityAuditLogger(),
            permissionCodes: ['users.write'],
        );

        $context = new SecurityContext(1, [], ['users.read']);

        $this->expectException(AuthorizationException::class);

        $service->assertCanGrantPermissions($context, [10]);
    }

    public function testAssertCanGrantPermissionsAllowsSuperadmin(): void
    {
        $service = new TestableIamAuthorizationService(
            new RecordingPermissionResolver([]),
            new RecordingSecurityAuditLogger(),
            permissionCodes: ['anything.delete'],
        );

        $context = new SecurityContext(1, [], ['iam.superadmin-access']);

        $service->assertCanGrantPermissions($context, [10]);
        $this->addToAssertionCount(1);
    }

    public function testAssertCanGrantRolesBlocksWhenAggregatedPermissionsExceedActor(): void
    {
        $service = new TestableIamAuthorizationService(
            new RecordingPermissionResolver([]),
            new RecordingSecurityAuditLogger(),
            rolePermissionCodes: ['users.write', 'users.delete'],
        );

        $context = new SecurityContext(1, [], ['users.read', 'users.write']);

        $this->expectException(AuthorizationException::class);

        $service->assertCanGrantRoles($context, [5]);
    }

    public function testAssertCanModifyRoleBlocksSystemRoleForNonSuperadmin(): void
    {
        $service = new TestableIamAuthorizationService(
            new RecordingPermissionResolver([]),
            new RecordingSecurityAuditLogger(),
            systemRoleIds: [99],
        );

        $context = new SecurityContext(1, [], ['users.write']);

        $this->expectException(AuthorizationException::class);

        $service->assertCanModifyRole($context, 99);
    }

    public function testAssertCanActOnSubjectBlocksOperatingOnSuperadmin(): void
    {
        $resolver = new RecordingPermissionResolver(['iam.superadmin-access']);
        $service  = new TestableIamAuthorizationService(
            $resolver,
            new RecordingSecurityAuditLogger(),
        );

        $context = new SecurityContext(1, [], ['users.write']);

        $this->expectException(AuthorizationException::class);

        $service->assertCanActOnSubject($context, 42);
    }

    public function testAssertSuperAdminThrowsForNonSuperadmin(): void
    {
        $service = $this->makeService();
        $context = new SecurityContext(1, [], ['users.read']);

        $this->expectException(AuthorizationException::class);

        $service->assertSuperAdmin($context);
    }

    public function testDenyNotifiesSecurityAuditLogger(): void
    {
        $logger  = new RecordingSecurityAuditLogger();
        $service = new TestableIamAuthorizationService(
            new RecordingPermissionResolver([]),
            $logger,
        );

        $context = new SecurityContext(1, [], ['users.read']);

        try {
            $service->assertSuperAdmin($context);
        } catch (AuthorizationException) {
            // expected
        }

        $this->assertCount(1, $logger->contextDenials);
        $this->assertSame('iam.authorization.denied', $logger->contextDenials[0]['action']);
        $this->assertSame('cannotPerformSuperAdminOperation', $logger->contextDenials[0]['details']['rule']);
    }

    private function makeService(
        ?PermissionResolverInterface $resolver = null,
        ?SecurityAuditLoggerInterface $logger = null,
    ): TestableIamAuthorizationService {
        return new TestableIamAuthorizationService(
            $resolver ?? new RecordingPermissionResolver([]),
            $logger ?? new RecordingSecurityAuditLogger(),
        );
    }
}

final class TestableIamAuthorizationService extends AbstractIamAuthorizationService
{
    /**
     * @param list<string> $permissionCodes      Codes returned by `resolvePermissionCodes()`
     * @param list<string> $rolePermissionCodes Codes returned by `resolveRolePermissionCodes()`
     * @param list<int>    $systemRoleIds       Role ids reported as `is_system=true`
     */
    public function __construct(
        PermissionResolverInterface $resolver,
        SecurityAuditLoggerInterface $audit,
        private readonly array $permissionCodes = [],
        private readonly array $rolePermissionCodes = [],
        private readonly array $systemRoleIds = [],
    ) {
        parent::__construct($resolver, $audit);
    }

    protected function loadRoleSystemFlag(int $roleId): bool
    {
        return in_array($roleId, $this->systemRoleIds, true);
    }

    protected function resolvePermissionCodes(array $permissionIds): array
    {
        return $this->permissionCodes;
    }

    protected function resolveRolePermissionCodes(array $roleIds): array
    {
        return $this->rolePermissionCodes;
    }
}

final class RecordingPermissionResolver implements PermissionResolverInterface
{
    /** @var list<array{0:int,1:int}> */
    public array $calls = [];

    /**
     * @param list<string> $codes
     */
    public function __construct(private array $codes)
    {
    }

    public function resolve(int $userId, int $applicationId): array
    {
        $this->calls[] = [$userId, $applicationId];

        return $this->codes;
    }

    public function invalidateForUser(int $userId, int $applicationId): void
    {
    }

    public function invalidateAll(): void
    {
    }
}

final class RecordingSecurityAuditLogger implements SecurityAuditLoggerInterface
{
    /** @var list<array{required:string,actor:?int,action:string}> */
    public array $requestDenials = [];

    /** @var list<array{action:string,details:array<string,mixed>}> */
    public array $contextDenials = [];

    /** @var list<array{jti:string,userId:?int}> */
    public array $revokedReuse = [];

    public function logAuthorizationDeniedFromRequest(
        RequestInterface $request,
        string $required,
        ?string $actorContext,
        ?int $actorId,
        string $action = 'authorization_denied_permission'
    ): void {
        $this->requestDenials[] = [
            'required' => $required,
            'actor'    => $actorId,
            'action'   => $action,
        ];
    }

    public function logAuthorizationDeniedFromContext(
        string $action,
        array $details,
        ?SecurityContext $context
    ): void {
        $this->contextDenials[] = [
            'action'  => $action,
            'details' => $details,
        ];
    }

    public function logRevokedTokenReuse(
        RequestInterface $request,
        ?int $userId,
        ?string $userContext,
        string $jti
    ): void {
        $this->revokedReuse[] = ['jti' => $jti, 'userId' => $userId];
    }
}
