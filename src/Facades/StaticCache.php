<?php

namespace Fydellys\InertiaStatic\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void purgeTags(array<int, string>|string $tags)
 * @method static void purgeUrls(array<int, string>|string $urls)
 * @method static void purgePrefix(array<int, string>|string $prefixes)
 * @method static void purgeAll()
 * @method static array<int, string> purgeNow(\Fydellys\InertiaStatic\Enums\PurgeOperation $operation, array<int, string> $items = [], string|null $driver = null)
 * @method static bool canPurge()
 * @method static \Fydellys\InertiaStatic\Contracts\CdnDriver driver(string|null $name = null)
 * @method static \Fydellys\InertiaStatic\StaticCache extend(string $driver, \Closure $callback)
 *
 * @see \Fydellys\InertiaStatic\StaticCache
 */
class StaticCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Fydellys\InertiaStatic\StaticCache::class;
    }
}
