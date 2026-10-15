# Static paths

A route with parameters (`/blog/{post:slug}`) has no concrete pages until someone asks for them. A **static paths
provider** lists them.

## When do I need one?

| Route | Provider |
|---|---|
| `/about`, `/blog` (no parameters) | not needed: there is exactly one page |
| `->isr()` with parameters | **optional**. Pages are rendered and cached on their first visit anyway; the provider lets `inertia-static:warm` pre-fill the CDN |
| `->static()` with parameters | **required**: every page is known in advance, and `inertia-static:warm` pre-renders all of them |

The provider is **not** called on regular requests. Only `inertia-static:warm` uses it.

## A provider in 3 steps

### 1. Create the class

```php
// app/StaticPaths/PostPaths.php
namespace App\StaticPaths;

use App\Models\Post;
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

Each yielded array maps **route parameter names** to values. The key is the parameter name (`post`), not the
binding field: for `{post:slug}` the key is still `post`, and the value is what goes in the URL (the slug).

### 2. Attach it to the route

```php
Route::get('/blog/{post:slug}', [PostController::class, 'show'])
    ->isr(revalidate: 3600, paths: PostPaths::class);
```

Or with the attribute:

```php
#[StaticPage(mode: 'isr', revalidate: 3600, paths: PostPaths::class)]
public function show(Post $post) { /* ... */ }
```

### 3. Use it

```bash
$ php artisan inertia-static:routes
+--------------+------------+--------+------------+-------+------+---------------------------+
| URI          | Name       | Mode   | Revalidate | SWR   | Tags | Paths                     |
+--------------+------------+--------+------------+-------+------+---------------------------+
| /blog/{post} | blog.show  | isr    | 3600       | -     |      | App\StaticPaths\PostPaths |
+--------------+------------+--------+------------+-------+------+---------------------------+

$ php artisan inertia-static:warm --only=blog.show --limit=2 --dry-run
https://example.com/blog/post-1
https://example.com/__inertia/<version>/blog/post-1.json
https://example.com/blog/post-2
https://example.com/__inertia/<version>/blog/post-2.json

$ php artisan inertia-static:warm --only=blog.show      # requests them: the CDN is warm
```

Every page is warmed on both of its URLs: the HTML (first visits) and the page JSON (SPA navigation).

## Examples

### Several parameters

```php
// Route::get('/{locale}/docs/{section}/{page}', ...)->static(paths: DocsPaths::class);
class DocsPaths implements StaticPathsProvider
{
    public function paths(): iterable
    {
        foreach (['en', 'pt'] as $locale) {
            foreach (Doc::published()->cursor() as $doc) {
                yield ['locale' => $locale, 'section' => $doc->section, 'page' => $doc->slug];
            }
        }
    }
}
```

### Optional parameters

```php
// Route::get('/products/{category?}', ...)->isr(paths: CategoryPaths::class);
public function paths(): iterable
{
    yield [];                                        // /products
    foreach (Category::pluck('slug') as $slug) {
        yield ['category' => $slug];                 // /products/shoes
    }
}
```

### Only the most popular pages

You do not have to list everything. With `isr`, unlisted pages are rendered on their first visit:

```php
public function paths(): iterable
{
    return Post::orderByDesc('views')->limit(100)->pluck('slug')->map(fn ($slug) => ['post' => $slug]);
}
```

### Paginated listings (by path)

A `static` route must list every page, so paginate it with a path parameter:

```php
// Route::get('/blog/page/{page}', ...)->static(paths: BlogPages::class);
public function paths(): iterable
{
    $last = (int) ceil(Post::published()->count() / 10);

    foreach (range(1, $last) as $page) {
        yield ['page' => $page];
    }
}
```

For `isr` routes, `?page=2` works too: whitelist it with `query: ['page']`.

### Dependencies

Providers are resolved from the container, so constructor injection works:

```php
class ProductPaths implements StaticPathsProvider
{
    public function __construct(private CatalogApi $catalog) {}

    public function paths(): iterable
    {
        foreach ($this->catalog->allSkus() as $sku) {
            yield ['product' => $sku];
        }
    }
}
```

## Good to know

- **Be lazy.** Return a generator, `lazy()`, `cursor()` or a collection. Providers are consumed one item at a time.
- **Keep it finite.** Filters, search queries and infinite pagination must not become static paths.
  `--limit=N` caps the pages per route.
- **Values are URL-encoded** for you (`'a b'` becomes `a%20b`). A missing required parameter throws
  `InvalidStaticRoute`.
- **`static` routes with parameters and no provider** throw `InvalidStaticRoute` when the route is registered.
- **New content** does not need the provider: the first visit renders and caches it, and
  [`HasStaticTags`](./revalidation.md#automatic-purges-from-models) purges it when it changes.
