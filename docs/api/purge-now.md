# `StaticCache::purgeNow()`

Synchronous purge, returning the purged absolute URLs. Used by commands, deploy scripts and tests.

```php
use Fydellys\InertiaStatic\Enums\PurgeOperation;

$urls = StaticCache::purgeNow(PurgeOperation::Tags, ['posts']);
StaticCache::purgeNow(PurgeOperation::All, driver: 'varnish');
```

## Reference

```php
StaticCache::purgeNow(PurgeOperation $operation, array $items = [], ?string $driver = null): array
```

| `PurgeOperation` | Items |
|---|---|
| `Urls` | page paths or absolute URLs |
| `Tags` | tags |
| `Prefixes` | path prefixes |
| `All` | — |

**Returns** the absolute URLs that were purged, when the package can know them (URL purges, tags through the
registry). Pass them to `WarmStaticCache` to refill the CDN.

## Good to know

- No queue, no debounce, no rate limit: errors are thrown (`RequestException`, `MissingCredentials`).
- Dispatches `StaticCachePurged` afterwards.
