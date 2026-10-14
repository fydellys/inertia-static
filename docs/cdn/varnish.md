# Varnish

## Configure

```ini
INERTIA_STATIC_CDN=varnish
VARNISH_HOSTS=http://varnish-1:6081,http://varnish-2:6081   # optional (see Headers-only mode)
```

## VCL

Requires [varnish-modules](https://github.com/varnish/varnish-modules) (`xkey`), included in the official
`varnish` Docker image. Use this VCL and change the `backend` to your app:

<<< @/varnish.vcl{txt}

It stores only responses marked `cacheable` by the package, reads the TTL and stale time from `Surrogate-Control`
(and removes it), and answers the package's purge requests. `X-Cache` shows `HIT`, `STALE`, `MISS` or `BYPASS`.

Allow purges only from your app servers: edit `acl purge`.

## Purging

| Method | Purges |
|---|---|
| `purgeUrls()` | the given pages (HTML and page JSON) |
| `purgeTags()` | every page with one of the tags |
| `purgePrefix()` | every page under the path |
| `purgeAll()` | every page |

Tag purges are soft by default: the old page is served once while the new one is fetched. See
[soft or hard purge](../guide/revalidation.md#soft-or-hard-purge).
