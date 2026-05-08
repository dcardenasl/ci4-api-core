<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * MaintenanceFilter — api-starter (audit B10.4, 2026-05-07)
 *
 * Returns `503 Service Unavailable` for every non-probe request when
 * `MAINTENANCE_MODE=true` is set in the environment.
 *
 * Bypassed paths (orchestrators must keep talking to us so they can
 * detect when we're back):
 *   - `/health`
 *   - `/ping`
 *   - `/ready`
 *   - `/live`
 *
 * Wired in `globals.before` so it short-circuits before correlationid,
 * locale, CORS, JWT — anything stateful.
 *
 * Operator usage:
 *   - Toggle on:  `export MAINTENANCE_MODE=true && systemctl reload php-fpm`
 *   - Toggle off: `unset MAINTENANCE_MODE && systemctl reload php-fpm`
 *
 * For zero-downtime deploys, flip the env on the **old** pods, drain
 * traffic, roll new pods, then flip the env off.
 */
class MaintenanceFilter implements FilterInterface
{
    private const BYPASS_PATHS = [
        '/health',
        '/ping',
        '/ready',
        '/live',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $this->isMaintenanceModeOn()) {
            return null;
        }

        if (! $request instanceof IncomingRequest) {
            return null;
        }

        $path = '/' . ltrim($request->getUri()->getPath(), '/');
        foreach (self::BYPASS_PATHS as $bypass) {
            if ($path === $bypass || str_starts_with($path, $bypass . '/')) {
                return null;
            }
        }

        $message = (string) (getenv('MAINTENANCE_MESSAGE') ?: env('MAINTENANCE_MESSAGE', 'Service is temporarily unavailable for maintenance.'));
        $retryAfter = (int) (getenv('MAINTENANCE_RETRY_AFTER') ?: env('MAINTENANCE_RETRY_AFTER', 60));

        return Services::response()
            ->setStatusCode(503)
            ->setHeader('Retry-After', (string) max(1, $retryAfter))
            ->setJSON(ApiResponse::error($message, 'ServiceUnavailable.maintenance', 503));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function isMaintenanceModeOn(): bool
    {
        $raw = (string) (getenv('MAINTENANCE_MODE') ?: env('MAINTENANCE_MODE', ''));

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }
}
