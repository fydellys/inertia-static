import {
  HttpResponseError,
  xhrHttpClient,
  type HttpClient,
  type HttpRequestConfig,
  type HttpResponse,
} from '@inertiajs/core'
import { BYPASS_HEADERS, getHeader, hasHeader } from './headers'
import {
  compileManifest,
  DEFAULT_PREFIX,
  emptyManifest,
  matchRoute,
  type CompiledManifest,
  type StaticManifest,
} from './manifest'
import { canonicalQuery, jsonPath, normalizePath, type TrailingSlash } from './urls'

export interface InertiaStaticOptions {
  /** Manifest object, e.g. `import manifest from './inertia-static.json'`. */
  manifest?: StaticManifest
  /** Where to fetch the manifest when no object is given. `false` disables fetching. */
  manifestUrl?: string | false
  /** The HTTP client being decorated (default: Inertia's built-in XHR client). */
  client?: HttpClient
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

const MISSING_STATUSES = [403, 404, 410]

/**
 * Decorates an Inertia HttpClient: GET visits to static / ISR routes are sent
 * to their cacheable JSON URL (/__inertia/{version}/{path}.json) and the
 * Inertia headers a static host cannot send are added to the response.
 */
export class StaticHttpClient implements HttpClient {
  protected manifest: CompiledManifest | null
  protected loading: Promise<CompiledManifest> | null = null
  protected readonly inner: HttpClient
  protected readonly options: Required<Omit<InertiaStaticOptions, 'manifest' | 'client'>>

  constructor(options: InertiaStaticOptions = {}) {
    this.inner = options.client ?? xhrHttpClient
    this.manifest = options.manifest ? compileManifest(options.manifest) : null
    this.options = {
      manifestUrl: options.manifestUrl ?? `${options.manifest?.prefix ?? DEFAULT_PREFIX}/manifest.json`,
      trailingSlash: options.trailingSlash ?? 'never',
      index: options.index ?? 'index',
      onMissing: options.onMissing ?? 'reload',
      debug: options.debug ?? false,
    }
  }

  async request(config: HttpRequestConfig): Promise<HttpResponse> {
    const decision = this.decide(config, await this.loadManifest())

    this.log(config, decision)

    if (!decision.rewrite || !decision.url) {
      return this.withInertiaHeader(config, await this.inner.request(config))
    }

    try {
      const response = await this.inner.request({ ...config, url: decision.url })

      if (!this.isPageJson(response)) {
        return this.missing(config)
      }

      return { ...response, headers: { ...response.headers, 'x-inertia': 'true' } }
    } catch (error) {
      if (error instanceof HttpResponseError && MISSING_STATUSES.includes(error.response.status)) {
        return this.missing(config)
      }

      throw error
    }
  }

  /**
   * Whether (and where) a request is rewritten. Exposed for debugging/tests.
   *
   * An Inertia visit to a static page never uses the page's HTML URL: CDNs that
   * ignore "Vary: X-Inertia" would answer it with the cached HTML (inertia#1923).
   * - cacheable visits go to /{prefix}/{version}/{path}.json
   * - visits that need fresh data (router.reload / polling, partial reloads,
   *   deferred props, non canonical query) go to /{prefix}/live/{version}/{path}.json
   */
  decide(config: HttpRequestConfig, manifest: CompiledManifest | null = this.manifest): RewriteDecision {
    if (!manifest || manifest.routes.length === 0) {
      return { rewrite: false, reason: 'no-manifest' }
    }

    if (config.method.toLowerCase() !== 'get') {
      return { rewrite: false, reason: 'method' }
    }

    // Only router visits; useHttp() and other requests keep their URL.
    if (!hasHeader(config.headers, 'X-Inertia')) {
      return { rewrite: false, reason: 'not-a-visit' }
    }

    if (config.data && typeof config.data === 'object' && Object.keys(config.data).length > 0) {
      return { rewrite: false, reason: 'data' }
    }

    const url = new URL(config.url, window.location.href)

    if (url.origin !== window.location.origin) {
      return { rewrite: false, reason: 'cross-origin' }
    }

    const route = matchRoute(manifest, url)

    if (!route) {
      return { rewrite: false, reason: 'not-static' }
    }

    const path = normalizePath(url.pathname, this.options.trailingSlash)
    const version = getHeader(config.headers, 'X-Inertia-Version')
    const live = (reason: string, search: string): RewriteDecision => ({
      rewrite: true,
      cacheable: false,
      reason: `live:${reason}`,
      url: url.origin + jsonPath(path, `${manifest.prefix}/live`, version, this.options.index) + search,
    })

    const bypass = BYPASS_HEADERS.find((header) => hasHeader(config.headers, header))

    if (bypass) {
      return live(bypass.toLowerCase(), url.search)
    }

    // router.reload() and polling send "Cache-Control: no-cache": they want fresh data.
    // A click on the link of the current page does not: it is a normal (cacheable) visit.
    if (String(getHeader(config.headers, 'Cache-Control') ?? '').includes('no-cache')) {
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

  protected async missing(config: HttpRequestConfig): Promise<HttpResponse> {
    if (this.options.onMissing === 'origin') {
      return this.inner.request(config)
    }

    // A 409 + X-Inertia-Location makes Inertia perform a full page load of the
    // original URL: no loop, and it works on hosts without PHP.
    return {
      status: 409,
      data: '',
      headers: { 'x-inertia-location': new URL(config.url, window.location.href).href },
    }
  }

  /**
   * A visit can end on a page JSON without its "x-inertia" header: the browser
   * followed a redirect inside the XHR (e.g. `return back()` to a static page,
   * rewritten to the live JSON URL), or a proxy stripped the header. When the
   * body IS an Inertia page, restore the header (inertia#1923).
   */
  protected withInertiaHeader(config: HttpRequestConfig, response: HttpResponse): HttpResponse {
    if (
      response.headers['x-inertia'] !== undefined ||
      !hasHeader(config.headers, 'X-Inertia') ||
      response.status !== 200 ||
      !this.isInertiaPage(response)
    ) {
      return response
    }

    return { ...response, headers: { ...response.headers, 'x-inertia': 'true' } }
  }

  protected isInertiaPage(response: HttpResponse): boolean {
    if (!(response.headers['content-type'] ?? '').includes('json') || typeof response.data !== 'string') {
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

  protected isPageJson(response: HttpResponse): boolean {
    const type = response.headers['content-type'] ?? ''

    if (type && !type.includes('json')) {
      return false
    }

    return typeof response.data === 'string' ? response.data.trimStart().startsWith('{') : true
  }

  protected async loadManifest(): Promise<CompiledManifest | null> {
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

  protected log(config: HttpRequestConfig, decision: RewriteDecision): void {
    if (this.options.debug) {
      console.debug('[inertia-static]', config.url, decision)
    }
  }
}

export function createStaticHttpClient(options: InertiaStaticOptions = {}): StaticHttpClient {
  return new StaticHttpClient(options)
}
