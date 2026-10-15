# Introduction

**Inertia Static** brings SSG/ISR to Laravel + Inertia.js **without depending on a specific provider**. Your pages are rendered at the origin (with the official Inertia SSR), cached at the edge
of any CDN, revalidated by time or on demand, and navigated as a normal Inertia SPA.

It is **one Composer package**, `fydellys/inertia-static`:

| Part | What |
|---|---|
| Laravel (PHP) | Route modes, session stripping, cache headers, ETag/304, page JSON endpoint, CDN drivers, purge queue, Artisan commands |
| Client (JavaScript, shipped in `vendor/fydellys/inertia-static/js`) | Plugin for Inertia's HTTP client (Vue) and a Vite plugin. No npm package to install |

## Render modes

| Mode | Behaviour |
|---|---|
| `dynamic` | Default. Plain Inertia, never cached. |
| `isr` | Rendered at the origin with SSR, cached by the CDN, revalidated by time (`revalidate`) and/or on demand (tags, URLs, prefixes). |
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

