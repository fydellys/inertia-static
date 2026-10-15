# Introduction

**Inertia Static** brings SSG/ISR to Laravel + Inertia.js **without depending on a specific provider**. Your pages are rendered at the origin, cached at the edge
of any CDN, revalidated by time or on demand, and navigated as a normal Inertia SPA.

It is based on the rendering model of [Next.js](https://nextjs.org/docs/app/guides/incremental-static-regeneration):
the same SSG and ISR ideas, with the same names (`revalidate`, tags, on-demand revalidation, preview mode), applied
to Laravel routes and served by your CDN. See [Compared to Next.js](../concepts/nextjs.md) for the mapping.

## SSG and ISR in short

**SSG (Static Site Generation).** A page is rendered once and the same HTML is served to every visitor, from a cache,
without running your controller again. It is fast and cheap, and it survives traffic spikes, because the origin is
not involved.

**ISR (Incremental Static Regeneration).** SSG that updates itself, page by page, without a new deploy:

- **By time.** After `revalidate` seconds the cached page is stale. The next visitor still gets it instantly, and the
  page is rendered again in the background (stale-while-revalidate). The following visitors get the new version.
- **On demand.** When your data changes, purge the pages that use it (by tag, URL or prefix), for example when a
  post is saved. The next visit renders a fresh page.
- **Pages that do not exist yet** are rendered on their first visit and cached from then on.

```
visit 1 ──► CDN: MISS ──► Laravel renders /blog/hello ──► cached
visit 2 ──► CDN: HIT  (no Laravel)
… revalidate seconds later …
visit 3 ──► CDN: STALE, served at once ──► background render ──► cached again
post saved ──► purge tag "post:1" ──► next visit renders it again
```

Here the cache is your CDN (Cloudflare, Fastly, CloudFront, Varnish, nginx…), and Laravel stays in charge of
everything dynamic: forms, sign in, and user data loaded in [islands](./islands.md).

It is **one Composer package**, `fydellys/inertia-static`:

| Part | What |
|---|---|
| Laravel (PHP) | Route modes, session stripping, cache headers, ETag/304, page JSON endpoint, CDN drivers, purge queue, Artisan commands |
| Client (JavaScript, shipped in `vendor/fydellys/inertia-static/js`) | Plugin for Inertia's HTTP client (Vue and React, Inertia 2 and 3) and a Vite plugin. No npm package to install |

## Render modes

| Mode | Behaviour |
|---|---|
| `dynamic` | Default. Plain Inertia, never cached. |
| `isr` | Rendered at the origin, cached by the CDN, revalidated by time (`revalidate`) and/or on demand (tags, URLs, prefixes). |
| `static` | Same as `isr`, for pages known in advance: a parameterized `static` route must list its pages, so `inertia-static:warm` can pre-render all of them on deploy. |

## Why not just set `Cache-Control`?

Because caching Inertia pages at the edge has sharp edges, and the package handles each of them:

1. **Private data in a shared cache.** Sessions, `auth` shared props, flash messages and cookies end up in cached
   HTML. The package removes the session pipeline, whitelists shared props, drops `Set-Cookie` and **detects**
   access to private data while rendering ([Security](../concepts/security.md)).
2. **One URL, two representations.** Inertia answers HTML and JSON on the same URL (varying on `X-Inertia`).
   Most CDNs ignore `Vary`, so a visitor can get JSON instead of HTML, which is
   [inertia#1923](https://github.com/inertiajs/inertia/issues/1923). The page JSON gets its own URL:
   `/__inertia/{assetVersion}/{path}.json` ([How it works](../concepts/how-it-works.md)).
3. **Degraded HTML.** When SSR is down, Inertia falls back to a client-side shell. Caching it hurts SEO for hours,
   so it is never cached.
4. **Purging is provider-specific and rate limited.** Drivers, a queue with debounce and batching, and a
   tag → URL registry for CDNs without tag purges.

## Requirements

| | Version |
|---|---|
| PHP | 8.3+ |
| Laravel | 12.x, 13.x |
| inertiajs/inertia-laravel | ^2.0.16 or ^3.0 |
| @inertiajs/vue3 or @inertiajs/react | ^2.3 or ^3.0 (Vue 3.5+, React 18+) |
| Inertia SSR | optional, supported |

