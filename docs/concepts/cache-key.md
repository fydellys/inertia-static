# Cache key normalization

One page should map to exactly one cache entry.

## Query string

- Only the parameters whitelisted on the route are kept: `->isr(query: ['page'])`.
- Tracking parameters are always removed: `utm_*`, `fbclid`, `gclid`, `gbraid`, `wbraid`, `msclkid`, `mc_cid`,
  `mc_eid`, `_ga`, `ref` (`normalization.strip_query`).
- Parameters are sorted; empty values are removed.
- A non-canonical URL gets a cacheable `301` to the canonical one:

```
GET /blog?utm_source=news&page=2&fbclid=x  →  301 Location: https://example.com/blog?page=2
```

The client plugin applies the same rules before rewriting, so SPA visits hit the same cache key.

## Trailing slash

`normalization.trailing_slash`: `never` (default), `always` or `ignore`. Configure the client with the same value
(`trailingSlash`).

## Locale

Put the locale **in the URL** (`/pt/...`, `/en/...`). Never vary static pages by `Accept-Language` or by a
cookie: the CDN would serve one language to everyone.

## Absolute URLs

Purge, warmup, canonical redirects and the sitemap build absolute URLs from `INERTIA_STATIC_BASE_URL` (or
`APP_URL`), **never** from `Host` / `X-Forwarded-Host`. This prevents unkeyed-header cache poisoning.
