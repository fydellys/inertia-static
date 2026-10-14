# `ensureCsrfCookie()`

Fetch an `XSRF-TOKEN` cookie before submitting a form from a static page.

```ts
import { ensureCsrfCookie } from '@fydellys/inertia-static/vue'

await ensureCsrfCookie()          // GET /sanctum/csrf-cookie once
```

## Reference

```ts
ensureCsrfCookie(url = '/sanctum/csrf-cookie', cookieName = 'XSRF-TOKEN'): Promise<void>
```

## Good to know

- **Laravel 13 does not need it** for same-origin requests: `PreventRequestForgery` accepts them through the
  `Sec-Fetch-Site: same-origin` header that browsers send. See [Islands, forms & CSRF](../guide/islands.md#csrf).
- Use it on **Laravel 12** (`ValidateCsrfToken` only checks the token) and for cross-origin requests.
- Concurrent calls share one request. It does nothing when the cookie already exists.
