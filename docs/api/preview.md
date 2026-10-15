# `StaticCache::enablePreview()` / `disablePreview()` / `isPreview()`

Turn preview mode on or off for the current browser: while it is on, static pages skip the cache and render fresh.
Guide: [Preview mode](../guide/preview.md).

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

Route::get('/preview/enable', fn () => redirect('/')->withCookie(StaticCache::enablePreview()))->middleware('auth');
Route::get('/preview/disable', fn () => redirect('/')->withCookie(StaticCache::disablePreview()));
```

```php
$posts = StaticCache::isPreview() ? Post::all() : Post::published()->get();
```

## Reference

```php
StaticCache::enablePreview(?int $minutes = null): Symfony\Component\HttpFoundation\Cookie   // default: preview.minutes (60)
StaticCache::disablePreview(): Symfony\Component\HttpFoundation\Cookie
StaticCache::isPreview(): bool   // true while a valid preview cookie is sent
```

## Good to know

- Protect the route that enables preview (for example with `auth`): the package does not.
- The cookie is signed with `APP_KEY`, so it cannot be forged.
- Configure your CDN to bypass its cache when the preview cookie (`inertia_static_preview`) is present.
