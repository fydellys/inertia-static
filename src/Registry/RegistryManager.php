<?php

namespace Fydellys\InertiaStatic\Registry;

use Fydellys\InertiaStatic\Contracts\TagRegistry;
use Illuminate\Support\Manager;

/**
 * @method TagRegistry driver(string|null $driver = null)
 */
class RegistryManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) ($this->config->get('inertia-static.registry.driver') ?: 'null');
    }

    protected function createNullDriver(): TagRegistry
    {
        return new NullTagRegistry;
    }

    protected function createDatabaseDriver(): TagRegistry
    {
        return new DatabaseTagRegistry(
            $this->container->make('db')->connection($this->config->get('inertia-static.registry.connection')),
            (string) $this->config->get('inertia-static.registry.table', 'inertia_static_tags'),
            $this->maxUrlsPerTag(),
        );
    }

    protected function createCacheDriver(): TagRegistry
    {
        return new CacheTagRegistry(
            $this->container->make('cache')->store($this->config->get('inertia-static.registry.store')),
            $this->maxUrlsPerTag(),
        );
    }

    protected function maxUrlsPerTag(): ?int
    {
        $max = $this->config->get('inertia-static.registry.max_urls_per_tag', 1000);

        return $max === null || $max === '' ? null : max(1, (int) $max);
    }
}
