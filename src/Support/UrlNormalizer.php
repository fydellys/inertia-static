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
        $path = self::encodePath($path);

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
     * One spelling per page, the one the router sees: decode once (as the router does,
     * so "%2F" becomes a "/"), then percent-encode each segment (RFC 3986).
     * Raw and encoded spellings ("x!y", "x%21y", "x%2fy") all give the same path.
     */
    public static function encodePath(string $path): string
    {
        return self::encodeSegments(rawurldecode($path));
    }

    /**
     * Characters a path segment keeps plain (RFC 3986 sub-delims, ":" and "@"), as browsers and
     * route() send them. "*" stays encoded: CloudFront reads it as a wildcard in invalidations.
     */
    protected const PLAIN = ['%21' => '!', '%24' => '$', '%26' => '&', '%27' => "'", '%28' => '(', '%29' => ')', '%2B' => '+', '%2C' => ',', '%3B' => ';', '%3D' => '=', '%3A' => ':', '%40' => '@'];

    /**
     * Percent-encodes each segment of an already decoded path ("/blog/a b" => "/blog/a%20b").
     */
    public static function encodeSegments(string $decodedPath): string
    {
        return implode('/', array_map(fn (string $segment) => strtr(rawurlencode($segment), self::PLAIN), explode('/', $decodedPath)));
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
