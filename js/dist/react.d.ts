import { InertiaStaticOptions, StaticHttpClient } from './index.js';
export { a as StaticManifest, e as ensureCsrfCookie } from './csrf-BrRGbcsv.js';
export { S as StaticIsland, u as useStaticIsland } from './useStaticIsland-MuVW90xM.js';
export { S as StaticIslandOptions } from './island-CxGGYsgy.js';
import '@inertiajs/core';

/**
 * Inertia 3: HTTP client for `createInertiaApp({ http: inertiaStatic({ manifest }) })`.
 */
declare function inertiaStatic(options?: InertiaStaticOptions): StaticHttpClient;

export { InertiaStaticOptions, inertiaStatic };
