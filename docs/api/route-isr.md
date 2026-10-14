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
| `staleWhileRevalidate` | `int` seconds | `86400` | How long a stale page may be served while it is being refreshed. |
| `staleIfError` | `int` seconds | `604800` | How long a stale page may be served while the origin errors. |

### Modes

| Macro | Rendered | Cached by the CDN | Exported |
|---|---|---|---|
| `->dynamic()` (default) | every request | never | no |
| `->isr()` | on a cache miss | yes, revalidated by time and on demand | no |
| `->static()` | on a cache miss | yes | yes (exporter, planned); needs `paths` when parameterized |

## Examples

### Time-based revalidation

```php
Route::get('/', HomeController::class)->static(revalidate: 600); // at most once every 10 minutes per edge
```

### Paginated listing

```php
Route::get('/blog', [PostController::class, 'index'])
    ->isr(revalidate: 60, query: ['page'], tags: ['posts']);
// /blog?page=2&utm_source=x  →  301  /blog?page=2   (one cache entry per page)
```

### Only on-demand revalidation

```php
Route::get('/legal/terms', TermsController::class)
    ->static(revalidate: 31536000, tags: ['legal']);   // one year; purge with StaticCache::purgeTags('legal')
```

## Good to know

- The session pipeline is removed from these routes (cookies, `StartSession`, CSRF). `$request->user()` returns
  `null`, and reading private data throws in local (see [Security](../concepts/security.md)).
- Only `GET` routes can be static. HEAD is handled like GET.
- Options live in the route action, so `php artisan route:cache` keeps them.
- Run `php artisan inertia-static:routes` to see the effective configuration.
