<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Config;

use CodeIgniter\Config\BaseConfig;

class Audit extends BaseConfig
{
    public bool $asyncEnabled = true;

    public string $queueName = 'audit';

    /** @var array<int, string> */
    public array $criticalActions = [
        'authorization_denied_role',
        'api_key_auth_failed',
        'api_key_rate_limit_exceeded',
        'revoked_token_reuse_detected',
    ];

    public int $maxPayloadBytes = 60000;
}
