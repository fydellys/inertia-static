<?php

namespace Fydellys\InertiaStatic\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Normalizes the cache key of static pages: whitelisted + sorted query
 * string and a single trailing slash policy.
 */
class UrlNormalizer
{
    public function __construct(protected Repository $config) {}

    /**
     * The canonical relative URL when the request is not canonical, or null.
     */
    public function redirectTarget(Request $request, RouteOptions $options): ?string
    {
        if (! $this->config->get('inertia-static.normalization.enabled', true)) {
            return null;
        }

        $path = $request->getPathInfo();
        $rawQuery = (string) $request->server->get('QUERY_STRING', '');

        $canonical = $this->canonicalPath($path).$this->canonicalQuery($rawQuery, $options->query);
        $current = $path.($rawQuery !== '' ? '?'.$rawQuery : '');

        return $canonical === $current ? null : $canonical;
    }

    public function canonicalPath(string $path): string
    {
        // One spelling per page (RFC 3986): decode unreserved escapes, uppercase the others.
        $path = (string) preg_replace_callback('/%[0-9A-Fa-f]{2}/', function (array $match) {
            $char = rawurldecode($match[0]);

            return preg_match('/^[A-Za-z0-9._~-]$/', $char) === 1 ? $char : strtoupper($match[0]);
        }, $path);

        if ($path === '/' || $path === '') {
            return '/';
        }

        return match ($this->config->get('inertia-static.normalization.trailing_slash', 'never')) {
            'always' => Str::finish($path, '/'),
            'never' => rtrim($path, '/'),
            default => $path,
        };
    }

    /**
     * @param  array<int, string>  $whitelist
     */
    public function canonicalQuery(string $rawQuery, array $whitelist): string
    {
        if ($rawQuery === '') {
            return '';
        }

        parse_str($rawQuery, $params);

        $strip = (array) $this->config->get('inertia-static.normalization.strip_query', []);

        $params = collect($params)
            ->reject(fn ($value, $key) => Str::is($strip, (string) $key))
            ->only($whitelist)
            ->reject(fn ($value) => $value === '' || $value === null)
            ->sortKeys()
            ->all();

        return $params === [] ? '' : '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
