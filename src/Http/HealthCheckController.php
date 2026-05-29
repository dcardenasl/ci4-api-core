<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http;

use CodeIgniter\RESTful\ResourceController;
use dcardenasl\Ci4ApiCore\Monitoring\HealthChecker;

class HealthCheckController extends ResourceController
{
    protected HealthChecker $healthChecker;

    public function __construct()
    {
        $this->healthChecker = new HealthChecker();
    }

    public function index()
    {
        $checks = [
            'database' => $this->healthChecker->checkDatabase(),
            'disk'     => $this->healthChecker->checkDiskSpace(),
        ];

        // Include Redis if it's potentially needed
        if (extension_loaded('redis')) {
            $checks['redis'] = $this->healthChecker->checkRedis();
        }

        $status = $this->healthChecker->getOverallStatus($checks);

        $response = [
            'status' => $status,
            'checks' => $checks,
        ];

        // Use 503 only if unhealthy, 200 for healthy or degraded
        $code = ($status === 'unhealthy') ? 503 : 200;

        return $this->respond($response, $code);
    }
}
