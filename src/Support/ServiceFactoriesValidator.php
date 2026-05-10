<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

/**
 * Validates that all four required ci4-api-core service factories are registered
 * in Config\Services before the first request is handled. Runs once per process
 * (static flag prevents redundant checks in subsequent requests on the same worker).
 */
class ServiceFactoriesValidator
{
    private static bool $checked = false;

    /** @var array<string, string> factory method => contract description */
    private const REQUIRED_FACTORIES = [
        'auditService'               => 'AuditServiceInterface (used by BaseAuditableModel)',
        'requestAuditContextFactory' => 'buildMetadata(ApiRequest): array (used by ApiController)',
        'requestDtoFactory'          => 'make(class-string, array): BaseRequestDTO (used by ApiController)',
        'requestDataCollector'       => 'collect(ApiRequest, ?array): array (used by ApiController)',
    ];

    public static function assertRegistered(): void
    {
        if (self::$checked) {
            return;
        }

        self::$checked = true;

        $servicesClass = 'Config\Services';

        if (!class_exists($servicesClass)) {
            return;
        }

        $missing = [];

        foreach (array_keys(self::REQUIRED_FACTORIES) as $method) {
            if (!method_exists($servicesClass, $method)) {
                $missing[] = $method;
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                'ci4-api-core: required service factories not registered in Config\\Services: '
                . implode(', ', $missing)
                . '. Run "php spark core:check" for details and fix instructions.'
            );
        }
    }

    /**
     * Reset the checked flag. Used in tests to force re-validation.
     */
    public static function reset(): void
    {
        self::$checked = false;
    }
}
