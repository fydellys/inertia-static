<?php

namespace Fydellys\InertiaStatic\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * Requests every URL (HTML + JSON) so the CDN is filled before visitors.
 */
class WarmStaticCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @param  array<int, string>  $urls  Absolute URLs.
     */
    public function __construct(public array $urls, public int $concurrency = 10)
    {
        $this->onConnection(config('inertia-static.purge.connection'));
        $this->onQueue(config('inertia-static.purge.queue'));
    }

    /**
     * @return array<string, int> Status code per URL.
     */
    public function handle(): array
    {
        $statuses = [];

        foreach (array_chunk($this->urls, max(1, $this->concurrency)) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $pool->as($url)->timeout(30)->connectTimeout(5)
                    ->withHeaders(['User-Agent' => 'inertia-static-warmer'] + $this->hostHeader($url))
                    ->get($this->target($url)),
                $chunk,
            ));

            foreach ($responses as $url => $response) {
                $statuses[$url] = $response instanceof Response ? $response->status() : 0;
            }
        }

        return $statuses;
    }

    /**
     * The URL actually requested: the public URL, or the same path on
     * "purge.warm_url" when the app reaches the CDN through another address.
     */
    protected function target(string $url): string
    {
        $warm = rtrim((string) config('inertia-static.purge.warm_url'), '/');

        if ($warm === '') {
            return $url;
        }

        $parts = parse_url($url);

        return $warm.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * Keep the public Host so the request lands on the same cache key.
     *
     * @return array<string, string>
     */
    protected function hostHeader(string $url): array
    {
        if (! config('inertia-static.purge.warm_url')) {
            return [];
        }

        $parts = parse_url($url);

        return isset($parts['host'])
            ? ['Host' => $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')]
            : [];
    }
}
