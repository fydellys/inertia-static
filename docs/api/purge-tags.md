# `StaticCache::purgeTags()`

Purge every page carrying one of the tags.

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::purgeTags('posts');
StaticCache::purgeTags(['post:42', 'author:7']);
```

## Reference

```php
StaticCache::purgeTags(array|string $tags, ?PurgeMode $mode = null): void
```

| `$mode` | Effect |
|---|---|
| `null` | `purge.mode` from the config (`soft` by default) |
| `PurgeMode::Soft` | pages are marked stale and served while they refresh |
| `PurgeMode::Hard` | pages are removed now; the next request renders them at the origin |

`PurgeMode` is `Fydellys\InertiaStatic\Enums\PurgeMode`. CDNs without soft purges always purge hard.

From the command line:

```bash
php artisan inertia-static:purge --tags=posts,post:42           # now
php artisan inertia-static:purge --tags=posts --queue --hard    # queued, hard
```

## Good to know

- The purge is queued: it runs after the current database transaction commits, `purge.debounce` seconds later
  (5 by default), and identical purges in that window run once. Use [`purgeNow()`](./purge-now) to purge
  synchronously.
- Usually you do not call it yourself: [`HasStaticTags`](./has-static-tags) does it when models change.
- CDNs without tag purges (Bunny, CloudFront, nginx) need the [tag registry](../guide/revalidation.md#tag-registry).
- It does nothing in [headers-only mode](../guide/headers-only.md) (no CDN credentials); a notice is logged.
- On Cloudflare Free, purges are limited to 5 requests per minute: set `INERTIA_STATIC_PURGE_RATE_LIMIT=5`.
