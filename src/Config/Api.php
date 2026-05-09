<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Config;

use CodeIgniter\Config\BaseConfig;

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

    /** @var list<string> */
    public array $accessPolicyBypassRoutes = [
        'api/v1/auth/resend-verification',
    ];

    /** @var array<string, array{status: string, deprecated_at: ?string, sunset_at: ?string, successor: ?string}> */
    public array $apiVersions = [
        'v1' => [
            'status'        => 'current',
            'deprecated_at' => null,
            'sunset_at'     => null,
            'successor'     => null,
        ],
    ];
}
