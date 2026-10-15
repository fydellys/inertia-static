<?php

use Fydellys\InertiaStatic\Contracts\TagRegistry;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Fydellys\InertiaStatic\Support\Downgrades;
use Fydellys\InertiaStatic\Support\StaticUrls;
use Fydellys\InertiaStatic\Support\UrlNormalizer;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

function kernelGet(string $uri, array $server = []): Response
{
    return app(Kernel::class)->handle(Request::create($uri, 'GET', [], [], [], $server));
}

it('never decodes the page path of the JSON URL twice', function () {
    // %2562log decodes once to %62log, which must not become "blog".
    expect(kernelGet('https://example.com/__inertia/v1/%2562log.json')->getStatusCode())->toBeIn([301, 404]);

    $response = kernelGet('https://example.com/__inertia/v1/%2562log.json');

    if ($response->getStatusCode() === 301) {
        expect($response->headers->get('Location'))->not->toContain('/blog.json');
    }
});

it('redirects every spelling of a path to one percent-encoded URL', function () {
    $response = kernelGet('https://example.com/blog/x%21y%2a');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('https://example.com/blog/x!y%2A');

    expect(kernelGet('https://example.com/blog/@x!y%2A')->getStatusCode())->toBe(200)
        ->and(kernelGet('https://example.com/blog/a%20b')->getStatusCode())->toBe(200);
});

it('records one registry entry per page, whatever the spelling', function () {
    config(['inertia-static.registry.driver' => 'cache']);

    $this->get('/blog/x%20y')->assertOk();
    $this->get('/__inertia/v1/blog/x%20y.json')->assertOk();

    expect(app(TagRegistry::class)->urlsFor(['post:x y']))->toBe(['/blog/x%20y']);
});

it('purges the encoded HTML and JSON URLs of a page', function () {
    expect(app(StaticUrls::class)->variants(['/blog/x y']))->toBe([
        'https://example.com/blog/x%20y',
        'https://example.com/__inertia/v1/blog/x%20y.json',
    ]);
});

it('keeps the client IP in the page JSON render (per-client rate limits)', function () {
    $first = kernelGet('https://example.com/__inertia/v1/client-ip.json', ['REMOTE_ADDR' => '10.0.0.1']);
    kernelGet('https://example.com/__inertia/v1/client-ip.json', ['REMOTE_ADDR' => '10.0.0.1']);
    $limited = kernelGet('https://example.com/__inertia/v1/client-ip.json', ['REMOTE_ADDR' => '10.0.0.1']);
    $other = kernelGet('https://example.com/__inertia/v1/client-ip.json', ['REMOTE_ADDR' => '10.0.0.2']);

    expect(json_decode((string) $first->getContent(), true)['props']['ip'])->toBe('10.0.0.1')
        ->and($limited->getStatusCode())->toBe(429)
        ->and($other->getStatusCode())->toBe(200);
});

it('ignores Inertia protocol headers on a static HTML request', function () {
    $this->get('/inertia-headers', ['X-Inertia-Except-Once-Props' => 'once'])
        ->assertOk()
        ->assertHeader('X-Inertia-Static', 'MISS')
        ->assertViewHas('page', fn (array $page) => $page['props']['once'] === null);
});

it('renders static pages with the base URL scheme, whatever X-Forwarded-Proto says', function () {
    $this->get('/__inertia/v1/inertia-headers.json', ['X-Forwarded-Proto' => 'http'])
        ->assertOk()->assertJsonPath('props.scheme', 'https');
});

it('never caches a redirect on the page JSON URL', function () {
    $this->get('/__inertia/v1/moved.json')->assertRedirect()->assertHeader('Cache-Control', 'no-store, private');
});

it('never caches a JSON 404 representation of an HTML URL', function () {
    $this->get('/missing', ['Accept' => 'application/json'])->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/missing')->assertNotFound()->assertHeader('X-Inertia-Static', 'MISS');
});

it('does not turn a route dynamic when a handled exception is reported', function () {
    config(['inertia-static.leak_detector.mode' => 'auto']);

    $this->get('/rescued')->assertOk()->assertHeader('X-Inertia-Static', 'MISS');

    expect(app(Downgrades::class)->has($this->router()->getRoutes()->match(Request::create('/rescued'))))->toBeFalse();
});

it('still serves the page when the tag registry fails', function () {
    $registry = Mockery::mock(TagRegistry::class);
    $registry->shouldReceive('record')->andThrow(new RuntimeException('lock timeout'));
    app()->instance(TagRegistry::class, $registry);

    $this->get('/blog/hello-world')->assertOk()->assertHeader('X-Inertia-Static', 'MISS');
});

describe('tag registry', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        $this->purgeStatus = 200;
        $this->duringPurge = fn () => null;
        Http::fake(function () {
            ($this->duringPurge)();

            return Http::response('', $this->purgeStatus);
        });

        config([
            'inertia-static.cdn' => 'nginx',
            'inertia-static.drivers.nginx' => ['driver' => 'nginx', 'hosts' => ['http://nginx'], 'purge_path' => '/purge'],
            'inertia-static.registry.max_urls_per_tag' => 2,
        ]);
    });

    dataset('registries', [
        'cache' => ['cache'],
        'database' => ['database'],
    ]);

    function registry(string $driver): TagRegistry
    {
        config(['inertia-static.registry.driver' => $driver]);

        if ($driver === 'database') {
            (include __DIR__.'/../../database/migrations/create_inertia_static_tags_table.php.stub')->up();
        }

        return app(TagRegistry::class);
    }

    it('stops recording URLs for a tag at max_urls_per_tag', function (string $driver) {
        $registry = registry($driver);

        $registry->record(['posts'], ['/a']);
        $registry->record(['posts'], ['/b']);
        $registry->record(['posts'], ['/c']);
        $registry->record(['posts'], ['/d']);
        $registry->record(['posts'], ['/a']);

        expect($registry->urlsFor(['posts']))->toEqualCanonicalizing(['/a', '/b', TagRegistry::OVERFLOW])
            ->and($registry->urlsWithPrefix(['/']))->toEqualCanonicalizing(['/a', '/b']);
    })->with('registries');

    it('purges everything when a tag overflowed and the CDN has no tag purge', function (string $driver) {
        Log::spy();
        $registry = registry($driver);
        $registry->record(['posts'], ['/a', '/b', '/c']);

        $urls = StaticCache::purgeNow(PurgeOperation::Tags, ['posts']);

        Http::assertSent(fn ($r) => $r->url() === 'http://nginx/purge/*');
        expect($urls)->toContain('https://example.com/a')
            ->and($registry->urlsFor(['posts']))->toBe([]);
    })->with('registries');

    it('forgets the tags before purging, so a page recorded during the purge stays known', function (string $driver) {
        $registry = registry($driver);
        $registry->record(['posts'], ['/a']);

        $this->duringPurge = fn () => $registry->record(['posts'], ['/a']);

        StaticCache::purgeNow(PurgeOperation::Tags, ['posts']);

        expect($registry->urlsFor(['posts']))->toBe(['/a']);
    })->with('registries');

    it('keeps the pages known when the purge fails', function (string $driver) {
        $registry = registry($driver);
        $registry->record(['posts'], ['/a']);
        $this->purgeStatus = 403;

        expect(fn () => StaticCache::purgeNow(PurgeOperation::Tags, ['posts']))->toThrow(RequestException::class);

        expect($registry->urlsFor(['posts']))->toBe(['/a']);
    })->with('registries');
});

it('spells paths the way the JS client does', function (string $path, string $canonical) {
    expect(UrlNormalizer::encodePath($path))->toBe($canonical);
})->with([
    ['/blog/a b', '/blog/a%20b'],
    ['/blog/%68ello', '/blog/hello'],
    ["/@user/x!$&'()+,;=:", "/@user/x!$&'()+,;=:"],
    ['/x*|^', '/x%2A%7C%5E'],
    ['/%e3%81%82', '/%E3%81%82'],
    ['/%2562log', '/%2562log'],
    ['/a%zz', '/a%25zz'],
]);
