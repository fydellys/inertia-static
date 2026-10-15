# PHP API

Namespace: `Fydellys\InertiaStatic`.

## Route macros

```php
Route::get(...)->isr(
    ?int $revalidate = null,
    ?string $paths = null,            // StaticPathsProvider class
    array $tags = [],
    array $query = [],                // whitelisted query parameters
    ?int $staleWhileRevalidate = null,
    ?int $staleIfError = null,
    ?int $expire = null,
);
Route::get(...)->static(/* same */);
Route::get(...)->dynamic();
```

`isr` and `static` require a `GET` route. A `static` route with parameters requires `paths`.

## Attribute

```php
use Fydellys\InertiaStatic\Attributes\StaticPage;

#[StaticPage(mode: 'isr'|'static'|RenderMode, revalidate: ?int, paths: ?string, tags: [], query: [],
    staleWhileRevalidate: ?int, staleIfError: ?int, expire: ?int)]
```

On a controller class or method. `mode` defaults to `isr`.

## `Inertia\Response` macros

```php
Inertia::render(...)
    ->revalidate(?int $seconds)
    ->expire(?int $seconds)
    ->staleWhileRevalidate(?int $seconds)
    ->staleIfError(?int $seconds)
    ->tags(array|string $tags);
```

## `StaticCache` facade

```php
use Fydellys\InertiaStatic\Facades\StaticCache;

StaticCache::purgeTags(array|string $tags, ?PurgeMode $mode = null): void        // queued
StaticCache::purgeUrls(array|string $urls, ?PurgeMode $mode = null): void        // queued, paths or absolute URLs
StaticCache::purgePrefix(array|string $prefixes, ?PurgeMode $mode = null): void  // queued
StaticCache::purgeAll(?PurgeMode $mode = null): void                             // queued
StaticCache::purgeNow(PurgeOperation $operation, array $items = [], ?string $driver = null, ?PurgeMode $mode = null): array // now, returns the purged URLs
StaticCache::canPurge(): bool                                // false in headers-only mode
StaticCache::enablePreview(?int $minutes = null): Cookie
StaticCache::disablePreview(): Cookie
StaticCache::isPreview(): bool
StaticCache::driver(?string $name = null): CdnDriver
StaticCache::extend(string $driver, Closure $factory): StaticCache // $factory(Application $app, array $config): CdnDriver
```

Queued purges are skipped (and logged) when the driver has no credentials. `$mode` defaults to `purge.mode`.

## Enums

| Enum | Cases |
|---|---|
| `Enums\RenderMode` | `Dynamic`, `Isr`, `Static` |
| `Enums\PurgeMode` | `Soft`, `Hard` |
| `Enums\PurgeOperation` | `Urls`, `Tags`, `Prefixes`, `All` |
| `Enums\Capability` | `PurgeUrls`, `PurgeTags`, `PurgePrefix`, `PurgeAll`, `CacheTags` |

## Model trait

```php
use Fydellys\InertiaStatic\Concerns\HasStaticTags;   // saved / deleted / restored → purgeTags(staticTags())

public function staticTags(): array                   // default: [table, singular:id], e.g. ['posts', 'post:42']
```

## Contracts

```php
interface StaticPathsProvider { public function paths(): iterable; }

interface CdnDriver {
    public function cacheHeaders(CachePolicy $policy): array;
    public function tagHeaders(array $tags): array;
    public function purgeUrls(array $urls): void;
    public function purgeTags(array $tags): void;
    public function purgePrefixes(array $prefixes): void;
    public function purgeAll(): void;
    public function supports(Capability $capability): bool;
    public function canPurge(): bool;
    public function withMode(PurgeMode $mode): static;
    public function batchSize(Capability $capability): int;
}

interface TagRegistry {
    public function record(array $tags, array $urls): void;
    public function urlsFor(array $tags): array;
    public function urlsWithPrefix(array $prefixes): array;
    public function forget(array $tags): void;
    public function flush(): void;
}
```

## Events

| Event | When |
|---|---|
| `Events\StaticCachePurged(string $operation, array $items, string $driver)` | after every purge |
| `Events\StaticRenderViolated(StaticRenderViolation $violation)` | the leak detector fired |

## Exceptions

| Exception | When |
|---|---|
| `Exceptions\StaticRenderViolation` | private data read during a static render (`throw` mode) |
| `Exceptions\InvalidStaticRoute` | invalid route declaration (not `GET`, negative `revalidate`, missing or invalid paths provider), deferred props in `throw` mode |
| `Exceptions\MissingCredentials` | purging now with a driver that has no credentials |
| `Exceptions\UnsupportedOperation` | the driver cannot perform the operation |

## Jobs

`Jobs\PurgeStaticCache` (unique, debounced, rate limited) and `Jobs\WarmStaticCache`.

## Artisan commands

| Command | Options |
|---|---|
| `inertia-static:routes` | `--json`, `--reset-downgrades` |
| `inertia-static:manifest` | `--path=` (default `resources/js/inertia-static.json`) |
| `inertia-static:purge` | `--tags=*`, `--url=*`, `--prefix=*`, `--all`, `--driver=`, `--queue`, `--warm`, `--hard` |
| `inertia-static:warm` | `--only=*`, `--concurrency=10`, `--limit=0`, `--dry-run` |
| `inertia-static:doctor` | — |

See [Artisan commands](../guide/commands.md).
