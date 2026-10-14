# nginx

```ini
INERTIA_STATIC_CDN=nginx
NGINX_CACHE_HOSTS=http://cache-1,http://cache-2
INERTIA_STATIC_REGISTRY=database   # nginx has no tags: tag purges go through the registry
```

## Headers

```http
Cache-Control: public, max-age=0, must-revalidate
X-Accel-Expires: 3600
```

`X-Accel-Expires` is read by nginx only, takes precedence over `Cache-Control`, and is not forwarded to the browser.

## Purging

Open-source nginx needs [ngx_cache_purge](https://github.com/nginx-modules/ngx_cache_purge) (Alpine:
`apk add nginx-mod-http-cache-purge`); NGINX Plus has `proxy_cache_purge` built in. The driver sends
`GET {host}/purge/{path}`, with a trailing `*` for prefixes and `/*` for purge all. A `404`/`412` answer
(key not cached) counts as success.

<<< @/nginx.conf{nginx}

The `map` + `proxy_no_cache` pair is the **safety net**, the equivalent of the Cloudflare Cache Response Rule: only
responses with `X-Inertia-Static-Cache: cacheable` are stored. nginx honours `Vary: X-Inertia` (it is part of the
cache variant), so an Inertia visit on an HTML URL never gets the cached HTML. To be extra strict, add
`proxy_cache_bypass $http_x_inertia;` to the HTML locations (not to `/__inertia/`).

## Verified

The [demo](../testing/e2e.md) runs exactly this setup (Alpine nginx + `ngx_cache_purge` + the safety net). The E2E
suite (109 checks) covers URL / tag (via registry) / prefix / all purges, time-based revalidation (`STALE`), and
asserts that dynamic pages are never stored.
