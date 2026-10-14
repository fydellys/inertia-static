# CloudFront

## Configure

```ini
INERTIA_STATIC_CDN=cloudfront
CLOUDFRONT_DISTRIBUTION_ID=E123ABC456
AWS_ACCESS_KEY_ID=...                # optional (see Headers-only mode)
AWS_SECRET_ACCESS_KEY=...
INERTIA_STATIC_REGISTRY=database     # tag purges become path invalidations
```

The IAM user or role needs `cloudfront:CreateInvalidation` on the distribution.

## Distribution setup

In the behavior that serves your Laravel app (**Distribution → Behaviors → Edit**):

1. *Allowed HTTP methods*: **GET, HEAD, OPTIONS, PUT, POST, PATCH, DELETE** (forms and the rest of the app).
2. *Cache policy*: **UseOriginCacheControlHeaders-QueryStrings**.
3. *Origin request policy*: **AllViewer** (cookies and headers reach Laravel on dynamic routes).
4. **Save**.

Static pages are cached for their `revalidate` time. Dynamic routes are not cached: Laravel answers them with
`Cache-Control: private`.

::: warning
CloudFront caches any response with `Cache-Control: public` and a `max-age`/`s-maxage`. Do not send those headers
from routes that are not static pages.
:::

Check it with `curl -I https://example.com/` twice: `x-cache` goes from `Miss from cloudfront` to
`Hit from cloudfront`.

## Optional: serve stale pages while refreshing

By default an expired page is fetched again before it is served. To serve the old page instantly and refresh it in
the background:

1. **Policies → Response headers → Create response headers policy** → *Custom headers* → **Add header**:
   `Cache-Control` = `public, max-age=0, must-revalidate`, **Origin override** on. Attach it to the behavior.
2. Set `CLOUDFRONT_STALE=true`.

The policy keeps browsers from applying the stale time to their own cache.

## Purging

| Method | Purges |
|---|---|
| `purgeUrls()` | the given pages (HTML and page JSON) |
| `purgeTags()` | the pages recorded for the tags in the registry |
| `purgePrefix()` | every page under the path |
| `purgeAll()` | the whole distribution |

CloudFront bills invalidated paths above 1,000 per month. A prefix counts as one path.
