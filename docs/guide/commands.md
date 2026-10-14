# Artisan commands

| Command | Purpose |
|---|---|
| `inertia-static:routes [--json]` | List static routes with mode, TTLs, tags and paths provider (`--json`: the client manifest). |
| `inertia-static:manifest [--path=]` | Write the client manifest (default `resources/js/inertia-static.json`). The Vite plugin runs it on every build. |
| `inertia-static:purge` | Purge the CDN now (or `--queue`). |
| `inertia-static:warm` | Request every static page (HTML + JSON) so the CDN is filled. |
| `inertia-static:doctor` | Validate the setup. |

## `inertia-static:purge`

```bash
php artisan inertia-static:purge --tags=posts,post:1
php artisan inertia-static:purge --url=/blog/my-post --url=/about
php artisan inertia-static:purge --prefix=/blog
php artisan inertia-static:purge --all
php artisan inertia-static:purge --all --warm      # purge, then warm the purged URLs
php artisan inertia-static:purge --tags=posts --queue
php artisan inertia-static:purge --all --driver=varnish
```

Without `--queue` the purge runs synchronously and fails loudly. In [headers-only mode](./headers-only.md) the
command refuses to run.

## `inertia-static:warm`

```bash
php artisan inertia-static:warm                          # every static page
php artisan inertia-static:warm --only=blog.* --only=/about
php artisan inertia-static:warm --limit=50 --concurrency=20
php artisan inertia-static:warm --dry-run                # list URLs only
```

Parameterized routes are expanded with their [paths provider](./static-paths.md). If your
servers reach the CDN through an internal address, set `INERTIA_STATIC_WARM_URL`. The public `Host` header is
kept, so the warmup lands on the same cache key.

## `inertia-static:doctor`

Checks:
- the package is enabled and the global middleware is registered;
- `base_url` is absolute;
- SSR is enabled and reachable;
- the CDN driver can be built, in purge or headers-only mode, and a tag registry exists when the driver cannot
  purge by tag;
- every static route really runs without session middleware and is not behind `auth`.
