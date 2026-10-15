import {
  HttpResponseError,
  xhrHttpClient,
  type HttpClient,
  type HttpRequestConfig,
  type HttpResponse,
} from '@inertiajs/core'
import { MISSING_STATUSES, StaticRouting, type RewriteDecision, type StaticRoutingOptions } from './core'
import type { CompiledManifest } from './manifest'

export type { RewriteDecision } from './core'

export interface InertiaStaticOptions extends StaticRoutingOptions {
  /** The HTTP client being decorated (default: Inertia's built-in XHR client). */
  client?: HttpClient
}

/**
 * Inertia 3: decorates the HttpClient. GET visits to static / ISR routes are sent
 * to their cacheable JSON URL (/__inertia/{version}/{path}.json) and the Inertia
 * headers a static host cannot send are added to the response.
 */
export class StaticHttpClient implements HttpClient {
  protected readonly routing: StaticRouting
  protected readonly inner: HttpClient

  constructor(options: InertiaStaticOptions = {}) {
    this.inner = options.client ?? xhrHttpClient
    this.routing = new StaticRouting(options)
  }

  async request(config: HttpRequestConfig): Promise<HttpResponse> {
    await this.routing.ensureCsrfCookie(config)

    const decision = this.routing.decide(config, await this.routing.loadManifest())

    this.routing.log(config.url, decision)

    if (!decision.rewrite || !decision.url) {
      const response = await this.inner.request(config)

      return this.routing.needsInertiaHeader(config, response) ? withInertiaHeader(response) : response
    }

    try {
      const response = await this.inner.request({ ...config, url: decision.url })

      return this.routing.isPageJson(response) ? withInertiaHeader(response) : this.missing(config)
    } catch (error) {
      if (error instanceof HttpResponseError && MISSING_STATUSES.includes(error.response.status)) {
        return this.missing(config)
      }

      throw error
    }
  }

  /** Whether (and where) a request is rewritten. Exposed for debugging/tests. */
  decide(config: HttpRequestConfig, manifest?: CompiledManifest | null): RewriteDecision {
    return manifest === undefined ? this.routing.decide(config) : this.routing.decide(config, manifest)
  }

  protected async missing(config: HttpRequestConfig): Promise<HttpResponse> {
    if (this.routing.options.onMissing === 'origin') {
      return this.inner.request(config)
    }

    return { status: 409, data: '', headers: { 'x-inertia-location': this.routing.reloadLocation(config) } }
  }
}

function withInertiaHeader(response: HttpResponse): HttpResponse {
  return { ...response, headers: { ...response.headers, 'x-inertia': 'true' } }
}

export function createStaticHttpClient(options: InertiaStaticOptions = {}): StaticHttpClient {
  return new StaticHttpClient(options)
}
