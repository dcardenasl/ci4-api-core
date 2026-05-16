<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Base API Configuration
 *
 * Centralizes all environment variables into strictly typed properties.
 * Prevents scattered env() calls throughout the business logic.
 *
 * Consumers SHOULD extend this class in their own `app/Config/Api.php`
 * (namespace `Config`) so they can override defaults or add domain
 * properties while inheriting auto-hydration from the environment:
 *
 * ```php
 * namespace Config;
 *
 * class Api extends \dcardenasl\Ci4ApiCore\Config\Api
 * {
 *     // Add overrides or extra properties here.
 * }
 * ```
 *
 * The constructor reads env vars when CodeIgniter's environment is set up
 * (i.e. the `env()` helper is available). Tests that instantiate the
 * config directly without bootstrapping CI4 still get sane defaults.
 */
class Api extends BaseConfig
{
    // Auth & Security
    public bool $jwtRevocationCheck = true;
    public bool $requireEmailVerification = true;
    public string $jwtSecretKey = '';
    public int $jwtAccessTokenTtl = 3600;
    public int $jwtRefreshTokenTtl = 604800;
    public int $jwtRevocationCacheTtl = 60;
    public int $jwtServiceTokenTtl = 900;
    public string $googleClientId = '';

    // Rate Limiting
    public int $rateLimitWindow = 60;
    public int $rateLimitRequests = 60;
    public int $rateLimitUserRequests = 100;
    public int $authRateLimitRequests = 5;
    public int $authRateLimitWindow = 900;

    // Search Engine
    public bool $searchEnabled = true;
    public bool $searchUseFulltext = true;
    public int $searchMinLength = 3;

    // Pagination
    public int $paginationDefaultLimit = 20;
    public int $paginationMaxLimit = 100;

    // File Management
    public int $fileMaxSize = 10485760; // 10MB
    public string $fileAllowedTypes = 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip';
    public string $fileStorageDriver = 'local';
    public string $fileUploadPath = 'writable/uploads/';
    public bool $filesUserScoped = true;

    // Logging & Monitoring
    public bool $requestLoggingEnabled = true;
    public int $slowQueryThreshold = 1000;
    public int $sloP95TargetMs = 500;

    // Outbound HTTP (service client defaults — read by AbstractServiceClient subclasses)
    public int $outboundHttpTimeout = 5;
    public int $outboundHttpRetries = 1;
    public int $outboundHttpRetryDelayMs = 250;

    /**
     * Routes (path-only, leading slash optional) that authenticate normally
     * via JWT but skip post-auth access policy checks such as email verification.
     *
     * Consumers can override or extend this list. The canonical case is an
     * endpoint a logged-in but unverified user must be able to call to
     * recover from an unmet policy (e.g. resend-verification).
     *
     * @var list<string>
     */
    public array $accessPolicyBypassRoutes = [];

    /**
     * Supported API versions and their lifecycle metadata.
     *
     * `DeprecationHeadersFilter` reads this map to inject `Deprecation`
     * and `Sunset` headers (RFC 8594 / IETF draft) and the
     * `Link: rel="successor-version"` header pointing at the replacement.
     *
     * @var array<string, array{status: string, deprecated_at: ?string, sunset_at: ?string, successor: ?string}>
     */
    public array $apiVersions = [
        'v1' => [
            'status'        => 'current',
            'deprecated_at' => null,
            'sunset_at'     => null,
            'successor'     => null,
        ],
    ];

    /**
     * Set to false in subclasses (or for tests) to skip env hydration and
     * keep the property defaults exactly as declared.
     */
    protected bool $hydrateFromEnv = true;

    public function __construct()
    {
        try {
            parent::__construct();
        } catch (\Throwable) {
            // CI4's BaseConfig requires Config\Modules which is provided
            // by the CI4 bootstrap. Allow tests and ad-hoc usage to
            // instantiate the config standalone — production paths
            // always have Modules available.
        }

        if (! $this->hydrateFromEnv) {
            return;
        }

        $this->hydrateFromEnvironment();
    }

    protected function hydrateFromEnvironment(): void
    {
        $this->jwtRevocationCheck       = (bool) filter_var($this->envValue('JWT_REVOCATION_CHECK', $this->jwtRevocationCheck), FILTER_VALIDATE_BOOLEAN);
        $this->requireEmailVerification = (bool) filter_var($this->envValue('AUTH_REQUIRE_EMAIL_VERIFICATION', $this->requireEmailVerification), FILTER_VALIDATE_BOOLEAN);
        $this->jwtSecretKey             = trim((string) $this->envValue('JWT_SECRET_KEY', $this->jwtSecretKey));
        $this->jwtAccessTokenTtl        = (int) $this->envValue('JWT_ACCESS_TOKEN_TTL', $this->jwtAccessTokenTtl);
        $this->jwtRefreshTokenTtl       = (int) $this->envValue('JWT_REFRESH_TOKEN_TTL', $this->jwtRefreshTokenTtl);
        $this->jwtRevocationCacheTtl    = (int) $this->envValue('JWT_REVOCATION_CACHE_TTL', $this->jwtRevocationCacheTtl);
        $this->jwtServiceTokenTtl       = (int) $this->envValue('JWT_SERVICE_TOKEN_TTL', $this->jwtServiceTokenTtl);
        $this->googleClientId           = trim((string) $this->envValue('GOOGLE_CLIENT_ID', $this->googleClientId));

        $this->rateLimitWindow        = (int) $this->envValue('RATE_LIMIT_WINDOW', $this->rateLimitWindow);
        $this->rateLimitRequests      = (int) $this->envValue('RATE_LIMIT_REQUESTS', $this->rateLimitRequests);
        $this->rateLimitUserRequests  = (int) $this->envValue('RATE_LIMIT_USER_REQUESTS', $this->rateLimitUserRequests);
        $this->authRateLimitRequests  = (int) $this->envValue('AUTH_RATE_LIMIT_REQUESTS', $this->authRateLimitRequests);
        $this->authRateLimitWindow    = (int) $this->envValue('AUTH_RATE_LIMIT_WINDOW', $this->authRateLimitWindow);

        $this->searchEnabled     = (bool) filter_var($this->envValue('SEARCH_ENABLED', $this->searchEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->searchUseFulltext = (bool) filter_var($this->envValue('SEARCH_USE_FULLTEXT', $this->searchUseFulltext), FILTER_VALIDATE_BOOLEAN);
        $this->searchMinLength   = (int) $this->envValue('SEARCH_MIN_LENGTH', $this->searchMinLength);

        $this->paginationDefaultLimit = (int) $this->envValue('PAGINATION_DEFAULT_LIMIT', $this->paginationDefaultLimit);
        $this->paginationMaxLimit     = (int) $this->envValue('PAGINATION_MAX_LIMIT', $this->paginationMaxLimit);

        $this->fileMaxSize       = (int) $this->envValue('FILE_MAX_SIZE', $this->fileMaxSize);
        $this->fileAllowedTypes  = (string) $this->envValue('FILE_ALLOWED_TYPES', $this->fileAllowedTypes);
        $this->fileStorageDriver = (string) $this->envValue('FILE_STORAGE_DRIVER', $this->fileStorageDriver);
        $this->fileUploadPath    = (string) $this->envValue('FILE_UPLOAD_PATH', $this->fileUploadPath);
        $this->filesUserScoped   = (bool) filter_var($this->envValue('FILES_USER_SCOPED', $this->filesUserScoped), FILTER_VALIDATE_BOOLEAN);

        $this->requestLoggingEnabled = (bool) filter_var($this->envValue('REQUEST_LOGGING_ENABLED', $this->requestLoggingEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->slowQueryThreshold    = (int) $this->envValue('SLOW_QUERY_THRESHOLD', $this->slowQueryThreshold);
        $this->sloP95TargetMs        = (int) $this->envValue('SLO_API_P95_TARGET_MS', $this->sloP95TargetMs);

        $this->outboundHttpTimeout      = (int) $this->envValue('OUTBOUND_HTTP_TIMEOUT', $this->outboundHttpTimeout);
        $this->outboundHttpRetries      = (int) $this->envValue('OUTBOUND_HTTP_RETRIES', $this->outboundHttpRetries);
        $this->outboundHttpRetryDelayMs = (int) $this->envValue('OUTBOUND_HTTP_RETRY_DELAY_MS', $this->outboundHttpRetryDelayMs);
    }

    /**
     * Prefer getenv() (mutable via putenv) over env() which checks
     * $_ENV/$_SERVER first. Falls back to env() when CI4 is bootstrapped.
     */
    protected function envValue(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (function_exists('env')) {
            return env($key, $default);
        }

        return $default;
    }
}
