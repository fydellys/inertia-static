import { getCurrentScope, getCurrentInstance, onMounted, onScopeDispose, ref, shallowRef, watch, type Ref, type ShallowRef } from 'vue'
import { fetchIsland, onceVisible, refreshAfterMutations, type StaticIslandOptions } from '../island'

export type { StaticIslandOptions } from '../island'

export interface StaticIsland<T> {
  data: ShallowRef<T | null>
  loading: Ref<boolean>
  error: ShallowRef<unknown>
  load: () => Promise<T | null>
  /** With `when: "visible"`: the element to watch (`<div ref="target">`). */
  target: Ref<Element | null>
}

/**
 * A dynamic "island" on a static page: user-specific data (e.g. /api/me)
 * fetched in the browser, never rendered into the cached HTML.
 *
 * It never runs during SSR, so the cached HTML is the same for every visitor.
 */
export function useStaticIsland<T = unknown>(url: string, options: StaticIslandOptions<T> = {}): StaticIsland<T> {
  const data = shallowRef<T | null>(options.initial ?? null)
  const loading = ref(false)
  const error = shallowRef<unknown>(null)
  const target = ref<Element | null>(null)
  let loaded = false
  let generation = 0

  const load = async (): Promise<T | null> => {
    if (typeof window === 'undefined') {
      return data.value
    }

    const current = ++generation
    loaded = true
    loading.value = true
    error.value = null

    try {
      const result = await fetchIsland<T>(url, options.init)

      if (current === generation) {
        data.value = result
      }
    } catch (e) {
      if (current === generation) {
        // Never keep the previous visitor's data (e.g. a 401 after logout).
        data.value = options.initial ?? null
        error.value = e
      }
    } finally {
      if (current === generation) {
        loading.value = false
      }
    }

    return data.value
  }

  const visible = options.when === 'visible'

  if (options.immediate !== false && !visible) {
    if (getCurrentInstance()) {
      onMounted(load)
    } else {
      void load()
    }
  }

  if (options.immediate !== false && visible && typeof window !== 'undefined') {
    let stopObserving = () => {}
    const stopWatching = watch(
      target,
      (element) => {
        stopObserving()
        stopObserving = element && !loaded ? onceVisible(element, options.buffer ?? 0, load) : () => {}
      },
      { immediate: true, flush: 'post' },
    )

    if (getCurrentScope()) {
      onScopeDispose(() => (stopWatching(), stopObserving()))
    }
  }

  if (options.refreshAfterMutations !== false && typeof document !== 'undefined') {
    // A visible island that has not loaded yet loads with fresh data anyway.
    const stop = refreshAfterMutations(() => (loaded ? load() : undefined))

    if (getCurrentScope()) {
      onScopeDispose(stop)
    }
  }

  return { data, loading, error, load, target }
}
