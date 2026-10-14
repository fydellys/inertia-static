<?php

namespace Fydellys\InertiaStatic\Routing;

use Fydellys\InertiaStatic\Enums\RenderMode;
use Fydellys\InertiaStatic\Exceptions\InvalidStaticRoute;
use Fydellys\InertiaStatic\Http\Middleware\RenderStatically;
use Fydellys\InertiaStatic\Support\RouteOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Route;

/**
 * Turns a route into an "isr" / "static" route: stores the options in the
 * route action (route:cache safe), removes the session pipeline and adds
 * the RenderStatically middleware.
 */
class StaticRouteConfigurator
{
    public function __construct(protected Repository $config) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function apply(Route $route, RenderMode $mode, array $options = []): Route
    {
        $options = RouteOptions::fromArray(['mode' => $mode] + array_filter($options, fn ($value) => $value !== null));

        $this->validate($route, $options);

        $route->setAction(array_merge($route->getAction(), [RouteOptions::ACTION_KEY => $options->toArray()]));

        if (! $mode->isCacheable()) {
            return $route;
        }

        $route->withoutMiddleware($this->strippedMiddleware());

        if (! in_array(RenderStatically::ALIAS, $route->middleware(), true)) {
            $route->middleware(RenderStatically::ALIAS);
        }

        return $route;
    }

    /**
     * @return array<int, string>
     */
    public function strippedMiddleware(): array
    {
        return array_values(array_filter(
            (array) $this->config->get('inertia-static.strip_middleware', []),
            fn (string $class) => class_exists($class),
        ));
    }

    protected function validate(Route $route, RouteOptions $options): void
    {
        if (! $options->mode->isCacheable()) {
            return;
        }

        if (! in_array('GET', $route->methods(), true)) {
            throw new InvalidStaticRoute("[inertia-static] Route [{$route->uri()}] must be a GET route to be {$options->mode->value}.");
        }

        if ($options->revalidate !== null && $options->revalidate < 0) {
            throw new InvalidStaticRoute("[inertia-static] Route [{$route->uri()}] has a negative revalidate.");
        }

        if ($options->paths !== null && ! class_exists($options->paths)) {
            throw new InvalidStaticRoute("[inertia-static] Paths provider [{$options->paths}] of route [{$route->uri()}] does not exist.");
        }

        if ($options->mode === RenderMode::Static && $route->parameterNames() !== [] && $options->paths === null) {
            throw new InvalidStaticRoute("[inertia-static] Static route [{$route->uri()}] has parameters but no paths provider; the exporter needs an explicit, finite list of paths.");
        }
    }
}
