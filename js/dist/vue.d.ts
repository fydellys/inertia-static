import { InertiaStaticOptions, StaticHttpClient } from './index.js';
export { a as StaticManifest, e as ensureCsrfCookie } from './csrf-BrRGbcsv.js';
export { S as StaticIsland, a as StaticIslandOptions, u as useStaticIsland } from './useStaticIsland-C4VvsoyC.js';
import '@inertiajs/core';
import 'vue';

/**
 * HTTP client for `createInertiaApp({ http: inertiaStatic({ manifest }) })`.
 */
declare function inertiaStatic(options?: InertiaStaticOptions): StaticHttpClient;

export { InertiaStaticOptions, inertiaStatic };
