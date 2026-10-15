import { getCurrentScope, getCurrentInstance, onMounted, onScopeDispose, ref, shallowRef, type Ref, type ShallowRef } from 'vue'
import { fetchIsland, refreshAfterMutations, type StaticIslandOptions } from '../island'

export type { StaticIslandOptions } from '../island'

export interface StaticIsland<T> {
  data: ShallowRef<T | null>
  loading: Ref<boolean>
  error: ShallowRef<unknown>
  load: () => Promise<T | null>
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

  const load = async (): Promise<T | null> => {
    if (typeof window === 'undefined') {
      return data.value
    }

    loading.value = true
    error.value = null

    try {
      data.value = await fetchIsland<T>(url, options.init)
    } catch (e) {
      error.value = e
    } finally {
      loading.value = false
    }

    return data.value
  }

  if (options.immediate !== false) {
    if (getCurrentInstance()) {
      onMounted(load)
    } else {
      void load()
    }
  }

  if (options.refreshAfterMutations !== false && typeof document !== 'undefined') {
    const stop = refreshAfterMutations(load)

    if (getCurrentScope()) {
      onScopeDispose(stop)
    }
  }

  return { data, loading, error, load }
}
