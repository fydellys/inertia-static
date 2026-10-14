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
 * Demo only: records a response as the CDN sent it. Called by the HTTP layer of
 * resources/js/inertia/v2.ts or v3.ts, BELOW the static client (rewritten URL,
 * status and cache headers, before the client fixes headers).
 */
export function recordResponse(method: string, url: string, response: { status: number; headers: object }, started: number) {
  const headers = response.headers as Record<string, string | undefined>
  const entry = {
    id: ++id,
    method: method.toUpperCase(),
    url: new URL(url, location.href).pathname + new URL(url, location.href).search,
    status: response.status,
    cache: headers['x-cache-status'] ?? headers['cf-cache-status']?.toUpperCase() ?? '-',
    inertiaStatic:
      headers['x-inertia-static'] ?? (headers['x-inertia-static-reason'] ? `BYPASS: ${headers['x-inertia-static-reason']}` : '-'),
    xInertiaOnWire: headers['x-inertia'] !== undefined,
    originHit: !SERVED_BY_CDN.includes((headers['x-cache-status'] ?? headers['cf-cache-status'] ?? '').toUpperCase()),
    browser: '…',
    originMs: '-',
    ms: Math.round(performance.now() - started),
  }
  network.requests.unshift(entry)
  // Resource Timing tells what the XHR API hides: a 304 revalidation of the browser cache.
  setTimeout(() => inspectTiming(url, network.requests.find((r) => r.id === entry.id)!), 50)
}

const SERVED_BY_CDN = ['HIT', 'STALE', 'UPDATING', 'REVALIDATED']

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
  // After a 304 the XHR exposes the headers of the copy in the browser cache (an older CDN
  // status). Server-Timing comes from the response actually received, so it wins when present.
  const cdn = timing.serverTiming?.find((entry) => entry.name === 'cdn')?.description.toUpperCase()
  if (cdn) {
    record.cache = cdn
    record.originHit = !SERVED_BY_CDN.includes(cdn)
  }
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
