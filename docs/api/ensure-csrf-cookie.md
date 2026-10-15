# `ensureCsrfCookie()`

Fetch the `XSRF-TOKEN` cookie before a `fetch()` call of your own from a static page. Inertia forms do not need it:
the client handles them ([CSRF](../guide/islands.md#csrf)).

```ts
import { ensureCsrfCookie } from '@fydellys/inertia-static/vue'

await ensureCsrfCookie('/__inertia/csrf')
```

## Reference

```ts
ensureCsrfCookie(url = '/sanctum/csrf-cookie', cookieName = 'XSRF-TOKEN'): Promise<void>
```

`url` is any GET route of the `web` group: `/__inertia/csrf` (always available) or Sanctum's
`/sanctum/csrf-cookie`.

## Good to know

- Only Laravel 12 needs the token for same-origin requests. Laravel 13 accepts them without it.
- Concurrent calls share one request. It does nothing when the cookie already exists.
