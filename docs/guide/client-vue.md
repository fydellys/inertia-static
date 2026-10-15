# Client

Vue and React work the same way (see [Installation](./installation.md#client) for every import).

```ts
import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // ...
})
```

`inertiaStatic()` returns an `HttpClient` that **decorates** Inertia 3's built-in XHR client (the `http` option of
`createInertiaApp`). It does not patch the router.

## Which visits are rewritten

::: danger Never the HTML URL
An Inertia visit to a static page **never** uses the page's HTML URL. CDNs that ignore `Vary: X-Inertia` would answer it with the
cached HTML, and Inertia would show the "invalid response" modal
([inertia#1923](https://github.com/inertiajs/inertia/issues/1923)).
:::

A `GET` router visit (`X-Inertia` header) to a path of the manifest goes to one of two URLs:

| Visit | URL | Cached |
|---|---|---|
| normal navigation, back/forward, **prefetch**, clicking the link of the current page | `/__inertia/{version}/{path}.json` (query whitelisted and sorted) | yes |
| `router.reload()` and **polling** (Inertia sends `Cache-Control: no-cache`) | `/__inertia/live/{version}/{path}.json` | **never** |
| partial reload, **deferred props**, `X-Inertia-Reset`, infinite scroll, `Precognition` | live (headers forwarded) | never |
| query outside the whitelist, `X-Inertia-Static-Bypass` | live (query kept) | never |

Not rewritten (sent unchanged): non-GET requests, `useHttp()` and other non-visit requests, other origins, requests
with a body, and paths that are not in the manifest (dynamic routes).

**Redirects.** When a visit (a form `POST`, a GET to a dynamic route…) is **redirected** to a static page, the
browser follows the redirect inside the XHR, out of the plugin's reach. The server therefore rewrites such
`Location` headers to the page's live JSON URL (`RewriteInertiaRedirects`). `return back()` after a form on a static
page works, including validation errors and flash data. See [Forms on static pages](./islands.md#forms-on-static-pages).

Force fresh data for a visit:

```ts
router.visit('/blog', { headers: { 'X-Inertia-Static-Bypass': '1' } })
```

## Responses

- `x-inertia: true` is injected into the response: static hosts and many CDNs do not forward it, which
  is what causes [inertia#1923](https://github.com/inertiajs/inertia/issues/1923).
- **Missing JSON** (404/403/410, or an HTML fallback page of SPA hosts) → `onMissing`:
  - `'reload'` (default): a `409` + `x-inertia-location` makes Inertia perform a full page load of the
    original URL. This works on hosts without PHP and never loops.
  - `'origin'`: the visit is retried once against the original URL.
- **Version mismatch**: the origin answers `409` + `X-Inertia-Location` and Inertia reloads with the new
  assets. A JSON URL of an old version that is still cached remains valid for old clients.

## Options

| Option | Default | |
|---|---|---|
| `manifest` | — | object written by `inertia-static:manifest` |
| `manifestUrl` | `'/__inertia/manifest.json'` | fetched once when no `manifest` is given; `false` disables it |
| `client` | `xhrHttpClient` | the client being decorated (e.g. `axiosAdapter()`, or your own) |
| `trailingSlash` | `'never'` | must match `normalization.trailing_slash` |
| `index` | `'index'` | must match `json.index` |
| `onMissing` | `'reload'` | `'reload'` or `'origin'` |
| `debug` | `false` | log every rewrite decision |

## Debugging

```ts
const http = inertiaStatic({ manifest, debug: true })
http.decide({ method: 'get', url: '/blog', headers: { 'X-Inertia': true } })
// { rewrite: true, reason: 'blog.index', url: 'https://example.com/__inertia/<v>/blog.json' }
```
