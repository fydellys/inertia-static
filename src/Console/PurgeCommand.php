<?php

namespace Fydellys\InertiaStatic\Console;

use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Jobs\WarmStaticCache;
use Fydellys\InertiaStatic\StaticCache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'inertia-static:purge')]
class PurgeCommand extends Command
{
    protected $signature = 'inertia-static:purge
        {--tags=* : Cache tags to purge}
        {--url=* : Page paths or absolute URLs to purge}
        {--prefix=* : Path prefixes to purge}
        {--all : Purge everything}
        {--driver= : CDN driver (default: config)}
        {--queue : Dispatch to the queue instead of purging now}
        {--warm : Warm the purged URLs afterwards}';

    protected $description = 'Purge the CDN cache of static pages';

    public function handle(StaticCache $cache): int
    {
        $operations = array_filter([
            PurgeOperation::Tags->value => $this->split($this->option('tags')),
            PurgeOperation::Urls->value => $this->split($this->option('url')),
            PurgeOperation::Prefixes->value => $this->split($this->option('prefix')),
            PurgeOperation::All->value => $this->option('all') ? ['*'] : [],
        ]);

        if ($operations === []) {
            $this->components->error('Nothing to purge: use --tags, --url, --prefix or --all.');

            return self::INVALID;
        }

        $driver = $this->option('driver');
        $cdn = $cache->driver(is_string($driver) && $driver !== '' ? $driver : null);

        if (! $cdn->canPurge()) {
            $this->components->error('The CDN driver runs in "headers only" mode: configure its API credentials to purge.');

            return self::FAILURE;
        }

        foreach ($operations as $operation => $items) {
            $operation = PurgeOperation::from($operation);
            $items = $operation === PurgeOperation::All ? [] : $items;

            if ($this->option('queue')) {
                match ($operation) {
                    PurgeOperation::Tags => $cache->purgeTags($items),
                    PurgeOperation::Urls => $cache->purgeUrls($items),
                    PurgeOperation::Prefixes => $cache->purgePrefix($items),
                    PurgeOperation::All => $cache->purgeAll(),
                };

                $this->components->info("Queued purge of {$operation->value}.");

                continue;
            }

            $this->components->task("Purging {$operation->value} ".implode(', ', $items), function () use ($cache, $operation, $items) {
                $driver = $this->option('driver');
                $urls = $cache->purgeNow($operation, $items, is_string($driver) && $driver !== '' ? $driver : null);

                if ($this->option('warm') && $urls !== []) {
                    WarmStaticCache::dispatchSync($urls);
                }
            });
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function split(mixed $values): array
    {
        return collect((array) $values)
            ->flatMap(fn (mixed $value) => explode(',', (string) $value))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values()
            ->all();
    }
}
