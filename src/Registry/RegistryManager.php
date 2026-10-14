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
        );
    }

    protected function createCacheDriver(): TagRegistry
    {
        return new CacheTagRegistry(
            $this->container->make('cache')->store($this->config->get('inertia-static.registry.store')),
        );
    }
}
