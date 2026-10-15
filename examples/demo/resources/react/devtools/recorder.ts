// Demo only (React): same network recorder as resources/js/devtools/recorder.ts, with a plain store.
import { useSyncExternalStore } from 'react'

export interface RecordedRequest {
  id: number
  method: string
  url: string
  status: number
  cache: string
  inertiaStatic: string
  xInertiaOnWire: boolean
  originHit: boolean
  browser: string
  originMs: string
  ms: number
}

const SERVED_BY_CDN = ['HIT', 'STALE', 'UPDATING', 'REVALIDATED']
let state = { requests: [] as RecordedRequest[], errors: [] as string[] }
const listeners = new Set<() => void>()
let id = 0

const update = (next: Partial<typeof state>) => {
  state = { ...state, ...next }
  listeners.forEach((listener) => listener())
}

export const useNetwork = () =>
  useSyncExternalStore(
    (listener) => (listeners.add(listener), () => listeners.delete(listener)),
    () => state,
    () => state,
  )

export const resetNetwork = () => update({ requests: [], errors: [] })

export function recordResponse(method: string, url: string, response: { status: number; headers: object }, started: number) {
  const headers = response.headers as Record<string, string | undefined>
  const entry: RecordedRequest = {
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
  update({ requests: [entry, ...state.requests] })
  setTimeout(() => inspectTiming(url, entry.id), 50)
}

function inspectTiming(url: string, entryId: number) {
  const timing = performance.getEntriesByName(new URL(url, location.href).href).at(-1) as
    | (PerformanceResourceTiming & { deliveryType?: string })
    | undefined
  const patch: Partial<RecordedRequest> = { browser: '?' }

  if (timing) {
    // deliveryType "cache" + bytes on the wire = revalidated (304); nothing on the wire = served locally.
    patch.browser = timing.transferSize === 0 ? 'local cache' : timing.deliveryType === 'cache' ? '304' : '200'
    // After a 304 the XHR exposes the headers of the browser's copy; Server-Timing comes from the real response.
    const cdn = timing.serverTiming?.find((entry) => entry.name === 'cdn')?.description.toUpperCase()
    if (cdn) {
      patch.cache = cdn
      patch.originHit = !SERVED_BY_CDN.includes(cdn)
    }
    const origin = timing.serverTiming?.find((entry) => entry.name === 'cfOrigin')
    patch.originMs = origin ? String(Math.round(origin.duration)) : '-'
  }

  update({ requests: state.requests.map((request) => (request.id === entryId ? { ...request, ...patch } : request)) })
}

if (typeof document !== 'undefined') {
  for (const name of ['httpException', 'networkError']) {
    document.addEventListener(`inertia:${name}`, (event) => {
      const detail = (event as CustomEvent).detail
      update({ errors: [`${name}: ${detail?.response?.status ?? detail?.error?.message ?? '?'}`, ...state.errors] })
    })
  }
}
