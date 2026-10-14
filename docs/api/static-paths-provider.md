# `StaticPathsProvider`

Lists the concrete pages of a parameterized route, like `generateStaticParams` in Next.js. Full guide:
[Static paths](../guide/static-paths.md).

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

### Returns

An iterable of arrays, one per page, mapping each **route parameter name** to its value in the URL:

| Route | Yield | Page |
|---|---|---|
| `/blog/{post:slug}` | `['post' => 'hello']` | `/blog/hello` |
| `/{locale}/docs/{page}` | `['locale' => 'en', 'page' => 'intro']` | `/en/docs/intro` |
| `/products/{category?}` | `[]` | `/products` |

## Used by

- `php artisan inertia-static:warm` (pre-fills the CDN with HTML + page JSON);
- the static exporter (planned);
- `php artisan inertia-static:routes` (shown in the *Paths* column).

It is **never** called on regular requests.

## Good to know

- Resolved from the container: constructor injection works.
- Consumed lazily: return a generator, `lazy()` or `cursor()` for large tables.
- Required for `->static()` routes with parameters, optional for `->isr()`.
