<?php

namespace Fydellys\InertiaStatic\Cdn;

use Fydellys\InertiaStatic\Cdn\Drivers\BunnyDriver;
use Fydellys\InertiaStatic\Cdn\Drivers\CloudflareDriver;
use Fydellys\InertiaStatic\Cdn\Drivers\FastlyDriver;
use Fydellys\InertiaStatic\Cdn\Drivers\MultiDriver;
use Fydellys\InertiaStatic\Cdn\Drivers\NginxDriver;
use Fydellys\InertiaStatic\Cdn\Drivers\NullDriver;
use Fydellys\InertiaStatic\Cdn\Drivers\VarnishDriver;
use Fydellys\InertiaStatic\Contracts\CdnDriver;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * @method CdnDriver driver(string|null $driver = null)
 */
class CdnManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('inertia-static.cdn', 'null');
    }

    /**
     * Resolve a configured connection ("drivers.{name}") or a raw driver.
     */
    protected function createDriver($driver): CdnDriver
    {
        $config = $this->configFor($driver);
        $type = isset($this->customCreators[$driver]) ? $driver : ($config['driver'] ?? $driver);

        if (isset($this->customCreators[$type])) {
            return $this->customCreators[$type]($this->container, $config);
        }

        $method = 'create'.ucfirst($type).'Driver';

        if (! method_exists($this, $method)) {
            throw new InvalidArgumentException("[inertia-static] CDN driver [{$driver}] is not supported.");
        }

        return $this->{$method}($config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createNullDriver(array $config): CdnDriver
    {
        return new NullDriver($this->http(), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createCloudflareDriver(array $config): CdnDriver
    {
        // Scope "purge all" to the application's hostname unless configured otherwise.
        $config['hosts'] ??= array_filter([parse_url((string) $this->config->get('inertia-static.base_url'), PHP_URL_HOST)]);

        return new CloudflareDriver($this->http(), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createFastlyDriver(array $config): CdnDriver
    {
        return new FastlyDriver($this->http(), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createBunnyDriver(array $config): CdnDriver
    {
        return new BunnyDriver($this->http(), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createVarnishDriver(array $config): CdnDriver
    {
        return new VarnishDriver($this->http(), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createNginxDriver(array $config): CdnDriver
    {
        return new NginxDriver($this->http(), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createMultiDriver(array $config): CdnDriver
    {
        return new MultiDriver(array_map(
            fn (string $name) => $this->driver($name),
            (array) ($config['drivers'] ?? []),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function configFor(string $name): array
    {
        return (array) $this->config->get("inertia-static.drivers.{$name}", ['driver' => $name]);
    }

    protected function http(): HttpFactory
    {
        return $this->container->make(HttpFactory::class);
    }
}
