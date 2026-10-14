# Testing & E2E

## Test suites

| Suite | Tool | Count | What |
|---|---|---|---|
| Unit + feature | Pest + Orchestra Testbench | 119 | modes, session stripping, leak detector, headers, ETag/304, JSON endpoint, normalization, drivers (`Http::fake`), queue, registry, commands |
| Client | Vitest (happy-dom) | 38 | rewrite rules, header injection, fallbacks, manifest loading, real `createInertiaApp` + `router.visit` integration, islands |
| E2E | [playwright-cli](https://github.com/microsoft/playwright-cli) + Docker | 108 checks | real browsers (Chromium, Firefox) behind a real cache |
| Live | Pest | 5 | real Cloudflare API (opt-in) |
| E2E Cloudflare | playwright-cli + Cloudflare Tunnel | 57 checks | the demo behind a real Cloudflare zone |

```bash
composer test                  # or ./bin-php vendor/bin/pest (Docker)
npm test                       # or ./bin-node npm test
```

## The demo

`examples/demo` is a Laravel 13 + Inertia 3 + Vue + SSR app behind an nginx "CDN" (`proxy_cache` +
`ngx_cache_purge`). The CDN is **hostile on purpose**: it strips `X-Inertia` from the page JSON, the trigger of
[inertia#1923](https://github.com/inertiajs/inertia/issues/1923). Each page shows a network panel with the status,
CDN status, the URL on the wire, origin hits and Inertia errors.

```bash
cd examples/demo-docker && docker compose up -d --build
./e2e.sh            # visible browser windows (WSLg / X11)
HEADED= ./e2e.sh    # headless
```

## E2E checklist

| § | Scenario |
|---|---|
| 2 | Warm CDN, new visitor: initial HTML `HIT`, 9 SPA transitions from the cache, **0 page hits at the origin**, the `/api/me` island fetched **once** (persistent layout), no Inertia error although the CDN strips `X-Inertia` |
| 3 | Signed in: static pages carry no `auth`/`flash`/`csrf`; the user only appears in the island |
| 4 | Anonymous right after: no trace of the user in HTML, props, JSON or cookies; no DevTools id |
| 5 | Sign out from a static page redirecting to the same URL refreshes the island |
| 5b | Prefetch uses the JSON URL (no request on click); polling uses the never-cached live endpoint |
| 5c | Clicking the link of the current page uses the cached JSON, never the cached HTML (inertia#1923) |
| FORMS | Static page (cached, no cookies, no CSRF token): `useForm` + `back()` (errors, `Inertia::flash`, no modal) and `useHttp` (422, JSON); dynamic page: the same with a session |
| SSR & SEO | HTML from the CDN is server-rendered; JavaScript disabled shows title, h1, description, canonical; no hydration mismatch |
| 6 | Time-based: `MISS` → `HIT` → TTL expires → `STALE` instantly → `HIT` with new content (HTML and JSON) |
| 7 | On demand: model saved → `HasStaticTags` → debounced queued purge → HTML, JSON and tagged listing purged |
| 8 | `purge --url / --tags / --prefix / --all / --warm` purge only their target (HTML + JSON) |
| 9 | `warm`: pages are a `HIT` on the first visit |
| 10 | 404 short TTL, canonical 301, `Authorization` bypass, ETag 304 without body, leak detector, SSR down |
| 10b | `X-Inertia-Static-Cache` header and headers-only mode |
| 11 | Deploy mid-session: old window gets 409, full reload, continues on the new version |
| 12 | Firefox: transitions, history, leaving and coming back show HTML (not raw JSON) |

## E2E behind a real Cloudflare zone

Expose the demo origin on port 80 through a Cloudflare Tunnel, then:

```bash
export CF_ENV_FILE=/path/outside/the/repo/cloudflare.env   # CLOUDFLARE_API_TOKEN, CLOUDFLARE_ZONE_ID
export CF_HOST=local.example.com
docker compose stop app      # a single queue worker: the Cloudflare origin's
docker compose -f compose.yml -f compose.cloudflare.yml up -d origin
./e2e-cloudflare.sh
```

It covers caching of HTML and JSON, a fresh browser served entirely by Cloudflare, privacy, time-based
revalidation (`UPDATING`), on-demand tag purges from a model save, every purge command (purge all is a hostname
purge), Firefox, and the safety-net Cache Response Rule. Every purge is scoped to `CF_HOST`, so it is safe on
a shared production zone.

## Live CDN tests

```bash
INERTIA_STATIC_LIVE=1 CLOUDFLARE_API_TOKEN=... CLOUDFLARE_ZONE_ID=... vendor/bin/pest tests/Live
```

Live tests only purge URLs, tags and prefixes that **do not exist** on the zone, and never purge everything, so
they are safe on a production zone. Keep credentials in the environment, never in the repository.
