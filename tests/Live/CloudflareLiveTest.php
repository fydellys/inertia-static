<?php

/**
 * Live tests against the real Cloudflare API. Skipped unless:
 *
 *   INERTIA_STATIC_LIVE=1 CLOUDFLARE_API_TOKEN=... CLOUDFLARE_ZONE_ID=... vendor/bin/pest tests/Live
 *
 * They only purge URLs / tags / prefixes that do not exist on the zone and NEVER
 * purge everything, so they are safe to run against a production zone. Keep in
 * mind the purge rate limits of the plan (Free: 5 tag/prefix requests a minute).
 */

use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! getenv('INERTIA_STATIC_LIVE') || ! getenv('CLOUDFLARE_API_TOKEN') || ! getenv('CLOUDFLARE_ZONE_ID')) {
        $this->markTestSkipped('Live Cloudflare tests disabled (set INERTIA_STATIC_LIVE=1 and the credentials).');
    }

    $zone = Http::withToken(getenv('CLOUDFLARE_API_TOKEN'))
        ->get('https://api.cloudflare.com/client/v4/zones/'.getenv('CLOUDFLARE_ZONE_ID'))
        ->throw()
        ->json('result.name');

    $this->host = (string) $zone;
    $this->marker = 'inertia-static-live-'.Str::lower(Str::random(10));

    config([
        'inertia-static.cdn' => 'cloudflare',
        'inertia-static.base_url' => "https://{$this->host}",
        'inertia-static.drivers.cloudflare' => [
            'driver' => 'cloudflare',
            'zone_id' => getenv('CLOUDFLARE_ZONE_ID'),
            'api_token' => getenv('CLOUDFLARE_API_TOKEN'),
            'batch_size' => 100,
        ],
    ]);
});

it('has purge credentials (not headers only)', function () {
    expect(app(CdnManager::class)->driver()->canPurge())->toBeTrue()
        ->and(StaticCache::canPurge())->toBeTrue();
});

it('purges URLs (HTML + JSON variants) on the real API', function () {
    Event::fake();

    $urls = StaticCache::purgeNow(PurgeOperation::Urls, ["/{$this->marker}/page"]);

    expect($urls)->toHaveCount(2)
        ->and($urls[0])->toBe("https://{$this->host}/{$this->marker}/page")
        ->and($urls[1])->toContain('/__inertia/');
});

it('purges more URLs than one request accepts, in batches', function () {
    $calls = 0;
    Http::globalRequestMiddleware(function ($request) use (&$calls) {
        if (str_contains((string) $request->getUri(), '/purge_cache')) {
            $calls++;
        }

        return $request;
    });

    // 51 pages => 102 URLs (HTML + JSON) => 2 requests of at most 100 files.
    $pages = array_map(fn ($i) => "/{$this->marker}/batch-{$i}", range(1, 51));
    $urls = StaticCache::purgeNow(PurgeOperation::Urls, $pages);

    expect($urls)->toHaveCount(102)->and($calls)->toBe(2);
});

it('purges cache tags on the real API', function () {
    StaticCache::purgeNow(PurgeOperation::Tags, [$this->marker, "{$this->marker}:1"]);
})->throwsNoExceptions();

it('purges a prefix (HTML + JSON prefix) on the real API', function () {
    StaticCache::purgeNow(PurgeOperation::Prefixes, ["/{$this->marker}"]);
})->throwsNoExceptions();
