import { ShallowRef, Ref } from 'vue';

interface StaticIslandOptions<T> {
    /** Fetch immediately (on mount inside a component). Default: true. */
    immediate?: boolean;
    /** Value used before the island loads and on the server (SSR). */
    initial?: T | null;
    /** Extra fetch options. Cookies are always sent (same-origin). */
    init?: RequestInit;
    /**
     * Reload after every Inertia visit that is not a GET (login, logout, forms):
     * those may change who the visitor is, while the page component can be
     * reused (e.g. logout redirecting to the same page). Default: true.
     */
    refreshAfterMutations?: boolean;
}
interface StaticIsland<T> {
    data: ShallowRef<T | null>;
    loading: Ref<boolean>;
    error: ShallowRef<unknown>;
    load: () => Promise<T | null>;
}
/**
 * A dynamic "island" on a static page: user-specific data (e.g. /api/me)
 * fetched in the browser, never rendered into the cached HTML.
 *
 * It never runs during SSR, so the cached HTML is the same for every visitor.
 */
declare function useStaticIsland<T = unknown>(url: string, options?: StaticIslandOptions<T>): StaticIsland<T>;

export { type StaticIsland as S, type StaticIslandOptions as a, useStaticIsland as u };
