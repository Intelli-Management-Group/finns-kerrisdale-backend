<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache for public API responses. Any admin write bumps the version,
 * so stale entries are simply never read again (works on every cache store, no tags needed).
 */
class ContentCache
{
    public static function remember(string $key, Closure $callback, int $seconds = 600): mixed
    {
        if (! config('app.public_cache', true)) {
            return $callback();
        }

        return Cache::remember('public:v'.self::version().':'.$key, $seconds, $callback);
    }

    public static function version(): int
    {
        return (int) Cache::get('public:version', 1);
    }

    public static function bust(): void
    {
        Cache::forever('public:version', self::version() + 1);
    }
}
