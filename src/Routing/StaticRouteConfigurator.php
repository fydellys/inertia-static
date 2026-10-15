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
     * Turn a static route back into a regular dynamic one (session pipeline restored).
     */
    public function downgrade(Route $route, string $reason): Route
    {
        $action = $route->getAction();
        $stripped = $this->strippedMiddleware();

        $action[RouteOptions::ACTION_KEY] = ['mode' => RenderMode::Dynamic->value, 'downgraded' => $reason] + (array) ($action[RouteOptions::ACTION_KEY] ?? []);
        $action[RouteOptions::ACTION_KEY]['mode'] = RenderMode::Dynamic->value;
        $action['middleware'] = array_values(array_diff((array) ($action['middleware'] ?? []), [RenderStatically::ALIAS]));
        $action['excluded_middleware'] = array_values(array_diff((array) ($action['excluded_middleware'] ?? []), $stripped));

        $route->setAction($action);
        $route->computedMiddleware = null;

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

        // GET only: the session and CSRF middleware are removed from the route, so another
        // verb on it (Route::match / Route::any) would run without CSRF protection.
        if (! in_array('GET', $route->methods(), true) || array_diff($route->methods(), ['GET', 'HEAD']) !== []) {
            throw new InvalidStaticRoute("[inertia-static] Route [{$route->uri()}] must be a GET route to be {$options->mode->value}.");
        }

        if ($options->revalidate !== null && $options->revalidate < 0) {
            throw new InvalidStaticRoute("[inertia-static] Route [{$route->uri()}] has a negative revalidate.");
        }

        if ($options->paths !== null && ! class_exists($options->paths)) {
            throw new InvalidStaticRoute("[inertia-static] Paths provider [{$options->paths}] of route [{$route->uri()}] does not exist.");
        }

        if ($options->mode === RenderMode::Static && $route->parameterNames() !== [] && $options->paths === null) {
            throw new InvalidStaticRoute("[inertia-static] Static route [{$route->uri()}] has parameters but no paths provider; static pages must be listed in advance.");
        }
    }
}
