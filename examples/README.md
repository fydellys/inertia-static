# Demo & E2E

A Laravel 13 + Inertia 3 + Vue 3.5 + SSR app using `fydellys/inertia-static`, behind a "CDN" (nginx
`proxy_cache` + `ngx_cache_purge`), driven by [playwright-cli](https://github.com/microsoft/playwright-cli).

```
browser (playwright-cli, WSLg) ──► cdn (nginx :8088) ──► app (php artisan serve + SSR + queue worker)
```

| Page | Mode | What it shows |
|---|---|---|
| `/` | static | `renderedAt` frozen by the cache, prefetch link, polling button |
| `/about` | static, `revalidate: 10` | time-based revalidation (STALE → background refresh) |
| `/blog?page=N` | isr (`query: ['page']`) | pagination in the cache key, tag `posts` |
| `/blog/{slug}` | isr + `PostPaths` + tags | `->revalidate(120)->tags(['posts', 'post:{id}'])` |
| `/login`, `/dashboard` | dynamic | session, `auth`, `flash`, private data, "rename post" (on-demand purge) |
| `/api/me` | dynamic | island consumed by `useStaticIsland()` |
| `/leak` | isr | reads `auth()->user()`: the leak detector must stop it |

The "CDN" is **hostile on purpose**: it strips `X-Inertia` from `/__inertia/*` responses (the trigger of
[inertiajs/inertia#1923](https://github.com/inertiajs/inertia/issues/1923)). It only stores responses marked
`X-Inertia-Static-Cache: cacheable`, which is how you would write a Cloudflare / Varnish rule. Every page has a
**network panel** showing the status, CDN status (HIT/MISS/STALE), the URL on the wire, whether `x-inertia`
arrived, origin hits (counted by a demo middleware) and Inertia errors.

## Static paths in the demo

`/blog/{post:slug}` is an `isr` route with a paths provider ([`app/StaticPaths/PostPaths.php`](demo/app/StaticPaths/PostPaths.php)):

```php
// routes/web.php
Route::get('/blog/{post:slug}', [PageController::class, 'post'])->name('blog.show')->isr(paths: PostPaths::class);

// app/StaticPaths/PostPaths.php
public function paths(): iterable
{
    foreach (Post::query()->orderBy('id')->lazy() as $post) {
        yield ['post' => $post->slug];      // /blog/post-1, /blog/post-2, …
    }
}
```

```bash
./run php artisan inertia-static:warm --only=blog.show --dry-run   # the 12 posts: HTML + page JSON URLs
./run php artisan inertia-static:warm --only=blog.show             # pre-fill the CDN
```

After the warmup, the first visit to any post is already a `HIT` (E2E §9). Full guide:
[Static paths](https://fydellys.github.io/inertia-static/guide/static-paths).

## Running

```bash
# images (once)
docker build -t inertia-static-php -f docker/php.Dockerfile docker
docker build -t inertia-static-app -f docker/app.Dockerfile docker
docker build -t inertia-static-playwright -f examples/demo-docker/playwright.Dockerfile examples/demo-docker

# app
cd examples && ./run composer install && ./run npm install
./run php artisan migrate --seed && ./run php artisan inertia-static:manifest && ./run npm run build

# stack (http://localhost:8088)
cd demo-docker && docker compose up -d --build

# E2E with visible windows (Chromium + Firefox through WSLg)
./e2e.sh
HEADED= ./e2e.sh     # headless
./pw open http://localhost:8088/ --headed --browser=chromium   # ad-hoc commands
```

If you restart the `cdn` container, restart `browser` too: it shares the CDN's network namespace.

## Checklist (`e2e.sh`, 105 checks)

| § | Scenario |
|---|---|
| 2 | Warm CDN, new visitor: initial HTML `HIT`, 9 transitions from the cache, **0 page hits at the origin**, versioned JSON URLs, `X-Inertia` stripped by the CDN and still no Inertia error / modal |
| 3 | Signed in: dashboard shows private data; static pages carry no `auth`/`flash`/`csrf`; the name only shows in the island |
| 4 | Anonymous right after: no "Alice" in HTML/props/JSON, no `Set-Cookie`, no DevTools id in the cached HTML |
| 5 | Sign out from a static page redirecting to the same URL: the island refreshes |
| 5b | Hover prefetch uses the JSON URL and the click makes no new request; polling goes to the origin as an uncached partial reload |
| 6 | Time-based: `MISS` → `HIT` → TTL expires → `STALE` served instantly → `HIT` with new content (HTML and JSON) |
| 7 | On demand: rename a post → `HasStaticTags` → debounced queued purge → HTML, JSON and the `posts` listing are `MISS` with the new title |
| 8 | `inertia-static:purge` `--url`, `--tags`, `--prefix`, `--all` (HTML + JSON, untouched pages stay `HIT`) and `--warm` |
| 9 | `inertia-static:warm`: pages are a `HIT` on the first visit |
| 10 | 404 short TTL, non-canonical query → 301, `Authorization` → bypass, ETag → 304 without body, leak detector, SSR down → `no-store` and back |
| 10b | `X-Inertia-Static-Cache` header; "headers only" mode without credentials: no purge jobs, purge command refuses, pages still cached by TTL |
| 11 | Deploy mid-session (new build + `purge --all`): old window gets 409, full reload, continues on the new version |
| 12 | Firefox: transitions, back/forward, leaving the site and coming back: no errors, HTML (not raw JSON) |
