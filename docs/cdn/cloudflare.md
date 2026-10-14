# Cloudflare

```ini
INERTIA_STATIC_CDN=cloudflare
CLOUDFLARE_ZONE_ID=...
CLOUDFLARE_API_TOKEN=...          # permission: Zone → Cache Purge (optional: headers-only without it)
CLOUDFLARE_PURGE_BATCH_SIZE=100   # Enterprise: up to 500 URLs
CLOUDFLARE_PURGE_EVERYTHING=false # "purge all" = only this app's hostname (see below)
INERTIA_STATIC_PURGE_RATE_LIMIT=5 # Free plan
```

## Headers

```http
Cache-Control: public, max-age=0, must-revalidate
Cloudflare-CDN-Cache-Control: public, max-age=3600, stale-while-revalidate=86400, stale-if-error=604800
Cache-Tag: posts,post:42
X-Inertia-Static-Cache: cacheable
```

`Cloudflare-CDN-Cache-Control` is used instead of `s-maxage`: on Cloudflare `s-maxage` implies
`proxy-revalidate`, which disables `stale-while-revalidate`. Cloudflare does not forward this header downstream,
and it takes precedence over `CDN-Cache-Control`. `Cache-Tag` is stripped by Cloudflare before reaching visitors.

## Required zone configuration

::: danger Cloudflare does not cache HTML or JSON by default
It decides by file extension. Without a Cache Rule your pages are always `DYNAMIC` (never cached), no matter
which headers the origin sends.
:::

1. **Caching → Cache Rules**, for your hostname:
   - *Cache eligibility*: **Eligible for cache**
   - *Edge TTL*: **Use cache-control header if present, bypass cache if not**
   - *Browser TTL*: respect origin
   - *Respect strong ETags*: **on** (otherwise compression weakens the ETag and 304s stop working)
2. **Cache Response Rule** (strongly recommended safety net, see below).

Dynamic routes keep sending `Cache-Control: no-cache, private` (Laravel's default) and are bypassed.

## Safety net: Cache Response Rule

A zone-wide "eligible for cache" rule stores **any** response with `Cache-Control: public`. For example, a
cookie-less API route of yours that sends `public, max-age=300` by mistake. This was reproduced on a real zone:
`MISS` → `HIT`, the same response served to everyone. The safety net makes the edge store **only** what the
package marks as cacheable:

1. **Caching → Cache Rules → Cache Response Rules** tab → **Create rule**. This is a different tab from the regular
   Cache Rules: the regular ones run on the request and reject `http.response.headers`.
2. Rule name: `inertia-static safety net`
3. *Custom filter expression* → **Edit expression**:

   ```txt
   (http.host eq "example.com" and not any(http.response.headers["x-inertia-static-cache"][*] eq "cacheable"))
   ```
4. *Then*: **Modify cache-control directives** → **Add directive** → **`no-store`**, with **Cloudflare only** on (the
   browser still receives your origin's `Cache-Control`).
5. **Deploy**.

Verified on a real zone: the careless route goes from `HIT` to `BYPASS`, and static pages stay `HIT`.

### Optional: bypass Inertia requests on HTML URLs

Clients using the plugin never send Inertia visits to HTML URLs. For clients without it (an old tab with a stale
bundle, a custom HTTP client), add a regular **Cache Rule** placed **after** the "eligible" one:

```txt
(http.host eq "example.com" and any(http.request.headers["x-inertia"][*] eq "true"))
```

→ **Bypass cache**. Cloudflare ignores `Vary: X-Inertia`, so without this rule such a request would get the cached HTML.

::: tip Respect strong ETags
Without it, Cloudflare drops the ETag of compressed HTML (observed on a real zone), so `If-None-Match`
revalidation does not work for HTML. Page JSON kept its ETag. Turn it on in the Cache Rule of your hostname.
:::

## Purge API

`POST /client/v4/zones/{zone_id}/purge_cache` with one of:

| Operation | Body |
|---|---|
| URLs | `{"files": ["https://example.com/blog", "https://example.com/__inertia/<v>/blog.json"]}` |
| Tags | `{"tags": ["posts", "post:42"]}` |
| Prefix | `{"prefixes": ["example.com/blog", "example.com/__inertia/<v>/blog"]}` (no scheme) |
| All | `{"hosts": ["example.com"]}`: **only this app's hostname** |
| All, opt-in (`CLOUDFLARE_PURGE_EVERYTHING=true`) | `{"purge_everything": true}`: the whole zone |

A zone is often shared by several sites (`example.com`, `app.example.com`, `staging.example.com`…). Purging
"everything" therefore purges the hostname of `INERTIA_STATIC_BASE_URL` by default (or `drivers.cloudflare.hosts`).
Hostname purges are available on every plan.

The driver fails on HTTP errors and on `200` answers with `"success": false`, retries 429/5xx, and batches by
100.

## Limits (2026-10)

| Plan | Tag / prefix / host / everything | URLs |
|---|---|---|
| Free | 5 requests/min (burst 25) | 800/s |
| Pro | 5/s (burst 25) | 1,500/s |
| Business | 10/s (burst 50) | 1,500/s |
| Enterprise | 50/s (burst 500) | 3,000/s, 500 per request |

Set `INERTIA_STATIC_PURGE_RATE_LIMIT` / `purge.rate_limit_per_minute` accordingly (Free: `5`). Tag purges are
available on every plan. A tag can be at most 1,024 characters; the whole `Cache-Tag` header at most 16 KB.

## Verified on a real zone (Free plan, 2026-10-14)

- **Live API tests** (`tests/Live/CloudflareLiveTest.php`): URL purge (HTML + JSON), batching over 100 items,
  tag purge, prefix purge.
- **E2E through a Cloudflare Tunnel** (`examples/demo-docker/e2e-cloudflare.sh`, 51 checks, Chromium and Firefox):
  - HTML and page JSON cached (`MISS` → `HIT`); dynamic pages and `Authorization` requests `BYPASS`;
  - a new browser navigates 9 pages entirely from Cloudflare (no synchronous origin hit);
  - no trace of a signed-in user in anything Cloudflare stored;
  - time-based revalidation: after the TTL Cloudflare answers **`UPDATING`** (old page served instantly,
    refreshed in the background), then `HIT` with new content;
  - on-demand: model saved → queued `Cache-Tag` purge → HTML, JSON and tagged listing refreshed;
  - `purge --url / --tags / --prefix / --all (hostname) / --warm` only touch their target;
  - safety-net Cache Response Rule.

`CF-Cache-Status` values you will see: `HIT`, `MISS`, `EXPIRED`, `UPDATING` (stale served while refreshing),
`BYPASS` (origin said no / safety net), `DYNAMIC` (not eligible: missing Cache Rule).
