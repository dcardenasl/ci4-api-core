<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Queue;

use Throwable;

/**
 * SyncQueueManager executes jobs immediately in the current request rather than
 * persisting them to the database. Intended for development environments, testing,
 * or single-server deployments where a background worker is not available.
 *
 * Drop-in replacement for QueueManager — same push()/later() signature, zero
 * infrastructure requirements. The `later()` method ignores the delay and runs
 * immediately (the sync transport has no concept of time-deferred execution).
 */
class SyncQueueManager
{
    /**
     * @param bool $throwOnFailure Re-throw exceptions from Job::handle(). Defaults to true
     *                            so failures surface during development. Set to false in
     *                            production to match the async queue behavior (failed jobs
     *                            are logged rather than crashing the request).
     */
    public function __construct(private readonly bool $throwOnFailure = true)
    {
    }

    /**
     * Instantiate and execute the job immediately.
     *
     * @param class-string<Job> $job  Fully-qualified Job class name
     * @param array<string, mixed> $data
     * @return int Always 0 (sync transport has no persistent job ID)
     */
    public function push(string $job, array $data = [], string $queue = 'default'): int
    {
        $this->run($job, $data);
        return 0;
    }

    /**
     * Instantiate and execute the job immediately, ignoring the requested delay.
     *
     * @param class-string<Job> $job
     * @param array<string, mixed> $data
     * @return int Always 0
     */
    public function later(int $delay, string $job, array $data = [], string $queue = 'default'): int
    {
        $this->run($job, $data);
        return 0;
    }

    /**
     * @param class-string<Job> $job
     * @param array<string, mixed> $data
     */
    private function run(string $job, array $data): void
    {
        $instance = new $job($data);
        $instance->setAttempts(1);

        try {
            $instance->handle();
        } catch (Throwable $e) {
            $instance->failed($e);

            if ($this->throwOnFailure) {
                throw $e;
            }
        }
    }
}
