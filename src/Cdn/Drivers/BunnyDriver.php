<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Support\CachePolicy;
use Illuminate\Support\Str;

/**
 * Bunny CDN: purge by URL (a trailing "*" makes it a prefix purge) and the
 * whole pull zone.
 *
 * Tags are deliberately NOT used: Bunny documents a single CDN-Tag value per
 * response (max 1 KB) without a documented multi-tag separator, so a tag
 * purge could silently miss. Tag purges go through the tag registry and
 * become URL purges instead (fail-safe). Opt in with "native_tags" => true.
 */
class BunnyDriver extends Driver
{
    public const API = 'https://api.bunny.net';

    protected array $capabilities = [
        Capability::PurgeUrls,
        Capability::PurgePrefix,
        Capability::PurgeAll,
    ];

    public function supports(Capability $capability): bool
    {
        if (in_array($capability, [Capability::PurgeTags, Capability::CacheTags], true)) {
            return (bool) ($this->config['native_tags'] ?? false);
        }

        return parent::supports($capability);
    }

    protected array $credentials = ['pull_zone_id', 'api_key'];

    public function name(): string
    {
        return 'bunny';
    }

    public function cacheHeaders(CachePolicy $policy): array
    {
        return [
            'Cache-Control' => $policy->browserCacheControl,
            'CDN-Cache-Control' => implode(', ', ['public', ...$policy->edgeDirectives()]),
        ];
    }

    public function tagHeaders(array $tags): array
    {
        if (! $this->supports(Capability::CacheTags)) {
            return [];
        }

        return ['CDN-Tag' => $this->joinTags(array_slice($tags, 0, 1), ',', 1024)];
    }

    public function batchSize(Capability $capability): int
    {
        return 1;
    }

    public function purgeUrls(array $urls): void
    {
        $this->ensureCredentials();

        foreach ($urls as $url) {
            $this->request()
                ->withHeaders(['AccessKey' => (string) $this->config['api_key']])
                ->post(self::API.'/purge?'.http_build_query(['url' => $url, 'async' => 'false']))
                ->throw();
        }
    }

    public function purgePrefixes(array $prefixes): void
    {
        $this->purgeUrls(array_map(fn (string $prefix) => Str::finish($prefix, '*'), $prefixes));
    }

    public function purgeTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->purgeZone(['CacheTag' => $tag]);
        }
    }

    public function purgeAll(): void
    {
        $this->purgeZone();
    }

    /**
     * @param  array<string, string>  $payload
     */
    protected function purgeZone(array $payload = []): void
    {
        $this->ensureCredentials();

        $this->request()
            ->withHeaders(['AccessKey' => (string) $this->config['api_key']])
            ->acceptJson()
            ->post(self::API."/pullzone/{$this->config['pull_zone_id']}/purgeCache", $payload ?: (object) [])
            ->throw();
    }
}
