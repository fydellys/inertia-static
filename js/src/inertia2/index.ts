// Inertia 2 + Vue: @fydellys/inertia-static/inertia2
export { installInertiaStatic, ssrRender } from './adapter'
export type { AxiosLike, InertiaStaticOptions } from './adapter'
export { ensureCsrfCookie } from '../csrf'
export { useStaticIsland } from '../vue/useStaticIsland'
export type { StaticIsland, StaticIslandOptions } from '../vue/useStaticIsland'
