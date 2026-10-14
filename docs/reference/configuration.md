# Configuration

`config/inertia-static.php` (publish with `php artisan vendor:publish --tag=inertia-static-config`).

## General

| Key | Env | Default | |
|---|---|---|---|
| `enabled` | `INERTIA_STATIC_ENABLED` | `true` | Kill switch: every route behaves as dynamic. |
| `base_url` | `INERTIA_STATIC_BASE_URL` | `APP_URL` | Origin of every absolute URL (purge, warmup, redirects). |
| `inertia_middleware` | — | `App\Http\Middleware\HandleInertiaRequests` | Used to resolve the asset version outside HTTP requests. |
| `json.prefix` | — | `__inertia` | Prefix of the page JSON endpoint. |
| `json.index` | — | `index` | JSON name of the root page. |
| `json.live_middleware` | — | `EncryptCookies`, `StartSession` | Middleware of the never-cached live endpoint, so it can return validation errors / Inertia flash to forms submitted from static pages. |
| `strip_middleware` | — | session, cookies, CSRF | Removed from static routes. Classes that do not exist are ignored. |
| `public_shared_props` | — | `[]` | Shared props kept on static routes. |
| `cache_header` | `INERTIA_STATIC_CACHE_HEADER` | `X-Inertia-Static-Cache` | `cacheable` / `bypass` header for CDN rules; empty disables it. |
| `debug_header` | `INERTIA_STATIC_DEBUG_HEADER` | `X-Inertia-Static` | `MISS; ttl=…` / `BYPASS; reason=…`; empty disables it. |

## Leak detector

| Key | Env | Default | |
|---|---|---|---|
| `leak_detector.mode` | `INERTIA_STATIC_LEAK_DETECTOR` | `null` | `throw` or `log`; `null` = throw in `throw_in` environments, log elsewhere. |
| `leak_detector.throw_in` | — | `['local', 'testing']` | |
| `leak_detector.log_channel` | `INERTIA_STATIC_LOG_CHANNEL` | default channel | |

## SSR & props

| Key | Env | Default | |
|---|---|---|---|
| `ssr.required` | `INERTIA_STATIC_SSR_REQUIRED` | `true` | HTML without SSR is sent with `no-store`. |
| `deferred_props` | `INERTIA_STATIC_DEFERRED_PROPS` | `throw` | `throw` or `allow` deferred props on static pages. |

## Cache policy defaults

| Key | Default | |
|---|---|---|
| `defaults.revalidate` | `3600` | Edge TTL (seconds). |
| `defaults.stale_while_revalidate` | `86400` | |
| `defaults.stale_if_error` | `604800` | |
| `defaults.browser_cache_control` | `public, max-age=0, must-revalidate` | Browsers always revalidate (cheap 304s). |
| `defaults.not_found_ttl` | `60` | TTL of cached 404s. |
| `defaults.cache_redirects` | `false` | Cache non-canonical redirects. |
| `defaults.redirect_ttl` | `300` | TTL of cached redirects. |

## Normalization

| Key | Default | |
|---|---|---|
| `normalization.enabled` | `true` | |
| `normalization.trailing_slash` | `never` | `never`, `always`, `ignore`. |
| `normalization.strip_query` | `utm_*`, `fbclid`, `gclid`, … | Always removed (wildcards allowed). |

## CDN

| Key | Env | Default |
|---|---|---|
| `cdn` | `INERTIA_STATIC_CDN` | `null` |
| `drivers.cloudflare.zone_id` / `api_token` / `batch_size` | `CLOUDFLARE_ZONE_ID` / `CLOUDFLARE_API_TOKEN` / `CLOUDFLARE_PURGE_BATCH_SIZE` | — / — / `100` |
| `drivers.fastly.service_id` / `api_token` / `soft_purge` | `FASTLY_SERVICE_ID` / `FASTLY_API_TOKEN` | — / — / `true` |
| `drivers.bunny.pull_zone_id` / `api_key` / `native_tags` | `BUNNY_PULL_ZONE_ID` / `BUNNY_API_KEY` | — / — / `false` |
| `drivers.varnish.hosts` / `tag_header` | `VARNISH_HOSTS` (comma separated) | `http://127.0.0.1:6081` / `xkey` |
| `drivers.nginx.hosts` / `purge_path` | `NGINX_CACHE_HOSTS` (comma separated) | `http://127.0.0.1` / `/purge` |
| `drivers.multi.drivers` | — | `['varnish', 'cloudflare']` |

Every driver also accepts `timeout` (15) and `connect_timeout` (5).

## Tag registry

| Key | Env | Default | |
|---|---|---|---|
| `registry.driver` | `INERTIA_STATIC_REGISTRY` | `null` | `database`, `cache` or `null` (disabled). |
| `registry.table` | — | `inertia_static_tags` | |
| `registry.connection` | — | default | Database connection. |
| `registry.store` | — | default | Cache store (e.g. `redis`). |

## Purging

| Key | Env | Default | |
|---|---|---|---|
| `purge.queue` | `INERTIA_STATIC_PURGE_QUEUE` | default | |
| `purge.connection` | `INERTIA_STATIC_PURGE_CONNECTION` | default | |
| `purge.debounce` | `INERTIA_STATIC_PURGE_DEBOUNCE` | `5` | Delay and uniqueness window (seconds). |
| `purge.tries` | — | `5` | Max exceptions before failing. |
| `purge.backoff` | — | `[5, 30, 120]` | |
| `purge.rate_limit_per_minute` | `INERTIA_STATIC_PURGE_RATE_LIMIT` | `60` | Cloudflare Free: `5`. |
| `purge.warm_after_purge` | — | `false` | Re-request purged URLs. |
| `purge.warm_url` | `INERTIA_STATIC_WARM_URL` | `null` | Internal address of the CDN for warmups (public `Host` kept). |
