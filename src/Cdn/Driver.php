<?php

namespace Fydellys\InertiaStatic\Cdn;

use Fydellys\InertiaStatic\Contracts\CdnDriver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Enums\PurgeMode;
use Fydellys\InertiaStatic\Exceptions\MissingCredentials;
use Fydellys\InertiaStatic\Exceptions\UnsupportedOperation;
use Fydellys\InertiaStatic\Support\CachePolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

/**
 * Base driver: by default emits RFC 9213 CDN-Cache-Control and supports
 * nothing. Concrete drivers override what their provider offers.
 */
abstract class Driver implements CdnDriver
{
    /** @var array<int, Capability> */
    protected array $capabilities = [];

    /**
     * Config keys needed to PURGE. Cache headers never need them, so a driver
     * without credentials still works in "headers only" mode.
     *
     * @var array<int, string>
     */
    protected array $credentials = [];

    protected PurgeMode $mode = PurgeMode::Hard;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected HttpFactory $http,
        protected array $config = [],
    ) {}

    abstract public function name(): string;

    public function cacheHeaders(CachePolicy $policy): array
    {
        return [
            'Cache-Control' => $policy->browserCacheControl,
            'CDN-Cache-Control' => implode(', ', ['public', ...$policy->edgeDirectives()]),
        ];
    }

    public function tagHeaders(array $tags): array
    {
        return [];
    }

    public function purgeUrls(array $urls): void
    {
        throw UnsupportedOperation::for($this->name(), Capability::PurgeUrls);
    }

    public function purgeTags(array $tags): void
    {
        throw UnsupportedOperation::for($this->name(), Capability::PurgeTags);
    }

    public function purgePrefixes(array $prefixes): void
    {
        throw UnsupportedOperation::for($this->name(), Capability::PurgePrefix);
    }

    public function purgeAll(): void
    {
        throw UnsupportedOperation::for($this->name(), Capability::PurgeAll);
    }

    public function supports(Capability $capability): bool
    {
        if (! in_array($capability, $this->capabilities, true)) {
            return false;
        }

        return $capability === Capability::CacheTags || $this->hasCredentials();
    }

    public function withMode(PurgeMode $mode): static
    {
        $driver = clone $this;
        $driver->mode = $mode;

        return $driver;
    }

    public function canPurge(): bool
    {
        foreach ([Capability::PurgeUrls, Capability::PurgeTags, Capability::PurgePrefix, Capability::PurgeAll] as $capability) {
            if ($this->supports($capability)) {
                return true;
            }
        }

        return false;
    }

    public function hasCredentials(): bool
    {
        return $this->missingCredentials() === [];
    }

    /**
     * @return array<int, string>
     */
    public function missingCredentials(): array
    {
        return array_values(array_filter($this->credentials, fn (string $key) => empty($this->config[$key])));
    }

    /**
     * Fail loudly when purging without the provider credentials.
     */
    protected function ensureCredentials(): void
    {
        if ($missing = $this->missingCredentials()) {
            throw MissingCredentials::for($this->name(), $missing);
        }
    }

    public function batchSize(Capability $capability): int
    {
        return (int) ($this->config['batch_size'] ?? 100);
    }

    protected function request(): PendingRequest
    {
        return $this->http
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5))
            ->retry([200, 1000, 3000], throw: true, when: fn ($exception, $request) => $this->shouldRetry($exception));
    }

    protected function shouldRetry(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    /**
     * The Host a cache keys the URL on: "host" or "host:port" (non-default ports
     * are part of the Host header, and Varnish hashes the full header).
     *
     * @param  array<string, int|string>|false  $parts  parse_url() result
     */
    protected function hostOf(array|false $parts): string
    {
        if ($parts === false) {
            return '';
        }

        return ($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * Join tags in a single header value, truncated to the provider limit.
     *
     * @param  array<int, string>  $tags
     */
    protected function joinTags(array $tags, string $separator, int $maxLength): string
    {
        $value = '';

        foreach (array_unique($tags) as $tag) {
            $candidate = $value === '' ? $tag : $value.$separator.$tag;

            if (strlen($candidate) > $maxLength) {
                break;
            }

            $value = $candidate;
        }

        return $value;
    }
}
