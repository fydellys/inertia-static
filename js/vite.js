// Vite plugin shipped with the Composer package (no npm install needed):
//
//   import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js'
//   export default defineConfig({ plugins: [laravel(...), vue(), inertiaStatic()] })
//
// - aliases "@fydellys/inertia-static" (and "/vue") to the built client in vendor/;
// - dedupes @inertiajs/core and vue, so the client and the app share ONE copy
//   (the client relies on Inertia's own classes, e.g. HttpResponseError);
// - writes the static route manifest (php artisan inertia-static:manifest) when the build starts.
import { execFileSync } from 'node:child_process'
import { URL, fileURLToPath } from 'node:url'

const dist = fileURLToPath(new URL('./dist', import.meta.url))

/**
 * @param {{ manifest?: boolean, php?: string, artisan?: string }} [options]
 * @returns {import('vite').Plugin}
 */
export default function inertiaStatic(options = {}) {
  const { manifest = true, php = 'php', artisan = 'artisan' } = options

  return {
    name: 'fydellys:inertia-static',
    enforce: 'pre',
    config() {
      return {
        resolve: {
          alias: [{ find: /^@fydellys\/inertia-static(?=\/|$)/, replacement: dist }],
          dedupe: ['@inertiajs/core', 'vue'],
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
