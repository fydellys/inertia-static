<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Enums\PurgeMode;
use Fydellys\InertiaStatic\Support\CachePolicy;
use Illuminate\Support\Str;

/**
 * Fastly: Surrogate-Control + Surrogate-Key, purge by key (batched),
 * by URL and purge_all. No prefix purge (falls back to the tag registry).
 */
class FastlyDriver extends Driver
{
    public const API = 'https://api.fastly.com';

    protected array $capabilities = [
        Capability::PurgeUrls,
        Capability::PurgeTags,
        Capability::PurgeAll,
        Capability::CacheTags,
    ];

    protected array $credentials = ['service_id', 'api_token'];

    public function name(): string
    {
        return 'fastly';
    }

    public function cacheHeaders(CachePolicy $policy): array
    {
        return [
            'Cache-Control' => $policy->browserCacheControl,
            'Surrogate-Control' => implode(', ', $policy->edgeDirectives()),
        ];
    }

    public function tagHeaders(array $tags): array
    {
        return ['Surrogate-Key' => $this->joinTags($tags, ' ', 16 * 1024)];
    }

    public function batchSize(Capability $capability): int
    {
        return match ($capability) {
            Capability::PurgeTags => 256,
            default => (int) ($this->config['batch_size'] ?? 100),
        };
    }

    public function purgeUrls(array $urls): void
    {
        $this->ensureCredentials();

        foreach ($urls as $url) {
            $this->request()
                ->withHeaders($this->purgeHeaders())
                ->acceptJson()
                ->post(self::API.'/purge/'.Str::after($url, '://'))
                ->throw();
        }
    }

    public function purgeTags(array $tags): void
    {
        $this->ensureCredentials();

        $this->request()
            ->withHeaders($this->purgeHeaders() + ['Surrogate-Key' => implode(' ', $tags)])
            ->acceptJson()
            ->post(self::API."/service/{$this->config['service_id']}/purge")
            ->throw();
    }

    public function purgeAll(): void
    {
        $this->ensureCredentials();

        $this->request()
            ->withHeaders(['Fastly-Key' => (string) $this->config['api_token']])
            ->acceptJson()
            ->post(self::API."/service/{$this->config['service_id']}/purge_all")
            ->throw();
    }

    /**
     * @return array<string, string>
     */
    protected function purgeHeaders(): array
    {
        return array_filter([
            'Fastly-Key' => (string) $this->config['api_token'],
            'Fastly-Soft-Purge' => $this->mode === PurgeMode::Soft ? '1' : null,
        ]);
    }
}
