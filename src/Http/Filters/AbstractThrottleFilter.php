<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\Filters\Concerns\RateLimitResponseHelpers;

/**
 * Abstract Throttle Filter — generic per-bucket rate limiting.
 *
 * Concrete subclasses describe a request's effective rate limit by
 * implementing `resolveBuckets()`, which returns the list of buckets
 * (IP-based, user-based, app-key-based, etc.) to enforce on the
 * request. The base class:
 *
 *   1. Walks each bucket through a fixed-window counter in cache.
 *   2. Returns 429 with a `Retry-After` header when any bucket is full.
 *   3. Stores the *primary* bucket's quota on the request so `after()`
 *      can attach `X-RateLimit-*` headers.
 *
 * The default subclass behaviour applies an IP bucket (always) plus a
 * user bucket when an `ApiRequest` carries an authenticated user id;
 * limits come from `config('Api')` if available, with safe constants
 * otherwise.
 */
abstract class AbstractThrottleFilter implements FilterInterface
{
    use RateLimitResponseHelpers;

    /**
     * @param  array<int, string>|null $arguments
     * @return RequestInterface|ResponseInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $cache    = $this->getCache();
        $response = Services::response();
        $buckets  = $this->resolveBuckets($request);

        if ($buckets === []) {
            return $request;
        }

        $primary = $buckets[0];

        foreach ($buckets as $bucket) {
            $remaining = $this->checkBucket($cache, $bucket['key'], $bucket['limit'], $bucket['window']);

            if ($remaining === false) {
                return $this->rateLimitExceeded($response, $bucket['limit'], $bucket['window']);
            }

            if ($bucket === $primary && $request instanceof ApiRequest) {
                $request->setRateLimitInfo([
                    'limit'     => $bucket['limit'],
                    'remaining' => max(0, $remaining),
                    'reset'     => time() + $bucket['window'],
                ]);
            }
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        if ($request instanceof ApiRequest) {
            $info = $request->getRateLimitInfo();
            if ($info !== null) {
                $this->attachRateLimitHeaders($response, $info);
            }
        }

        return $response;
    }

    // ------------------------------------------------------------------
    // Hook subclasses must / may override
    // ------------------------------------------------------------------

    /**
     * Buckets to enforce for this request. The first bucket is treated
     * as primary and its quota is exposed via `X-RateLimit-*` headers.
     *
     * Default: IP bucket (+ user bucket when authenticated).
     *
     * @return list<array{key: string, limit: int, window: int}>
     */
    protected function resolveBuckets(RequestInterface $request): array
    {
        [$window, $ipLimit, $userLimit] = $this->defaultLimits();

        $buckets = [
            [
                'key'    => 'rate_limit_ip_' . md5($request->getIPAddress()),
                'limit'  => $ipLimit,
                'window' => $window,
            ],
        ];

        $userId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;
        if ($userId !== null) {
            $buckets[] = [
                'key'    => 'rate_limit_user_' . $userId,
                'limit'  => $userLimit,
                'window' => $window,
            ];
        }

        return $buckets;
    }

    /**
     * @return array{0:int, 1:int, 2:int} {window, ipLimit, userLimit}
     */
    protected function defaultLimits(): array
    {
        $window    = 60;
        $ipLimit   = 60;
        $userLimit = 100;

        if (function_exists('config')) {
            $apiConfig = config('Api');
            if ($apiConfig !== null) {
                $window    = (int) ($apiConfig->rateLimitWindow ?? $window);
                $ipLimit   = (int) ($apiConfig->rateLimitRequests ?? $ipLimit);
                $userLimit = (int) ($apiConfig->rateLimitUserRequests ?? $userLimit);
            }
        }

        return [$window, $ipLimit, $userLimit];
    }

    protected function getCache(): CacheInterface
    {
        return Services::cache();
    }

    // ------------------------------------------------------------------
    // Helpers (shared by subclasses)
    // ------------------------------------------------------------------

    /**
     * Increment the counter for $key and return the remaining quota.
     * Returns `false` when the limit is exceeded.
     */
    protected function checkBucket(CacheInterface $cache, string $key, int $limit, int $window): int|false
    {
        /** @var int|null $current */
        $current = $cache->get($key);

        if ($current === null) {
            $cache->save($key, 1, $window);

            return $limit - 1;
        }

        if ($current >= $limit) {
            return false;
        }

        $cache->save($key, $current + 1, $window);

        return $limit - $current - 1;
    }

    protected function rateLimitExceeded(ResponseInterface $response, int $maxRequests, int $window): ResponseInterface
    {
        return $this->buildRateLimitExceededResponse(
            $response,
            $maxRequests,
            $window,
            'Auth.tooManyRequests',
            [$maxRequests, $window]
        );
    }
}
