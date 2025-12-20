<?php

namespace App\Services;

use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggableStore;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SliderCacheService
{
    private const CACHE_TAG = 'slider:eligible';
    private const CACHE_KEY_PREFIX = 'slider:eligible';

    /**
     * @param array<int, string> $interfaceTypes
     * @return mixed
     */
    public static function rememberEligible(
        array $interfaceTypes,
        ?string $userIdentifier,
        ?string $sessionIdentifier,
        int $ttlSeconds,
        Closure $callback
    ) {

        $cacheKey = self::buildCacheKey($interfaceTypes, $userIdentifier, $sessionIdentifier);
        $expiry = now()->addSeconds(max($ttlSeconds, 1));

        $store = self::resolveStore();

        if (self::supportsTags($store)) {
            try {
                return $store->tags([self::CACHE_TAG])->remember($cacheKey, $expiry, $callback);
            } catch (Throwable) {
                // fall through to non-tagged cache
            }
        }

        return $store->remember($cacheKey, $expiry, $callback);
    }

    public static function flushEligible(): void
    {
        $store = self::resolveStore();

        if (self::supportsTags($store)) {
            try {
                $store->tags([self::CACHE_TAG])->flush();
                return;
            } catch (Throwable) {
                // fall through
            }
        }

        // If tags not supported, do nothing (best-effort) to avoid clearing whole cache store.
    }

    /**
     * @param array<int, string> $interfaceTypes
     */
    public static function buildCacheKey(
        array $interfaceTypes,
        ?string $userIdentifier,
        ?string $sessionIdentifier
    ): string {

        
        $normalizedInterfaces = array_map(
            static fn (string $interfaceType) => self::normalizePart($interfaceType, 'all'),
            $interfaceTypes
        );

        if ($normalizedInterfaces === []) {
            $normalizedInterfaces = ['all'];
        }

        $interfacesKey = implode('.', $normalizedInterfaces);
        $userKey = self::normalizePart($userIdentifier, 'guest');
        $sessionKey = self::normalizePart($sessionIdentifier, 'none');

        return sprintf(
            '%s:interfaces:%s:user:%s:session:%s',
            self::CACHE_KEY_PREFIX,
            $interfacesKey,
            $userKey,
            $sessionKey
        );
    }

    private static function normalizePart(?string $value, string $fallback): string
    {
        $value = $value ?? $fallback;
        $value = trim($value);

        if ($value === '') {
            $value = $fallback;
        }

        $normalized = preg_replace('/[^A-Za-z0-9:_-]/', '_', $value);

        if ($normalized === '' || $normalized === null) {
            return $fallback;
        }

        return $normalized;
    }

    private static function resolveStore(): Repository
    {
        $preferred = 'redis';
        $fallback = config('cache.default', 'file');

        foreach (Arr::where([$preferred, $fallback, 'array'], static fn ($v) => !empty($v)) as $storeName) {
            try {
                return Cache::store($storeName);
            } catch (Throwable) {
                continue;
            }
        }

        return Cache::store(); // final fallback to default
    }

    private static function supportsTags(Repository $store): bool
    {
        try {
            return $store->getStore() instanceof TaggableStore;
        } catch (Throwable) {
            return false;
        }
    }
}
