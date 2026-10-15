# Cloudflare

## Configure

```ini
INERTIA_STATIC_CDN=cloudflare
CLOUDFLARE_ZONE_ID=...
CLOUDFLARE_API_TOKEN=...            # permission Zone → Cache Purge; optional (see Headers-only mode)
INERTIA_STATIC_PURGE_RATE_LIMIT=5   # purge requests per minute allowed by your plan (Free: 5)
```

## Zone setup

::: warning Required
Cloudflare does not cache HTML or JSON until you create rule 1.
:::

Create **both** rules, in this order. They live in two different tabs of **Caching → Cache Rules**:

| | Tab | What it does |
|---|---|---|
| **Rule 1** | **Cache Rules** | Makes your hostname eligible for cache, following the origin's headers. |
| **Rule 2** | **Cache Response Rules** | Safety net: refuses to store anything the package did not mark as cacheable. |

Rule 1 is enough to cache your static pages. Rule 2 makes sure nothing else is ever cached, even a route of yours
that sends `Cache-Control: public` by mistake.

## Rule 1: Cache Rule

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
   - *Respect strong ETags*: **on**
4. *Place at*: if the zone has other Cache Rules that match the same hostname, put this one **after** them.
   Later rules override earlier ones.
5. **Deploy**.

## Rule 2: Cache Response Rule (safety net)

**Caching → Cache Rules → Cache Response Rules** tab → **Create rule**.

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

## Check it

```bash
curl -sI https://example.com/ | grep -i -E 'cf-cache-status|x-inertia-static'
# 1st request: cf-cache-status: MISS, x-inertia-static: MISS
# 2nd request: cf-cache-status: HIT
curl -sI https://example.com/login | grep -i cf-cache-status
# cf-cache-status: BYPASS or DYNAMIC (never HIT)
```

## Optional: bypass Inertia requests on HTML URLs

Only needed if some clients do not use the package's client (for example an old tab with a previous bundle). Add a
third rule in the **Cache Rules** tab, placed **after** rule 1:

```txt
(http.host eq "example.com" and any(http.request.headers["x-inertia"][*] eq "true"))
```

→ *Cache eligibility*: **Bypass cache**.

## Purging

| Method | Purges |
|---|---|
| `purgeUrls()` | the given pages (HTML and page JSON) |
| `purgeTags()` | every page with one of the tags |
| `purgePrefix()` | every page under the path |
| `purgeAll()` | every page of this app's hostname (or the `hosts` listed in the driver config). Set `CLOUDFLARE_PURGE_EVERYTHING=true` to purge the whole zone instead |
