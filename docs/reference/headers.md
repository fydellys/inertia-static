# Headers

## Emitted on cacheable responses

| Header | Example | Who reads it |
|---|---|---|
| `Cache-Control` | `public, max-age=0, must-revalidate` | browsers (and `s-maxage` caches on Varnish) |
| `Cloudflare-CDN-Cache-Control` | `public, max-age=3600, stale-while-revalidate=86400, stale-if-error=604800` | Cloudflare |
| `CDN-Cache-Control` | same | RFC 9213 CDNs (null, Bunny) |
| `Surrogate-Control` | `max-age=3600, stale-while-revalidate=86400` | Fastly |
| `X-Accel-Expires` | `3600` | nginx |
| `Cache-Tag` / `Surrogate-Key` / `xkey` | `posts,post:42` | Cloudflare / Fastly / Varnish |
| `ETag` | `"9f2c0d…"` (strong) | everyone; `If-None-Match` → `304` |
| `X-Inertia-Static-Cache` | `cacheable` | your CDN rules |
| `X-Inertia-Static` | `MISS; ttl=3600` | humans (debug) |

`X-Inertia-Static: MISS` is stored with the response: it describes the **origin's** decision (rendered and
cacheable), not the CDN state. Use your CDN's header for that (`CF-Cache-Status`, `X-Cache`, `X-Cache-Status`…).

## Emitted on non-cacheable responses of static routes

```http
Cache-Control: no-store, private
X-Inertia-Static-Cache: bypass
X-Inertia-Static: BYPASS; reason=authorization
```

CDN headers (`*-Cache-Control`, `Surrogate-*`, tags, `X-Accel-Expires`) are removed.

## Removed

| Header | Why |
|---|---|
| `Set-Cookie` | a shared cache must never store or replay a cookie |
| `Cookie` in `Vary` | |
| `X-Inertia` in `Vary` (page JSON endpoint only) | its URL already identifies the representation |
| `X-Inertia-Devtools-*` | per-request ids |

## Request headers that matter

| Header | Effect |
|---|---|
| `Authorization` | never cached (`BYPASS; reason=authorization`) |
| `If-None-Match` | `304` when it matches |
| `X-Inertia-Partial-*`, `X-Inertia-Reset` | partial reload: origin, never cached |
| `X-Inertia-Static-Bypass` (client) | the plugin sends the visit to the never-cached live endpoint |
