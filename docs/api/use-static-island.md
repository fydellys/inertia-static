# `useStaticIsland()`

Load user-specific data in the browser, on a page that is cached for everyone.

```vue
<script setup lang="ts">
import { useStaticIsland } from '@fydellys/inertia-static/vue'

const { data, loading } = useStaticIsland<{ user: { name: string } | null }>('/api/me')
</script>

<template>
  <span v-if="data?.user">Hello, {{ data.user.name }}</span>
</template>
```

## Reference

```ts
useStaticIsland<T>(url: string, options?: {
  immediate?: boolean              // fetch on mount, default true
  initial?: T | null               // value before loading and during SSR
  init?: RequestInit               // extra fetch options (cookies are always sent)
  refreshAfterMutations?: boolean  // reload after non-GET Inertia visits, default true
}): {
  data: ShallowRef<T | null>
  loading: Ref<boolean>
  error: ShallowRef<unknown>
  load(): Promise<T | null>
}
```

## Good to know

- Never runs during SSR, so the cached HTML is identical for every visitor.
- Put it in a **persistent layout** (`defineOptions({ layout: Layout })` in the pages). Then it is fetched once,
  not on every navigation.
- It reloads after sign in, sign out and forms (non-GET visits), even when the page component is reused.
- The endpoint is a regular dynamic route: Laravel's default `Cache-Control: private` already keeps it out of the CDN.
