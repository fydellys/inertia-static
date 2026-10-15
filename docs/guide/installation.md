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

- aliases `@fydellys/inertia-static` (and `/vue`, `/inertia2`) to the built client in `vendor/`;
- **dedupes** `@inertiajs/core`, `vue` and `axios`, so the client and your app share one copy;
- runs `php artisan inertia-static:manifest` when the build starts. Options: `manifest: false`, `php`, `artisan`.

::: code-group

```ts [Inertia 3]
// resources/js/app.ts
import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

```ts [Inertia 2]
// resources/js/app.ts
import { createInertiaApp } from '@inertiajs/vue3'
import axios from 'axios'
import { installInertiaStatic } from '@fydellys/inertia-static/inertia2'
import manifest from './inertia-static.json'

installInertiaStatic(axios, { manifest })

createInertiaApp({
  // resolve, setup, ...
})
```

:::

On Inertia 2, import `useStaticIsland` and `ensureCsrfCookie` from `@fydellys/inertia-static/inertia2` too. With SSR,
wrap `renderToString` with `ssrRender` (Vue 3.5.44+ renders `[object Promise]` otherwise):

```ts
// resources/js/ssr.ts (Inertia 2)
import { ssrRender } from '@fydellys/inertia-static/inertia2'

createServer((page) => createInertiaApp({ page, render: ssrRender(renderToString), /* ... */ }))
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

The package works with or without [Inertia SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering).
With SSR enabled, a page that renders without it (SSR server down) is not cached, so the CDN never stores a
degraded copy.

## Configure the CDN

Pick a driver in `.env` and follow its page ([Choosing a driver](../cdn/overview.md)):

```ini
INERTIA_STATIC_CDN=cloudflare
CLOUDFLARE_ZONE_ID=...
CLOUDFLARE_API_TOKEN=...     # optional: without it the driver runs in headers-only mode
INERTIA_STATIC_BASE_URL=https://example.com
```
