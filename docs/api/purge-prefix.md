# `StaticCache::purgePrefix()`

Purge every page under a path.

```php
StaticCache::purgePrefix('/blog');                 // /blog, /blog?page=2, /blog/hello, …
StaticCache::purgePrefix(['/docs', '/changelog']);
```

## Reference

```php
StaticCache::purgePrefix(array|string $prefixes, ?PurgeMode $mode = null): void
```

The JSON prefix (`/__inertia/<version>/blog`) is purged with the HTML prefix.

| Driver | How |
|---|---|
| Cloudflare | native prefix purge (`example.com/blog`) |
| Varnish | `BAN` on the URL prefix |
| nginx / Bunny | wildcard purge (`/blog*`) |
| Fastly | no prefix purge: [implicit path tag](../guide/revalidation.md#implicit-path-tags) `_IST_/blog/` |

## Good to know

- When neither prefix purge nor the registry can resolve the pages, the purge **escalates to purge all**: stale
  content is worse than a cold cache.
