<?php

use Fydellys\InertiaStatic\Exceptions\InvalidStaticRoute;
use Fydellys\InertiaStatic\Tests\Fixtures\FakeSsrGateway;

beforeEach(fn () => FakeSsrGateway::$available = true);

it('emits browser and CDN cache headers for ISR pages', function () {
    $response = $this->get('/blog');

    $response->assertOk()
        ->assertHeader('X-Inertia-Static', 'MISS; ttl=300')
        ->assertHeader('CDN-Cache-Control', 'public, max-age=300, stale-while-revalidate=86400, stale-if-error=604800');

    expect($response->headers->get('Cache-Control'))->toContain('max-age=0')->toContain('must-revalidate')->toContain('public');
});

it('applies response-level overrides and tags', function () {
    config(['inertia-static.cdn' => 'cloudflare', 'inertia-static.drivers.cloudflare.zone_id' => 'z', 'inertia-static.drivers.cloudflare.api_token' => 't']);

    $response = $this->get('/blog/hello-world');

    $response->assertOk()
        ->assertHeader('Cloudflare-CDN-Cache-Control', 'public, max-age=3600, stale-while-revalidate=60, stale-if-error=604800')
        ->assertHeader('Cache-Tag', 'posts,post:hello-world');
});

it('renders full SSR HTML for SEO', function () {
    $this->get('/blog/hello-world')
        ->assertOk()
        ->assertSee('<title inertia>Post hello-world</title>', false)
        ->assertSee('<h1>Post hello-world</h1>', false);
});

it('never caches degraded HTML when SSR is unavailable', function () {
    FakeSsrGateway::$available = false;

    $response = $this->get('/blog');

    $response->assertOk()->assertHeader('X-Inertia-Static', 'BYPASS; reason=ssr-unavailable');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->has('CDN-Cache-Control'))->toBeFalse();
});

it('can serve HTML without SSR when SSR is not required', function () {
    FakeSsrGateway::$available = false;
    config(['inertia-static.ssr.required' => false]);

    $this->get('/blog')->assertHeader('X-Inertia-Static', 'MISS; ttl=300');
});

it('sets a strong ETag and answers If-None-Match with an empty 304', function () {
    $first = $this->get('/blog');
    $etag = $first->headers->get('ETag');

    expect($etag)->toMatch('/^"[a-f0-9]{32}"$/');
    expect($this->get('/blog')->headers->get('ETag'))->toBe($etag);

    $second = $this->withHeaders(['If-None-Match' => $etag])->get('/blog');

    $second->assertStatus(304);
    expect($second->getContent())->toBe('')
        ->and($second->headers->get('ETag'))->toBe($etag)
        ->and($second->headers->has('CDN-Cache-Control'))->toBeTrue();
});

it('treats HEAD like GET', function () {
    $this->call('HEAD', '/blog')->assertOk()->assertHeader('X-Inertia-Static', 'MISS; ttl=300');
});

it('never caches requests carrying Authorization', function () {
    $this->withHeaders(['Authorization' => 'Bearer secret'])
        ->get('/blog')
        ->assertOk()
        ->assertHeader('X-Inertia-Static', 'BYPASS; reason=authorization')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('never caches 5xx responses', function () {
    $this->get('/broken')->assertStatus(500)->assertHeader('X-Inertia-Static', 'BYPASS; reason=server-error');
});

it('caches 404 with the short not-found TTL', function () {
    config(['inertia-static.defaults.not_found_ttl' => 30]);

    $this->get('/missing')->assertNotFound()->assertHeader('X-Inertia-Static', 'MISS; ttl=30');
});

it('does not cache redirects unless configured', function () {
    $this->get('/moved')->assertRedirect()->assertHeader('X-Inertia-Static', 'BYPASS; reason=redirect');

    config(['inertia-static.defaults.cache_redirects' => true, 'inertia-static.defaults.redirect_ttl' => 120]);

    $this->get('/moved')->assertRedirect()->assertHeader('X-Inertia-Static', 'MISS; ttl=120');
});

it('does not touch dynamic routes', function () {
    $response = $this->get('/dashboard');

    expect($response->headers->has('X-Inertia-Static'))->toBeFalse()
        ->and($response->headers->getCookies())->not->toBeEmpty();
});

it('never caches the Inertia JSON on the HTML URL', function () {
    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'])
        ->get('/blog')
        ->assertOk()
        ->assertHeader('X-Inertia-Static', 'BYPASS; reason=inertia-json-on-html-url');
});

it('never caches partial reloads', function () {
    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => 'v1',
        'X-Inertia-Partial-Component' => 'Blog/Index',
        'X-Inertia-Partial-Data' => 'title',
    ])->get('/__inertia/v1/blog.json')->assertHeader('X-Inertia-Static', 'BYPASS; reason=partial-reload');
});

it('fails loudly on deferred props in development', function () {
    $this->withoutExceptionHandling();

    $this->get('/deferred');
})->throws(InvalidStaticRoute::class, 'deferred props');

it('allows deferred props when configured', function () {
    config(['inertia-static.deferred_props' => 'allow']);

    $this->get('/deferred')->assertOk()->assertHeader('X-Inertia-Static', 'MISS; ttl=3600');
});

it('can hide the debug header', function () {
    config(['inertia-static.debug_header' => null]);

    expect($this->get('/blog')->headers->has('X-Inertia-Static'))->toBeFalse();
});

it('does nothing when disabled', function () {
    config(['inertia-static.enabled' => false]);

    expect($this->get('/blog')->headers->has('X-Inertia-Static'))->toBeFalse();
});

it('never stores per-request debug headers in the shared cache', function () {
    $response = $this->get('/devtools');

    $response->assertHeader('X-Inertia-Static', 'MISS; ttl=3600');
    expect($response->headers->has('X-Inertia-Devtools-Id'))->toBeFalse();
});

it('disables Inertia DevTools on static routes only', function () {
    config(['inertia.devtools.enabled' => true]);

    $this->get('/blog');
    expect(config('inertia.devtools.enabled'))->toBeFalse();
});

it('emits the stable cache header for CDN rules', function () {
    $this->get('/blog')->assertHeader('X-Inertia-Static-Cache', 'cacheable');
    $this->get('/broken')->assertHeader('X-Inertia-Static-Cache', 'bypass');
    $this->withHeaders(['Authorization' => 'Bearer x'])->get('/blog')->assertHeader('X-Inertia-Static-Cache', 'bypass');

    expect($this->get('/dashboard')->headers->has('X-Inertia-Static-Cache'))->toBeFalse();
});

it('can rename or disable the cache header', function () {
    config(['inertia-static.cache_header' => 'X-Edge-Cache']);
    $this->get('/blog')->assertHeader('X-Edge-Cache', 'cacheable');

    config(['inertia-static.cache_header' => null]);
    expect($this->get('/blog')->headers->has('X-Edge-Cache'))->toBeFalse();
});
