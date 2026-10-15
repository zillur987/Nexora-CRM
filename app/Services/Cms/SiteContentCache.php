<?php

declare(strict_types=1);

namespace App\Services\Cms;

use Illuminate\Support\Facades\Cache;

/** Public header/banner payloads are cached; any CMS write flushes them (see FlushesSiteContent). */
final class SiteContentCache
{
    public const KEYS = ['header', 'banner'];
    private const TTL = 3600;

    public static function remember(string $key, \Closure $build): array
    {
        return Cache::remember("site-content:{$key}", self::TTL, $build);
    }

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget("site-content:{$key}");
        }
    }
}
