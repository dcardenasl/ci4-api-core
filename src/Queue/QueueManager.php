<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Queue;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Config\Queue as QueueConfig;
use Throwable;

class QueueManager implements QueueManagerInterface
{
    protected QueueConfig $config;

    /**
     * @var BaseConnection<object, object>
     */
    protected BaseConnection $db;

    public function __construct()
    {
        $this->config = config('Queue');
        $this->db = Database::connect($this->config->databaseConnection ?: 'default');
    }

    /**
     * Push a job onto the queue
     *
     * @param string $job Job class name
     * @param array<string, mixed> $data Job data
     * @param string $queue Queue name
     * @return int Job ID
     */
    public function push(string $job, array $data = [], string $queue = 'default'): int
    {
        $payload = json_encode([
            'job' => $job,
            'data' => $data,
        ]);

        $now = time();

        $jobData = [
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now,
            'created_at' => $now,
        ];

        $this->db->table('jobs')->insert($jobData);

        return (int) $this->db->insertID();
    }

    /**
     * Push a job onto the queue with delay
     *
     * @param int $delay Delay in seconds
     * @param string $job Job class name
     * @param array<string, mixed> $data Job data
     * @param string $queue Queue name
     * @return int Job ID
     */
    public function later(int $delay, string $job, array $data = [], string $queue = 'default'): int
    {
        $payload = json_encode([
            'job' => $job,
            'data' => $data,
        ]);

        $now = time();

        $jobData = [
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now + $delay,
            'created_at' => $now,
        ];

        $this->db->table('jobs')->insert($jobData);

        return (int) $this->db->insertID();
    }

    /**
     * Process jobs from the queue
     *
     * @param string $queue Queue name
     * @return bool True if a job was processed, false if no job was found
     */
    public function process(string $queue = 'default'): bool
    {
        $job = $this->getNextJob($queue);

        if (! $job) {
            return false;
        }

        $this->processJob($job);

        return true;
    }

    /**
     * Get the next available job
     *
     * @param string $queue
     * @return object|null
     */
    protected function getNextJob(string $queue): ?object
    {
        $now = time();
        $staleThreshold = $now - $this->config->retryAfter;

        // Attempt multiple times in case another worker reserves the same row first.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $query = $this->db->table('jobs')
                ->where('queue', $queue)
                ->where('available_at <=', $now)
                ->groupStart()
                    ->where('reserved_at', null)
                    ->orWhere('reserved_at <=', $staleThreshold)
                ->groupEnd()
                ->orderBy('id', 'ASC')
                ->get();

            $job = $query ? $query->getRow() : null;

            if (! $job) {
                return null;
            }

            // Atomic reservation: update only if still unreserved or stale.
            $this->db->table('jobs')
                ->where('id', $job->id)
                ->groupStart()
                    ->where('reserved_at', null)
                    ->orWhere('reserved_at <=', $staleThreshold)
                ->groupEnd()
                ->update(['reserved_at' => $now]);

            if ($this->db->affectedRows() === 1) {
                $job->reserved_at = $now;
                return $job;
            }
        }

        return null;
    }

    /**
     * Process a single job
     *
     * @param object $jobRecord
     * @return void
     */
    protected function processJob(object $jobRecord): void
    {
        $job = null;
        try {
            $payload = json_decode($jobRecord->payload, true);
            $jobClass = $payload['job'];
            $jobData = $payload['data'] ?? [];

            if (! class_exists($jobClass)) {
                throw new \RuntimeException("Job class {$jobClass} does not exist");
            }

            /** @var Job $job */
            $job = new $jobClass($jobData);
            $job->setAttempts((int) $jobRecord->attempts + 1);
            $job->setJobId((int) $jobRecord->id);

            // Execute the job
            $job->handle();

            // Job succeeded - delete it
            $this->db->table('jobs')->delete(['id' => $jobRecord->id]);
        } catch (Throwable $e) {
            $this->handleFailedJob($jobRecord, $e, $job);
        }
    }

    /**
     * Handle a failed job
     *
     * @param object $jobRecord
     * @param Throwable $exception
     * @param Job|null $job
     * @return void
     */
    protected function handleFailedJob(object $jobRecord, Throwable $exception, ?Job $job = null): void
    {
        $attempts = (int) $jobRecord->attempts + 1;
        $maxAttempts = $job->maxAttempts ?? $this->config->maxAttempts;

        if ($attempts >= $maxAttempts) {
            // Move to failed_jobs table
            $this->moveToFailedJobs($jobRecord, $exception);

            // Delete from jobs table
            $this->db->table('jobs')->delete(['id' => $jobRecord->id]);

            // Call the job's failed method
            try {
                if ($job) {
                    $job->failed($exception);
                } else {
                    $payload = json_decode($jobRecord->payload, true);
                    $jobClass = $payload['job'] ?? '';
                    $jobData = $payload['data'] ?? [];

                    if (class_exists($jobClass)) {
                        /** @var Job $fallbackJob */
                        $fallbackJob = new $jobClass($jobData);
                        $fallbackJob->failed($exception);
                    }
                }
            } catch (Throwable $e) {
                log_message('error', 'Failed to call job failed handler: ' . $e->getMessage());
            }
        } else {
            // Retry the job with exponential backoff if job is available, or fixed delay
            $delay = $job ? $job->getRetryDelay() : $this->config->retryAfter;
            $retryAt = time() + $delay;

            $this->db->table('jobs')
                ->where('id', $jobRecord->id)
                ->update([
                    'attempts' => $attempts,
                    'reserved_at' => null,
                    'available_at' => $retryAt,
                ]);

            log_message('info', "Job {$jobRecord->id} failed, will retry in {$delay}s (attempt {$attempts})");
        }
    }

    /**
     * Move a job to the failed_jobs table
     *
     * @param object $jobRecord
     * @param Throwable $exception
     * @return void
     */
    protected function moveToFailedJobs(object $jobRecord, Throwable $exception): void
    {
        $failedJob = [
            'connection' => $this->config->databaseConnection,
            'queue' => $jobRecord->queue,
            'payload' => $jobRecord->payload,
            'exception' => $exception->getMessage() . "\n" . $exception->getTraceAsString(),
            'failed_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->table('failed_jobs')->insert($failedJob);
    }

    /**
     * Get queue statistics
     *
     * @param string $queue
     * @return array<string, int>
     */
    public function getStats(string $queue = 'default'): array
    {
        $pending = (int) $this->db->table('jobs')
            ->where('queue', $queue)
            ->where('reserved_at', null)
            ->countAllResults();

        $processing = (int) $this->db->table('jobs')
            ->where('queue', $queue)
            ->where('reserved_at IS NOT NULL')
            ->countAllResults();

        $failed = (int) $this->db->table('failed_jobs')
            ->where('queue', $queue)
            ->countAllResults();

        return [
            'pending' => $pending,
            'processing' => $processing,
            'failed' => $failed,
        ];
    }
}
