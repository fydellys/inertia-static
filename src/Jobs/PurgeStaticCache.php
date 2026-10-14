<?php

namespace Fydellys\InertiaStatic\Jobs;

use DateTimeInterface;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Purger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued purge. Unique per (operation, items) during the debounce window,
 * so a burst of identical purges (e.g. several model saves) runs once.
 */
class PurgeStaticCache implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const RATE_LIMITER = 'inertia-static-purge';

    /** Attempts are bounded by retryUntil(); exceptions by $maxExceptions. */
    public int $tries = 0;

    public int $maxExceptions;

    public int $uniqueFor;

    /**
     * @param  array<int, string>  $items
     */
    public function __construct(
        public PurgeOperation $operation,
        public array $items = [],
        public ?string $driver = null,
        public bool $warm = false,
    ) {
        sort($this->items);

        $this->maxExceptions = (int) config('inertia-static.purge.tries', 5);
        $this->uniqueFor = max(1, (int) config('inertia-static.purge.debounce', 5));

        $this->onConnection(config('inertia-static.purge.connection'));
        $this->onQueue(config('inertia-static.purge.queue'));
    }

    public function handle(Purger $purger): void
    {
        $urls = $purger->purge($this->operation, $this->items, $this->driver);

        if ($this->warm && $urls !== []) {
            WarmStaticCache::dispatch($urls);
        }
    }

    public function uniqueId(): string
    {
        return hash('xxh128', $this->operation->value.'|'.($this->driver ?? '').'|'.implode("\n", $this->items));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited(self::RATE_LIMITER)];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return array_map('intval', (array) config('inertia-static.purge.backoff', [5, 30, 120]));
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('[inertia-static] CDN purge failed; cached pages may be stale.', [
            'operation' => $this->operation->value,
            'items' => $this->items,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
