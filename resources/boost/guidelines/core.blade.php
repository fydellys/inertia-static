@verbatim
## fydellys/inertia-static

Static (SSG) and ISR pages for Laravel + Inertia (Vue or React, Inertia 2 and 3), cached by a CDN (Cloudflare, Fastly, Bunny, CloudFront, Varnish, nginx). A route marked `->isr()` or `->static()` is rendered without session, cookies or user, so one cached copy serves every visitor. Routes without a mode stay dynamic.

<code-snippet name="Declare static and ISR routes" lang="php">
Route::get('/', HomeController::class)->static(revalidate: 600);
Route::get('/blog', [PostController::class, 'index'])->isr(revalidate: 300, tags: ['posts'], query: ['page']);
Route::get('/blog/{post:slug}', [PostController::class, 'show'])->isr(revalidate: 3600, paths: PostPaths::class, tags: ['posts']);
Route::get('/dashboard', DashboardController::class)->middleware('auth'); // dynamic
</code-snippet>

### Rules

- Never read the user, session, cookies or auth headers while rendering an `isr`/`static` route (controller, shared props, view composers, Blade root view): no `auth()->user()`, `Auth::id()`, `$request->user()`, `session()`, `Cookie::queue()`, `$request->bearerToken()`, gates. The leak detector throws `StaticRenderViolation` in `local`/`testing`.
- Load user-specific data in the browser with an island (`useStaticIsland('/api/me')` or `<StaticIsland>` from `@fydellys/inertia-static/vue` or `/react`) that calls a normal dynamic route returning JSON. Otherwise make the route dynamic.
- Only shared props listed in `config('inertia-static.public_shared_props')` reach static pages; `auth`, `flash` and the rest are dropped and `errors` is always `{}`. Add only props that are identical for every visitor.
- Static routes must be `GET` only (`Route::get`). A `->static()` route with parameters needs `paths:` (a `StaticPathsProvider`).
- Whitelist only the query parameters that change the page (`query: ['page']`); everything else is removed with a 301. Never vary a static page by cookie or `Accept-Language`: put the locale in the URL.
- Tag pages with the data they show (route `tags:` or `Inertia::render(...)->tags([...])`) and purge with `StaticCache::purgeTags()` or the `HasStaticTags` model trait. Purges are queued: a queue worker must run.
- Deferred props on static pages throw unless `INERTIA_STATIC_DEFERRED_PROPS=allow`. Use `<StaticIsland when="visible">`, not `<WhenVisible>`, for user data.
- Build absolute URLs from config (`INERTIA_STATIC_BASE_URL` / `APP_URL`), never from the request host.
- After changing static routes, rebuild assets (the Vite plugin rewrites `resources/js/inertia-static.json`) and run `php artisan inertia-static:doctor`.

For installation, islands, purging, CDN setup, debug headers and troubleshooting, activate the `inertia-static-development` skill.
@endverbatim
