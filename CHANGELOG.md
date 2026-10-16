# Changelog

All notable changes to this project follow [Semantic Versioning](https://semver.org). While the version is `0.x`,
minor versions may contain breaking changes.

## [Unreleased]

### Added
- Load test with k6 (`examples/demo-docker/load.sh`): through the CDN, straight to the origin, and signed-in +
  anonymous visitors at the same time.
- Docs: shared props and the signed-in user (starter-kit `HandleInertiaRequests`), in Islands, forms & CSRF.

## [0.3.1] - 2026-10-16

### Fixed
- Laravel Octane: a route the leak detector turns dynamic (`auto` mode) is dynamic from the next request in the same
  worker, instead of after the next worker boot.

### Added
- Octane (FrankenPHP worker mode) in the demo and its E2E checks; deploy guide section for Octane.

## [0.3.0] - 2026-10-15

### Changed
- `ssr.required` (`INERTIA_STATIC_SSR_REQUIRED`) defaults to `false`: pages are cached with or without SSR, also
  when the SSR server is down. Set it to `true` for the previous behaviour (`no-store` when SSR is unavailable).

## [0.2.1] - 2026-10-15

### Security
- The page path of the page JSON URL is no longer decoded twice (`/__inertia/v1/%2562log.json` is not `/blog`).
- Paths are percent-encoded one canonical way on every URL, purge and registry entry (`/blog/%21` redirects to
  `/blog/!`), so the registry and the CDN keep one entry per page.
- The page JSON render keeps the client IP, so per-client rate limits (`throttle`) work on static routes.
- Static renders use the scheme of the base URL, never `X-Forwarded-Proto`.
- Inertia protocol headers (`X-Inertia-Except-Once-Props`, `X-Inertia-Infinite-Scroll-Merge-Intent`, partial
  reload headers, …) are ignored on static HTML requests, which the CDN does not key on them.
- Redirects on the page JSON URL and JSON `404` responses are never cached.
- Leak detector: `Auth::id()` read by Laravel's exception reporting (`rescue()`, `report()`) is not a leak.
- Tag registry: at most `registry.max_urls_per_tag` URLs per tag (default `1000`); past it, a tag purge on a CDN
  without tag purges purges the whole cache. A page rendered during a purge stays known, and a failed purge keeps
  the pages known for the retry. A registry failure no longer fails the page.
- Islands: a failed reload puts `data` back to `initial` (no previous user after a `401`), and an older response
  never overwrites a newer one.

### Added
- Agent skill `inertia-static-development` and guidelines for Laravel Boost (`php artisan boost:install`), also
  installable as a Claude Code plugin (`/plugin marketplace add fydellys/inertia-static`).

### Fixed
- Leak detector, `auto` mode: a leak that only happens in a partial reload (a `<WhenVisible>`, deferred or scroll
  prop reading the user) no longer turns the whole route dynamic. It is still logged and served with `no-store`;
  the cached page never runs that code.

### Changed
- GitHub Actions pinned by commit SHA, kept up to date by Dependabot.

## [0.2.0] - 2026-10-15

### Client
- `withInertiaStatic({ manifest })`: the same setup on Inertia 2 and 3,
  `createInertiaApp({ ...withInertiaStatic({ manifest }) })`. The Vite plugin detects the Inertia version installed
  (option `inertia` to force it) and resolves `@fydellys/inertia-static/vue` and `/react` to the matching client.
  `ssrRender` is also exported for Inertia 3, so `ssr.ts` is the same on both versions.
- Islands that load when they become visible, the island version of `<WhenVisible>` for user data: the
  `<StaticIsland url when="visible">` component (Vue and React) and `useStaticIsland(url, { when: 'visible', buffer })`
  with its `target`.

## [0.1.0] - 2026-10-15

First release.

### Rendering and routes
- Route modes `->isr()`, `->static()` and `->dynamic()`, or the `#[StaticPage]` attribute, with `revalidate`,
  `expire`, `staleWhileRevalidate`, `staleIfError`, `tags` and a `query` whitelist.
- Per-response overrides: `->revalidate()`, `->staleWhileRevalidate()`, `->staleIfError()`, `->expire()`, `->tags()`.
- Static paths providers for parameterized routes, used by `inertia-static:warm`.
- Works with or without Inertia SSR; a page rendered while the SSR server is down is never cached.

### Privacy
- Static routes run without session, cookies or CSRF middleware; request cookies and forwarded host headers are
  dropped before routing; `Set-Cookie` is always stripped.
- Only `public_shared_props` reach static pages; user data is loaded in the browser (`useStaticIsland`).
- Leak detector (`throw`, `auto`, `log`): reading the user, session, cookies or credentials while rendering a static
  page is reported, and the page is never cached.

### Caching
- Page JSON on its own cacheable URL (`/__inertia/{version}/{path}.json`) and a never-cached live URL for reloads,
  partial reloads, polling, forms and preview mode, so CDNs that ignore `Vary` never mix HTML and JSON.
- One canonical URL per page (query whitelist, trailing slash, percent-encoding), strong ETags and `304`s.
- `X-Inertia-Static-Cache: cacheable|bypass` header to build CDN rules on, and "headers only" mode without CDN
  credentials.
- Preview mode (`StaticCache::enablePreview()`).

### Purging
- `StaticCache::purgeTags()`, `purgeUrls()`, `purgePrefix()`, `purgeAll()` and `purgeNow()`, soft or hard, queued
  with debounce, batching and rate limiting; automatic purges from models with `HasStaticTags`.
- Drivers: Cloudflare, Fastly, Bunny, CloudFront, Varnish, nginx, `multi` and `null`, plus custom drivers.
- Tag → URL registry (database or cache) for CDNs without tag purges.

### Client
- Shipped inside the Composer package with a Vite plugin: no npm install.
- Vue and React, on Inertia 3 (`createInertiaApp({ http: inertiaStatic({ manifest }) })`) and Inertia 2
  (`installInertiaStatic(axios, { manifest })`, plus `ssrRender` for Vue SSR).
- Forms on static pages work on Laravel 12 and 13 without CSRF code.
- Inertia features on static pages: deferred props, `<WhenVisible>`, `<InfiniteScroll>`, polling, partial reloads,
  merge and once props, prefetch, `useRemember`, history encryption, `<Form>`, and on Inertia 3 instant visits and
  optimistic updates.

### Tooling
- Artisan commands: `inertia-static:routes`, `manifest`, `purge`, `warm`, `doctor`.
- Supports PHP 8.3+, Laravel 12 and 13, `inertiajs/inertia-laravel` ^2.0.16 or ^3.0.

[Unreleased]: https://github.com/fydellys/inertia-static/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/fydellys/inertia-static/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/fydellys/inertia-static/releases/tag/v0.1.0
