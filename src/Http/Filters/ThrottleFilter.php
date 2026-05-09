<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Support\ApiConfigFacade;

/**
 * Basic rate-limiting filter backed by CI4 Cache.
 *
 * Strategy: fixed window per minute, keyed by user_id (authenticated) or
 * client IP (anonymous). The CI4 Cache abstraction means any configured
 * handler works — File for development, Redis or Memcached for production.
 *
 * Activation: set `throttleEnabled = true` in `Config\Api` and register
 * this filter under the alias 'throttle' in `Config\Filters`.
 *
 * Limits (configurable via Config\Api):
 *   - Anonymous:     $throttleRequestsPerMinute     (default 60)
 *   - Authenticated: $throttleAuthRequestsPerMinute (default 600)
 *
 * Returns 429 with X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After
 * headers. Successful responses also receive X-RateLimit-* headers via after().
 *
 * Note: this filter provides a sensible default. Projects requiring per-API-key
 * quotas, subscription tiers, or more granular strategies should replace this
 * with a domain-specific filter and keep this file as a fallback.
 */
class ThrottleFilter implements FilterInterface
{
    private const WINDOW_SECONDS = 60;

    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (!ApiConfigFacade::bool('throttleEnabled', false)) {
            return null;
        }

        $userId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;
        $maxRequests = $this->maxRequests($userId);
        $cacheKey = $this->cacheKey($request, $userId);

        /** @var CacheInterface $cache */
        $cache = service('cache');
        $hits = (int) ($cache->get($cacheKey) ?? 0);

        if ($hits >= $maxRequests) {
            /** @var ResponseInterface $response */
            $response = service('response');

            return $response
                ->setStatusCode(429)
                ->setHeader('X-RateLimit-Limit', (string) $maxRequests)
                ->setHeader('X-RateLimit-Remaining', '0')
                ->setHeader('Retry-After', (string) self::WINDOW_SECONDS)
                ->setJSON([
                    'status' => 'error',
                    'code'   => 429,
                    'message' => 'Too Many Requests',
                    'errors' => [],
                ]);
        }

        if ($hits === 0) {
            $cache->save($cacheKey, 1, self::WINDOW_SECONDS);
        } else {
            $cache->increment($cacheKey);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        if (!ApiConfigFacade::bool('throttleEnabled', false)) {
            return null;
        }

        $userId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;
        $maxRequests = $this->maxRequests($userId);
        $cacheKey = $this->cacheKey($request, $userId);

        /** @var CacheInterface $cache */
        $cache = service('cache');
        $hits = (int) ($cache->get($cacheKey) ?? 0);
        $remaining = max(0, $maxRequests - $hits);

        return $response
            ->setHeader('X-RateLimit-Limit', (string) $maxRequests)
            ->setHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function maxRequests(?int $userId): int
    {
        return $userId !== null
            ? ApiConfigFacade::int('throttleAuthRequestsPerMinute', 600)
            : ApiConfigFacade::int('throttleRequestsPerMinute', 60);
    }

    private function cacheKey(RequestInterface $request, ?int $userId): string
    {
        return $userId !== null
            ? "throttle:u:{$userId}"
            : 'throttle:ip:' . md5($request->getIPAddress());
    }
}
