# `useStaticIsland()`

Load user-specific data in the browser, on a page that is cached for everyone.

::: code-group

```vue [Vue]
<script setup lang="ts">
import { useStaticIsland } from '@fydellys/inertia-static/vue'

const { data } = useStaticIsland<{ user: { name: string } | null }>('/api/me')
</script>

<template>
  <span v-if="data?.user">Hello, {{ data.user.name }}</span>
</template>
```

```tsx [React]
import { useStaticIsland } from '@fydellys/inertia-static/react'

export function Greeting() {
  const { data } = useStaticIsland<{ user: { name: string } | null }>('/api/me')

  return data?.user ? <span>Hello, {data.user.name}</span> : null
}
```

:::

## Load when visible

```vue
<script setup lang="ts">
const { data, target } = useStaticIsland<{ orders: Order[] }>('/api/me/orders', { when: 'visible' })
</script>

<template>
  <div ref="target">{{ data ? `${data.orders.length} orders` : 'Loading…' }}</div>
</template>
```

Or use the [`<StaticIsland>`](./static-island.md) component.

## Reference

```ts
useStaticIsland<T>(url: string, options?: {
  when?: 'mount' | 'visible'       // fetch on mount (default) or when `target` scrolls into view
  buffer?: number                  // with 'visible': start this many pixels before, default 0
  immediate?: boolean              // false: only when you call load(), default true
  initial?: T | null               // value before loading and during SSR, default null
  init?: RequestInit               // extra fetch options (cookies are always sent)
  refreshAfterMutations?: boolean  // reload after non-GET Inertia visits, default true
}): {
  data: T | null                   // Vue: ShallowRef<T | null>
  loading: boolean                 // Vue: Ref<boolean>
  error: unknown                   // Vue: ShallowRef<unknown>
  load(): Promise<T | null>        // fetch again
  target                           // with 'visible': Vue: <div ref="target">; React: <div ref={island.target}>
}
```

React returns plain values; Vue returns refs.

## Good to know

- It never runs during SSR, so the cached HTML is the same for every visitor.
- Put it in a **persistent layout** (Vue: `defineOptions({ layout: Layout })`; React: `Page.layout = (page) =>
  <Layout>{page}</Layout>`), so it is fetched once, not on every navigation.
- It reloads after sign in, sign out and form submissions (non-GET visits). A `visible` island that has not
  loaded yet waits until it is visible.
- A failed request sets `error` and puts `data` back to `initial`. When calls overlap, the latest one wins.
- The endpoint is a regular dynamic route: Laravel's default `Cache-Control: private` keeps it out of the CDN.
