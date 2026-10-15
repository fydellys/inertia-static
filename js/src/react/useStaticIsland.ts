import { useCallback, useEffect, useRef, useState } from 'react'
import { fetchIsland, refreshAfterMutations, type StaticIslandOptions } from '../island'

export type { StaticIslandOptions } from '../island'

export interface StaticIsland<T> {
  data: T | null
  loading: boolean
  error: unknown
  load: () => Promise<T | null>
}

/**
 * A dynamic "island" on a static page: user-specific data (e.g. /api/me)
 * fetched in the browser, never rendered into the cached HTML.
 *
 * Effects never run during SSR, so the cached HTML is the same for every visitor.
 */
export function useStaticIsland<T = unknown>(url: string, options: StaticIslandOptions<T> = {}): StaticIsland<T> {
  const [data, setData] = useState<T | null>(options.initial ?? null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const current = useRef<T | null>(data)
  const init = useRef(options.init)
  init.current = options.init

  const load = useCallback(async (): Promise<T | null> => {
    setLoading(true)
    setError(null)

    try {
      current.current = await fetchIsland<T>(url, init.current)
      setData(current.current)
    } catch (e) {
      setError(e)
    } finally {
      setLoading(false)
    }

    return current.current
  }, [url])

  useEffect(() => {
    if (options.immediate !== false) {
      void load()
    }
  }, [load, options.immediate])

  useEffect(() => (options.refreshAfterMutations === false ? undefined : refreshAfterMutations(load)), [load, options.refreshAfterMutations])

  return { data, loading, error, load }
}
