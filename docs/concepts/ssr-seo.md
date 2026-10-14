# SSR & SEO

Every cached HTML page comes from the official Inertia SSR. The package does not render anything itself.

- **SSR down** → the HTML is a client-side shell: it is sent with `no-store` and logged
  (`ssr.required = true`, the default). The CDN keeps serving the previous good copy (`stale-if-error`) until
  SSR is back.
- Set `INERTIA_STATIC_SSR_REQUIRED=false` only if you do not run SSR and accept caching CSR shells.

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

## When SSR is down

The page is still served, without SSR and with `no-store`, so the CDN never stores it. Once SSR is back, the page is
cached again.

## Deferred props

Deferred props on a static page are fetched by a partial reload, which always goes to the origin, uncached. By
default (`deferred_props = throw`) the package throws in that case, so you resolve them during the static render.
Set it to `allow` to keep the page cacheable and fetch the deferred props from the origin.

## Sitemap

`sitemap.xml` and `robots.txt` are yours to provide: the package does not generate them.
