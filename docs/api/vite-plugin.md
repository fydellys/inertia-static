# Vite plugin

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
  manifest?: boolean   // run `php artisan inertia-static:manifest` on build start, default true
  php?: string         // default 'php'
  artisan?: string     // default 'artisan'
}): Plugin
```

What it does:

- aliases `@fydellys/inertia-static` and `@fydellys/inertia-static/vue` to `vendor/fydellys/inertia-static/js/dist`;
- dedupes `@inertiajs/core` and `vue`, so there is a single copy of each in the bundle;
- writes `resources/js/inertia-static.json` when the build starts.

## Good to know

- If PHP is not available when the build runs (a Node-only CI step), the manifest step only warns. Commit the
  manifest, or run the command before the build.
