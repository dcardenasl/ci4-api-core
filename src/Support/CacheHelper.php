<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use CodeIgniter\Cache\CacheInterface;

/**
 * Cache-aside helpers built on CI4 Cache.
 *
 * Works with any configured CI4 cache handler (File, Redis, Memcached, etc.).
 * The handler is resolved via service('cache') on each call — no static state.
 *
 * Usage:
 *
 *   $user = CacheHelper::remember("user:{$id}", 300, fn() => $this->model->find($id));
 *   CacheHelper::forget("user:{$id}");
 */
class CacheHelper
{
    /**
     * Return the cached value, or compute and cache it on miss.
     *
     * @param int $ttl Seconds until expiry. Use 0 for no expiry (same as rememberForever).
     */
    public static function remember(string $key, int $ttl, callable $compute): mixed
    {
        $cache = self::cache();
        $value = $cache->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $compute();
        $cache->save($key, $value, $ttl);

        return $value;
    }

    /**
     * Return the cached value, or compute and cache it with no expiry.
     *
     * Use with care — indefinitely cached entries accumulate over time. Prefer
     * remember() with a long TTL unless the data is truly immutable.
     */
    public static function rememberForever(string $key, callable $compute): mixed
    {
        $cache = self::cache();
        $value = $cache->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $compute();
        $cache->save($key, $value, 0);

        return $value;
    }

    /**
     * Delete a single cache entry.
     */
    public static function forget(string $key): bool
    {
        return self::cache()->delete($key);
    }

    /**
     * Delete multiple cache entries.
     *
     * @param list<string> $keys
     */
    public static function forgetMany(array $keys): void
    {
        $cache = self::cache();

        foreach ($keys as $key) {
            $cache->delete($key);
        }
    }

    private static function cache(): CacheInterface
    {
        /** @var CacheInterface $cache */
        $cache = service('cache');

        return $cache;
    }
}
