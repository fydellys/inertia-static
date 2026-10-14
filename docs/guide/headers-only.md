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

### Cloudflare

Two rules, created in this order: a **Cache Rule** that makes your hostname eligible for cache (Cloudflare does
not cache HTML or JSON without it), then a **Cache Response Rule** that stores only responses with
`X-Inertia-Static-Cache: cacheable`. Step by step in [Cloudflare: zone setup](../cdn/cloudflare.md#zone-setup-two-rules).
Verified on a real zone.

### Other CDNs

| CDN | Safety net | Verified |
|---|---|---|
| [Cloudflare](../cdn/cloudflare.md#rule-2-cache-response-rule-safety-net) | Cache Response Rule → `no-store` | ✅ real zone |
| [nginx](../cdn/nginx.md) | `map $upstream_http_x_inertia_static_cache` + `proxy_no_cache` | ✅ demo E2E |
| [Varnish](../cdn/varnish.md) | `vcl_backend_response`: `beresp.uncacheable` unless `cacheable` | ✅ Varnish 7.7 |
| [Fastly](../cdn/fastly.md#safety-net-vcl-snippets) | `vcl_fetch` snippet: `return(pass)` unless `cacheable` | docs only |
| [Bunny](../cdn/bunny.md#safety-net-edge-rules) | Edge Rule: Override Cache Time 0 on response header | docs only |

```nginx
# nginx
map $upstream_http_x_inertia_static_cache $not_cacheable_by_app {
    default   1;
    cacheable 0;
}
proxy_no_cache $http_authorization $not_cacheable_by_app;
```

```txt
# Varnish (vcl_backend_response)
if (beresp.http.X-Inertia-Static-Cache != "cacheable") {
    set beresp.uncacheable = true;
    set beresp.ttl = 120s;
    return (deliver);
}
```
