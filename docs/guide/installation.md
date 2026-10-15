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

- makes `@fydellys/inertia-static` (and `/vue`, `/react`, `/inertia2`, `/inertia2/react`) importable;
- dedupes `@inertiajs/core`, `vue`, `react`, `react-dom` and `axios`, so the client and your app share one copy;
- writes `resources/js/inertia-static.json` (`php artisan inertia-static:manifest`) when the build starts.

| Option | Default | |
|---|---|---|
| `manifest` | `true` | Set `false` to skip writing the manifest |
| `php` | `'php'` | PHP binary |
| `artisan` | `'artisan'` | Path to `artisan` |

::: code-group

```ts [Vue · Inertia 3]
// resources/js/app.ts
import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

```tsx [React · Inertia 3]
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react'
import { inertiaStatic } from '@fydellys/inertia-static/react'
import manifest from './inertia-static.json'

createInertiaApp({
  http: inertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

```ts [Vue · Inertia 2]
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

```tsx [React · Inertia 2]
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react'
import axios from 'axios'
import { installInertiaStatic } from '@fydellys/inertia-static/inertia2/react'
import manifest from './inertia-static.json'

installInertiaStatic(axios, { manifest })

createInertiaApp({
  // resolve, setup, ...
})
```

:::

| | Import from |
|---|---|
| Vue · Inertia 3 | `@fydellys/inertia-static/vue` |
| React · Inertia 3 | `@fydellys/inertia-static/react` |
| Vue · Inertia 2 | `@fydellys/inertia-static/inertia2` |
| React · Inertia 2 | `@fydellys/inertia-static/inertia2/react` |

`useStaticIsland` and `ensureCsrfCookie` come from the same import.

**Vue · Inertia 2 with SSR**: wrap `renderToString` with `ssrRender`, created inside the `createServer` callback
(without it, Vue 3.5.44+ renders `[object Promise]`):

```ts
// resources/js/ssr.ts (Inertia 2)
import { createInertiaApp } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import { renderToString } from 'vue/server-renderer'
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
