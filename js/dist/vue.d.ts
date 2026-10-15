import { InertiaStaticOptions, StaticHttpClient } from './index.js';
export { a as StaticManifest, e as ensureCsrfCookie } from './csrf-BrRGbcsv.js';
export { S as StaticIsland, u as useStaticIsland } from './StaticIsland-LsHNyORi.js';
export { S as StaticIslandOptions } from './island-Cx4_sdB7.js';
import '@inertiajs/core';
import 'vue';

/**
 * HTTP client for `createInertiaApp({ http: inertiaStatic({ manifest }) })`.
 */
declare function inertiaStatic(options?: InertiaStaticOptions): StaticHttpClient;
/**
 * Inertia 2 and 3: `createInertiaApp({ ...withInertiaStatic({ manifest }), ... })`.
 * With the Vite plugin, this import resolves to the adapter of the installed Inertia.
 */
declare function withInertiaStatic(options?: InertiaStaticOptions): {
    http: StaticHttpClient;
};
/** Inertia 3 awaits its SSR render: nothing to adapt (Inertia 2 needs it, see /inertia2). */
declare function ssrRender<T>(renderToString: T): T;

export { InertiaStaticOptions, inertiaStatic, ssrRender, withInertiaStatic };
