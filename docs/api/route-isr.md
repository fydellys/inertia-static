# `->isr()` / `->static()` / `->dynamic()`

Route macros that declare how a route is rendered and cached.

```php
Route::get('/blog/{post:slug}', [PostController::class, 'show'])
    ->isr(revalidate: 3600, tags: ['posts'], paths: PostPaths::class);
```

## Reference

```php
->isr(
    ?int $revalidate = null,
    ?string $paths = null,
    array $tags = [],
    array $query = [],
    ?int $staleWhileRevalidate = null,
    ?int $staleIfError = null,
    ?int $expire = null,
): Route

->static(/* same parameters */): Route
->dynamic(): Route
```

### Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `revalidate` | `int` seconds | `3600` (`defaults.revalidate`) | How long the CDN serves the page before revalidating it in the background. `0` = cache, but always revalidate. |
| `paths` | `class-string<StaticPathsProvider>` | `null` | Lists the concrete pages of a parameterized route. See [`StaticPathsProvider`](./static-paths-provider). |
| `tags` | `string[]` | `[]` | Cache tags of every response of the route, purgeable with [`purgeTags()`](./purge-tags). |
| `query` | `string[]` | `[]` | Query parameters that are part of the cache key. Others are removed with a `301`. |
| `staleWhileRevalidate` | `int` seconds | `expire - revalidate` | Explicit stale window; overrides `expire`. |
| `staleIfError` | `int` seconds | `604800` (`defaults.stale_if_error`) | How long a stale page may be served while the origin errors. |
| `expire` | `int` seconds | `31536000` (`defaults.expire`) | After it, a stale page is no longer served. |

### Modes

| Macro | Rendered | Cached by the CDN | `paths` |
|---|---|---|---|
| `->dynamic()` (default) | every request | never | — |
| `->isr()` | on a cache miss | yes, revalidated by time and on demand | optional |
| `->static()` | on a cache miss | yes, revalidated by time and on demand | required when the route has parameters |

## Examples

```php
// Paginated listing: one cache entry per page
Route::get('/blog', [PostController::class, 'index'])
    ->isr(revalidate: 60, query: ['page'], tags: ['posts']);
// /blog?page=2&utm_source=x  →  301  /blog?page=2

// Only on-demand revalidation: cached for a year, purged with StaticCache::purgeTags('legal')
Route::get('/legal/terms', TermsController::class)
    ->static(revalidate: 31536000, tags: ['legal']);
```

## Good to know

- Static routes have no session, cookies or CSRF: `$request->user()` is `null`. Reading private data throws in
  `local` and `testing` (see [Security](../concepts/security.md)).
- Only `GET` routes can be `isr` or `static`; a route that also accepts other verbs throws an exception.
- A negative `revalidate`, a missing `paths` class or a parameterized `static` route without `paths` also throw.
- `->dynamic()` keeps a route dynamic even when its controller has a [`#[StaticPage]`](./static-page-attribute).
- `php artisan route:cache` keeps the options. Run `php artisan inertia-static:routes` to see the effective
  configuration.
