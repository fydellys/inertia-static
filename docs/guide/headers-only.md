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

1. **Cache Rule** (request phase) for your hostname: *Eligible for cache*, *Edge TTL: use cache-control header
   if present, bypass cache if not* (`edge_ttl.mode: respect_origin`). Cloudflare does not cache HTML/JSON
   without it.
2. **Cache Response Rule** (response phase, separate tab) as a safety net: when

   ```txt
   (http.host eq "example.com" and not any(http.response.headers["x-inertia-static-cache"][*] eq "cacheable"))
   ```

   then *Modify cache-control directives* → add `no-store` (*Cloudflare only*). Only pages marked by the package
   are stored, even when a dynamic route forgets `Cache-Control: private`. Step by step in
   [Cloudflare](../cdn/cloudflare.md#safety-net-cache-response-rule). Verified on a real zone.

### Other CDNs

| CDN | Safety net | Verified |
|---|---|---|
| [Cloudflare](../cdn/cloudflare.md#safety-net-cache-response-rule) | Cache Response Rule → `no-store` | ✅ real zone |
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
