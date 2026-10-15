import { createElement, type ReactNode } from 'react'
import { useStaticIsland, type StaticIsland as Island } from './useStaticIsland'

/** What useStaticIsland() returns (same name as the component). */
export type StaticIsland<T> = Island<T>

export interface StaticIslandProps<T> {
  url: string
  when?: 'mount' | 'visible'
  buffer?: number
  as?: string
  init?: RequestInit
  refreshAfterMutations?: boolean
  /** Shown until the data arrives. */
  fallback?: ReactNode | ((island: Omit<Island<T>, 'target'>) => ReactNode)
  children?: ReactNode | ((island: Omit<Island<T>, 'target'>) => ReactNode)
}

/**
 * `<StaticIsland url="/api/me/orders" when="visible" fallback={...}>{({ data }) => ...}</StaticIsland>`:
 * an island as a component.
 */
export function StaticIsland<T = unknown>({ url, when = 'mount', buffer = 0, as = 'div', init, refreshAfterMutations = true, fallback, children }: StaticIslandProps<T>) {
  const { target, ...island } = useStaticIsland<T>(url, { when, buffer, init, refreshAfterMutations })
  const render = (node: StaticIslandProps<T>['children']) => (typeof node === 'function' ? node(island) : node)

  return createElement(as, { ref: target }, island.data === null && fallback !== undefined ? render(fallback) : render(children))
}
