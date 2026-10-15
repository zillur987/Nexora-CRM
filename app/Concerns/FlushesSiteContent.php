<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Services\Cms\SiteContentCache;

trait FlushesSiteContent
{
    protected static function bootFlushesSiteContent(): void
    {
        static::saved(fn () => SiteContentCache::flush());
        static::deleted(fn () => SiteContentCache::flush());
    }
}
