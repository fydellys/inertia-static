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

## Zone setup: two rules

::: danger Cloudflare does not cache HTML or JSON by default
It decides by file extension. Without rule 1 your pages are always `DYNAMIC` (never cached), no matter which
headers the origin sends.
:::

Create **both** rules, in this order. They live in two different tabs of **Caching → Cache Rules**:

| | Tab | What it does |
|---|---|---|
| **Rule 1** | **Cache Rules** | Makes your hostname eligible for cache, following the origin's headers. |
| **Rule 2** | **Cache Response Rules** | Safety net: refuses to store anything the package did not mark as cacheable. |

Rule 1 alone already works: static pages are cached and dynamic routes are not, because Laravel answers them with
`Cache-Control: private`. Rule 2 protects you from your own mistakes. Without it, any route of yours that sends
`Cache-Control: public` by accident would be stored and served to everyone. This was reproduced on a real zone.

### Rule 1: Cache Rule

**Caching → Cache Rules → Create rule**

1. *Rule name*: `inertia-static`
2. *If incoming requests match…* → **Custom filter expression** → **Edit expression**:

   ```txt
   (http.host eq "example.com")
   ```
3. *Then…*
   - *Cache eligibility*: **Eligible for cache**
   - *Edge TTL*: **Use cache-control header if present, bypass cache if not**
   - *Browser TTL*: **Respect origin TTL**
   - *Respect strong ETags*: **on**. Without it, Cloudflare drops the ETag of compressed HTML and `304`
     revalidation stops working.
4. *Place at*: if the zone has other Cache Rules that match the same hostname, put this one **after** them.
   Later rules override earlier ones.
5. **Deploy**.

### Rule 2: Cache Response Rule (safety net)

**Caching → Cache Rules → Cache Response Rules** tab → **Create rule**. It must be this tab: regular Cache Rules
run on the request and reject `http.response.headers`.

1. *Rule name*: `inertia-static safety net`
2. *If incoming requests match…* → **Custom filter expression** → **Edit expression**:

   ```txt
   (http.host eq "example.com" and not any(http.response.headers["x-inertia-static-cache"][*] eq "cacheable"))
   ```
3. *Then…* → **Modify cache-control directives**:
   - *Action*: **Add directive**
   - *Directive*: **`no-store`**
   - **Cloudflare only**: **on** (browsers still receive your origin's `Cache-Control`)
4. **Deploy**.

### Check it

```bash
curl -sI https://example.com/ | grep -i -E 'cf-cache-status|x-inertia-static'
# 1st request: cf-cache-status: MISS, x-inertia-static: MISS
# 2nd request: cf-cache-status: HIT
curl -sI https://example.com/login | grep -i cf-cache-status
# cf-cache-status: BYPASS or DYNAMIC (never HIT)
```

### Optional: bypass Inertia requests on HTML URLs

Clients using the plugin never send Inertia visits to HTML URLs. For clients without it (an old tab with a stale
bundle, a custom HTTP client), add a third rule in the **Cache Rules** tab, placed **after** rule 1:

```txt
(http.host eq "example.com" and any(http.request.headers["x-inertia"][*] eq "true"))
```

→ *Cache eligibility*: **Bypass cache**. Cloudflare ignores `Vary: X-Inertia`, so without this rule such a request
would get the cached HTML.

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
- **E2E through a Cloudflare Tunnel** (`examples/demo-docker/e2e-cloudflare.sh`, 84 checks, Chromium and Firefox):
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
