# Installation

## Server

```bash
composer require fydellys/inertia-static
php artisan vendor:publish --tag=inertia-static-config
```

The service provider and the `StaticCache` facade are auto-discovered. The global middleware
(`ApplyStaticCache`) is registered automatically.

Optional, for CDNs **without tag purging** (nginx, Bunny, or when you want purges by tag to become URL purges):

```bash
php artisan vendor:publish --tag=inertia-static-migrations
php artisan migrate
```

```ini
INERTIA_STATIC_REGISTRY=database   # or "cache" (e.g. Redis)
```

## Client

The built client ships **inside the Composer package** (`vendor/fydellys/inertia-static/js`), like Ziggy. There is
no npm package to install.

```js
// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js'

export default defineConfig({
  plugins: [laravel({ /* ... */ }), vue(), inertiaStatic()],
})
```

The plugin:

- aliases `@fydellys/inertia-static` (and `@fydellys/inertia-static/vue`) to the built client in `vendor/`;
- **dedupes** `@inertiajs/core` and `vue`, so the client and your app share one copy (the client relies on
  Inertia's own classes);
- runs `php artisan inertia-static:manifest` when the build starts. Options: `manifest: false`, `php`, `artisan`.

```ts
// resources/js/app.ts
import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

TypeScript: point the alias at the bundled types in `tsconfig.json`:

```json
{
  "compilerOptions": {
    "paths": {
      "@fydellys/inertia-static": ["./vendor/fydellys/inertia-static/js/dist/index.d.ts"],
      "@fydellys/inertia-static/*": ["./vendor/fydellys/inertia-static/js/dist/*"]
    }
  }
}
```


::: tip
Without an imported manifest, the plugin fetches `/__inertia/manifest.json` once. If that fails, every visit
keeps the normal Inertia flow (fail-open).
:::

## SSR

Inertia SSR is strongly recommended: by default an HTML response rendered **without** SSR is sent with
`no-store` (`inertia-static.ssr.required`). Follow the
[Inertia SSR guide](https://inertiajs.com/docs/v3/advanced/server-side-rendering), then check:

```bash
php artisan inertia-static:doctor
```

## Configure the CDN

Pick a driver in `.env` and follow its page ([Cloudflare](../cdn/cloudflare.md), [Fastly](../cdn/fastly.md),
[Bunny](../cdn/bunny.md), [Varnish](../cdn/varnish.md), [nginx](../cdn/nginx.md)):

```ini
INERTIA_STATIC_CDN=cloudflare
CLOUDFLARE_ZONE_ID=...
CLOUDFLARE_API_TOKEN=...     # optional: without it the driver runs in headers-only mode
INERTIA_STATIC_BASE_URL=https://example.com
```
