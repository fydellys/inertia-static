# JavaScript API

Shipped with the Composer package and imported through the [Vite plugin](../guide/installation.md#client) alias.

| Import | For |
|---|---|
| `@fydellys/inertia-static/vue` | Vue, Inertia 2 and 3 (the Vite plugin picks the client of your Inertia version) |
| `@fydellys/inertia-static/react` | React, Inertia 2 and 3 |
| `@fydellys/inertia-static/inertia2` | Inertia 2 + Vue, without the Vite plugin |
| `@fydellys/inertia-static/inertia2/react` | Inertia 2 + React, without the Vite plugin |
| `@fydellys/inertia-static` | framework-agnostic helpers |

## Vite plugin

```js
import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js'

inertiaStatic({ manifest: true, php: 'php', artisan: 'artisan', inertia: undefined /* 2 | 3, detected */ })
```

Aliases `@fydellys/inertia-static` to the client in `vendor/` (on Inertia 2, `/vue` and `/react` to the Inertia 2
client), and runs `php artisan inertia-static:manifest` when
the build starts (`manifest: false` disables it).

## `/vue` and `/react`

Every import, on Inertia 2 and 3:

```ts
withInertiaStatic(options?: InertiaStaticOptions): object   // createInertiaApp({ ...withInertiaStatic({ manifest }) })
useStaticIsland<T>(url: string, options?: StaticIslandOptions<T>): StaticIsland<T>
StaticIsland                                                // component: <StaticIsland url when="visible">
ssrRender(renderToString)                                   // Vue: createInertiaApp({ render: ssrRender(renderToString) })
ensureCsrfCookie(url = '/sanctum/csrf-cookie', cookieName = 'XSRF-TOKEN'): Promise<void>
```

Inertia 3 only:

```ts
inertiaStatic(options?: InertiaStaticOptions): StaticHttpClient   // createInertiaApp({ http: inertiaStatic({ manifest }) })
ensureCsrfCookie(url = '/sanctum/csrf-cookie', cookieName = 'XSRF-TOKEN'): Promise<void>
```

## `/inertia2` and `/inertia2/react` (Inertia 2)

```ts
installInertiaStatic(axios: AxiosLike, options?: InertiaStaticOptions): () => void  // call before createInertiaApp; returns an uninstall function
withInertiaStatic, useStaticIsland, StaticIsland, ensureCsrfCookie, ssrRender (Vue)  // as above
```

`installInertiaStatic` needs axios ≥ 1.6.

## Options

```ts
interface InertiaStaticOptions {
  manifest?: StaticManifest                 // e.g. import manifest from './inertia-static.json'
  manifestUrl?: string | false              // default '/__inertia/manifest.json'; false: never fetch
  trailingSlash?: 'never' | 'always' | 'ignore' // must match normalization.trailing_slash (default 'never')
  index?: string                            // json.index (default 'index')
  onMissing?: 'reload' | 'origin'           // JSON missing (403/404/410): full page load (default) or retry the original URL
  debug?: boolean                           // log rewrite decisions to the console
  client?: HttpClient                       // Inertia 3 only: the client being decorated (default: Inertia's XHR client)
}
```

## `useStaticIsland`

```ts
interface StaticIslandOptions<T> {
  when?: 'mount' | 'visible'      // fetch on mount (default) or when `target` scrolls into view
  buffer?: number                 // with 'visible': start this many pixels before (default 0)
  immediate?: boolean             // false: only when load() is called (default true)
  initial?: T | null              // value before loading and during SSR
  init?: RequestInit              // extra fetch options (cookies are always sent)
  refreshAfterMutations?: boolean // reload after every non-GET Inertia visit (default true)
}

// Vue
{ data: ShallowRef<T | null>, loading: Ref<boolean>, error: ShallowRef<unknown>, load(): Promise<T | null>, target: Ref<Element | null> }
// React
{ data: T | null, loading: boolean, error: unknown, load(): Promise<T | null>, target: (element: Element | null) => void }
```

## `@fydellys/inertia-static`

```ts
createStaticHttpClient(options?: InertiaStaticOptions): StaticHttpClient

class StaticHttpClient implements HttpClient {
  request(config: HttpRequestConfig): Promise<HttpResponse>
  decide(config: HttpRequestConfig, manifest?: CompiledManifest | null): RewriteDecision
}

interface RewriteDecision { rewrite: boolean; cacheable?: boolean; reason: string; url?: string }

class StaticRouting               // the rewrite logic shared by the Inertia 2 and 3 clients
compileManifest(manifest: StaticManifest): CompiledManifest
matchRoute(manifest: CompiledManifest, url: URL): CompiledRoute | undefined
canonicalQuery(url: URL, whitelist: string[]): string | null  // null: the URL has non-whitelisted parameters
jsonPath(pathname: string, prefix: string, version: unknown, index = 'index'): string
ensureCsrfCookie(url?, cookieName?): Promise<void>
```

## Manifest format

Written by `php artisan inertia-static:manifest` (default `resources/js/inertia-static.json`) and served at
`/__inertia/manifest.json`:

```json
{
  "prefix": "/__inertia",
  "previewCookie": "inertia_static_preview",
  "csrfCookieUrl": "/__inertia/csrf",
  "routes": [
    { "name": "blog.index", "pattern": "^/blog/?$", "mode": "isr", "query": ["page"] },
    { "name": "blog.show", "pattern": "^/blog/([^/]+)/?$", "mode": "isr", "query": [] }
  ]
}
```

`csrfCookieUrl` is present only when `csrf_cookie` is on (by default, on Laravel 12). A route on a domain also has
`domain`.
