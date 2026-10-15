# Vite plugin

Resolves the `@fydellys/inertia-static` imports to the Composer package and writes the route manifest on build.
Shipped with the Composer package: no npm install.

```js
// vite.config.js
import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js'

export default defineConfig({
  plugins: [laravel({ /* ... */ }), vue(), inertiaStatic()],
})
```

## Reference

```ts
inertiaStatic(options?: {
  manifest?: boolean   // run `php artisan inertia-static:manifest` when the build starts, default true
  php?: string         // PHP binary, default 'php'
  artisan?: string     // path to artisan, default 'artisan'
  inertia?: 2 | 3      // Inertia version, default: detected from node_modules/@inertiajs/core
}): Plugin
```

## Good to know

- `@fydellys/inertia-static/vue` and `/react` resolve to the client of the Inertia version installed, so
  [`withInertiaStatic()`](./with-inertia-static.md) is the same on Inertia 2 and 3. `/inertia2` and
  `/inertia2/react` also work.
- The manifest is written to `resources/js/inertia-static.json` each time Vite starts or builds: restart it after changing static routes.
- If PHP is not available when the build runs (a Node-only CI step), the manifest step only warns: commit the
  manifest, or run `php artisan inertia-static:manifest` before the build.
