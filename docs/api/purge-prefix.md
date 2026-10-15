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

`$mode` works as in [`purgeTags()`](./purge-tags#reference).

## Good to know

- The page JSON of those pages is purged too.
- Fastly has no prefix purge: it is done through [implicit path tags](../guide/revalidation.md#implicit-path-tags),
  so keep `implicit_tags` enabled.
- When the CDN can neither purge the prefix nor find the pages under it, it purges everything instead.
- Queued and debounced like [`purgeTags()`](./purge-tags#good-to-know). From the command line:
  `php artisan inertia-static:purge --prefix=/blog`.
