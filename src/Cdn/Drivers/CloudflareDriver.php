<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Support\CachePolicy;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cloudflare: Cloudflare-CDN-Cache-Control + Cache-Tag, purge through
 * POST /zones/{zone_id}/purge_cache.
 */
class CloudflareDriver extends Driver
{
    public const API = 'https://api.cloudflare.com/client/v4';

    protected array $capabilities = [
        Capability::PurgeUrls,
        Capability::PurgeTags,
        Capability::PurgePrefix,
        Capability::PurgeAll,
        Capability::CacheTags,
    ];

    protected array $credentials = ['zone_id', 'api_token'];

    public function name(): string
    {
        return 'cloudflare';
    }

    public function cacheHeaders(CachePolicy $policy): array
    {
        return [
            'Cache-Control' => $policy->browserCacheControl,
            'Cloudflare-CDN-Cache-Control' => implode(', ', ['public', ...$policy->edgeDirectives()]),
        ];
    }

    public function tagHeaders(array $tags): array
    {
        return ['Cache-Tag' => $this->joinTags($tags, ',', 16 * 1024)];
    }

    /**
     * 100 items per request on every plan (Enterprise: 500 URLs).
     */
    public function batchSize(Capability $capability): int
    {
        return min((int) ($this->config['batch_size'] ?? 100), $capability === Capability::PurgeUrls ? 500 : 100);
    }

    public function purgeUrls(array $urls): void
    {
        $this->purge(['files' => array_values($urls)]);
    }

    public function purgeTags(array $tags): void
    {
        $this->purge(['tags' => array_values($tags)]);
    }

    public function purgePrefixes(array $prefixes): void
    {
        // Prefixes are sent without the scheme: "www.example.com/blog".
        $this->purge(['prefixes' => array_values(array_map(
            fn (string $prefix) => Str::after($prefix, '://'),
            $prefixes,
        ))]);
    }

    /**
     * "Everything" means this application's hostname by default: a zone is often
     * shared by several sites, and purge_everything would empty all of them.
     * Set "purge_everything" => true to purge the whole zone instead.
     */
    public function purgeAll(): void
    {
        $hosts = array_values(array_filter((array) ($this->config['hosts'] ?? [])));

        if (($this->config['purge_everything'] ?? false) || $hosts === []) {
            $this->purge(['purge_everything' => true]);

            return;
        }

        $this->purge(['hosts' => $hosts]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function purge(array $payload): void
    {
        $this->ensureCredentials();

        $response = $this->request()
            ->withToken((string) $this->config['api_token'])
            ->acceptJson()
            ->post(self::API."/zones/{$this->config['zone_id']}/purge_cache", $payload)
            ->throw();

        // The API can answer 200 with "success": false.
        if ($response->json('success') !== true) {
            throw new RuntimeException('[inertia-static] Cloudflare purge failed: '.json_encode($response->json('errors')));
        }
    }
}
