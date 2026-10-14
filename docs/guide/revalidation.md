# Revalidation

Two complementary mechanisms, like Next.js: **time-based** (the CDN expires and refreshes pages) and
**on-demand** (you purge when data changes).

## Time-based

`revalidate` is the edge TTL. After it, the CDN serves the **stale** page instantly and refreshes it in the
background (`stale-while-revalidate`). Visitors never wait for the origin.

```
t=0     MISS   rendered at the origin, stored          (renderedAt 10:00:00)
t=5     HIT    from the edge                           (renderedAt 10:00:00)
t=11    STALE  served instantly, refresh in background (renderedAt 10:00:00)
t=12    HIT    refreshed version                       (renderedAt 10:00:11)
```

This is verified by the [E2E suite](../testing/e2e.md) on both the HTML and the page JSON. The exact header that
carries the TTL depends on the driver (`Cloudflare-CDN-Cache-Control`, `Surrogate-Control`, `s-maxage`,
`X-Accel-Expires`); see [Headers](../reference/headers.md).

## On demand

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::purgeTags(['post:1']);
StaticCache::purgeUrls(['/blog/my-post']);   // paths or absolute URLs
StaticCache::purgePrefix('/blog');
StaticCache::purgeAll();
```

Each page is always purged on **both** of its URLs: the HTML and `/__inertia/{version}/{path}.json`.

### Automatic purges from models

```php
use Fydellys\InertiaStatic\Concerns\HasStaticTags;

class Post extends Model
{
    use HasStaticTags;

    public function staticTags(): array   // optional
    {
        return ['posts', "post:{$this->id}", "author:{$this->author_id}"];
    }
}
```

`saved`, `deleted` and `restored` queue a purge of `staticTags()`. The purge is dispatched **after the database
transaction commits**.

### The purge queue

Every purge is a queued `PurgeStaticCache` job:

| Concern | How |
|---|---|
| Debounce | the job is delayed by `purge.debounce` seconds and is `ShouldBeUnique` for that window, so ten saves of the same post produce one purge |
| Batching | items are split by the provider limit (Cloudflare 100, Fastly 256 keys, …) |
| Rate limits | `RateLimited` job middleware, `purge.rate_limit_per_minute` (Cloudflare Free: 5 tag/prefix purges per minute) |
| Retries | exponential `purge.backoff`, `purge.tries` exceptions, `retryUntil` one hour; HTTP 429/5xx are retried by the client too |
| Failure | `failed()` logs the purge that could not be done |
| Warmup | `purge.warm_after_purge` re-requests the purged URLs afterwards |

Purge synchronously (deploy scripts, tests) with `StaticCache::purgeNow(PurgeOperation::Tags, ['posts'])` or the
[`inertia-static:purge`](./commands.md) command.

::: tip Headers-only mode
Without CDN credentials nothing is queued: pages expire by TTL only. See [Headers-only mode](./headers-only.md).
:::

## Tag registry

Some CDNs cannot purge by tag (nginx, Bunny in safe mode, CloudFront before 2026), and some cannot purge by
prefix. The **tag registry** remembers which pages were cached with which tags (recorded when the origin renders
them) and converts:

- tag purges into URL purges (HTML + JSON of every page with the tag);
- prefix purges into URL purges of the known pages under the prefix.

```ini
INERTIA_STATIC_REGISTRY=database   # table inertia_static_tags (publish the migration)
INERTIA_STATIC_REGISTRY=cache      # any cache store, e.g. Redis (registry.store)
```

When a prefix cannot be resolved at all (no prefix purge, nothing in the registry), the purge **escalates to a
full purge**: stale content is worse than a cold cache.
