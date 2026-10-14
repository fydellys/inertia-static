# `->revalidate()`, `->staleWhileRevalidate()`, `->staleIfError()`

Override the cache lifetimes for **one response**, from the controller.

```php
return Inertia::render('Post/Show', ['post' => $post])
    ->revalidate($post->isBreaking() ? 60 : 3600)
    ->staleWhileRevalidate(86400)
    ->staleIfError(604800);
```

## Reference

```php
Inertia\Response::revalidate(?int $seconds): Inertia\Response
Inertia\Response::staleWhileRevalidate(?int $seconds): Inertia\Response
Inertia\Response::staleIfError(?int $seconds): Inertia\Response
Inertia\Response::expire(?int $seconds): Inertia\Response   // stale-while-revalidate = expire - revalidate
```

| Method | Header directive (driver dependent) |
|---|---|
| `revalidate(3600)` | `max-age=3600` in `Cloudflare-CDN-Cache-Control` / `Surrogate-Control`, `s-maxage=3600`, `X-Accel-Expires: 3600` |
| `staleWhileRevalidate(86400)` | `stale-while-revalidate=86400` |
| `staleIfError(604800)` | `stale-if-error=604800` |

### Precedence

response macro → route option → `config('inertia-static.defaults')`

## Good to know

- They only apply to `isr` / `static` routes; on dynamic routes they are ignored.
- They apply to both URLs of the page (HTML and page JSON).
- Browsers always get `max-age=0, must-revalidate` (cheap `304`s); only the CDN keeps the page for `revalidate`.
