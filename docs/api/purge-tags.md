# `StaticCache::purgeTags()`

Purge every page carrying one of the tags, like `revalidateTag()` in Next.js.

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::purgeTags('posts');
StaticCache::purgeTags(['post:42', 'author:7']);
```

## Reference

```php
StaticCache::purgeTags(array|string $tags): void
```

### Behaviour

1. A `PurgeStaticCache` job is queued after the current database transaction commits, delayed by
   `purge.debounce` seconds and unique for that window (identical purges collapse into one).
2. The job purges the tags through the driver, in batches (Cloudflare 100, Fastly 256).
3. CDNs without tag purges use the [tag registry](../guide/revalidation.md#tag-registry): every page recorded
   with the tag is purged on both of its URLs (HTML + page JSON).
4. The next request is a cache miss: rendered at the origin, cached again.

## Examples

### In a controller

```php
public function update(UpdatePostRequest $request, Post $post)
{
    $post->update($request->validated());

    StaticCache::purgeTags(["post:{$post->id}", 'posts']);

    return to_route('admin.posts.edit', $post);
}
```

### From the command line

```bash
php artisan inertia-static:purge --tags=posts,post:42          # synchronous
php artisan inertia-static:purge --tags=posts --queue          # queued
```

## Good to know

- Usually you do not call it: [`HasStaticTags`](./has-static-tags) does it when models change.
- It does nothing in [headers-only mode](../guide/headers-only.md) (no CDN credentials); a notice is logged.
- On Cloudflare Free, tag purges are limited to 5 requests per minute: set `INERTIA_STATIC_PURGE_RATE_LIMIT=5`.
