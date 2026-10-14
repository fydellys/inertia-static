# Fastly

```ini
INERTIA_STATIC_CDN=fastly
FASTLY_SERVICE_ID=...
FASTLY_API_TOKEN=...
```

## Headers

```http
Cache-Control: public, max-age=0, must-revalidate
Surrogate-Control: max-age=3600, stale-while-revalidate=86400, stale-if-error=604800
Surrogate-Key: posts post:42
```

Fastly reads `Surrogate-Control` first and strips it (except with shielding). `Surrogate-Key` is
space-separated: at most 16 KB, 1 KB per key, and keys are case-sensitive.

## Purge

| Operation | Request |
|---|---|
| URL | `POST https://api.fastly.com/purge/{host}/{path}` |
| Keys | `POST /service/{id}/purge` with `Surrogate-Key: k1 k2` (up to 256 per request) |
| All | `POST /service/{id}/purge_all` |
| Prefix | not supported → [tag registry](../guide/revalidation.md#tag-registry) / purge all |

Soft purge (`Fastly-Soft-Purge: 1`) is on by default (`soft_purge`): objects are marked stale, not removed,
so `stale-while-revalidate` keeps working.

## Safety net (VCL snippets)

The equivalent of the Cloudflare Cache Response Rule. Fastly's default TTL parsing does **not** honour
`Cache-Control: private` / `no-store`, so an explicit rule matters even more here.

**Service → Edit configuration → clone → VCL → VCL snippets → Add snippet**, type *Regular*, placement
*within subroutine*:

```txt
# fetch: only store what the app marks as shared-cacheable
if (beresp.http.X-Inertia-Static-Cache != "cacheable") {
  set beresp.ttl = 120s;   # hit-for-pass lifetime (Fastly's minimum is 120s)
  return(pass);
}
```

```txt
# recv: Inertia visits on HTML URLs never use the cache (defense in depth; Fastly honours Vary)
if (req.http.X-Inertia && req.url.path !~ "^/__inertia/") { return(pass); }
```

Activate the new version. Do not combine `set beresp.cacheable = false` with `return(pass)`: it suppresses the
hit-for-pass object and makes queued requests hit the origin one by one.

::: warning Not verified on a live Fastly service
Based on the current Fastly VCL documentation (`vcl_fetch`, `beresp.cacheable`, VCL snippets). Unlike Cloudflare,
Varnish and nginx, it has not been run against a real service yet.
:::

## Notes

- Responses with `Set-Cookie` or `private` are not cached; the package strips `Set-Cookie` on static routes.
- Purges are not counted against the general API rate limit (about 100k purges per hour per customer).
