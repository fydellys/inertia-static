<?php

use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Enums\PurgeMode;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'inertia-static.drivers.cloudflare' => ['driver' => 'cloudflare', 'zone_id' => 'zone', 'api_token' => 'token', 'batch_size' => 2],
        'inertia-static.drivers.fastly' => ['driver' => 'fastly', 'service_id' => 'svc', 'api_token' => 'token'],
        'inertia-static.drivers.bunny' => ['driver' => 'bunny', 'pull_zone_id' => '7', 'api_key' => 'key'],
        'inertia-static.drivers.varnish' => ['driver' => 'varnish', 'hosts' => ['http://varnish:6081']],
        'inertia-static.drivers.nginx' => ['driver' => 'nginx', 'hosts' => ['http://nginx'], 'purge_path' => '/purge'],
    ]);
});

it('sends Cloudflare purge payloads in batches', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
    config(['inertia-static.cdn' => 'cloudflare']);

    StaticCache::purgeNow(PurgeOperation::Urls, ['/blog/a', '/blog/b']);

    // 2 pages x (HTML + JSON) = 4 URLs, batch size 2 => 2 requests.
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone/purge_cache'
        && $request->hasHeader('Authorization', 'Bearer token')
        && $request['files'] === ['https://example.com/blog/a', 'https://example.com/__inertia/v1/blog/a.json']);
});

it('sends Cloudflare tag, prefix and everything purges', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
    $cdn = app(CdnManager::class)->driver('cloudflare');

    $cdn->purgeTags(['posts']);
    $cdn->purgePrefixes(['https://example.com/blog']);
    $cdn->purgeAll();

    $bodies = Http::recorded()->map(fn ($pair) => $pair[0]->data())->all();

    expect($bodies)->toBe([
        ['tags' => ['posts']],
        ['prefixes' => ['example.com/blog']],
        ['hosts' => ['example.com']], // the app's host, never the whole zone by default
    ]);
});

it('purges the whole Cloudflare zone only when opted in', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
    config(['inertia-static.drivers.cloudflare.purge_everything' => true]);

    app(CdnManager::class)->driver('cloudflare')->purgeAll();

    Http::assertSent(fn (Request $request) => $request->data() === ['purge_everything' => true]);
});

it('purges the HTML and JSON prefixes', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
    config(['inertia-static.cdn' => 'cloudflare', 'inertia-static.drivers.cloudflare.batch_size' => 100]);

    StaticCache::purgeNow(PurgeOperation::Prefixes, ['/blog']);

    Http::assertSent(fn (Request $request) => $request['prefixes'] === ['example.com/blog', 'example.com/__inertia/v1/blog']);
});

it('retries on 429 / 5xx and then fails', function () {
    Http::fakeSequence('api.cloudflare.com/*')
        ->push(['success' => false], 429)
        ->push(['success' => false], 500)
        ->push(['success' => true], 200);

    app(CdnManager::class)->driver('cloudflare')->purgeTags(['x']);

    Http::assertSentCount(3);
});

it('does not retry client errors', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false], 403)]);

    try {
        app(CdnManager::class)->driver('cloudflare')->purgeTags(['x']);
    } catch (RequestException) {
    }

    Http::assertSentCount(1);
});

it('sends Fastly surrogate key purges', function () {
    Http::fake(['api.fastly.com/*' => Http::response(['status' => 'ok'])]);
    $cdn = app(CdnManager::class)->driver('fastly')->withMode(PurgeMode::Soft);

    $cdn->purgeTags(['posts', 'post:1']);
    $cdn->purgeUrls(['https://example.com/blog']);
    $cdn->purgeAll();

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.fastly.com/service/svc/purge'
        && $r->hasHeader('Surrogate-Key', 'posts post:1')
        && $r->hasHeader('Fastly-Soft-Purge', '1')
        && $r->hasHeader('Fastly-Key', 'token'));
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.fastly.com/purge/example.com/blog');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.fastly.com/service/svc/purge_all');
});

it('sends Bunny URL, wildcard prefix and zone purges', function () {
    Http::fake(['api.bunny.net/*' => Http::response(null, 200)]);
    $cdn = app(CdnManager::class)->driver('bunny');

    $cdn->purgeUrls(['https://example.com/blog']);
    $cdn->purgePrefixes(['https://example.com/blog/']);
    $cdn->purgeAll();

    Http::assertSent(fn (Request $r) => $r->hasHeader('AccessKey', 'key') && str_contains($r->url(), 'url='.urlencode('https://example.com/blog').'&'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), urlencode('https://example.com/blog/*')));
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.bunny.net/pullzone/7/purgeCache');
});

it('sends Varnish PURGE / xkey / BAN requests', function () {
    Http::fake(['varnish:6081/*' => Http::response('', 200)]);
    $cdn = app(CdnManager::class)->driver('varnish');

    $cdn->purgeUrls(['https://example.com/blog?page=2']);
    $cdn->purgeTags(['posts', 'post:1']);
    $cdn->purgePrefixes(['https://example.com/blog']);

    Http::assertSent(fn (Request $r) => $r->method() === 'PURGE' && $r->url() === 'http://varnish:6081/blog?page=2' && $r->hasHeader('Host', 'example.com'));
    Http::assertSent(fn (Request $r) => $r->method() === 'PURGE' && $r->hasHeader('xkey-purge', 'posts post:1'));
    Http::assertSent(fn (Request $r) => $r->method() === 'BAN' && $r->hasHeader('X-Ban-Prefix', '/blog'));
});

it('sends nginx cache purge requests', function () {
    Http::fake(['nginx/*' => Http::response('', 200)]);

    app(CdnManager::class)->driver('nginx')->purgeUrls(['https://example.com/blog']);

    Http::assertSent(fn (Request $r) => $r->url() === 'http://nginx/purge/blog' && $r->hasHeader('Host', 'example.com'));
});

it('treats "not cached" answers of ngx_cache_purge as success', function () {
    Http::fake(['nginx/purge/missing' => Http::response('', 412), 'nginx/purge/gone' => Http::response('', 404), 'nginx/*' => Http::response('', 200)]);

    app(CdnManager::class)->driver('nginx')->purgeUrls(['https://example.com/missing', 'https://example.com/gone', 'https://example.com/ok']);

    Http::assertSentCount(3);
});

it('still fails on real nginx purge errors', function () {
    Http::fake(['nginx/*' => Http::response('', 403)]);

    app(CdnManager::class)->driver('nginx')->purgeUrls(['https://example.com/x']);
})->throws(RequestException::class);

it('fails when Cloudflare answers success false', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['code' => 1134, 'message' => 'rate limited']]], 200)]);

    app(CdnManager::class)->driver('cloudflare')->purgeTags(['x']);
})->throws(RuntimeException::class, '1134');

it('keeps non-default ports in the Host of Varnish purges and bans', function () {
    Http::fake(['varnish:6081/*' => Http::response('', 200)]);
    $cdn = app(CdnManager::class)->driver('varnish');

    $cdn->purgeUrls(['http://example.com:8080/blog']);
    $cdn->purgePrefixes(['http://example.com:8080/blog']);

    Http::assertSent(fn (Request $r) => $r->method() === 'PURGE' && $r->hasHeader('Host', 'example.com:8080'));
    Http::assertSent(fn (Request $r) => $r->method() === 'BAN' && $r->hasHeader('X-Ban-Host', 'example.com:8080'));
});

it('purges hard or soft depending on the mode', function () {
    Http::fake(['api.fastly.com/*' => Http::response(['status' => 'ok']), 'varnish:6081/*' => Http::response('', 200)]);

    app(CdnManager::class)->driver('fastly')->withMode(PurgeMode::Hard)->purgeTags(['a']);
    app(CdnManager::class)->driver('varnish')->withMode(PurgeMode::Soft)->purgeTags(['b']);
    app(CdnManager::class)->driver('varnish')->withMode(PurgeMode::Hard)->purgeTags(['c']);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'fastly') && ! $r->hasHeader('Fastly-Soft-Purge'));
    Http::assertSent(fn (Request $r) => $r->hasHeader('xkey-softpurge', 'b'));
    Http::assertSent(fn (Request $r) => $r->hasHeader('xkey-purge', 'c'));
});

it('uses the configured purge mode by default (soft)', function () {
    Http::fake(['api.fastly.com/*' => Http::response(['status' => 'ok'])]);
    config(['inertia-static.cdn' => 'fastly']);

    StaticCache::purgeNow(PurgeOperation::Tags, ['a']);
    StaticCache::purgeNow(PurgeOperation::Tags, ['b'], mode: PurgeMode::Hard);

    Http::assertSent(fn (Request $r) => $r->hasHeader('Surrogate-Key', 'a') && $r->hasHeader('Fastly-Soft-Purge', '1'));
    Http::assertSent(fn (Request $r) => $r->hasHeader('Surrogate-Key', 'b') && ! $r->hasHeader('Fastly-Soft-Purge'));
});
