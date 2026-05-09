<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use dcardenasl\Ci4ApiCore\Config\Api;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $this->envBackup = [
            'JWT_ACCESS_TOKEN_TTL'   => getenv('JWT_ACCESS_TOKEN_TTL'),
            'JWT_SECRET_KEY'         => getenv('JWT_SECRET_KEY'),
            'SEARCH_ENABLED'         => getenv('SEARCH_ENABLED'),
            'PAGINATION_DEFAULT_LIMIT' => getenv('PAGINATION_DEFAULT_LIMIT'),
            'CORS_ALLOWED_ORIGINS'   => getenv('CORS_ALLOWED_ORIGINS'),
        ];

        foreach (array_keys($this->envBackup) as $key) {
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }
    }

    public function testDefaultsAreReturnedWhenEnvIsAbsent(): void
    {
        $config = new Api();

        $this->assertSame(3600, $config->jwtAccessTokenTtl);
        $this->assertSame(604800, $config->jwtRefreshTokenTtl);
        $this->assertSame(60, $config->rateLimitWindow);
        $this->assertTrue($config->searchEnabled);
        $this->assertSame(20, $config->paginationDefaultLimit);
        $this->assertSame(100, $config->paginationMaxLimit);
        $this->assertSame('', $config->jwtSecretKey);
        $this->assertSame([], $config->accessPolicyBypassRoutes);
        $this->assertArrayHasKey('v1', $config->apiVersions);
    }

    public function testEnvVariablesOverrideDefaults(): void
    {
        putenv('JWT_ACCESS_TOKEN_TTL=7200');
        putenv('JWT_SECRET_KEY=test-secret-value');
        putenv('SEARCH_ENABLED=false');
        putenv('PAGINATION_DEFAULT_LIMIT=50');

        $config = new Api();

        $this->assertSame(7200, $config->jwtAccessTokenTtl);
        $this->assertSame('test-secret-value', $config->jwtSecretKey);
        $this->assertFalse($config->searchEnabled);
        $this->assertSame(50, $config->paginationDefaultLimit);
    }

    public function testSubclassCanOverrideDefaultsWithoutHydration(): void
    {
        $config = new class () extends Api {
            protected bool $hydrateFromEnv = false;

            public int $jwtAccessTokenTtl = 9000;

            public string $jwtSecretKey = 'override';
        };

        $this->assertSame(9000, $config->jwtAccessTokenTtl);
        $this->assertSame('override', $config->jwtSecretKey);
    }

    public function testSubclassCanInheritHydrationAndAddProperties(): void
    {
        putenv('JWT_ACCESS_TOKEN_TTL=1234');

        $config = new class () extends Api {
            public string $extraField = 'extra-value';
        };

        $this->assertSame(1234, $config->jwtAccessTokenTtl);
        $this->assertSame('extra-value', $config->extraField);
    }
}
