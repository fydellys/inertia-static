# Installation

## Server

```bash
composer require fydellys/inertia-static
php artisan vendor:publish --tag=inertia-static-config
```

The service provider, its middleware and the `StaticCache` facade are registered automatically.

For CDNs **without tag purging** (nginx, CloudFront, Bunny), enable a tag registry so purges by tag become URL purges:

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

- makes `@fydellys/inertia-static/vue` and `/react` importable, pointing to the client of the Inertia version you
  have installed (2 or 3);
- dedupes `@inertiajs/core`, `vue`, `react`, `react-dom` and `axios`, so the client and your app share one copy;
- writes `resources/js/inertia-static.json` (`php artisan inertia-static:manifest`) when the build starts.

| Option | Default | |
|---|---|---|
| `manifest` | `true` | Set `false` to skip writing the manifest |
| `php` | `'php'` | PHP binary |
| `artisan` | `'artisan'` | Path to `artisan` |
| `inertia` | detected | `2` or `3`: the Inertia version, when it cannot be detected from `node_modules` |

Then add the client to `createInertiaApp`. The code is the same on Inertia 2 and 3:

::: code-group

```ts [Vue]
// resources/js/app.ts
import { createInertiaApp } from '@inertiajs/vue3'
import { withInertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  ...withInertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

```tsx [React]
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react'
import { withInertiaStatic } from '@fydellys/inertia-static/react'
import manifest from './inertia-static.json'

createInertiaApp({
  ...withInertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

:::

`useStaticIsland`, `<StaticIsland>` and `ensureCsrfCookie` come from the same import.

**Vue with SSR**: wrap `renderToString` with `ssrRender`, created inside the `createServer` callback. On Inertia 3
it changes nothing; on Inertia 2 it is required (Vue 3.5.44+ would render `[object Promise]`):

```ts
// resources/js/ssr.ts
import { createInertiaApp } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import { renderToString } from 'vue/server-renderer'
import { ssrRender } from '@fydellys/inertia-static/vue'

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

Without the Vite plugin, import the client of your Inertia version directly:
[`inertiaStatic()`](../api/inertia-static.md) (Inertia 3) or
[`installInertiaStatic()`](../api/install-inertia-static.md) (Inertia 2).

::: tip
Without an imported manifest, the client fetches `/__inertia/manifest.json` once. If that fails, every visit
keeps the normal Inertia flow (fail-open).
:::

## SSR

The package works with or without [Inertia SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering).
With SSR enabled, a page that renders without it (SSR server down) is sent with `no-store`, so the CDN never
stores a degraded copy. Set `INERTIA_STATIC_SSR_REQUIRED=false` to cache those pages anyway.

## Configure the CDN

Pick a driver in `.env` (`cloudflare`, `fastly`, `bunny`, `cloudfront`, `varnish`, `nginx`, `multi` or `null`, the
default) and follow its page ([Choosing a driver](../cdn/overview.md)):

```ini
INERTIA_STATIC_CDN=cloudflare
INERTIA_STATIC_BASE_URL=https://example.com
CLOUDFLARE_ZONE_ID=...       # zone ID and token are only needed to purge;
CLOUDFLARE_API_TOKEN=...     # without them the driver runs in headers-only mode
```
