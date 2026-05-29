<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Client;

/**
 * Immutable configuration for {@see HubClient}.
 *
 * Decouples the shared core client from a consumer's `Config\Hub` — a framework
 * `BaseConfig` exposes public properties, which cannot satisfy a contract, and
 * core must not depend on an app-level class. Each consumer maps its `Config\Hub`
 * into this value object inside its `Services::hubClient()` factory, so the core
 * client depends only on this package.
 */
final readonly class HubClientConfig
{
    public function __construct(
        public string $url,
        public string $apiKey,
        public string $introspectPath = '/api/v1/auth/introspect',
        public string $serviceTokenPath = '/api/v1/auth/service-token',
        public string $permissionsPath = '/api/v1/iam/permissions',
        public int $introspectCacheTtl = 60,
        public int $serviceTokenSafetyMargin = 30,
        public int $httpTimeout = 5,
    ) {
    }
}
