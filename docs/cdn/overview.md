# CDN drivers

```ini
INERTIA_STATIC_CDN=cloudflare   # null | cloudflare | fastly | bunny | varnish | nginx | multi | your own
```

| Driver | Edge TTL header | Tags | Purge | Credentials (purge only) |
|---|---|---|---|---|
| `null` | `CDN-Cache-Control` | — | no-op | — |
| [`cloudflare`](./cloudflare.md) | `Cloudflare-CDN-Cache-Control` | `Cache-Tag` | URL, tag, prefix, all (batches of 100) | `zone_id`, `api_token` |
| [`fastly`](./fastly.md) | `Surrogate-Control` | `Surrogate-Key` | URL, key (batches of 256), all, soft purge | `service_id`, `api_token` |
| [`bunny`](./bunny.md) | `CDN-Cache-Control` | via registry | URL, prefix (`*`), zone | `pull_zone_id`, `api_key` |
| [`varnish`](./varnish.md) | `s-maxage` | `xkey` | `PURGE`, `xkey-purge`, `BAN` | `hosts` |
| [`nginx`](./nginx.md) | `X-Accel-Expires` | via registry | `ngx_cache_purge` (URL, prefix, all) | `hosts` |
| `multi` | all of them | all of them | fans out to each driver | each driver's |

Without credentials a driver runs in [headers-only mode](../guide/headers-only.md).

## Capabilities and fallbacks

Each driver declares what it supports (`Capability::PurgeUrls`, `PurgeTags`, `PurgePrefix`, `PurgeAll`,
`CacheTags`). The purger adapts:

| Request | Driver lacks it | Fallback |
|---|---|---|
| purge tags | `PurgeTags` | [tag registry](../guide/revalidation.md#tag-registry) → URL purges |
| purge prefix | `PurgePrefix` | registry → URL purges, else purge all |
| purge all | `PurgeAll` | purge the `/` prefix |

## Multi

Chain several caches, e.g. Varnish in front of the app plus Cloudflare at the edge:

```php
'multi' => ['driver' => 'multi', 'drivers' => ['varnish', 'cloudflare']],
```

Headers are merged (first driver wins on conflicts). Purges go to every driver that supports the operation. A
capability counts as supported only when **every** chained driver has it, so the others fall back to the registry.

## Custom drivers

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::extend('akamai', fn ($app, array $config) => new AkamaiDriver(
    $app->make(\Illuminate\Http\Client\Factory::class),
    $config,
));
```

```php
// config/inertia-static.php
'drivers' => [
    'akamai' => ['driver' => 'akamai', 'host' => env('AKAMAI_HOST'), /* ... */],
],
```

Extend `Fydellys\InertiaStatic\Cdn\Driver` (or implement `Contracts\CdnDriver`): declare `$capabilities` and
`$credentials`, then implement `cacheHeaders()`, `tagHeaders()` and the purge methods you support. Call
`$this->ensureCredentials()` at the start of every purge method.
