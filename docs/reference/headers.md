# Headers

## Emitted on cacheable responses

The cache headers depend on the CDN driver (defaults: `revalidate = 3600`, `expire` = one year,
`stale_if_error = 604800`):

| Header | Example | Driver |
|---|---|---|
| `Cache-Control` | `public, max-age=0, must-revalidate` | every driver except CloudFront (browsers) |
| `Cache-Control` | `public, max-age=0, s-maxage=3600` | `cloudfront` (`stale: true` adds `stale-while-revalidate` / `stale-if-error`) |
| `CDN-Cache-Control` | `public, max-age=3600, stale-while-revalidate=31532400, stale-if-error=604800` | `null`, `bunny` |
| `Cloudflare-CDN-Cache-Control` | same | `cloudflare` |
| `Surrogate-Control` | `max-age=3600, stale-while-revalidate=31532400, stale-if-error=604800` | `fastly`, `varnish` |
| `X-Accel-Expires` | `3600` | `nginx` |
| `Cache-Tag` | `posts,post:42,_IST_/,_IST_/blog/,…` | `cloudflare` |
| `Surrogate-Key` | `posts post:42 _IST_/ _IST_/blog/ …` | `fastly` |
| `xkey` (`tag_header`) | `posts post:42 _IST_/ _IST_/blog/ …` | `varnish` |
| `CDN-Tag` | `posts` (first tag only) | `bunny` with `native_tags: true` |
| `ETag` | `"9f2c0d…"` (strong) | every `200`; `If-None-Match` → `304` |
| `X-Inertia-Static-Cache` | `cacheable` | your CDN rules |
| `X-Inertia-Static` | `MISS` | debugging |

The `multi` driver emits the headers of every driver it chains. Tags are the route and response tags plus the
[implicit path tags](../guide/revalidation.md#implicit-path-tags).

`X-Inertia-Static: MISS` is set by the origin when it renders a cacheable response; the CDN stores and replays it.
For the CDN state, read the CDN's own header (`CF-Cache-Status`, `X-Cache`, `X-Cache-Status`…).

## Emitted on non-cacheable responses of static routes

```http
Cache-Control: no-store, private
X-Inertia-Static-Cache: bypass
X-Inertia-Static-Reason: authorization
```

The CDN headers (`*-Cache-Control`, `Surrogate-*`, tag headers, `X-Accel-Expires`) are removed, and there is no
`X-Inertia-Static` header.

| `X-Inertia-Static-Reason` | Cause |
|---|---|
| `authorization` | the request has an `Authorization` header |
| `preview` | a valid preview cookie was sent |
| `live` | the never-cached live JSON URL |
| `leak:auth`, `leak:session`, `leak:cookie`, `leak:header` | the leak detector fired (`log` / `auto` mode) |
| `server-error` | status ≥ 500 |
| `partial-reload` | partial reload, deferred props or `X-Inertia-Reset` |
| `inertia-json-on-html-url` | an Inertia visit (`X-Inertia`) on the HTML URL |
| `redirect` | a redirect from your code, with `defaults.cache_redirects` off, or any redirect on the page JSON URL |
| `status:404-json` | a `404` asked as JSON (`Accept: application/json`) on the HTML URL |
| `status:<code>` | any status other than 200, 404 or a redirect (e.g. `status:409` after a deploy) |
| `ssr-unavailable` | with `ssr.required` on: SSR is enabled but the HTML was rendered without it |
| `not-static` | the page JSON URL of an unknown or dynamic route (404) |

The header names follow `cache_header` and `debug_header` (`{debug_header}-Reason`); an empty value disables them.

## Removed

| Header | Why |
|---|---|
| `Set-Cookie` | a shared cache must never store or replay a cookie |
| `Cookie` in `Vary` | |
| `X-Inertia` in `Vary` (page JSON URLs only) | the URL already identifies the representation |
| `X-Inertia-Devtools-*` | per-request ids (Inertia DevTools is disabled on static routes) |

## Request headers that matter

| Header | Effect |
|---|---|
| `Authorization` | never cached (`authorization`) |
| `Cookie` | removed before routing on static routes (except the live URL) |
| `X-Forwarded-Host`, `X-Forwarded-Port`, `X-Forwarded-Prefix`, `Forwarded` | removed on static routes |
| `X-Forwarded-Proto` | set to the scheme of the base URL on static routes |
| `If-None-Match` | `304` when it matches |
| `X-Inertia` on the HTML URL | not cached (`inertia-json-on-html-url`) |
| `X-Inertia-Partial-*`, `X-Inertia-Reset` | partial reload: not cached; the client sends these visits to the live URL |
| Other `X-Inertia-*` request headers (`Except-Once-Props`, `Infinite-Scroll-Merge-Intent`, …) | ignored on a page load (no `X-Inertia`) of the HTML URL |
| `X-Inertia-Static-Bypass` | set on a visit, it makes the client plugin send it to the live URL |
| `Cache-Control: no-cache` (`router.reload()`, polling) | the client plugin sends the visit to the live URL |
