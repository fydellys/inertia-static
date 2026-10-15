# `ensureCsrfCookie()`

Fetch the `XSRF-TOKEN` cookie before a `fetch()` call of your own that changes data from a static page.

```ts
import { ensureCsrfCookie } from '@fydellys/inertia-static/vue'

await ensureCsrfCookie('/__inertia/csrf')

const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

await fetch('/newsletter', { method: 'POST', headers: { 'X-XSRF-TOKEN': token }, body: new FormData(form) })
```

## Reference

```ts
ensureCsrfCookie(url = '/sanctum/csrf-cookie', cookieName = 'XSRF-TOKEN'): Promise<void>
```

`url` is any GET route of the `web` group: `/__inertia/csrf` (registered by the package) or Sanctum's
`/sanctum/csrf-cookie`.

## Good to know

- Inertia forms and visits do not need it: the client fetches the cookie by itself when needed
  ([CSRF](../guide/islands.md#csrf)). Neither does axios, which sends the cookie as `X-XSRF-TOKEN` on its own.
- Only Laravel 12 needs the token for same-origin requests. Laravel 13 accepts them without it.
- It does nothing when the cookie already exists, and concurrent calls share one request.
- It rejects when the request fails.
- Exported from `/vue`, `/react`, `/inertia2` and `/inertia2/react`.
