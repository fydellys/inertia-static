<?php

namespace Fydellys\InertiaStatic\Http\Middleware;

use Closure;
use Fydellys\InertiaStatic\Exceptions\InvalidStaticRoute;
use Fydellys\InertiaStatic\Routing\StaticRouteConfigurator;
use Fydellys\InertiaStatic\Security\RenderGuard;
use Fydellys\InertiaStatic\Ssr\SsrRecorder;
use Fydellys\InertiaStatic\Support\Downgrades;
use Fydellys\InertiaStatic\Support\RouteOptions;
use Fydellys\InertiaStatic\Support\StaticRequestContext;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware attached to every "isr" / "static" route. It runs after
 * HandleInertiaRequests::share(), so it can reduce the shared props to the
 * public whitelist, then renders the page with the leak detector armed.
 */
class RenderStatically
{
    public const ALIAS = 'inertia-static';

    public function __construct(
        protected Container $container,
        protected Repository $config,
        protected StaticRequestContext $context,
        protected RenderGuard $guard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $options = RouteOptions::fromRoute($request->route());

        if (! $this->config->get('inertia-static.enabled', true) || $options === null || ! $options->mode->isCacheable()) {
            return $next($request);
        }

        $this->context->activate($options);

        $this->restrictSharedProps();

        $response = $this->guard->protect($request, fn () => $next($request));

        if (($pending = $this->context->pendingDowngrade) !== null && $response->getStatusCode() < 500 && ($response->exception ?? null) === null) {
            $this->container->make(Downgrades::class)->add($pending['route'], $pending['reason']);
            // Also on the route in memory: a long-running worker (Octane) only reads the store when it boots.
            $this->container->make(StaticRouteConfigurator::class)->downgrade($pending['route'], $pending['reason']);
        }

        $this->guardDeferredProps($response);

        return $response;
    }

    /**
     * Keep only the whitelisted shared props; "auth", "flash" and "errors"
     * never reach a cached page.
     */
    protected function restrictSharedProps(): void
    {
        $shared = Inertia::getShared();

        Inertia::flushShared();

        Inertia::share(Arr::only($shared, (array) $this->config->get('inertia-static.public_shared_props', [])));
        Inertia::share('errors', Inertia::always((object) []));
    }

    protected function guardDeferredProps(Response $response): void
    {
        $page = $response instanceof JsonResponse
            ? (array) $response->getData(true)
            : $this->container->make(SsrRecorder::class)->page();

        if (empty($page['deferredProps'])) {
            return;
        }

        $groups = implode(', ', array_keys((array) $page['deferredProps']));

        if ($this->config->get('inertia-static.deferred_props', 'throw') === 'throw') {
            throw new InvalidStaticRoute("[inertia-static] Static page [{$page['url']}] uses deferred props ({$groups}). Resolve them during the static render, or set inertia-static.deferred_props to \"allow\" to fetch them from the origin.");
        }
    }
}
