# Deploying

1. **Build** your assets (`npm run build`). The Inertia asset version changes, so every page JSON gets a **new
   URL** (`/__inertia/{newVersion}/...`) automatically.
2. **Purge the HTML** (it embeds the asset URLs and the version):

   ```bash
   php artisan inertia-static:manifest     # before the build, if routes changed
   php artisan inertia-static:purge --all --warm
   ```
3. **Keep the previous build's assets** for a few hours. Visitors with an old page open keep loading old
   chunks until their next full reload.

## Mid-session version change

A visitor with an old page open requests `/__inertia/{oldVersion}/page.json`:

- if the CDN still has it, it is served: old page, old assets, still consistent;
- otherwise the origin answers `409` + `X-Inertia-Location` and Inertia performs one full reload onto the new
  version. Then navigation continues on the new version.

## Checklist

- [ ] `INERTIA_STATIC_BASE_URL` (or `APP_URL`) is the public URL.
- [ ] If you use Inertia SSR: the SSR server runs in production (`php artisan inertia:start-ssr` under a supervisor).
- [ ] A queue worker runs (purges are queued).
- [ ] The CDN caches HTML and JSON (Cloudflare: Cache Rule).
- [ ] `public_shared_props` only lists public data.
- [ ] `php artisan inertia-static:doctor` passes.
