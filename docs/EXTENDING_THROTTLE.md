# Extending throttling — plugging in a custom rate-limit strategy

`ci4-api-core` ships an abstract throttle filter (`AbstractThrottleFilter`) that owns the generic shape of fixed-window rate limiting: walk a list of buckets, fail-fast on the first one that's full, attach `X-RateLimit-*` headers, return `429` with `Retry-After` on overflow.

The strategy itself — how many buckets to apply, what cache key each uses, what limits and windows — is **left to the consumer**. This guide describes the extension contract so you can wire your own strategy (per-API-key quotas, tiered limits, geo-aware throttling, Redis token-bucket, etc.) without forking core.

---

## Why this guide?

The default subclass behaviour applies one IP bucket and (for authenticated requests) one user bucket, with limits read from `config('Api')`. That covers most projects. But common needs go beyond it:

- API-key-aware throttling (per-key quotas, with IP defensive bucket)
- Tiered limits (free / pro / enterprise tenants)
- Per-route quotas (search-heavy endpoints with stricter caps)
- Distributed limits via Redis token-buckets

All of these are achieved by subclassing `AbstractThrottleFilter` and overriding `resolveBuckets()`.

---

## The contract in 3 pieces

### 1. `AbstractThrottleFilter` — `src/Http/Filters/AbstractThrottleFilter.php`

Generic before/after lifecycle. Calls `resolveBuckets()` to get the list of buckets to enforce, walks each one through `checkBucket()`, returns `429` on the first overflow, and stores the *primary* bucket's quota on the `ApiRequest` so `after()` can attach headers.

**Required hook (subclass must override only if the default doesn't fit):**

```php
/**
 * @return list<array{key: string, limit: int, window: int}>
 */
protected function resolveBuckets(RequestInterface $request): array;
```

The first bucket in the returned list is the **primary** — its quota appears in `X-RateLimit-Limit` / `X-RateLimit-Remaining` / `X-RateLimit-Reset` headers. Returning `[]` lets the request through with no limiting at all.

**Default implementation:** IP bucket (always) + user bucket (when `ApiRequest::getAuthUserId()` returns a value). Limits come from `config('Api')->rateLimitWindow` / `rateLimitRequests` / `rateLimitUserRequests`, with safe constants (60 s window / 60 IP / 100 user) when the config or property is absent.

**Optional hooks:**

```php
protected function defaultLimits(): array;          // default: [window, ipLimit, userLimit] from config
protected function getCache(): CacheInterface;      // default: Services::cache()
```

### 2. Internal helpers (already implemented — call from `resolveBuckets()` or override)

```php
protected function checkBucket(CacheInterface $cache, string $key, int $limit, int $window): int|false;
protected function attachRateLimitHeaders(ResponseInterface $response, array $info): void;
protected function rateLimitExceeded(ResponseInterface $response, int $maxRequests, int $window): ResponseInterface;
```

`checkBucket()` returns the remaining quota or `false` if the bucket is full. The fixed-window counter is stored under `$key` with a TTL of `$window` seconds.

### 3. `config('Api')` knobs

Read by `defaultLimits()` (only consulted when you don't override it):

| Property | Default | Meaning |
|---|---|---|
| `rateLimitWindow` | 60 | Window length, in seconds |
| `rateLimitRequests` | 60 | Max requests per IP per window |
| `rateLimitUserRequests` | 100 | Max requests per authenticated user per window |

Each property is read defensively; missing values fall back to the constants above.

---

## How to plug in your own throttle (step by step)

### 1. Subclass `AbstractThrottleFilter`

```php
final class TenantAwareThrottleFilter extends AbstractThrottleFilter
{
    protected function resolveBuckets(RequestInterface $request): array
    {
        $window  = 60;
        $tenant  = $this->resolveTenantId($request);
        $userId  = $request instanceof ApiRequest ? $request->getAuthUserId() : null;

        $buckets = [];

        if ($tenant !== null) {
            $buckets[] = [
                'key'    => "rate_limit_tenant_{$tenant->id}",
                'limit'  => $tenant->plan->requestsPerMinute,
                'window' => $window,
            ];
        }

        if ($userId !== null) {
            $buckets[] = [
                'key'    => "rate_limit_user_{$userId}",
                'limit'  => 100,
                'window' => $window,
            ];
        }

        $buckets[] = [
            'key'    => 'rate_limit_ip_' . md5($request->getIPAddress()),
            'limit'  => 200,
            'window' => $window,
        ];

        return $buckets;
    }

    private function resolveTenantId(RequestInterface $request): ?Tenant { /* … */ }
}
```

The first bucket (`tenant`) becomes the primary — its quota drives the response headers. Order matters: put the most informative bucket first.

### 2. Register the filter alias

In `app/Config/Filters.php`:

```php
public array $aliases = [
    // ...
    'throttle' => \YourNamespace\Filters\TenantAwareThrottleFilter::class,
];
```

Use it in `Routes.php` exactly like the default — usually in the global filter chain, before `auth`:

```php
$routes->group('', ['filter' => 'throttle'], static function ($routes) {
    // ...
});
```

### 3. (Optional) Swap the cache backend

`AbstractThrottleFilter::getCache()` returns `Services::cache()` by default — that resolves through CI4's standard cache config (`app/Config/Cache.php`). If you want a dedicated cache (e.g. a separate Redis instance for rate limits, isolated from the app cache), override `getCache()`:

```php
protected function getCache(): CacheInterface
{
    return Services::rateLimitCache();
}
```

…and register `rateLimitCache()` in your service factories.

### 4. (Optional) Customise the 429 body

`rateLimitExceeded()` already returns a structured 429 with `Retry-After`, `X-RateLimit-*` headers, and a JSON body containing `error` + `retry_after`. To change the message or add fields, override it in the subclass — but keep the headers, since clients depend on them.

---

## Common patterns

**Per-API-key throttling.** Resolve the API key from a header (e.g. `X-App-Key`), look up the tenant/plan, prepend a tenant-level bucket. Add an IP-level *defensive* bucket (lower than the per-key limit) to protect against misconfigured clients sharing one key across thousands of nodes.

**Tiered plans.** Map the actor (user or tenant) to a plan tier and pick the limit from a constant table. Keep the bucket key shape stable — it's tempting to encode the tier in the key, but that lets a tier downgrade reset the counter mid-window.

**Per-route quotas.** Read the matched route from CI4's router and apply an additional bucket keyed on `route + actor`. Useful for search/list endpoints that are cheaper to abuse than they look.

**Burst + sustained limits.** Add two buckets for the same actor with different `(limit, window)` pairs — e.g. 30 req / 1 s + 600 req / 1 min. The second one fires only on sustained traffic.

---

## Integration checklist

- [ ] Subclass `AbstractThrottleFilter` and override `resolveBuckets()`
- [ ] First bucket in the returned list is the one whose quota you want in response headers
- [ ] All bucket `key` values are deterministic for the same actor + window (a key that drifts mid-window resets the counter)
- [ ] Filter registered as the `throttle` alias in `app/Config/Filters.php`
- [ ] `throttle` runs **before** `auth` in route filter chains (so unauthenticated bursts are caught at the IP layer)
- [ ] If using a dedicated rate-limit cache, the factory is registered in `app/Config/Services.php`
- [ ] `config('Api')` exposes `rateLimitWindow` / `rateLimitRequests` / `rateLimitUserRequests` if your subclass falls back to `defaultLimits()`
