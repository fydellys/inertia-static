/**
 * The manifest produced by `php artisan inertia-static:manifest`
 * (or served at /__inertia/manifest.json).
 */
export interface StaticManifest {
  prefix: string
  routes: StaticManifestRoute[]
}

export interface StaticManifestRoute {
  name?: string
  pattern: string
  mode: 'isr' | 'static'
  query?: string[]
  domain?: string
}

export interface CompiledRoute extends StaticManifestRoute {
  regex: RegExp
  query: string[]
}

export interface CompiledManifest {
  prefix: string
  routes: CompiledRoute[]
}

export const DEFAULT_PREFIX = '/__inertia'

export const emptyManifest = (): CompiledManifest => ({ prefix: DEFAULT_PREFIX, routes: [] })

export function compileManifest(manifest: StaticManifest): CompiledManifest {
  return {
    prefix: '/' + (manifest.prefix || DEFAULT_PREFIX).replace(/^\/+|\/+$/g, ''),
    routes: (manifest.routes ?? []).flatMap((route) => {
      try {
        return [{ ...route, query: route.query ?? [], regex: new RegExp(route.pattern) }]
      } catch {
        // An invalid pattern is skipped: that route simply keeps the dynamic flow.
        return []
      }
    }),
  }
}

/**
 * The static route matching the URL, if any.
 */
export function matchRoute(manifest: CompiledManifest, url: URL): CompiledRoute | undefined {
  return manifest.routes.find(
    (route) => (!route.domain || route.domain === url.hostname) && route.regex.test(url.pathname),
  )
}
