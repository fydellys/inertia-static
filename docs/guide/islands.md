# Islands, forms & CSRF

Static pages are shared by every visitor. Anything user-specific is loaded **in the browser** from a dynamic
endpoint: an *island*.

## `useStaticIsland()`

::: code-group

```vue [Vue]
<script setup lang="ts">
import { useStaticIsland } from '@fydellys/inertia-static/vue'

const { data, loading, error, load } = useStaticIsland<{ user: { name: string } | null }>('/api/me')
</script>

<template>
  <span v-if="loading">…</span>
  <span v-else-if="data?.user">Hello, {{ data.user.name }}</span>
  <a v-else href="/login">Sign in</a>
</template>
```

```tsx [React]
import { useStaticIsland } from '@fydellys/inertia-static/react'

export function Greeting() {
  const { data, loading } = useStaticIsland<{ user: { name: string } | null }>('/api/me')

  if (loading) return <span>…</span>

  return data?.user ? <span>Hello, {data.user.name}</span> : <a href="/login">Sign in</a>
}
```

:::

On Inertia 2, import it from `@fydellys/inertia-static/inertia2` (Vue) or `@fydellys/inertia-static/inertia2/react`.

- Fetches on mount with the visitor's cookies, never during SSR, so the cached HTML is identical for everyone.
- **Reloads after every non-GET Inertia visit** (sign in, sign out, forms), even when the page component is
  reused. Disable it with `refreshAfterMutations: false`.
- A failed request (non-2xx) sets `error`. Call `load()` to fetch again.
- Options: `immediate` (default `true`), `initial`, `init` (extra `fetch` options), `refreshAfterMutations`.
  See [`useStaticIsland()`](../api/use-static-island.md).

The endpoint is a regular dynamic route. Nothing else is needed: Laravel already answers with
`Cache-Control: private`, which CDNs never store.

```php
Route::get('/api/me', fn (Request $request) => [
    'user' => $request->user()?->only('id', 'name'),
]);
```

## Put islands in a persistent layout

If the layout is rendered **inside** each page, it is mounted again on every visit and the island fetches again on
every navigation. Use an Inertia persistent layout: it is mounted once and the island loads once.

::: code-group

```vue [Vue]
<!-- Pages/About.vue -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import Layout from '../Layouts/Layout.vue'

defineOptions({ layout: Layout })   // persistent: kept across visits
</script>

<template>
  <Head title="About" />
  <h1>About</h1>
</template>
```

```tsx [React]
// Pages/About.tsx
import { Head } from '@inertiajs/react'
import Layout from '../Layouts/Layout'

export default function About() {
  return (
    <>
      <Head title="About" />
      <h1>About</h1>
    </>
  )
}

About.layout = (page: React.ReactNode) => <Layout>{page}</Layout>   // persistent: kept across visits
```

:::

## Forms on static pages

Both Inertia ways of submitting a form work on a static page, with **no CSRF code** (see [CSRF](#csrf)):

| | `useForm` / `<Form>` → `return back()` | `useHttp` (Inertia 3) → JSON |
|---|---|---|
| Request | Inertia visit (POST), redirect | plain JSON request |
| Validation errors | `form.errors`, as on any page | `422` in the response |
| Success message | `Inertia::flash()` (page `flash`) | the JSON you return |
| Best for | forms that also exist on dynamic pages | static pages, newsletter, contact, search |

```php
Route::post('/newsletter', function (Request $request) {
    $data = $request->validate(['email' => ['required', 'email']]);

    Inertia::flash('newsletter', "Subscribed {$data['email']}");   // shown on static pages too

    return back();
});

Route::post('/api/newsletter', function (Request $request) {
    $data = $request->validate(['email' => ['required', 'email']]);

    return response()->json(['message' => "Subscribed {$data['email']}"]);
});
```

```vue
<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3'

const form = useForm({ email: '' })      // form.post('/newsletter')      → form.errors / $page.flash.newsletter
const http = useHttp({ email: '' })      // http.post('/api/newsletter')  → http.errors / resolved JSON
</script>
```

`return back()`, `redirect()->route(...)` and logout redirects to a static page all work: the visitor gets their
own validation errors and Inertia flash data, while the cached page stays identical for everyone.

Flash data shared through `HandleInertiaRequests::share()` (for example `->with('status', ...)`) is not a public
shared prop, so it does not reach static pages: use `Inertia::flash()`.

### CSRF

Nothing to do: Inertia forms (`useForm`, `<Form>`, `router.post`, `useHttp`) work on static pages on Laravel 12
and 13.

- **Laravel 13** accepts same-origin requests without a token.
- **Laravel 12**: static pages carry no CSRF cookie, so the client fetches it from `/__inertia/csrf` before the
  first submission, and Inertia sends it. Force this on or off with `INERTIA_STATIC_CSRF_COOKIE` (`csrf_cookie`).

For your own `fetch()` calls, use [`ensureCsrfCookie()`](../api/ensure-csrf-cookie.md).
