# Changelog

All notable changes to this project follow [Semantic Versioning](https://semver.org). While the version is `0.x`,
minor versions may contain breaking changes.

## [Unreleased]

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

### Tooling
- Artisan commands: `inertia-static:routes`, `manifest`, `purge`, `warm`, `doctor`.
- Supports PHP 8.3+, Laravel 12 and 13, `inertiajs/inertia-laravel` ^2.0.16 or ^3.0.

[Unreleased]: https://github.com/fydellys/inertia-static/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/fydellys/inertia-static/releases/tag/v0.1.0
