# `installInertiaStatic()`

The client for **Inertia 2**: installs on the axios instance Inertia 2 uses, and loads static pages from their
cacheable JSON URL. Same behavior as [`inertiaStatic()`](./inertia-static.md) on Inertia 3.

```ts
import { createInertiaApp } from '@inertiajs/vue3'
import axios from 'axios'
import { installInertiaStatic } from '@fydellys/inertia-static/inertia2'
import manifest from './inertia-static.json'

installInertiaStatic(axios, { manifest })

createInertiaApp({
  // ...
})
```

With React, import it from `@fydellys/inertia-static/inertia2/react`.

## Reference

```ts
installInertiaStatic(
  axios: AxiosStatic,                // the default `axios` import
  options?: {
    manifest?: StaticManifest        // resources/js/inertia-static.json, written by the Vite plugin
    manifestUrl?: string | false     // fetched when no manifest is given, default '/__inertia/manifest.json'
    trailingSlash?: 'never' | 'always' | 'ignore'   // default 'never'
    index?: string                   // JSON name of "/", default 'index'
    onMissing?: 'reload' | 'origin'  // page JSON missing: full page load (default) or retry on the origin
    debug?: boolean
  },
): () => void                        // uninstall
```

## Good to know

- Call it **before** `createInertiaApp`. Calling it twice does nothing.
- Pass the default `axios` import: that is the instance Inertia 2 uses. Requires axios 1.6 or later.
- Vue SSR on Vue 3.5.44+: wrap the renderer with `ssrRender` from `@fydellys/inertia-static/inertia2`, created
  inside the `createServer` callback:

  ```ts
  createServer((page) => createInertiaApp({ page, render: ssrRender(renderToString), /* ... */ }))
  ```
