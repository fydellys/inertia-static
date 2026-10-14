<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

it('serves the page JSON on its own URL with the Inertia headers', function () {
    $response = $this->get('/__inertia/v1/blog/hello-world.json');

    $response->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertHeader('X-Inertia-Static', 'MISS; ttl=3600')
        ->assertJsonPath('component', 'Blog/Show')
        ->assertJsonPath('url', '/blog/hello-world')
        ->assertJsonPath('version', 'v1')
        ->assertJsonPath('props.title', 'Post hello-world');

    expect($response->headers->getCookies())->toBeEmpty()
        ->and((string) $response->headers->get('Vary'))->not->toContain('X-Inertia');
});

it('keeps Vary: X-Inertia on the HTML URL', function () {
    expect($this->get('/blog')->headers->get('Vary'))->toContain('X-Inertia');
});

it('maps index.json to the root page', function () {
    $this->get('/__inertia/v1/index.json')->assertOk()->assertJsonPath('component', 'Home');
});

it('keeps whitelisted query parameters', function () {
    $this->get('/__inertia/v1/blog.json?page=2')->assertOk()->assertJsonPath('props.page', '2');
});

it('answers a version mismatch with 409 + X-Inertia-Location and never caches it', function () {
    $response = $this->get('/__inertia/old-version/blog.json');

    $response->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://example.com/blog')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('returns 404 for dynamic routes so private pages are never exposed', function () {
    $this->get('/__inertia/v1/dashboard.json')->assertNotFound();
});

it('returns 404 for unknown pages and non-json paths', function () {
    $this->get('/__inertia/v1/nope.json')->assertNotFound();
    $this->get('/__inertia/v1/blog')->assertNotFound();
});

it('serves the client manifest', function () {
    $response = $this->get('/__inertia/manifest.json');

    $response->assertOk()->assertJsonPath('prefix', '/__inertia');

    $patterns = collect($response->json('routes'))->pluck('pattern');

    expect($patterns)->toContain('^/$', '^/blog/?$', '^/blog/([^/]+)/?$')
        ->not->toContain('^/dashboard/?$');
});

it('serves never-cached page JSON on the live endpoint', function () {
    $response = $this->get('/__inertia/live/v1/blog.json?page=2');

    $response->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Inertia-Static', 'BYPASS; reason=live')
        ->assertHeader('X-Inertia-Static-Cache', 'bypass')
        ->assertJsonPath('component', 'Blog/Index')
        ->assertJsonPath('url', '/blog?page=2');

    expect($response->headers->getCookies())->toBeEmpty();
});

it('forwards partial reload headers on the live endpoint', function () {
    $response = $this->withHeaders([
        'X-Inertia-Partial-Component' => 'Blog/Index',
        'X-Inertia-Partial-Data' => 'page',
    ])->get('/__inertia/live/v1/blog.json?page=3');

    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    expect(array_keys($response->json('props')))->toContain('page')->not->toContain('title');
});

it('keeps the live endpoint private: 404 for dynamic routes, 409 on version mismatch', function () {
    $this->get('/__inertia/live/v1/dashboard.json')->assertNotFound();
    $this->get('/__inertia/live/old/blog.json')->assertStatus(409)->assertHeader('Cache-Control', 'no-store, private');
});

it('sends Inertia redirects to a static page to its live JSON URL (return back() after a form)', function () {
    Route::middleware('web')->post('/subscribe', fn () => back())->withoutMiddleware(PreventRequestForgery::class);

    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1', 'Referer' => 'https://example.com/blog?page=2'])
        ->post('/subscribe')
        ->assertRedirect('https://example.com/__inertia/live/v1/blog.json?page=2');
});

it('leaves other redirects alone', function () {
    Route::middleware('web')->post('/subscribe', fn () => back())->withoutMiddleware(PreventRequestForgery::class);
    Route::middleware('web')->post('/go-dashboard', fn () => redirect('/dashboard'))->withoutMiddleware(PreventRequestForgery::class);

    // not an Inertia visit: a classic form post keeps the HTML URL
    $this->withHeaders(['Referer' => 'https://example.com/blog'])->post('/subscribe')->assertRedirect('https://example.com/blog');

    // dynamic page: normal Inertia flow
    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'])->post('/go-dashboard')->assertRedirect('/dashboard');
});

it('hands session validation errors and Inertia flash data to the live endpoint only', function () {
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['The email is invalid.']]));

    $this->withSession(['errors' => $errors, 'inertia.flash_data' => ['message' => 'Thanks!']])
        ->get('/__inertia/live/v1/blog.json')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('props.errors.email', 'The email is invalid.')
        ->assertJsonPath('flash.message', 'Thanks!');

    // the cacheable endpoint never reads the session
    $this->withSession(['errors' => $errors])
        ->get('/__inertia/v1/blog.json')
        ->assertOk()
        ->assertJsonPath('props.errors', []);
});
