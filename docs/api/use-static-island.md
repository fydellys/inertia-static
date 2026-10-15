# `useStaticIsland()`

Load user-specific data in the browser, on a page that is cached for everyone.

::: code-group

```vue [Vue]
<script setup lang="ts">
import { useStaticIsland } from '@fydellys/inertia-static/vue'

const { data, loading } = useStaticIsland<{ user: { name: string } | null }>('/api/me')
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

On Inertia 2, import it from `@fydellys/inertia-static/inertia2` (Vue) or `@fydellys/inertia-static/inertia2/react`.

## Reference

```ts
useStaticIsland<T>(url: string, options?: {
  immediate?: boolean              // fetch on mount, default true
  initial?: T | null               // value before loading and during SSR
  init?: RequestInit               // extra fetch options (cookies are always sent)
  refreshAfterMutations?: boolean  // reload after non-GET Inertia visits, default true
}): {
  data: T | null          // Vue: ShallowRef<T | null>
  loading: boolean        // Vue: Ref<boolean>
  error: unknown          // Vue: ShallowRef<unknown>
  load(): Promise<T | null>
}
```

## Good to know

- Never runs during SSR, so the cached HTML is identical for every visitor.
- Put it in a **persistent layout** (Vue: `defineOptions({ layout: Layout })`; React: `Page.layout = (page) =>
  <Layout>{page}</Layout>`). Then it is fetched once, not on every navigation.
- It reloads after sign in, sign out and forms (non-GET visits), even when the page component is reused.
- The endpoint is a regular dynamic route: Laravel's default `Cache-Control: private` already keeps it out of the CDN.
