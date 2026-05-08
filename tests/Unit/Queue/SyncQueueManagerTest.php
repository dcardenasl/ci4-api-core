<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use dcardenasl\Ci4ApiCore\Queue\Job;
use dcardenasl\Ci4ApiCore\Queue\SyncQueueManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class SyncQueueManagerTest extends TestCase
{
    public function testPushRunsJobImmediately(): void
    {
        $manager = new SyncQueueManager();
        $manager->push(AlwaysSucceedsJob::class, ['value' => 42]);

        $this->assertSame(42, AlwaysSucceedsJob::$lastValue);
    }

    public function testPushReturnsZero(): void
    {
        $manager = new SyncQueueManager();
        $id = $manager->push(AlwaysSucceedsJob::class);

        $this->assertSame(0, $id);
    }

    public function testLaterRunsJobImmediatelyIgnoringDelay(): void
    {
        $manager = new SyncQueueManager();
        $manager->later(3600, AlwaysSucceedsJob::class, ['value' => 99]);

        $this->assertSame(99, AlwaysSucceedsJob::$lastValue);
    }

    public function testPushRethrowsExceptionWhenThrowOnFailureIsTrue(): void
    {
        $manager = new SyncQueueManager(throwOnFailure: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('job failure');
        $manager->push(AlwaysFailsJob::class);
    }

    public function testPushDoesNotRethrowWhenThrowOnFailureIsFalse(): void
    {
        $manager = new SyncQueueManager(throwOnFailure: false);

        // Must not throw — failed() is called but exception is swallowed.
        $manager->push(AlwaysFailsJob::class);
        $this->assertTrue(AlwaysFailsJob::$failedWasCalled);
    }

    public function testJobAttemptsIsSetToOne(): void
    {
        $manager = new SyncQueueManager();
        $manager->push(RecordsAttemptsJob::class);

        $this->assertSame(1, RecordsAttemptsJob::$recordedAttempts);
    }
}

// --------------- inline test doubles ---------------

final class AlwaysSucceedsJob extends Job
{
    public static int $lastValue = 0;

    public function handle(): void
    {
        self::$lastValue = (int) ($this->data['value'] ?? 0);
    }
}

final class AlwaysFailsJob extends Job
{
    public static bool $failedWasCalled = false;

    public function handle(): void
    {
        throw new RuntimeException('job failure');
    }

    public function failed(Throwable $exception): void
    {
        self::$failedWasCalled = true;
    }
}

final class RecordsAttemptsJob extends Job
{
    public static int $recordedAttempts = 0;

    public function handle(): void
    {
        self::$recordedAttempts = $this->attempts();
    }
}
