<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters\Concerns;

use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

trait RateLimitResponseHelpers
{
    /**
     * @param array{limit:int,remaining:int,reset:int} $info
     */
    protected function attachRateLimitHeaders(ResponseInterface $response, array $info): void
    {
        $response->setHeader('X-RateLimit-Limit', (string) $info['limit']);
        $response->setHeader('X-RateLimit-Remaining', (string) $info['remaining']);
        $response->setHeader('X-RateLimit-Reset', (string) $info['reset']);
    }

    /**
     * Build a standardized 429 response body + headers.
     *
     * @param array<int|string, mixed> $errorParams
     */
    protected function buildRateLimitExceededResponse(
        ResponseInterface $response,
        int $maxRequests,
        int $window,
        string $errorMessage,
        array $errorParams = []
    ): ResponseInterface {
        $retryAfter = $window;

        $body = array_merge(
            ApiResponse::error(
                ['rate_limit' => $this->langOrDefault($errorMessage, "Too many requests", $errorParams)],
                $this->langOrDefault('Auth.rateLimitExceeded', 'Rate limit exceeded'),
                429
            ),
            ['retry_after' => $retryAfter]
        );

        $response->setStatusCode(429);
        $response->setHeader('Retry-After', (string) $retryAfter);
        $this->attachRateLimitHeaders($response, [
            'limit' => $maxRequests,
            'remaining' => 0,
            'reset' => time() + $retryAfter,
        ]);
        $response->setJSON($body);

        return $response;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function langOrDefault(string $key, string $default, array $params = []): string
    {
        if (! function_exists('lang')) {
            return $default;
        }

        $translated = (string) lang($key, $params);

        return $translated === '' || $translated === $key ? $default : $translated;
    }
}
