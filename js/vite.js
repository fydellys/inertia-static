// Vite plugin shipped with the Composer package (no npm install needed):
//
//   import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js'
//   export default defineConfig({ plugins: [laravel(...), vue(), inertiaStatic()] })
//
// - aliases "@fydellys/inertia-static" (and "/vue", "/react") to the built client in vendor/:
//   on Inertia 2, "/vue" and "/react" resolve to the Inertia 2 adapters, so one import works on both;
// - dedupes @inertiajs/core and vue, so the client and the app share ONE copy
//   (the client relies on Inertia's own classes, e.g. HttpResponseError), and axios on Inertia 2 only;
// - writes the static route manifest (php artisan inertia-static:manifest) when the build starts.
import { execFileSync } from 'node:child_process'
import { existsSync, readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { URL, fileURLToPath } from 'node:url'

const dist = fileURLToPath(new URL('./dist', import.meta.url))

/**
 * Major version of the @inertiajs/core installed for the project (closest node_modules), or null.
 *
 * @param {string} root
 * @returns {number | null}
 */
export function installedInertia(root) {
  for (let dir = resolve(root); ; dir = dirname(dir)) {
    const file = join(dir, 'node_modules', '@inertiajs', 'core', 'package.json')

    if (existsSync(file)) {
      const major = Number.parseInt(String(JSON.parse(readFileSync(file, 'utf8')).version), 10)

      return Number.isNaN(major) ? null : major
    }

    if (dirname(dir) === dir) {
      return null
    }
  }
}

/**
 * @param {number | null} inertia
 * @returns {import('vite').Alias[]}
 */
export function aliases(inertia) {
  const adapters =
    inertia === 2
      ? [
          { find: /^@fydellys\/inertia-static\/vue$/, replacement: join(dist, 'inertia2', 'index.js') },
          { find: /^@fydellys\/inertia-static\/react$/, replacement: join(dist, 'inertia2', 'react.js') },
        ]
      : []

  return [...adapters, { find: /^@fydellys\/inertia-static(?=\/|$)/, replacement: dist }]
}

/**
 * Packages the client and the app must share. axios only on Inertia 2, where the adapter is installed on the
 * app's axios instance: Inertia 3 does not depend on axios, and deduping a package that is not installed makes
 * Vite fail on the optional import("axios") inside @inertiajs/core.
 *
 * @param {number | null} inertia
 * @returns {string[]}
 */
export function dedupe(inertia) {
  const shared = ['@inertiajs/core', 'vue', 'react', 'react-dom']

  return inertia === 2 ? [...shared, 'axios'] : shared
}

/**
 * @param {{ manifest?: boolean, php?: string, artisan?: string, inertia?: 2 | 3 }} [options]
 * @returns {import('vite').Plugin}
 */
export default function inertiaStatic(options = {}) {
  const { manifest = true, php = 'php', artisan = 'artisan' } = options

  return {
    name: 'fydellys:inertia-static',
    enforce: 'pre',
    config(config) {
      const inertia = options.inertia ?? installedInertia(config.root ?? globalThis.process.cwd())

      return {
        resolve: {
          alias: aliases(inertia),
          dedupe: dedupe(inertia),
        },
      }
    },
    buildStart() {
      if (!manifest) {
        return
      }

      try {
        execFileSync(php, [artisan, 'inertia-static:manifest'], { stdio: 'pipe' })
      } catch (error) {
        this.warn(`[inertia-static] could not run "php artisan inertia-static:manifest": ${error.message}`)
      }
    },
  }
}
