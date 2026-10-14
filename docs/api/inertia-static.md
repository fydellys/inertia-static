# `inertiaStatic()`

The client plugin for **Inertia 3**: an HTTP client for `createInertiaApp` that serves visits to static pages from
their cacheable JSON URL. On Inertia 2, use [`installInertiaStatic()`](./install-inertia-static.md).

```ts
import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // ...
})
```

## Reference

```ts
inertiaStatic(options?: {
  manifest?: StaticManifest          // from inertia-static:manifest (written by the Vite plugin)
  manifestUrl?: string | false       // default '/__inertia/manifest.json' when no manifest is given
  client?: HttpClient                // decorated client, default: Inertia's XHR client
  trailingSlash?: 'never' | 'always' | 'ignore'
  index?: string                     // JSON name of "/", default 'index'
  onMissing?: 'reload' | 'origin'    // JSON missing: full reload (default) or retry on the origin
  debug?: boolean
}): StaticHttpClient
```

### Where visits go

| Visit to a static page | URL | Cached |
|---|---|---|
| link click (also to the current page), back/forward, prefetch | `/__inertia/{v}/{path}.json` | yes |
| `router.reload()`, `usePoll()`, partial reloads, deferred props | `/__inertia/live/{v}/{path}.json` | never |

Visits to dynamic routes, `useHttp()` and non-GET requests are not touched.

## Good to know

- An Inertia visit never uses the HTML URL of a static page: CDNs ignoring `Vary` would answer it with the cached
  HTML ([inertia#1923](https://github.com/inertiajs/inertia/issues/1923)).
- `x-inertia: true` is added to the responses, so hosts that strip it still work.
- `decide(config)` returns `{ rewrite, cacheable, reason, url }` for debugging.
