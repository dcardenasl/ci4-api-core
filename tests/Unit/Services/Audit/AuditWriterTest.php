<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Audit;

use CodeIgniter\Database\Exceptions\DatabaseException;
use dcardenasl\Ci4ApiCore\Repositories\AuditRepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\Audit\AuditWriter;
use PHPUnit\Framework\TestCase;

final class AuditWriterTest extends TestCase
{
    public function testWriteInsertsDataViaRepository(): void
    {
        $data = ['action' => 'create', 'user_id' => 5, 'entity' => 'Article'];
        $repo = $this->createMock(AuditRepositoryInterface::class);
        $repo->expects($this->once())->method('insert')->with($data);

        (new AuditWriter($repo))->write($data);
    }

    public function testWriteRetriesWithNullUserIdOnFkConstraintError(): void
    {
        $data = ['action' => 'delete', 'user_id' => 99];
        $fkError = new DatabaseException('1452 Cannot add or update a child row: foreign key constraint fails');
        $callCount = 0;

        $repo = $this->createMock(AuditRepositoryInterface::class);
        $repo->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(function (array $payload) use ($data, $fkError, &$callCount): int {
                $callCount++;
                if ($callCount === 1) {
                    $this->assertSame(99, $payload['user_id']);
                    throw $fkError;
                }
                $this->assertNull($payload['user_id']);
                return 1;
            });

        (new AuditWriter($repo))->write($data);
    }

    public function testWriteRethrowsNonFkDatabaseException(): void
    {
        $data = ['action' => 'create', 'user_id' => 1];
        $dbError = new DatabaseException('Deadlock found when trying to get lock');

        $repo = $this->createMock(AuditRepositoryInterface::class);
        $repo->expects($this->once())->method('insert')->willThrowException($dbError);

        $this->expectException(DatabaseException::class);
        (new AuditWriter($repo))->write($data);
    }

    public function testWriteRethrowsNonDatabaseException(): void
    {
        $data = ['action' => 'create', 'user_id' => 1];
        $repo = $this->createMock(AuditRepositoryInterface::class);
        $repo->expects($this->once())->method('insert')->willThrowException(new \RuntimeException('unexpected'));

        $this->expectException(\RuntimeException::class);
        (new AuditWriter($repo))->write($data);
    }

    public function testWriteSkipsFkRetryWhenUserIdAbsent(): void
    {
        $data = ['action' => 'create'];
        $fkError = new DatabaseException('1452 foreign key constraint fails');

        $repo = $this->createMock(AuditRepositoryInterface::class);
        $repo->expects($this->once())->method('insert')->willThrowException($fkError);

        $this->expectException(DatabaseException::class);
        (new AuditWriter($repo))->write($data);
    }
}
