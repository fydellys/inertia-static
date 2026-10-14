<?php

namespace Fydellys\InertiaStatic\Http\Middleware;

use Closure;
use Fydellys\InertiaStatic\Support\AssetVersion;
use Fydellys\InertiaStatic\Support\RouteOptions;
use Fydellys\InertiaStatic\Support\StaticUrls;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Global middleware: an Inertia visit that is redirected to a static page
 * (return back() after a form, redirect()->route(...), logout...) is sent to
 * the page's never-cached live JSON URL instead of its HTML URL.
 *
 * The browser follows redirects inside the XHR, without the client plugin: a
 * CDN ignoring "Vary: X-Inertia" would answer the HTML URL with the cached HTML
 * (inertia#1923). The live endpoint also carries the visitor's validation
 * errors and Inertia flash data, which a cached page never can.
 */
class RewriteInertiaRedirects
{
    public function __construct(
        protected Repository $config,
        protected Router $router,
        protected StaticUrls $urls,
        protected AssetVersion $version,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->config->get('inertia-static.enabled', true)
            || ! $request->headers->has(Header::INERTIA)
            || ! $response->isRedirection()
            || ! ($location = $response->headers->get('Location'))) {
            return $response;
        }

        if ($target = $this->liveTarget($request, $location)) {
            $response->headers->set('Location', $target);
        }

        return $response;
    }

    protected function liveTarget(Request $request, string $location): ?string
    {
        $parts = parse_url($location);

        if ($parts === false || isset($parts['fragment'])) {
            return null;
        }

        if (isset($parts['host']) && ! in_array($parts['host'], [$request->getHost(), parse_url($this->urls->baseUrl(), PHP_URL_HOST)], true)) {
            return null; // another site
        }

        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        if (str_starts_with($path, '/'.$this->urls->prefix().'/')) {
            return null; // already a JSON endpoint
        }

        try {
            $route = $this->router->getRoutes()->match(Request::create($path, 'GET'));
        } catch (HttpExceptionInterface) {
            return null;
        }

        $options = RouteOptions::fromRoute($route);

        if ($options === null || ! $options->mode->isCacheable()) {
            return null; // dynamic page: the normal Inertia flow is fine
        }

        $version = $request->headers->get(Header::VERSION);

        $live = $this->urls->liveJson($path, $version ?? $this->version->current($request));

        return isset($parts['host']) ? $this->urls->absolute($live) : $live;
    }
}
