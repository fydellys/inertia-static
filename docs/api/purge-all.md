# `StaticCache::purgeAll()`

Purge every page of the application, typically after a deploy.

```php
StaticCache::purgeAll();
```

```bash
# deploy hook: purge now, then refill the CDN with every static page
php artisan inertia-static:purge --all --warm
```

## Reference

```php
StaticCache::purgeAll(?PurgeMode $mode = null): void
```

`$mode` works as in [`purgeTags()`](./purge-tags#reference).

## Good to know

- On Cloudflare it purges only this app's hostname (from `INERTIA_STATIC_BASE_URL`), since a zone is often shared
  by several sites. Set `CLOUDFLARE_PURGE_EVERYTHING=true` to purge the whole zone.
- On the other CDNs it purges everything they cache for the site.
- Queued and debounced like [`purgeTags()`](./purge-tags#good-to-know).
