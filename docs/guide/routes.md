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
| `expire` | `int` (seconds) | `defaults.expire` (1 year) | Total lifetime of a cached page. Stale-while-revalidate is `expire - revalidate`. |
| `staleWhileRevalidate` | `int` | `defaults.stale_while_revalidate` (`null`) | Explicit stale-while-revalidate window; overrides `expire`. |
| `staleIfError` | `int` | `defaults.stale_if_error` (604800) | How long a stale page may be served when the origin errors. |
| `tags` | `string[]` | `[]` | Cache tags of every response of the route. |
| `query` | `string[]` | `[]` | Query parameters that are part of the cache key (everything else is removed with a 301). |
| `paths` | `class-string<StaticPathsProvider>` | `null` | Concrete pages of a parameterized route, for `inertia-static:warm`. Required for `static` routes with parameters. |

On `isr` and `static` routes:

- the session, cookies and CSRF middleware listed in `strip_middleware` do not run;
- only the shared props listed in `public_shared_props` are sent;
- reading the session, the user, cookies or auth headers while rendering is reported (`leak_detector`, see
  [Security](../concepts/security.md)).

Routes are checked when they are registered. An `InvalidStaticRoute` exception is thrown when:

- the route is not `GET` only (`Route::post`, `Route::match` and `Route::any` cannot be static);
- `revalidate` is negative;
- the `paths` class does not exist;
- a `static` route has parameters but no `paths`.

## Attribute

```php
use Fydellys\InertiaStatic\Attributes\StaticPage;

class PostController
{
    #[StaticPage(mode: 'isr', revalidate: 3600, paths: PostPaths::class, tags: ['posts'])]
    public function show(Post $post) { /* ... */ }
}
```

The attribute takes the same arguments as the macros, plus `mode` (`'isr'` by default, `'static'` or
`'dynamic'`). It can also be put on the controller class. A macro on the route wins over the attribute. It works
with `route:cache`.

## Static paths

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
injection works) and consumed lazily. `inertia-static:warm --limit=N` caps the number of pages per route. See
[Static paths](./static-paths.md).

::: warning Infinite paths
Pagination and filters can produce unbounded URLs. Whitelist only the query parameters that matter
(`query: ['page']`) and keep providers finite.
:::

## Listing

```bash
php artisan inertia-static:routes
php artisan inertia-static:routes --json   # the client manifest
```
