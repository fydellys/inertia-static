# Preview mode

While a signed cookie is present, static pages **skip the cache** and render fresh.
Use it to show drafts or unpublished content to editors.

## Enable it from your own route

You decide who can preview, with your usual middleware:

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

Route::get('/preview/enable', fn (Request $request) => redirect($request->query('to', '/'))
    ->withCookie(StaticCache::enablePreview(minutes: 60)))
    ->middleware(['auth', 'can:preview']);

Route::get('/preview/disable', fn () => redirect('/')->withCookie(StaticCache::disablePreview()));
```

## Show drafts

```php
public function show(Post $post)
{
    abort_unless($post->isPublished() || StaticCache::isPreview(), 404);

    return Inertia::render('Post/Show', ['post' => $post, 'preview' => StaticCache::isPreview()]);
}
```

## What happens

| | Normal visitor | Preview cookie |
|---|---|---|
| HTML | from the CDN | origin, `no-store` (`X-Inertia-Static-Reason: preview`) |
| SPA visits | cached page JSON | never-cached live endpoint |
| `StaticCache::isPreview()` | `false` | `true` |

`enablePreview()` lasts `preview.minutes` (default `60`) when called without `minutes`. The cookie is signed with
your `APP_KEY` and expires on its own; a forged or expired cookie is ignored. It is not `HttpOnly` (the client
reads it), and it only grants "skip the cache", never any access.

## Configure the CDN

The CDN must not answer preview requests from its cache:

| CDN | Rule |
|---|---|
| Cloudflare | Cache Rule: `http.cookie contains "inertia_static_preview="` → **Bypass cache** |
| nginx | `proxy_cache_bypass $cookie_inertia_static_preview;` and `proxy_no_cache $cookie_inertia_static_preview;` |
| Varnish | `if (req.http.Cookie ~ "inertia_static_preview=") { return (pass); }` (in `vcl_recv`) |

The cookie name is configurable (`inertia-static.preview.cookie`).
