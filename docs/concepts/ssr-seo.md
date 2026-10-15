# SSR & SEO

SSR is optional. Without it, pages are cached as Inertia renders them (the page data and an empty root element).
With [Inertia SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering), the cached HTML contains the
rendered page, which is what search engines and social previews read.

If your app uses SSR and the SSR server is down, Inertia sends the HTML without its server-rendered content (it only
appears once JavaScript runs). That copy is cached like any other until it expires or is purged. Set
`ssr.required` to `true` to send it with `no-store` instead (logged), so the CDN keeps the last good page.

## Head tags

Use Inertia's `<Head>` for `<title>`, meta tags, canonical and Open Graph. SSR renders them into the cached HTML.
Build absolute URLs on the **server**, from `APP_URL`, never from the request host: the cached page is served to
every visitor.

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

By default (`deferred_props = throw`) a static page that uses deferred props throws, so you resolve them during
the static render. Set it to `allow` to keep the page cacheable: the client then fetches the deferred props from
the never-cached live URL.

## Sitemap

`sitemap.xml` and `robots.txt` are yours to provide: the package does not generate them.
