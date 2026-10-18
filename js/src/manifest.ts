/**
 * The manifest produced by `php artisan inertia-static:manifest`
 * (or served at /__inertia/manifest.json).
 */
export interface StaticManifest {
  prefix: string
  /** Preview mode cookie: while present, static pages skip the cache. */
  previewCookie?: string
  /** Where to get the XSRF-TOKEN cookie before a form submission (Laravel 12). */
  csrfCookieUrl?: string
  routes: StaticManifestRoute[]
}

export interface StaticManifestRoute {
  name?: string
  pattern: string
  /** "isr" or "static"; a plain string so a manifest imported from JSON type-checks. */
  mode: string
  query?: string[]
  domain?: string
}

export interface CompiledRoute extends StaticManifestRoute {
  regex: RegExp
  query: string[]
}

export interface CompiledManifest {
  prefix: string
  previewCookie: string
  csrfCookieUrl: string | null
  routes: CompiledRoute[]
}

export const DEFAULT_PREFIX = '/__inertia'

export const DEFAULT_PREVIEW_COOKIE = 'inertia_static_preview'

export const emptyManifest = (): CompiledManifest => ({ prefix: DEFAULT_PREFIX, previewCookie: DEFAULT_PREVIEW_COOKIE, csrfCookieUrl: null, routes: [] })

export function compileManifest(manifest: StaticManifest): CompiledManifest {
  return {
    prefix: '/' + (manifest.prefix || DEFAULT_PREFIX).replace(/^\/+|\/+$/g, ''),
    previewCookie: manifest.previewCookie || DEFAULT_PREVIEW_COOKIE,
    csrfCookieUrl: manifest.csrfCookieUrl || null,
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
