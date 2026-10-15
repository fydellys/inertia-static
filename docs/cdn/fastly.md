# Fastly

## Configure

```ini
INERTIA_STATIC_CDN=fastly
FASTLY_SERVICE_ID=...
FASTLY_API_TOKEN=...      # scopes purge_select + purge_all; optional (see Headers-only mode)
```

## Service setup

Add two VCL snippets: **Service → Edit configuration → Clone → VCL → VCL snippets → Add snippet**, type
*Regular*, placement *within subroutine*.

**fetch**: store only what the package marks as cacheable.

```txt
if (beresp.http.X-Inertia-Static-Cache != "cacheable") {
  set beresp.ttl = 120s;
  return(pass);
}
```

**recv**: Inertia visits on HTML URLs never use the cache.

```txt
if (req.http.X-Inertia && req.url.path !~ "^/__inertia/") { return(pass); }
```

Activate the new version.

## Purging

| Method | Purges |
|---|---|
| `purgeUrls()` | the given pages (HTML and page JSON) |
| `purgeTags()` | every page with one of the tags |
| `purgePrefix()` | every page under the path |
| `purgeAll()` | the whole service |

Purges are soft by default (except `purgeAll()`): the old page is served while the new one is fetched. See
[soft or hard purge](../guide/revalidation.md#soft-or-hard-purge).
