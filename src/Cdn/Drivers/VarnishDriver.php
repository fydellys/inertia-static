<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Enums\PurgeMode;
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
        // Edge directives go in Surrogate-Control (read and removed by the VCL): in Cache-Control,
        // stale-while-revalidate would also let browsers serve their stale copy.
        return [
            'Cache-Control' => $policy->browserCacheControl,
            'Surrogate-Control' => implode(', ', $policy->edgeDirectives()),
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
        // docs/varnish.vcl: xkey-softpurge marks stale (grace), xkey-purge removes.
        $header = $this->mode === PurgeMode::Soft ? 'xkey-softpurge' : 'xkey-purge';

        $this->each('PURGE', [$header => implode(' ', $tags)]);
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
