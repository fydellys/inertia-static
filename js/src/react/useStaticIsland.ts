import { useCallback, useEffect, useRef, useState } from 'react'
import { fetchIsland, onceVisible, refreshAfterMutations, type StaticIslandOptions } from '../island'

export type { StaticIslandOptions } from '../island'

export interface StaticIsland<T> {
  data: T | null
  loading: boolean
  error: unknown
  load: () => Promise<T | null>
  /** With `when: "visible"`: the element to watch (`<div ref={island.target}>`). */
  target: (element: Element | null) => void
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
  const [element, setElement] = useState<Element | null>(null)
  const current = useRef<T | null>(data)
  const loaded = useRef(false)
  const init = useRef(options.init)
  init.current = options.init

  const load = useCallback(async (): Promise<T | null> => {
    loaded.current = true
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

  const visible = options.when === 'visible'
  const buffer = options.buffer ?? 0

  useEffect(() => {
    if (options.immediate !== false && !visible) {
      void load()
    }
  }, [load, options.immediate, visible])

  useEffect(() => {
    if (options.immediate === false || !visible || !element || loaded.current) {
      return undefined
    }

    return onceVisible(element, buffer, load)
  }, [load, options.immediate, visible, element, buffer])

  // A visible island that has not loaded yet loads with fresh data anyway.
  useEffect(
    () => (options.refreshAfterMutations === false ? undefined : refreshAfterMutations(() => (loaded.current ? load() : undefined))),
    [load, options.refreshAfterMutations],
  )

  return { data, loading, error, load, target: setElement }
}
