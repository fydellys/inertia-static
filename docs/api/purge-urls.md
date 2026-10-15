# `StaticCache::purgeUrls()`

Purge specific pages.

```php
StaticCache::purgeUrls('/about');
StaticCache::purgeUrls(['/blog/hello', 'https://example.com/pricing']);
```

## Reference

```php
StaticCache::purgeUrls(array|string $urls, ?PurgeMode $mode = null): void   // page paths or absolute URLs
```

`$mode` works as in [`purgeTags()`](./purge-tags#reference).

## Good to know

- Each page is purged on both of its URLs: the HTML and the page JSON
  (`/__inertia/<asset version>/blog/hello.json`).
- Paths are resolved against `INERTIA_STATIC_BASE_URL`, never against the request host.
- The query string is part of the URL: `/blog?page=2` purges only that page. Use [`purgePrefix()`](./purge-prefix)
  for all of them.
- Queued and debounced like [`purgeTags()`](./purge-tags#good-to-know). From the command line:
  `php artisan inertia-static:purge --url=/about`.
