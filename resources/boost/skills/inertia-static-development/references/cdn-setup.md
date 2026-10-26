# CDN setup

Pick the driver of the cache in front of the app. Credentials are only needed to purge; without them the driver
runs in headers-only mode (cache headers sent, pages expire by TTL, `StaticCache::canPurge()` is `false`,
`inertia-static:purge` refuses).

| Driver | Purge by tag | Env |
|---|---|---|
| `cloudflare` | yes | `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_API_TOKEN` |
| `fastly` | yes | `FASTLY_SERVICE_ID`, `FASTLY_API_TOKEN` |
| `bunny` | through the tag registry | `BUNNY_PULL_ZONE_ID`, `BUNNY_API_KEY` |
| `cloudfront` | through the tag registry | `CLOUDFRONT_DISTRIBUTION_ID`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` (`AWS_SESSION_TOKEN`) |
| `varnish` | yes | `VARNISH_HOSTS` |
| `nginx` | through the tag registry | `NGINX_CACHE_HOSTS` |
| `multi` | each driver's | each driver's |
| `null` (default) | — | none: sends cache headers, purges are no-ops |

Every CDN rule should key on the response header `X-Inertia-Static-Cache: cacheable` (name from
`INERTIA_STATIC_CACHE_HEADER`). Responses marked `bypass`, and responses without the header, must never be stored.

## Cloudflare

```ini
INERTIA_STATIC_CDN=cloudflare
CLOUDFLARE_ZONE_ID=...
CLOUDFLARE_API_TOKEN=...            # permission Zone -> Cache Purge
INERTIA_STATIC_PURGE_RATE_LIMIT=5   # Free plan: 5 purge requests per minute
```

Cloudflare does not cache HTML or JSON until rule 1 exists. In **Caching -> Cache Rules**:

1. **Cache Rules** tab, rule `inertia-static`: expression `(http.host eq "example.com")`; Cache eligibility
   **Eligible for cache**; Edge TTL **Use cache-control header if present, bypass cache if not**; Browser TTL
   **Respect origin TTL**; Respect strong ETags **on**. Place it after other rules matching the same host.
2. **Cache Response Rules** tab (safety net), rule `inertia-static safety net`: expression
   `(http.host eq "example.com" and not any(http.response.headers["x-inertia-static-cache"][*] eq "cacheable"))`;
   Modify cache-control directives -> Add directive `no-store`, **Cloudflare only** on.
3. Optional, for clients without the package client: Cache Rule after rule 1,
   `(http.host eq "example.com" and any(http.request.headers["x-inertia"][*] eq "true"))` -> **Bypass cache**.
4. Preview: Cache Rule `http.cookie contains "inertia_static_preview="` -> **Bypass cache**.

`purgeAll()` purges only the base URL hostname (or `drivers.cloudflare.hosts`); `CLOUDFLARE_PURGE_EVERYTHING=true`
purges the whole zone.

Check: `curl -sI https://example.com/ | grep -i -E 'cf-cache-status|x-inertia-static'` -> first `MISS`, then `HIT`.
`DYNAMIC` means rule 1 is missing; `BYPASS` means the origin said no.

## Fastly

```ini
INERTIA_STATIC_CDN=fastly
FASTLY_SERVICE_ID=...
FASTLY_API_TOKEN=...      # scopes purge_select + purge_all
```

Two Regular VCL snippets (within subroutine):

```txt
# fetch: store only what the package marks cacheable
if (beresp.http.X-Inertia-Static-Cache != "cacheable") {
  set beresp.ttl = 120s;
  return(pass);
}
```

```txt
# recv: Inertia visits on HTML URLs never use the cache
if (req.http.X-Inertia && req.url.path !~ "^/__inertia/") { return(pass); }
```

Purges are soft by default (except `purgeAll()`). Prefix purges use implicit path tags: keep `implicit_tags` on.

## Bunny

```ini
INERTIA_STATIC_CDN=bunny
BUNNY_PULL_ZONE_ID=...
BUNNY_API_KEY=...                  # account API key
INERTIA_STATIC_REGISTRY=database   # tag purges become URL purges
```

Pull zone -> Edge Rules -> Add Edge Rule: action **Override Cache Time** = your `revalidate` (e.g. `3600`), trigger
**Response Header** `X-Inertia-Static-Cache` matches `cacheable`. Check: `CDN-Cache` goes `MISS` -> `HIT`.
`drivers.bunny.native_tags` (default `false`) sends `CDN-Tag` (first tag only) instead of using the registry.

## CloudFront

```ini
INERTIA_STATIC_CDN=cloudfront
CLOUDFRONT_DISTRIBUTION_ID=E123ABC456
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_SESSION_TOKEN=...                # only with temporary credentials
INERTIA_STATIC_REGISTRY=database     # tag purges become path invalidations
```

IAM needs `cloudfront:CreateInvalidation`. Behavior: allowed methods GET, HEAD, OPTIONS, PUT, POST, PATCH, DELETE;
cache policy **UseOriginCacheControlHeaders-QueryStrings**; origin request policy **AllViewer**. CloudFront caches
any `Cache-Control: public` with `max-age`/`s-maxage`: never send that from non-static routes.

Serve stale while refreshing (optional): add a response headers policy overriding `Cache-Control` to
`public, max-age=0, must-revalidate` (Origin override on), then set `CLOUDFRONT_STALE=true`.

Invalidations above 1,000 paths/month are billed; a prefix counts as one path.

## Varnish

```ini
INERTIA_STATIC_CDN=varnish
VARNISH_HOSTS=http://varnish-1:6081,http://varnish-2:6081   # default http://127.0.0.1:6081; empty = headers-only
```

Requires varnish-modules (`xkey`, included in the official `varnish` Docker image). Use
[varnish.vcl](varnish.vcl) (in this skill), change the `backend` to the app and restrict `acl purge` to the app
servers. It stores only `cacheable` responses and reads TTL/stale time from
`Surrogate-Control`; `X-Cache` shows `HIT`, `STALE`, `MISS` or `BYPASS`. Tag header: `drivers.varnish.tag_header` (`xkey`). Soft tag purges by
default. Preview bypass in `vcl_recv`: `if (req.http.Cookie ~ "inertia_static_preview=") { return (pass); }`.

## nginx

```ini
INERTIA_STATIC_CDN=nginx
NGINX_CACHE_HOSTS=http://cache-1,http://cache-2   # default http://127.0.0.1; empty = headers-only
INERTIA_STATIC_REGISTRY=database
```

Needs the `ngx_cache_purge` module (Alpine: `apk add nginx-mod-http-cache-purge`). Purge path:
`drivers.nginx.purge_path` (`/purge`). The `map` + `proxy_no_cache` pair stores only `cacheable` responses, and the
preview cookie bypasses the cache:

```nginx
# nginx (OSS + ngx_cache_purge) configuration matching the "nginx" driver.
proxy_cache_path /var/cache/nginx/inertia levels=1:2 keys_zone=inertia:50m max_size=2g inactive=7d use_temp_path=off;

# Safety net: only store what the app marks as shared-cacheable
# (X-Inertia-Static-Cache: cacheable). Anything else is never stored.
# The purge target as sent (still percent-encoded), to match the cache key built from $request_uri.
map $request_uri $inertia_purge_target {
    "~^/purge(?<target>/[^?]*)" $target;
    default "";
}

map $upstream_http_x_inertia_static_cache $not_cacheable_by_app {
    default   1;
    cacheable 0;
}

server {
    listen 80;
    server_name example.com;

    location / {
        proxy_pass http://app:8000;
        proxy_set_header Host $host;

        proxy_cache inertia;
        proxy_cache_key "$scheme$host$request_uri";
        proxy_cache_methods GET HEAD;
        proxy_cache_revalidate on;                    # If-None-Match / 304 to the origin
        proxy_cache_lock on;                          # one origin hit per key
        proxy_cache_use_stale error timeout updating http_500 http_502 http_503 http_504;
        proxy_cache_background_update on;             # stale-while-revalidate
        proxy_cache_bypass $http_authorization $cookie_inertia_static_preview;
        proxy_no_cache $http_authorization $not_cacheable_by_app $cookie_inertia_static_preview;

        add_header X-Cache-Status $upstream_cache_status always;
    }

    # GET /purge/blog/post  (the driver appends "*" for prefix purges)
    location ~ ^/purge(/.*)$ {
        allow 127.0.0.1;
        allow 10.0.0.0/8;
        allow 172.16.0.0/12;
        deny all;
        proxy_cache_purge inertia "$scheme$host$inertia_purge_target$is_args$args";
    }
}
```

## Multi

```php
// config/inertia-static.php
'drivers' => [
    'multi' => ['driver' => 'multi', 'drivers' => ['varnish', 'cloudflare']],
],
```

Emits every chained driver's headers and sends every purge to each. Enable the tag registry if one has no tag purge.

## Custom driver

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::extend('my-cdn', fn ($app, array $config) => new MyCdnDriver(
    $app->make(\Illuminate\Http\Client\Factory::class),
    $config,
));

// config/inertia-static.php
'drivers' => [
    'my-cdn' => ['driver' => 'my-cdn', 'token' => env('MY_CDN_TOKEN')],
],
```

`MyCdnDriver` extends `Fydellys\InertiaStatic\Cdn\Driver`. Every driver accepts `timeout` (15) and
`connect_timeout` (5).

## Cache headers per driver

| Header | Driver |
|---|---|
| `Cache-Control: public, max-age=0, must-revalidate` | all except CloudFront (browsers) |
| `Cache-Control: public, max-age=0, s-maxage=3600` | `cloudfront` |
| `CDN-Cache-Control` | `null`, `bunny` |
| `Cloudflare-CDN-Cache-Control` | `cloudflare` |
| `Surrogate-Control` | `fastly`, `varnish` |
| `X-Accel-Expires` | `nginx` |
| `Cache-Tag` / `Surrogate-Key` / `xkey` / `CDN-Tag` | cloudflare / fastly / varnish / bunny with `native_tags` |
| strong `ETag` | every cacheable 200 (`If-None-Match` -> 304) |

Tag headers are capped by the CDN's size limit (16 KB on Cloudflare and Fastly); extra tags are dropped.

## Deploy

1. `npm run build` (Vite plugin writes `resources/js/inertia-static.json`; without it run
   `php artisan inertia-static:manifest`; in a Node-only CI step commit the manifest or run the command first).
2. `php artisan inertia-static:purge --all --warm` (skip in headers-only mode).
3. Keep the previous build's assets for a few hours. Old clients get `409` + `X-Inertia-Location` and reload once.
4. `php artisan inertia-static:doctor` (non-zero exit on failure).

Also: a queue worker runs; the SSR server is supervised if SSR is used; `INERTIA_STATIC_BASE_URL` is public.
