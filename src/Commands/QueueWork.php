<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Generic worker for the QueueManager shipped with ci4-api-core.
 *
 * Default behaviour: processes the `default` queue continuously. Use
 * `--once` for a single job, `--max-jobs N` to bound the run, or
 * `--queue <name>` to target a specific queue.
 */
class QueueWork extends BaseCommand
{
    protected $group       = 'Queue';
    protected $name        = 'queue:work';
    protected $description = 'Process jobs from the queue';
    protected $usage       = 'queue:work [options]';

    /** @var array<string, string> */
    protected $arguments = [];

    /** @var array<string, string> */
    protected $options = [
        '--queue'     => 'The queue to process (default: default)',
        '--once'      => 'Process a single job and exit',
        '--sleep'     => 'Seconds to sleep between iterations (default: 3)',
        '--max-jobs'  => 'Maximum number of jobs to process (0 = unlimited)',
        '--job-delay' => 'Seconds to wait between each processed job (default: 0)',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $queue    = $this->resolveOption('queue', 'default');
        $once     = CLI::getOption('once') !== null || $this->resolveOption('once') !== null;
        $sleep    = (int) $this->resolveOption('sleep', '3');
        $maxJobs  = (int) $this->resolveOption('max-jobs', '0');
        $jobDelay = (int) $this->resolveOption('job-delay', '0');

        $queueManager  = Services::queueManager(false);
        $processedJobs = 0;

        CLI::write("Queue worker started for queue: {$queue}", 'green');

        if ($once) {
            CLI::write('Processing single job...', 'yellow');
            $processed = $queueManager->process($queue);
            CLI::write($processed ? 'Job processed' : 'No pending jobs found', $processed ? 'green' : 'yellow');

            return;
        }

        while (true) {
            try {
                $stats = $queueManager->getStats($queue);

                if ($stats['pending'] > 0) {
                    CLI::write(sprintf(
                        '[%s] Processing job... (Pending: %d, Processing: %d, Failed: %d)',
                        date('Y-m-d H:i:s'),
                        $stats['pending'],
                        $stats['processing'],
                        $stats['failed']
                    ), 'yellow');

                    $queueManager->process($queue);
                    $processedJobs++;

                    CLI::write(sprintf(
                        '[%s] Job completed (Total processed: %d)',
                        date('Y-m-d H:i:s'),
                        $processedJobs
                    ), 'green');

                    if ($jobDelay > 0) {
                        sleep($jobDelay);
                    }

                    if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
                        CLI::write("Reached maximum jobs limit ({$maxJobs}). Exiting...", 'cyan');
                        break;
                    }
                } else {
                    if ($processedJobs > 0) {
                        CLI::write(sprintf(
                            '[%s] No pending jobs. Waiting %d seconds...',
                            date('Y-m-d H:i:s'),
                            $sleep
                        ), 'cyan');
                    }

                    sleep($sleep);
                }
            } catch (\Throwable $e) {
                CLI::error(sprintf(
                    '[%s] Error: %s',
                    date('Y-m-d H:i:s'),
                    $e->getMessage()
                ));

                log_message('error', 'Queue worker error: ' . $e->getMessage());
                sleep($sleep);
            }
        }

        CLI::write('Queue worker stopped', 'green');
    }

    /**
     * Resolve a CLI option supporting both `--name value` and `--name=value`.
     */
    private function resolveOption(string $name, ?string $default = null): ?string
    {
        $value = CLI::getOption($name);

        if ($value === null || $value === true) {
            foreach (CLI::getOptions() as $key => $val) {
                if (str_starts_with((string) $key, "{$name}=")) {
                    return substr((string) $key, strlen($name) + 1);
                }
            }
        }

        if ($value === true) {
            return null;
        }

        return $value ?? $default;
    }
}
