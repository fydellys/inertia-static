import { HttpResponseError, xhrHttpClient, type HttpClient, type HttpResponse } from '@inertiajs/core'
import { reactive } from 'vue'

export interface RecordedRequest {
  id: number
  method: string
  url: string
  status: number
  cache: string
  inertiaStatic: string
  xInertiaOnWire: boolean
  originHit: boolean
  /** browser cache: "304" when revalidated (only headers transferred), "200" when the body was downloaded */
  browser: string
  /** Cloudflare's time talking to the origin (Server-Timing cfOrigin), "0" = origin not contacted */
  originMs: string
  ms: number
}

export const network = reactive({ requests: [] as RecordedRequest[], errors: [] as string[] })

let id = 0

/**
 * Demo only: sits BELOW inertiaStatic(), so it sees the raw response from the
 * CDN (rewritten URL, status, cache headers) before the plugin fixes headers.
 */
export function recordingClient(inner: HttpClient = xhrHttpClient): HttpClient {
  return {
    async request(config) {
      const started = performance.now()
      const record = (response: HttpResponse) => {
        const entry = {
          id: ++id,
          method: config.method.toUpperCase(),
          url: new URL(config.url, location.href).pathname + new URL(config.url, location.href).search,
          status: response.status,
          cache: response.headers['x-cache-status'] ?? response.headers['cf-cache-status']?.toUpperCase() ?? '-',
          inertiaStatic:
            response.headers['x-inertia-static'] ??
            (response.headers['x-inertia-static-reason'] ? `BYPASS: ${response.headers['x-inertia-static-reason']}` : '-'),
          xInertiaOnWire: response.headers['x-inertia'] !== undefined,
          originHit: !['HIT', 'STALE', 'UPDATING', 'REVALIDATED'].includes((response.headers['x-cache-status'] ?? response.headers['cf-cache-status'] ?? '').toUpperCase()),
          browser: '…',
          originMs: '-',
          ms: Math.round(performance.now() - started),
        }
        network.requests.unshift(entry)
        // Resource Timing tells what the XHR API hides: a 304 revalidation of the browser cache.
        setTimeout(() => inspectTiming(config.url, network.requests.find((r) => r.id === entry.id)!), 50)
      }

      try {
        const response = await inner.request(config)
        record(response)
        return response
      } catch (error) {
        if (error instanceof HttpResponseError) {
          record(error.response as HttpResponse)
        }
        throw error
      }
    },
  }
}

function inspectTiming(url: string, record: RecordedRequest) {
  const href = new URL(url, location.href).href
  const timing = performance.getEntriesByName(href).at(-1) as (PerformanceResourceTiming & { responseStatus?: number }) | undefined

  if (!timing) {
    record.browser = '?'
    return
  }

  const delivery = (timing as PerformanceResourceTiming & { deliveryType?: string }).deliveryType
  // deliveryType "cache" + bytes on the wire = revalidated (304); nothing on the wire = served locally.
  record.browser = timing.transferSize === 0 ? 'local cache' : delivery === 'cache' ? '304' : '200'
  const origin = timing.serverTiming?.find((entry) => entry.name === 'cfOrigin')
  record.originMs = origin ? String(Math.round(origin.duration)) : '-'
}

if (typeof document !== 'undefined') {
  for (const name of ['httpException', 'networkError']) {
    document.addEventListener(`inertia:${name}`, (event) => {
      const detail = (event as CustomEvent).detail
      network.errors.unshift(`${name}: ${detail?.response?.status ?? detail?.error?.message ?? '?'}`)
    })
  }
}
