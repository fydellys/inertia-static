<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

it('removes tracking and non-whitelisted query parameters with a cacheable 301', function () {
    $this->get('/blog?utm_source=x&page=2&foo=bar&fbclid=1')
        ->assertStatus(301)
        ->assertRedirect('https://example.com/blog?page=2')
        ->assertHeader('X-Inertia-Static', 'MISS; ttl=300');
});

it('sorts query parameters', function () {
    config(['inertia-static.normalization.enabled' => true]);
    $this->router()->getRoutes()->getByName('blog.index')->isr(query: ['page', 'tag']);

    $this->get('/blog?tag=php&page=2')->assertRedirect('https://example.com/blog?page=2&tag=php');
    $this->get('/blog?page=2&tag=php')->assertOk();
});

it('redirects the trailing slash to the canonical form', function () {
    // The test client trims trailing slashes, so hit the kernel directly.
    $response = app(Kernel::class)
        ->handle(Request::create('https://example.com/blog/'));

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('https://example.com/blog');
});

it('supports the "always" trailing slash policy', function () {
    config(['inertia-static.normalization.trailing_slash' => 'always']);

    $this->get('/blog')->assertStatus(301)->assertRedirect('https://example.com/blog/');
});

it('builds absolute URLs from the base URL, never from forwarded headers', function () {
    $this->withHeaders(['X-Forwarded-Host' => 'evil.test', 'Host' => 'evil.test'])
        ->get('http://evil.test/blog?utm_source=x')
        ->assertRedirect('https://example.com/blog');
});

it('leaves canonical URLs alone', function () {
    $this->get('/blog?page=2')->assertOk();
});

it('canonicalizes the query of the cacheable page JSON URL too (one cache entry per page)', function () {
    $this->get('/__inertia/v1/blog.json?utm_source=x&page=2&foo=bar')
        ->assertStatus(301)
        ->assertRedirect('https://example.com/__inertia/v1/blog.json?page=2')
        ->assertHeader('X-Inertia-Static-Cache', 'cacheable');

    $this->get('/__inertia/v1/blog.json?page=2')->assertOk();
    $this->get('/__inertia/v1/blog/hello-world.json?ref=x')->assertRedirect('https://example.com/__inertia/v1/blog/hello-world.json');
});

it('does not canonicalize the never-cached live endpoint', function () {
    $this->get('/__inertia/live/v1/blog.json?utm_source=x&page=2')->assertOk();
});
