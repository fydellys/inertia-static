<?php

use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Exceptions\MissingCredentials;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Fydellys\InertiaStatic\Support\CachePolicy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['cloudfront.amazonaws.com/*' => Http::response('<Invalidation/>', 201)]);

    config(['inertia-static.drivers.cloudfront' => [
        'driver' => 'cloudfront', 'distribution_id' => 'E123', 'key' => 'AKID', 'secret' => 'secret',
    ]]);
});

function invalidatedPaths(Request $request): array
{
    preg_match_all('#<Path>(.*?)</Path>#', $request->body(), $matches);

    return array_map(fn (string $path) => html_entity_decode($path, ENT_XML1), $matches[1]);
}

it('invalidates the HTML and JSON paths of purged pages', function () {
    config(['inertia-static.cdn' => 'cloudfront']);

    StaticCache::purgeNow(PurgeOperation::Urls, ['/blog/a?page=2&sort=new']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://cloudfront.amazonaws.com/2020-05-31/distribution/E123/invalidation'
        && $request->method() === 'POST'
        && str_starts_with($request->header('Authorization')[0], 'AWS4-HMAC-SHA256 Credential=AKID/')
        && str_contains($request->header('Authorization')[0], '/us-east-1/cloudfront/aws4_request')
        && $request->hasHeader('X-Amz-Date')
        && str_contains($request->body(), '<CallerReference>inertia-static-')
        && invalidatedPaths($request) === ['/blog/a?page=2&sort=new', '/__inertia/v1/blog/a.json?page=2&sort=new']);
});

it('invalidates prefixes with a wildcard and everything with /*', function () {
    $cdn = app(CdnManager::class)->driver('cloudfront');

    $cdn->purgePrefixes(['https://example.com/blog']);
    $cdn->purgeAll();

    expect(Http::recorded()->map(fn ($pair) => invalidatedPaths($pair[0]))->all())->toBe([['/blog*'], ['/*']]);
});

it('purges tags through the registry (CloudFront has no tags)', function () {
    expect(app(CdnManager::class)->driver('cloudfront')->tagHeaders(['posts']))->toBe([]);
});

it('sends stale-while-revalidate only when a response headers policy protects browsers', function () {
    config(['inertia-static.drivers.cloudfront.stale' => true]);

    $headers = app(CdnManager::class)->driver('cloudfront')->cacheHeaders(new CachePolicy(cacheable: true, revalidate: 60, staleWhileRevalidate: 30));

    expect($headers['Cache-Control'])->toBe('public, max-age=0, s-maxage=60, stale-while-revalidate=30');
});

it('needs credentials to purge, not to cache', function () {
    config(['inertia-static.drivers.cloudfront' => ['driver' => 'cloudfront']]);
    $cdn = app(CdnManager::class)->driver('cloudfront');

    expect($cdn->canPurge())->toBeFalse()
        ->and($cdn->cacheHeaders(new CachePolicy(cacheable: true, revalidate: 60)))->not->toBeEmpty();

    $cdn->purgeAll();
})->throws(MissingCredentials::class);
