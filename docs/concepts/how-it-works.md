# How it works

A route declared `isr` or `static` is rendered at the origin like any Inertia page, but without a session, cookies
or the user, so the response is the same for every visitor. The CDN stores it and serves it until it expires or
is purged. Dynamic routes are not touched.

## Two URLs per page

Inertia answers the HTML and the JSON of a page on the same URL, varying on the `X-Inertia` header. Most CDNs
ignore that, and would answer a visit with the cached HTML (or a page load with the JSON). So every static page
has two cacheable URLs:

```
GET /blog/hello                          # HTML (first page load)
GET /__inertia/{version}/blog/hello.json # page JSON (SPA visits)
GET /__inertia/{version}/index.json      # page JSON of the root page
```

The [client plugin](../guide/client.md) sends Inertia visits to static routes to the JSON URL, using the route
manifest (`/__inertia/manifest.json`, or the file written by `inertia-static:manifest`). Only `isr` and `static`
routes answer there; any other path is a `404`.

## The live URL

Visits that need fresh data go to a URL that is never cached:

```
GET /__inertia/live/{version}/blog/hello.json
```

The client uses it for `router.reload()`, polling, partial reloads, deferred props, a query string that is not
canonical, and preview mode. It is the only URL of a static page that reads the session: after a form submitted
from a static page, `return back()` lands there and gets the visitor's validation `errors` and Inertia `flash`
data. Redirects of Inertia visits to static pages are sent to it automatically.

## Fail-safe cache policy

When in doubt, `no-store`. A response of a static route is cached only when it is a `200`, a `404` (short TTL), or
a redirect to the canonical URL. It is never cached when:

- the request has an `Authorization` header, or a valid preview cookie;
- the page read private data during the render (see the [leak detector](./security.md#leak-detector));
- the status is `5xx`, or any status other than 200 / 404 (redirects from your code only with
  `defaults.cache_redirects`);
- it is a partial reload, or an Inertia visit on the HTML URL;
- your app uses SSR and the SSR server is down (see below).

Non-cacheable responses get `Cache-Control: no-store, private` and no CDN headers. `Set-Cookie` is always removed.

Every cacheable `200` gets a strong `ETag`, so browsers and CDNs revalidate with a cheap `304`.

## After a deploy

The asset version is part of the JSON URL, so a deploy gives every page new JSON URLs. A visitor still running the
old build asks for the old version; when that JSON is not in the CDN cache, Inertia answers `409` and the browser
does a full page load of the new version.

## When the SSR server is down

If your app uses SSR and the SSR server is down, Inertia sends the HTML without its server-rendered content (it
only appears once JavaScript runs). That copy is cached like any other page. Set `ssr.required` to `true` to send it
with `no-store` instead, so the CDN keeps the last good page it has.

## Check a response

```bash
curl -sI https://example.com/blog/hello | grep -i -E 'x-inertia-static|cache-control'
```

| Header | Meaning |
|---|---|
| `X-Inertia-Static: MISS` | the origin rendered a cacheable response |
| `X-Inertia-Static-Reason: …` | not cacheable, and why (see [Headers](../reference/headers.md)) |
| `X-Inertia-Static-Cache: cacheable` / `bypass` | the stable header to build CDN rules on |

The CDN's own header (`CF-Cache-Status`, `X-Cache`…) tells whether it served a `HIT`.
