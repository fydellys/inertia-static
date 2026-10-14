# CDN drivers

Pick the driver of the cache in front of your app:

```ini
INERTIA_STATIC_CDN=cloudflare   # cloudflare | fastly | bunny | varnish | nginx | multi | null
```

| Driver | Purge by tag | Credentials |
|---|---|---|
| [`cloudflare`](./cloudflare.md) | yes | `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_API_TOKEN` |
| [`fastly`](./fastly.md) | yes | `FASTLY_SERVICE_ID`, `FASTLY_API_TOKEN` |
| [`bunny`](./bunny.md) | through the tag registry | `BUNNY_PULL_ZONE_ID`, `BUNNY_API_KEY` |
| [`varnish`](./varnish.md) | yes | `VARNISH_HOSTS` |
| [`nginx`](./nginx.md) | through the tag registry | `NGINX_CACHE_HOSTS` |
| `multi` | see below | each driver's |
| `null` | — | — (sends cache headers, never purges) |

Credentials are only needed to purge. Without them, pages are cached and expire by time:
[headers-only mode](../guide/headers-only.md).

Drivers marked "through the tag registry" need one, so tag purges can be turned into URL purges:

```ini
INERTIA_STATIC_REGISTRY=database
```

## Multi

Several caches at once, e.g. Varnish in front of the app and Cloudflare at the edge:

```php
// config/inertia-static.php
'drivers' => [
    'multi' => ['driver' => 'multi', 'drivers' => ['varnish', 'cloudflare']],
],
```

Every purge goes to every cache.

## Custom drivers

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::extend('my-cdn', fn ($app, array $config) => new MyCdnDriver(
    $app->make(\Illuminate\Http\Client\Factory::class),
    $config,
));
```

```php
// config/inertia-static.php
'drivers' => [
    'my-cdn' => ['driver' => 'my-cdn', 'token' => env('MY_CDN_TOKEN')],
],
```

Extend `Fydellys\InertiaStatic\Cdn\Driver`: declare `$capabilities` and `$credentials`, implement
`cacheHeaders()`, `tagHeaders()` and the purge methods you support, and call `$this->ensureCredentials()` at the
start of each purge method.
