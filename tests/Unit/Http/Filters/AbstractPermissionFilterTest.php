<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Contracts\SecurityAuditLoggerInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ContextHolder;
use dcardenasl\Ci4ApiCore\Http\Filters\AbstractPermissionFilter;
use PHPUnit\Framework\TestCase;

final class AbstractPermissionFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        ContextHolder::flush();
    }

    /**
     * @param list<string> $permissions
     */
    private function filter(
        ?int $userId,
        array $permissions,
        ?string $bypassCode = null,
        ?SecurityAuditLoggerInterface $logger = null
    ): AbstractPermissionFilter {
        $fakeResponse = $this->createStub(ResponseInterface::class);

        return new class ($userId, $permissions, $bypassCode, $logger, $fakeResponse) extends AbstractPermissionFilter {
            /** @var array{0: int, 1: string}|null */
            public ?array $denialArgs = null;

            /**
             * @param list<string> $permissions
             */
            public function __construct(
                ?int $userId,
                array $permissions,
                private ?string $bypassCode,
                private ?SecurityAuditLoggerInterface $logger,
                private ResponseInterface $fakeResponse
            ) {
                ContextHolder::set(new SecurityContext($userId, [], $permissions));
            }

            protected function getSecurityAuditLogger(): ?SecurityAuditLoggerInterface
            {
                return $this->logger;
            }

            protected function superAdminBypassCode(): ?string
            {
                return $this->bypassCode;
            }

            protected function deny(int $status, string $message): ResponseInterface
            {
                $this->denialArgs = [$status, $message];

                return $this->fakeResponse;
            }
        };
    }

    private function plainRequest(): RequestInterface
    {
        return $this->createStub(RequestInterface::class);
    }

    public function testDeniesUnauthenticatedWithoutAContext(): void
    {
        ContextHolder::flush();
        $fakeResponse = $this->createStub(ResponseInterface::class);
        $filter = new class (null, $fakeResponse) extends AbstractPermissionFilter {
            public ?array $denialArgs = null;

            public function __construct(private ?SecurityAuditLoggerInterface $logger, private ResponseInterface $fakeResponse)
            {
            }

            protected function getSecurityAuditLogger(): ?SecurityAuditLoggerInterface
            {
                return $this->logger;
            }

            protected function deny(int $status, string $message): ResponseInterface
            {
                $this->denialArgs = [$status, $message];

                return $this->fakeResponse;
            }
        };

        $filter->before($this->plainRequest(), ['users.write']);

        $this->assertSame(401, $filter->denialArgs[0]);
    }

    public function testDeniesForbiddenWhenRequiredPermissionIsMissing(): void
    {
        $filter = $this->filter(userId: 1, permissions: ['users.read']);

        $filter->before($this->plainRequest(), ['users.write']);

        $this->assertSame(403, $filter->denialArgs[0]);
    }

    public function testDeniesForbiddenWhenNoPermissionArgumentIsDeclared(): void
    {
        $filter = $this->filter(userId: 1, permissions: ['users.write']);

        $filter->before($this->plainRequest(), []);

        $this->assertSame(403, $filter->denialArgs[0]);
    }

    public function testAllowsThroughWhenRequiredPermissionIsPresent(): void
    {
        $filter = $this->filter(userId: 1, permissions: ['users.write']);

        $result = $filter->before($this->plainRequest(), ['users.write']);

        $this->assertNull($result);
        $this->assertNull($filter->denialArgs);
    }

    public function testBypassCodeIsDisabledByDefault(): void
    {
        // A context carrying a permission that WOULD be a superadmin code
        // in a subclass, but this base filter never opts in — must still
        // deny for the missing specific permission. Locks in the
        // backward-compatible default for existing consumers (e.g. the
        // hub's PermissionFilter today) that don't override the hook.
        $filter = $this->filter(userId: 1, permissions: ['iam.superadmin-access']);

        $filter->before($this->plainRequest(), ['users.write']);

        $this->assertSame(403, $filter->denialArgs[0]);
    }

    public function testBypassCodeAllowsThroughWhenOptedIn(): void
    {
        $filter = $this->filter(userId: 1, permissions: ['iam.superadmin-access'], bypassCode: 'iam.superadmin-access');

        $result = $filter->before($this->plainRequest(), ['users.write']);

        $this->assertNull($result);
        $this->assertNull($filter->denialArgs);
    }

    public function testBypassCodeDoesNotRescueARouteWithNoDeclaredPermission(): void
    {
        $filter = $this->filter(userId: 1, permissions: ['iam.superadmin-access'], bypassCode: 'iam.superadmin-access');

        $filter->before($this->plainRequest(), []);

        $this->assertSame(403, $filter->denialArgs[0]);
    }

    public function testServiceTokenWithoutUserIdButWithPermissionsIsTreatedAsForbiddenNotUnauthenticated(): void
    {
        // A populated SecurityContext with no user_id (a service token) must
        // reach the permission check and get 403 for a missing code, not
        // 401 — this is the behavior the inline domain-app filters got
        // wrong before migrating onto this abstract base.
        $filter = $this->filter(userId: null, permissions: ['other.permission']);

        $filter->before($this->plainRequest(), ['users.write']);

        $this->assertSame(403, $filter->denialArgs[0]);
    }

    public function testLogsAuthorizationDeniedOnForbidden(): void
    {
        $logger = $this->createMock(SecurityAuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logAuthorizationDeniedFromRequest')
            ->with($this->isInstanceOf(RequestInterface::class), 'users.write', null, 1);

        $filter = $this->filter(userId: 1, permissions: [], logger: $logger);

        $filter->before($this->plainRequest(), ['users.write']);
    }

    public function testAfterReturnsResponseUnchanged(): void
    {
        $filter = $this->filter(userId: 1, permissions: ['users.write']);
        $response = $this->createStub(ResponseInterface::class);

        $result = $filter->after($this->plainRequest(), $response);

        $this->assertSame($response, $result);
    }
}
