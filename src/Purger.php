<?php

namespace Fydellys\InertiaStatic;

use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Contracts\CdnDriver;
use Fydellys\InertiaStatic\Contracts\TagRegistry;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Enums\PurgeMode;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Events\StaticCachePurged;
use Fydellys\InertiaStatic\Exceptions\UnsupportedOperation;
use Fydellys\InertiaStatic\Support\ImplicitTags;
use Fydellys\InertiaStatic\Support\StaticUrls;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Executes purges against the CDN driver: batching by the provider limit,
 * expanding pages to their HTML + JSON URLs and falling back to the tag
 * registry when the provider lacks tag / prefix purging.
 */
class Purger
{
    public function __construct(
        protected CdnManager $cdn,
        protected TagRegistry $registry,
        protected StaticUrls $urls,
        protected Dispatcher $events,
        protected Repository $config,
    ) {}

    /**
     * @param  array<int, string>  $items
     * @return array<int, string> The absolute URLs that were purged (for warmup).
     */
    public function purge(PurgeOperation $operation, array $items = [], ?string $driver = null, ?PurgeMode $mode = null): array
    {
        $cdn = $this->cdn->driver($driver)->withMode($mode ?? $this->defaultMode());
        $items = array_values(array_unique(array_filter($items)));

        $purged = match ($operation) {
            PurgeOperation::Urls => $this->purgeUrls($cdn, $items),
            PurgeOperation::Tags => $this->purgeTags($cdn, $items),
            PurgeOperation::Prefixes => $this->purgePrefixes($cdn, $items),
            PurgeOperation::All => $this->purgeAll($cdn),
        };

        $this->events->dispatch(new StaticCachePurged($operation->value, $items, $driver ?? $this->cdn->getDefaultDriver()));

        return $purged;
    }

    /**
     * @param  array<int, string>  $pages  Paths or absolute URLs of pages.
     * @return array<int, string>
     */
    protected function purgeUrls(CdnDriver $cdn, array $pages): array
    {
        $urls = $this->urls->variants($pages);

        $this->inBatches($cdn, Capability::PurgeUrls, $urls, fn (array $batch) => $cdn->purgeUrls($batch));

        return $urls;
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    protected function purgeTags(CdnDriver $cdn, array $tags): array
    {
        $pages = $this->registry->urlsFor($tags);

        if ($cdn->supports(Capability::PurgeTags)) {
            $this->inBatches($cdn, Capability::PurgeTags, $tags, fn (array $batch) => $cdn->purgeTags($batch));
            $purged = $this->urls->variants($pages);
        } elseif ($pages !== []) {
            $purged = $this->purgeUrls($cdn, $pages);
        } else {
            Log::warning('[inertia-static] Tag purge skipped: the CDN driver has no tag purge and the tag registry knows no URL for the tags.', ['tags' => $tags]);
            $purged = [];
        }

        $this->registry->forget($tags);

        return $purged;
    }

    /**
     * @param  array<int, string>  $prefixes
     * @return array<int, string>
     */
    protected function purgePrefixes(CdnDriver $cdn, array $prefixes): array
    {
        $relative = array_map(fn (string $prefix) => $this->urls->relative($prefix), $prefixes);

        if ($cdn->supports(Capability::PurgePrefix)) {
            $targets = collect($relative)
                ->flatMap(fn (string $prefix) => [
                    $this->urls->absolute($prefix),
                    $this->urls->absolute($this->urls->jsonPrefix($prefix)),
                ])
                ->unique()->values()->all();

            $this->inBatches($cdn, Capability::PurgePrefix, $targets, fn (array $batch) => $cdn->purgePrefixes($batch));

            return $this->urls->variants($this->registry->urlsWithPrefix($relative));
        }

        // Implicit path tags (_IST_/blog/) turn a prefix purge into a tag purge.
        if ($cdn->supports(Capability::PurgeTags) && $this->config->get('inertia-static.implicit_tags', true)) {
            $tags = array_values(array_unique(array_map(fn (string $prefix) => ImplicitTags::forPrefix($prefix), $relative)));
            $this->inBatches($cdn, Capability::PurgeTags, $tags, fn (array $batch) => $cdn->purgeTags($batch));

            return $this->urls->variants($this->registry->urlsWithPrefix($relative));
        }

        $pages = $this->registry->urlsWithPrefix($relative);

        if ($pages !== []) {
            return $this->purgeUrls($cdn, $pages);
        }

        if ($cdn->supports(Capability::PurgeAll)) {
            // Fail-safe: when we cannot know which URLs are under the prefix,
            // stale content is worse than a cold cache.
            Log::warning('[inertia-static] Prefix purge escalated to purge all: no prefix purge and no registry entries.', ['prefixes' => $prefixes]);

            return $this->purgeAll($cdn);
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function purgeAll(CdnDriver $cdn): array
    {
        if ($cdn->supports(Capability::PurgeAll)) {
            $cdn->purgeAll();
        } elseif ($cdn->supports(Capability::PurgePrefix)) {
            // e.g. nginx: "everything" is the "/" prefix of the site.
            $cdn->purgePrefixes([$this->urls->absolute('/')]);
        } else {
            throw UnsupportedOperation::for($this->cdn->getDefaultDriver(), Capability::PurgeAll);
        }

        $this->registry->flush();

        return [];
    }

    protected function defaultMode(): PurgeMode
    {
        return PurgeMode::tryFrom((string) $this->config->get('inertia-static.purge.mode', 'soft')) ?? PurgeMode::Soft;
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function inBatches(CdnDriver $cdn, Capability $capability, array $items, \Closure $callback): void
    {
        foreach (array_chunk($items, max(1, $cdn->batchSize($capability))) as $batch) {
            $callback($batch);
        }
    }
}
