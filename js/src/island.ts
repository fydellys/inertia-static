// Framework-agnostic parts of useStaticIsland() (Vue and React).

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

/** Fetches the island's JSON (cookies included, never cached by the CDN). */
export async function fetchIsland<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, {
    credentials: 'same-origin',
    ...init,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...init?.headers },
  })

  if (!response.ok) {
    throw new Error(`[inertia-static] Island ${url} failed with status ${response.status}.`)
  }

  return (await response.json()) as T
}

interface VisitEventDetail {
  visit?: { method?: string; id?: string }
}

/**
 * Calls `callback` when a non-GET Inertia visit finishes (document events).
 */
export function refreshAfterMutations(callback: () => unknown): () => void {
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
