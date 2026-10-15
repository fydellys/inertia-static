# `withInertiaStatic()`

Add the client to `createInertiaApp`. The same code works on Inertia 2 and 3.

::: code-group

```ts [Vue]
import { createInertiaApp } from '@inertiajs/vue3'
import { withInertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  ...withInertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

```tsx [React]
import { createInertiaApp } from '@inertiajs/react'
import { withInertiaStatic } from '@fydellys/inertia-static/react'
import manifest from './inertia-static.json'

createInertiaApp({
  ...withInertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

:::

## Reference

```ts
withInertiaStatic(options?: InertiaStaticOptions): object   // spread into createInertiaApp()
```

The options are those of the [client](../guide/client.md#options).

## Good to know

- It needs the [Vite plugin](./vite-plugin.md): the plugin detects the Inertia version installed and resolves
  `@fydellys/inertia-static/vue` and `/react` to the matching client. Force it with `inertiaStatic({ inertia: 2 })`.
- On Inertia 3 it sets the `http` option ([`inertiaStatic()`](./inertia-static.md)). On Inertia 2 it installs the
  client on the app's `axios` ([`installInertiaStatic()`](./install-inertia-static.md)) and sets no option.
- `client` (a custom HTTP client) only applies to Inertia 3.
