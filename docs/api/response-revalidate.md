# `->revalidate()`, `->staleWhileRevalidate()`, `->staleIfError()`, `->expire()`

Override the cache lifetimes of **one response**, from the controller.

```php
return Inertia::render('Post/Show', ['post' => $post])
    ->revalidate($post->isBreaking() ? 60 : 3600)
    ->staleIfError(604800);
```

## Reference

```php
Inertia\Response::revalidate(?int $seconds): Inertia\Response             // CDN TTL
Inertia\Response::expire(?int $seconds): Inertia\Response                 // stale-while-revalidate = expire - revalidate
Inertia\Response::staleWhileRevalidate(?int $seconds): Inertia\Response   // explicit value, overrides expire
Inertia\Response::staleIfError(?int $seconds): Inertia\Response
```

They take the same values as the [route options](./route-isr#parameters) of the same name. Each value comes from the
response method, else the route option, else `config('inertia-static.defaults')`.

## Good to know

- They only apply to `isr` and `static` routes; on dynamic routes they are ignored.
- They apply to both URLs of the page (HTML and page JSON).
- Browsers always get `Cache-Control: public, max-age=0, must-revalidate`: only the CDN keeps the page for
  `revalidate` seconds.
- The exact headers depend on the CDN: see [Headers](../reference/headers.md).
