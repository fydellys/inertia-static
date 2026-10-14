# `installInertiaStatic()`

The client for **Inertia 2**: installs on the axios instance Inertia 2 uses, and serves visits to static pages from
their cacheable JSON URL. Same behavior as [`inertiaStatic()`](./inertia-static.md) on Inertia 3.

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

## Reference

```ts
installInertiaStatic(
  axios: AxiosStatic,                // the default `axios` import (the instance Inertia 2 uses)
  options?: {
    manifest?: StaticManifest        // from inertia-static:manifest (written by the Vite plugin)
    manifestUrl?: string | false     // default '/__inertia/manifest.json' when no manifest is given
    trailingSlash?: 'never' | 'always' | 'ignore'
    index?: string                   // JSON name of "/", default 'index'
    onMissing?: 'reload' | 'origin'  // JSON missing: full reload (default) or retry on the origin
    debug?: boolean
  },
): () => void                        // uninstall
```

## Good to know

- Call it **before** `createInertiaApp`. Calling it twice does nothing.
- Pass the default `axios` import: that is the instance Inertia 2 sends visits with. The Vite plugin dedupes
  `axios`, so your app and Inertia share it.
- `useStaticIsland` and `ensureCsrfCookie` are exported from `@fydellys/inertia-static/inertia2` as well.
