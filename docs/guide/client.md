# Client

Install the client in your app entry (see [Installation](./installation.md#client) for every framework and Inertia
version):

```ts
// Inertia 3 (Vue; React: '@fydellys/inertia-static/react')
import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // ...
})
```

```ts
// Inertia 2 (Vue; React: '@fydellys/inertia-static/inertia2/react')
import axios from 'axios'
import { installInertiaStatic } from '@fydellys/inertia-static/inertia2'
import manifest from './inertia-static.json'

installInertiaStatic(axios, { manifest })   // before createInertiaApp
```

## Which visits are rewritten

A `GET` Inertia visit to a path of the manifest goes to one of two URLs:

| Visit | URL | Cached |
|---|---|---|
| normal navigation, back/forward, **prefetch**, clicking the link of the current page | `/__inertia/{version}/{path}.json` (query whitelisted and sorted) | yes |
| `router.reload()` and **polling** | `/__inertia/live/{version}/{path}.json` | **never** |
| partial reload, **deferred props**, `X-Inertia-Reset`, infinite scroll, `Precognition` | live | never |
| query outside the whitelist, `X-Inertia-Static-Bypass` header, [preview mode](./preview.md) | live (query kept) | never |

Sent unchanged: non-GET requests, requests that are not Inertia visits (`useHttp()`, your own `axios`/`fetch`
calls), other origins, GET visits with `data`, and paths that are not in the manifest (dynamic routes).

**Redirects.** When a visit (a form `POST`, a GET to a dynamic route…) is redirected to a static page, the server
points the redirect to the page's live JSON URL. `return back()` after a form on a static page works, including
validation errors and flash data. See [Forms on static pages](./islands.md#forms-on-static-pages).

Force fresh data for a visit:

```ts
router.visit('/blog', { headers: { 'X-Inertia-Static-Bypass': '1' } })
```

## Responses

- The `x-inertia: true` response header is added by the client, so static hosts and CDNs that drop it still work.
- **Missing JSON** (404/403/410, or an HTML page instead of JSON) → `onMissing`:
  - `'reload'` (default): Inertia performs a full page load of the original URL. Works on hosts without PHP.
  - `'origin'`: the visit is retried once against the original URL.
- **Version mismatch**: the origin answers `409` + `X-Inertia-Location` and Inertia reloads with the new
  assets. A JSON URL of an old version that is still cached remains valid for old clients.

## Options

`inertiaStatic()` and `installInertiaStatic(axios, options)` take the same options (except `client`, Inertia 3 only):

| Option | Default | |
|---|---|---|
| `manifest` | — | object written by `inertia-static:manifest` |
| `manifestUrl` | `'/__inertia/manifest.json'` | fetched once when no `manifest` is given; `false` disables it |
| `client` | `xhrHttpClient` | Inertia 3: the HTTP client being decorated (e.g. `axiosAdapter()`, or your own) |
| `trailingSlash` | `'never'` | must match `normalization.trailing_slash` |
| `index` | `'index'` | must match `json.index` |
| `onMissing` | `'reload'` | `'reload'` or `'origin'` |
| `debug` | `false` | log every rewrite decision to the console |

## Debugging

With `debug: true`, every decision is logged. On Inertia 3 you can also ask the client directly:

```ts
const http = inertiaStatic({ manifest, debug: true })
http.decide({ method: 'get', url: '/blog', headers: { 'X-Inertia': true } })
// { rewrite: true, cacheable: true, reason: 'blog.index', url: 'https://example.com/__inertia/_/blog.json' }
```

The version segment is `_` here because the call sends no `X-Inertia-Version` header; real visits use the asset
version.
