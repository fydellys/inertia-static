import { ShallowRef, Ref } from 'vue';
import { S as StaticIslandOptions } from './island-CxGGYsgy.js';

interface StaticIsland<T> {
    data: ShallowRef<T | null>;
    loading: Ref<boolean>;
    error: ShallowRef<unknown>;
    load: () => Promise<T | null>;
}
/**
 * A dynamic "island" on a static page: user-specific data (e.g. /api/me)
 * fetched in the browser, never rendered into the cached HTML.
 *
 * It never runs during SSR, so the cached HTML is the same for every visitor.
 */
declare function useStaticIsland<T = unknown>(url: string, options?: StaticIslandOptions<T>): StaticIsland<T>;

export { type StaticIsland as S, useStaticIsland as u };
