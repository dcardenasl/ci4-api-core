<?php

declare(strict_types=1);

namespace Tests\Integration\Monitoring;

use CodeIgniter\Database\BaseConnection;
use dcardenasl\Ci4ApiCore\Monitoring\HealthChecker;
use PHPUnit\Framework\TestCase;

/**
 * Subclass that overrides createConnection() so tests can inject a mock
 * BaseConnection without needing a live database.
 */
final class TestHealthChecker extends HealthChecker
{
    private BaseConnection $mockDb;

    public function __construct(BaseConnection $mockDb)
    {
        // Set the mock before parent::__construct() calls createConnection().
        $this->mockDb = $mockDb;
        parent::__construct();
    }

    protected function createConnection(): BaseConnection
    {
        return $this->mockDb;
    }
}

final class HealthCheckerTest extends TestCase
{
    private BaseConnection $db;
    private TestHealthChecker $checker;

    protected function setUp(): void
    {
        $this->db = $this->createMock(BaseConnection::class);
        $this->checker = new TestHealthChecker($this->db);
    }

    public function testCheckDatabaseReturnsHealthyWhenQuerySucceeds(): void
    {
        $this->db->method('query')->willReturn(new \stdClass());

        $result = $this->checker->checkDatabase();

        $this->assertSame('healthy', $result['status']);
        $this->assertArrayHasKey('response_time_ms', $result);
    }

    public function testCheckDatabaseReturnsUnhealthyWhenQueryThrows(): void
    {
        $this->db->method('query')->willThrowException(new \RuntimeException('Connection refused'));

        $result = $this->checker->checkDatabase();

        $this->assertSame('unhealthy', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testCheckDiskSpaceReturnsArrayWithStatusKey(): void
    {
        $result = $this->checker->checkDiskSpace();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['healthy', 'warning', 'critical', 'unknown']);
    }

    public function testCheckWritableFoldersReturnsArrayWithStatusKey(): void
    {
        $result = $this->checker->checkWritableFolders();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['healthy', 'unhealthy']);
    }

    public function testCheckAllReturnsExpectedComponentKeys(): void
    {
        $this->db->method('query')->willReturn(new \stdClass());

        $result = $this->checker->checkAll();

        $this->assertArrayHasKey('database', $result);
        $this->assertArrayHasKey('disk', $result);
        $this->assertArrayHasKey('writable', $result);
    }

    public function testGetOverallStatusIsHealthyWhenAllChecksPass(): void
    {
        $checks = [
            'database' => ['status' => 'healthy'],
            'disk'     => ['status' => 'healthy'],
            'writable' => ['status' => 'healthy'],
        ];

        $this->assertSame('healthy', $this->checker->getOverallStatus($checks));
    }

    public function testGetOverallStatusIsUnhealthyWhenOneCheckFails(): void
    {
        $checks = [
            'database' => ['status' => 'healthy'],
            'disk'     => ['status' => 'unhealthy'],
            'writable' => ['status' => 'healthy'],
        ];

        $this->assertSame('unhealthy', $this->checker->getOverallStatus($checks));
    }
}
