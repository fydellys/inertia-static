import { getCurrentScope, getCurrentInstance, onMounted, onScopeDispose, ref, shallowRef, type Ref, type ShallowRef } from 'vue'

export interface StaticIslandOptions<T> {
  /** Fetch immediately (on mount inside a component). Default: true. */
  immediate?: boolean
  /** Value used before the island loads and on the server (SSR). */
  initial?: T | null
  /** Extra fetch options. Cookies are always sent (same-origin). */
  init?: RequestInit
  /**
   * Reload after every Inertia visit that is not a GET (login, logout, forms):
   * those may change who the visitor is, while the page component can be
   * reused (e.g. logout redirecting to the same page). Default: true.
   */
  refreshAfterMutations?: boolean
}

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
      const response = await fetch(url, {
        credentials: 'same-origin',
        ...options.init,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...options.init?.headers },
      })

      if (!response.ok) {
        throw new Error(`[inertia-static] Island ${url} failed with status ${response.status}.`)
      }

      data.value = (await response.json()) as T
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

interface VisitEventDetail {
  visit?: { method?: string; id?: string }
}

/**
 * Calls `callback` when a non-GET Inertia visit finishes (document events).
 */
function refreshAfterMutations(callback: () => unknown): () => void {
  const pending = new Set<unknown>()

  const onStart = (event: Event) => {
    const visit = (event as CustomEvent<VisitEventDetail>).detail?.visit

    if (visit?.method && visit.method.toLowerCase() !== 'get') {
      pending.add(visit.id ?? visit)
    }
  }

  const onFinish = (event: Event) => {
    const visit = (event as CustomEvent<VisitEventDetail>).detail?.visit

    if (visit && pending.delete(visit.id ?? visit)) {
      void callback()
    }
  }

  document.addEventListener('inertia:start', onStart)
  document.addEventListener('inertia:finish', onFinish)

  return () => {
    document.removeEventListener('inertia:start', onStart)
    document.removeEventListener('inertia:finish', onFinish)
  }
}
