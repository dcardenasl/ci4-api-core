<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Audit;

use Config\Audit as AuditConfig;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\AuditRepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\Audit\AuditPayloadSanitizer;
use dcardenasl\Ci4ApiCore\Services\Audit\AuditService;
use dcardenasl\Ci4ApiCore\Services\Audit\AuditWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests the audit pipeline: AuditService → AuditWriter → AuditPayloadSanitizer → mock repo.
 *
 * Config\Audit is aliased to the package's own Audit config in tests/bootstrap.php.
 * BaseConfig::$override = false prevents auto-discovery from calling service('locator').
 */
final class AuditPipelineTest extends TestCase
{
    private AuditRepositoryInterface $auditRepo;
    private AuditConfig $auditConfig;
    private ResponseMapperInterface $responseMapper;

    protected function setUp(): void
    {
        $this->auditRepo = $this->createMock(AuditRepositoryInterface::class);
        $this->responseMapper = $this->createMock(ResponseMapperInterface::class);

        $this->auditConfig = new AuditConfig();
        $this->auditConfig->asyncEnabled = false; // force synchronous writes in tests
    }

    private function makeService(bool $enabled = true): AuditService
    {
        return new AuditService(
            auditRepository: $this->auditRepo,
            responseMapper: $this->responseMapper,
            auditWriter: new AuditWriter($this->auditRepo),
            queueManager: null,
            auditConfig: $this->auditConfig,
            enabled: $enabled,
        );
    }

    public function testLogCreatePersistsAuditRecordViaWriterAndRepo(): void
    {
        $this->auditRepo->expects($this->once())->method('insert');

        $this->makeService()->logCreate('product', 42, ['name' => 'Widget', 'price' => 9.99]);
    }

    public function testLogUpdateSkipsInsertWhenOldAndNewDataAreIdentical(): void
    {
        $this->auditRepo->expects($this->never())->method('insert');

        $this->makeService()->logUpdate('product', 42, ['name' => 'Widget'], ['name' => 'Widget']);
    }

    public function testLogUpdateSanitizesThenPersistsWhenNonSensitiveFieldsDiffer(): void
    {
        /** @var array<string, mixed>|null $capturedData */
        $capturedData = null;
        $this->auditRepo->method('insert')
            ->willReturnCallback(function (mixed $data) use (&$capturedData): int {
                $capturedData = $data;
                return 1;
            });

        // Name changed (non-sensitive) + password changed (sensitive).
        // After sanitization: old = {name:'Alice'}, new = {name:'Alice Updated'} — different → insert.
        $this->makeService()->logUpdate(
            'user',
            1,
            ['name' => 'Alice', 'password' => 'old_secret'],
            ['name' => 'Alice Updated', 'password' => 'new_secret'],
        );

        $this->assertNotNull($capturedData);
        $oldDecoded = json_decode((string) ($capturedData['old_values'] ?? '{}'), true);
        $newDecoded = json_decode((string) ($capturedData['new_values'] ?? '{}'), true);
        $this->assertArrayNotHasKey('password', $oldDecoded);
        $this->assertArrayNotHasKey('password', $newDecoded);
        $this->assertSame('Alice', $oldDecoded['name']);
        $this->assertSame('Alice Updated', $newDecoded['name']);
    }

    public function testLogDoesNotPersistWhenServiceIsDisabled(): void
    {
        $this->auditRepo->expects($this->never())->method('insert');

        $this->makeService(enabled: false)->logCreate('product', 1, ['name' => 'Widget']);
    }

    public function testSanitizerAndWriterRemoveSensitiveFieldsBeforePersisting(): void
    {
        $sanitizer = new AuditPayloadSanitizer();
        $writer = new AuditWriter($this->auditRepo);

        $clean = $sanitizer->sanitize([
            'user_id' => 5,
            'email'   => 'alice@example.com',
            'token'   => 'super-secret',
            'api_key' => 'apk_xxxx',
        ]);

        $this->auditRepo->expects($this->once())->method('insert')->with($clean);
        $writer->write($clean);

        $this->assertArrayNotHasKey('token', $clean);
        $this->assertArrayNotHasKey('api_key', $clean);
        $this->assertSame('alice@example.com', $clean['email']);
    }
}
