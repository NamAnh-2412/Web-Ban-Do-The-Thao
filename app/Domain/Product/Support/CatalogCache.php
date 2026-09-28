<?php

namespace App\Domain\Product\Support;

use Illuminate\Support\Facades\Cache;

final class CatalogCache
{
    public static function remember(string $key, \Closure $callback): mixed
    {
        $version = (int) Cache::get('catalog:version', 1);
        $ttl = 300 + random_int(0, 60);

        return Cache::remember('catalog:v'.$version.':'.$key, $ttl, $callback);
    }

    public static function bump(): void
    {
        if (! Cache::has('catalog:version')) {
            Cache::forever('catalog:version', 1);
        }

        Cache::increment('catalog:version');
    }
}
