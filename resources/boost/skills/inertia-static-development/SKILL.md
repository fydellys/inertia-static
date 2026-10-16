---
name: inertia-static-development
description: Use when making Laravel/Inertia pages static or ISR with fydellys/inertia-static, configuring CDN caching, purging or revalidating cached pages, loading user data with islands, writing static paths providers, fixing leak detector errors (StaticRenderViolation, downgraded routes), or setting up deploy and CDN config.
compatible_agents:
  - Claude Code
  - Cursor
  - Codex
  - GitHub Copilot
tags:
  - laravel
  - php
  - inertia
  - ssg
  - isr
  - cdn
---

# inertia-static development

`fydellys/inertia-static` caches Inertia pages at a CDN. Routes marked `->isr()` or `->static()` render without
session, cookies or user, so one copy serves every visitor. Each static page has two cacheable URLs: the HTML
(`/blog/hello`) and the page JSON (`/__inertia/{version}/blog/hello.json`) used by SPA visits. A never-cached live
URL (`/__inertia/live/{version}/...`) serves reloads, partial reloads, polling and preview.

More detail:

- [references/cdn-setup.md](references/cdn-setup.md): per-driver env, CDN rules, preview bypass, deploy.
- [references/configuration.md](references/configuration.md): every config key and env var.
- [references/troubleshooting.md](references/troubleshooting.md): debug headers, reason values, symptom table.
- [references/client.md](references/client.md): client options, visit routing, TypeScript, Inertia 2/3 without the Vite plugin.
- [references/testing.md](references/testing.md): testing static routes and purges in the app.

## Installation

```bash
composer require fydellys/inertia-static
php artisan vendor:publish --tag=inertia-static-config
# Only for CDNs without tag purges (nginx, CloudFront, Bunny):
php artisan vendor:publish --tag=inertia-static-migrations
php artisan migrate
```

The service provider, middleware and `StaticCache` facade register automatically. The JS client ships inside the
Composer package; there is no npm package.

```js
// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js'

export default defineConfig({
  plugins: [laravel({ /* ... */ }), vue(), inertiaStatic()],
})
```

Vite plugin options: `manifest` (default `true`, writes `resources/js/inertia-static.json` via
`php artisan inertia-static:manifest` when Vite starts or builds), `php` (`'php'`), `artisan` (`'artisan'`),
`inertia` (`2` or `3`, detected from `node_modules`).

Client, identical on Inertia 2 and 3:

```ts
// resources/js/app.ts  (React: '@inertiajs/react' and '@fydellys/inertia-static/react')
import { createInertiaApp } from '@inertiajs/vue3'
import { withInertiaStatic } from '@fydellys/inertia-static/vue'
import manifest from './inertia-static.json'

createInertiaApp({
  ...withInertiaStatic({ manifest }),
  // resolve, setup, ...
})
```

Vue with SSR: wrap `renderToString` with `ssrRender` inside the `createServer` callback (required on Inertia 2 with
Vue 3.5.44+, harmless on Inertia 3):

```ts
// resources/js/ssr.ts
import { createInertiaApp } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import { renderToString } from 'vue/server-renderer'
import { ssrRender } from '@fydellys/inertia-static/vue'

createServer((page) => createInertiaApp({ page, render: ssrRender(renderToString), /* ... */ }))
```

TypeScript paths, use without the Vite plugin and every client option: [references/client.md](references/client.md).

Required env:

```ini
INERTIA_STATIC_BASE_URL=https://example.com   # defaults to APP_URL; must be the public URL
INERTIA_STATIC_CDN=cloudflare                 # cloudflare|fastly|bunny|cloudfront|varnish|nginx|multi|null (default null)
CLOUDFLARE_ZONE_ID=...                        # driver credentials: only needed to purge
CLOUDFLARE_API_TOKEN=...
```

Without credentials the driver runs in headers-only mode: pages are cached and expire by TTL, purges are skipped.

## Route modes

```php
use Illuminate\Support\Facades\Route;

Route::get('/about', AboutController::class)->static(revalidate: 600);
Route::get('/blog', [PostController::class, 'index'])->isr(revalidate: 60, query: ['page'], tags: ['posts']);
Route::get('/blog/{post:slug}', [PostController::class, 'show'])
    ->isr(revalidate: 3600, paths: PostPaths::class, tags: ['posts']);
Route::get('/admin', AdminController::class)->dynamic(); // explicit; the default
```

| Mode | Rendered | Cached | `paths` |
|---|---|---|---|
| `->dynamic()` (default) | every request | never | — |
| `->isr()` | on a cache miss | yes, revalidated by time and on demand | optional |
| `->static()` | on a cache miss | yes, revalidated by time and on demand | required when the route has parameters |

Named arguments (same on `isr`, `static` and `#[StaticPage]`):

| Argument | Default | Meaning |
|---|---|---|
| `revalidate` | `3600` (`defaults.revalidate`) | Edge TTL in seconds. `0` = cache, always revalidate. |
| `expire` | `31536000` (`defaults.expire`) | Total lifetime; stale-while-revalidate = `expire - revalidate`. |
| `staleWhileRevalidate` | `null` | Explicit SWR window; overrides `expire`. |
| `staleIfError` | `604800` | How long stale may be served while the origin errors. |
| `tags` | `[]` | Cache tags of every response of the route. |
| `query` | `[]` | Query parameters kept in the cache key; others are removed with a 301. |
| `paths` | `null` | `StaticPathsProvider` class for warming. Required on parameterized `static` routes. |

`InvalidStaticRoute` is thrown at registration when the route is not GET-only, `revalidate` is negative, the
`paths` class does not exist, or a parameterized `static` route has no `paths`.

Attribute (on a controller method or class; method overrides class; a route macro wins over the attribute; works
with `route:cache`):

```php
use Fydellys\InertiaStatic\Attributes\StaticPage;

class PostController
{
    #[StaticPage(mode: 'isr', revalidate: 3600, paths: PostPaths::class, tags: ['posts'])]
    public function show(Post $post) { /* ... */ }
}
```

`mode` is `'isr'` (default), `'static'`, `'dynamic'` or a `Fydellys\InertiaStatic\Enums\RenderMode`. Use
`->dynamic()` on the route to exclude one action of a class marked `#[StaticPage]`.

### Per-response overrides

```php
return Inertia::render('Post/Show', ['post' => $post])
    ->revalidate(3600)
    ->staleWhileRevalidate(86400)   // or ->expire($seconds)
    ->staleIfError(604800)
    ->tags(['posts', "post:{$post->id}"]);
```

They override the route for this request only; `tags()` adds to the route tags. Ignored on dynamic routes.

### What gets cached

200 HTML and page JSON (`revalidate`), 404 (`defaults.not_found_ttl`, 60s), canonical 301s
(`defaults.redirect_ttl`, 300s). Never: 5xx and other statuses, redirects from your code (unless
`defaults.cache_redirects`), requests with `Authorization`, preview mode, partial reloads and leaks. HTML rendered
while the SSR server is down is cached like any page; with `INERTIA_STATIC_SSR_REQUIRED=true` it is sent `no-store`.

## Static paths and warmup

```php
namespace App\StaticPaths;

use App\Models\Post;
use Fydellys\InertiaStatic\Contracts\StaticPathsProvider;

class PostPaths implements StaticPathsProvider
{
    public function paths(): iterable
    {
        foreach (Post::query()->orderBy('id')->lazy() as $post) {
            yield ['post' => $post->slug];   // key = route parameter name, value = URL segment
        }
    }
}
```

- Keys are route parameter names (`post` for `{post:slug}`), values are what goes in the URL.
- Optional parameters: `yield []` for the bare route.
- Only `inertia-static:warm` (and `inertia-static:routes`) call providers; never regular requests.
- Resolved from the container (constructor injection works). Return generators, `lazy()` or `cursor()`.
- Keep providers finite. With `isr`, unlisted pages render on first visit; list only popular ones if needed.
- A `static` paginated listing uses a path parameter (`/blog/page/{page}`); `isr` can use `query: ['page']`.

```bash
php artisan inertia-static:warm                         # every static page, HTML + JSON
php artisan inertia-static:warm --only=blog.* --only=/about --limit=50 --concurrency=20
php artisan inertia-static:warm --dry-run               # list URLs only
```

Set `INERTIA_STATIC_WARM_URL` when servers reach the CDN through an internal address (public `Host` is kept).

## Rules for static renders

On `isr`/`static` routes the session, cookie and CSRF middleware (`strip_middleware`) do not run, request cookies
are dropped, `Set-Cookie` is removed, and `errors` is always `{}`. Never do any of this in the controller, shared
props, view composers, components or the root Blade view of a static route:

| Access | Reported as |
|---|---|
| `auth()->user()`, `Auth::id()`, `auth()->check()`, `request()->user()`, the Blade auth directive, gates | `auth` |
| `session(...)`, `Session::get()`, `Session::put()` | `session` |
| `Cookie::queue()`, `cookie(...)` | `cookie` |
| `$request->bearerToken()`, `header('Authorization')`, `Cookie` / `X-XSRF-TOKEN` headers | `header` |

`$request->session()` throws immediately. `$request->cookie()` always returns `null` and is not reported.

Shared props: only keys in `public_shared_props` are sent.

```php
// config/inertia-static.php
'public_shared_props' => ['appName', 'locale'],
```

Flash data shared through `HandleInertiaRequests::share()` does not reach static pages; use `Inertia::flash()`.

`HandleInertiaRequests::share()` needs no change, including the starter-kit style `'auth' => ['user' =>
$request->user()]`: on static routes `$request->user()` is `null` (no session) and `auth` is dropped because it is not
in `public_shared_props`. Never add `auth`, `flash` or per-user props to `public_shared_props`. Props read from cookies
(`sidebarOpen` in the starter kits) get their default on static pages. Show the user in a layout shared by static and
dynamic pages with an island on `/api/me`, not with `$page.props.auth`.

### Leak detector

`leak_detector.mode` / `INERTIA_STATIC_LEAK_DETECTOR`:

- `throw` (default in `local` and `testing`): throws `StaticRenderViolation` naming the accessor.
- `auto` (default elsewhere): sends `no-store`, logs, and if the page rendered successfully turns the route
  dynamic from the next request. A leak only in a partial reload (deferred, optional or scroll prop) is logged and
  sent `no-store` but the route stays static.
- `log`: logs and sends `no-store`; the route stays static.

Fixing a violation: the exception message names the exact accessor (e.g. `auth()->check()`). The read can come
from the controller, a shared prop, a view composer, a component or the root Blade view. Move it into an island
(below), or make the route `->dynamic()`.

A route turned dynamic by `auto` shows `dynamic (downgraded: …)` in `inertia-static:routes` and fails `doctor`.
After fixing and deploying:

```bash
php artisan inertia-static:routes --reset-downgrades   # then php artisan octane:reload (Octane) and restart queue workers
```

## Islands (user-specific data)

Islands fetch JSON in the browser with the visitor's cookies, never during SSR.

```vue
<script setup lang="ts">
import { useStaticIsland } from '@fydellys/inertia-static/vue'

const { data, loading, error, load } = useStaticIsland<{ user: { name: string } | null }>('/api/me')
</script>

<template>
  <span v-if="loading">…</span>
  <span v-else-if="data?.user">Hello, {{ data.user.name }}</span>
  <a v-else href="/login">Sign in</a>
</template>
```

```tsx
import { useStaticIsland } from '@fydellys/inertia-static/react'

export function Greeting() {
  const { data, loading } = useStaticIsland<{ user: { name: string } | null }>('/api/me')
  if (loading) return <span>…</span>
  return data?.user ? <span>Hello, {data.user.name}</span> : <a href="/login">Sign in</a>
}
```

The endpoint is a normal dynamic route returning JSON (Laravel's default `Cache-Control: private` keeps it out of
the CDN):

```php
Route::get('/api/me', fn (Request $request) => [
    'user' => $request->user()?->only('id', 'name'),
]);
```

`useStaticIsland(url, options)` options: `when` (`'mount'` default, or `'visible'` with `target`), `buffer`
(pixels, default `0`), `immediate` (default `true`; `false` = only on `load()`), `initial` (value before loading
and during SSR, default `null`), `init` (extra `fetch` options; cookies always sent), `refreshAfterMutations`
(default `true`: reload after every non-GET Inertia visit such as login, logout, forms). Returns
`{ data, loading, error, load, target }` (Vue: refs; React: plain values, `target` is a ref callback). A non-2xx
response sets `error` and resets `data` to `initial`; the latest call wins.

Load when visible:

```vue
<script setup lang="ts">
import { StaticIsland } from '@fydellys/inertia-static/vue'
</script>

<template>
  <StaticIsland url="/api/me/orders" when="visible" :buffer="200">
    <template #fallback>Loading…</template>
    <template #default="{ data }">{{ data.orders.length }} orders</template>
  </StaticIsland>
</template>
```

```tsx
import { StaticIsland } from '@fydellys/inertia-static/react'

<StaticIsland<{ orders: Order[] }> url="/api/me/orders" when="visible" buffer={200} fallback={<span>Loading…</span>}>
  {({ data }) => <span>{data?.orders.length} orders</span>}
</StaticIsland>
```

`<StaticIsland>` props: `url`, `when`, `buffer`, `as` (default `'div'`), `init`, `refreshAfterMutations`; React also
`fallback` and `children` (node or render function). Slot / render props: `{ data, loading, error, load }`.

Put islands in a persistent layout (Vue `defineOptions({ layout: Layout })`, React `Page.layout = (page) =>
<Layout>{page}</Layout>`); otherwise they refetch on every navigation.

### Forms and CSRF

`useForm` / `<Form>` / `router.post` / `useHttp` work on static pages with no CSRF code (Laravel 13 accepts
same-origin requests; on Laravel 12 the client fetches the cookie from `/__inertia/csrf`, toggle with
`INERTIA_STATIC_CSRF_COOKIE`). `return back()` and redirects to static pages land on the live URL, so validation
errors and `Inertia::flash()` data reach the visitor. For your own `fetch()` calls:

```ts
import { ensureCsrfCookie } from '@fydellys/inertia-static/vue'
await ensureCsrfCookie('/__inertia/csrf')
```

## Inertia features on static pages

| Feature | Behaviour |
|---|---|
| `<Link>`, back/forward, `prefetch` | cached page JSON from the CDN |
| `<Deferred>` / `Inertia::defer()` | origin, never cached; requires `INERTIA_STATIC_DEFERRED_PROPS=allow` (default `throw`) |
| `<WhenVisible>` / `Inertia::optional()` | origin when visible, never cached |
| `<InfiniteScroll>` / `Inertia::scroll()` | next pages from origin; add the page param to `query` |
| Partial reloads (`only`, `reset`), `router.reload()`, `usePoll` | live URL, never cached |
| `Inertia::merge()` | merged in the browser |
| `Inertia::once()` | works; every cached page contains the once prop |
| `useRemember`, `Inertia::encryptHistory()` | work (encryptHistory needs HTTPS or localhost) |
| Instant visits, optimistic updates (Inertia 3) | work |
| User data when visible | `<StaticIsland when="visible">`, not `<WhenVisible>` |

- Deferred, optional and scroll props render with static rules: no session, no user. Never put user data in them.
- Vue + SSR + `<WhenVisible>` requires `@inertiajs/vue3` 2.3.21+ or 3.0.1+.
- Force a fresh visit: `router.visit('/blog', { headers: { 'X-Inertia-Static-Bypass': '1' } })`.

## Revalidation and purging

Time-based: after `revalidate` the CDN serves stale and refreshes in the background until `expire`.

On demand (queued, debounced, rate limited, dispatched after the DB transaction commits; a queue worker must run):

```php
use Fydellys\InertiaStatic\Enums\PurgeMode;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::purgeTags(['post:1']);                          // array|string
StaticCache::purgeTags("post:{$post->id}", PurgeMode::Hard); // read-your-own-writes
StaticCache::purgeUrls(['/blog/my-post']);                   // paths or absolute URLs; query is part of the URL
StaticCache::purgePrefix('/blog');                           // /blog, /blog?page=2, /blog/hello…
StaticCache::purgeAll();
$urls = StaticCache::purgeNow(PurgeOperation::Tags, ['posts']); // synchronous, throws on error
StaticCache::canPurge();                                     // false in headers-only mode
```

Every purge covers both URLs of a page (HTML and page JSON). `PurgeMode::Soft` (default, `purge.mode`) marks stale;
only Fastly and Varnish support it, others always purge hard. `PurgeOperation` cases: `Urls`, `Tags`, `Prefixes`,
`All`.

Model trait: purges `staticTags()` on `saved`, `deleted`, `restored`. Default tags are `[table, singular:id]`
(`posts`, `post:42`); tag pages with the same names.

```php
use Fydellys\InertiaStatic\Concerns\HasStaticTags;

class Post extends Model
{
    use HasStaticTags;

    public function staticTags(): array   // optional override
    {
        return ['posts', "post:{$this->id}", "author:{$this->author_id}"];
    }
}
```

Tag design: listings get a collection tag (`posts`), detail pages an id tag (`post:42`), shared fragments their own
tag (`author:7`). Every cached page also gets implicit path tags (`_IST_/`, `_IST_/blog/`, …; `implicit_tags`),
used for prefix purges on Fastly.

Tag registry (required for tag purges on nginx, CloudFront, Bunny without `native_tags`): `INERTIA_STATIC_REGISTRY`
= `database` (table `inertia_static_tags`, publish the migration) or `cache` (`registry.store`, e.g. Redis).
`INERTIA_STATIC_REGISTRY_MAX_URLS` (default `1000`) caps URLs per tag; past it, a tag purge on a CDN without tag
purges purges everything. A prefix that cannot be resolved escalates to a full purge.

Queue options: `INERTIA_STATIC_PURGE_MODE`, `INERTIA_STATIC_PURGE_DEBOUNCE` (5s), `INERTIA_STATIC_PURGE_RATE_LIMIT`
(60/min; Cloudflare Free: 5), `INERTIA_STATIC_PURGE_QUEUE`, `INERTIA_STATIC_PURGE_CONNECTION`,
`purge.warm_after_purge`.

## Artisan commands

| Command | Options |
|---|---|
| `inertia-static:routes` | `--json` (client manifest), `--reset-downgrades` |
| `inertia-static:manifest` | `--path=` (default `resources/js/inertia-static.json`) |
| `inertia-static:purge` | `--tags=*`, `--url=*`, `--prefix=*`, `--all`, `--driver=`, `--queue`, `--warm`, `--hard` |
| `inertia-static:warm` | `--only=*`, `--concurrency=10`, `--limit=0`, `--dry-run` |
| `inertia-static:doctor` | — (non-zero exit on failure; use in CI/deploy) |

`inertia-static:purge` takes `--tags=posts,post:1` (repeatable or comma-separated), `--url=/about`,
`--prefix=/blog`, `--all`, `--hard`, `--warm` (synchronous only), `--queue`, `--driver=`. Without `--queue` it runs
synchronously, fails loudly, and refuses in headers-only mode.

## Preview mode

A signed cookie makes static pages skip the cache. You protect the enabling route:

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

Route::get('/preview/enable', fn (Request $request) => redirect($request->query('to', '/'))
    ->withCookie(StaticCache::enablePreview(minutes: 60)))
    ->middleware(['auth', 'can:preview']);

Route::get('/preview/disable', fn () => redirect('/')->withCookie(StaticCache::disablePreview()));

// In a static controller:
abort_unless($post->isPublished() || StaticCache::isPreview(), 404);
```

Default lifetime `preview.minutes` (60), cookie `preview.cookie` (`inertia_static_preview`). The CDN must bypass
its cache when that cookie is present (rules in [references/cdn-setup.md](references/cdn-setup.md)).

## Deploy

1. `npm run build` (the Vite plugin writes the manifest; otherwise run `php artisan inertia-static:manifest`). The
   asset version changes, so page JSON URLs change automatically.
2. `php artisan inertia-static:purge --all --warm` (HTML embeds asset URLs). Skip in headers-only mode.
3. Keep the previous build's assets for a few hours.
4. With Octane (FrankenPHP, Swoole, RoadRunner): `php artisan octane:reload`. No other Octane setup is needed.

Checklist: `INERTIA_STATIC_BASE_URL` is public; `INERTIA_STATIC_CDN` + credentials set; SSR server supervised if
SSR is used (`php artisan inertia:start-ssr`); a queue worker runs; CDN caches HTML and JSON; `public_shared_props`
lists only public data; `php artisan inertia-static:doctor` passes.

## Testing in the app

See [references/testing.md](references/testing.md): leak checks as guest, header assertions, faking purge jobs.
