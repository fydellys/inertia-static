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

export type { StaticIslandOptions as S };
