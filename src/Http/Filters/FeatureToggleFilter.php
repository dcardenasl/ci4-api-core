<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Config\FeatureFlags;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * Feature Toggle Filter
 *
 * Returns 503 when a configured feature flag is disabled. Subclasses can
 * extend `recordToggle()` to integrate with metrics/observability and
 * `disabledMessage()` to override the i18n message map per feature flag.
 */
class FeatureToggleFilter implements FilterInterface
{
    /**
     * @param array<int|string, mixed>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $flag = is_array($arguments) && isset($arguments[0]) ? (string) $arguments[0] : '';
        if ($flag === '') {
            return $request;
        }

        /** @var FeatureFlags $flags */
        $flags = config('FeatureFlags', false);
        $enabled = $flags->isEnabled($flag);

        $this->recordToggle($flag, $enabled);

        if ($enabled) {
            return $request;
        }

        return Services::response()
            ->setJSON(ApiResponse::error([], $this->disabledMessage($flag), 503))
            ->setStatusCode(503);
    }

    /**
     * @param array<int|string, mixed>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    /**
     * Hook for subclasses to record a feature-flag evaluation in metrics.
     * Default: no-op. Implementations should swallow their own exceptions —
     * observability failures must never block feature evaluation.
     */
    protected function recordToggle(string $flag, bool $enabled): void
    {
        // no-op
    }

    /**
     * I18n message returned in the 503 body when the flag is disabled.
     * Subclasses can override to add app-specific flag mappings.
     */
    protected function disabledMessage(string $flag): string
    {
        return match ($flag) {
            'monitoring' => lang('Health.monitoringDisabled'),
            default      => lang('Api.requestFailed'),
        };
    }
}
