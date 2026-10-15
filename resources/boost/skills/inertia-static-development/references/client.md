# Client reference

## Imports

| Import | For |
|---|---|
| `@fydellys/inertia-static/vue` | Vue, Inertia 2 and 3 (needs the Vite plugin) |
| `@fydellys/inertia-static/react` | React, Inertia 2 and 3 (needs the Vite plugin) |
| `@fydellys/inertia-static/inertia2` | Inertia 2 + Vue, without the Vite plugin |
| `@fydellys/inertia-static/inertia2/react` | Inertia 2 + React, without the Vite plugin |

`/vue` and `/react` export `withInertiaStatic`, `useStaticIsland`, `StaticIsland`, `ensureCsrfCookie`, `inertiaStatic`
(Inertia 3) and, for Vue, `ssrRender`.

## TypeScript

Point the alias at the bundled types in `tsconfig.json`:

```json
{
  "compilerOptions": {
    "paths": {
      "@fydellys/inertia-static": ["./vendor/fydellys/inertia-static/js/dist/index.d.ts"],
      "@fydellys/inertia-static/*": ["./vendor/fydellys/inertia-static/js/dist/*"]
    }
  }
}
```

## Without the Vite plugin

Inertia 3:

```ts
import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // ...
})
```

Inertia 2 (call before `createInertiaApp`; pass the default `axios` import, axios >= 1.6; React: import from
`@fydellys/inertia-static/inertia2/react`):

```ts
import { createInertiaApp } from '@inertiajs/vue3'
import axios from 'axios'
import { installInertiaStatic } from '@fydellys/inertia-static/inertia2'
import manifest from './inertia-static.json'

installInertiaStatic(axios, { manifest })

createInertiaApp({
  // ...
})
```

Without the plugin the manifest is not written automatically: run `php artisan inertia-static:manifest` before
building. Without an imported manifest the client fetches `/__inertia/manifest.json` once; if that fails, visits
keep the normal Inertia flow.

## Options

`withInertiaStatic()`, `inertiaStatic()` and `installInertiaStatic(axios, options)` take:

| Option | Default | Notes |
|---|---|---|
| `manifest` | — | object written by `inertia-static:manifest` |
| `manifestUrl` | `'/__inertia/manifest.json'` | fetched once without `manifest`; `false` disables |
| `client` | Inertia's XHR client | Inertia 3 only: HTTP client to decorate (e.g. `axiosAdapter()`) |
| `trailingSlash` | `'never'` | must match `normalization.trailing_slash` |
| `index` | `'index'` | must match `json.index` |
| `onMissing` | `'reload'` | `'reload'` (full page load) or `'origin'` (retry once on the original URL; only with a PHP origin) |
| `debug` | `false` | log every rewrite decision |

## Visit routing

| Visit | URL | Cached |
|---|---|---|
| navigation, back/forward, prefetch, clicking the current page link | `/__inertia/{version}/{path}.json` | yes |
| `router.reload()`, polling | `/__inertia/live/{version}/{path}.json` | never |
| partial reload, deferred props, `X-Inertia-Reset`, infinite scroll, Precognition | live | never |
| query outside the whitelist, `X-Inertia-Static-Bypass` header, preview mode | live (query kept) | never |

Sent unchanged: non-GET requests, `useHttp()`, your own axios/fetch calls, other origins, GET visits with `data`,
paths not in the manifest.

```ts
router.visit('/blog', { headers: { 'X-Inertia-Static-Bypass': '1' } })   // force fresh data
```

On Inertia 3, inspect a decision:

```ts
const http = inertiaStatic({ manifest, debug: true })
http.decide({ method: 'get', url: '/blog', headers: { 'X-Inertia': true } })
// { rewrite: true, cacheable: true, reason: 'blog.index', url: '…/__inertia/_/blog.json' }
```

## CSRF for your own requests

Inertia forms need nothing. For your own `fetch()` that changes data from a static page:

```ts
import { ensureCsrfCookie } from '@fydellys/inertia-static/vue'

await ensureCsrfCookie('/__inertia/csrf')
const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')
await fetch('/newsletter', { method: 'POST', headers: { 'X-XSRF-TOKEN': token }, body: new FormData(form) })
```

Signature: `ensureCsrfCookie(url = '/sanctum/csrf-cookie', cookieName = 'XSRF-TOKEN')`. Axios sends the cookie as
`X-XSRF-TOKEN` on its own. Only Laravel 12 needs the token for same-origin requests.
