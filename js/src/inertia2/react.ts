// Inertia 2 + React: @fydellys/inertia-static/inertia2/react
import axios from 'axios'
import { installInertiaStatic, type AxiosLike, type InertiaStaticOptions } from './adapter'

export { installInertiaStatic } from './adapter'
export type { AxiosLike, InertiaStaticOptions } from './adapter'
export { ensureCsrfCookie } from '../csrf'
export { useStaticIsland } from '../react/useStaticIsland'
export type { StaticIslandOptions } from '../react/useStaticIsland'
export { StaticIsland } from '../react/StaticIsland'
export type { StaticIslandProps } from '../react/StaticIsland'

/**
 * Inertia 2 and 3: `createInertiaApp({ ...withInertiaStatic({ manifest }), ... })`.
 * Inertia 2 sends its visits through axios: the adapter is installed on the app's axios.
 */
export function withInertiaStatic(options: InertiaStaticOptions = {}): Record<string, never> {
  installInertiaStatic(axios as unknown as AxiosLike, options)

  return {}
}
