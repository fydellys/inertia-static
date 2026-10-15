# Changelog

All notable changes to this project follow [Semantic Versioning](https://semver.org).

## [Unreleased]

### Added
- Inertia 2 support: `inertiajs/inertia-laravel` ^2.0.16, and `installInertiaStatic(axios, { manifest })` from
  `@fydellys/inertia-static/inertia2` for `@inertiajs/vue3` 2.x.
- CloudFront driver: `s-maxage` headers, purges through invalidations (signed with AWS SigV4, no SDK needed).
- The built client ships inside the Composer package (`js/dist`), plus a Vite plugin
  (`vendor/fydellys/inertia-static/js/vite.js`): alias, dedupe of `@inertiajs/core` / `vue`, and automatic
  `inertia-static:manifest`. No npm install needed.
- Documentation site (VitePress) published to GitHub Pages (`docs/`, `.github/workflows/docs.yml`).
- Opt-in live tests against the real Cloudflare API (`tests/Live`, `INERTIA_STATIC_LIVE=1`).
- `INERTIA_STATIC_PURGE_RATE_LIMIT` and `INERTIA_STATIC_PURGE_DEBOUNCE` env variables.
- `X-Inertia-Static-Cache: cacheable|bypass` header (configurable) to build CDN rules on.
- "Headers only" mode: CDN credentials are only needed to purge; without them purges are skipped with a notice,
  `inertia-static:purge` refuses and `doctor` reports the mode. `StaticCache::canPurge()`.
- `purge.warm_url` / `INERTIA_STATIC_WARM_URL`: warm through an internal CDN address keeping the public Host.
- Purge all falls back to a `/` prefix purge on drivers without purge all (nginx).

### Changed
- SSR is optional: apps without Inertia SSR get their HTML cached. `ssr.required` only applies when SSR is enabled
  (a page rendered while the SSR server is down is sent with `no-store`).
- Varnish: edge TTL and stale time moved to `Surrogate-Control` (read and removed by the VCL); browsers only get
  `max-age=0, must-revalidate`. Update your VCL from `docs/varnish.vcl`.
- The JSON endpoint controller resolves its request context per call: the router keeps controller instances
  between requests (Octane), which could leak the previous request's state.
- The cacheable page JSON endpoint redirects (`301`, cacheable) non-canonical queries to the canonical JSON URL,
  like the HTML URL. One cache entry per page.
- Cloudflare "purge all" purges only the app's hostname (`{"hosts": [...]}`) by default; the whole zone
  (`purge_everything`) requires `CLOUDFLARE_PURGE_EVERYTHING=true`. Zones are often shared by several sites.

### Fixed (found by the playwright-cli E2E and live tests)
- Forms on static pages: `return back()` (or any redirect of an Inertia visit) to a static page was followed by the
  XHR to the HTML URL. Cloudflare answered with the cached HTML (inertia#1923 modal), and validation errors were lost.
  `RewriteInertiaRedirects` now sends such redirects to the live JSON URL, which reads the session
  (`json.live_middleware`) to return the visitor's validation errors and `Inertia::flash()` data.
- Varnish / nginx drivers: the `Host` of `PURGE` / `X-Ban-Host` dropped non-default ports, so purges silently
  missed (Varnish hashes the full Host). Found by running the VCL against a real Varnish 7.7.
- **inertia#1923 on Cloudflare**: a visit to the current static page was sent to the page's HTML URL; Cloudflare
  ignores `Vary: X-Inertia` and answered with the cached HTML, so Inertia showed the invalid-response modal. Clicking
  the link of the current page now uses the cached JSON URL; `router.reload()` / polling (`Cache-Control: no-cache`),
  partial reloads and deferred props go to the new never-cached endpoint `/__inertia/live/{version}/{path}.json`,
  which forwards the partial reload headers.
- Cloudflare driver: a `200` answer with `"success": false` now fails the purge.
- nginx driver: `ngx_cache_purge` answers 404/412 for uncached keys; treated as success instead of failing the whole purge.
- Inertia DevTools is disabled on static routes (its per-request id was stamped into cached HTML).
- The page JSON endpoint no longer sends `Vary: X-Inertia`; it split the cache of Vary-aware proxies (nginx, Varnish, Fastly).
- Per-request `X-Inertia-Devtools-*` headers are stripped from cacheable responses.
- `useStaticIsland()` reloads after non-GET visits (logout redirecting to the same page left a stale user).

### Added
- Route modes `dynamic` / `isr` / `static` (route macros and `#[StaticPage]` attribute).
- Automatic session stripping, public shared props whitelist, cookie removal and `Set-Cookie` stripping.
- Leak detector (`StaticRenderViolation`) for session, auth, cookie jar and auth headers.
- Page JSON endpoint `/__inertia/{version}/{path}.json` and client manifest.
- Fail-safe cache policy, strong ETag / 304, `X-Inertia-Static` debug header, SSR-failure `no-store`.
- URL normalization (query whitelist, trailing slash, base-URL-only absolute URLs).
- CDN drivers: null, cloudflare, fastly, bunny, varnish, nginx, multi (+ `StaticCache::extend`).
- Tag registry (database, cache), queued purges with debounce, rate limit and backoff; `HasStaticTags`.
- Commands: `inertia-static:routes`, `manifest`, `purge`, `warm`, `doctor`.
- npm package `@fydellys/inertia-static`: `StaticHttpClient` decorating Inertia 3's HTTP client
  (rewrite to the JSON URL, header injection, missing-JSON fallback, lazy manifest), `ensureCsrfCookie()`.
- E2E behind a real Cloudflare zone through a Cloudflare Tunnel (`e2e-cloudflare.sh`, 51 checks).
- Demo app + E2E (`examples/`): nginx "CDN" stripping `X-Inertia` (inertia#1923), network panel, playwright-cli on Chromium and Firefox.
- Vue adapter `@fydellys/inertia-static/vue`: `inertiaStatic()` and the `useStaticIsland()` composable.
