<?php

namespace Fydellys\InertiaStatic\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

/**
 * Resolves the Inertia asset version, also outside of an HTTP request
 * (queue workers, artisan commands), by asking the app's Inertia middleware.
 */
class AssetVersion
{
    public function __construct(
        protected Container $container,
        protected Repository $config,
    ) {}

    public function current(?Request $request = null): string
    {
        $version = Inertia::getVersion();

        if ($version !== '') {
            return $version;
        }

        $class = $this->config->get('inertia-static.inertia_middleware');

        $middleware = is_string($class) && class_exists($class) && is_subclass_of($class, Middleware::class)
            ? $this->container->make($class)
            : $this->container->make(Middleware::class);

        return (string) $middleware->version($request ?? $this->container->make('request'));
    }

    /**
     * The version as a URL segment (an empty version still needs a segment).
     */
    public function segment(?string $version = null): string
    {
        $version ??= $this->current();

        return $version === '' ? '_' : rawurlencode($version);
    }

    public static function fromSegment(string $segment): string
    {
        return $segment === '_' ? '' : rawurldecode($segment);
    }
}
