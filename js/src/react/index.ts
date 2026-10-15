import { createStaticHttpClient, type InertiaStaticOptions, type StaticHttpClient } from '../client'

export { ensureCsrfCookie } from '../csrf'
export type { InertiaStaticOptions, StaticManifest } from '../index'
export { useStaticIsland } from './useStaticIsland'
export type { StaticIsland, StaticIslandOptions } from './useStaticIsland'

/**
 * Inertia 3: HTTP client for `createInertiaApp({ http: inertiaStatic({ manifest }) })`.
 */
export function inertiaStatic(options: InertiaStaticOptions = {}): StaticHttpClient {
  return createStaticHttpClient(options)
}
