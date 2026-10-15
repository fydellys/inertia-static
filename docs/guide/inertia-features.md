# Inertia features

Inertia's client features work on static and ISR pages, on Inertia 2 and 3, with Vue and React. The cached page
is the same for every visitor; anything that loads data after the page goes to the origin and is never cached.

| Feature | On a static page |
|---|---|
| `<Link>`, back/forward, `prefetch` | served from the CDN (cached page JSON) |
| `<Deferred>` / `Inertia::defer()` | loaded from the origin after the page, never cached. Needs `deferred_props = allow` |
| `<WhenVisible>` / `Inertia::optional()` | loaded from the origin when it becomes visible, never cached |
| `<InfiniteScroll>` / `Inertia::scroll()` | next pages loaded from the origin, never cached |
| Partial reloads (`only`, `reset`), `router.reload()`, `usePoll` | from the origin, never cached |
| `Inertia::merge()` (append, `reset`) | merged in the browser, as usual |
| `Inertia::once()` | works; a cached page always contains the once prop |
| `useRemember` | restored on back/forward, as usual |
| `Inertia::encryptHistory()` | works (the page needs HTTPS or `localhost`) |
| `<Form>`, `useForm`, `useHttp` | sent to the origin; see [User data, forms & CSRF](./islands.md) |
| Instant visits, optimistic updates (Inertia 3) | work; the visit then loads the cached page JSON |

## Deferred props

A static page with deferred props throws by default, so the content you want indexed is not left out of the cached
HTML by accident. To load them in the browser instead, allow them:

```dotenv
INERTIA_STATIC_DEFERRED_PROPS=allow
```

```php
Route::get('/pricing', PricingController::class)->isr(revalidate: 3600);

return Inertia::render('Pricing', [
    'plans' => Plan::all(),                              // in the cached page
    'stats' => Inertia::defer(fn () => Stats::today()),  // loaded after the page
]);
```

## WhenVisible

```php
'comments' => Inertia::optional(fn () => $post->comments),
```

```vue
<WhenVisible data="comments">
  <template #fallback>Loading…</template>
  <Comments :comments="comments" />
</WhenVisible>
```

::: warning Vue with SSR
Use `@inertiajs/vue3` 2.3.20+ (or 3.0.1+). Older versions crash the SSR server when they render `<WhenVisible>`.
:::

## Infinite scroll

Add the page parameter to the route's `query`, so `/blog?page=2` opened directly is cached as its own page:

```php
Route::get('/blog', BlogController::class)->isr(revalidate: 60, query: ['page']);

return Inertia::render('Blog', [
    'posts' => Inertia::scroll(fn () => Post::latest()->paginate(10)),
]);
```

```vue
<InfiniteScroll data="posts">
  <PostCard v-for="post in posts.data" :key="post.id" :post="post" />
</InfiniteScroll>
```

## Once props

`Inertia::once()` works as usual. Every cached page contains the once prop, whatever the visitor already has.

## Keep user data out of these props

Deferred, optional and scroll props are rendered by the origin for the visitor who asks, but **with the static
page's rules**: no session, no user. Load user data with an [island](./islands.md).
