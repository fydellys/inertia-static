import { HttpClient, HttpRequestConfig, HttpResponse } from '@inertiajs/core';

/**
 * The manifest produced by `php artisan inertia-static:manifest`
 * (or served at /__inertia/manifest.json).
 */
interface StaticManifest {
    prefix: string;
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

interface InertiaStaticOptions {
    /** Manifest object, e.g. `import manifest from './inertia-static.json'`. */
    manifest?: StaticManifest;
    /** Where to fetch the manifest when no object is given. `false` disables fetching. */
    manifestUrl?: string | false;
    /** The HTTP client being decorated (default: Inertia's built-in XHR client). */
    client?: HttpClient;
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
/**
 * Decorates an Inertia HttpClient: GET visits to static / ISR routes are sent
 * to their cacheable JSON URL (/__inertia/{version}/{path}.json) and the
 * Inertia headers a static host cannot send are added to the response.
 */
declare class StaticHttpClient implements HttpClient {
    protected manifest: CompiledManifest | null;
    protected loading: Promise<CompiledManifest> | null;
    protected readonly inner: HttpClient;
    protected readonly options: Required<Omit<InertiaStaticOptions, 'manifest' | 'client'>>;
    constructor(options?: InertiaStaticOptions);
    request(config: HttpRequestConfig): Promise<HttpResponse>;
    /**
     * Whether (and where) a request is rewritten. Exposed for debugging/tests.
     *
     * An Inertia visit to a static page never uses the page's HTML URL: CDNs that
     * ignore "Vary: X-Inertia" would answer it with the cached HTML (inertia#1923).
     * - cacheable visits go to /{prefix}/{version}/{path}.json
     * - visits that need fresh data (router.reload / polling, partial reloads,
     *   deferred props, non canonical query) go to /{prefix}/live/{version}/{path}.json
     */
    decide(config: HttpRequestConfig, manifest?: CompiledManifest | null): RewriteDecision;
    protected missing(config: HttpRequestConfig): Promise<HttpResponse>;
    /**
     * A visit can end on a page JSON without its "x-inertia" header: the browser
     * followed a redirect inside the XHR (e.g. `return back()` to a static page,
     * rewritten to the live JSON URL), or a proxy stripped the header. When the
     * body IS an Inertia page, restore the header (inertia#1923).
     */
    protected withInertiaHeader(config: HttpRequestConfig, response: HttpResponse): HttpResponse;
    protected isInertiaPage(response: HttpResponse): boolean;
    protected isPageJson(response: HttpResponse): boolean;
    protected loadManifest(): Promise<CompiledManifest | null>;
    protected log(config: HttpRequestConfig, decision: RewriteDecision): void;
}
declare function createStaticHttpClient(options?: InertiaStaticOptions): StaticHttpClient;

/**
 * Static pages carry no session nor CSRF cookie. Call this before submitting
 * a form from a static page: it fetches the XSRF-TOKEN cookie once (Sanctum's
 * /sanctum/csrf-cookie or any dynamic endpoint of yours that starts a session).
 */
declare function ensureCsrfCookie(url?: string, cookieName?: string): Promise<void>;

export { type CompiledManifest, type InertiaStaticOptions, type RewriteDecision, StaticHttpClient, type StaticManifest, type StaticManifestRoute, canonicalQuery, compileManifest, createStaticHttpClient, ensureCsrfCookie, jsonPath, matchRoute };
