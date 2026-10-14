<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Support\CachePolicy;

/**
 * nginx proxy_cache with the ngx_cache_purge module (see docs/nginx.conf).
 * No tags: tag purges are expanded to URLs through the tag registry.
 */
class NginxDriver extends Driver
{
    protected array $capabilities = [
        Capability::PurgeUrls,
        Capability::PurgePrefix,
    ];

    protected array $credentials = ['hosts'];

    public function name(): string
    {
        return 'nginx';
    }

    public function cacheHeaders(CachePolicy $policy): array
    {
        // X-Accel-Expires is read by nginx only and takes precedence over
        // Cache-Control; nginx strips it before the response leaves.
        return [
            'Cache-Control' => $policy->browserCacheControl,
            'X-Accel-Expires' => (string) $policy->revalidate,
        ];
    }

    public function purgeUrls(array $urls): void
    {
        foreach ($this->config['hosts'] ?? [] as $host) {
            foreach ($urls as $url) {
                $this->purgePath($host, $url);
            }
        }
    }

    public function purgePrefixes(array $prefixes): void
    {
        $this->purgeUrls(array_map(fn (string $prefix) => rtrim($prefix, '*').'*', $prefixes));
    }

    protected function purgePath(string $host, string $url): void
    {
        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $response = $this->http
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5))
            ->retry([200, 1000, 3000], throw: false, when: fn ($exception) => $this->shouldRetry($exception))
            ->withHeaders(['Host' => $this->hostOf($parts)])
            ->get(rtrim($host, '/').rtrim((string) ($this->config['purge_path'] ?? '/purge'), '/').$path);

        // ngx_cache_purge answers 404 / 412 when the key is not cached: already purged.
        if (! in_array($response->status(), [404, 412], true)) {
            $response->throw();
        }
    }
}
