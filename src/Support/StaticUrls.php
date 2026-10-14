<?php

namespace Fydellys\InertiaStatic\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

/**
 * Builds the canonical HTML / JSON URLs of static pages.
 *
 * Absolute URLs are always derived from the configured base URL, never from
 * request headers, so a forged Host / X-Forwarded-Host cannot poison them.
 */
class StaticUrls
{
    public function __construct(
        protected Repository $config,
        protected AssetVersion $version,
    ) {}

    public function prefix(): string
    {
        return trim((string) $this->config->get('inertia-static.json.prefix', '__inertia'), '/');
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->config->get('inertia-static.base_url'), '/');
    }

    public function absolute(string $pathOrUrl): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://'])) {
            return $pathOrUrl;
        }

        return $this->baseUrl().'/'.ltrim($pathOrUrl, '/');
    }

    /**
     * Relative path of a URL ("/blog/post?page=2").
     */
    public function relative(string $pathOrUrl): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://'])) {
            $parts = parse_url($pathOrUrl);
            $pathOrUrl = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return '/'.ltrim($pathOrUrl, '/');
    }

    /**
     * The JSON URL of a page: /__inertia/{version}/{path}.json[?query]
     */
    public function json(string $pathOrUrl, ?string $version = null): string
    {
        $relative = $this->relative($pathOrUrl);
        $path = trim(Str::before($relative, '?'), '/');
        $query = Str::contains($relative, '?') ? '?'.Str::after($relative, '?') : '';

        $path = $path === '' ? $this->config->get('inertia-static.json.index', 'index') : $path;

        return '/'.$this->prefix().'/'.$this->version->segment($version).'/'.$path.'.json'.$query;
    }

    /**
     * The never-cached JSON URL of a page: /__inertia/live/{version}/{path}.json[?query]
     */
    public function liveJson(string $pathOrUrl, ?string $version = null): string
    {
        $json = $this->json($pathOrUrl, $version);

        return '/'.$this->prefix().'/live'.Str::after($json, '/'.$this->prefix());
    }

    /**
     * The JSON URL prefix of a page prefix: "/blog" => "/__inertia/{v}/blog".
     */
    public function jsonPrefix(string $prefix, ?string $version = null): string
    {
        $path = trim(Str::before($this->relative($prefix), '?'), '/');

        return '/'.$this->prefix().'/'.$this->version->segment($version).'/'.$path;
    }

    /**
     * Inverse of json(): the page path for a "{path}.json" endpoint segment.
     */
    public function pagePathFromJson(string $jsonPath): ?string
    {
        if (! Str::endsWith($jsonPath, '.json')) {
            return null;
        }

        $path = trim(Str::beforeLast($jsonPath, '.json'), '/');

        return $path === $this->config->get('inertia-static.json.index', 'index') ? '/' : '/'.$path;
    }

    /**
     * Every absolute URL a page is cached under (HTML + JSON).
     *
     * @param  array<int, string>  $pathsOrUrls
     * @return array<int, string>
     */
    public function variants(array $pathsOrUrls, ?string $version = null): array
    {
        $version ??= $this->version->current();

        return collect($pathsOrUrls)
            ->flatMap(fn (string $url) => Str::contains($url, '/'.$this->prefix().'/')
                ? [$this->absolute($url)]
                : [$this->absolute($url), $this->absolute($this->json($url, $version))])
            ->unique()
            ->values()
            ->all();
    }
}
