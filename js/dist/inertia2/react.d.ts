export { A as AxiosLike, i as installInertiaStatic } from '../adapter-CmGTDh41.js';
import { S as StaticRoutingOptions } from '../csrf-ClD4GH0G.js';
export { e as ensureCsrfCookie } from '../csrf-ClD4GH0G.js';
export { S as StaticIsland, a as StaticIslandProps, u as useStaticIsland } from '../StaticIsland-DNU_7NXW.js';
export { S as StaticIslandOptions } from '../island-Cx4_sdB7.js';
import 'react';

/**
 * Inertia 2 and 3: `createInertiaApp({ ...withInertiaStatic({ manifest }), ... })`.
 * Inertia 2 sends its visits through axios: the adapter is installed on the app's axios.
 */
declare function withInertiaStatic(options?: StaticRoutingOptions): Record<string, never>;

export { StaticRoutingOptions as InertiaStaticOptions, withInertiaStatic };
