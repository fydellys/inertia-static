import { H as HeaderBag, S as StaticRoutingOptions } from './csrf-l1dPRhIJ.js';
export { e as ensureCsrfCookie } from './csrf-l1dPRhIJ.js';
export { S as StaticIsland, a as StaticIslandOptions, u as useStaticIsland } from './useStaticIsland-C4VvsoyC.js';
import 'vue';

interface AxiosConfigLike {
    method?: string;
    url?: string;
    baseURL?: string;
    params?: unknown;
    headers?: HeaderBag;
    [key: string]: unknown;
}
interface AxiosResponseLike {
    status: number;
    data: unknown;
    headers: HeaderBag;
    [key: string]: unknown;
}
type AxiosAdapterLike = (config: AxiosConfigLike) => Promise<AxiosResponseLike>;
interface AxiosLike {
    defaults: {
        adapter?: unknown;
    };
    getAdapter?: (adapter: unknown) => AxiosAdapterLike;
}
/**
 * Installs the static rewrite on the axios instance Inertia 2 uses (the default
 * `axios` import). Call it before `createInertiaApp`. Returns an uninstall function.
 */
declare function installInertiaStatic(axios: AxiosLike, options?: StaticRoutingOptions): () => void;

export { type AxiosLike, StaticRoutingOptions as InertiaStaticOptions, installInertiaStatic };
