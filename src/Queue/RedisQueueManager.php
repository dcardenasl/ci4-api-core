<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Queue;

use Throwable;

/**
 * Redis-backed queue manager using the ext-redis PHP extension.
 *
 * Data layout:
 *   queue:jobs:{queue}     — LPUSH / RPOP list for available jobs (FIFO)
 *   queue:delayed:{queue}  — ZADD sorted set keyed by available_at timestamp
 *   queue:failed:{queue}   — LPUSH list for failed jobs (inspect / replay manually)
 *
 * Usage: wire in Config\Services:
 *
 *   public static function queueManager(): QueueManagerInterface
 *   {
 *       $redis = new \Redis();
 *       $redis->connect(env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', 6379));
 *       if ($password = env('REDIS_PASSWORD')) {
 *           $redis->auth($password);
 *       }
 *       return new RedisQueueManager($redis);
 *   }
 *
 * Requires: ext-redis PHP extension (https://github.com/phpredis/phpredis).
 */
class RedisQueueManager implements QueueManagerInterface
{
    public function __construct(private readonly \Redis $redis)
    {
    }

    /**
     * Push a job for immediate processing.
     *
     * @param class-string<Job> $job
     * @param array<string, mixed> $data
     */
    public function push(string $job, array $data = [], string $queue = 'default'): int
    {
        $payload = $this->encode($job, $data);
        $this->redis->lPush("queue:jobs:{$queue}", $payload);
        return 0;
    }

    /**
     * Push a job to run after $delay seconds.
     *
     * @param class-string<Job> $job
     * @param array<string, mixed> $data
     */
    public function later(int $delay, string $job, array $data = [], string $queue = 'default'): int
    {
        $payload = $this->encode($job, $data);
        $availableAt = time() + $delay;
        $this->redis->zAdd("queue:delayed:{$queue}", $availableAt, $payload);
        return 0;
    }

    /**
     * Move matured delayed jobs to the ready queue, then process one job.
     *
     * @return bool True if a job was processed, false if queue is empty.
     */
    public function process(string $queue = 'default'): bool
    {
        $this->releaseDelayed($queue);

        $payload = $this->redis->rPop("queue:jobs:{$queue}");

        if (!is_string($payload) || $payload === '') {
            return false;
        }

        $this->processPayload($payload, $queue);

        return true;
    }

    /**
     * Queue statistics (pending / delayed / failed counts).
     *
     * @return array<string, int>
     */
    public function getStats(string $queue = 'default'): array
    {
        return [
            'pending'  => (int) $this->redis->lLen("queue:jobs:{$queue}"),
            'delayed'  => (int) $this->redis->zCard("queue:delayed:{$queue}"),
            'failed'   => (int) $this->redis->lLen("queue:failed:{$queue}"),
        ];
    }

    private function releaseDelayed(string $queue): void
    {
        $now = time();
        $ready = $this->redis->zRangeByScore("queue:delayed:{$queue}", '-inf', (string) $now);

        if (empty($ready)) {
            return;
        }

        foreach ($ready as $payload) {
            $this->redis->lPush("queue:jobs:{$queue}", $payload);
        }

        $this->redis->zRemRangeByScore("queue:delayed:{$queue}", '-inf', (string) $now);
    }

    private function processPayload(string $payload, string $queue): void
    {
        $job = null;

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $jobClass = (string) $decoded['job'];
            $jobData = (array) ($decoded['data'] ?? []);

            if (!class_exists($jobClass)) {
                throw new \RuntimeException("Job class {$jobClass} does not exist");
            }

            /** @var Job $job */
            $job = new $jobClass($jobData);
            $job->setAttempts(1);
            $job->handle();
        } catch (Throwable $e) {
            log_message('error', '[RedisQueue] Job failed: ' . $e->getMessage());

            $failed = json_encode([
                'job'        => $payload,
                'exception'  => $e->getMessage(),
                'failed_at'  => date('Y-m-d H:i:s'),
            ]);

            $this->redis->lPush("queue:failed:{$queue}", (string) $failed);

            if ($job !== null) {
                try {
                    $job->failed($e);
                } catch (Throwable $inner) {
                    log_message('error', '[RedisQueue] Job::failed() threw: ' . $inner->getMessage());
                }
            }
        }
    }

    /**
     * @param class-string<Job> $job
     * @param array<string, mixed> $data
     */
    private function encode(string $job, array $data): string
    {
        return json_encode(['job' => $job, 'data' => $data, 'created_at' => time()], JSON_THROW_ON_ERROR);
    }
}
