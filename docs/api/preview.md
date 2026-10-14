# `enablePreview()` / `disablePreview()` / `isPreview()`

Preview mode. Guide: [Preview mode](../guide/preview.md).

```php
Route::get('/preview/enable', fn () => redirect('/')->withCookie(StaticCache::enablePreview()))->middleware('auth');
Route::get('/preview/disable', fn () => redirect('/')->withCookie(StaticCache::disablePreview()));

StaticCache::isPreview();   // true while a valid preview cookie is sent
```

## Reference

```php
StaticCache::enablePreview(?int $minutes = null): Symfony\Component\HttpFoundation\Cookie   // default preview.minutes (60)
StaticCache::disablePreview(): Symfony\Component\HttpFoundation\Cookie
StaticCache::isPreview(): bool
```
