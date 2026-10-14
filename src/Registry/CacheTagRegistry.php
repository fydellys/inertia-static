<?php

namespace Fydellys\InertiaStatic\Registry;

use Fydellys\InertiaStatic\Contracts\TagRegistry;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

/**
 * Stores tag => URLs in a cache store (e.g. Redis). Each tag is one key;
 * an index key lists every tag so prefix lookups and flushes are possible.
 */
class CacheTagRegistry implements TagRegistry
{
    protected const INDEX = 'inertia-static:registry:index';

    public function __construct(protected Repository $cache) {}

    public function record(array $tags, array $urls): void
    {
        $write = function () use ($tags, $urls) {
            foreach (array_unique($tags) as $tag) {
                $this->cache->forever($this->key($tag), array_values(array_unique([...$this->get($tag), ...$urls])));
            }

            $this->cache->forever(self::INDEX, array_values(array_unique([...$this->index(), ...$tags])));
        };

        $store = $this->cache->getStore();

        $store instanceof LockProvider
            ? $store->lock('inertia-static:registry:lock', 5)->block(5, $write)
            : $write();
    }

    public function urlsFor(array $tags): array
    {
        return collect($tags)->flatMap(fn (string $tag) => $this->get($tag))->unique()->sort()->values()->all();
    }

    public function urlsWithPrefix(array $prefixes): array
    {
        $prefixes = array_map(fn (string $prefix) => Str::start($prefix, '/'), $prefixes);

        return collect($this->urlsFor($this->index()))
            ->filter(fn (string $url) => Str::startsWith($url, $prefixes))
            ->values()
            ->all();
    }

    public function forget(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->cache->forget($this->key($tag));
        }

        $this->cache->forever(self::INDEX, array_values(array_diff($this->index(), $tags)));
    }

    public function flush(): void
    {
        $this->forget($this->index());
        $this->cache->forget(self::INDEX);
    }

    /**
     * @return array<int, string>
     */
    protected function get(string $tag): array
    {
        return (array) $this->cache->get($this->key($tag), []);
    }

    /**
     * @return array<int, string>
     */
    protected function index(): array
    {
        return (array) $this->cache->get(self::INDEX, []);
    }

    protected function key(string $tag): string
    {
        return 'inertia-static:registry:tag:'.hash('xxh128', $tag);
    }
}
