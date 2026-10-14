# Headers-only mode

**Caching never needs credentials; purging does.** A CDN driver without its API credentials runs in
*headers-only mode*:

- it still emits every cache header (TTL, tags, ETag) and the `X-Inertia-Static-Cache` header;
- pages expire by `revalidate` + `stale-while-revalidate` only;
- `StaticCache::purge*()` and `HasStaticTags` queue nothing (a notice is logged);
- `php artisan inertia-static:purge` refuses with a clear message, and `inertia-static:doctor` reports the mode;
- `StaticCache::canPurge()` returns `false`.

Add the credentials later and purges start working, with no code change.

```ini
INERTIA_STATIC_CDN=cloudflare      # no CLOUDFLARE_* variables = headers-only
```

## The `X-Inertia-Static-Cache` header

Every response of a static route carries a stable header you can build CDN rules on:

| Value | Meaning |
|---|---|
| `cacheable` | a shared cache may store this response |
| `bypass` | never store it (leak, `Authorization`, 5xx, SSR down, partial reload…) |
| *(absent)* | not a static route |

The name is configurable (`INERTIA_STATIC_CACHE_HEADER`; empty disables it).

### CDN setup

Each CDN page shows how to store only responses marked `cacheable`:
[Cloudflare](../cdn/cloudflare.md#zone-setup), [Fastly](../cdn/fastly.md#service-setup),
[Bunny](../cdn/bunny.md#pull-zone-setup), [Varnish](../cdn/varnish.md#vcl), [nginx](../cdn/nginx.md#server-setup).
