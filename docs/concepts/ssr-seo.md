# SSR & SEO

SSR is optional. Without it, pages are cached as Inertia renders them (the page data and an empty root element).
With [Inertia SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering), the cached HTML contains the
rendered page, which is what search engines and social previews read.

When SSR is enabled and the SSR server is down, pages are sent with `no-store` and logged: the CDN keeps the
previous copy until SSR is back.

## Head tags

Use Inertia's `<Head>` for `<title>`, meta tags, canonical and Open Graph. SSR renders them into the cached HTML.
Build absolute URLs on the **server**, from `APP_URL`, never from the request host: the same build serves every
environment, and the Host header is not part of the cache key.

```php
return Inertia::render('Blog/Show', [
    'post' => $post,
    'canonical' => rtrim(config('app.url'), '/')."/blog/{$post->slug}",
]);
```

```vue
<Head :title="post.title">
  <meta name="description" :content="post.excerpt" />
  <meta property="og:title" :content="post.title" />
  <link rel="canonical" :href="canonical" />
</Head>
```

## Deferred props

Deferred props on a static page are fetched by a partial reload, which always goes to the origin, uncached. By
default (`deferred_props = throw`) the package throws in that case, so you resolve them during the static render.
Set it to `allow` to keep the page cacheable and fetch the deferred props from the origin.

## Sitemap

`sitemap.xml` and `robots.txt` are yours to provide: the package does not generate them.
