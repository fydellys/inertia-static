import { InertiaStaticOptions, StaticHttpClient } from './index.js';
export { a as StaticManifest, e as ensureCsrfCookie } from './csrf-ClD4GH0G.js';
export { S as StaticIsland, a as StaticIslandProps, u as useStaticIsland } from './StaticIsland-DNU_7NXW.js';
export { S as StaticIslandOptions } from './island-Cx4_sdB7.js';
import '@inertiajs/core';
import 'react';

/**
 * Inertia 3: HTTP client for `createInertiaApp({ http: inertiaStatic({ manifest }) })`.
 */
declare function inertiaStatic(options?: InertiaStaticOptions): StaticHttpClient;
/**
 * Inertia 2 and 3: `createInertiaApp({ ...withInertiaStatic({ manifest }), ... })`.
 * With the Vite plugin, this import resolves to the adapter of the installed Inertia.
 */
declare function withInertiaStatic(options?: InertiaStaticOptions): {
    http: StaticHttpClient;
};

export { InertiaStaticOptions, inertiaStatic, withInertiaStatic };
