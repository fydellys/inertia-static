import { HttpClient, HttpRequestConfig, HttpResponse } from '@inertiajs/core';
import { S as StaticRoutingOptions, b as StaticRouting, C as CompiledManifest, R as RewriteDecision } from './csrf-BrRGbcsv.js';
export { a as StaticManifest, c as StaticManifestRoute, d as canonicalQuery, f as compileManifest, e as ensureCsrfCookie, j as jsonPath, m as matchRoute } from './csrf-BrRGbcsv.js';

interface InertiaStaticOptions extends StaticRoutingOptions {
    /** The HTTP client being decorated (default: Inertia's built-in XHR client). */
    client?: HttpClient;
}
/**
 * Inertia 3: decorates the HttpClient. GET visits to static / ISR routes are sent
 * to their cacheable JSON URL (/__inertia/{version}/{path}.json) and the Inertia
 * headers a static host cannot send are added to the response.
 */
declare class StaticHttpClient implements HttpClient {
    protected readonly routing: StaticRouting;
    protected readonly inner: HttpClient;
    constructor(options?: InertiaStaticOptions);
    request(config: HttpRequestConfig): Promise<HttpResponse>;
    /** Whether (and where) a request is rewritten. Exposed for debugging/tests. */
    decide(config: HttpRequestConfig, manifest?: CompiledManifest | null): RewriteDecision;
    protected missing(config: HttpRequestConfig): Promise<HttpResponse>;
}
declare function createStaticHttpClient(options?: InertiaStaticOptions): StaticHttpClient;

export { CompiledManifest, type InertiaStaticOptions, RewriteDecision, StaticHttpClient, StaticRouting, StaticRoutingOptions, createStaticHttpClient };
