# Debugging and troubleshooting

## Check a response

```bash
curl -sI https://example.com/blog/hello | grep -i -E 'x-inertia-static|cache-control'
php artisan inertia-static:routes    # effective mode, TTLs, tags, paths provider, downgrades
php artisan inertia-static:doctor    # setup checks; non-zero exit on failure
```

| Header | Meaning |
|---|---|
| `X-Inertia-Static: MISS` | the origin rendered a cacheable response (replayed by the CDN) |
| `X-Inertia-Static-Cache: cacheable` | a shared cache may store it |
| `X-Inertia-Static-Cache: bypass` | never store it; see `X-Inertia-Static-Reason` |
| no `X-Inertia-Static-Cache` | not a static route |
| `CF-Cache-Status`, `X-Cache`, `X-Cache-Status`, `CDN-Cache` | the CDN's own HIT/MISS |

Non-cacheable responses of static routes get `Cache-Control: no-store, private` and no CDN headers.

## `X-Inertia-Static-Reason` values

| Value | Cause | Fix |
|---|---|---|
| `authorization` | request has an `Authorization` header | expected; do not send it to static pages |
| `preview` | valid preview cookie | expected while previewing |
| `live` | the never-cached live JSON URL | expected (reloads, partial reloads, forms) |
| `leak:auth`, `leak:session`, `leak:cookie`, `leak:header` | the render read private data (`log`/`auto` mode) | move the read to an island or make the route dynamic |
| `server-error` | status >= 500 | fix the error; the CDN keeps serving stale (`staleIfError`) |
| `partial-reload` | partial reload, deferred props or `X-Inertia-Reset` | expected |
| `inertia-json-on-html-url` | an Inertia visit (`X-Inertia`) hit the HTML URL | install the client (`withInertiaStatic`) and rebuild the manifest |
| `redirect` | redirect from your code (`defaults.cache_redirects` off), or any redirect on the page JSON URL | expected, or enable `defaults.cache_redirects` |
| `status:404-json` | a 404 asked as JSON on the HTML URL | expected |
| `status:<code>` | any other status (e.g. `status:409` after a deploy) | expected for version mismatch |
| `ssr-unavailable` | `INERTIA_STATIC_SSR_REQUIRED=true`, SSR enabled but HTML rendered without it | start the SSR server (`php artisan inertia:check-ssr`), disable SSR, or `INERTIA_STATIC_SSR_REQUIRED=false` |
| `not-static` | page JSON URL of an unknown or dynamic route (404) | declare the route `isr`/`static`, rebuild the manifest |

## Symptom table

| Symptom | Cause | Fix |
|---|---|---|
| `StaticRenderViolation` in local/tests | the static render (controller, shared prop, view composer, component, root view) reads user/session/cookie/auth header; message names the accessor | load that data with `useStaticIsland` / `<StaticIsland>` from a dynamic route, or `->dynamic()` |
| Route shows `dynamic (downgraded: …)` | `auto` leak detector turned it dynamic in production | fix the leak, deploy, `php artisan inertia-static:routes --reset-downgrades`, `php artisan octane:reload` (Octane), restart queue workers |
| `InvalidStaticRoute` at boot | route not GET-only, negative `revalidate`, missing `paths` class, or parameterized `static` route without `paths` | use `Route::get`, fix the option, add a `StaticPathsProvider` or use `isr` |
| `InvalidStaticRoute` about deferred props | static page uses `Inertia::defer()` with `deferred_props = throw` | `INERTIA_STATIC_DEFERRED_PROPS=allow`, or resolve the prop eagerly |
| User menu / `auth` missing on static pages | `auth` is not a public shared prop | island calling e.g. `/api/me` |
| Flash message missing on a static page | flash shared through `HandleInertiaRequests::share()` is dropped | `Inertia::flash()` |
| Island refetches on every navigation | layout rendered inside each page | persistent layout |
| Island still shows the user after logout | own fetch code without refresh, or `refreshAfterMutations: false` | use `useStaticIsland` defaults, or reload on `inertia:finish` of non-GET visits |
| `<WhenVisible>` shows no user data | it reloads the static page, which has no user | `<StaticIsland when="visible">` |
| SSR server crashes rendering `<WhenVisible>` (Vue) | old `@inertiajs/vue3` | 2.3.21+ or 3.0.1+ |
| Vue SSR renders `[object Promise]` (Inertia 2) | renderer not wrapped | `render: ssrRender(renderToString)` in `ssr.ts` |
| "All Inertia requests must receive a valid Inertia response" / modal with the page | CDN answered a visit with cached HTML | ensure `...withInertiaStatic({ manifest })` and the Vite plugin; rebuild the manifest; optional CDN bypass for `x-inertia` requests on HTML URLs |
| New static route still loads via the normal Inertia URL | stale `resources/js/inertia-static.json` | restart Vite / rebuild, or `php artisan inertia-static:manifest` |
| Cloudflare always `DYNAMIC` | Cache Rule 1 missing | create the "Eligible for cache" rule (see cdn-setup.md) |
| Purge does nothing | headers-only mode (no credentials), no queue worker, debounce delay, missing tag registry (nginx, Bunny, CloudFront), Cloudflare Free rate limit | `inertia-static:doctor`; run a worker; `INERTIA_STATIC_REGISTRY=database`; `INERTIA_STATIC_PURGE_RATE_LIMIT=5` |
| `inertia-static:purge` refuses | driver has no credentials | add credentials, or rely on TTL |
| Whole cache purged on a tag purge | tag exceeded `registry.max_urls_per_tag`, or an unresolvable prefix | finer tags, raise `INERTIA_STATIC_REGISTRY_MAX_URLS` |
| Many cache entries for one page | non-whitelisted query parameters or spellings | they 301 to the canonical URL; whitelist only meaningful params in `query` |
| One language served to everyone | page varies by cookie / `Accept-Language` | put the locale in the URL |
| Wrong host in canonical or purge URLs | `INERTIA_STATIC_BASE_URL` / `APP_URL` not the public URL | set it to the public URL |
| Preview shows cached page | CDN not bypassing the preview cookie | add the bypass rule for `inertia_static_preview` |
| Forms on static pages fail with 419 (Laravel 12) | CSRF cookie not fetched | client installed? force `INERTIA_STATIC_CSRF_COOKIE=true`; for own `fetch()` use `ensureCsrfCookie('/__inertia/csrf')` |
| Old visitors after deploy | expected | they reload once on `409`; keep old assets a few hours |
| Never see a `304` in DevTools | the browser hides it from XHR | uncheck "Disable cache"; Cloudflare: Respect strong ETags on |

## Client-side debugging

```ts
createInertiaApp({ ...withInertiaStatic({ manifest, debug: true }) /* logs every rewrite decision */ })
```

Visit routing: navigation, back/forward and prefetch use `/__inertia/{version}/{path}.json` (cached);
`router.reload()`, polling, partial reloads, deferred props, non-whitelisted query, `X-Inertia-Static-Bypass` and
preview use `/__inertia/live/{version}/{path}.json` (never cached). Non-GET requests, `useHttp()`, own
`fetch`/axios calls, GET visits with `data`, and routes not in the manifest are sent unchanged.

`onMissing`: `'reload'` (default, full page load when the JSON is missing) or `'origin'` (retry once on the
original URL; only when a PHP origin is behind the CDN).
