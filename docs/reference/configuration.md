# Configuration

`config/inertia-static.php` (publish with `php artisan vendor:publish --tag=inertia-static-config`).

## General

| Key | Env | Default | |
|---|---|---|---|
| `enabled` | `INERTIA_STATIC_ENABLED` | `true` | Kill switch: every route behaves as dynamic and purges are skipped. |
| `base_url` | `INERTIA_STATIC_BASE_URL` | `APP_URL` | Origin of every absolute URL (purge, warmup, canonical redirects). |
| `inertia_middleware` | — | `App\Http\Middleware\HandleInertiaRequests` | Used to resolve the asset version outside HTTP requests (queues, commands). |
| `json.prefix` | — | `__inertia` | Prefix of the page JSON, live, manifest and CSRF URLs. |
| `json.index` | — | `index` | JSON name of the root page (`/__inertia/{version}/index.json`). |
| `json.live_middleware` | — | `EncryptCookies`, `StartSession` | Middleware of the never-cached live URL, so it can return validation errors and Inertia flash data to forms submitted from static pages. |
| `strip_middleware` | — | session, cookie and CSRF middleware | Removed from static routes. Classes that do not exist are ignored. |
| `public_shared_props` | — | `[]` | Shared props kept on static routes. |
| `cache_header` | `INERTIA_STATIC_CACHE_HEADER` | `X-Inertia-Static-Cache` | `cacheable` / `bypass` header for CDN rules; empty disables it. |
| `debug_header` | `INERTIA_STATIC_DEBUG_HEADER` | `X-Inertia-Static` | `MISS` on cacheable responses; `{header}-Reason` explains the others; empty disables both. |
| `implicit_tags` | — | `true` | Tag every cached page with its path prefixes (`_IST_/`, `_IST_/blog/`, …). |

## Leak detector

| Key | Env | Default | |
|---|---|---|---|
| `leak_detector.mode` | `INERTIA_STATIC_LEAK_DETECTOR` | `null` | `throw`, `auto` or `log`; `null` = `throw` in `throw_in` environments, `auto` elsewhere. |
| `leak_detector.store` | `INERTIA_STATIC_DOWNGRADES_STORE` | default cache store | Where `auto` mode remembers the routes it turned dynamic. |
| `leak_detector.throw_in` | — | `['local', 'testing']` | |
| `leak_detector.log_channel` | `INERTIA_STATIC_LOG_CHANNEL` | default channel | |

## SSR, CSRF and props

| Key | Env | Default | |
|---|---|---|---|
| `ssr.required` | `INERTIA_STATIC_SSR_REQUIRED` | `false` | `true`: when Inertia SSR is enabled, an HTML page rendered without it (SSR server down) is sent with `no-store` instead of being cached. |
| `csrf_cookie` | `INERTIA_STATIC_CSRF_COOKIE` | `null` | `true`: the client fetches the CSRF cookie from `/__inertia/csrf` before the first form submission. `null`: only on Laravel 12. |
| `deferred_props` | `INERTIA_STATIC_DEFERRED_PROPS` | `throw` | `throw` or `allow` deferred props on static pages. |

## Preview mode

| Key | Default | |
|---|---|---|
| `preview.cookie` | `inertia_static_preview` | Name of the preview cookie. |
| `preview.minutes` | `60` | Default lifetime of `StaticCache::enablePreview()`. |

## Cache policy defaults

| Key | Default | |
|---|---|---|
| `defaults.revalidate` | `3600` | Edge TTL (seconds). |
| `defaults.expire` | `31536000` | How long a page may be served stale while it is regenerated: `stale-while-revalidate = expire - revalidate`. |
| `defaults.stale_while_revalidate` | `null` | Explicit value; overrides `expire`. |
| `defaults.stale_if_error` | `604800` | |
| `defaults.browser_cache_control` | `public, max-age=0, must-revalidate` | Browsers always revalidate (cheap 304s). |
| `defaults.not_found_ttl` | `60` | TTL of cached 404s. |
| `defaults.cache_redirects` | `false` | Cache redirects returned by your code (canonical redirects are always cached). |
| `defaults.redirect_ttl` | `300` | TTL of cached redirects. |

## Normalization

| Key | Default | |
|---|---|---|
| `normalization.enabled` | `true` | Redirect non-canonical URLs. |
| `normalization.trailing_slash` | `never` | `never`, `always`, `ignore`. |
| `normalization.strip_query` | `utm_*`, `fbclid`, `gclid`, `gbraid`, `wbraid`, `msclkid`, `mc_cid`, `mc_eid`, `_ga`, `ref` | Always removed, even when whitelisted (wildcards allowed). |

## CDN

| Key | Env | Default |
|---|---|---|
| `cdn` | `INERTIA_STATIC_CDN` | `null` |
| `drivers.cloudflare.zone_id` / `api_token` | `CLOUDFLARE_ZONE_ID` / `CLOUDFLARE_API_TOKEN` | — |
| `drivers.cloudflare.batch_size` | `CLOUDFLARE_PURGE_BATCH_SIZE` | `100` |
| `drivers.cloudflare.hosts` | — | the `base_url` host (hostnames purged by "purge all") |
| `drivers.cloudflare.purge_everything` | `CLOUDFLARE_PURGE_EVERYTHING` | `false` (purge the whole zone on "purge all") |
| `drivers.fastly.service_id` / `api_token` | `FASTLY_SERVICE_ID` / `FASTLY_API_TOKEN` | — |
| `drivers.bunny.pull_zone_id` / `api_key` | `BUNNY_PULL_ZONE_ID` / `BUNNY_API_KEY` | — |
| `drivers.bunny.native_tags` | — | `false` (tag purges go through the tag registry) |
| `drivers.cloudfront.distribution_id` | `CLOUDFRONT_DISTRIBUTION_ID` | — |
| `drivers.cloudfront.key` / `secret` / `token` | `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_SESSION_TOKEN` | — |
| `drivers.cloudfront.stale` | `CLOUDFRONT_STALE` | `false` |
| `drivers.varnish.hosts` | `VARNISH_HOSTS` (comma separated) | `http://127.0.0.1:6081` |
| `drivers.varnish.tag_header` | — | `xkey` |
| `drivers.nginx.hosts` | `NGINX_CACHE_HOSTS` (comma separated) | `http://127.0.0.1` |
| `drivers.nginx.purge_path` | — | `/purge` |
| `drivers.multi.drivers` | — | `['varnish', 'cloudflare']` |

Drivers: `null`, `cloudflare`, `fastly`, `bunny`, `cloudfront`, `varnish`, `nginx`, `multi`, or your own
(`StaticCache::extend()`). Every driver also accepts `timeout` (default `15`) and `connect_timeout` (default `5`).

## Tag registry

| Key | Env | Default | |
|---|---|---|---|
| `registry.driver` | `INERTIA_STATIC_REGISTRY` | `null` | `database`, `cache` or `null` (disabled). |
| `registry.table` | — | `inertia_static_tags` | |
| `registry.connection` | — | default | Database connection. |
| `registry.store` | — | default | Cache store (e.g. `redis`). |
| `registry.max_urls_per_tag` | `INERTIA_STATIC_REGISTRY_MAX_URLS` | `1000` | URLs kept per tag. Past it, a tag purge on a CDN without tag purges purges the whole cache. `null`: no limit. |

## Purging

| Key | Env | Default | |
|---|---|---|---|
| `purge.queue` | `INERTIA_STATIC_PURGE_QUEUE` | default | |
| `purge.connection` | `INERTIA_STATIC_PURGE_CONNECTION` | default | |
| `purge.debounce` | `INERTIA_STATIC_PURGE_DEBOUNCE` | `5` | Delay and uniqueness window (seconds). |
| `purge.mode` | `INERTIA_STATIC_PURGE_MODE` | `soft` | `soft` (mark stale) or `hard` (remove now). CDNs without soft purges always purge hard. |
| `purge.tries` | — | `5` | Max exceptions before the job fails. |
| `purge.backoff` | — | `[5, 30, 120]` | |
| `purge.rate_limit_per_minute` | `INERTIA_STATIC_PURGE_RATE_LIMIT` | `60` | Cloudflare Free: `5`. |
| `purge.warm_after_purge` | — | `false` | Re-request purged URLs. |
| `purge.warm_url` | `INERTIA_STATIC_WARM_URL` | `null` | Address used to reach the CDN for warmups (the public `Host` is kept). |
