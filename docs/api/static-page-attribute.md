# `#[StaticPage]`

The attribute version of [`->isr()` / `->static()`](./route-isr), for controller methods or classes.

```php
use Fydellys\InertiaStatic\Attributes\StaticPage;

class PostController
{
    #[StaticPage(mode: 'isr', revalidate: 3600, paths: PostPaths::class, tags: ['posts'])]
    public function show(Post $post) { /* ... */ }
}
```

## Reference

```php
#[StaticPage(
    RenderMode|string $mode = 'isr',     // 'isr' | 'static' | 'dynamic'
    ?int $revalidate = null,
    ?string $paths = null,
    array $tags = [],
    array $query = [],
    ?int $staleWhileRevalidate = null,
    ?int $staleIfError = null,
)]
```

The parameters are the same as [`->isr()`](./route-isr#parameters).

## Good to know

- On the class, it applies to every action of the controller. On a method, it overrides the class.
- A route that already uses a macro keeps the macro: the macro wins.
- Attributes are read once the application has booted, so they also work with `route:cache`.
