<?php

namespace Fydellys\InertiaStatic;

use Closure;
use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Contracts\CdnDriver;
use Fydellys\InertiaStatic\Enums\PurgeMode;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Jobs\PurgeStaticCache;
use Fydellys\InertiaStatic\Support\Preview;
use Fydellys\InertiaStatic\Support\StaticRequestContext;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Root of the StaticCache facade: on-demand revalidation.
 *
 * Every purge is queued (retry, backoff, rate limit, debounce). Use the
 * "now" variants to purge synchronously (commands, deploy hooks).
 */
class StaticCache
{
    public function __construct(
        protected CdnManager $cdn,
        protected Purger $purger,
        protected Repository $config,
    ) {}

    /**
     * @param  array<int, string>|string  $tags
     */
    public function purgeTags(array|string $tags, ?PurgeMode $mode = null): void
    {
        $this->queue(PurgeOperation::Tags, (array) $tags, $mode);
    }

    /**
     * @param  array<int, string>|string  $urls  Page paths or absolute URLs.
     */
    public function purgeUrls(array|string $urls, ?PurgeMode $mode = null): void
    {
        $this->queue(PurgeOperation::Urls, (array) $urls, $mode);
    }

    /**
     * @param  array<int, string>|string  $prefixes
     */
    public function purgePrefix(array|string $prefixes, ?PurgeMode $mode = null): void
    {
        $this->queue(PurgeOperation::Prefixes, (array) $prefixes, $mode);
    }

    public function purgeAll(?PurgeMode $mode = null): void
    {
        $this->queue(PurgeOperation::All, [], $mode);
    }

    /**
     * Purge synchronously, returning the purged absolute URLs.
     *
     * @param  array<int, string>  $items
     * @return array<int, string>
     */
    public function purgeNow(PurgeOperation $operation, array $items = [], ?string $driver = null, ?PurgeMode $mode = null): array
    {
        return $this->purger->purge($operation, $items, $driver, $mode);
    }

    /**
     * False in "headers only" mode (no API token configured).
     */
    public function canPurge(): bool
    {
        return $this->cdn->driver()->canPurge();
    }

    /**
     * Preview mode cookie. Return it from your own,
     * authenticated route: redirect($url)->withCookie(StaticCache::enablePreview()).
     */
    public function enablePreview(?int $minutes = null): Cookie
    {
        return app(Preview::class)->enable($minutes);
    }

    public function disablePreview(): Cookie
    {
        return app(Preview::class)->disable();
    }

    /**
     * Whether the current request is a preview (use it to show drafts).
     */
    public function isPreview(): bool
    {
        return app(StaticRequestContext::class)->preview;
    }

    public function driver(?string $name = null): CdnDriver
    {
        return $this->cdn->driver($name);
    }

    /**
     * Register a custom CDN driver.
     *
     * @param  Closure(Application, array<string, mixed>): CdnDriver  $callback
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->cdn->extend($driver, $callback);

        return $this;
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function queue(PurgeOperation $operation, array $items = [], ?PurgeMode $mode = null): void
    {
        if (! $this->config->get('inertia-static.enabled', true)) {
            return;
        }

        $items = array_values(array_unique(array_filter($items)));

        if ($operation !== PurgeOperation::All && $items === []) {
            return;
        }

        if (! $this->canPurge()) {
            // "Headers only" mode: the CDN expires pages by TTL; nothing to purge with.
            Log::notice('[inertia-static] Purge skipped: the CDN driver has no purge credentials (headers only mode).', [
                'operation' => $operation->value,
                'items' => $items,
            ]);

            return;
        }

        PurgeStaticCache::dispatch($operation, $items, null, (bool) $this->config->get('inertia-static.purge.warm_after_purge', false), $mode)
            ->delay(now()->addSeconds((int) $this->config->get('inertia-static.purge.debounce', 5)))
            ->afterCommit();
    }
}
