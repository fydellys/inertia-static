# `#[StaticPage]`

The attribute version of [`->isr()` / `->static()` / `->dynamic()`](./route-isr), for controller methods or classes.

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
    RenderMode|string $mode = RenderMode::Isr,   // 'isr' | 'static' | 'dynamic'
    ?int $revalidate = null,
    ?string $paths = null,
    array $tags = [],
    array $query = [],
    ?int $staleWhileRevalidate = null,
    ?int $staleIfError = null,
    ?int $expire = null,
)]
```

The other parameters are the same as in [`->isr()`](./route-isr#parameters).
`RenderMode` is `Fydellys\InertiaStatic\Enums\RenderMode`.

## Good to know

- On the class, it applies to every action of the controller. On a method, it overrides the class.
- A route macro wins over the attribute: use `->dynamic()` to exclude one route of a class marked `#[StaticPage]`.
- It works with `php artisan route:cache`.
