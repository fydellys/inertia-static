<?php

use Fydellys\InertiaStatic\Contracts\TagRegistry;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Events\StaticCachePurged;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['nginx/*' => Http::response('', 200)]);

    config([
        'inertia-static.cdn' => 'nginx',
        'inertia-static.drivers.nginx' => ['driver' => 'nginx', 'hosts' => ['http://nginx'], 'purge_path' => '/purge'],
        'inertia-static.registry.driver' => 'cache',
    ]);
});

it('expands tags to HTML + JSON URLs through the registry when the CDN has no tag purge', function () {
    app(TagRegistry::class)->record(['post:1'], ['/blog/a']);

    $urls = StaticCache::purgeNow(PurgeOperation::Tags, ['post:1']);

    expect($urls)->toBe(['https://example.com/blog/a', 'https://example.com/__inertia/v1/blog/a.json']);
    Http::assertSent(fn ($r) => $r->url() === 'http://nginx/purge/blog/a');
    Http::assertSent(fn ($r) => $r->url() === 'http://nginx/purge/__inertia/v1/blog/a.json');

    expect(app(TagRegistry::class)->urlsFor(['post:1']))->toBe([]);
});

it('records tags of cached responses in the registry', function () {
    $this->get('/blog/hello-world')->assertOk();
    $this->get('/__inertia/v1/blog/second-post.json')->assertOk();

    expect(app(TagRegistry::class)->urlsFor(['posts']))->toBe(['/blog/hello-world', '/blog/second-post'])
        ->and(app(TagRegistry::class)->urlsFor(['post:hello-world']))->toBe(['/blog/hello-world']);
});

it('purges a prefix through implicit path tags on CDNs without prefix purges (Fastly)', function () {
    config(['inertia-static.cdn' => 'fastly', 'inertia-static.drivers.fastly' => ['driver' => 'fastly', 'service_id' => 's', 'api_token' => 't']]);
    Http::fake(['api.fastly.com/*' => Http::response(['status' => 'ok'])]);

    StaticCache::purgeNow(PurgeOperation::Prefixes, ['/blog']);

    Http::assertSent(fn ($r) => $r->hasHeader('Surrogate-Key', '_IST_/blog/'));
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/purge_all'));
});

it('escalates a prefix purge to purge all when nothing is known (fail-safe)', function () {
    config(['inertia-static.cdn' => 'fastly', 'inertia-static.implicit_tags' => false, 'inertia-static.drivers.fastly' => ['driver' => 'fastly', 'service_id' => 's', 'api_token' => 't']]);
    Http::fake(['api.fastly.com/*' => Http::response(['status' => 'ok'])]);

    StaticCache::purgeNow(PurgeOperation::Prefixes, ['/blog']);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/purge_all'));
});

it('dispatches an event after purging', function () {
    Event::fake([StaticCachePurged::class]);

    StaticCache::purgeNow(PurgeOperation::Urls, ['/']);

    Event::assertDispatched(StaticCachePurged::class, fn ($e) => $e->operation === 'urls' && $e->driver === 'nginx');
});

it('stores the registry in the database', function () {
    config(['inertia-static.registry.driver' => 'database']);
    $migration = include __DIR__.'/../../database/migrations/create_inertia_static_tags_table.php.stub';
    $migration->up();

    $registry = app(TagRegistry::class);
    $registry->record(['posts', 'post:1'], ['/blog/a']);
    $registry->record(['posts'], ['/blog/a', '/blog/b', '/about']);

    expect($registry->urlsFor(['posts']))->toBe(['/about', '/blog/a', '/blog/b'])
        ->and($registry->urlsWithPrefix(['/blog']))->toBe(['/blog/a', '/blog/b']);

    $registry->forget(['posts']);
    expect($registry->urlsFor(['posts']))->toBe([])
        ->and($registry->urlsFor(['post:1']))->toBe(['/blog/a']);

    $registry->flush();
    expect(Schema::hasTable('inertia_static_tags'))->toBeTrue()
        ->and($registry->urlsFor(['post:1']))->toBe([]);
});

it('turns purge all into a "/" prefix purge when the driver has no purge all', function () {
    StaticCache::purgeNow(PurgeOperation::All);

    Http::assertSent(fn ($r) => $r->url() === 'http://nginx/purge/*');
});

it('never records implicit path tags in the registry', function () {
    $this->get('/blog/hello-world')->assertOk();

    expect(app(TagRegistry::class)->urlsFor(['_IST_/blog/']))->toBe([])
        ->and(app(TagRegistry::class)->urlsFor(['posts']))->toBe(['/blog/hello-world']);
});
