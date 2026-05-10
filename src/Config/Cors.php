<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    /**
     * @var array{
     *      allowedOrigins: list<string>,
     *      allowedOriginsPatterns: list<string>,
     *      supportsCredentials: bool,
     *      allowedHeaders: list<string>,
     *      exposedHeaders: list<string>,
     *      allowedMethods: list<string>,
     *      maxAge: int,
     *  }
     */
    public array $default = [
        'allowedOrigins' => [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:8082',
            'http://localhost:5173',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
        ],
        'allowedOriginsPatterns' => [],
        'supportsCredentials'    => false,
        'allowedHeaders'         => ['Content-Type', 'Authorization', 'X-App-Key', 'X-Requested-With', 'X-Request-Id', 'Accept', 'Origin'],
        'exposedHeaders'         => [],
        'allowedMethods'         => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'maxAge'                 => 86400,
    ];
}
