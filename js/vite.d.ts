import type { Plugin } from 'vite'

export interface InertiaStaticViteOptions {
  /** Run `php artisan inertia-static:manifest` when the build starts. Default: true. */
  manifest?: boolean
  /** PHP binary. Default: "php". */
  php?: string
  /** Path to artisan. Default: "artisan". */
  artisan?: string
}

export default function inertiaStatic(options?: InertiaStaticViteOptions): Plugin
