# How it works

```
                 ┌──────────── global middleware: ApplyStaticCache ────────────────┐
request ─► CDN ─►│ 1. match the route → RouteOptions (stored in the route action)  │
                 │ 2. drop request cookies; Authorization ⇒ BYPASS                 │
                 │ 3. canonical URL (query whitelist, trailing slash) ⇒ 301        │
                 │   ┌── web group WITHOUT session / cookies / CSRF ─────────────┐ │
                 │   │ HandleInertiaRequests::share()                            │ │
                 │   │  └─ RenderStatically (route middleware)                   │ │
                 │   │      • shared props ⇒ public whitelist                    │ │
                 │   │      • leak detector armed (auth, session, cookie jar…)   │ │
                 │   │      • controller → Inertia::render → SSR                 │ │
                 │   └───────────────────────────────────────────────────────────┘ │
                 │ 4. PolicyResolver (fail-safe) → CachePolicy                     │
                 │ 5. ResponseFinalizer: strip Set-Cookie, driver headers, ETag,   │
                 │    304, X-Inertia-Static-Cache, debug header                    │
                 │ 6. TagRegistry::record(tags, page)                              │
                 └─────────────────────────────────────────────────────────────────┘
```

## The page JSON endpoint

Inertia answers HTML and JSON on the **same URL**, varying on `X-Inertia`. Most CDNs ignore `Vary` for arbitrary
headers. The result is cache poisoning: a visitor gets the JSON of a page instead of its HTML, or the reverse
([inertia#1923](https://github.com/inertiajs/inertia/issues/1923)).

So the JSON of static pages lives on its own URL:

```
GET /__inertia/{assetVersion}/{path}.json[?whitelisted-query]   # cacheable
GET /__inertia/{assetVersion}/index.json                        # the root page
GET /__inertia/live/{assetVersion}/{path}.json[?query]          # never cached
```

The **live** endpoint serves visits that need fresh data: `router.reload()`, polling, partial reloads,
deferred props and non-canonical queries. It forwards the Inertia partial reload headers and always answers
`Cache-Control: no-store, private` (`X-Inertia-Static-Reason: live`). Together they guarantee that an
Inertia visit never reaches the HTML URL of a static page, where a CDN ignoring `Vary` would answer with the cached
HTML.

Because it is never cached, the live endpoint is the only static-page URL that reads the **session**
(`json.live_middleware`: cookies + `StartSession`). It returns this visitor's validation `errors` and Inertia `flash`
data, which is how `return back()` from a form on a static page works. Redirects of Inertia visits to static pages
are rewritten to it by the `RewriteInertiaRedirects` global middleware.

- Only `isr` and `static` routes answer there; dynamic routes give **404**, so private pages can never be
  exposed on a cacheable URL.
- The endpoint dispatches an internal sub-request to the page route with Inertia headers. The JSON is therefore
  exactly what Inertia would have produced (same middleware, same shared-props whitelist).
- The asset version is part of the URL, so every deploy gets fresh URLs. A request with an old version is
  answered by Inertia with `409` + `X-Inertia-Location` (never cached).
- `Vary: X-Inertia` is removed from these responses: the URL already identifies the representation, and Vary-aware
  caches would otherwise store duplicates.

The client plugin rewrites visits to this URL ([Client](../guide/client-vue.md)), using the manifest
(`/__inertia/manifest.json` or `inertia-static:manifest`) to know which routes are static.

## Fail-safe cache policy

When in doubt, `no-store`. A response is cacheable only if **none** of these holds:

| Condition | `X-Inertia-Static-Reason` |
|---|---|
| method is not GET/HEAD | `method` |
| request has `Authorization` | `authorization` |
| the leak detector fired | `leak:<type>` |
| status ≥ 500 | `server-error` |
| redirect (non canonical) without `cache_redirects` | `redirect` |
| status other than 200 / 404 / canonical 301 | `status:<code>` |
| partial reload / deferred props / reset | `partial-reload` |
| Inertia JSON requested on the HTML URL | `inertia-json-on-html-url` |
| SSR enabled but the page rendered without it (`ssr.required`) | `ssr-unavailable` |

Non-cacheable responses get `Cache-Control: no-store, private`, no CDN headers, and
`X-Inertia-Static-Cache: bypass`.

## ETag and 304

Every cacheable `200` gets a **strong** ETag (`xxh128` of the final body). `If-None-Match` is answered with an
empty `304`, so the CDN revalidates cheaply. HEAD is handled like GET.

## Request lifecycle state

Per-request state (`StaticRequestContext`, the leak detector, the CDN manager) is bound as **scoped**: it is reset
for every request and queued job, so the package is safe under Octane and in queue workers. There are no
singletons.
