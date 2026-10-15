<?php

namespace Fydellys\InertiaStatic\Support;

use Fydellys\InertiaStatic\Ssr\SsrRecorder;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides, fail-safe, whether a response of a static route may be cached.
 * Anything unexpected results in no-store.
 */
class PolicyResolver
{
    public function __construct(
        protected Container $container,
        protected Repository $config,
        protected StaticRequestContext $context,
    ) {}

    public function resolve(Request $request, Response $response): CachePolicy
    {
        $route = $this->context->route;

        if ($route === null || ! $route->mode->isCacheable()) {
            return CachePolicy::noStore('dynamic');
        }

        if ($this->context->canonicalRedirect && $response->isRedirection()) {
            return $this->canonicalRedirect();
        }

        if ($reason = $this->bypassReason($request, $response)) {
            return CachePolicy::noStore($reason);
        }

        $defaults = (array) $this->config->get('inertia-static.defaults', []);

        $revalidate = (int) $this->context->overridden('revalidate', $route->revalidate ?? $defaults['revalidate'] ?? 3600);

        $policy = new CachePolicy(
            cacheable: true,
            revalidate: $revalidate,
            staleWhileRevalidate: $this->staleWhileRevalidate($route, $revalidate, $defaults),
            staleIfError: $this->context->overridden('stale_if_error', $route->staleIfError ?? $defaults['stale_if_error'] ?? null),
            tags: [...$this->context->tags(), ...$this->implicitTags($request)],
            browserCacheControl: (string) ($defaults['browser_cache_control'] ?? 'public, max-age=0, must-revalidate'),
        );

        if ($response->getStatusCode() === 404) {
            return $policy->withTtl((int) ($defaults['not_found_ttl'] ?? 60));
        }

        if ($response->isRedirection()) {
            return $policy->withTtl((int) ($defaults['redirect_ttl'] ?? 300));
        }

        return $policy;
    }

    /**
     * Explicit stale-while-revalidate wins; otherwise: expire - revalidate.
     *
     * @param  array<string, mixed>  $defaults
     */
    protected function staleWhileRevalidate(RouteOptions $route, int $revalidate, array $defaults): ?int
    {
        $explicit = $this->context->overridden('stale_while_revalidate', $route->staleWhileRevalidate ?? $defaults['stale_while_revalidate'] ?? null);

        if ($explicit !== null) {
            return $explicit;
        }

        $expire = $this->context->overridden('expire', $route->expire ?? $defaults['expire'] ?? null);

        return $expire === null ? null : (max(0, $expire - $revalidate) ?: null);
    }

    /**
     * @return array<int, string>
     */
    protected function implicitTags(Request $request): array
    {
        if (! $this->config->get('inertia-static.implicit_tags', true)) {
            return [];
        }

        return ImplicitTags::forPath($this->context->pagePath ?? $request->getPathInfo());
    }

    /**
     * Redirects to the canonical URL are deterministic and always cacheable.
     */
    public function canonicalRedirect(): CachePolicy
    {
        if ($this->context->bypass) {
            return CachePolicy::noStore($this->context->bypassReason ?? 'bypass');
        }

        return new CachePolicy(
            cacheable: true,
            revalidate: (int) $this->config->get('inertia-static.defaults.redirect_ttl', 300),
            browserCacheControl: (string) $this->config->get('inertia-static.defaults.browser_cache_control', 'public, max-age=0, must-revalidate'),
        );
    }

    protected function bypassReason(Request $request, Response $response): ?string
    {
        $status = $response->getStatusCode();

        return match (true) {
            ! $request->isMethodCacheable() => 'method',
            $this->context->bypass => $this->context->bypassReason ?? 'bypass',
            $this->context->hasViolations() => 'leak',
            $status >= 500 => 'server-error',
            $status === 404 => null,
            $response->isRedirection() && $status !== 304 => $this->config->get('inertia-static.defaults.cache_redirects') ? null : 'redirect',
            $status !== 200 => "status:{$status}",
            $this->isPartialReload($request) => 'partial-reload',
            $this->isInertiaVisitOnHtmlUrl($request) => 'inertia-json-on-html-url',
            $this->isDegradedHtml($request, $response) => 'ssr-unavailable',
            default => null,
        };
    }

    protected function isPartialReload(Request $request): bool
    {
        return $request->headers->has(Header::PARTIAL_COMPONENT)
            || $request->headers->has(Header::PARTIAL_ONLY)
            || $request->headers->has(Header::PARTIAL_EXCEPT)
            || $request->headers->has(Header::RESET);
    }

    /**
     * JSON responses must only be cached on their own URL (/__inertia/...).
     */
    protected function isInertiaVisitOnHtmlUrl(Request $request): bool
    {
        return ! $this->context->jsonEndpoint && $request->headers->has(Header::INERTIA);
    }

    /**
     * SSR is optional. When the app enables it and a page still renders without it
     * (SSR server down), that page is a degraded copy: never cache it.
     */
    protected function isDegradedHtml(Request $request, Response $response): bool
    {
        if ($this->context->jsonEndpoint
            || ! $this->config->get('inertia.ssr.enabled', false)
            || ! $this->config->get('inertia-static.ssr.required', true)) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        if ($this->container->make(SsrRecorder::class)->response() !== null) {
            return false;
        }

        Log::warning('[inertia-static] SSR unavailable: the HTML of a static page was sent with no-store.', ['url' => $request->getRequestUri()]);

        return true;
    }
}
