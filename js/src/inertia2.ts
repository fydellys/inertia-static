import { MISSING_STATUSES, StaticRouting, type StaticRoutingOptions, type VisitRequest } from './core'
import { getHeader, type HeaderBag } from './headers'

/**
 * Inertia 2: visits go through the shared axios instance (there is no `http`
 * option). The same rewrite as the Inertia 3 client, installed as an axios adapter.
 *
 *   import axios from 'axios'
 *   installInertiaStatic(axios, { manifest })
 *   createInertiaApp({ ... })
 */

export type { StaticRoutingOptions as InertiaStaticOptions } from './core'
export { ensureCsrfCookie } from './csrf'
export { useStaticIsland } from './vue/useStaticIsland'
export type { StaticIsland, StaticIslandOptions } from './vue/useStaticIsland'

interface AxiosConfigLike {
  method?: string
  url?: string
  baseURL?: string
  params?: unknown
  headers?: HeaderBag
  [key: string]: unknown
}

interface AxiosResponseLike {
  status: number
  data: unknown
  headers: HeaderBag
  [key: string]: unknown
}

type AxiosAdapterLike = (config: AxiosConfigLike) => Promise<AxiosResponseLike>

export interface AxiosLike {
  defaults: { adapter?: unknown }
  getAdapter?: (adapter: unknown) => AxiosAdapterLike
}

const INSTALLED = Symbol.for('fydellys.inertia-static')

type RenderToString = (app: never) => Promise<string>

/**
 * Inertia 2 SSR on every Vue 3.5 release. Inertia 2 renders the page app inside
 * the root element's `innerHTML` without awaiting it, and Vue 3.5.44+ turns that
 * pending promise into "[object Promise]". The nested render gets a placeholder,
 * replaced once it resolves: the HTML is the same as before.
 *
 *   createServer((page) => createInertiaApp({ page, render: ssrRender(renderToString), ... }))
 *
 * Create it per page (inside the createServer callback): it holds that render's state.
 */
export function ssrRender(renderToString: RenderToString): (app: never) => Promise<string> {
  const nested: Promise<string>[] = []
  let rendering = false

  return ((app: never) => {
    if (rendering) {
      nested.push(renderToString(app))

      return `<!--inertia-static-ssr:${nested.length - 1}-->`
    }

    rendering = true

    return renderToString(app).then(async (html) => {
      const parts = await Promise.all(nested)

      return parts.reduce((out, part, index) => out.replace(`<!--inertia-static-ssr:${index}-->`, () => part), html)
    })
  }) as (app: never) => Promise<string>
}

/**
 * Installs the static rewrite on the axios instance Inertia 2 uses (the default
 * `axios` import). Call it before `createInertiaApp`. Returns an uninstall function.
 */
export function installInertiaStatic(axios: AxiosLike, options: StaticRoutingOptions = {}): () => void {
  const previous = axios.defaults.adapter

  if (typeof previous === 'function' && INSTALLED in previous) {
    return () => {}
  }

  if (typeof previous !== 'function' && !axios.getAdapter) {
    throw new Error('[inertia-static] Unsupported axios version: axios.getAdapter() is missing (axios >= 1.6 is required).')
  }

  const base: AxiosAdapterLike =
    typeof previous === 'function' ? (previous as AxiosAdapterLike) : (axios.getAdapter as NonNullable<AxiosLike['getAdapter']>)(previous)
  const routing = new StaticRouting(options)

  const adapter = async (config: AxiosConfigLike): Promise<AxiosResponseLike> => {
    await routing.ensureCsrfCookie({
      method: config.method ?? 'get',
      url: new URL(config.url ?? '', config.baseURL ?? window.location.href).href,
    })

    const request: VisitRequest = {
      method: config.method ?? 'get',
      url: new URL(config.url ?? '', config.baseURL ?? window.location.href).href,
      headers: config.headers,
      data: config.params,
    }
    const decision = routing.decide(request, await routing.loadManifest())

    routing.log(request.url, decision)

    if (!decision.rewrite || !decision.url) {
      const response = await base(config)

      return routing.needsInertiaHeader(request, response) ? withInertiaHeader(response) : response
    }

    try {
      const response = await base({ ...config, url: decision.url, baseURL: undefined, params: undefined })

      return routing.isPageJson(response) ? withInertiaHeader(response) : missing(config, request)
    } catch (error) {
      const status = (error as { response?: AxiosResponseLike }).response?.status

      if (status !== undefined && MISSING_STATUSES.includes(status)) {
        return missing(config, request)
      }

      throw error
    }
  }

  const missing = (config: AxiosConfigLike, request: VisitRequest): Promise<AxiosResponseLike> => {
    if (routing.options.onMissing === 'origin') {
      return base(config)
    }

    // Resolved (not rejected): Inertia 2 turns a 409 + X-Inertia-Location into a full page load.
    return Promise.resolve({
      status: 409,
      statusText: 'Conflict',
      data: '',
      headers: { 'x-inertia-location': routing.reloadLocation(request) },
      config,
      request: {},
    })
  }

  Object.defineProperty(adapter, INSTALLED, { value: true })
  axios.defaults.adapter = adapter

  return () => {
    axios.defaults.adapter = previous
  }
}

function withInertiaHeader(response: AxiosResponseLike): AxiosResponseLike {
  // AxiosHeaders keeps headers as own properties, so assigning works on both shapes.
  if (getHeader(response.headers, 'x-inertia') === undefined) {
    ;(response.headers as Record<string, unknown>)['x-inertia'] = 'true'
  }

  return response
}
