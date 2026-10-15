# `inertiaStatic()`

The client for **Inertia 3**: an HTTP client for `createInertiaApp` that loads static pages from their cacheable
JSON URL. On Inertia 2, use [`installInertiaStatic()`](./install-inertia-static.md).

::: code-group

```ts [Vue]
import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // ...
})
```

```ts [React]
import { createInertiaApp } from '@inertiajs/react'
import { inertiaStatic } from '@fydellys/inertia-static/react'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // ...
})
```

:::

## Reference

```ts
inertiaStatic(options?: {
  manifest?: StaticManifest          // resources/js/inertia-static.json, written by the Vite plugin
  manifestUrl?: string | false       // fetched when no manifest is given, default '/__inertia/manifest.json'
  client?: HttpClient                // client to wrap, default: Inertia's built-in client
  trailingSlash?: 'never' | 'always' | 'ignore'   // default 'never', must match normalization.trailing_slash
  index?: string                     // JSON name of "/", default 'index', must match json.index
  onMissing?: 'reload' | 'origin'    // page JSON missing: full page load (default) or retry on the origin
  debug?: boolean                    // log each decision to the console
}): StaticHttpClient
```

| Visit to a static page | Served from |
|---|---|
| link click, back/forward, prefetch | the CDN (cached page JSON) |
| `router.reload()`, polling, partial reloads, deferred props, preview mode, query parameters not in the route's `query` | the origin, never cached |

## Good to know

- Visits to dynamic routes, non-GET requests and `useHttp()` are sent unchanged.
- Forms on static pages work: on Laravel 12 the client fetches the CSRF cookie before the first non-GET request.
- Use `onMissing: 'origin'` only when a PHP origin answers behind the CDN.
