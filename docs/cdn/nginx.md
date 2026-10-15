# nginx

## Configure

```ini
INERTIA_STATIC_CDN=nginx
NGINX_CACHE_HOSTS=http://cache-1,http://cache-2   # default http://127.0.0.1; empty = headers-only
INERTIA_STATIC_REGISTRY=database                  # tag purges become URL purges
```

## Server setup

Purging needs the [ngx_cache_purge](https://github.com/nginx-modules/ngx_cache_purge) module (Alpine:
`apk add nginx-mod-http-cache-purge`).

<<< @/nginx.conf{nginx}

The `map` + `proxy_no_cache` pair stores only responses marked `cacheable` by the package.

## Purging

| Method | Purges |
|---|---|
| `purgeUrls()` | the given pages (HTML and page JSON) |
| `purgeTags()` | every page with one of the tags (through the tag registry) |
| `purgePrefix()` | every page under the path |
| `purgeAll()` | every page of this app's hostname |
