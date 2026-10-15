import { createStaticHttpClient, type InertiaStaticOptions, type StaticHttpClient } from '../client'

export { ensureCsrfCookie } from '../csrf'
export type { InertiaStaticOptions, StaticManifest } from '../index'
export { useStaticIsland } from './useStaticIsland'
export type { StaticIslandOptions } from './useStaticIsland'
export { StaticIsland } from './StaticIsland'
export type { StaticIslandProps } from './StaticIsland'

/**
 * Inertia 3: HTTP client for `createInertiaApp({ http: inertiaStatic({ manifest }) })`.
 */
export function inertiaStatic(options: InertiaStaticOptions = {}): StaticHttpClient {
  return createStaticHttpClient(options)
}

/**
 * Inertia 2 and 3: `createInertiaApp({ ...withInertiaStatic({ manifest }), ... })`.
 * With the Vite plugin, this import resolves to the adapter of the installed Inertia.
 */
export function withInertiaStatic(options: InertiaStaticOptions = {}): { http: StaticHttpClient } {
  return { http: inertiaStatic(options) }
}
