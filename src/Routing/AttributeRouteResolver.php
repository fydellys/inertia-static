<?php

namespace Fydellys\InertiaStatic\Routing;

use Fydellys\InertiaStatic\Attributes\StaticPage;
use Fydellys\InertiaStatic\Support\RouteOptions;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionClass;
use ReflectionMethod;

/**
 * Applies #[StaticPage] attributes found on controllers to their routes.
 */
class AttributeRouteResolver
{
    public function __construct(
        protected Router $router,
        protected StaticRouteConfigurator $configurator,
    ) {}

    public function resolve(): void
    {
        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($route->getAction(RouteOptions::ACTION_KEY) !== null) {
                continue;
            }

            if ($attribute = $this->attributeFor($route)) {
                $this->configurator->apply($route, $attribute->renderMode(), $attribute->toOptions());
            }
        }
    }

    protected function attributeFor(Route $route): ?StaticPage
    {
        $uses = $route->getAction('uses');

        if (! is_string($uses)) {
            return null;
        }

        [$class, $method] = str_contains($uses, '@') ? explode('@', $uses, 2) : [$uses, '__invoke'];

        if (! class_exists($class)) {
            return null;
        }

        if (method_exists($class, $method)) {
            $attributes = (new ReflectionMethod($class, $method))->getAttributes(StaticPage::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        $attributes = (new ReflectionClass($class))->getAttributes(StaticPage::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
