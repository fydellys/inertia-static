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

Each page is purged on **both** of its URLs:

```
https://example.com/blog/hello
https://example.com/__inertia/<asset version>/blog/hello.json
```

## Good to know

- Paths are resolved against `INERTIA_STATIC_BASE_URL`, never against the request host.
- Query strings are part of the URL: `/blog?page=2` purges only that page. Use [`purgePrefix()`](./purge-prefix) for all of them.
- Queued and debounced like [`purgeTags()`](./purge-tags#behaviour).
