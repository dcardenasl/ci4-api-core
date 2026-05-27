<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Audit;

use dcardenasl\Ci4ApiCore\Dto\Common\PayloadResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use PHPUnit\Framework\TestCase;

final class AuditServiceInterfaceTest extends TestCase
{
    public function testAuditServiceInterfaceDocumentsStableAuditPayloadAliases(): void
    {
        $reflection = new \ReflectionClass(AuditServiceInterface::class);
        $doc = $reflection->getDocComment() ?: '';

        $this->assertStringContainsString('@phpstan-type AuditValues array<string, mixed>', $doc);
        $this->assertStringContainsString('@phpstan-type AuditMetadata array<string, mixed>', $doc);
        $this->assertTrue($reflection->hasMethod('log'));
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->hasMethod('show'));
        $this->assertTrue($reflection->hasMethod('byEntity'));
    }

    public function testAnonymousImplementationSatisfiesTheAuditServiceContract(): void
    {
        $service = new class () implements AuditServiceInterface {
            public function log(
                string $action,
                string $entityType,
                ?int $entityId,
                array $oldValues,
                array $newValues,
                ?SecurityContext $context = null,
                string $result = 'success',
                string $severity = 'info',
                array $metadata = [],
                ?string $requestId = null
            ): void {
            }

            public function logCreate(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void
            {
            }

            public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null, ?string $action = null): void
            {
            }

            public function logDelete(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void
            {
            }

            public function index(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
            {
                return PayloadResponseDTO::fromArray(['ok' => true]);
            }

            public function show(int $id, ?SecurityContext $context = null): DataTransferObjectInterface
            {
                return PayloadResponseDTO::fromArray(['id' => $id]);
            }

            public function byEntity(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
            {
                return PayloadResponseDTO::fromArray(['items' => []]);
            }
        };

        $this->assertInstanceOf(AuditServiceInterface::class, $service);
        $this->assertSame(['ok' => true], $service->index(PayloadResponseDTO::fromArray([]))->toArray());
        $this->assertSame(['id' => 7], $service->show(7)->toArray());
        $this->assertSame(['items' => []], $service->byEntity(PayloadResponseDTO::fromArray([]))->toArray());
    }
}
