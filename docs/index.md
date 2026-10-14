---
layout: home

hero:
  name: Inertia Static
  text: SSG + ISR for Laravel + Inertia
  tagline: Serve your Inertia pages from any CDN with on-demand revalidation, without leaking a single session.
  actions:
    - theme: brand
      text: Get started
      link: /guide/introduction
    - theme: alt
      text: Quick start
      link: /guide/quick-start
    - theme: alt
      text: GitHub
      link: https://github.com/fydellys/inertia-static

features:
  - title: Static, ISR or dynamic, per route
    details: "Route::get(...)->isr(revalidate: 3600) or #[StaticPage]. Pages render once at the origin (with Inertia SSR) and are served by the CDN."
  - title: CDN-agnostic
    details: Cloudflare, Fastly, Bunny, Varnish, nginx, or any HTTP cache. Purge by tag, URL, prefix or everything, through queued jobs with batching and rate limits.
  - title: Fail-safe privacy
    details: Session pipeline removed, cookies dropped, shared props whitelisted, Set-Cookie stripped, and a leak detector that catches auth()->user() during a static render.
  - title: SPA navigation stays intact
    details: A tiny client plugin sends page visits to a cacheable, versioned JSON URL. This avoids the cache poisoning (and inertia#1923) of HTML and JSON sharing one URL.
  - title: Time-based and on-demand revalidation
    details: Time-based stale-while-revalidate, on-demand purges with StaticCache::purgeTags(), and automatic purges when your models change (HasStaticTags).
  - title: No token required to start
    details: Headers-only mode works without any CDN API token. Add the token later to enable purges.
---
