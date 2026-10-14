# `StaticCache::purgeAll()`

Purge every page of the application, typically after a deploy.

```php
StaticCache::purgeAll();
```

```bash
php artisan inertia-static:purge --all --warm      # deploy hook: purge, then refill the CDN
```

## Reference

```php
StaticCache::purgeAll(?PurgeMode $mode = null): void
```

| Driver | What "all" means |
|---|---|
| Cloudflare | **this app's hostname** (`{"hosts": ["example.com"]}`). The whole zone only with `CLOUDFLARE_PURGE_EVERYTHING=true` |
| Fastly | `purge_all` on the service |
| Bunny | the pull zone |
| Varnish | `BAN` of `/` |
| nginx | the `/*` prefix |

## Good to know

- Zones are often shared by several sites, so the Cloudflare default purges only the hostname of
  `INERTIA_STATIC_BASE_URL`.
- The tag registry is flushed too.
