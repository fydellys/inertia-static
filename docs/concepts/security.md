# Security

A private response in a shared cache would show one visitor's data to everyone. Static routes are built so that
this cannot happen, and fall back to `no-store` when in doubt.

## What is protected

On `isr` and `static` routes:

- **No session, no user.** The session, cookie and CSRF middleware are removed from the route
  (`strip_middleware`), and the request cookies are dropped before routing. `auth()->user()` and
  `$request->user()` can never resolve a user, even from a remember-me cookie.
- **Only public shared props.** Props shared by `HandleInertiaRequests` (`auth`, `flash`, …) are dropped unless
  listed in `public_shared_props`; `errors` is always `{}`.
- **No cookies in responses.** Every `Set-Cookie` is removed, and `Cookie` is removed from `Vary`.
- **No forwarded host headers.** `X-Forwarded-Host`, `X-Forwarded-Port`, `X-Forwarded-Prefix` and `Forwarded` are
  removed and `X-Forwarded-Proto` is set to the scheme of the base URL, so a client cannot change the URLs built by
  `url()`, `route()`, `asset()` or `@vite` in a cached page.
- **One URL per page.** Unknown query parameters, a trailing slash or a different percent-encoding
  (`/blog/%68ello`) redirect to the canonical URL ([Cache key](./cache-key.md)).
- **GET only.** A static route must be a `GET` route (`Route::get`): declaring `isr` / `static` on a route that
  accepts other methods throws, because that route has no CSRF protection.
- **Never cached:** requests with an `Authorization` header, server errors, partial reloads, and Inertia DevTools
  ids (DevTools is off on static routes). If your app uses SSR and the SSR server is down, the page without its
  server-rendered content is not cached either, so the CDN keeps the last good copy.
- **Page JSON only for static routes.** `/__inertia/...` answers only for `isr` / `static` routes (`404` otherwise),
  and returns what the public HTML page already shows.

## What you do

```php
// config/inertia-static.php
'public_shared_props' => ['appName', 'locale'],
```

1. List the shared props that are the same for every visitor in `public_shared_props`.
2. Load user data in the browser from dynamic endpoints: [islands](../guide/islands.md).
3. Fix every leak the detector reports (below), or make that route dynamic.

## Leak detector

While a static page renders, any read of private data is reported:

| Access | Reported as |
|---|---|
| `auth()->user()`, `Auth::id()`, `auth()->check()`, `request()->user()`, `@auth`, gates | `auth` |
| `session(...)`, `Session::get()`, `Session::put()` | `session` |
| `Cookie::queue()`, `cookie(...)` | `cookie` |
| `$request->bearerToken()`, `header('Authorization')`, the `Cookie` and `X-XSRF-TOKEN` headers | `header` |

`$request->session()` throws right away: static requests have no session. `$request->cookie()` is not reported: it
always returns `null`, the anonymous value.

The mode is set by `leak_detector.mode` (`INERTIA_STATIC_LEAK_DETECTOR`):

- `throw` (default in `local` and `testing`): throws `StaticRenderViolation`, naming the accessor;
- `auto` (default elsewhere): the response is sent with `no-store`, the violation is logged and, if the page
  rendered successfully, the route **becomes dynamic** from the next request on (session restored). A leak in a
  partial reload only (a `<WhenVisible>`, deferred or scroll prop reading the user) is logged and served with
  `no-store`, but the page stays static: the cached render never runs that code;
- `log`: logs and sends `no-store`, but the route stays static.

### When a route was turned dynamic

`php artisan inertia-static:routes` shows it as `dynamic (downgraded: …)`, and `inertia-static:doctor` warns about
it. Fix the page (move the private data to an island), deploy, then make it static again:

```bash
php artisan inertia-static:routes --reset-downgrades
```

Restart Octane and queue workers afterwards.
