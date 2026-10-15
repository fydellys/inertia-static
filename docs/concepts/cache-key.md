# Cache key normalization

Each page has one canonical URL, so it is stored once by the CDN. Any other spelling gets a cacheable `301` to the
canonical URL, on the HTML and the page JSON URLs.

## Query string

Only the parameters whitelisted on the route are kept; they are sorted and empty values are removed:

```php
Route::get('/blog', BlogController::class)->isr(query: ['page']);
```

```
GET /blog?utm_source=news&page=2&fbclid=x  →  301 Location: https://example.com/blog?page=2
```

Tracking parameters (`normalization.strip_query`: `utm_*`, `fbclid`, `gclid`, …) are removed even when
whitelisted. The client plugin sends visits with a canonical query to the cacheable JSON URL, and visits with
other parameters to the never-cached live URL.

## Trailing slash

`normalization.trailing_slash`: `never` (default), `always` or `ignore`. Configure the client with the same value
(`trailingSlash`).

## Percent-encoding

Paths are percent-encoded one way, as browsers send them: letters, digits, `-._~` and `!$&'()+,;=:@` stay plain,
every other character is encoded with uppercase escapes. `/blog/%68ello` redirects to `/blog/hello`, `/blog/a b`
to `/blog/a%20b`. Purges use the same spelling, so pass page paths decoded or encoded, as you prefer.

## Locale

Put the locale **in the URL** (`/pt/...`, `/en/...`). Never vary static pages by `Accept-Language` or by a
cookie: the CDN would serve one language to everyone.

## Absolute URLs

Purges, warmups and canonical redirects build absolute URLs from `INERTIA_STATIC_BASE_URL` (or `APP_URL`), never
from the request's `Host` or forwarded headers.
