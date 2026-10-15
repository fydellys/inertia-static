# Artisan commands

| Command | Purpose |
|---|---|
| `inertia-static:routes` | List static routes with mode, TTLs, tags and paths provider. |
| `inertia-static:manifest [--path=]` | Write the client manifest (default `resources/js/inertia-static.json`). The Vite plugin runs it when the build starts. |
| `inertia-static:purge` | Purge the CDN now (or `--queue`). |
| `inertia-static:warm` | Request every static page (HTML + JSON) so the CDN is filled. |
| `inertia-static:doctor` | Validate the setup. |

## `inertia-static:routes`

```bash
php artisan inertia-static:routes
php artisan inertia-static:routes --json                # print the client manifest
php artisan inertia-static:routes --reset-downgrades    # make routes the leak detector turned dynamic static again
```

## `inertia-static:purge`

```bash
php artisan inertia-static:purge --tags=posts,post:1
php artisan inertia-static:purge --url=/blog/my-post --url=/about
php artisan inertia-static:purge --prefix=/blog
php artisan inertia-static:purge --all
php artisan inertia-static:purge --all --warm      # purge everything, then warm every static page
php artisan inertia-static:purge --tags=posts --hard
php artisan inertia-static:purge --tags=posts --queue
php artisan inertia-static:purge --all --driver=varnish
```

| Option | |
|---|---|
| `--tags=`, `--url=`, `--prefix=` | repeatable or comma-separated |
| `--all` | purge everything |
| `--hard` | remove the content instead of marking it stale (see [soft or hard purge](./revalidation.md#soft-or-hard-purge)) |
| `--warm` | warm the purged URLs afterwards (synchronous purges only) |
| `--queue` | dispatch to the queue instead of purging now |
| `--driver=` | CDN driver to use (default: `INERTIA_STATIC_CDN`) |

Without `--queue` the purge runs synchronously and fails loudly. In [headers-only mode](./headers-only.md) the
command refuses to run.

## `inertia-static:warm`

```bash
php artisan inertia-static:warm                          # every static page
php artisan inertia-static:warm --only=blog.* --only=/about
php artisan inertia-static:warm --limit=50 --concurrency=20
php artisan inertia-static:warm --dry-run                # list URLs only
```

`--only` matches route names or URIs (wildcards allowed), `--limit` caps the pages per route, `--concurrency`
defaults to `10`. Parameterized routes are expanded with their [paths provider](./static-paths.md). If your servers
reach the CDN through an internal address, set `INERTIA_STATIC_WARM_URL`. The public `Host` header is kept, so the
warmup lands on the same cache key.

## `inertia-static:doctor`

Checks:
- the package is enabled and its global middleware is registered;
- `base_url` is absolute;
- when Inertia SSR is enabled, the SSR server is reachable;
- the CDN driver can be built, in purge or headers-only mode, and a tag registry exists when the driver cannot
  purge by tag;
- no route was turned dynamic by the leak detector;
- every static route runs without session middleware and is not behind `auth`.

It exits with a non-zero code when a check fails, so it can run in CI or a deploy script.
