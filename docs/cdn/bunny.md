# Bunny

```ini
INERTIA_STATIC_CDN=bunny
BUNNY_PULL_ZONE_ID=...
BUNNY_API_KEY=...
```

## Headers

`Cache-Control` + `CDN-Cache-Control`. Bunny's Smart Cache **never caches `text/html` or `application/json`**:
add an Edge Rule *Override Cache Time* (or turn Smart Cache off) for your pages and `/__inertia/*`.

## Tags: off by default

Bunny stores a single `CDN-Tag` value per response (max 1 KB) and has no documented multi-tag separator, so a tag
purge could silently miss pages. By default the driver does not send tags. Tag purges go through the
[tag registry](../guide/revalidation.md#tag-registry) and become URL purges. Opt in with `native_tags => true`
(only the first tag is sent).

## Safety net (Edge Rules)

**CDN → pull zone → Edge Rules → Add Edge Rule**:

| Rule | Action | Trigger |
|---|---|---|
| A. safety net | **Override Cache Time** = `0` | **Response Header** `X-Inertia-Static-Cache`, pattern `cacheable`, matching **Match None** |
| B. Inertia visits on HTML URLs | **Override Cache Time** = `0` | **Request Header** `X-Inertia`, pattern `true` (exclude `/__inertia/*` with a URL trigger) |

Give them the lowest order: `Override Cache Time` is first-match.

A stricter alternative is an **allow-list**: keep Smart Cache (HTML and JSON are never cached) and add
*Override Cache Time* > 0 triggered by the response header `X-Inertia-Static-Cache` = `cacheable`.

::: warning Not verified on a live Bunny zone
Response-header triggers exist in the Edge Rules API, but the docs neither show them combined with
`Override Cache Time` to prevent storage nor say whether a **missing** header counts as "match none". Check with
`curl -I` (`CDN-Cache` header) before relying on it.
:::

## Purge

| Operation | Request |
|---|---|
| URL | `POST https://api.bunny.net/purge?url=...` |
| Prefix | same, with a trailing `*` |
| All | `POST /pullzone/{id}/purgeCache` |

Rate limits: exact purges about 300/min, prefix and zone purges about 30/min.
