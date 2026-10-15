interface StaticIslandOptions<T> {
    /** Fetch automatically (see `when`). `false`: only when you call `load()`. Default: true. */
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
    /**
     * When to fetch: "mount" (default) or "visible", the first time the island's
     * element (`target`) enters the viewport, like Inertia's <WhenVisible>.
     */
    when?: 'mount' | 'visible';
    /** With `when: "visible"`: start loading this many pixels before the element is visible. Default: 0. */
    buffer?: number;
}

export type { StaticIslandOptions as S };
