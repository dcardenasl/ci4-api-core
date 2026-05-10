<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Config;

use CodeIgniter\Config\BaseConfig;

class Queue extends BaseConfig
{
    public string $driver = 'database';

    public string $defaultQueue = 'default';

    public int $maxAttempts = 3;

    public int $retryAfter = 90;

    public string $databaseConnection = 'default';

    /** @var array<string, mixed> */
    public array $redis = [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'password' => null,
        'database' => 0,
    ];
}
