<?php

namespace Fydellys\InertiaStatic\Http\Middleware;

use Closure;
use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Contracts\TagRegistry;
use Fydellys\InertiaStatic\Support\CachePolicy;
use Fydellys\InertiaStatic\Support\ImplicitTags;
use Fydellys\InertiaStatic\Support\PolicyResolver;
use Fydellys\InertiaStatic\Support\Preview;
use Fydellys\InertiaStatic\Support\ResponseFinalizer;
use Fydellys\InertiaStatic\Support\RouteOptions;
use Fydellys\InertiaStatic\Support\StaticRequestContext;
use Fydellys\InertiaStatic\Support\StaticUrls;
use Fydellys\InertiaStatic\Support\UrlNormalizer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Global middleware (outermost layer of static routes).
 *
 * Before the router: identifies static routes, drops the incoming cookies
 * (no identity can be resolved) and redirects to the canonical URL.
 * After the router: decides the cache policy and finalizes the headers.
 */
class ApplyStaticCache
{
    public function __construct(
        protected Repository $config,
        protected Router $router,
        protected StaticRequestContext $context,
        protected PolicyResolver $policies,
        protected ResponseFinalizer $finalizer,
        protected UrlNormalizer $normalizer,
        protected CdnManager $cdn,
        protected TagRegistry $registry,
        protected StaticUrls $urls,
        protected Preview $preview,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->reset();

        if (! $this->config->get('inertia-static.enabled', true) || ! $request->isMethodCacheable()) {
            return $next($request);
        }

        $route = $this->matchRoute($request);
        $options = RouteOptions::fromRoute($route);

        // The JSON endpoint activates the context itself once it has matched
        // the page route; only finalize when it did.
        $jsonEndpoint = in_array($route?->getName(), ['inertia-static.page', 'inertia-static.live'], true);

        if (! $jsonEndpoint && ($options === null || ! $options->mode->isCacheable())) {
            return $next($request);
        }

        if ($options !== null) {
            $this->context->activate($options);
        }

        // Inertia DevTools stamps a per-request id into the HTML and headers:
        // never let it reach a shared cache.
        $this->config->set('inertia.devtools.enabled', false);

        if ($request->headers->has('Authorization')) {
            $this->context->bypass('authorization');
        }

        // Preview mode: render fresh, never cache (read before the cookies are dropped).
        if ($this->preview->isActive($request)) {
            $this->context->preview = true;
            $this->context->bypass('preview');
        }

        // No identity on cacheable requests. The live endpoint is never cached and
        // reads the session (validation errors / flash of the visitor's form).
        if ($route?->getName() !== 'inertia-static.live') {
            $request->cookies->replace([]);
            $request->headers->remove('Cookie');
        }

        // The CDN keys pages on Host + URL only: forwarded host headers sent by a client would
        // otherwise change url()/route()/asset() in a page cached for everyone (trusted proxies).
        foreach (['X-Forwarded-Host', 'X-Forwarded-Port', 'X-Forwarded-Prefix', 'Forwarded'] as $header) {
            $request->headers->remove($header);
        }

        if ($options !== null && ! $this->context->bypass && $target = $this->normalizer->redirectTarget($request, $options)) {
            return $this->finalizer->finalize(
                $request,
                redirect($this->urls->absolute($target), 301),
                $this->policies->canonicalRedirect(),
                $this->cdn->driver(),
            );
        }

        $response = $next($request);

        $policy = $this->context->isActive()
            ? $this->policies->resolve($request, $response)
            : CachePolicy::noStore('not-static');

        $response = $this->finalizer->finalize($request, $response, $policy, $this->cdn->driver(), $this->context->jsonEndpoint);

        if ($policy->cacheable && $policy->tags !== [] && $response->getStatusCode() === 200) {
            $this->registry->record(array_values(array_filter($policy->tags, fn (string $tag) => ! ImplicitTags::isImplicit($tag))), [$this->context->pagePath ?? $this->urls->relative($request->getRequestUri())]);
        }

        return $response;
    }

    protected function matchRoute(Request $request): ?Route
    {
        try {
            return $this->router->getRoutes()->match($request);
        } catch (HttpExceptionInterface) {
            return null;
        }
    }
}
