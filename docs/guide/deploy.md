# Deploying

1. **Build** your assets (`npm run build`). The Vite plugin writes the client manifest first
   (`inertia-static:manifest`); run that command yourself if you build without the plugin. The Inertia asset
   version changes, so every page JSON gets a **new URL** (`/__inertia/{newVersion}/...`) automatically.
2. **Purge the HTML** (it embeds the asset URLs and the version), then warm it:

   ```bash
   php artisan inertia-static:purge --all --warm
   ```

   In [headers-only mode](./headers-only.md) there is nothing to purge: the HTML refreshes after `revalidate`.
3. **Keep the previous build's assets** for a few hours. Visitors with an old page open keep loading old
   chunks until their next full reload.

## Mid-session version change

A visitor with an old page open requests `/__inertia/{oldVersion}/page.json`:

- if the CDN still has it, it is served: old page, old assets, still consistent;
- otherwise the origin answers `409` + `X-Inertia-Location` and Inertia performs one full reload onto the new
  version. Then navigation continues on the new version.

## Laravel Octane

The package works with [Octane](https://laravel.com/docs/octane) (FrankenPHP, Swoole, RoadRunner) with no extra
setup: its request state is reset on every request, and Octane's default listeners are enough. After a deploy,
reload the workers so they load the new build and routes:

```bash
php artisan octane:reload
```

A route the leak detector turns dynamic (`auto` mode) is dynamic from the next request in the worker that saw the
leak; other workers switch the first time they render it. After
`php artisan inertia-static:routes --reset-downgrades`, run `php artisan octane:reload`.

## Checklist

- [ ] `INERTIA_STATIC_BASE_URL` (or `APP_URL`) is the public URL.
- [ ] `INERTIA_STATIC_CDN` and the driver's credentials are set (without credentials: headers-only mode).
- [ ] If you use Inertia SSR: the SSR server runs in production (`php artisan inertia:start-ssr` under a supervisor).
- [ ] A queue worker runs (purges are queued).
- [ ] With Octane: `php artisan octane:reload` runs after each deploy.
- [ ] The CDN caches HTML and JSON (see your [CDN page](../cdn/overview.md)).
- [ ] `public_shared_props` only lists public data.
- [ ] `php artisan inertia-static:doctor` passes.
