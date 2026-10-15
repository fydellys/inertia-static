# Revalidation

Two complementary mechanisms: **time-based** (the CDN expires and refreshes pages) and
**on-demand** (you purge when data changes).

## Time-based

`revalidate` is the edge TTL. After it, the CDN serves the **stale** page instantly and refreshes it in the
background (`stale-while-revalidate`). Visitors never wait for the origin.

```
revalidate: 10
t=0     MISS   rendered at the origin, stored
t=5     HIT    from the edge
t=11    STALE  served instantly, refreshed in the background
t=12    HIT    the refreshed version
```

### `revalidate` and `expire`

Two numbers control the lifetime:

| Option | Meaning | Default |
|---|---|---|
| `revalidate` | the edge TTL: after it, the page is refreshed in the background | `3600` |
| `expire` | after it, a stale page may no longer be served: the next visitor waits for a fresh render | `31536000` (1 year) |

`stale-while-revalidate` is `expire - revalidate`:

```php
Route::get('/pricing', PricingController::class)->isr(revalidate: 300, expire: 3600);
// CDN-Cache-Control: public, max-age=300, stale-while-revalidate=3300, stale-if-error=604800
```

Set them per route (`->isr(...)`), per response (`->revalidate()`, `->expire()`) or globally
(`defaults.revalidate`, `defaults.expire`). An explicit `staleWhileRevalidate` (route, response or
`defaults.stale_while_revalidate`) wins over `expire`. The header that carries the TTL depends on the CDN driver;
see [Headers](../reference/headers.md).

## On demand

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::purgeTags(['post:1']);
StaticCache::purgeUrls(['/blog/my-post']);   // paths or absolute URLs
StaticCache::purgePrefix('/blog');
StaticCache::purgeAll();
```

Purges are queued (see [The purge queue](#the-purge-queue)). Each page is always purged on **both** of its URLs:
the HTML and its page JSON.

### Soft or hard purge

| Mode | Effect |
|---|---|
| `PurgeMode::Soft` (default, `purge.mode` / `INERTIA_STATIC_PURGE_MODE`) | content is marked **stale**: the CDN serves it once more while it refetches |
| `PurgeMode::Hard` | content is removed: the next request waits for the origin |

```php
use Fydellys\InertiaStatic\Enums\PurgeMode;

StaticCache::purgeTags('posts');                              // soft
StaticCache::purgeTags("post:{$post->id}", PurgeMode::Hard);  // read-your-own-writes
```

```bash
php artisan inertia-static:purge --tags=posts --hard
```

Soft purges work on Fastly and Varnish. Every other CDN always purges hard.

### Implicit path tags

Every cached page also carries one tag per path prefix (`implicit_tags`, on by default):

```
/blog/hello  →  Cache-Tag: posts, post:1, _IST_/, _IST_/blog/, _IST_/blog/hello/
```

On CDNs without prefix purges (Fastly), `purgePrefix('/blog')` becomes a purge of the tag `_IST_/blog/`.

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

`saved`, `deleted` and `restored` queue a purge of `staticTags()`, dispatched **after the database transaction
commits**. Without `staticTags()`, the tags are the table name and `{singular}:{id}` (e.g. `posts`, `post:1`).

### The purge queue

Purges run on your queue, so a queue worker must be running. Options in `config/inertia-static.php`:

| Option | Default | |
|---|---|---|
| `purge.mode` | `'soft'` | `'soft'` or `'hard'` (`INERTIA_STATIC_PURGE_MODE`) |
| `purge.debounce` | `5` | seconds to wait, so ten saves of the same post produce one purge (`INERTIA_STATIC_PURGE_DEBOUNCE`) |
| `purge.rate_limit_per_minute` | `60` | purge jobs per minute allowed by your CDN plan (Cloudflare Free: `5`; `INERTIA_STATIC_PURGE_RATE_LIMIT`) |
| `purge.tries` / `purge.backoff` | `5` / `[5, 30, 120]` | retries of a failed purge |
| `purge.warm_after_purge` | `false` | request the purged pages again, so the next visitor gets a `HIT` |
| `purge.queue` / `purge.connection` | default | where the jobs go (`INERTIA_STATIC_PURGE_QUEUE` / `_CONNECTION`) |

Purge synchronously (deploy scripts, tests) with the [`inertia-static:purge`](./commands.md#inertia-static-purge)
command or `purgeNow()`:

```php
use Fydellys\InertiaStatic\Enums\PurgeOperation;

StaticCache::purgeNow(PurgeOperation::Tags, ['posts']);   // returns the purged URLs
```

::: tip Headers-only mode
Without CDN credentials nothing is queued: pages expire by TTL only. See [Headers-only mode](./headers-only.md).
:::

## Tag registry

Some CDNs cannot purge by tag (nginx, CloudFront, Bunny unless `native_tags` is on), and some cannot purge by
prefix. The **tag registry** remembers which pages were rendered with which tags and converts:

- tag purges into URL purges (HTML + JSON of every page with the tag);
- prefix purges into URL purges of the known pages under the prefix.

```ini
INERTIA_STATIC_REGISTRY=database   # table inertia_static_tags
INERTIA_STATIC_REGISTRY=cache      # any cache store, e.g. Redis (registry.store)
```

For `database`, publish and run the migration:

```bash
php artisan vendor:publish --tag=inertia-static-migrations
php artisan migrate
```

When a prefix cannot be resolved at all (no prefix purge, nothing in the registry), the purge **escalates to a
full purge**.
