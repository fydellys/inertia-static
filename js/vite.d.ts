import type { Alias, Plugin } from 'vite'

export interface InertiaStaticViteOptions {
  /** Run `php artisan inertia-static:manifest` when the build starts. Default: true. */
  manifest?: boolean
  /** PHP binary. Default: "php". */
  php?: string
  /** Path to artisan. Default: "artisan". */
  artisan?: string
  /** Inertia major version, when it cannot be detected from node_modules. */
  inertia?: 2 | 3
}

export function installedInertia(root: string): number | null

export function aliases(inertia: number | null): Alias[]

export function dedupe(inertia: number | null): string[]

export default function inertiaStatic(options?: InertiaStaticViteOptions): Plugin
