<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

abstract class BaseExceptionHandler
{
    public function handle(Throwable $exception): ResponseInterface
    {
        // Default mapping
        $code = 500;
        $message = 'An unexpected error occurred.';

        // Custom mapping logic here

        return service('response')->setJSON([
            'success' => false,
            'message' => $message,
        ])->setStatusCode($code);
    }
}
