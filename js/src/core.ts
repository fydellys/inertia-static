import { BYPASS_HEADERS, getHeader, hasHeader, type HeaderBag } from './headers'
import {
  compileManifest,
  DEFAULT_PREFIX,
  emptyManifest,
  matchRoute,
  type CompiledManifest,
  type StaticManifest,
} from './manifest'
import { canonicalQuery, jsonPath, normalizePath, type TrailingSlash } from './urls'

/**
 * Version-agnostic core shared by the Inertia 3 client and the Inertia 2 (axios) adapter.
 * It must not import anything from @inertiajs/core at runtime.
 */

export interface StaticRoutingOptions {
  /** Manifest object, e.g. `import manifest from './inertia-static.json'`. */
  manifest?: StaticManifest
  /** Where to fetch the manifest when no object is given. `false` disables fetching. */
  manifestUrl?: string | false
  /** Must match `inertia-static.normalization.trailing_slash`. */
  trailingSlash?: TrailingSlash
  /** Name of the root page JSON (`inertia-static.json.index`). */
  index?: string
  /**
   * When the static JSON is missing (404 / 403 / not JSON):
   * - "reload": full page load of the original URL (default, works on pure static hosts);
   * - "origin": retry the visit against the original URL (needs a PHP origin).
   */
  onMissing?: 'reload' | 'origin'
  /** Log rewrite decisions to the console. */
  debug?: boolean
}

export interface RewriteDecision {
  rewrite: boolean
  /** false for the never-cached "live" endpoint */
  cacheable?: boolean
  reason: string
  url?: string
}

export interface VisitRequest {
  method: string
  url: string
  headers?: HeaderBag
  data?: unknown
}

export interface VisitResponse {
  status: number
  headers: HeaderBag
  data: unknown
}

export const MISSING_STATUSES = [403, 404, 410]

export class StaticRouting {
  protected manifest: CompiledManifest | null
  protected loading: Promise<CompiledManifest> | null = null
  readonly options: Required<Omit<StaticRoutingOptions, 'manifest'>>

  constructor(options: StaticRoutingOptions = {}) {
    this.manifest = options.manifest ? compileManifest(options.manifest) : null
    this.options = {
      manifestUrl: options.manifestUrl ?? `${options.manifest?.prefix ?? DEFAULT_PREFIX}/manifest.json`,
      trailingSlash: options.trailingSlash ?? 'never',
      index: options.index ?? 'index',
      onMissing: options.onMissing ?? 'reload',
      debug: options.debug ?? false,
    }
  }

  /**
   * Whether (and where) a visit is rewritten.
   *
   * An Inertia visit to a static page never uses the page's HTML URL: CDNs that
   * ignore "Vary: X-Inertia" would answer it with the cached HTML (inertia#1923).
   * - cacheable visits go to /{prefix}/{version}/{path}.json
   * - visits that need fresh data (router.reload / polling, partial reloads,
   *   deferred props, non canonical query) go to /{prefix}/live/{version}/{path}.json
   */
  decide(request: VisitRequest, manifest: CompiledManifest | null = this.manifest): RewriteDecision {
    if (!manifest || manifest.routes.length === 0) {
      return { rewrite: false, reason: 'no-manifest' }
    }

    if (request.method.toLowerCase() !== 'get') {
      return { rewrite: false, reason: 'method' }
    }

    // Only router visits; other requests keep their URL.
    if (!hasHeader(request.headers, 'X-Inertia')) {
      return { rewrite: false, reason: 'not-a-visit' }
    }

    if (request.data && typeof request.data === 'object' && Object.keys(request.data).length > 0) {
      return { rewrite: false, reason: 'data' }
    }

    const url = new URL(request.url, window.location.href)

    if (url.origin !== window.location.origin) {
      return { rewrite: false, reason: 'cross-origin' }
    }

    const route = matchRoute(manifest, url)

    if (!route) {
      return { rewrite: false, reason: 'not-static' }
    }

    const path = normalizePath(url.pathname, this.options.trailingSlash)
    const version = getHeader(request.headers, 'X-Inertia-Version') as string | undefined
    const live = (reason: string, search: string): RewriteDecision => ({
      rewrite: true,
      cacheable: false,
      reason: `live:${reason}`,
      url: url.origin + jsonPath(path, `${manifest.prefix}/live`, version, this.options.index) + search,
    })

    // Preview mode: fresh content, never the cache.
    if (hasCookie(manifest.previewCookie)) {
      return live('preview', url.search)
    }

    const bypass = BYPASS_HEADERS.find((header) => hasHeader(request.headers, header))

    if (bypass) {
      return live(bypass.toLowerCase(), url.search)
    }

    // router.reload() and polling send "Cache-Control: no-cache": they want fresh data.
    // A click on the link of the current page does not: it is a normal (cacheable) visit.
    if (String(getHeader(request.headers, 'Cache-Control') ?? '').includes('no-cache')) {
      return live('reload', url.search)
    }

    const query = canonicalQuery(url, route.query)

    if (query === null) {
      return live('query', url.search)
    }

    return {
      rewrite: true,
      cacheable: true,
      reason: route.name ?? route.pattern,
      url: url.origin + jsonPath(path, manifest.prefix, version, this.options.index) + query,
    }
  }

  /** A 409 + X-Inertia-Location makes Inertia perform a full page load of the original URL. */
  reloadLocation(request: VisitRequest): string {
    return new URL(request.url, window.location.href).href
  }

  /**
   * A visit can end on a page JSON without its "x-inertia" header: the browser
   * followed a redirect inside the XHR (e.g. `return back()` to a static page,
   * rewritten to the live JSON URL), or a proxy stripped the header. When the
   * body IS an Inertia page, the header must be restored (inertia#1923).
   */
  needsInertiaHeader(request: VisitRequest, response: VisitResponse): boolean {
    return (
      getHeader(response.headers, 'x-inertia') === undefined &&
      hasHeader(request.headers, 'X-Inertia') &&
      response.status === 200 &&
      this.isInertiaPage(response)
    )
  }

  isInertiaPage(response: VisitResponse): boolean {
    if (!String(getHeader(response.headers, 'content-type') ?? '').includes('json') || typeof response.data !== 'string') {
      return false
    }

    try {
      const page = JSON.parse(response.data) as Record<string, unknown>

      return (
        typeof page.component === 'string' &&
        typeof page.url === 'string' &&
        'version' in page &&
        typeof page.props === 'object' &&
        page.props !== null
      )
    } catch {
      return false
    }
  }

  isPageJson(response: VisitResponse): boolean {
    const type = String(getHeader(response.headers, 'content-type') ?? '')

    if (type && !type.includes('json')) {
      return false
    }

    return typeof response.data === 'string' ? response.data.trimStart().startsWith('{') : true
  }

  async loadManifest(): Promise<CompiledManifest | null> {
    if (this.manifest || this.options.manifestUrl === false || typeof fetch === 'undefined') {
      return this.manifest
    }

    this.loading ??= fetch(this.options.manifestUrl, { headers: { Accept: 'application/json' } })
      .then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
      .then((manifest: StaticManifest) => compileManifest(manifest))
      // Fail open: without a manifest every visit keeps the normal Inertia flow.
      .catch(() => emptyManifest())

    this.manifest = await this.loading

    return this.manifest
  }

  log(url: string, decision: RewriteDecision): void {
    if (this.options.debug) {
      console.debug('[inertia-static]', url, decision)
    }
  }
}

function hasCookie(name: string): boolean {
  if (typeof document === 'undefined') {
    return false
  }

  return document.cookie.split('; ').some((cookie) => cookie.startsWith(`${name}=`) && cookie.length > name.length + 1)
}
