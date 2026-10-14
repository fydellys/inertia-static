# Troubleshooting / FAQ

## Cloudflare always answers `CF-Cache-Status: DYNAMIC`

Cloudflare does not cache HTML or JSON by default (it decides by file extension). Create the Cache Rule
*Eligible for cache* with *Edge TTL: use cache-control header if present* ([Cloudflare](./cdn/cloudflare.md)).
`DYNAMIC` means "not eligible at request time"; `BYPASS` means the origin headers said no.

## "All Inertia requests must receive a valid Inertia response…" / a modal shows the page

[inertia#1923](https://github.com/inertiajs/inertia/issues/1923): a cache or proxy answered an Inertia visit with
something that is not an Inertia response. Usually it is the **cached HTML**, because the CDN ignores
`Vary: X-Inertia`; sometimes it is JSON without the `X-Inertia` header.

With the client plugin, visits to static pages never use the HTML URL: they go to the cacheable JSON URL or to the
never-cached live endpoint, and `x-inertia` is injected. If you still see it, check that `createInertiaApp` uses
`http: inertiaStatic({ manifest })` and that the manifest is up to date (it is written on every Vite build).

## I never see a `304` in DevTools

The browser hides 304s from the code that made the request: the XHR sees a `200` built from its local cache. In
Chrome DevTools, uncheck **Disable cache** to see `304 Not Modified`. On Cloudflare, turn **Respect strong ETags** on
(see [Rule 1](./cdn/cloudflare.md#rule-1-cache-rule)).

Extra protection for clients without the plugin (or with a stale manifest): a Cloudflare **Cache Rule** that bypasses
the cache when the request has the header, `any(http.request.headers["x-inertia"][*] eq "true")` → *Bypass cache*.
On dynamic routes, make sure your proxies forward `X-Inertia` and honour `Vary: X-Inertia`.

## Pressing back shows raw JSON

Same root cause (HTML and JSON on one URL in the browser cache). Static pages are not affected: their JSON has its
own URL.

## Do I need a CSRF token for forms on static pages?

Not on Laravel 13 (same-origin requests are accepted through `Sec-Fetch-Site`). On Laravel 12, call
`ensureCsrfCookie()` before submitting.

## `/api/me` is called on every navigation

Your layout is rendered inside each page, so it is mounted again on every visit. Make it a persistent layout
(`defineOptions({ layout: Layout })`), and the island loads once.

## The user menu disappears on static pages

By design: `auth` is not a public shared prop, so it is dropped on static pages. Use an
[island](./guide/islands.md) (`useStaticIsland('/api/me')`).

## The island still shows the user after signing out

Fixed: islands reload after every non-GET Inertia visit. If you fetch user data yourself, reload it on the
`inertia:finish` event of non-GET visits.

## `StaticRenderViolation` in local

Your static page (or a shared prop, a view composer, a component) reads private data. The message names the exact
accessor (for example `auth()->check()`). Move it to an island, or make the route dynamic.

## My purge does nothing

- `php artisan inertia-static:doctor`: is the driver in headers-only mode (no credentials)?
- Is a queue worker running? Purges are queued with a `purge.debounce` delay.
- nginx / Bunny: enable the [tag registry](./guide/revalidation.md#tag-registry), otherwise tag purges have no URL to purge.
- Cloudflare Free: 5 tag/prefix purges per minute. Set `INERTIA_STATIC_PURGE_RATE_LIMIT=5`.

## Pages are never cached and `X-Inertia-Static-Reason: ssr-unavailable`

The SSR server is down or not configured (`php artisan inertia:check-ssr`). HTML without SSR is never cached
unless `INERTIA_STATIC_SSR_REQUIRED=false`.

## Old visitors after a deploy

They keep the old assets until the next navigation to an uncached page, which returns `409` and triggers one full
reload. Keep the previous build's assets for a few hours.
