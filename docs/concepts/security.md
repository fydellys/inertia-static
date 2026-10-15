# Security

**A private response in a shared cache is the worst possible bug.** Every layer is fail-safe.

## Session stripping

On `isr` / `static` routes, these middleware are removed (subclasses included):

`EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `AuthenticateSession`, `ShareErrorsFromSession`,
`PreventRequestForgery` (Laravel 13), `ValidateCsrfToken`, `VerifyCsrfToken`.

## Cookies dropped before routing

The global middleware empties the request cookies (and the `Cookie` header) of static routes **before** the
pipeline runs. No identity can be resolved: even an eager `'user' => $request->user()` in
`HandleInertiaRequests::share()` returns `null`, and a remember-me cookie is never read.

## Forwarded host headers dropped

`X-Forwarded-Host`, `X-Forwarded-Port`, `X-Forwarded-Prefix` and `Forwarded` are removed from static requests too.
The CDN keys pages on `Host` and the URL only, so with trusted proxies these headers could otherwise change the
URLs of `url()`, `route()`, `asset()` and `@vite` in a page cached for everyone.

## One URL per page

Non-canonical spellings of a page (`/blog/%68ello`, a trailing slash, an unknown query parameter) are redirected
to the canonical URL before rendering, on the HTML and the JSON URLs.

## Public shared props only

```php
'public_shared_props' => ['appName', 'locale'],
```

Everything else shared by `HandleInertiaRequests` (`auth`, `flash`, `csrf`, …) is dropped, and `errors` is always
`{}`.

## Set-Cookie stripped

Every `Set-Cookie` is removed from static responses, and `Cookie` is removed from `Vary`. Cookies set by your
code never reach the CDN.

## Leak detector

While a static page renders, private accessors are replaced by traps:

| Access | Detected as |
|---|---|
| `auth()->user()`, `Auth::id()`, `auth()->check()`, `request()->user()`, `@auth`, gates | `auth` |
| `session(...)`, `Session::get()`, `Session::put()` | `session` |
| `Cookie::queue()`, `cookie(...)` | `cookie` |
| `$request->bearerToken()`, `header('Authorization')`, `Cookie`, `X-XSRF-TOKEN` headers | `header` |

Mode (`leak_detector.mode` / `INERTIA_STATIC_LEAK_DETECTOR`):

- `throw` (default in `local` and `testing`): throws `StaticRenderViolation` with the exact accessor;
- `auto` (default elsewhere): the route **stops being static**. The response is served with `no-store`, the violation is logged, and from the next request on the route is a
  regular dynamic route (session restored). `inertia-static:routes` shows it as `dynamic (downgraded: …)` and
  `doctor` warns. Fix the page, then run `php artisan inertia-static:routes --reset-downgrades`;
- `log`: logs and serves `no-store`, but keeps the route static.

`$request->session()` throws right away: static requests have no session at all.

Reading a request cookie (`$request->cookie()`) is not reported: cookies were already removed, so the read
deterministically returns `null`, the anonymous value.

## Never cached

- requests with `Authorization`;
- with SSR enabled, a page rendered without it (SSR server down);
- 5xx, partial reloads, the Inertia JSON on the HTML URL;
- the per-request id of Inertia DevTools: DevTools is disabled on static routes.

## Islands

User data on static pages is loaded in the browser from dynamic endpoints ([Islands](../guide/islands.md)).
