# Islands, forms & CSRF

Static pages are shared by every visitor. Anything user-specific is loaded **in the browser** from a dynamic
endpoint: an *island*.

## `useStaticIsland()`

```vue
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

- Fetches on mount (never during SSR, so the cached HTML is identical for everyone) with cookies
  (`credentials: 'same-origin'`).
- **Reloads after every non-GET Inertia visit** (sign in, sign out, forms). A sign out that redirects to the same
  page reuses the page component, so without this the island would keep showing the old user. Disable it
  with `refreshAfterMutations: false`.
- Options: `immediate` (default `true`), `initial`, `init` (extra `fetch` options), `refreshAfterMutations`.

The endpoint must be dynamic and never cached:

```php
Route::get('/api/me', fn (Request $request) => response()
    ->json(['user' => $request->user()?->only('id', 'name')])
    ->header('Cache-Control', 'no-store, private'));
```

## Put islands in a persistent layout

If the layout is rendered **inside** each page, Vue mounts it again on every visit and the island fetches
again on every navigation. Make the layout the page's parent (an Inertia persistent layout): it is mounted once and
the island loads once.

```vue
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

Verified in the demo: `/api/me` is fetched **once** for 9 navigations (it was 9 times with the layout inside the
pages).

## Forms on static pages

Both Inertia ways of submitting a form work on a static page, with **no CSRF helper** on Laravel 13:

| | `useForm` / `<Form>` → `return back()` | `useHttp` → JSON |
|---|---|---|
| Request | Inertia visit (POST), redirect | plain JSON request |
| Validation errors | from the session, through the live endpoint | `422` in the response |
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

### How `return back()` works on a static page

`back()` redirects to the static page's **HTML URL**, and the browser follows that redirect inside the XHR,
without the client plugin. A CDN ignoring `Vary: X-Inertia` would answer with the cached HTML
([inertia#1923](https://github.com/inertiajs/inertia/issues/1923)), and the validation errors live in the session,
which cached pages never read. The package handles both:

1. **Redirect rewrite.** When an Inertia visit is redirected to a static page, `Location` points to the page's
   **live** JSON URL (`/__inertia/live/{version}/{path}.json`). This also applies to `redirect()->route(...)`,
   logout, and so on.
2. **The live endpoint reads the session.** It is never cached, so it returns this visitor's validation `errors`
   and Inertia `flash` data. The cached page stays identical for everyone.

Flash data shared through `HandleInertiaRequests::share()` (for example `->with('status', ...)`) is not a public
shared prop, so it does not reach static pages: use `Inertia::flash()`.

Verified on a real Cloudflare zone, with the page cached and **no cookies at all** (so no CSRF token): errors,
flash, no modal, and a second visitor sees none of it.

### CSRF

- **Laravel 13**: `PreventRequestForgery` accepts same-origin requests through the `Sec-Fetch-Site` header that
  browsers send. No token, no helper: verified with every cookie deleted before submitting.
- **Laravel 12** (`ValidateCsrfToken`) needs the token. Call [`ensureCsrfCookie()`](../api/ensure-csrf-cookie.md)
  before submitting:

  ```ts
  import { ensureCsrfCookie } from '@fydellys/inertia-static/vue'

  await ensureCsrfCookie()     // GET /sanctum/csrf-cookie once
  form.post('/newsletter')
  ```
