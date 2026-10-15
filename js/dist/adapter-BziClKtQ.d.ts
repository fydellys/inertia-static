import { H as HeaderBag, S as StaticRoutingOptions } from './csrf-BrRGbcsv.js';

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
type RenderToString = (app: never) => Promise<string>;
/**
 * Inertia 2 SSR on every Vue 3.5 release. Inertia 2 renders the page app inside
 * the root element's `innerHTML` without awaiting it, and Vue 3.5.44+ turns that
 * pending promise into "[object Promise]". The nested render gets a placeholder,
 * replaced once it resolves: the HTML is the same as before.
 *
 *   createServer((page) => createInertiaApp({ page, render: ssrRender(renderToString), ... }))
 *
 * Create it per page (inside the createServer callback): it holds that render's state.
 */
declare function ssrRender(renderToString: RenderToString): (app: never) => Promise<string>;
/**
 * Installs the static rewrite on the axios instance Inertia 2 uses (the default
 * `axios` import). Call it before `createInertiaApp`. Returns an uninstall function.
 */
declare function installInertiaStatic(axios: AxiosLike, options?: StaticRoutingOptions): () => void;

export { type AxiosLike as A, installInertiaStatic as i, ssrRender as s };
