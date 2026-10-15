<?php

namespace Fydellys\InertiaStatic\Support;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Compiles the static routes into JavaScript-compatible patterns.
 */
class Manifest
{
    public function __construct(
        protected Router $router,
        protected StaticUrls $urls,
    ) {}

    /**
     * @return Collection<int, array{route: Route, options: RouteOptions}>
     */
    public function routes(): Collection
    {
        return collect($this->router->getRoutes()->getRoutes())
            ->map(fn (Route $route) => ['route' => $route, 'options' => RouteOptions::fromRoute($route)])
            ->filter(fn (array $entry) => $entry['options'] !== null && in_array('GET', $entry['route']->methods(), true))
            ->values();
    }

    /**
     * @return array{prefix: string, previewCookie: string, csrfCookieUrl?: string, routes: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return array_filter([
            'prefix' => '/'.$this->urls->prefix(),
            'previewCookie' => (string) config('inertia-static.preview.cookie', 'inertia_static_preview'),
            'csrfCookieUrl' => $this->needsCsrfCookie() ? '/'.$this->urls->prefix().'/csrf' : null,
            'routes' => $this->routes()
                ->filter(fn (array $entry) => $entry['options']->mode->isCacheable())
                ->map(fn (array $entry) => array_filter([
                    'name' => $entry['route']->getName(),
                    'pattern' => $this->pattern($entry['route']),
                    'mode' => $entry['options']->mode->value,
                    'query' => $entry['options']->query,
                    'domain' => $entry['route']->getDomain(),
                ], fn ($value) => $value !== null))
                ->values()
                ->all(),
        ], fn ($value) => $value !== null);
    }

    /**
     * Static pages carry no CSRF cookie. Laravel 13 accepts same-origin requests
     * without a token (PreventRequestForgery); older versions need the cookie, so
     * the client fetches it before the first form submission.
     */
    public function needsCsrfCookie(): bool
    {
        $configured = config('inertia-static.csrf_cookie');

        return is_bool($configured) ? $configured : ! class_exists(PreventRequestForgery::class);
    }

    /**
     * A JavaScript RegExp source matching the route path ("^/blog/([^/]+)$").
     */
    public function pattern(Route $route): string
    {
        $uri = trim($route->uri(), '/');

        if ($uri === '') {
            return '^/$';
        }

        $pattern = collect(explode('/', $uri))
            ->map(function (string $segment) use ($route) {
                if (! preg_match('/^\{(\w+)(?::\w+)?(\??)\}$/', $segment, $match)) {
                    return '/'.preg_quote($segment, '/');
                }

                $constraint = $this->jsConstraint($route->wheres[$match[1]] ?? '[^/]+');

                return $match[2] !== ''
                    ? "(?:/({$constraint}))?"
                    : "/({$constraint})";
            })
            ->implode('');

        return '^'.$pattern.'/?$';
    }

    protected function jsConstraint(string $where): string
    {
        // Possessive quantifiers and named groups are not valid in JS RegExp.
        return Str::of($where)
            ->replace(['++', '*+', '?+'], ['+', '*', '?'])
            ->replaceMatches('/\(\?P?<\w+>/', '(?:')
            ->toString();
    }
}
