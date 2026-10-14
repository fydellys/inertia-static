<?php

use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Cdn\Drivers\NullDriver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Exceptions\MissingCredentials;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Fydellys\InertiaStatic\Support\CachePolicy;
use Illuminate\Http\Client\Factory;

beforeEach(function () {
    config([
        'inertia-static.drivers.cloudflare' => ['driver' => 'cloudflare', 'zone_id' => 'zone', 'api_token' => 'token'],
        'inertia-static.drivers.fastly' => ['driver' => 'fastly', 'service_id' => 'svc', 'api_token' => 'token'],
        'inertia-static.drivers.bunny' => ['driver' => 'bunny', 'pull_zone_id' => '1', 'api_key' => 'key'],
    ]);

    $this->policy = new CachePolicy(cacheable: true, revalidate: 3600, staleWhileRevalidate: 60, staleIfError: 600, tags: ['posts', 'post:1']);
});

it('builds the headers of each driver', function (string $driver, array $expected) {
    $cdn = app(CdnManager::class)->driver($driver);

    expect([...$cdn->cacheHeaders($this->policy), ...$cdn->tagHeaders($this->policy->tags)])->toBe($expected);
})->with([
    'null' => ['null', [
        'Cache-Control' => 'public, max-age=0, must-revalidate',
        'CDN-Cache-Control' => 'public, max-age=3600, stale-while-revalidate=60, stale-if-error=600',
    ]],
    'cloudflare' => ['cloudflare', [
        'Cache-Control' => 'public, max-age=0, must-revalidate',
        'Cloudflare-CDN-Cache-Control' => 'public, max-age=3600, stale-while-revalidate=60, stale-if-error=600',
        'Cache-Tag' => 'posts,post:1',
    ]],
    'fastly' => ['fastly', [
        'Cache-Control' => 'public, max-age=0, must-revalidate',
        'Surrogate-Control' => 'max-age=3600, stale-while-revalidate=60, stale-if-error=600',
        'Surrogate-Key' => 'posts post:1',
    ]],
    'bunny' => ['bunny', [
        'Cache-Control' => 'public, max-age=0, must-revalidate',
        'CDN-Cache-Control' => 'public, max-age=3600, stale-while-revalidate=60, stale-if-error=600',
    ]],
    'varnish' => ['varnish', [
        'Cache-Control' => 'public, max-age=0, must-revalidate',
        'Surrogate-Control' => 'max-age=3600, stale-while-revalidate=60, stale-if-error=600',
        'xkey' => 'posts post:1',
    ]],
    'cloudfront' => ['cloudfront', [
        // No stale-while-revalidate: in Cache-Control it would reach browsers too.
        'Cache-Control' => 'public, max-age=0, s-maxage=3600',
    ]],
    'nginx' => ['nginx', [
        'Cache-Control' => 'public, max-age=0, must-revalidate',
        'X-Accel-Expires' => '3600',
    ]],
]);

it('merges headers of chained drivers', function () {
    config(['inertia-static.drivers.multi' => ['driver' => 'multi', 'drivers' => ['varnish', 'cloudflare']]]);

    $headers = app(CdnManager::class)->driver('multi')->tagHeaders(['a']);

    expect($headers)->toBe(['xkey' => 'a', 'Cache-Tag' => 'a']);
});

it('truncates tag headers to the provider limit', function () {
    $tags = array_map(fn ($i) => str_repeat('t', 1000).$i, range(1, 40));

    $header = app(CdnManager::class)->driver('cloudflare')->tagHeaders($tags)['Cache-Tag'];

    expect(strlen($header))->toBeLessThanOrEqual(16 * 1024);
});

it('rejects unknown drivers', function () {
    app(CdnManager::class)->driver('nope');
})->throws(InvalidArgumentException::class, 'not supported');

it('supports custom drivers through the facade', function () {
    StaticCache::extend('custom', fn ($app, $config) => new NullDriver($app->make(Factory::class), $config));

    expect(StaticCache::driver('custom'))->toBeInstanceOf(NullDriver::class);
});

it('works in "headers only" mode without credentials', function () {
    config(['inertia-static.drivers.cloudflare' => ['driver' => 'cloudflare']]);

    $cdn = app(CdnManager::class)->driver('cloudflare');

    expect($cdn->cacheHeaders($this->policy))->toHaveKey('Cloudflare-CDN-Cache-Control')
        ->and($cdn->tagHeaders(['a']))->toBe(['Cache-Tag' => 'a'])
        ->and($cdn->canPurge())->toBeFalse()
        ->and($cdn->supports(Capability::PurgeTags))->toBeFalse();
});

it('fails loudly when purging without credentials', function () {
    config(['inertia-static.drivers.fastly' => ['driver' => 'fastly']]);

    app(CdnManager::class)->driver('fastly')->purgeTags(['x']);
})->throws(MissingCredentials::class, 'api_token');
