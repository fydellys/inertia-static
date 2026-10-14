<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Support\CachePolicy;

/**
 * Varnish with the xkey vmod. The purge protocol (PURGE / BAN methods and
 * the xkey-purge / X-Ban-Prefix headers) matches docs/varnish.vcl.
 */
class VarnishDriver extends Driver
{
    protected array $capabilities = [
        Capability::PurgeUrls,
        Capability::PurgeTags,
        Capability::PurgePrefix,
        Capability::PurgeAll,
        Capability::CacheTags,
    ];

    protected array $credentials = ['hosts'];

    public function name(): string
    {
        return 'varnish';
    }

    public function cacheHeaders(CachePolicy $policy): array
    {
        // Varnish's builtin VCL honours s-maxage before max-age.
        return [
            'Cache-Control' => implode(', ', ['public', 'max-age=0', ...$policy->edgeDirectives('s-maxage')]),
        ];
    }

    public function tagHeaders(array $tags): array
    {
        return [$this->config['tag_header'] ?? 'xkey' => $this->joinTags($tags, ' ', 8 * 1024)];
    }

    public function purgeUrls(array $urls): void
    {
        foreach ($this->hosts() as $host) {
            foreach ($urls as $url) {
                $parts = parse_url($url);

                $this->request()
                    ->withHeaders(['Host' => $this->hostOf($parts)])
                    ->send('PURGE', rtrim($host, '/').($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : ''))
                    ->throw();
            }
        }
    }

    public function purgeTags(array $tags): void
    {
        $this->each('PURGE', ['xkey-purge' => implode(' ', $tags)]);
    }

    public function purgePrefixes(array $prefixes): void
    {
        foreach ($prefixes as $prefix) {
            $parts = parse_url($prefix);

            $this->each('BAN', ['X-Ban-Host' => $this->hostOf($parts), 'X-Ban-Prefix' => $parts['path'] ?? '/']);
        }
    }

    public function purgeAll(): void
    {
        $this->each('BAN', ['X-Ban-Prefix' => '/']);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function each(string $method, array $headers): void
    {
        foreach ($this->hosts() as $host) {
            $this->request()->withHeaders($headers)->send($method, rtrim($host, '/').'/')->throw();
        }
    }

    /**
     * @return array<int, string>
     */
    protected function hosts(): array
    {
        return array_values((array) ($this->config['hosts'] ?? []));
    }
}
