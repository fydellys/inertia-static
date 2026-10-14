/** Request or response headers: a plain object, Inertia's headers or an AxiosHeaders instance. */
type HeaderBag = object;

/**
 * The manifest produced by `php artisan inertia-static:manifest`
 * (or served at /__inertia/manifest.json).
 */
interface StaticManifest {
    prefix: string;
    /** Preview mode cookie: while present, static pages skip the cache. */
    previewCookie?: string;
    routes: StaticManifestRoute[];
}
interface StaticManifestRoute {
    name?: string;
    pattern: string;
    mode: 'isr' | 'static';
    query?: string[];
    domain?: string;
}
interface CompiledRoute extends StaticManifestRoute {
    regex: RegExp;
    query: string[];
}
interface CompiledManifest {
    prefix: string;
    previewCookie: string;
    routes: CompiledRoute[];
}
declare function compileManifest(manifest: StaticManifest): CompiledManifest;
/**
 * The static route matching the URL, if any.
 */
declare function matchRoute(manifest: CompiledManifest, url: URL): CompiledRoute | undefined;

type TrailingSlash = 'never' | 'always' | 'ignore';
/**
 * Canonical query string: only whitelisted keys, sorted, empty values removed.
 * Returns null when the URL carries keys outside the whitelist (the visit then
 * goes to the origin, which redirects to the canonical URL).
 */
declare function canonicalQuery(url: URL, whitelist: string[]): string | null;
/**
 * /blog/post => {prefix}/{version}/blog/post.json   /  => {prefix}/{version}/index.json
 */
declare function jsonPath(pathname: string, prefix: string, version: unknown, index?: string): string;

/**
 * Version-agnostic core shared by the Inertia 3 client and the Inertia 2 (axios) adapter.
 * It must not import anything from @inertiajs/core at runtime.
 */
interface StaticRoutingOptions {
    /** Manifest object, e.g. `import manifest from './inertia-static.json'`. */
    manifest?: StaticManifest;
    /** Where to fetch the manifest when no object is given. `false` disables fetching. */
    manifestUrl?: string | false;
    /** Must match `inertia-static.normalization.trailing_slash`. */
    trailingSlash?: TrailingSlash;
    /** Name of the root page JSON (`inertia-static.json.index`). */
    index?: string;
    /**
     * When the static JSON is missing (404 / 403 / not JSON):
     * - "reload": full page load of the original URL (default, works on pure static hosts);
     * - "origin": retry the visit against the original URL (needs a PHP origin).
     */
    onMissing?: 'reload' | 'origin';
    /** Log rewrite decisions to the console. */
    debug?: boolean;
}
interface RewriteDecision {
    rewrite: boolean;
    /** false for the never-cached "live" endpoint */
    cacheable?: boolean;
    reason: string;
    url?: string;
}
interface VisitRequest {
    method: string;
    url: string;
    headers?: HeaderBag;
    data?: unknown;
}
interface VisitResponse {
    status: number;
    headers: HeaderBag;
    data: unknown;
}
declare class StaticRouting {
    protected manifest: CompiledManifest | null;
    protected loading: Promise<CompiledManifest> | null;
    readonly options: Required<Omit<StaticRoutingOptions, 'manifest'>>;
    constructor(options?: StaticRoutingOptions);
    /**
     * Whether (and where) a visit is rewritten.
     *
     * An Inertia visit to a static page never uses the page's HTML URL: CDNs that
     * ignore "Vary: X-Inertia" would answer it with the cached HTML (inertia#1923).
     * - cacheable visits go to /{prefix}/{version}/{path}.json
     * - visits that need fresh data (router.reload / polling, partial reloads,
     *   deferred props, non canonical query) go to /{prefix}/live/{version}/{path}.json
     */
    decide(request: VisitRequest, manifest?: CompiledManifest | null): RewriteDecision;
    /** A 409 + X-Inertia-Location makes Inertia perform a full page load of the original URL. */
    reloadLocation(request: VisitRequest): string;
    /**
     * A visit can end on a page JSON without its "x-inertia" header: the browser
     * followed a redirect inside the XHR (e.g. `return back()` to a static page,
     * rewritten to the live JSON URL), or a proxy stripped the header. When the
     * body IS an Inertia page, the header must be restored (inertia#1923).
     */
    needsInertiaHeader(request: VisitRequest, response: VisitResponse): boolean;
    isInertiaPage(response: VisitResponse): boolean;
    isPageJson(response: VisitResponse): boolean;
    loadManifest(): Promise<CompiledManifest | null>;
    log(url: string, decision: RewriteDecision): void;
}

/**
 * Static pages carry no session nor CSRF cookie. Call this before submitting
 * a form from a static page: it fetches the XSRF-TOKEN cookie once (Sanctum's
 * /sanctum/csrf-cookie or any dynamic endpoint of yours that starts a session).
 */
declare function ensureCsrfCookie(url?: string, cookieName?: string): Promise<void>;

export { type CompiledManifest as C, type HeaderBag as H, type RewriteDecision as R, type StaticRoutingOptions as S, type StaticManifest as a, StaticRouting as b, type StaticManifestRoute as c, canonicalQuery as d, ensureCsrfCookie as e, compileManifest as f, jsonPath as j, matchRoute as m };
