# `StaticPathsProvider`

Lists the concrete pages of a parameterized route, for warming the CDN. Guide: [Static paths](../guide/static-paths.md).

```php
use Fydellys\InertiaStatic\Contracts\StaticPathsProvider;

class PostPaths implements StaticPathsProvider
{
    public function paths(): iterable
    {
        foreach (Post::query()->orderBy('id')->lazy() as $post) {
            yield ['post' => $post->slug];
        }
    }
}
```

```php
Route::get('/blog/{post:slug}', ...)->isr(paths: PostPaths::class);
```

## Reference

```php
interface StaticPathsProvider
{
    /** @return iterable<int, array<string, mixed>> */
    public function paths(): iterable;
}
```

Each item maps the **route parameter names** to their values in the URL:

| Route | Item | Page |
|---|---|---|
| `/blog/{post:slug}` | `['post' => 'hello']` | `/blog/hello` |
| `/{locale}/docs/{page}` | `['locale' => 'en', 'page' => 'intro']` | `/en/docs/intro` |
| `/products/{category?}` | `[]` | `/products` |

## Good to know

- Used by `php artisan inertia-static:warm` (pre-fills the CDN) and `inertia-static:routes`. It is never called on
  regular requests.
- Resolved from the container: constructor injection works.
- Return a generator, `lazy()` or `cursor()` for large tables.
- Required for `->static()` routes with parameters, optional for `->isr()`.
