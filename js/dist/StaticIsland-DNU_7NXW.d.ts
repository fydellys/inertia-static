import * as react from 'react';
import { ReactNode } from 'react';
import { S as StaticIslandOptions } from './island-Cx4_sdB7.js';

interface StaticIsland$1<T> {
    data: T | null;
    loading: boolean;
    error: unknown;
    load: () => Promise<T | null>;
    /** With `when: "visible"`: the element to watch (`<div ref={island.target}>`). */
    target: (element: Element | null) => void;
}
/**
 * A dynamic "island" on a static page: user-specific data (e.g. /api/me)
 * fetched in the browser, never rendered into the cached HTML.
 *
 * Effects never run during SSR, so the cached HTML is the same for every visitor.
 */
declare function useStaticIsland<T = unknown>(url: string, options?: StaticIslandOptions<T>): StaticIsland$1<T>;

interface StaticIslandProps<T> {
    url: string;
    when?: 'mount' | 'visible';
    buffer?: number;
    as?: string;
    init?: RequestInit;
    refreshAfterMutations?: boolean;
    /** Shown until the data arrives. */
    fallback?: ReactNode | ((island: Omit<StaticIsland$1<T>, 'target'>) => ReactNode);
    children?: ReactNode | ((island: Omit<StaticIsland$1<T>, 'target'>) => ReactNode);
}
/** What useStaticIsland() returns (same name as the component). */
type StaticIsland<T> = StaticIsland$1<T>;
/**
 * `<StaticIsland url="/api/me/orders" when="visible" fallback={...}>{({ data }) => ...}</StaticIsland>`:
 * an island as a component.
 */
declare function StaticIsland<T = unknown>({ url, when, buffer, as, init, refreshAfterMutations, fallback, children }: StaticIslandProps<T>): react.DOMElement<react.DOMAttributes<Element>, Element>;

export { StaticIsland as S, type StaticIslandProps as a, useStaticIsland as u };
