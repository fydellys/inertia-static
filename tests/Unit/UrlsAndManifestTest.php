<?php

use Fydellys\InertiaStatic\Jobs\WarmStaticCache;
use Fydellys\InertiaStatic\Support\Manifest;
use Fydellys\InertiaStatic\Support\PathCollector;
use Fydellys\InertiaStatic\Support\StaticUrls;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

it('builds JSON URLs', function (string $page, string $json) {
    expect(app(StaticUrls::class)->json($page, 'v1'))->toBe($json);
})->with([
    ['/', '/__inertia/v1/index.json'],
    ['/blog', '/__inertia/v1/blog.json'],
    ['/blog/a/', '/__inertia/v1/blog/a.json'],
    ['/blog?page=2', '/__inertia/v1/blog.json?page=2'],
    ['https://example.com/docs/x', '/__inertia/v1/docs/x.json'],
]);

it('maps JSON paths back to pages', function () {
    $urls = app(StaticUrls::class);

    expect($urls->pagePathFromJson('index.json'))->toBe('/')
        ->and($urls->pagePathFromJson('blog/a.json'))->toBe('/blog/a')
        ->and($urls->pagePathFromJson('blog/a'))->toBeNull();
});

it('compiles route patterns to JavaScript regexes', function () {
    $route = Route::get('/docs/{section}/{page?}', fn () => 'x')->where('section', '[a-z]+');

    expect(app(Manifest::class)->pattern($route))->toBe('^/docs/([a-z]+)(?:/([^/]+))?/?$');
});

it('expands static paths with the providers', function () {
    expect(app(PathCollector::class)->collect())
        ->toContain('/', '/blog', '/blog/hello-world', '/blog/second-post', '/attribute')
        ->not->toContain('/dashboard');

    expect(app(PathCollector::class)->collect(['blog.show'], 1))->toBe(['/blog/hello-world']);
});

it('lists warmup URLs in dry-run mode', function () {
    $this->artisan('inertia-static:warm', ['--only' => ['blog.show'], '--dry-run' => true])
        ->expectsOutput('https://example.com/blog/hello-world')
        ->expectsOutput('https://example.com/__inertia/v1/blog/hello-world.json')
        ->assertSuccessful();
});

it('warms URLs', function () {
    Http::fake(['example.com/*' => Http::response('ok')]);

    $this->artisan('inertia-static:warm', ['--only' => ['home']])->assertSuccessful();

    Http::assertSentCount(2);
});

it('runs the doctor', function () {
    $this->artisan('inertia-static:doctor')
        ->expectsOutputToContain('Global middleware registered')
        ->assertSuccessful();
});

it('warms through an internal CDN address keeping the public host', function () {
    config(['inertia-static.purge.warm_url' => 'http://cdn:8088']);
    Http::fake(['cdn:8088/*' => Http::response('ok')]);

    (new WarmStaticCache(['https://example.com/blog?page=2']))->handle();

    Http::assertSent(fn ($r) => $r->url() === 'http://cdn:8088/blog?page=2' && $r->hasHeader('Host', 'example.com'));
});
