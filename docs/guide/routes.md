# Static & ISR routes

## Route macros

```php
Route::get('/about', AboutController::class)->static(revalidate: 600);
Route::get('/blog', [PostController::class, 'index'])->isr(revalidate: 60, query: ['page']);
Route::get('/admin', AdminController::class)->dynamic(); // explicit, the default
```

Every macro accepts the same named arguments:

| Argument | Type | Default | Meaning |
|---|---|---|---|
| `revalidate` | `int` (seconds) | `defaults.revalidate` (3600) | Edge TTL. After it, the CDN serves stale while it refreshes. |
| `staleWhileRevalidate` | `int` | `defaults.stale_while_revalidate` (86400) | How long a stale page may be served while revalidating. |
| `staleIfError` | `int` | `defaults.stale_if_error` (604800) | How long a stale page may be served when the origin errors. |
| `tags` | `string[]` | `[]` | Cache tags of every response of the route. |
| `query` | `string[]` | `[]` | Query parameters that are part of the cache key (everything else is removed with a 301). |
| `paths` | `class-string<StaticPathsProvider>` | `null` | Concrete pages of a parameterized route (warmup, export). Required for `static` routes with parameters. |

What the macro does:

- stores the options in the route action (compatible with `route:cache`);
- removes the session pipeline (`strip_middleware`: cookies encryption, queued cookies, `StartSession`,
  `AuthenticateSession`, `ShareErrorsFromSession`, CSRF);
- adds the `inertia-static` route middleware (shared props whitelist and leak detector).

Validation is strict: only `GET` routes can be static, `revalidate` cannot be negative, the paths provider must
exist, and `static` routes with parameters need a paths provider (the exporter needs a finite list).

## Attribute

```php
use Fydellys\InertiaStatic\Attributes\StaticPage;

class PostController
{
    #[StaticPage(mode: 'isr', revalidate: 3600, paths: PostPaths::class, tags: ['posts'])]
    public function show(Post $post) { /* ... */ }
}
```

The attribute can also be put on the controller class. Attributes are applied once the application booted, so
they work with `route:cache` too.

## Static paths (`getStaticPaths`)

```php
use Fydellys\InertiaStatic\Contracts\StaticPathsProvider;

class PostPaths implements StaticPathsProvider
{
    public function paths(): iterable
    {
        foreach (Post::published()->orderBy('id')->lazy() as $post) {
            yield ['post' => $post->slug];
        }
    }
}
```

Each yielded array holds the route parameters. Providers are resolved from the container (constructor
injection works) and consumed lazily. `inertia-static:warm --limit=N` caps the number of pages per route.

::: warning Infinite paths
Pagination and filters can produce unbounded URLs. Whitelist only the query parameters that matter
(`query: ['page']`) and keep providers finite.
:::

## Listing

```bash
php artisan inertia-static:routes
php artisan inertia-static:routes --json   # the client manifest
```
