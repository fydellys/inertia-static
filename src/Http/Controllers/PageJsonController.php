<?php

namespace Fydellys\InertiaStatic\Http\Controllers;

use Fydellys\InertiaStatic\Support\RouteOptions;
use Fydellys\InertiaStatic\Support\StaticRequestContext;
use Fydellys\InertiaStatic\Support\StaticUrls;
use Fydellys\InertiaStatic\Support\UrlNormalizer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\MessageBag as MessageBagContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the Inertia page JSON of a static route on its own URLs:
 *
 * GET /__inertia/{version}/{path}.json        cacheable (full page visits, prefetch)
 * GET /__inertia/live/{version}/{path}.json   never cached (reloads, polling, partial
 *                                             reloads, deferred props)
 *
 * An Inertia visit to a static page must never use the HTML URL: CDNs that
 * ignore "Vary: X-Inertia" would answer it with the cached HTML (inertia#1923).
 *
 * Only routes declared as "isr" / "static" are reachable; dynamic routes
 * return 404, so this endpoint can never expose private pages to the CDN.
 * A version mismatch is answered by Inertia itself (409 + X-Inertia-Location).
 */
class PageJsonController
{
    public function __construct(
        protected Container $container,
        protected Router $router,
        protected StaticUrls $urls,
        protected UrlNormalizer $normalizer,
    ) {}

    /**
     * Resolved per call: the router keeps controller instances between requests
     * (Octane), so request-scoped state must never live in a property.
     */
    protected function context(): StaticRequestContext
    {
        return $this->container->make(StaticRequestContext::class);
    }

    /**
     * Inertia request headers forwarded by the live endpoint.
     */
    public const FORWARDED_HEADERS = [
        Header::PARTIAL_COMPONENT,
        Header::PARTIAL_ONLY,
        Header::PARTIAL_EXCEPT,
        Header::RESET,
        Header::EXCEPT_ONCE_PROPS,
        Header::INFINITE_SCROLL_MERGE_INTENT,
        Header::ERROR_BAG,
        'Purpose',
    ];

    public function live(Request $request, string $version, string $path): Response
    {
        $this->context()->bypass('live');

        return $this->withSessionData($request, $this->__invoke($request, $version, $path, live: true));
    }

    /**
     * The live endpoint is never cached, so it may carry what belongs to this
     * visitor only: the validation errors and the Inertia flash data left in
     * the session by the request that redirected here (return back()).
     */
    protected function withSessionData(Request $request, Response $response): Response
    {
        if (! $request->hasSession() || ! $response instanceof JsonResponse || $response->getStatusCode() !== 200) {
            return $response;
        }

        $page = (array) $response->getData(true);

        if (! isset($page['component'])) {
            return $response;
        }

        if ($errors = $this->sessionErrors($request)) {
            $page['props']['errors'] = $errors;
        }

        if ($flash = $request->session()->pull('inertia.flash_data')) {
            $page['flash'] = $flash;
        }

        return $response->setData($page);
    }

    /**
     * Same format as Inertia\Middleware::resolveValidationErrors().
     *
     * @return array<string, mixed>
     */
    protected function sessionErrors(Request $request): array
    {
        $bags = $request->session()->get('errors');

        if (! $bags instanceof ViewErrorBag) {
            return [];
        }

        $errors = collect($bags->getBags())
            ->map(fn (MessageBagContract $bag): array => array_map(
                fn (array $messages) => $messages[0],
                $bag->getMessages(),
            ));

        if ($errors->has('default') && ($bag = $request->headers->get(Header::ERROR_BAG))) {
            return [$bag => $errors->get('default')];
        }

        return $errors->has('default') ? (array) $errors->get('default') : $errors->all();
    }

    public function __invoke(Request $request, string $version, string $path, bool $live = false): Response
    {
        $pagePath = $this->urls->pagePathFromJson($path) ?? throw new NotFoundHttpException;

        // The router already decoded the segments: a "?" or "#" here was smuggled as %3F / %23.
        if (Str::contains($pagePath, ['?', '#'])) {
            throw new NotFoundHttpException;
        }

        $version = $version === '_' ? '' : $version;
        $page = $this->pageRequest($request, $pagePath, $version, $live);

        try {
            $route = $this->router->getRoutes()->match($page);
        } catch (HttpExceptionInterface) {
            throw new NotFoundHttpException;
        }

        $options = RouteOptions::fromRoute($route);

        if ($options === null || ! $options->mode->isCacheable()) {
            throw new NotFoundHttpException;
        }

        $this->context()->activate($options);
        $this->context()->jsonEndpoint = true;

        // One cache entry per page: like the HTML URL, a
        // non-canonical query (tracking params, unknown keys, order) gets a 301.
        if (! $live && ($canonical = $this->canonicalJsonUrl($request, $version, $pagePath, $options))) {
            $this->context()->canonicalRedirect = true;

            return redirect($this->urls->absolute($canonical), 301);
        }
        $this->context()->pagePath = $pagePath.($page->getQueryString() ? '?'.$page->getQueryString() : '');

        return $this->dispatch($request, $page);
    }

    protected function canonicalJsonUrl(Request $request, string $version, string $pagePath, RouteOptions $options): ?string
    {
        $raw = (string) $request->server->get('QUERY_STRING', '');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $pagePath)));
        $canonical = $this->urls->json($encoded, $version).$this->normalizer->canonicalQuery($raw, $options->query);

        return $canonical === $request->getPathInfo().($raw === '' ? '' : '?'.$raw) ? null : $canonical;
    }

    protected function pageRequest(Request $request, string $pagePath, string $version, bool $live = false): Request
    {
        $page = Request::create(
            $pagePath,
            'GET',
            $request->query->all(),
            [],
            [],
            array_intersect_key($request->server->all(), array_flip(['HTTP_HOST', 'SERVER_NAME', 'SERVER_PORT', 'HTTPS', 'REMOTE_ADDR'])),
        );

        $page->headers->set(Header::INERTIA, 'true');
        $page->headers->set(Header::VERSION, $version);
        $page->headers->set('X-Requested-With', 'XMLHttpRequest');
        $page->headers->set('Accept', 'text/html, application/xhtml+xml');

        if ($live) {
            foreach (self::FORWARDED_HEADERS as $header) {
                if ($request->headers->has($header)) {
                    $page->headers->set($header, (string) $request->headers->get($header));
                }
            }
        }

        if ($request->headers->has('Authorization')) {
            $page->headers->set('Authorization', (string) $request->headers->get('Authorization'));
        }

        return $page;
    }

    protected function dispatch(Request $original, Request $page): Response
    {
        $this->container->instance('request', $page);
        Facade::clearResolvedInstance('request');

        try {
            return $this->router->dispatch($page);
        } finally {
            $this->container->instance('request', $original);
            Facade::clearResolvedInstance('request');
        }
    }
}
