<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Monitoring;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class HealthChecker
{
    /**
     * @var BaseConnection<object, object>
     */
    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Check all system components
     *
     * @return array<string, array<string, mixed>>
     */
    public function checkAll(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'disk' => $this->checkDiskSpace(),
            'writable' => $this->checkWritableFolders(),
        ];
    }

    /**
     * Check database connection
     *
     * @return array<string, mixed>
     */
    public function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            // Simple query to check connection
            $this->db->query('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'message' => lang('Health.databaseConnectionSuccessful'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => lang('Health.databaseConnectionFailed', [$e->getMessage()]),
            ];
        }
    }

    /**
     * Check Redis connection (if available)
     *
     * @return array<string, mixed>
     */
    public function checkRedis(): array
    {
        if (! extension_loaded('redis')) {
            return [
                'status' => 'unavailable',
                'message' => lang('Health.redisExtensionNotLoaded'),
            ];
        }

        $start = microtime(true);

        try {
            $redis = new \Redis();
            $host = env('QUEUE_REDIS_HOST', '127.0.0.1');
            $port = (int) env('QUEUE_REDIS_PORT', 6379);

            $connected = $redis->connect($host, $port, 2); // 2 second timeout

            if (! $connected) {
                return [
                    'status' => 'unhealthy',
                    'message' => lang('Health.redisConnectionFailed'),
                ];
            }

            $redis->ping();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            $redis->close();

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'message' => lang('Health.redisConnectionSuccessful'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => lang('Health.redisCheckFailed', [$e->getMessage()]),
            ];
        }
    }

    /**
     * Check email service
     *
     * @return array<string, mixed>
     */
    public function checkEmail(): array
    {
        $provider = env('EMAIL_PROVIDER', 'smtp');
        $host = env('EMAIL_SMTP_HOST', '');

        if (empty($host)) {
            return [
                'status' => 'unconfigured',
                'message' => lang('Health.emailSmtpNotConfigured'),
            ];
        }

        return [
            'status' => 'configured',
            'provider' => $provider,
            'host' => $host,
            'message' => lang('Health.emailServiceConfigured'),
        ];
    }

    /**
     * Check queue system
     *
     * @return array<string, mixed>
     */
    public function checkQueue(): array
    {
        try {
            // Check if jobs table exists
            if (! $this->db->tableExists('jobs')) {
                return [
                    'status' => 'unhealthy',
                    'message' => lang('Health.jobsTableMissing'),
                ];
            }

            // Get queue stats
            $pending = $this->db->table('jobs')
                ->where('reserved_at', null)
                ->countAllResults();

            $processing = $this->db->table('jobs')
                ->where('reserved_at IS NOT NULL')
                ->countAllResults();

            $failed = $this->db->table('failed_jobs')
                ->countAllResults();

            return [
                'status' => 'healthy',
                'pending_jobs' => $pending,
                'processing_jobs' => $processing,
                'failed_jobs' => $failed,
                'message' => lang('Health.queueOperational'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => lang('Health.queueCheckFailed', [$e->getMessage()]),
            ];
        }
    }

    /**
     * Check disk space
     *
     * @return array<string, mixed>
     */
    public function checkDiskSpace(): array
    {
        $path = WRITEPATH;
        $freeSpace = disk_free_space($path);
        $totalSpace = disk_total_space($path);

        if ($freeSpace === false || $totalSpace === false) {
            return [
                'status' => 'unknown',
                'message' => lang('Health.diskCheckFailed'),
            ];
        }

        $usedSpace = $totalSpace - $freeSpace;
        $usedPercentage = round(($usedSpace / $totalSpace) * 100, 2);

        $status = 'healthy';
        if ($usedPercentage > 90) {
            $status = 'critical';
        } elseif ($usedPercentage > 80) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'free_space_mb' => round($freeSpace / 1024 / 1024, 2),
            'total_space_mb' => round($totalSpace / 1024 / 1024, 2),
            'used_percentage' => $usedPercentage,
            'message' => lang('Health.diskUsage', [$usedPercentage]),
        ];
    }

    /**
     * Check writable folders
     *
     * @return array<string, mixed>
     */
    public function checkWritableFolders(): array
    {
        $folders = [
            WRITEPATH . 'cache',
            WRITEPATH . 'logs',
            WRITEPATH . 'session',
            WRITEPATH . 'uploads',
        ];

        $nonWritable = [];

        foreach ($folders as $folder) {
            if (! is_writable($folder)) {
                $nonWritable[] = $folder;
            }
        }

        if (empty($nonWritable)) {
            return [
                'status' => 'healthy',
                'message' => lang('Health.writableFoldersAccessible'),
            ];
        }

        return [
            'status' => 'unhealthy',
            'non_writable' => $nonWritable,
            'message' => lang('Health.writableFoldersNotAccessible'),
        ];
    }

    /**
     * Get overall health status
     *
     * @param array<string, array<string, mixed>> $checks
     * @return string
     */
    public function getOverallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if (isset($check['status']) && in_array($check['status'], ['unhealthy', 'critical'], true)) {
                return 'unhealthy';
            }
        }

        foreach ($checks as $check) {
            if (isset($check['status']) && $check['status'] === 'warning') {
                return 'degraded';
            }
        }

        return 'healthy';
    }
}
