# `<StaticIsland>`

An [island](../guide/islands.md) as a component: user data loaded in the browser, on a page that is cached for
everyone. With `when="visible"` it loads when it scrolls into view, like Inertia's `<WhenVisible>`.

::: code-group

```vue [Vue]
<script setup lang="ts">
import { StaticIsland } from '@fydellys/inertia-static/vue'
</script>

<template>
  <StaticIsland url="/api/me/orders" when="visible">
    <template #fallback>Loading…</template>
    <template #default="{ data }">{{ data.orders.length }} orders</template>
  </StaticIsland>
</template>
```

```tsx [React]
import { StaticIsland } from '@fydellys/inertia-static/react'

export function Orders() {
  return (
    <StaticIsland<{ orders: Order[] }> url="/api/me/orders" when="visible" fallback={<span>Loading…</span>}>
      {({ data }) => <span>{data?.orders.length} orders</span>}
    </StaticIsland>
  )
}
```

:::

```php
// routes/web.php: a regular dynamic route
Route::get('/api/me/orders', fn (Request $request) => ['orders' => $request->user()?->orders ?? []]);
```

## Reference

| Prop | Default | |
|---|---|---|
| `url` | — | the island's endpoint |
| `when` | `'mount'` | `'mount'`: load when mounted; `'visible'`: load the first time it scrolls into view |
| `buffer` | `0` | with `when="visible"`: start loading this many pixels before it is visible |
| `as` | `'div'` | element that wraps the content (and is watched for visibility) |
| `init` | — | extra `fetch` options (cookies are always sent) |
| `refreshAfterMutations` | `true` | reload after sign in, sign out and form submissions, once loaded |
| `fallback` | — | React: shown until the data is loaded (Vue: the `fallback` slot) |
| `children` | — | React: a node, or a function of `{ data, loading, error, load }` (Vue: the default slot) |

Slots (Vue) / render props (React): `{ data, loading, error, load }`. The fallback shows until the data arrives.

## Good to know

- It never runs during SSR: the cached HTML has the fallback, the same for every visitor.
- Use it instead of `<WhenVisible>` for user data. `<WhenVisible>` reloads the static page itself, which never
  sees the signed-in user.
- Without `IntersectionObserver`, it loads right away.
