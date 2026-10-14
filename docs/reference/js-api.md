# JavaScript API

Imported from the Composer package through the [Vite plugin](../guide/installation.md#client) (or from npm).

## Vite plugin

```js
import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js' // or '@fydellys/inertia-static/vite'

inertiaStatic({ manifest: true, php: 'php', artisan: 'artisan' })
```

## `@fydellys/inertia-static/vue`

```ts
inertiaStatic(options?: InertiaStaticOptions): StaticHttpClient

useStaticIsland<T>(url: string, options?: {
  immediate?: boolean            // default true (on mount)
  initial?: T | null
  init?: RequestInit
  refreshAfterMutations?: boolean // default true
}): { data: ShallowRef<T | null>, loading: Ref<boolean>, error: ShallowRef<unknown>, load(): Promise<T | null> }

ensureCsrfCookie(url = '/sanctum/csrf-cookie', cookieName = 'XSRF-TOKEN'): Promise<void>
```

## `@fydellys/inertia-static` (framework-agnostic)

```ts
createStaticHttpClient(options?: InertiaStaticOptions): StaticHttpClient

class StaticHttpClient implements HttpClient {
  request(config: HttpRequestConfig): Promise<HttpResponse>
  decide(config: HttpRequestConfig): { rewrite: boolean; reason: string; url?: string }
}

interface InertiaStaticOptions {
  manifest?: StaticManifest
  manifestUrl?: string | false
  client?: HttpClient
  trailingSlash?: 'never' | 'always' | 'ignore'
  index?: string
  onMissing?: 'reload' | 'origin'
  debug?: boolean
}

compileManifest(manifest: StaticManifest): CompiledManifest
matchRoute(manifest: CompiledManifest, url: URL): CompiledRoute | undefined
canonicalQuery(url: URL, whitelist: string[]): string | null
jsonPath(pathname: string, prefix: string, version: unknown, index?: string): string
ensureCsrfCookie(...)
```

## Manifest format

```json
{
  "prefix": "/__inertia",
  "routes": [
    { "name": "blog.index", "pattern": "^/blog/?$", "mode": "isr", "query": ["page"] },
    { "name": "blog.show", "pattern": "^/blog/([^/]+)/?$", "mode": "isr" }
  ]
}
```
