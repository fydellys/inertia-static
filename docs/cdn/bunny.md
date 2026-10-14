# Bunny

## Configure

```ini
INERTIA_STATIC_CDN=bunny
BUNNY_PULL_ZONE_ID=...
BUNNY_API_KEY=...                  # optional (see Headers-only mode)
INERTIA_STATIC_REGISTRY=database   # tag purges become URL purges
```

## Pull zone setup

Bunny's Smart Cache never caches HTML or JSON. In **CDN → pull zone → Edge Rules → Add Edge Rule**, add:

| Rule | Action | Trigger |
|---|---|---|
| Cache static pages | **Override Cache Time**: your `revalidate` in seconds (e.g. `3600`) | **Response Header** `X-Inertia-Static-Cache` matches `cacheable` |

Only responses marked `cacheable` by the package are cached; everything else keeps Smart Cache's default (not
cached). Purges refresh pages before that time when your content changes.

Check it with `curl -I https://example.com/` twice: the `CDN-Cache` header goes from `MISS` to `HIT`.

## Purging

| Method | Purges |
|---|---|
| `purgeUrls()` | the given pages (HTML and page JSON) |
| `purgeTags()` | the pages recorded for the tags in the registry |
| `purgePrefix()` | every page under the path |
| `purgeAll()` | the whole pull zone |
