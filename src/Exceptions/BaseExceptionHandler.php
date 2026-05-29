<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Exceptions;

use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

abstract class BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        $response
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setBody((string) json_encode([
                'success' => false,
                'message' => $exception->getMessage() ?: 'An unexpected error occurred.',
            ], JSON_THROW_ON_ERROR))
            ->send();

        exit($exitCode);
    }
}
