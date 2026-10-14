<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Contracts\CdnDriver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Enums\PurgeMode;
use Fydellys\InertiaStatic\Support\CachePolicy;

/**
 * Chains several drivers (e.g. Varnish in front of the origin + Cloudflare).
 * Headers are merged (first driver wins on conflicts); purges fan out to
 * every driver that supports the operation.
 */
class MultiDriver extends Driver
{
    /**
     * @param  array<int, CdnDriver>  $drivers
     */
    public function __construct(protected array $drivers)
    {
        // The multi driver performs no HTTP calls of its own.
    }

    public function name(): string
    {
        return 'multi';
    }

    public function cacheHeaders(CachePolicy $policy): array
    {
        return array_reduce(
            $this->drivers,
            fn (array $headers, CdnDriver $driver) => $headers + $driver->cacheHeaders($policy),
            [],
        );
    }

    public function tagHeaders(array $tags): array
    {
        return array_reduce(
            $this->drivers,
            fn (array $headers, CdnDriver $driver) => $headers + $driver->tagHeaders($tags),
            [],
        );
    }

    public function purgeUrls(array $urls): void
    {
        $this->fanOut(Capability::PurgeUrls, fn (CdnDriver $driver) => $driver->purgeUrls($urls));
    }

    public function purgeTags(array $tags): void
    {
        $this->fanOut(Capability::PurgeTags, fn (CdnDriver $driver) => $driver->purgeTags($tags));
    }

    public function purgePrefixes(array $prefixes): void
    {
        $this->fanOut(Capability::PurgePrefix, fn (CdnDriver $driver) => $driver->purgePrefixes($prefixes));
    }

    public function purgeAll(): void
    {
        $this->fanOut(Capability::PurgeAll, fn (CdnDriver $driver) => $driver->purgeAll());
    }

    /**
     * Supported only when every chained driver supports it, so the purger
     * falls back to URL purges (via the registry) for the others.
     */
    public function supports(Capability $capability): bool
    {
        foreach ($this->drivers as $driver) {
            if (! $driver->supports($capability)) {
                return false;
            }
        }

        return $this->drivers !== [];
    }

    public function withMode(PurgeMode $mode): static
    {
        $multi = clone $this;
        $multi->drivers = array_map(fn (CdnDriver $driver) => $driver->withMode($mode), $this->drivers);

        return $multi;
    }

    public function canPurge(): bool
    {
        foreach ($this->drivers as $driver) {
            if ($driver->canPurge()) {
                return true;
            }
        }

        return false;
    }

    public function batchSize(Capability $capability): int
    {
        return min(array_map(fn (CdnDriver $driver) => $driver->batchSize($capability), $this->drivers) ?: [100]);
    }

    /**
     * @return array<int, CdnDriver>
     */
    public function drivers(): array
    {
        return $this->drivers;
    }

    protected function fanOut(Capability $capability, \Closure $callback): void
    {
        foreach ($this->drivers as $driver) {
            if ($driver->supports($capability)) {
                $callback($driver);
            }
        }
    }
}
