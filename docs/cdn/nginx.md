# nginx

## Configure

```ini
INERTIA_STATIC_CDN=nginx
NGINX_CACHE_HOSTS=http://cache-1,http://cache-2   # optional (see Headers-only mode)
INERTIA_STATIC_REGISTRY=database                  # tag purges become URL purges
```

## Server setup

Purging needs [ngx_cache_purge](https://github.com/nginx-modules/ngx_cache_purge) on open-source nginx (Alpine:
`apk add nginx-mod-http-cache-purge`). NGINX Plus has it built in.

<<< @/nginx.conf{nginx}

The `map` + `proxy_no_cache` pair stores only responses marked `cacheable` by the package.

## Purging

| Method | Purges |
|---|---|
| `purgeUrls()` | the given pages (HTML and page JSON) |
| `purgeTags()` | the pages recorded for the tags in the registry |
| `purgePrefix()` | every page under the path |
| `purgeAll()` | every page |
