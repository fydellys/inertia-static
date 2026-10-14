# PHP API

## Route macros

```php
Route::get(...)->isr(?int $revalidate = null, ?string $paths = null, array $tags = [], array $query = [], ?int $staleWhileRevalidate = null, ?int $staleIfError = null);
Route::get(...)->static(/* same */);
Route::get(...)->dynamic();
```

## Attribute

```php
#[StaticPage(mode: 'isr'|'static'|RenderMode, revalidate: ?int, paths: ?string, tags: [], query: [], staleWhileRevalidate: ?int, staleIfError: ?int)]
```

## `Inertia\Response` macros

```php
->revalidate(?int $seconds)
->staleWhileRevalidate(?int $seconds)
->staleIfError(?int $seconds)
->tags(array|string $tags)
```

## `StaticCache` facade

```php
StaticCache::purgeTags(array|string $tags): void            // queued
StaticCache::purgeUrls(array|string $urls): void            // queued, paths or absolute URLs
StaticCache::purgePrefix(array|string $prefixes): void      // queued
StaticCache::purgeAll(): void                               // queued
StaticCache::purgeNow(PurgeOperation $op, array $items = [], ?string $driver = null): array // sync, returns purged URLs
StaticCache::canPurge(): bool                               // false in headers-only mode
StaticCache::driver(?string $name = null): CdnDriver
StaticCache::extend(string $driver, Closure $factory): StaticCache
```

## Model trait

```php
use Fydellys\InertiaStatic\Concerns\HasStaticTags;   // saved / deleted / restored → purgeTags(staticTags())
public function staticTags(): array                   // default: [table, singular:id]
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
| `Events\StaticCachePurged($operation, $items, $driver)` | after every purge |
| `Events\StaticRenderViolated($violation)` | the leak detector fired |

## Exceptions

| Exception | When |
|---|---|
| `StaticRenderViolation` | private data accessed during a static render (`throw` mode) |
| `InvalidStaticRoute` | invalid route declaration, deferred props in `throw` mode |
| `MissingCredentials` | purging with a driver in headers-only mode |
| `UnsupportedOperation` | the driver cannot perform the operation |

## Jobs

`Jobs\PurgeStaticCache` (unique, debounced, rate limited) and `Jobs\WarmStaticCache`.
