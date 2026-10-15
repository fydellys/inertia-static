import * as vue from 'vue';
import { ShallowRef, Ref, PropType, SlotsType } from 'vue';
import { S as StaticIslandOptions } from './island-Cx4_sdB7.js';

interface StaticIsland$1<T> {
    data: ShallowRef<T | null>;
    loading: Ref<boolean>;
    error: ShallowRef<unknown>;
    load: () => Promise<T | null>;
    /** With `when: "visible"`: the element to watch (`<div ref="target">`). */
    target: Ref<Element | null>;
}
/**
 * A dynamic "island" on a static page: user-specific data (e.g. /api/me)
 * fetched in the browser, never rendered into the cached HTML.
 *
 * It never runs during SSR, so the cached HTML is the same for every visitor.
 */
declare function useStaticIsland<T = unknown>(url: string, options?: StaticIslandOptions<T>): StaticIsland$1<T>;

type SlotProps = {
    data: unknown;
    loading: boolean;
    error: unknown;
    load: StaticIsland$1<unknown>['load'];
};
/** What useStaticIsland() returns (same name as the component). */
type StaticIsland<T> = StaticIsland$1<T>;
/**
 * `<StaticIsland url="/api/me/orders" when="visible">`: an island as a component.
 * The default slot gets `{ data, loading, error, load }`; `#fallback` shows until the data arrives.
 */
declare const StaticIsland: vue.DefineComponent<vue.ExtractPropTypes<{
    url: {
        type: StringConstructor;
        required: true;
    };
    when: {
        type: PropType<"mount" | "visible">;
        default: string;
    };
    buffer: {
        type: NumberConstructor;
        default: number;
    };
    as: {
        type: StringConstructor;
        default: string;
    };
    init: {
        type: PropType<RequestInit>;
        default: undefined;
    };
    refreshAfterMutations: {
        type: BooleanConstructor;
        default: boolean;
    };
}>, () => vue.VNode<vue.RendererNode, vue.RendererElement, {
    [key: string]: any;
}>, {}, {}, {}, vue.ComponentOptionsMixin, vue.ComponentOptionsMixin, {}, string, vue.PublicProps, Readonly<vue.ExtractPropTypes<{
    url: {
        type: StringConstructor;
        required: true;
    };
    when: {
        type: PropType<"mount" | "visible">;
        default: string;
    };
    buffer: {
        type: NumberConstructor;
        default: number;
    };
    as: {
        type: StringConstructor;
        default: string;
    };
    init: {
        type: PropType<RequestInit>;
        default: undefined;
    };
    refreshAfterMutations: {
        type: BooleanConstructor;
        default: boolean;
    };
}>> & Readonly<{}>, {
    when: "mount" | "visible";
    buffer: number;
    as: string;
    init: RequestInit;
    refreshAfterMutations: boolean;
}, SlotsType<{
    default: SlotProps;
    fallback: SlotProps;
}>, {}, {}, string, vue.ComponentProvideOptions, true, {}, any>;

export { StaticIsland as S, useStaticIsland as u };
