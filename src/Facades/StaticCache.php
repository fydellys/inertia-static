<?php

namespace Fydellys\InertiaStatic\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void purgeTags(array<int, string>|string $tags, \Fydellys\InertiaStatic\Enums\PurgeMode|null $mode = null)
 * @method static void purgeUrls(array<int, string>|string $urls, \Fydellys\InertiaStatic\Enums\PurgeMode|null $mode = null)
 * @method static void purgePrefix(array<int, string>|string $prefixes, \Fydellys\InertiaStatic\Enums\PurgeMode|null $mode = null)
 * @method static void purgeAll(\Fydellys\InertiaStatic\Enums\PurgeMode|null $mode = null)
 * @method static array<int, string> purgeNow(\Fydellys\InertiaStatic\Enums\PurgeOperation $operation, array<int, string> $items = [], string|null $driver = null, \Fydellys\InertiaStatic\Enums\PurgeMode|null $mode = null)
 * @method static bool canPurge()
 * @method static \Symfony\Component\HttpFoundation\Cookie enablePreview(int|null $minutes = null)
 * @method static \Symfony\Component\HttpFoundation\Cookie disablePreview()
 * @method static bool isPreview()
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
