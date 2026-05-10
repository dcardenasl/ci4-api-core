<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Queue;

/**
 * Common contract for all queue backends (DB, Redis, Sync).
 *
 * Only the dispatch surface (push / later) is required — backends that support
 * processing (DB, Redis) expose process() and getStats() as concrete methods
 * without making them part of the shared interface.
 */
interface QueueManagerInterface
{
    /**
     * Push a job onto the queue for immediate processing.
     *
     * @param class-string<Job> $job  Fully-qualified Job class name
     * @param array<string, mixed> $data
     * @return int Job ID (0 if the backend has no persistent ID)
     */
    public function push(string $job, array $data = [], string $queue = 'default'): int;

    /**
     * Push a job onto the queue to be processed after a delay.
     *
     * @param int $delay Seconds to wait before the job becomes available
     * @param class-string<Job> $job
     * @param array<string, mixed> $data
     * @return int Job ID (0 if the backend has no persistent ID)
     */
    public function later(int $delay, string $job, array $data = [], string $queue = 'default'): int;
}
