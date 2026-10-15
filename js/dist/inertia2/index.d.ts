export { A as AxiosLike, i as installInertiaStatic, s as ssrRender } from '../adapter-BB0clyZ7.js';
import { S as StaticRoutingOptions } from '../csrf-BrRGbcsv.js';
export { e as ensureCsrfCookie } from '../csrf-BrRGbcsv.js';
export { S as StaticIsland, u as useStaticIsland } from '../StaticIsland-LsHNyORi.js';
export { S as StaticIslandOptions } from '../island-Cx4_sdB7.js';
import 'vue';

/**
 * Inertia 2 and 3: `createInertiaApp({ ...withInertiaStatic({ manifest }), ... })`.
 * Inertia 2 sends its visits through axios: the adapter is installed on the app's axios.
 */
declare function withInertiaStatic(options?: StaticRoutingOptions): Record<string, never>;

export { StaticRoutingOptions as InertiaStaticOptions, withInertiaStatic };
