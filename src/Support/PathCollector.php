<?php

namespace Fydellys\InertiaStatic\Support;

use Fydellys\InertiaStatic\Contracts\StaticPathsProvider;
use Fydellys\InertiaStatic\Exceptions\InvalidStaticRoute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Expands static routes into concrete page paths using their providers.
 */
class PathCollector
{
    public function __construct(
        protected Container $container,
        protected Manifest $manifest,
    ) {}

    /**
     * @param  array<int, string>  $only  Route names / URIs (wildcards allowed).
     * @return array<int, string>
     */
    public function collect(array $only = [], int $limit = 0): array
    {
        return $this->manifest->routes()
            ->filter(fn (array $entry) => $entry['options']->mode->isCacheable())
            ->filter(fn (array $entry) => $only === [] || $this->matches($entry['route'], $only))
            ->flatMap(fn (array $entry) => $this->pathsFor($entry['route'], $entry['options'], $limit))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function pathsFor(Route $route, RouteOptions $options, int $limit = 0): array
    {
        if ($route->parameterNames() === []) {
            return ['/'.ltrim($route->uri(), '/')];
        }

        if ($options->paths === null) {
            return [];
        }

        $provider = $this->container->make($options->paths);

        if (! $provider instanceof StaticPathsProvider) {
            throw new InvalidStaticRoute("[inertia-static] [{$options->paths}] must implement StaticPathsProvider.");
        }

        $paths = [];

        foreach ($provider->paths() as $parameters) {
            $paths[] = $this->substitute($route, (array) $parameters);

            if ($limit > 0 && count($paths) >= $limit) {
                break;
            }
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function substitute(Route $route, array $parameters): string
    {
        $uri = preg_replace_callback('/\{(\w+)(?::\w+)?(\??)\}/', function (array $match) use ($parameters, $route) {
            if (! array_key_exists($match[1], $parameters)) {
                if ($match[2] === '?') {
                    return '';
                }

                throw new InvalidStaticRoute("[inertia-static] Missing parameter [{$match[1]}] for route [{$route->uri()}].");
            }

            return rawurlencode((string) $parameters[$match[1]]);
        }, $route->uri());

        return '/'.trim((string) preg_replace('#/+#', '/', (string) $uri), '/');
    }

    /**
     * @param  array<int, string>  $only
     */
    protected function matches(Route $route, array $only): bool
    {
        return Str::is($only, (string) $route->getName()) || Str::is($only, '/'.ltrim($route->uri(), '/'));
    }
}
