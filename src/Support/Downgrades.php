<?php

namespace Fydellys\InertiaStatic\Support;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Route;

/**
 * Routes taken out of static rendering by the leak detector in "auto" mode,
 * because they read private data while rendering.
 * Persisted in a cache store, so they survive requests and route:cache.
 */
class Downgrades
{
    protected const KEY = 'inertia-static:downgrades';

    public function __construct(
        protected CacheFactory $cache,
        protected Config $config,
    ) {}

    public static function keyOf(Route $route): string
    {
        return $route->getName() ?? implode('|', $route->methods()).' /'.ltrim($route->uri(), '/');
    }

    public function add(Route $route, string $reason): void
    {
        $all = $this->all();
        $all[self::keyOf($route)] ??= ['reason' => $reason, 'at' => now()->toIso8601String()];

        $this->store()->forever(self::KEY, $all);
    }

    public function has(Route $route): bool
    {
        return array_key_exists(self::keyOf($route), $this->all());
    }

    public function reasonFor(Route $route): ?string
    {
        return $this->all()[self::keyOf($route)]['reason'] ?? null;
    }

    /**
     * @return array<string, array{reason: string, at: string}>
     */
    public function all(): array
    {
        return (array) $this->store()->get(self::KEY, []);
    }

    public function clear(): void
    {
        $this->store()->forget(self::KEY);
    }

    protected function store(): Repository
    {
        return $this->cache->store($this->config->get('inertia-static.leak_detector.store'));
    }
}
