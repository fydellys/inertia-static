# Configuration reference

File: `config/inertia-static.php` (`php artisan vendor:publish --tag=inertia-static-config`).

## General

| Key | Env | Default | Notes |
|---|---|---|---|
| `enabled` | `INERTIA_STATIC_ENABLED` | `true` | Kill switch: every route dynamic, queued purges skipped. |
| `base_url` | `INERTIA_STATIC_BASE_URL` | `APP_URL` | Origin of every absolute URL (purge, warmup, canonical redirects). |
| `inertia_middleware` | — | `App\Http\Middleware\HandleInertiaRequests` | Resolves the asset version outside HTTP requests. |
| `json.prefix` | — | `__inertia` | Prefix of page JSON, live, manifest and CSRF URLs. |
| `json.index` | — | `index` | JSON name of `/`; client option `index` must match. |
| `json.live_middleware` | — | `EncryptCookies`, `StartSession` | Middleware of the live URL (errors and flash for forms). |
| `strip_middleware` | — | session, cookie and CSRF middleware | Removed from static routes. |
| `public_shared_props` | — | `[]` | Shared props kept on static routes. |
| `cache_header` | `INERTIA_STATIC_CACHE_HEADER` | `X-Inertia-Static-Cache` | `cacheable` / `bypass`; empty disables. |
| `debug_header` | `INERTIA_STATIC_DEBUG_HEADER` | `X-Inertia-Static` | `MISS` + `{header}-Reason`; empty disables both. |
| `implicit_tags` | — | `true` | Path prefix tags `_IST_/`, `_IST_/blog/`, … |

## Leak detector

| Key | Env | Default | Notes |
|---|---|---|---|
| `leak_detector.mode` | `INERTIA_STATIC_LEAK_DETECTOR` | `null` | `throw`, `auto`, `log`; `null` = `throw` in `throw_in` envs, `auto` elsewhere. |
| `leak_detector.store` | `INERTIA_STATIC_DOWNGRADES_STORE` | default cache store | Where `auto` remembers downgraded routes. |
| `leak_detector.throw_in` | — | `['local', 'testing']` | |
| `leak_detector.log_channel` | `INERTIA_STATIC_LOG_CHANNEL` | default channel | |

## SSR, CSRF, deferred props

| Key | Env | Default | Notes |
|---|---|---|---|
| `ssr.required` | `INERTIA_STATIC_SSR_REQUIRED` | `false` | `true`: with Inertia SSR enabled, HTML rendered without SSR is sent `no-store` instead of cached. |
| `csrf_cookie` | `INERTIA_STATIC_CSRF_COOKIE` | `null` | `true`: client fetches the cookie from `/__inertia/csrf` before the first form. `null`: only on Laravel 12. |
| `deferred_props` | `INERTIA_STATIC_DEFERRED_PROPS` | `throw` | `throw` or `allow`. |

## Preview

| Key | Default |
|---|---|
| `preview.cookie` | `inertia_static_preview` |
| `preview.minutes` | `60` |

## Cache policy defaults

| Key | Default | Notes |
|---|---|---|
| `defaults.revalidate` | `3600` | Edge TTL. |
| `defaults.expire` | `31536000` | SWR = `expire - revalidate`. |
| `defaults.stale_while_revalidate` | `null` | Overrides `expire`. |
| `defaults.stale_if_error` | `604800` | |
| `defaults.browser_cache_control` | `public, max-age=0, must-revalidate` | Browsers always revalidate (304). |
| `defaults.not_found_ttl` | `60` | Cached 404s. |
| `defaults.cache_redirects` | `false` | Cache redirects returned by your code. |
| `defaults.redirect_ttl` | `300` | |

Precedence for each value: response macro, then route option / attribute, then `defaults`.

## Normalization

| Key | Default | Notes |
|---|---|---|
| `normalization.enabled` | `true` | 301 to the canonical URL. |
| `normalization.trailing_slash` | `never` | `never`, `always`, `ignore`; client `trailingSlash` must match. |
| `normalization.strip_query` | `utm_*`, `fbclid`, `gclid`, `gbraid`, `wbraid`, `msclkid`, `mc_cid`, `mc_eid`, `_ga`, `ref` | Always removed, even when whitelisted. |

## CDN

| Key | Env | Default |
|---|---|---|
| `cdn` | `INERTIA_STATIC_CDN` | `null` |
| `drivers.cloudflare.zone_id` / `api_token` | `CLOUDFLARE_ZONE_ID` / `CLOUDFLARE_API_TOKEN` | — |
| `drivers.cloudflare.batch_size` | `CLOUDFLARE_PURGE_BATCH_SIZE` | `100` |
| `drivers.cloudflare.hosts` | — | host of `base_url` (hostnames purged by purge all) |
| `drivers.cloudflare.purge_everything` | `CLOUDFLARE_PURGE_EVERYTHING` | `false` |
| `drivers.fastly.service_id` / `api_token` | `FASTLY_SERVICE_ID` / `FASTLY_API_TOKEN` | — |
| `drivers.bunny.pull_zone_id` / `api_key` | `BUNNY_PULL_ZONE_ID` / `BUNNY_API_KEY` | — |
| `drivers.bunny.native_tags` | — | `false` |
| `drivers.cloudfront.distribution_id` | `CLOUDFRONT_DISTRIBUTION_ID` | — |
| `drivers.cloudfront.key` / `secret` / `token` | `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_SESSION_TOKEN` | — |
| `drivers.cloudfront.stale` | `CLOUDFRONT_STALE` | `false` |
| `drivers.varnish.hosts` | `VARNISH_HOSTS` (comma separated) | `http://127.0.0.1:6081` |
| `drivers.varnish.tag_header` | — | `xkey` |
| `drivers.nginx.hosts` | `NGINX_CACHE_HOSTS` (comma separated) | `http://127.0.0.1` |
| `drivers.nginx.purge_path` | — | `/purge` |
| `drivers.multi.drivers` | — | `['varnish', 'cloudflare']` |

Every driver also accepts `timeout` (15) and `connect_timeout` (5).

## Tag registry

| Key | Env | Default | Notes |
|---|---|---|---|
| `registry.driver` | `INERTIA_STATIC_REGISTRY` | `null` | `database`, `cache` or `null`. |
| `registry.table` | — | `inertia_static_tags` | Publish with `--tag=inertia-static-migrations`. |
| `registry.connection` | — | default | |
| `registry.store` | — | default | e.g. `redis`. |
| `registry.max_urls_per_tag` | `INERTIA_STATIC_REGISTRY_MAX_URLS` | `1000` | Past it, a tag purge on a CDN without tag purges purges everything. `null`: no limit. |

## Purging

| Key | Env | Default | Notes |
|---|---|---|---|
| `purge.queue` | `INERTIA_STATIC_PURGE_QUEUE` | default | |
| `purge.connection` | `INERTIA_STATIC_PURGE_CONNECTION` | default | |
| `purge.debounce` | `INERTIA_STATIC_PURGE_DEBOUNCE` | `5` | Delay and uniqueness window (s). |
| `purge.mode` | `INERTIA_STATIC_PURGE_MODE` | `soft` | `soft` or `hard`. |
| `purge.tries` | — | `5` | |
| `purge.backoff` | — | `[5, 30, 120]` | |
| `purge.rate_limit_per_minute` | `INERTIA_STATIC_PURGE_RATE_LIMIT` | `60` | Cloudflare Free: `5`. |
| `purge.warm_after_purge` | — | `false` | Re-request purged URLs. |
| `purge.warm_url` | `INERTIA_STATIC_WARM_URL` | `null` | Internal address for warmups (public `Host` kept). |

## PHP API summary

- Facade `Fydellys\InertiaStatic\Facades\StaticCache`: `purgeTags`, `purgeUrls`, `purgePrefix`, `purgeAll`,
  `purgeNow`, `canPurge`, `enablePreview`, `disablePreview`, `isPreview`, `driver`, `extend`.
- Enums (`Fydellys\InertiaStatic\Enums`): `RenderMode` (`Dynamic`, `Isr`, `Static`), `PurgeMode` (`Soft`, `Hard`),
  `PurgeOperation` (`Urls`, `Tags`, `Prefixes`, `All`), `Capability`.
- Events: `Events\StaticCachePurged(string $operation, array $items, string $driver)`,
  `Events\StaticRenderViolated(StaticRenderViolation $violation)`.
- Exceptions: `StaticRenderViolation` (leak in `throw` mode), `InvalidStaticRoute` (bad route declaration,
  deferred props in `throw` mode), `MissingCredentials` (purging now without credentials), `UnsupportedOperation`.
- Jobs: `Jobs\PurgeStaticCache` (unique, debounced, rate limited), `Jobs\WarmStaticCache`.
