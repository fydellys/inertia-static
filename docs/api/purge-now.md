# `StaticCache::purgeNow()`

Purge synchronously, for deploy scripts and tests. Returns the purged absolute URLs.

```php
use Fydellys\InertiaStatic\Enums\PurgeOperation;

$urls = StaticCache::purgeNow(PurgeOperation::Tags, ['posts']);
StaticCache::purgeNow(PurgeOperation::All, driver: 'varnish');
```

## Reference

```php
StaticCache::purgeNow(
    PurgeOperation $operation,   // Urls | Tags | Prefixes | All
    array $items = [],           // page paths or URLs, tags or prefixes; empty for All
    ?string $driver = null,      // a driver from the config, default: the configured one
    ?PurgeMode $mode = null,     // as in purgeTags()
): array
```

**Returns** the absolute URLs that were purged when they are known (URL purges, and tag or prefix purges through
the [tag registry](../guide/revalidation.md#tag-registry)). `All` returns `[]`.

## Good to know

- Not queued, debounced or rate limited: errors are thrown to the caller.
- It purges even when `inertia-static.enabled` is `false`.
