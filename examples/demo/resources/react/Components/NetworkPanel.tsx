import { useEffect, useState } from 'react'
import { resetNetwork, useNetwork } from '../devtools/recorder'

interface OriginStats {
  static: number
  dynamic: number
}

export default function NetworkPanel() {
  const network = useNetwork()
  const [mounted, setMounted] = useState(false)
  const [initial, setInitial] = useState({ status: '?' as number | string, cdn: '?' })
  const [origin, setOrigin] = useState<OriginStats | null>(null)

  const refreshOrigin = async () => setOrigin(await (await fetch('/__origin/stats', { cache: 'no-store' })).json())

  useEffect(() => {
    setMounted(true)
    const nav = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming & { responseStatus?: number }
    const timing = (name: string) => nav?.serverTiming?.find((entry) => entry.name === name)?.description ?? '-'
    setInitial({ status: nav?.responseStatus ?? '?', cdn: timing('cdn') !== '-' ? timing('cdn') : timing('cfCacheStatus') })
    void refreshOrigin()
    const onFinish = () => setTimeout(refreshOrigin, 150)
    document.addEventListener('inertia:finish', onFinish)
    return () => document.removeEventListener('inertia:finish', onFinish)
  }, [])

  if (!mounted) return null

  return (
    <section className="mt-6 rounded border border-slate-300 bg-white p-3 text-xs" data-testid="network-panel">
      <h2 className="mb-2 text-sm font-bold">Network panel (demo)</h2>
      <p data-testid="initial-load">
        Initial load (HTML): status <b>{initial.status}</b> · CDN <b>{initial.cdn}</b>
      </p>
      <p className="my-1" data-testid="origin-hits">
        ORIGIN hits — static pages: <b data-testid="origin-static">{origin?.static ?? '…'}</b> · dynamic:{' '}
        <b data-testid="origin-dynamic">{origin?.dynamic ?? '…'}</b>
        <button
          className="ml-2 rounded border px-1"
          data-testid="reset-origin"
          onClick={async () => {
            await fetch('/__origin/reset', { cache: 'no-store' })
            resetNetwork()
            await refreshOrigin()
          }}
        >
          reset
        </button>
      </p>
      {network.errors.length ? (
        <p className="text-red-700" data-testid="inertia-errors">Inertia errors: {network.errors.join(' | ')}</p>
      ) : (
        <p className="text-green-700" data-testid="inertia-errors">Inertia errors: none</p>
      )}
      <table className="mt-2 w-full text-left" data-testid="requests">
        <thead>
          <tr>
            <th>#</th><th>method</th><th>URL on the wire</th><th>status</th><th>CDN</th><th>X-Inertia-Static (origin decision)</th>
            <th>x-inertia on the wire</th><th>origin?</th><th>browser</th><th>CF→origin ms</th><th>ms</th>
          </tr>
        </thead>
        <tbody>
          {network.requests.slice(0, 12).map((r) => (
            <tr key={r.id} className={r.cache === 'HIT' ? 'text-green-700' : ''}>
              <td>{r.id}</td><td>{r.method}</td><td className="break-all">{r.url}</td><td>{r.status}</td>
              <td>{r.cache}</td><td>{r.inertiaStatic}</td><td>{r.xInertiaOnWire ? 'yes' : 'NO'}</td>
              <td>{r.originHit ? 'yes' : 'no'}</td><td data-testid="browser-cache">{r.browser}</td><td>{r.originMs}</td><td>{r.ms}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  )
}
